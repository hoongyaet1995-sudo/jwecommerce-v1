<?php
namespace App\Services\Marketing;

use App\Models\{MarketingPostAutomation,MarketingChannelConnection,MarketingDailyDraftRun,MarketingPost,MarketingPostDispatch,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Scheduled-only publishing handoff. Never contacts a social provider itself. */
class MarketingAutomationPublisher
{
 private function platform(string $platform):string {return ['facebook'=>'Facebook','instagram'=>'Instagram','tiktok'=>'TikTok'][$platform]??'';}
 private function invalid(string $message):never {throw ValidationException::withMessages(['publish_targets'=>$message]);}

 public function preferences(User $user,array $settings):array
 {
  $off=['auto_publish'=>false,'publish_targets'=>[],'publish_options'=>[],'publish_connections'=>[]];
  if(empty($settings['auto_publish']))return $off;
  $targets=$settings['publish_targets']??[];$options=$settings['publish_options']??[];
  if(!$targets)$this->invalid('Choose at least one connected account for automatic publishing.');
  $known=collect(app(MarketingPosts::class)->accounts())->keyBy('id');$versions=[];$clean=[];
  foreach($targets as $id){
   $c=MarketingChannelConnection::where('merchant_key',app(MarketingPosts::class)->merchant())->find($id);
   if(!$c||!($known[$id]['canSchedule']??false))$this->invalid('A selected account is unavailable or lacks publishing access.');
   if(!in_array($this->platform($c->platform),$settings['platforms']??[],true))$this->invalid('Enable the matching platform version for each publishing account.');
   $versions[$id]=$c->version;$clean[$id]=$options[$id]??[];
  }
  app(MarketingPostQueue::class)->approveAutomation($user->id,$targets,$clean);
  return ['auto_publish'=>true,'publish_targets'=>array_values($targets),'publish_options'=>$clean,'publish_connections'=>$versions];
 }

 public function publishCompletedOccurrence(string $id):array
 {
  $row=DB::table('marketing_automation_occurrences')->find($id);abort_unless($row,404);
  try {
   return DB::transaction(function()use($id,$row){
    // Same lock order as settings saves: Automation first, then occurrence.
    $a=MarketingPostAutomation::lockForUpdate()->find($row->automation_id);
    $o=DB::table('marketing_automation_occurrences')->where('id',$id)->lockForUpdate()->first();
    if($o->publication_state!==null)return $this->record($o);
    $settings=json_decode($o->settings,true,512,JSON_THROW_ON_ERROR);
    if(empty($settings['auto_publish']))return $this->store($id,'draft_only');
    abort_unless($a&&$a->schedule_enabled&&$a->version===$o->version&&($a->settings['auto_publish']??false),409,'Publishing approval changed.');
    abort_unless(in_array($o->status,['running','queued'],true),409,'This occurrence is not active.');
    $u=User::findOrFail($a->user_id);app(MarketingFreePosts::class)->authorize($u);
    abort_unless($a->merchant_key===app(MarketingPosts::class)->merchant(),403);
    $preferences=$this->preferences($u,$settings);
    abort_unless($preferences['publish_connections']==($settings['publish_connections']??[]),409,'An account connection changed.');
    foreach($preferences['publish_connections'] as $target=>$version){$c=MarketingChannelConnection::lockForUpdate()->findOrFail($target);abort_unless($c->version===$version,409);}
    $run=MarketingDailyDraftRun::lockForUpdate()->findOrFail($o->generation_id);
    abort_unless($run->status==='completed'&&$run->user_id===$a->user_id&&$run->merchant_key===$a->merchant_key,409,'Generation is not complete.');
    $groups=[];
    foreach($preferences['publish_targets'] as $target){$c=MarketingChannelConnection::findOrFail($target);$platform=$this->platform($c->platform);$postId=$run->post_ids[strtolower($platform)]??$run->post_ids[$platform]??null;abort_unless($postId,409,'A generated platform draft is missing.');$groups[$postId][]=$target;}
    $dispatches=[];
    foreach($groups as $postId=>$targets){
     $post=MarketingPost::lockForUpdate()->findOrFail($postId);$context=$post->document['ai_context']??[];
     // FreePosts::save inserts fresh drafts at revision 1. Any later edit needs manual approval.
     abort_unless($post->version===1,409,'The generated draft was edited. Review it before publishing.');
     abort_unless($post->user_id===$a->user_id&&$post->merchant_key===$a->merchant_key&&$post->status==='Draft'&&($context['daily_draft_run']??null)===$run->id&&($context['automation_id']??null)===$a->id,409,'The generated draft changed.');
     $data=app(MarketingPosts::class)->view($post);$data['targets']=$targets;$data['copies']=[];$data['options']=array_intersect_key($preferences['publish_options'],array_flip($targets));
     app(MarketingPostQueue::class)->submit($u->id,$data,true);
     $dispatches[$postId]=MarketingPostDispatch::where('post_id',$postId)->where('revision',$post->version+1)->sole()->id;
    }
    return $this->store($id,'queued',$dispatches);
   });
  }catch(\Throwable $e){
   report($e);
   // The queue and marker use one DB transaction. Validation/queue failures leave ALL drafts intact.
   return DB::transaction(function()use($id){$o=DB::table('marketing_automation_occurrences')->where('id',$id)->lockForUpdate()->first();if($o->publication_state!==null)return $this->record($o);return $this->store($id,'attention',[],'publishing_requires_review');});
  }
 }
 private function store(string $id,string $state,array $dispatches=[],?string $reason=null):array {
  DB::table('marketing_automation_occurrences')->where('id',$id)->update(['publication_state'=>$state,'publication_dispatches'=>json_encode($dispatches),'publication_reason'=>$reason,'updated_at'=>now()]);
  return ['state'=>$state,'dispatches'=>$dispatches,'reason'=>$reason];
 }
 private function record(object $o):array {return ['state'=>$o->publication_state,'dispatches'=>json_decode($o->publication_dispatches??'[]',true),'reason'=>$o->publication_reason];}
 public function publicationForGeneration(string $id):?array {
  $o=DB::table('marketing_automation_occurrences')->where('generation_id',$id)->first();if(!$o||$o->publication_state===null)return null;
  $result=$this->record($o);
  if($result['state']==='queued'){
   $ids=array_values($result['dispatches']);$states=MarketingPostDispatch::whereIn('id',$ids)->pluck('state');
   if($states->count()!==count($ids)||$states->contains(fn($s)=>in_array($s,['attention','cancelled','superseded'],true)))$result['state']='attention';
   elseif($states->isNotEmpty()&&$states->every(fn($s)=>$s==='completed'))$result['state']='published';
   elseif($states->contains(fn($s)=>in_array($s,['executing','checking','waiting_result','query_queued'],true)))$result['state']='publishing';
  }
  return $result;
 }
}
