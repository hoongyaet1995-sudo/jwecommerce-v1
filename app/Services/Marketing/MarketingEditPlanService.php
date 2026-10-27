<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingVideoFilm,MarketingVideoClip,MarketingEditPlan,MarketingClipAnalysis as Analysis,MarketingConversationMessage as Message};
use App\Jobs\ProcessMarketingWorkspaceVideo;
use Illuminate\Support\Facades\{DB,Validator};

/** Compiles a narrow media recipe; LLM output never supplies paths, code or generation tools. */
class MarketingEditPlanService {
 public const VERSION='workspace-film-edits-v1';
 public function propose(MarketingProject $project,MarketingVideoFilm $base,array $candidate,array $analyses,string $messageId):MarketingEditPlan {
  abort_unless(config('marketing.editing.enabled'),403);
  $message=Message::where('project_id',$project->id)->where('actor_id',$project->owner_id)->where('role','assistant')->findOrFail($messageId);
  $recipe=$this->recipe($project,$base,$candidate,$analyses,(bool)($message->context['head_trim_requested']??false));$recipe['plan_id']=null;
  return DB::transaction(function()use($project,$base,$candidate,$analyses,$message,$recipe){
   MarketingProject::whereKey($project->id)->lockForUpdate()->firstOrFail();$existing=MarketingEditPlan::where('message_id',$message->id)->first();
   if($existing){abort_unless(MarketingEditingDigest::hash($existing->candidate)===MarketingEditingDigest::hash($candidate),409);return $existing;}
   MarketingEditPlan::where('project_id',$project->id)->where('base_film_id',$base->id)->where('status','pending')->update(['status'=>'superseded']);
   $plan=MarketingEditPlan::create(['project_id'=>$project->id,'owner_id'=>$project->owner_id,'message_id'=>$message->id,'base_film_id'=>$base->id,'base_sha256'=>$base->output_sha256,'status'=>'pending','candidate'=>$candidate,'recipe'=>[],'analysis_ids'=>array_map(fn($a)=>$a->id,$analyses),'fingerprint'=>str_repeat('0',64)]);
   $recipe['plan_id']=$plan->id;$plan->update(['recipe'=>$recipe,'fingerprint'=>MarketingEditingDigest::hash($recipe)]);return $plan;
  });
 }
 private function recipe(MarketingProject $project,MarketingVideoFilm $base,array $candidate,array $analyses,bool $headRequested):array {
  $p=app(MarketingClipSource::class)->project($project->id,$project->owner_id);
  abort_unless($base->project_id===$p->id&&$base->owner_id===$p->owner_id&&$base->status==='completed',409,'请先选择已完成且属于本项目的基准成片。');
  $files=app(MarketingVideoFiles::class);$files->file($base->output_path,$base->output_sha256);
  $kind=$base->input['version']??'';abort_unless(in_array($kind,[MarketingWorkspaceFilm::VERSION,MarketingStoryboardProduction::FILM,self::VERSION],true),409,'成片配方不适用于当前剪辑。');
  $rootId=$kind===self::VERSION?($base->input['root_film_id']??null):$base->id;
  $root=MarketingVideoFilm::where('project_id',$p->id)->where('owner_id',$p->owner_id)->where('status','completed')->findOrFail($rootId);
  abort_unless(in_array($root->input['version']??'',[MarketingWorkspaceFilm::VERSION,MarketingStoryboardProduction::FILM],true),409);$files->file($root->output_path,$root->output_sha256);
  $ids=array_column($root->input['scenes'],'clip_id');
  $fresh=($root->input['version']??'')===MarketingStoryboardProduction::FILM?['input'=>$root->input,'fingerprint'=>$root->fingerprint]:app(MarketingWorkspaceFilm::class)->inputs($p,$ids);if(($root->input['version']??'')===MarketingStoryboardProduction::FILM)app(MarketingStoryboardProduction::class)->assertFilmCurrent($root);abort_unless(hash_equals($root->fingerprint,$fresh['fingerprint']),409,'分镜、片段或授权已变化，请重新讨论剪辑方案。');
  if($kind===self::VERSION)abort_unless(hash_equals($base->fingerprint,MarketingEditingDigest::hash($base->input))&&($base->input['source_manifest_fingerprint']??'')===$root->fingerprint,409,'所选剪辑版本的来源已变化。');
  Validator::make(['candidate'=>$candidate],[
   'candidate'=>'required|array:scenes,duration_policy,target_frames,cta_hold_frames','candidate.scenes'=>'required|array|list|min:1|max:12','candidate.scenes.*'=>'required|array:clip_id,in_frame,out_frame,reason','candidate.scenes.*.clip_id'=>'required|uuid|distinct','candidate.scenes.*.in_frame'=>'required|integer|min:0','candidate.scenes.*.out_frame'=>'required|integer|min:1','candidate.scenes.*.reason'=>'required|string|max:600','candidate.duration_policy'=>'required|in:allow_shorter,exact','candidate.target_frames'=>'nullable|integer|min:1|max:2880','candidate.cta_hold_frames'=>'required|integer|min:24|max:120',
  ])->validate();
  abort_unless(array_column($candidate['scenes'],'clip_id')===$ids&&count($analyses)===count($ids),422,'剪辑不能缺段、重复、换素材或重排故事。');
  $evidence=[];foreach($analyses as $a){abort_unless($a instanceof Analysis&&$a->project_id===$p->id&&$a->owner_id===$p->owner_id&&!isset($evidence[$a->clip_id]),422);$evidence[$a->clip_id]=['record'=>$a,'data'=>app(MarketingClipAnalysis::class)->evidence($a)];}
  $scenes=[];$digests=[];$total=0;
  foreach($candidate['scenes'] as $i=>$choice){
   $source=$fresh['input']['scenes'][$i];$row=$evidence[$choice['clip_id']]??null;abort_unless($row,422);$e=$row['data'];$in=$choice['in_frame'];$out=$choice['out_frame'];$n=$source['seconds']*24;
   abort_unless(is_int($in)&&is_int($out)&&$in>=0&&$out>$in&&$out<=$n&&($e['frame_count']??0)===$n&&($e['fps']??0)===24,422,'剪辑区间必须是当前片段内有效的整数帧。');
   abort_unless(($e['clip_id']??'')===$choice['clip_id']&&($e['raw_sha256']??'')===$source['raw_sha256']&&($e['output_sha256']??'')===$source['output_sha256'],409,'素材证据不匹配。');
   abort_unless($in===0||($headRequested&&($e['can_trim_head']??false)),422,'片头默认保留；需要明确提出并有对应的音画安全证据才能调整。');
   abort_unless($out===$n||($e['can_trim_tail']??false),422,'这段缺少可信的片尾裁剪证据，需保留原长度。');
   foreach($e['protected_intervals']??[] as $protected)abort_unless($in<=$protected['in_frame']&&$out>=$protected['out_frame'],422,'方案会裁掉受保护的口播、动作或必要画面，请重新讨论。');
   foreach(MarketingEditingLocalMedia::audioProtection($e) as $protected)abort_unless($in<=$protected['in_frame']&&$out>=$protected['out_frame'],422,'实际音频活动仍未结束，不能依据漏识别的台词裁掉这部分原声。');
   $minimum=min($n,max(24,(int)ceil(mb_strwidth($source['subtitle'])/16*24),(int)($e['minimum_frames']??0)));
   abort_unless($out-$in>=$minimum,422,'裁剪后字幕或产品展示时间不足。');
   $scenes[]=array_merge($source,['source_frames'=>$n,'in_frame'=>$in,'out_frame'=>$out,'frames'=>$out-$in,'analysis_id'=>$row['record']->id,'analysis_digest'=>$e['digest'],'protected_intervals'=>$e['protected_intervals'],'reason'=>$choice['reason']]);$digests[]=$e['digest'];$total+=$out-$in;
   if($i===count($ids)-1){$acoustic=MarketingEditingLocalMedia::audioProtection($e);$speechEnd=max((int)($e['speech_end_frame']??0),$acoustic?max(array_column($acoustic,'out_frame'))-9:0);abort_unless(is_int($candidate['cta_hold_frames'])&&$out-max($in,$speechEnd)>=$candidate['cta_hold_frames'],422,'结尾完整口播之后，没有足够的现有画面满足所选停留；请缩短停留或另行准备素材。');}
  }
  abort_unless($total>0&&$total<=2880,422);
  if($candidate['duration_policy']==='exact')abort_unless(is_int($candidate['target_frames']??null)&&$candidate['target_frames']===$total,422,'指定时长与当前裁剪不相容；不能自动定格、循环或拉伸补时。');
  return ['version'=>self::VERSION,'project_id'=>$p->id,'base_film_id'=>$base->id,'base_output_sha256'=>$base->output_sha256,'root_film_id'=>$root->id,'source_manifest_fingerprint'=>$root->fingerprint,'analysis_digest'=>MarketingEditingDigest::hash($digests),'scenes'=>$scenes,'joins'=>$fresh['input']['joins'],'cta'=>$fresh['input']['cta'],'cta_hold_frames'=>$candidate['cta_hold_frames'],'total_frames'=>$total,'fps'=>24,'duration'=>$total/24,'duration_policy'=>$candidate['duration_policy'],'publication_authorized'=>false];
 }
 public function compile(MarketingEditPlan $plan):array {
  $p=MarketingProject::where('owner_id',$plan->owner_id)->findOrFail($plan->project_id);$base=MarketingVideoFilm::where('project_id',$p->id)->where('owner_id',$p->owner_id)->findOrFail($plan->base_film_id);abort_unless(hash_equals($base->output_sha256??'',$plan->base_sha256),409,'基准成片已变化。');
  $message=Message::where('project_id',$p->id)->findOrFail($plan->message_id);$ids=$plan->analysis_ids;$found=Analysis::where('project_id',$p->id)->whereIn('id',$ids)->get()->keyBy('id');$analyses=array_map(fn($id)=>$found->get($id),$ids);abort_if(in_array(null,$analyses,true),409);
  $recipe=$this->recipe($p,$base,$plan->candidate,$analyses,(bool)($message->context['head_trim_requested']??false));$recipe['plan_id']=$plan->id;
  $fingerprint=MarketingEditingDigest::hash($recipe);abort_unless(hash_equals($plan->fingerprint,$fingerprint)&&MarketingEditingDigest::hash($plan->recipe)===$fingerprint,409,'剪辑方案或证据已变化，请重新讨论。');return ['input'=>$recipe,'fingerprint'=>$fingerprint];
 }
 public function adopt(string $project,int $owner,string $id,string $requestId,string $baseId,string $baseHash):MarketingVideoFilm {
  abort_unless(config('marketing.editing.enabled'),403);
  return DB::transaction(function()use($project,$owner,$id,$requestId,$baseId,$baseHash){
   app(MarketingClipSource::class)->project($project,$owner);MarketingProject::whereKey($project)->lockForUpdate()->firstOrFail();$plan=MarketingEditPlan::where('project_id',$project)->where('owner_id',$owner)->lockForUpdate()->findOrFail($id);
   abort_unless($baseId===$plan->base_film_id&&hash_equals($plan->base_sha256,$baseHash),409,'请先切回此方案对应的基准成片。');
   if($plan->status==='accepted')return MarketingVideoFilm::where('project_id',$project)->where('owner_id',$owner)->findOrFail($plan->film_id);
   abort_unless($plan->status==='pending',409,'这份方案已失效或已被新版替代。');abort_if(MarketingEditPlan::where('project_id',$project)->where('accepted_request_id',$requestId)->whereKeyNot($id)->exists(),409);
   $built=$this->compile($plan);$base=MarketingVideoFilm::findOrFail($baseId);
   $film=MarketingVideoFilm::firstOrCreate(['project_id'=>$project,'fingerprint'=>$built['fingerprint']],['owner_id'=>$owner,'revision_id'=>$base->revision_id,'status'=>'queued','input'=>$built['input'],'result'=>['composition_external_api_cost'=>0,'new_generation_requests'=>0,'edit_plan_id'=>$plan->id,'publication_authorized'=>false]]);
   $plan->update(['status'=>'accepted','accepted_request_id'=>$requestId,'film_id'=>$film->id]);if($film->wasRecentlyCreated)ProcessMarketingWorkspaceVideo::dispatch('film',$film->id);return $film;
  });
 }
 public function reject(string $project,int $owner,string $id):MarketingEditPlan {return DB::transaction(function()use($project,$owner,$id){app(MarketingClipSource::class)->project($project,$owner);MarketingProject::whereKey($project)->lockForUpdate()->firstOrFail();$p=MarketingEditPlan::where('project_id',$project)->where('owner_id',$owner)->lockForUpdate()->findOrFail($id);abort_unless(in_array($p->status,['pending','rejected']),409);if($p->status==='pending')$p->update(['status'=>'rejected']);return $p;});}
 public function assertFilmCurrent(MarketingVideoFilm $film):MarketingProject {
  $plan=MarketingEditPlan::where('project_id',$film->project_id)->where('owner_id',$film->owner_id)->where('film_id',$film->id)->where('status','accepted')->findOrFail($film->input['plan_id']??null);$fresh=$this->compile($plan);abort_unless(hash_equals($film->fingerprint,$fresh['fingerprint'])&&MarketingEditingDigest::hash($film->input)===$fresh['fingerprint'],409,'成片来源或剪辑方案已变化。');return app(MarketingClipSource::class)->project($film->project_id,$film->owner_id);
 }
}
