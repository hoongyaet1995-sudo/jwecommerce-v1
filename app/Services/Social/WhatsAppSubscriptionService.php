<?php
namespace App\Services\Social;
use App\Models\ManagedSocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class WhatsAppSubscriptionService
{
 private function call(ManagedSocialAccount $a,bool $write=false):array {
  try{$h=Http::withToken($a->access_token)->acceptJson()->connectTimeout(5)->timeout(12)->withoutRedirecting();$url='https://graph.facebook.com/'.config('services.whatsapp.graph_version','v25.0').'/'.$a->waba_id.'/subscribed_apps';$r=$write?$h->asForm()->post($url):$h->get($url,['limit'=>100]);$body=$r->json();if(!$r->successful()||!is_array($body)||isset($body['error']))throw new RuntimeException();return $body;}catch(\Throwable){throw new RuntimeException('WhatsApp subscription could not be verified.');}
 }
 private function subscribed(ManagedSocialAccount $a):bool {
  $body=$this->call($a);if(!is_array($body['data']??null)||!empty($body['paging']['next']))throw new RuntimeException('WhatsApp subscription could not be fully verified.');
  return collect($body['data'])->contains(fn($v)=>(string)data_get($v,'whatsapp_business_api_data.id')===$a->app_id);
 }
 public function activate(ManagedSocialAccount $a):void {
  try{
   if($a->platform!=='whatsapp'||$a->merchant_key!==app(WhatsAppAccountService::class)->merchant()||$a->state!=='connected'||$a->app_id!==(string)config('services.whatsapp.app_id')||config('services.whatsapp.inbound_routing_ready')!==true)throw new RuntimeException('WhatsApp Hub routing is awaiting administrator verification.');
   if(array_diff(['whatsapp_business_management','whatsapp_business_messaging'],$a->permissions??[]))throw new RuntimeException('WhatsApp messaging permissions are required.');
   try{$phoneResponse=Http::withToken($a->access_token)->acceptJson()->connectTimeout(5)->timeout(12)->withoutRedirecting()->get('https://graph.facebook.com/'.config('services.whatsapp.graph_version','v25.0').'/'.$a->provider_account_id,['fields'=>'id,status,platform_type']);$phone=$phoneResponse->json();if(!$phoneResponse->successful()||(string)($phone['id']??'')!==$a->provider_account_id||($phone['status']??null)!=='CONNECTED'||($phone['platform_type']??null)!=='CLOUD_API')throw new RuntimeException();}catch(\Throwable){throw new RuntimeException('WhatsApp phone registration must be completed and verified in Meta before messaging can start.');}
   if(!$this->subscribed($a)){if(($this->call($a,true)['success']??null)!==true||!$this->subscribed($a))throw new RuntimeException('Meta has not confirmed the WhatsApp subscription.');}
   ManagedSocialAccount::whereKey($a->id)->where('state','connected')->where('access_token',$a->getRawOriginal('access_token'))->update(['inbound_enabled'=>true,'inbound_verified_at'=>now(),'messaging_error'=>null,'updated_at'=>now()]);
  }catch(RuntimeException $e){ManagedSocialAccount::whereKey($a->id)->where('access_token',$a->getRawOriginal('access_token'))->update(['inbound_enabled'=>false,'inbound_verified_at'=>null,'messaging_error'=>$e->getMessage(),'updated_at'=>now()]);}
  app(FacebookAccountService::class)->invalidateInbox($a);
 }
}
