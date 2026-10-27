<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingRevision,MarketingAiRun};
use Illuminate\Support\Facades\{DB,Storage};

class MarketingMaterialRepair
{
 private ?array $readDigests=null;
 /** Memoize evidence only during an explicit read; mutations and workers always recalculate. */
 public function readSnapshot(callable $read):mixed{
  abort_if($this->readDigests!==null,409);$this->readDigests=[];
  try{return $read();}finally{$this->readDigests=null;}
 }
 public const VERSION='targeted-frame-repair-v1';
 public function latest(MarketingProject $p):?MarketingAiRun{return MarketingAiRun::where('project_id',$p->id)->where('action','material_images')->whereNotNull('usage->repair_context')->latest('created_at')->latest('id')->first();}
 public function digest(MarketingProject $p):string{
  $r=$p->currentRevision;$key=$p->id.':'.$p->version.':'.$r->id;
  if($this->readDigests!==null&&isset($this->readDigests[$key]))return $this->readDigests[$key];
  $digest=hash('sha256',json_encode([self::VERSION,$r->id,$r->document,$r->context['director_review']??null,app(MarketingTransitionPlanning::class)->referenceEvidence($p,$r),app(MarketingTransitionPlanning::class)->inventory($p,$r)],JSON_THROW_ON_ERROR));
  if($this->readDigests!==null)$this->readDigests[$key]=$digest;return $digest;
 }
 public function assertCurrent(MarketingProject $p,MarketingAiRun $run):void{abort_unless(($run->usage['repair_context']['version']??'')===self::VERSION&&($run->usage['repair_context']['digest']??'')===$this->digest($p),409,'修正依据、素材或授权已变化；原图保留，请重新评估。');}
 public function eligible(MarketingProject $p):array{
  $r=$p->currentRevision;if(!$r)return [];$state=app(MarketingTransitionPlanning::class)->state($p,$r);if(in_array($state['status'],['missing','stale','not_required']))return [];$eligible=[];
  foreach($r->document['transition_plan']['pair_reviews']??[] as $n=>$review){if(($review['verdict']??'')!=='revise'||($review['source_digest']??'')!==app(MarketingFramePairReview::class)->digest($p,$r,(int)$n))continue;foreach(['start_frame','end_frame'] as $role)if(($state['frames'][$n.'-'.$role]['status']??'')==='approved')$eligible[]='scene-'.$n.'-'.$role;}
  return $eligible;
 }
 private function path(MarketingProject $p,array $frame):string{$a=$p->assets()->where('authorized',true)->findOrFail($frame['asset_id']);$path=Storage::disk('local')->path($a->path);abort_unless(is_file($path)&&hash_file('sha256',$path)===$frame['sha256'],409,'修正素材文件或权限已变化。');return $path;}
 public function authorize(string $id,int $actor,string $run,float $stop):MarketingAiRun{return DB::transaction(function()use($id,$actor,$run,$stop){$p=app(MarketingMaterialImages::class)->project($id,$actor);$r=MarketingAiRun::where('project_id',$id)->lockForUpdate()->findOrFail($run);$this->assertCurrent($p,$r);abort_unless($stop>=.6&&$stop<=100,422);if($r->status==='quoted'){$u=$r->usage;$u['auto_review']['spend_stop_usd']=$stop;$r->update(['usage'=>$u]);}return app(MarketingMaterialImages::class)->authorize($id,$actor,$run);});}
 public function plan(string $id,int $actor,int $version,string $request):MarketingAiRun{
  @set_time_limit(120);$config=app(MarketingMaterialImageClient::class)->configuration();$policy=app(MarketingMaterialAutoReview::class)->policy();
  $start=DB::transaction(function()use($id,$actor,$version,$request,$config,$policy){$p=MarketingProject::where('owner_id',$actor)->lockForUpdate()->findOrFail($id);$existing=MarketingAiRun::where('project_id',$id)->where('request_id',$request)->first();if($existing){abort_unless(isset($existing->usage['repair_context'])&&$existing->status==='quoted',409,'请求已记录，请查看已保存修正任务。');return ['existing'=>$existing];}$p=app(MarketingWorkflow::class)->locked($id,$actor,$version);$allowed=$this->eligible($p);abort_unless($allowed,422,'没有可直接修正的已核验失败首尾图；请先完成质检或补充实际缺少的依据。');
   $last=app(MarketingMaterialImages::class)->latest($p);if($last)abort_if((in_array($last->status,['running','awaiting_batch_review'])||($last->status==='quoted'&&now()->lt($last->usage['expires_at']??now())))||collect($last->usage['outputs']??[])->contains(fn($o)=>in_array($o['status'],['submitting','unknown','qa_submitting','qa_unknown'])),409,'请先处理现有图片任务；不会重复生成。');
   $run=MarketingAiRun::create(['project_id'=>$id,'request_id'=>$request,'action'=>'material_images','status'=>'planning','feedback'=>'failed-frame-repair','reserved_usd'=>.02,'usage'=>$config+['contract'=>MarketingMaterialImages::CONTRACT,'design_version'=>MarketingMaterialImageDesign::VERSION,'repair_context'=>['version'=>self::VERSION,'digest'=>$this->digest($p),'source_revision'=>$p->current_revision_id],'auto_review'=>$policy+['spend_stop_usd'=>2],'spent_usd'=>0,'outputs'=>[]]]);$p->update(['busy_run_id'=>$run->id,'version'=>$p->version+1]);return compact('p','run','allowed');});if(isset($start['existing']))return $start['existing'];['p'=>$p,'run'=>$run,'allowed'=>$allowed]=$start;
  try{$r=$p->currentRevision;$frames=app(MarketingTransitionPlanning::class)->inventory($p,$r);$images=[];foreach($frames as $f)if($f['status']==='approved')$images['scene-'.$f['scene'].'-'.$f['role']]=$this->path($p,$f);
   $proposal=app(MarketingMaterialRepairPlanner::class)->plan(['director'=>$r->document['director_plan'],'boundaries'=>$r->document['transition_plan']['boundaries'],'failed_pair_checks'=>array_map(fn($report)=>array_intersect_key($report,array_flip(['checks','verdict'])),$r->document['transition_plan']['pair_reviews']),'references'=>app(MarketingTransitionPlanning::class)->referenceEvidence($p,$r)],$images,$allowed);
   $u=$run->fresh()->usage;$u['repair_proposal']=$proposal;$u['planning_usage']=$proposal['usage']??[];if(($proposal['actual_usd']??null)===null)$u['unknown_cost']=true;$run->update(['usage'=>$u]);abort_if($u['unknown_cost']??false,422,'修正规划用量未知，请先核对，未创建图片生成任务。');$this->assertCurrent($p->fresh(),$run);abort_unless(($proposal['needs_input']??'')===''&&count($proposal['targets'])>0,422,$proposal['needs_input']?:'AI没有给出可靠的定向修正范围。');$targets=array_column($proposal['targets'],'output_id');abort_unless(count(array_unique($targets))===count($targets)&&!array_diff($targets,$allowed),422,'修正对象超出失败段落范围。');
   $pkg=$r->document['material_package'];$outputs=[];foreach($proposal['targets'] as $target){abort_unless($target['anchors']&&!array_diff($target['anchors'],array_keys($images))&&!array_intersect($target['anchors'],$targets),422,'修正锚点必须是保留的已批准图，不能是待修正对象。');$output=collect($pkg['outputs'])->firstWhere('id',$target['output_id']);$built=app(MarketingMaterialImageDesign::class)->build($p,$pkg,$output);$anchors=[];foreach(array_unique($target['anchors']) as $anchor){$a=collect($pkg['outputs'])->firstWhere('id',$anchor);$anchors[]=['id'=>$anchor,'asset_id'=>$a['asset_id'],'sha256'=>$a['sha256']];}
    $outputs[]=['id'=>$output['id'],'scene'=>$output['scene_number'],'role'=>$output['role'],'status'=>'repair_pending','asset_id'=>$output['asset_id'],'sha256'=>$output['sha256'],'prompt'=>$built['prompt'],'design'=>$built['design'],'postproduction'=>$built['postproduction'],'correction'=>$target['correction'],'repair_anchors'=>$anchors,'repairs'=>0,'attempts'=>[]];}
   $u['outputs']=$outputs;$u['fingerprint']=app(MarketingMaterialImages::class)->fingerprint($p);$u['source_revision']=$r->id;$u['ratio']=$p->concepts[$p->selected_concept]['ratio'];$u['size']=app(MarketingMaterialImageClient::class)->size($u['ratio']);$u['expires_at']=now()->addMinutes(30)->toIso8601String();$u['spent_usd']=0;$u['unit_usd']=null;$u['total_usd']=null;$run->update(['status'=>'quoted','usage'=>$u]);
  }catch(\Throwable $e){if($e instanceof \App\Services\AI\ProviderFailure&&in_array($e->reason,['timeout','connection','response'])){$u=$run->fresh()->usage;$u['unknown_cost']=true;$run->update(['usage'=>$u]);}$run->update(['status'=>'needs_attention','error'=>$e instanceof \App\Services\AI\ProviderFailure&&$e->reason==='balance'?'AI余额不足，请top up；没有重试。':mb_substr($e->getMessage(),0,350)]);throw $e;}
  finally{DB::transaction(function()use($p,$run){$fresh=MarketingProject::lockForUpdate()->findOrFail($p->id);if($fresh->busy_run_id===$run->id)$fresh->update(['busy_run_id'=>null,'version'=>$fresh->version+1]);});}return $run->fresh();
 }
 public function finalChecks(MarketingProject $p,MarketingAiRun $run):MarketingAiRun{
  $u=$run->usage;$this->assertCurrent($p,$run);$r=$p->currentRevision;$pkg=$r->document['material_package'];$byId=collect($u['outputs'])->keyBy('id');$affected=array_unique(array_column($u['outputs'],'scene'));$jobs=[];
  foreach($affected as $n)$jobs['scene-'.$n]=['kind'=>'scene','number'=>$n,'first'=>'scene-'.$n.'-start_frame','last'=>'scene-'.$n.'-end_frame','design'=>['frame_role'=>'end_frame','shot'=>$r->document['director_plan']['shots'][$n-1]]];
  foreach($r->document['transition_plan']['boundaries'] as $e)if(in_array($e['from'],$affected)||in_array($e['to'],$affected))$jobs['boundary-'.$e['from'].'-'.$e['to']]=['kind'=>'boundary','first'=>'scene-'.$e['from'].'-end_frame','last'=>'scene-'.$e['to'].'-start_frame','design'=>['frame_role'=>'start_frame','boundary'=>$e]];
  foreach($jobs as $key=>$job){$inputs=[];foreach(['first','last'] as $side){$o=$byId[$job[$side]]??collect($pkg['outputs'])->firstWhere('id',$job[$side]);$inputs[$side]=$o;}$digest=hash('sha256',json_encode([$inputs,$job],JSON_THROW_ON_ERROR));$previous=$u['repair_checks'][$key]??null;if(($previous['digest']??'')===$digest&&($previous['report']['verdict']??'')==='pass')continue;
   if(($u['spent_usd']??0)+.1>$u['auto_review']['spend_stop_usd']){$u['pause_reason']='budget';$run->update(['status'=>'needs_attention','usage'=>$u,'error'=>'已达预算停止线，尚未提交成对复检。']);return $run;}
   if(($previous['status']??'')==='submitting'){$u['unknown_cost']=true;$run->update(['status'=>'needs_attention','usage'=>$u,'error'=>'成对复检结果未知，未重复提交。']);return $run;}
   $u['repair_checks'][$key]=['status'=>'submitting','digest'=>$digest,'kind'=>$job['kind'],'number'=>$job['number']??null,'candidate_output_id'=>$job['last'],'anchor_output_id'=>$job['first']];$run->update(['usage'=>$u]);
   try{$report=app(MarketingMaterialVisionClient::class)->inspect($job['design'],['candidate'=>$this->path($p,$inputs['last']),$job['kind']==='scene'?'scene_start':'previous_passed_frame'=>$this->path($p,$inputs['first'])],$job['kind']==='boundary');}
   catch(\Throwable){$u['unknown_cost']=true;$run->update(['status'=>'needs_attention','usage'=>$u,'error'=>'成对复检未完整返回，请核对用量；没有重试。']);return $run;}
   $u['repair_checks'][$key]+=['report'=>$report];$u['repair_checks'][$key]['status']='completed';if($report['actual_usd']===null){$u['unknown_cost']=true;$run->update(['status'=>'needs_attention','usage'=>$u,'error'=>'成对复检用量未知，已暂停。']);return $run;}$u['spent_usd']=round($u['spent_usd']+$report['actual_usd'],6);
   if($report['verdict']!=='pass'){
    // A pair verdict describes two images. It does not identify an authorized edit target.
    // Preserve individual passes and require a new scope assessment instead of editing
    // whichever output happens to occur first in this batch.
    $u['pause_reason']='pair_scope';$run->update(['status'=>'needs_attention','usage'=>$u,'error'=>'成对或跨段复检未通过，已暂停；单帧通过的候选保留，需重新评估修正范围：'.$report['summary']]);return $run;
   }
   $run->update(['usage'=>$u]);return $run;
  }
  $u['repair_checks_complete']=true;$run->update(['status'=>'awaiting_batch_review','usage'=>$u,'error'=>null]);return $run;
 }
 public function pairIssues(MarketingAiRun $run):array{
  $issues=[];foreach($run->usage['repair_checks']??[] as $key=>$check){if(($check['status']??'')!=='completed'||($check['report']['verdict']??'pass')==='pass')continue;
   $scene=$check['number']??null;$candidate=$check['candidate_output_id']??($scene?'scene-'.$scene.'-end_frame':null);
   $issues[]=['candidate'=>$candidate,'retained'=>$candidate&&!in_array($candidate,array_column($run->usage['outputs'],'id'),true),'summary'=>$check['report']['summary']??'', 'correction'=>$check['report']['correction']??''];
  }return $issues;
 }
 public function progress(MarketingAiRun $run):array{
  $outputs=$run->usage['outputs']??[];$pending=collect($outputs)->first(fn($o)=>!in_array($o['status'],['ai_passed','approved']));
  $stage=$pending?(in_array($pending['status'],['qa_pending','qa_submitting'])?'vision':'image'):'pairs';
  $message=$pending?'正在处理第'.$pending['scene'].'段 · '.($pending['role']==='start_frame'?'开场关键帧':'结束关键帧').' · '.($stage==='vision'?'AI视觉质检':'定向修图'):'正在复查同段首尾与相邻跨段衔接';
  return ['stage'=>$stage,'message'=>$run->status==='running'?$message:($run->error??'当前步骤已完成'),'passed'=>collect($outputs)->whereIn('status',['ai_passed','approved'])->count(),'total'=>count($outputs),'spent_usd'=>$run->usage['spent_usd']??0];
 }
 public function assertFinal(MarketingProject $p,MarketingAiRun $run):void{$this->assertCurrent($p,$run);abort_unless(($run->usage['repair_checks_complete']??false)&&!empty($run->usage['repair_checks'])&&collect($run->usage['repair_checks'])->every(fn($c)=>($c['report']['verdict']??'')==='pass'),422,'先完成同段首尾及相邻跨段复检，不能采用未通过的修正。');}
 public function refreshRevision(MarketingProject $p,MarketingRevision $r,MarketingAiRun $run):void{
  $doc=$r->document;$plan=$doc['transition_plan'];foreach($run->usage['repair_checks'] as $c)if($c['kind']==='scene'){$n=$c['number'];$plan['pair_reviews'][$n]=$c['report']+['version'=>MarketingFramePairReview::VERSION,'source_digest'=>app(MarketingFramePairReview::class)->digest($p,$r,$n),'checked_at'=>now()->toIso8601String()];}$doc['transition_plan']=$plan;$r->update(['document'=>$doc]);
  $context=$r->context;$hash=hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR));$context['transition_adoption']=['actor_id'=>$p->owner_id,'at'=>now()->toIso8601String(),'plan_digest'=>$hash];$context['transition_review']=['actor_id'=>$p->owner_id,'at'=>now()->toIso8601String(),'plan_digest'=>$hash,'frame_digest'=>app(MarketingTransitionPlanning::class)->frameDigest(app(MarketingTransitionPlanning::class)->inventory($p,$r)),'basis'=>'targeted_repair_visual_checks_and_batch_consent'];$r->update(['context'=>$context]);
 }
}
