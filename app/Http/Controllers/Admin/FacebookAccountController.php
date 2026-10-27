<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManagedSocialAccount;
use App\Services\Social\FacebookAccountService;
use App\Services\Social\SocialHubOAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class FacebookAccountController extends Controller
{
    protected string $platform='facebook';
    public function __construct(private FacebookAccountService $facebook) {}
    private function requireManager(Request $request): void {abort_unless($request->user()?->is_super_admin,403);}
    private function result(string $status){
        $r=request();$intent=$r->session()->get($this->platform.'_marketing_oauth');
        if(is_array($intent)&&($intent['actor']??null)===$r->user()?->id&&($intent['platform']??null)===$this->platform&&($intent['expires']??0)>=now()->timestamp){
            if($status!=='select')$r->session()->forget($this->platform.'_marketing_oauth');
            return redirect('/admin/marketing/channels?'.http_build_query(['platform'=>$this->platform,'result'=>$status]))->header('Referrer-Policy','no-referrer')->header('Cache-Control','no-store');
        }
        return redirect('/admin/social/'.$this->platform.'?'.$this->platform.'='.$status)->header('Referrer-Policy','no-referrer');
    }
    public function authorize(Request $request)
    {
        $this->requireManager($request);
        if(!$request->routeIs('marketing.channels.authorize'))$request->session()->forget($this->platform.'_marketing_oauth');
        $request->session()->forget([$this->platform.'_oauth',$this->platform.'_pending']);
        if(!$this->facebook->configured())return $this->result('not_configured');
        $state=Str::random(64);
        $hub=app(SocialHubOAuth::class);
        try{$grant=$hub->start($state,$this->platform);$fingerprint=$hub->fingerprint();}
        catch(\RuntimeException $e){return $this->result($e->getMessage());}
        $request->session()->put($this->platform.'_oauth',['state'=>$state,'actor'=>$request->user()->id,
            'merchant'=>$this->facebook->merchant(),'app'=>(string)config('services.facebook.client_id'),
            'installation'=>$fingerprint,'transaction'=>$grant['transaction_id'],'expires'=>now()->addMinutes(10)->timestamp]);
        return redirect()->away($grant['launch_url'])->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
    public function callback(Request $request)
    {
        // Retired direct Meta callback cannot bypass Hub domain permission.
        $this->requireManager($request);
        return $this->result('invalid_state');
    }
    public function hubReturn(Request $request)
    {
        $this->requireManager($request);$state=$request->session()->pull($this->platform.'_oauth');
        if($request->session()->has($this->platform.'_marketing_oauth')&&!app(\App\Services\Marketing\MarketingChannels::class)->ready($request)['ready'])return $this->result('invalid_state');
        try{$fingerprint=app(SocialHubOAuth::class)->fingerprint();}catch(\RuntimeException){return $this->result('invalid_state');}
        if(!is_array($state)||!is_string($request->query('state'))||!hash_equals($state['state'],$request->query('state'))
            ||$state['actor']!==$request->user()->id||$state['merchant']!==$this->facebook->merchant()
            ||$state['app']!==(string)config('services.facebook.client_id')||($state['installation']??null)!==$fingerprint
            ||($state['transaction']??null)!==$request->query('transaction')
            ||$state['expires']<now()->timestamp)return $this->result('invalid_state');
        $handoff=$request->query('handoff');if(!is_string($handoff)||!preg_match('/^[a-f0-9]{64}$/D',$handoff))return $this->result('invalid_state');
        try {
            $result=app(SocialHubOAuth::class)->call('redeem',['transaction_id'=>$state['transaction'],'handoff'=>$handoff],$this->platform);
            if(($result['result']??null)!=='select')return $this->result(in_array($result['result']??'',[
                'cancelled','no_pages','permission_required','business_permission_required','authorization_expired','provider_error'],true)?$result['result']:'provider_error');
            $pages=$result['pages']??[];
            if(!is_array($pages)||!$pages||count(array_filter($pages,fn($p)=>is_array($p)&&is_string($p['id']??null)&&is_string($p['name']??null)))!==count($pages))return $this->result('hub_unavailable');
            $request->session()->put($this->platform.'_pending',Crypt::encryptString(json_encode([
                'actor'=>$request->user()->id,'merchant'=>$state['merchant'],'app'=>$state['app'],'transaction'=>$state['transaction'],
                'installation'=>$fingerprint,'expires'=>now()->addMinutes(5)->timestamp,'pages'=>$pages],JSON_THROW_ON_ERROR)));
            return $this->result('select');
        } catch(\RuntimeException $e){return $this->result($e->getMessage());}
        catch(\Throwable){return $this->result('hub_unavailable');}
    }
    private function pending(Request $request): ?array
    {
        try {$raw=$request->session()->get($this->platform.'_pending');$p=$raw?json_decode(Crypt::decryptString($raw),true,512,JSON_THROW_ON_ERROR):null;
            if(is_array($p)&&$p['actor']===$request->user()->id&&$p['merchant']===$this->facebook->merchant()
                &&$p['app']===(string)config('services.facebook.client_id')&&($p['installation']??null)===app(SocialHubOAuth::class)->fingerprint()&&$p['expires']>=now()->timestamp)return $p;
        }catch(\Throwable $e){}
        $request->session()->forget($this->platform.'_pending');return null;
    }
    public function index(Request $request)
    {
        $pending=$this->pending($request);
        $accounts=ManagedSocialAccount::where('merchant_key',$this->facebook->merchant())->where('platform',$this->platform)->get();
        return response()->json(['configured'=>$this->facebook->configured(),'canManage'=>(bool)$request->user()->is_super_admin,
            'pages'=>array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name']],$pending['pages']??[]),
            'accounts'=>$accounts->map(fn($a)=>$this->facebook->check($a))])->header('Cache-Control','no-store');
    }
    private function ownedAccount(string $id): ManagedSocialAccount
    {
        return ManagedSocialAccount::where('merchant_key',$this->facebook->merchant())->where('platform',$this->platform)->findOrFail($id);
    }
    public function check(Request $request,string $id)
    {
        $account=$this->ownedAccount($id);
        return response()->json(['account'=>$this->facebook->check($account,(bool)$request->user()->is_super_admin)])->header('Cache-Control','no-store');
    }
    public function disconnect(Request $request,string $id)
    {
        $this->requireManager($request);$account=$this->ownedAccount($id);
        $values=['access_token'=>'','state'=>'disconnected','checked_at'=>now()];
        if(array_key_exists('inbound_enabled',$account->getAttributes()))$values+=['inbound_enabled'=>false,'inbound_verified_at'=>null];
        $account->update($values);
        $this->facebook->invalidateInbox($account);
        $request->session()->forget($this->platform.'_pending');
        return $this->index($request);
    }
    public function destroy(Request $request,string $id)
    {
        $this->requireManager($request);$account=$this->ownedAccount($id);
        abort_unless($account->state==='disconnected',422,'Disconnect this Page before deleting its saved connection.');
        $account->delete();return $this->index($request);
    }
    public function inbound(Request $request,string $id)
    {
        $this->requireManager($request);$account=$this->ownedAccount($id);
        try {app(\App\Services\Social\MessengerSubscriptionService::class)->enable($account);}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){throw $e;}
        catch(\RuntimeException $e){return response()->json(['message'=>$e->getMessage()],502);}
        return response()->json(['account'=>$this->facebook->summary($account->fresh())])->header('Cache-Control','no-store');
    }
    public function select(Request $request)
    {
        $this->requireManager($request);$data=$request->validate([
            'page_id'=>['required_without:page_ids',\Illuminate\Validation\Rule::prohibitedIf($request->has('page_ids')),'string','max:100'],
            'page_ids'=>['required_without:page_id','array','min:1','max:100'],
            'page_ids.*'=>['required','string','distinct','max:100']]);
        $ids=$data['page_ids']??[$data['page_id']];
        $pending=$this->pending($request);abort_unless($pending,422,'Authorization expired. Connect Facebook again.');
        foreach($ids as $id)abort_unless(collect($pending['pages'])->contains('id',$id),422,'Choose an authorized Page.');
        try {
            $grant=app(SocialHubOAuth::class)->call('select',['transaction_id'=>$pending['transaction']]+(isset($data['page_ids'])?['page_ids'=>$ids]:['page_id'=>$ids[0]]),$this->platform);
            $pages=$grant['pages']??(isset($grant['page'])?[$grant['page']]:[]);$permissions=$grant['permissions']??null;
            abort_unless(is_array($pages)&&count($pages)===count($ids)&&is_array($permissions)
                &&in_array($this->platform==='instagram'?'instagram_manage_messages':'pages_show_list',$permissions,true)&&count(array_filter($permissions,'is_string'))===count($permissions),502,'The authorization receipt could not be verified.');
            $returned=[];
            foreach($pages as $page){
                abort_unless(is_array($page)&&is_string($page['id']??null)&&in_array($page['id'],$ids,true)
                    &&!in_array($page['id'],$returned,true)&&is_string($page['name']??null)
                    &&is_string($page['access_token']??null)&&$page['access_token']!=='',502,'The authorization receipt could not be verified.');
                if($this->platform==='instagram')abort_unless(is_string($page['facebook_page_id']??null)&&preg_match('/^[0-9]{1,100}$/D',$page['facebook_page_id']),502,'The linked Page could not be verified.');
                $returned[]=$page['id'];
            }
            $accounts=\Illuminate\Support\Facades\DB::transaction(function()use($pages,$permissions,$request){
                $accounts=[];
                foreach($pages as $page)$accounts[]=ManagedSocialAccount::updateOrCreate(['merchant_key'=>$this->facebook->merchant(),'platform'=>$this->platform,'provider_account_id'=>$page['id']],
                    ['app_id'=>config('services.facebook.client_id'),'name'=>mb_substr($page['name'],0,255),'access_token'=>$page['access_token'],
                        'permissions'=>$permissions,'tasks'=>$page['tasks']??[],'authorized_by'=>$request->user()->id,'state'=>'unknown','inbound_enabled'=>false,'inbound_verified_at'=>null,'messaging_error'=>null,...($this->platform==='instagram'?['linked_page_id'=>$page['facebook_page_id']]:[])]);
                return $accounts;
            });
            foreach($accounts as $account){
                $this->facebook->check($account);
                app(\App\Services\Social\MessengerSubscriptionService::class)->activate($account->fresh());
            }
            $request->session()->forget($this->platform.'_pending');
            return $this->index($request);
        }catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){throw $e;}
        catch(\RuntimeException $e){return response()->json(['message'=>match($e->getMessage()){
            'hub_not_permitted'=>'Social Hub has not permitted OAuth for this website. Ask your administrator to enable Facebook authorization.',
            'invalid_state'=>'Authorization expired or was already used. Connect Facebook again.',
            default=>'Social Hub could not complete authorization. Connect Facebook again.',
        }],$e->getMessage()==='hub_not_permitted'?403:502);}
    }
}
