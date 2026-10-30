<?php
namespace App\Services\Marketing;

use App\Services\Marketing\Audience\AudienceDirectory;
use Illuminate\Support\Facades\{DB,Crypt,URL};

/** Port of CRM CampaignUnsubscribeService: recipient signature, suppression and pending cancellation. */
final class MarketingEmailUnsubscribe
{
 public function link(string $id,string $merchant):string {
  return URL::temporarySignedRoute('marketing.email.preferences',now()->addDays(180),['delivery'=>$id,'store'=>hash('sha256',$merchant)]);
 }
 public function resolve(string $id,string $store):object {
  $merchant=app(AudienceDirectory::class)->merchant();
  abort_unless(hash_equals(hash('sha256',$merchant),$store),404);
  $r=DB::table('marketing_email_deliveries')->where('merchant_key',$merchant)->find($id);abort_unless($r,404);return $r;
 }
 public function lock(string $merchant):void {
  DB::table('marketing_audience_locks')->insertOrIgnore(['merchant_key'=>$merchant]);
  DB::table('marketing_audience_locks')->where('merchant_key',$merchant)->lockForUpdate()->first();
 }
 public function suppressed(string $merchant,string $email):bool {
  return DB::table('marketing_audience_subscriptions')->where('merchant_key',$merchant)->whereIn('channel',['email','global'])->where('destination_hash',hash('sha256',$email))->whereIn('status',['unsubscribed','suppressed'])->exists();
 }
 public function apply(string $id,string $store):void {
  $r=$this->resolve($id,$store);DB::transaction(function()use($r){
   $this->lock($r->merchant_key);$r=DB::table('marketing_email_deliveries')->where('id',$r->id)->lockForUpdate()->first();
   $email=mb_strtolower(trim(Crypt::decryptString($r->recipient)));$dir=app(AudienceDirectory::class);$dest=hash('sha256',$email);
   $old=DB::table('marketing_audience_subscriptions')->where('merchant_key',$r->merchant_key)->where('channel','email')->where('destination_hash',$dest)->first();
   if(!$old||!in_array($old->status,['unsubscribed','suppressed'],true))$dir->subscribe('email',$email,'unsubscribed','campaign_email:'.$r->id,now());
   // Preserve accepted/sending/unknown receipts. Cancel only requests not yet claimed by the transport.
   DB::table('marketing_email_deliveries')->where('merchant_key',$r->merchant_key)->where('recipient_hash',$r->recipient_hash)->where('state','queued')->update(['state'=>'blocked','error_code'=>'recipient_unsubscribed','updated_at'=>now()]);
   if(!DB::table('marketing_audience_audits')->where('merchant_key',$r->merchant_key)->where('action','email_unsubscribed')->where('record_id',$r->id)->exists())
    app(Audience\AudienceTasks::class)->audit($r->user_id,'email_unsubscribed',$r->id,['campaign_id'=>$r->campaign_id]);
  });
 }
}
