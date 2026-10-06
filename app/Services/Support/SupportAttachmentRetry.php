<?php
namespace App\Services\Support;
use App\Models\{SupportAttachment,ManagedSocialAccount};
use Illuminate\Support\Facades\DB;
class SupportAttachmentRetry {
 public function retry(SupportAttachment $attachment):void {
  DB::transaction(function()use($attachment){
   $a=SupportAttachment::whereKey($attachment->id)->lockForUpdate()->firstOrFail();
   abort_unless($a->message_id&&$a->account_id&&!$a->staff_user_id&&$a->merchant_key===config('services.facebook.merchant_key','jw-ecommerce'),404);
   $account=ManagedSocialAccount::whereKey($a->account_id)->lockForUpdate()->first();
   abort_unless($account&&$account->merchant_key===$a->merchant_key&&$account->provider_account_id===$a->conversation->provider_page_id&&$account->platform===$a->conversation->channel,404);
   abort_unless($account->state==='connected'&&$account->inbound_enabled,409,'Reconnect the account before retrying.');
   abort_unless($a->status==='failed'&&is_string($a->provider_reference)&&$a->provider_reference!=='',409,'This attachment cannot be retried.');
   $a->update(['status'=>'pending','error'=>null]);\App\Jobs\DownloadSupportImage::dispatch($a->id)->onConnection('support')->onQueue('support-ai')->afterCommit();$a->conversation->touch();
  });
 }
}
