<?php
namespace App\Services\Marketing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Optional visitor data only. Financial/source snapshots are never deleted here. */
class MarketingTrackingPrivacy
{
 public function choice(Request $r):?bool {
  if(!$r->hasSession())return null;$s=$r->session();$choice=$s->get('marketing.consent');if(!is_bool($choice))return null;
  $expires=$s->get('marketing.consent_expires_at');
  // Preserve an existing session's explicit choice during rollout, with a finite deadline.
  if(!is_int($expires)){$expires=now()->addDays((int)config('marketing.privacy.choice_days',180))->timestamp;$s->put('marketing.consent_expires_at',$expires);}
  if($expires<=now()->timestamp){$s->forget(['marketing.consent','marketing.consent_expires_at']);$this->clearContext($r);return null;}
  return $choice;
 }
 public function save(Request $r,bool $accepted):void {
  $r->session()->put('marketing.consent',$accepted);$r->session()->put('marketing.consent_expires_at',now()->addDays((int)config('marketing.privacy.choice_days',180))->timestamp);
  if(!$accepted)$this->clearContext($r);
 }
 public function clearContext(Request $r):void {$r->session()->forget(['marketing.sid','marketing.activity','marketing.first','marketing.last','marketing.checkout','marketing.checkout_touch']);}
 public function prune(bool $dryRun=true):array {
  $merchant=app(MarketingPosts::class)->merchant();$days=max(8,(int)config('marketing.privacy.event_days',90));$rawDays=max(1,(int)config('marketing.privacy.request_days',7));
  $expired=fn()=>DB::table('marketing_analytics_events')->where('merchant_key',$merchant)->where(fn($q)=>$q->where(fn($r)=>$r->where('kind','request')->where('occurred_at','<',now()->subDays($rawDays)))->orWhere(fn($r)=>$r->where('kind','!=','request')->where('occurred_at','<',now()->subDays($days))));
  $events=$expired()->count();$retired=0;
  if(!$dryRun){do{$ids=$expired()->orderBy('id')->limit(1000)->pluck('id');if($ids->isNotEmpty())$expired()->whereIn('id',$ids)->delete();}while($ids->isNotEmpty());}
  foreach(['marketing_order_attributions'=>'placed_at','marketing_enquiry_attributions'=>'submitted_at'] as $table=>$date){
   $q=DB::table($table)->where('merchant_key',$merchant)->where($date,'<',now()->subDays($days))->where('session_key','not like','retired:%');$retired+=$q->count();
   if(!$dryRun)$q->orderBy('id')->chunkById(500,function($rows)use($table,$merchant){foreach($rows as $row)DB::table($table)->where('merchant_key',$merchant)->where('id',$row->id)->update(['session_key'=>'retired:'.substr(hash_hmac('sha256',$table.':'.$row->id,(string)config('app.key')),0,56)]);});
  }
  return ['dry_run'=>$dryRun,'events'=>$events,'retired'=>$retired,'event_days'=>$days,'request_days'=>$rawDays];
 }
}
