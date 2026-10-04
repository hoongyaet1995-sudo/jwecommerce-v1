<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManagedSocialAccount;
use App\Services\Social\FacebookAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class FacebookAccountController extends Controller
{
    public function __construct(private FacebookAccountService $facebook) {}
    private function requireManager(Request $request): void {abort_unless($request->user()?->is_super_admin,403);}
    private function result(string $status){return redirect('/admin/social/facebook?facebook='.$status)->header('Referrer-Policy','no-referrer');}
    public function authorize(Request $request)
    {
        $this->requireManager($request);
        $request->session()->forget(['facebook_oauth','facebook_pending']);
        if(!$this->facebook->configured())return $this->result('not_configured');
        $state=Str::random(64);
        $request->session()->put('facebook_oauth',['state'=>$state,'actor'=>$request->user()->id,
            'merchant'=>$this->facebook->merchant(),'app'=>(string)config('services.facebook.client_id'),
            'redirect'=>config('services.facebook.redirect'),'expires'=>now()->addMinutes(10)->timestamp]);
        return redirect()->away($this->facebook->authorizeUrl($state))->header('Cache-Control','no-store');
    }
    public function callback(Request $request)
    {
        $this->requireManager($request);$state=$request->session()->pull('facebook_oauth');
        if(!is_array($state)||!is_string($request->query('state'))||!hash_equals($state['state'],$request->query('state'))
            ||$state['actor']!==$request->user()->id||$state['merchant']!==$this->facebook->merchant()
            ||$state['app']!==(string)config('services.facebook.client_id')||$state['redirect']!==config('services.facebook.redirect')
            ||$state['expires']<now()->timestamp)return $this->result('invalid_state');
        if($request->query('error')||!is_string($request->query('code'))||strlen($request->query('code'))>4096)return $this->result('cancelled');
        try {
            $token=$this->facebook->exchange($request->query('code'));
            $permissions=$this->facebook->permissions($token);
            if(!in_array('pages_show_list',$permissions,true))return $this->result('permission_required');
            $pages=$this->facebook->pages($token);
            $request->session()->put('facebook_pending',Crypt::encryptString(json_encode([
                'token'=>$token,'actor'=>$request->user()->id,'merchant'=>$state['merchant'],'app'=>$state['app'],
                'expires'=>now()->addMinutes(10)->timestamp,'pages'=>$pages,'permissions'=>$permissions],JSON_THROW_ON_ERROR)));
            return $this->result($pages?'select':(in_array('business_management',$permissions,true)?'no_pages':'business_permission_required'));
        } catch(\Throwable $e){return $this->result('provider_error');}
    }
    private function pending(Request $request): ?array
    {
        try {$raw=$request->session()->get('facebook_pending');$p=$raw?json_decode(Crypt::decryptString($raw),true,512,JSON_THROW_ON_ERROR):null;
            if(is_array($p)&&$p['actor']===$request->user()->id&&$p['merchant']===$this->facebook->merchant()
                &&$p['app']===(string)config('services.facebook.client_id')&&$p['expires']>=now()->timestamp)return $p;
        }catch(\Throwable $e){}
        $request->session()->forget('facebook_pending');return null;
    }
    public function index(Request $request)
    {
        $pending=$this->pending($request);
        $accounts=ManagedSocialAccount::where('merchant_key',$this->facebook->merchant())->where('platform','facebook')->get();
        return response()->json(['configured'=>$this->facebook->configured(),'canManage'=>(bool)$request->user()->is_super_admin,
            'pages'=>array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name']],$pending['pages']??[]),
            'accounts'=>$accounts->map(fn($a)=>$this->facebook->check($a))])->header('Cache-Control','no-store');
    }
    public function select(Request $request)
    {
        $this->requireManager($request);$data=$request->validate(['page_id'=>['required','string','max:100']]);
        $pending=$this->pending($request);abort_unless($pending,422,'Authorization expired. Connect Facebook again.');
        abort_unless(collect($pending['pages'])->contains('id',$data['page_id']),422,'Choose an authorized Page.');
        try {
            $permissions=$this->facebook->permissions($pending['token']);
            abort_unless(in_array('pages_show_list',$permissions,true),422,'Page permission was not granted.');
            $page=collect($this->facebook->pages($pending['token']))->firstWhere('id',$data['page_id']);
            abort_unless($page,422,'Page authorization is no longer available.');
            $account=ManagedSocialAccount::updateOrCreate(['merchant_key'=>$this->facebook->merchant(),'platform'=>'facebook','provider_account_id'=>$page['id']],
                ['app_id'=>config('services.facebook.client_id'),'name'=>mb_substr($page['name'],0,255),'access_token'=>$page['access_token'],
                    'permissions'=>$permissions,'tasks'=>$page['tasks']??[],'authorized_by'=>$request->user()->id,'state'=>'unknown']);
            $request->session()->forget('facebook_pending');
            return $this->index($request);
        }catch(\RuntimeException $e){return response()->json(['message'=>'Meta could not verify the connection. Try again.'],502);}
    }
}
