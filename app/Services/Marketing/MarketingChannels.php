<?php
namespace App\Services\Marketing;
use App\Models\MarketingChannelConnection;
use App\Services\Social\{SocialHubOAuth,FacebookAccountService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt,DB,Http};
class MarketingChannels {
 public function merchant():string{return app(FacebookAccountService::class)->merchant();}
 public function ready(Request $r):array{
  if(!app(FacebookAccountService::class)->configured())return ['ready'=>false,'reason'=>'Meta app configuration is incomplete.'];
  if(!$r->isSecure()&&!(app()->environment('local','testing')&&in_array($r->getHost(),['127.0.0.1','localhost','::1'],true)))return ['ready'=>false,'reason'=>'Public client websites must use HTTPS for authorization.'];
  try{$hub=app(SocialHubOAuth::class);$hub->fingerprint();$domain=$hub->installation()->registered_domain;$host=parse_url(str_contains($domain,'://')?$domain:'https://'.$domain,PHP_URL_HOST);if(strtolower((string)$host)!==strtolower($r->getHost()))return ['ready'=>false,'reason'=>'The registered Social Hub domain does not match this website. Review the installation settings; no alternative callback will be used.'];return ['ready'=>true,'reason'=>null];}catch(\Throwable){return ['ready'=>false,'reason'=>'Social Hub installation or website access is not ready. Contact your administrator.'];}
 }
 public function pending(Request $r,string $platform):?array{
  try{$intent=$r->session()->get($platform.'_marketing_oauth');if(!is_array($intent)||($intent['actor']??null)!==$r->user()->id||($intent['platform']??null)!==$platform||($intent['expires']??0)<now()->timestamp)return null;
   $raw=$r->session()->get($platform.'_pending');$p=$raw?json_decode(Crypt::decryptString($raw),true,32,JSON_THROW_ON_ERROR):null;
   if(is_array($p)&&($p['actor']??null)===$r->user()->id&&($p['merchant']??null)===$this->merchant()&&($p['app']??null)===(string)config('services.facebook.client_id')&&($p['installation']??null)===app(SocialHubOAuth::class)->fingerprint()&&($p['expires']??0)>=now()->timestamp)return $p;
  }catch(\Throwable){}return null;
 }
 public function listing(Request $r):array{
  $rows=MarketingChannelConnection::where('merchant_key',$this->merchant())->orderBy('name')->get();$pending=[];foreach(['facebook','instagram'] as $p){$context=$this->pending($r,$p);$pending[$p]=array_map(fn($a)=>['id'=>$a['id'],'name'=>$a['name']],$context['pages']??[]);}
  return ['can_manage'=>(bool)$r->user()->is_super_admin,'configuration'=>$this->ready($r),'accounts'=>$rows->map(fn($a)=>$this->summary($a))->all(),'pending'=>$pending];
 }
 public function summary(MarketingChannelConnection $a):array{
  $scopes=$a->permissions??[];$active=in_array($a->state,['connected','unverified'],true)&&$a->app_id===(string)config('services.facebook.client_id');$has=fn($required)=>$active&&!array_diff($required,$scopes);$fb=$a->platform==='facebook';return ['id'=>$a->id,'platform'=>$a->platform,'account_id'=>$a->provider_account_id,'name'=>$a->name,'state'=>$a->state,'checked_at'=>$a->checked_at?->toIso8601String(),'check_code'=>$a->check_code,'permissions'=>$scopes,'capabilities'=>['identity_granted'=>$has($fb?['pages_show_list','pages_read_engagement']:['instagram_basic','pages_read_engagement']),'publishing_granted'=>$has($fb?['pages_manage_posts']:['instagram_basic','instagram_content_publish']),'analytics_granted'=>$has($fb?['read_insights','pages_read_engagement']:['instagram_basic','instagram_manage_insights']),'publishing_enabled'=>false,'analytics_enabled'=>false]];
 }
 public function select(Request $r,string $platform,string $id):void{
  $p=$this->pending($r,$platform);abort_unless($p,409,'The account selection expired. Reconnect to continue.');abort_unless(collect($p['pages']??[])->contains('id',$id),422,'Choose an account returned by this authorization.');
  try{$grant=app(SocialHubOAuth::class)->call('select',['transaction_id'=>$p['transaction'],'page_id'=>$id],$platform);}catch(\Throwable){$r->session()->forget([$platform.'_pending',$platform.'_marketing_oauth']);abort(502,'Authorization could not be confirmed. Reconnect to continue. Existing accounts are retained; there is no automatic retry.');}
  $r->session()->forget([$platform.'_pending',$platform.'_marketing_oauth']);$pages=$grant['pages']??(isset($grant['page'])?[$grant['page']]:[]);$scopes=$grant['permissions']??null;$page=count($pages)===1?$pages[0]:null;
  abort_unless(is_array($page)&&($page['id']??null)===$id&&is_string($page['name']??null)&&is_string($page['access_token']??null)&&$page['access_token']!==''&&is_array($scopes)&&count(array_filter($scopes,'is_string'))===count($scopes),502,'The authorization receipt is incomplete. Existing connections are retained. Reconnect to continue.');
  abort_unless(in_array($platform==='instagram'?'instagram_basic':'pages_show_list',$scopes,true),422,'Account identity permissions are missing. Existing connections are retained.');
  if($platform==='instagram')abort_unless(is_string($page['facebook_page_id']??null)&&preg_match('/^[0-9]{1,100}$/D',$page['facebook_page_id']),502,'The linked Facebook Page could not be verified.');
  DB::transaction(function()use($page,$scopes,$r,$platform,$id){$keys=['merchant_key'=>$this->merchant(),'platform'=>$platform,'provider_account_id'=>$id];$row=MarketingChannelConnection::where($keys)->lockForUpdate()->first()??new MarketingChannelConnection($keys);$row->fill(['app_id'=>(string)config('services.facebook.client_id'),'name'=>mb_substr($page['name'],0,255),'access_token'=>$page['access_token'],'permissions'=>array_values(array_unique($scopes)),'tasks'=>is_array($page['tasks']??null)?$page['tasks']:[],'linked_page_id'=>$page['facebook_page_id']??null,'authorized_by'=>$r->user()->id,'state'=>'unverified','check_code'=>null,'checked_at'=>null,'version'=>$row->exists?$row->version+1:1])->save();});
 }
 public function owned(string $platform,string $id):MarketingChannelConnection{return MarketingChannelConnection::where('merchant_key',$this->merchant())->where('platform',$platform)->findOrFail($id);}
 public function check(MarketingChannelConnection $a):void{
  if($a->state==='disconnected')return;$version=$a->version;$state='unknown';$code='provider_unavailable';$name=$a->name;
  try{if($a->app_id!==(string)config('services.facebook.client_id'))throw new \RuntimeException('authorization_expired');$token=$a->access_token;$http=Http::acceptJson()->connectTimeout(5)->timeout(12)->withoutRedirecting()->withToken($token);$res=$http->get('https://graph.facebook.com/'.config('services.facebook.graph_version','v25.0').'/'.$a->provider_account_id,['fields'=>$a->platform==='instagram'?'id,username':'id,name','appsecret_proof'=>hash_hmac('sha256',$token,(string)config('services.facebook.client_secret'))]);$body=$res->json();if(!$res->successful()||!is_array($body)||isset($body['error']))throw new \RuntimeException(match((int)($body['error']['code']??0)){190=>'authorization_expired',10,200=>'permission_required',default=>'provider_unavailable'});if(($body['id']??null)!==$a->provider_account_id)throw new \RuntimeException('identity_mismatch');$state='connected';$code=null;$name=mb_substr((string)($body['name']??$body['username']??$name),0,255);
  }catch(\Throwable $e){$known=['authorization_expired','permission_required','identity_mismatch'];$code=in_array($e->getMessage(),$known,true)?$e->getMessage():'provider_unavailable';$state=in_array($code,['authorization_expired','permission_required'],true)?$code:'unknown';}
  MarketingChannelConnection::whereKey($a->id)->where('version',$version)->where('state','!=','disconnected')->update(['state'=>$state,'name'=>$name,'check_code'=>$code,'checked_at'=>now()]);
 }
 public function disconnect(MarketingChannelConnection $a):void{MarketingChannelConnection::whereKey($a->id)->update(['access_token'=>Crypt::encryptString(''),'state'=>'disconnected','version'=>DB::raw('version + 1'),'checked_at'=>now(),'check_code'=>null]);}
}
