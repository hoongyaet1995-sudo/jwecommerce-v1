<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManagedSocialAccount;
use App\Services\Social\WhatsAppAccountService;
use App\Services\Social\SocialHubOAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WhatsAppAccountController extends Controller
{
 protected string $platform='whatsapp';
    private function requireManager(Request $request): void {abort_unless($request->user()?->is_super_admin,403);}
    private function result(string $status){$r=request();$intent=$r->session()->get('whatsapp_marketing_oauth');if(is_array($intent)&&($intent['actor']??null)===$r->user()?->id&&($intent['platform']??null)==='whatsapp'&&($intent['expires']??0)>=now()->timestamp)return redirect('/admin/marketing/channels/whatsapp?result='.urlencode($status))->header('Referrer-Policy','no-referrer');return redirect('/admin/social/'.$this->platform.'?'.$this->platform.'='.$status)->header('Referrer-Policy','no-referrer');}
    public function authorize(Request $request)
    {
        $this->requireManager($request);
        $request->session()->forget([$this->platform.'_oauth',$this->platform.'_pending']);
        if(!app(WhatsAppAccountService::class)->configured())return $this->result('not_configured');
        $state=Str::random(64);
        $hub=app(SocialHubOAuth::class);
        try{$grant=$hub->start($state,$this->platform);$fingerprint=$hub->fingerprint();}
        catch(\RuntimeException $e){return $this->result($e->getMessage());}
        $request->session()->put($this->platform.'_oauth',['state'=>$state,'actor'=>$request->user()->id,
            'merchant'=>app(WhatsAppAccountService::class)->merchant(),'app'=>(string)config('services.whatsapp.app_id'),
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
        try{$fingerprint=app(SocialHubOAuth::class)->fingerprint();}catch(\RuntimeException){return $this->result('invalid_state');}
        if(!is_array($state)||!is_string($request->query('state'))||!hash_equals($state['state'],$request->query('state'))
            ||$state['actor']!==$request->user()->id||$state['merchant']!==app(WhatsAppAccountService::class)->merchant()
            ||$state['app']!==(string)config('services.whatsapp.app_id')||($state['installation']??null)!==$fingerprint
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
    protected function pending(Request $request): ?array
    {
        try {$raw=$request->session()->get($this->platform.'_pending');$p=$raw?json_decode(Crypt::decryptString($raw),true,512,JSON_THROW_ON_ERROR):null;
            if(is_array($p)&&$p['actor']===$request->user()->id&&$p['merchant']===app(WhatsAppAccountService::class)->merchant()
                &&$p['app']===(string)config('services.whatsapp.app_id')&&($p['installation']??null)===app(SocialHubOAuth::class)->fingerprint()&&$p['expires']>=now()->timestamp)return $p;
        }catch(\Throwable $e){}
        $request->session()->forget($this->platform.'_pending');return null;
    }

 public function index(Request $r,WhatsAppAccountService $accounts){
  return response()->json(['configured'=>$accounts->configured(),'canManage'=>(bool)$r->user()?->is_super_admin,'pages'=>$this->pending($r)['pages']??[],
   'accounts'=>array_map(fn($a)=>$this->presentation($a),$accounts->list())]+app(SocialHubOAuth::class)->permitted('whatsapp'))->header('Cache-Control','no-store');
 }
 private function presentation(array $a):array {
  return ['id'=>(string)$a['id'],'pageId'=>$a['provider_account_id'],'wabaId'=>$a['waba_id'],'name'=>$a['name'],'state'=>$a['state'],
   'checkedAt'=>$a['checked_at'],'permissions'=>$a['permissions'],'messagingReady'=>$a['messaging_ready'],'connectionMethod'=>$a['connection_method'],'displayPhoneNumber'=>$a['display_phone_number'],
   'inboundEnabled'=>$a['inbound_enabled'],'inboundVerifiedAt'=>$a['inbound_verified_at'],'inboundRoutingReady'=>$a['inbound_routing_ready'],
   'messagingError'=>$a['messaging_ready']?null:($a['messaging_error']?:'Existing sender configuration requires verified Hub routing and message subscription.')];
 }

 public function select(Request $r,WhatsAppAccountService $accounts){
  $this->requireManager($r);$d=$r->validate(['page_ids'=>'required|array|min:1|max:100','page_ids.*'=>'required|string|distinct|regex:/^[0-9]{1,100}$/D']);
  $pending=$this->pending($r);abort_unless($pending,422,'Authorization expired. Connect WhatsApp again.');
  foreach($d['page_ids'] as $id)abort_unless(collect($pending['pages'])->contains('id',$id),422,'Choose an authorized WhatsApp number.');
  try{$grant=app(SocialHubOAuth::class)->call('select',['transaction_id'=>$pending['transaction'],'page_ids'=>$d['page_ids']],'whatsapp');
   $pages=$grant['pages']??[];abort_unless(is_array($pages)&&count($pages)===count($d['page_ids']),502,'Invalid authorization receipt.');$seen=[];
   foreach($pages as $page){abort_unless(is_array($page)&&in_array($page['id']??null,$d['page_ids'],true)&&!in_array($page['id'],$seen,true)&&is_string($page['waba_id']??null)&&is_string($page['access_token']??null),502,'Invalid authorization receipt.');$seen[]=$page['id'];}
   foreach($pages as $page){$account=$accounts->importLegacy(['app_id'=>$grant['app_id'],'waba_id'=>$page['waba_id'],'phone_number_id'=>$page['id'],'access_token'=>$page['access_token']],$r->user());$account->forceFill(['connection_method'=>'embedded_signup'])->save();app(\App\Services\Social\WhatsAppSubscriptionService::class)->activate($account->fresh());}
   $r->session()->forget('whatsapp_pending');return $this->index($r,$accounts);
  }catch(\RuntimeException){return response()->json(['message'=>'Could not complete WhatsApp authorization. Reconnect and try again.'],409);}
 }
 public function manage(Request $r,string $id,string $action,WhatsAppAccountService $accounts){
  abort_unless($r->user()?->is_super_admin,403);abort_unless(in_array($action,['check','disconnect'],true),404);
  $account=ManagedSocialAccount::where('merchant_key',$accounts->merchant())->where('platform','whatsapp')->findOrFail($id);
  if($action==='check'){
   try{$accounts->check($account);app(\App\Services\Social\WhatsAppSubscriptionService::class)->activate($account->fresh());return response()->json(['account'=>$this->presentation($accounts->summary($account->fresh()))])->header('Cache-Control','no-store');}
   catch(\RuntimeException $e){$state=in_array($e->getMessage(),['authorization_expired','permission_required'],true)?$e->getMessage():'unknown';$account->forceFill(['state'=>$state,'inbound_enabled'=>false,'inbound_verified_at'=>null,'checked_at'=>now()])->save();app(\App\Services\Social\FacebookAccountService::class)->invalidateInbox($account);return response()->json(['message'=>match($e->getMessage()){'authorization_expired'=>'WhatsApp authorization expired. Reconnect this account.','permission_required'=>'WhatsApp messaging permission is required.',default=>'Could not verify the WhatsApp connection.'}],409);}
  }
  DB::transaction(function()use($account){$locked=ManagedSocialAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();$locked->update(['state'=>'disconnected','access_token'=>'','inbound_enabled'=>false,'inbound_verified_at'=>null]);});
  return $this->index($r,$accounts);
 }
 public function destroy(Request $r,string $id,WhatsAppAccountService $accounts){
  abort_unless($r->user()?->is_super_admin,403);
  DB::transaction(function()use($id,$accounts){$account=ManagedSocialAccount::where('merchant_key',$accounts->merchant())->where('platform','whatsapp')->lockForUpdate()->findOrFail($id);abort_unless($account->state==='disconnected',409,'Disconnect the WhatsApp account before removing it.');$account->delete();});
  return $this->index($r,$accounts);
 }
}
