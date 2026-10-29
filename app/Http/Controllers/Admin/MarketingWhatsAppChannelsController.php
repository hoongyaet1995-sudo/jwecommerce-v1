<?php
namespace App\Http\Controllers\Admin;
use App\Domain\Marketing\MarketingPermission;
use App\Services\Marketing\MarketingChannels;
use App\Services\Social\{WhatsAppAccountService,SocialHubOAuth};
use Illuminate\Http\Request;
class MarketingWhatsAppChannelsController extends WhatsAppAccountController {
 private function access(Request $r,bool $manage=false):void {abort_unless(MarketingPermission::allows($r->user())&&(!$manage||$r->user()->is_super_admin),403);}
 public function page(Request $r,MarketingChannels $channels){
  $this->access($r);$data=$channels->listing($r);$wa=['pages'=>$this->pending($r)['pages']??[],'accounts'=>array_map(fn($a)=>['id'=>(string)$a['id'],'pageId'=>$a['provider_account_id'],'name'=>$a['name'],'state'=>$a['state'],'checkedAt'=>$a['checked_at'],'permissions'=>$a['permissions'],'displayPhoneNumber'=>$a['display_phone_number']],app(WhatsAppAccountService::class)->list())];
  $ready=app(WhatsAppAccountService::class)->configured()?$channels->ready($r,'whatsapp'):['ready'=>false,'reason'=>'WhatsApp connection setup is not ready. Contact your administrator.'];
  $data['platform_configuration']['whatsapp']=$ready;$data['pending']['whatsapp']=$wa['pages'];
  foreach($wa['accounts'] as $a)$data['accounts'][]=['id'=>$a['id'],'platform'=>'whatsapp','account_id'=>$a['pageId'],'display_phone_number'=>$a['displayPhoneNumber'],'name'=>$a['name'],'state'=>$a['state'],'checked_at'=>$a['checkedAt'],'permissions'=>$a['permissions'],'capabilities'=>['identity_granted'=>$a['state']==='connected','publishing_granted'=>false,'publishing_enabled'=>false,'analytics_granted'=>false],'marketing_readiness'=>'Not enabled'];
  return response()->view('marketing.navigation.preview',['studioNavActive'=>'channels','navigationBoot'=>MarketingNavigationController::boot('channels')+['channelsPlatform'=>'whatsapp','channels'=>$data,'csrf'=>csrf_token()]])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
 }
 public function start(Request $r,MarketingChannels $channels){
  $this->access($r,true);abort_unless(app(WhatsAppAccountService::class)->configured()&&$channels->ready($r,'whatsapp')['ready'],409,'WhatsApp connection setup is not ready. Contact your administrator.');
  $r->session()->put('whatsapp_marketing_oauth',['actor'=>$r->user()->id,'platform'=>'whatsapp','expires'=>now()->addMinutes(10)->timestamp]);return parent::authorize($r);
 }

 public function save(Request $r,MarketingChannels $channels){
  $this->access($r,true);$d=$r->validate(['account_id'=>'required|string|regex:/^[0-9]{1,100}$/D']);
  $visible=$this->pending($r)['pages']??[];abort_unless(collect($visible)->contains('id',$d['account_id']),409,'Authorization expired. Reconnect WhatsApp.');
  $pending=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($r->session()->get('whatsapp_pending')),true,512,JSON_THROW_ON_ERROR);
  $r->session()->forget('whatsapp_pending');
  try{$grant=app(SocialHubOAuth::class)->call('select',['transaction_id'=>$pending['transaction'],'page_ids'=>[$d['account_id']]],'whatsapp');$pages=$grant['pages']??[];
   abort_unless(count($pages)===1&&($pages[0]['id']??null)===$d['account_id'],502,'The authorization result could not be verified.');
   $a=app(WhatsAppAccountService::class)->importLegacy(['app_id'=>$grant['app_id']??'','waba_id'=>$pages[0]['waba_id']??'','phone_number_id'=>$d['account_id'],'access_token'=>$pages[0]['access_token']??''],$r->user(),true);$a->update(['connection_method'=>'embedded_signup']);
   // Connection only: never activate subscriptions or send a marketing message here.
  }catch(\RuntimeException){abort(409,'The number could not be verified. Existing accounts are retained. Reconnect to continue.');}
  $r->session()->forget('whatsapp_marketing_oauth');return response()->json($this->page($r,$channels)->getOriginalContent()->getData()['navigationBoot']['channels'])->header('Cache-Control','no-store');
 }
 public function cancelSelection(Request $r,MarketingChannels $channels){$this->access($r,true);$r->session()->forget(['whatsapp_pending','whatsapp_marketing_oauth']);return response()->json($this->page($r,$channels)->getOriginalContent()->getData()['navigationBoot']['channels'])->header('Cache-Control','no-store');}
}
