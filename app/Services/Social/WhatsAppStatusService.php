<?php
namespace App\Services\Social;
use App\Models\ManagedSocialAccount;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\DB;
class WhatsAppStatusService
{
 public function record(ManagedSocialAccount $account,array $event):void
 {
  $id=$event['id']??null;$recipient=$event['recipient_id']??null;$status=$event['status']??null;
  if(!is_string($id)||$id===''||strlen($id)>512||!is_string($recipient)||!preg_match('/^[0-9]{1,100}$/D',$recipient)||!in_array($status,['sent','delivered','read','failed'],true))return;
  DB::table('whatsapp_status_receipts')->insertOrIgnore(['event_key'=>hash('sha256',json_encode([$account->id,$id,$recipient,$status])),'account_id'=>$account->id,'provider_message_id'=>$id,'recipient_id'=>$recipient,'status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
  if(\Illuminate\Support\Facades\Schema::hasTable('marketing_whatsapp_deliveries'))app(\App\Services\Marketing\MarketingWhatsAppBatches::class)->reconcile($account,$id,$recipient);
  $threads=SupportConversation::where('channel','whatsapp')->where('merchant_key',$account->merchant_key)->where('provider_page_id',$account->provider_account_id)->where('provider_sender_id',$recipient)->get();
  foreach($threads as $thread){$thread=SupportConversation::whereKey($thread->id)->lockForUpdate()->first();$message=SupportMessage::where('conversation_id',$thread->id)->where('provider_message_id',$id)->lockForUpdate()->first();if($message)$this->reconcile($account,$message,$recipient);}
 }
 public function reconcile(ManagedSocialAccount $account,SupportMessage $message,string $recipient):void
 {
  if(!$message->provider_message_id)return;
  $statuses=DB::table('whatsapp_status_receipts')->where('account_id',$account->id)->where('provider_message_id',$message->provider_message_id)->where('recipient_id',$recipient)->pluck('status')->all();
  $next=$message->delivery_status;
  foreach(['sent','failed','delivered','read'] as $candidate){
   if(!in_array($candidate,$statuses,true))continue;
   $rank=['sending'=>0,'unknown'=>0,'accepted'=>1,'sent'=>2,'failed'=>3,'delivered'=>4,'read'=>5];
   if(($rank[$candidate]??0)>($rank[$next]??0))$next=$candidate;
  }
  if($next!==$message->delivery_status){$message->update(['delivery_status'=>$next,'send_error'=>$next==='failed'?'WhatsApp reported delivery failure. Check the account before retrying.':null]);SupportConversation::whereKey($message->conversation_id)->update(['updated_at'=>now()]);}
 }
}
