<?php
namespace App\Services\Social;

use App\Models\ManagedSocialAccount;
use App\Models\SupportConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class WhatsAppInboundService
{
 public function receive(array $payload):int
 {
  if(($payload['object']??null)!=='whatsapp_business_account')return 0;
  $processed=0;
  foreach($payload['entry']??[] as $entry){
   if(!is_array($entry)||!$this->identity($entry['id']??null))continue;
   foreach($entry['changes']??[] as $change){
    if(!is_array($change)||($change['field']??null)!=='messages')continue;
    $value=$change['value']??[];$phone=data_get($value,'metadata.phone_number_id');if(!$this->identity($phone))continue;
    $processed+=DB::transaction(function()use($entry,$phone,$value){
     $account=ManagedSocialAccount::where('merchant_key',config('services.facebook.merchant_key','jw-ecommerce'))->where('platform','whatsapp')->where('app_id',(string)config('services.whatsapp.app_id'))->where('waba_id',$entry['id'])->where('provider_account_id',$phone)->lockForUpdate()->first();
     if(!$account||$account->state!=='connected'||!$account->inbound_enabled)return 0;
     $count=0;
     foreach($value['messages']??[] as $event){
      if(!is_array($event)||!$this->identity($event['from']??null)||!is_string($event['id']??null)||strlen($event['id'])>512||$event['id']==='')continue;
      $image=($event['type']??null)==='image' && is_string(data_get($event,'image.id')) && preg_match('/^\d{1,100}$/D',$event['image']['id']);
      $audio=($event['type']??null)==='audio' && is_string(data_get($event,'audio.id')) && preg_match('/^\d{1,100}$/D',$event['audio']['id']);
      $document=($event['type']??null)==='document'&&is_string(data_get($event,'document.id'))&&preg_match('/^\d{1,100}$/D',$event['document']['id']);
      $text=$document?(string)data_get($event,'document.caption',''):($audio?'':($image?(string)data_get($event,'image.caption',''):(($event['type']??null)==='text'?data_get($event,'text.body'):'[Unsupported WhatsApp message: '.substr((string)($event['type']??'unknown'),0,40).']')));
      if(!is_string($text)||(trim($text)===''&&!$image&&!$audio&&!$document)||mb_strlen($text)>16000)continue;
      $key=hash('sha256',json_encode(['whatsapp',$account->merchant_key,$account->app_id,$phone,$event['id']]));
      if(DB::table('messenger_inbound_receipts')->where('event_key',$key)->exists())continue;
      $owner=hash('sha256',json_encode(['whatsapp',$account->merchant_key,$account->app_id,$phone,$event['from']]));
      $conversation=SupportConversation::firstOrCreate(['owner_hash'=>$owner],['id'=>(string)Str::uuid(),'channel'=>'whatsapp','merchant_key'=>$account->merchant_key,'provider_page_id'=>$phone,'provider_sender_id'=>$event['from'],'provider_page_name'=>$account->name,'status'=>'open']);
      $conversation=SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
      $profile=collect($value['contacts']??[])->first(fn($contact)=>is_array($contact)&&($contact['wa_id']??null)===$event['from']);
      $timestamp=$event['timestamp']??null;
      // Invalid/future timestamps must never open the customer-service reply window.
      $sentAt=is_string($timestamp)&&ctype_digit($timestamp)&&(int)$timestamp>0&&(int)$timestamp<=now()->timestamp?Carbon::createFromTimestamp((int)$timestamp):now()->subHours(24);
      $message=$conversation->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>$text,'created_at'=>$sentAt,'updated_at'=>now()]);
      if($image)app(\App\Services\Support\SupportAttachmentService::class)->incoming($conversation,$message,$account,$event['image']['id']);
      if($document)app(\App\Services\Support\SupportAttachmentService::class)->incoming($conversation,$message,$account,$event['document']['id'],'document',is_string(data_get($event,'document.filename'))?$event['document']['filename']:null);
      if($audio)app(\App\Services\Support\SupportAttachmentService::class)->incoming($conversation,$message,$account,$event['audio']['id'],'audio');
      if($conversation->notes()->where('content','like','[Archive] %')->latest('id')->value('content')==='[Archive] true')$conversation->notes()->create(['content'=>'[Archive] false']);
      $conversation->update(['status'=>in_array($conversation->status,['human_mode','waiting_human'],true)?$conversation->status:'open','provider_page_name'=>$account->name,'last_message_at'=>$conversation->last_message_at?->greaterThan($sentAt)?$conversation->last_message_at:$sentAt]);
      app(\App\Services\Customers\CustomerContactResolver::class)->associate($conversation,['platform'=>'whatsapp','merchant_key'=>$account->merchant_key,'app_id'=>$account->app_id,'account_id'=>$phone,'sender_id'=>$event['from']],['name'=>data_get($profile,'profile.name','WhatsApp customer'),'phone'=>'+'.$event['from'],'phone_trusted'=>true]);
      DB::table('messenger_inbound_receipts')->insert(['event_key'=>$key,'conversation_id'=>$conversation->id,'page_id'=>$phone,'message_id'=>$event['id'],'created_at'=>now(),'updated_at'=>now()]);
      $account->update(['last_inbound_at'=>now()]);app(\App\Services\Support\SupportCustomerMemoryService::class)->schedule($conversation,$message); app(\App\Services\Support\SupportMessageAlertService::class)->received($conversation,$message);
      if(!app(\App\Services\Support\SupportAttachmentAnalysisService::class)->schedule($message) && !$document && (($event['type']??null)==='text' || $image) && trim($text)!==''
          && !app(\App\Services\Support\SupportReplyCadenceService::class)->enqueue($message))\App\Jobs\ProcessWhatsAppSupportReply::dispatch($message->id)->onConnection('support')->onQueue('support-ai')->afterCommit();
      $count++;
     }
     foreach($value['statuses']??[] as $event){if(is_array($event))app(WhatsAppStatusService::class)->record($account,$event);}
     return $count;
    },3);
   }
  }
  return $processed;
 }
 private function identity(mixed $value):bool {return is_string($value)&&preg_match('/^[0-9]{1,100}$/D',$value)===1;}
}
