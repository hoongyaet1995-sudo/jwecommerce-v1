<?php
namespace App\Services\Marketing;

use App\Models\MarketingPostAutomation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MarketingAutomationSchedule
{
 public function reserveDue(CarbonImmutable $now):array
 {
  $now=$now->utc();$ids=[];
  MarketingPostAutomation::where('schedule_enabled',true)->orderBy('id')->chunkById(100,function($rows)use($now,&$ids){foreach($rows as $row){
   $reserved=DB::transaction(function()use($row,$now){
    $a=MarketingPostAutomation::whereKey($row->id)->lockForUpdate()->first();
    if(!$a?->schedule_enabled||!$a->schedule_epoch)return [];
    $epoch=CarbonImmutable::parse($a->schedule_epoch,'UTC');$cursor=$a->schedule_checked_at?CarbonImmutable::parse($a->schedule_checked_at,'UTC')->max($epoch):$epoch;
    if($cursor->greaterThan($now))return [];
    $settings=$a->settings;$zone='Asia/Kuala_Lumpur';$day=$cursor->setTimezone($zone)->startOfDay();$last=$now->setTimezone($zone)->startOfDay();$out=[];
    // Bound each scan, preserving the cursor so later ticks can finish a long outage.
    $end=$day->addDays(30)->min($last);$scanEnd=$end->endOfDay()->utc()->min($now);
    for(;$day->lessThanOrEqualTo($end);$day=$day->addDay()){
     if(!in_array($day->isoWeekday(),$settings['days']??[],true))continue;
     if(!preg_match('/^\d{2}:\d{2}$/',$settings['time']??''))continue;
     $slot=$day->setTimeFromTimeString($settings['time'])->utc();
     if($slot->lessThanOrEqualTo($epoch)||$slot->lessThanOrEqualTo($cursor)||$slot->greaterThan($now))continue;
     $id=(string)Str::uuid();$expired=$slot->addMinutes(30)->lessThan($now);
     $inserted=DB::table('marketing_automation_occurrences')->insertOrIgnore(['id'=>$id,'automation_id'=>$a->id,'scheduled_at'=>$slot,'local_label'=>$slot->setTimezone($zone)->format('D Y-m-d H:i').' (UTC+8)','version'=>$a->version,'settings'=>json_encode($settings,JSON_THROW_ON_ERROR),'status'=>$expired?'skipped':'queued','reason'=>$expired?'catchup_expired':null,'generation_id'=>(string)Str::uuid(),'created_at'=>$now,'updated_at'=>$now]);
     if($inserted&&!$expired)$out[]=$id;
    }
    $a->update(['schedule_checked_at'=>$scanEnd]);return $out;
   });$ids=array_merge($ids,$reserved);
  }});
  return $ids;
 }

 public function next(MarketingPostAutomation $a,CarbonImmutable $now):?string
 {
  if(!$a->schedule_enabled)return null;
  $local=$now->setTimezone('Asia/Kuala_Lumpur');
  for($i=0;$i<8;$i++){$day=$local->startOfDay()->addDays($i);if(in_array($day->isoWeekday(),$a->settings['days']??[],true)){$slot=$day->setTimeFromTimeString($a->settings['time']);if($slot->greaterThan($local))return $slot->format('D, d M H:i').' (UTC+8)';}}
  return null;
 }
}
