<?php
namespace App\Services\Marketing;

use App\Models\{MarketingPostAutomation,MarketingAutomationRun,User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache,DB};

final class MarketingAutomationRunner
{
 public function tick(CarbonImmutable $now):array
 {
  if(!config('marketing.automation_cron_enabled',false))return ['status'=>'disabled'];
  $lock=Cache::lock('marketing:automation-runner',1800);
  if(!$lock->get())return ['status'=>'busy'];
  try {
   app(MarketingAutomationSchedule::class)->reserveDue($now);
   $o=DB::table('marketing_automation_occurrences')->whereIn('status',['queued','running'])->orderBy('scheduled_at')->orderBy('id')->first();
   if(!$o)return ['status'=>'idle','counts'=>$this->counts()];
   $a=MarketingPostAutomation::find($o->automation_id);
   if(!$a?->schedule_enabled||$a->version!==$o->version){
    $existing=MarketingAutomationRun::find($o->generation_id);
    if($existing){try{$state=app(MarketingPostAutomations::class)->view($existing);if(($state['status']??'')==='completed')return $this->finish($o,'completed',null);}catch(\Throwable){}return $this->finish($o,'needs_attention','paused_existing_generation');}
    return $this->finish($o,'cancelled','automation_changed_or_paused');
   }
   if($o->status==='queued'&&CarbonImmutable::parse($o->scheduled_at,'UTC')->addMinutes(30)->lessThan($now))return $this->finish($o,'skipped','catchup_expired');
   $user=User::find($a->user_id);
   if(!$user||$a->merchant_key!==app(MarketingPosts::class)->merchant())return $this->finish($o,'cancelled','owner_unavailable');
   try {
    app(MarketingFreePosts::class)->authorize($user);
    DB::table('marketing_automation_occurrences')->where('id',$o->id)->update(['status'=>'running','started_at'=>$o->started_at??$now,'updated_at'=>$now]);
    $service=app(MarketingPostAutomations::class);$run=MarketingAutomationRun::find($o->generation_id);
    if(!$run)$run=$service->prepare($user,$a->id,['id'=>$o->generation_id,'version'=>$o->version]);
    $state=$service->view($run);
    // One durable step per tick. Existing provider CAS states prevent duplicate calls even after a process dies.
    if(($state['can_step']??false)||($state['can_save']??false)){
     $fresh=$a->fresh();if(!$fresh->schedule_enabled||$fresh->version!==$o->version)return $this->finish($o,'cancelled','automation_changed_or_paused');
     app(MarketingFreePosts::class)->authorize($user->fresh());
     $state=$service->step($user,$o->generation_id);
    }
    if($state['status']==='completed'){app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($o->id);return $this->finish($o,'completed',null);}
    if(!($state['can_step']??false)&&!($state['can_save']??false))return $this->finish($o,'needs_attention','generation_result_unconfirmed');
    return ['status'=>'running','occurrence_id'=>$o->id,'generation_id'=>$o->generation_id,'counts'=>$this->counts()];
   }catch(\Throwable $e){report($e);return $this->finish($o,'needs_attention','generation_requires_review');}
  }finally{$lock->release();}
 }
 private function finish(object $o,string $status,?string $reason):array
 {
  DB::table('marketing_automation_occurrences')->where('id',$o->id)->update(['status'=>$status,'reason'=>$reason,'finished_at'=>now(),'updated_at'=>now()]);
  return ['status'=>$status,'reason'=>$reason,'occurrence_id'=>$o->id,'generation_id'=>$o->generation_id,'counts'=>$this->counts()];
 }
 private function counts():array
 {
  return DB::table('marketing_automation_occurrences')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status')->map(fn($n)=>(int)$n)->all();
 }
}
