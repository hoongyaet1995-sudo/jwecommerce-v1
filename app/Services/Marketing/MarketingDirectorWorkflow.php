<?php
namespace App\Services\Marketing;

use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\MarketingAiRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingDirectorWorkflow
{
 public function digest(MarketingProject $p,MarketingRevision $r):string
 {
  return hash('sha256',json_encode([$r->id,$r->document['scenes'],$r->document['duration'],$r->document['cta'],$p->brief,$p->snapshot,$p->preferences,$p->concepts[$p->selected_concept]??null,$p->assets()->whereIn('id',collect($r->document['scenes'])->pluck('asset_ids')->flatten()->all())->orderBy('id')->get(['id','authorized'])->toArray()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 }
 public function draft(string $id,int $actor,int $version,string $sourceRevision,string $requestId,string $action='director',string $feedback=''):MarketingRevision
 {
  if($action==='transitions')@set_time_limit(450);
  $start=DB::transaction(function()use($id,$actor,$version,$sourceRevision,$requestId,$action,$feedback){
   abort_unless(in_array($action,['director','material_plan','transitions'],true),422);
   abort_if(mb_strlen($feedback)>2000,422);
   $p=MarketingProject::where('owner_id',$actor)->lockForUpdate()->findOrFail($id);
   $existing=MarketingAiRun::where('project_id',$id)->where('request_id',$requestId)->first();
   if($existing){abort_unless($existing->action===$action&&$existing->feedback===$sourceRevision&&$existing->status==='completed'&&($existing->usage['instruction_digest']??hash('sha256',''))===hash('sha256',$feedback),409,'请求已记录，请检查结果后再操作。');return ['existing'=>MarketingRevision::where('project_id',$id)->where(match($action){'material_plan'=>'context->material_plan_run_id','transitions'=>'context->transitions_run_id',default=>'context->director_run_id'},$existing->id)->firstOrFail()];}
   $p=app(MarketingWorkflow::class)->locked($id,$actor,$version);
   abort_unless((config('marketing.billing.enabled')||config('marketing.conversation.open_testing'))&&app(MarketingConversation::class)->ready($p)&&($p->planning_state['status']??'')==='confirmed',422,'请先确认策划摘要并开启文字AI。');
   abort_unless($p->selected_concept!==null&&$p->current_revision_id===$sourceRevision,422,'请先选定故事并保存当前叙事分镜。');
   $r=MarketingRevision::where('project_id',$id)->findOrFail($sourceRevision);
   abort_unless(($r->context['brief']??null)===$p->brief&&($r->context['concept']??null)===($p->concepts[$p->selected_concept]??null)&&($r->context['snapshot']??null)===$p->snapshot,409,'分镜的来源资料已改变，请先更新分镜。');
   app(MarketingWorkspaceCreative::class)->assertSelectedForm($p,$p->concepts[$p->selected_concept]);
   $authorized=$p->assets()->where('authorized',true)->pluck('id');
   abort_if(collect($r->document['scenes'])->pluck('asset_ids')->flatten()->diff($authorized)->isNotEmpty(),422,'原分镜引用的素材尚未获得授权。');
   if($action==='material_plan')abort_unless(isset($r->document['director_plan']),422,'请先保存导演稿。');
   $facts=['source_revision_id'=>$r->id,'source_digest'=>match($action){'material_plan'=>app(MarketingMaterialPackage::class)->digest($p,$r),'transitions'=>app(MarketingTransitionPlanning::class)->digest($p,$r),default=>$this->digest($p,$r)},'duration'=>(int)$r->document['duration'],'scenes'=>$r->document['scenes'],'director_plan'=>$r->document['director_plan']??null,'assets'=>$p->assets()->get(['id','name','authorized','mime'])->toArray()];
   $context['creative_tone']=app(MarketingVideoTone::class)->direction($p->brief['tone']??null);
   if($action==='transitions'){abort_unless(count($r->document['scenes'])>1&&isset($r->document['director_plan'])&&($r->context['director_review']['plan_digest']??'')===hash('sha256',json_encode($r->document['director_plan'],JSON_THROW_ON_ERROR)),422,'先采用导演稿，多段故事才需要跨段设计。');$facts['video_type']=$p->concepts[$p->selected_concept]['video_type']??null;$facts['frame_inventory']=app(MarketingTransitionPlanning::class)->inventory($p,$r);unset($facts['assets']);$facts['reference_evidence']=app(MarketingTransitionPlanning::class)->referenceEvidence($p,$r);}
   if(config('marketing.billing.enabled'))app(MarketingPlanningAllowance::class)->assertAvailable($id);
   $run=MarketingAiRun::create(['project_id'=>$id,'request_id'=>$requestId,'action'=>$action,'feedback'=>$sourceRevision,'status'=>'running','reserved_usd'=>config('marketing.billing.enabled')?0.02:0,'usage'=>['instruction_digest'=>hash('sha256',$feedback)]]);
   $p->update(['busy_run_id'=>$run->id,'status'=>'processing','last_error'=>null,'version'=>$p->version+1]);
   return compact('p','r','facts','run');
  });
  if(isset($start['existing']))return $start['existing'];
  ['p'=>$p,'r'=>$r,'facts'=>$facts,'run'=>$run]=$start;
  try{
   $pairReviews=$action==='transitions'?app(MarketingFramePairReview::class)->inspect($p,$r,$run):[];
   $context=['project_id'=>$id,'run_id'=>$run->id,'workspace_planning'=>true,'brief'=>$p->brief,'preferences'=>$p->preferences,'concept'=>$p->concepts[$p->selected_concept],'snapshot'=>$action==='material_plan'?\Illuminate\Support\Arr::only($p->snapshot,['title','description','subtitle','images']):['title'=>$p->snapshot['title']??''],'director_facts'=>$facts];
   $context['creative_tone']=app(MarketingVideoTone::class)->direction($p->brief['tone']??null);
   if($action==='transitions'){
    $context['concept']=\Illuminate\Support\Arr::only($context['concept'],['title','video_type','visual_treatment']);$context['brief']=\Illuminate\Support\Arr::only($p->brief,['language','cta']);$context['preferences']=\Illuminate\Support\Arr::only($p->preferences??[],['video_type','narration','subtitles','voice']);
    $context['director_facts']['scenes']=array_map(fn($scene)=>\Illuminate\Support\Arr::only($scene,['duration','visual','voiceover']),$facts['scenes']);
    $context['director_facts']['director_plan']=['shots'=>array_map(fn($shot)=>\Illuminate\Support\Arr::only($shot,['scene_number','start','end','purpose','subject','framing','motion','lighting','continuity','beats','checks']),$facts['director_plan']['shots'])];
    $context['director_facts']['frame_inventory']=array_map(function($f){$f['saved_design']=\Illuminate\Support\Arr::only($f['saved_design'],['subject','composition','frame_state']);return $f;},$facts['frame_inventory']);
   }
   $result=app(MarketingWriter::class)->propose($context,$action,$feedback!==''?$feedback:'Design only the saved storyboard. Do not generate media.');
   // Independently validate even if a writer adapter returns an unchecked object.
   $plan=$action==='transitions'?app(MarketingTransitionContract::class)->validate(array_intersect_key($result,array_flip(['summary','boundaries','notes'])),$facts)+['schema_version'=>MarketingTransitionContract::VERSION,'source_revision_id'=>$r->id,'source_digest'=>$facts['source_digest'],'analysis_basis'=>'saved_design_and_verified_reference_metadata_with_separate_pixel_pair_reviews','pair_reviews'=>$pairReviews,'assessed_frames'=>array_map(fn($f)=>array_intersect_key($f,array_flip(['asset_id','sha256'])),$facts['frame_inventory'])]:($action==='material_plan'?app(MarketingMaterialPackage::class)->build($result,$facts,isset($r->document['material_package'])?app(MarketingMaterialPackage::class)->inspectReferences($p,$r->document['material_package']):null):app(MarketingDirectorContract::class)->validate(array_intersect_key($result,array_flip(['summary','shots','materials','notes'])),$facts));
   return DB::transaction(function()use($id,$actor,$p,$r,$facts,$run,$plan,$action){
    $current=MarketingProject::lockForUpdate()->findOrFail($id);
    abort_unless($current->busy_run_id===$run->id&&$current->version===$p->version&&$current->current_revision_id===$r->id&&(match($action){'material_plan'=>app(MarketingMaterialPackage::class)->digest($current,$r),'transitions'=>app(MarketingTransitionPlanning::class)->digest($current,$r),default=>$this->digest($current,$r)})===$facts['source_digest'],409,'来源已变化，本导演稿未采用。');
    $document=$r->document;$document[match($action){'material_plan'=>'material_package','transitions'=>'transition_plan',default=>'director_plan'}]=$plan;if($action==='director')unset($document['material_package'],$document['transition_plan']);
    $context=$r->context;if($action==='director')unset($context['director_review'],$context['transition_review'],$context['transition_adoption']);if($action==='transitions')unset($context['transition_review'],$context['transition_adoption']);unset($context['production_strategy'],$context['production_change'],$context['dynamic_source']);$context[match($action){'material_plan'=>'material_plan_run_id','transitions'=>'transitions_run_id',default=>'director_run_id'}]=$run->id;$context['production_recipe']='pending-tool-routing';$context['material_policy']='ai-prepared-v1';
    $new=MarketingRevision::create(['project_id'=>$id,'actor_id'=>$actor,'number'=>(int)$current->revisions()->max('number')+1,'document'=>$document,'context'=>$context,'parent_id'=>$r->id,'feedback'=>match($action){'material_plan'=>'结构化素材计划；未生成图片，未授权制作','transitions'=>'跨段衔接设计；原分镜、导演和生产图保留，未授权制作',default=>'导演设计；原故事与口播不变，未授权制作'}]);
    $current->update(['current_revision_id'=>$new->id,'confirmed_revision_id'=>null,'busy_run_id'=>null,'status'=>'needs_confirmation','stage'=>'storyboard','version'=>$current->version+1]);$run->update(['status'=>'completed']);return $new;
   });
  }catch(\Throwable $e){
   $reason=$e instanceof \App\Services\AI\ProviderFailure ? $e->reason : ($e instanceof ValidationException ? 'validation' : ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode()===409 ? 'source_changed' : 'internal'));
   $message=$e instanceof ValidationException ? mb_substr(implode(' ',array_map(fn($v)=>$v[0]??'',$e->errors())),0,180) : match($reason){
    'timeout'=>'AI生成等待超时，原分镜保留。未收到完整结果，无法确认本次用量。',
    'connection'=>'未能连接AI服务，原分镜保留。',
    'balance'=>'AI余额不足，请top up；没有自动充值。',
    'quota'=>'AI服务暂时限流，请稍后再试；原分镜保留。',
    'authentication'=>'AI连接授权失效，请检查AI连接设置；原分镜保留。',
    'schema'=>'导演稿输出格式被AI服务拒绝，需要修复格式后再试；原分镜保留。',
    'source_changed'=>'生成期间项目资料已变化，本导演稿未采用；请重新检查当前分镜。',
    'unavailable','response'=>'AI服务未返回可用结果，原分镜保留。',
    default=>'导演稿处理异常，原分镜保留；诊断编号：'.$run->id,
   };
   $diagnostic=['reason'=>$reason,'type'=>class_basename($e)];
   logger()->warning('Marketing director failed',['run_id'=>$run->id]+$diagnostic);
   DB::transaction(function()use($id,$run,$message,$diagnostic){$p=MarketingProject::lockForUpdate()->findOrFail($id);if($p->busy_run_id===$run->id)$p->update(['busy_run_id'=>null,'status'=>'failed','last_error'=>mb_substr($message,0,160),'version'=>$p->version+1]);$run->refresh()->update(['status'=>'failed','error'=>$message,'usage'=>array_replace($run->usage??[],['failure'=>$diagnostic])]);});
   throw ValidationException::withMessages(['director'=>$message.' 未自动重试。']);
  }
 }
 public function assertReady(MarketingProject $p,MarketingRevision $r):void
 {
  $plan=$r->document['director_plan']??null;
  if(!$plan){abort_if(($r->context['workspace_planning']??false)||($r->context['material_policy']??'')==='ai-prepared-v1',422,'请先完成导演稿与生产素材包，不能跳过素材制备。');return;}
  $package=app(MarketingMaterialPackage::class)->assertFresh($p,$r);
  abort_if(collect($package['outputs'])->contains(fn($output)=>($output['status']??'')!=='approved'||empty($output['generation_run_id'])||empty($output['asset_id'])||empty($output['approved_at'])),422,'生产素材仍待AI制备与审阅；参考图不能直接用于视频制作。');
  $review=$r->context['director_review']??null;
  abort_unless($review&&($review['plan_digest']??'')===hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR)),422,'请先审阅并采用当前镜头设计。');
  $source=MarketingRevision::where('project_id',$p->id)->findOrFail($plan['source_revision_id']);
  abort_unless($this->digest($p,$source)===$plan['source_digest'],409,'镜头设计的来源或素材权限已变化。');
  // Current structured requirements and approved generated files supersede legacy free-text material gaps.

 } public function confirm(string $id,int $actor,int $version,string $revision):void
 {
  DB::transaction(function()use($id,$actor,$version,$revision){
   $p=app(MarketingWorkflow::class)->locked($id,$actor,$version);abort_unless($p->current_revision_id===$revision,409);
   $r=MarketingRevision::where('project_id',$id)->findOrFail($revision);$plan=$r->document['director_plan']??null;abort_unless($plan,422,'请先生成导演稿。');
   $source=MarketingRevision::where('project_id',$id)->findOrFail($plan['source_revision_id']);abort_unless($this->digest($p,$source)===$plan['source_digest'],409,'来源或素材授权已改变，请重新审阅。');
   // Material collection and production approval remain separate; design adoption can retain explicit missing materials.
   $context=$r->context;$context['director_review']=['actor_id'=>$actor,'at'=>now()->toIso8601String(),'plan_digest'=>hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR))];$r->update(['context'=>$context]);$p->update(['version'=>$p->version+1]);
  });
 }
}
