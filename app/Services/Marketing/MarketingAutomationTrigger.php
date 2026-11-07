<?php
namespace App\Services\Marketing;
use App\Jobs\AdvanceMarketingAutomation;
use App\Services\Social\SocialHubInstallation;
use Illuminate\Support\Facades\{DB,Schema};
use Carbon\CarbonImmutable;
final class MarketingAutomationTrigger {
 public function accept(object $installation,string $id,string $hash):array {
  if(!config('marketing.automation_cron_enabled',false))return ['status'=>'disabled','reason'=>'client_automation_paused','dispatch_id'=>$id];
  foreach(['marketing_automation_triggers','marketing_post_automations','marketing_automation_occurrences'] as $table)if(!Schema::hasTable($table))return ['status'=>'not_configured','reason'=>'automation_not_installed','dispatch_id'=>$id];
  if(!app(\App\Support\AutomationResponseSupport::class)->available())return ['status'=>'not_configured','reason'=>'async_response_unavailable','dispatch_id'=>$id];
  $inserted=DB::table('marketing_automation_triggers')->insertOrIgnore(['id'=>$id,'client_id'=>$installation->client_id,'key_id'=>$installation->key_id,'registered_domain'=>$installation->registered_domain,'payload_hash'=>$hash,'status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
  $row=DB::table('marketing_automation_triggers')->find($id);abort_unless($row&&$row->client_id===$installation->client_id&&hash_equals($row->payload_hash,$hash),409,'dispatch_payload_conflict');
  // A future distinct pulse recovers any queued receipt whose terminating callback never ran.
  if($inserted)AdvanceMarketingAutomation::dispatchAfterResponse();
  $result=$row->status==='finished'?(json_decode($row->result??'{}',true)??[]):[];
  return ['status'=>$result['status']??'accepted','dispatch_id'=>$id,'reason'=>$result['reason']??null];
 }
 public function advanceOldest():void {
  $row=DB::transaction(function(){$r=DB::table('marketing_automation_triggers')->where('status','queued')->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();if(!$r)return null;DB::table('marketing_automation_triggers')->where('id',$r->id)->update(['status'=>'processing','started_at'=>now(),'updated_at'=>now()]);return $r;});
  if(!$row)return;
  try {
   $s=app(SocialHubInstallation::class)->settings();
   if(!$s||!$s->enabled||$s->client_id!==$row->client_id||$s->key_id!==$row->key_id||$s->registered_domain!==$row->registered_domain)$result=['status'=>'disabled','reason'=>'installation_changed'];
   else $result=app(MarketingAutomationRunner::class)->tick(CarbonImmutable::now('UTC'));
   $allowed=['disabled','busy','idle','running','completed','cancelled','skipped','needs_attention'];if(!in_array($result['status']??null,$allowed,true))$result=['status'=>'needs_attention','reason'=>'client_result_unconfirmed'];
  }catch(\Throwable $e){report($e);$result=['status'=>'needs_attention','reason'=>'client_execution_unconfirmed'];}
  $safe=array_intersect_key($result,array_flip(['status','reason','occurrence_id','generation_id','counts']));
  DB::table('marketing_automation_triggers')->where('id',$row->id)->where('status','processing')->update(['status'=>'finished','result'=>json_encode($safe,JSON_THROW_ON_ERROR),'finished_at'=>now(),'updated_at'=>now()]);
 }
}
