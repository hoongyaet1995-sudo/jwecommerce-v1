<?php
namespace App\Jobs;
use App\Models\{SupportConversation,ManagedSocialAccount,CustomerChannelIdentity};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class EnrichSocialCustomerProfile implements ShouldQueue {
 use Queueable;public $tries=3;
 public function __construct(public string $conversationId,public int $accountId){}
 public function backoff():array{return [60,300,900];}
 public function handle(\App\Services\Social\SocialCustomerProfileService $profiles,\App\Services\Customers\CustomerContactResolver $resolver):void {
  $c=SupportConversation::find($this->conversationId);$a=ManagedSocialAccount::find($this->accountId);
  if(!$c||!$a||$a->state!=='connected'||$c->channel!==$a->platform||$c->merchant_key!==$a->merchant_key||$c->provider_page_id!==$a->provider_account_id)return;
  $link=CustomerChannelIdentity::where('customer_id',$c->customer_id)->where('platform',$a->platform)->where('merchant_key',$a->merchant_key)->where('app_id',$a->app_id)->where('account_id',$a->provider_account_id)->where('external_sender_id',$c->provider_sender_id)->first();
  if(!$link||$link->profile_checked_at?->greaterThan(now()->subDay()))return;
  $resolver->associate($c,['platform'=>$a->platform,'merchant_key'=>$a->merchant_key,'app_id'=>$a->app_id,'account_id'=>$a->provider_account_id,'sender_id'=>$c->provider_sender_id],$profiles->fetch($a,$c->provider_sender_id));
  $link->update(['profile_checked_at'=>now()]);
  $c->touch(); // Publish profile changes to incremental inbox polling.
 }
}
