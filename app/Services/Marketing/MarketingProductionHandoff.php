<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingRevision,MarketingAiRun};
/** Local immutable input contract. No provider calls, URLs, pricing or dispatch. */
class MarketingProductionHandoff
{
 public const VERSION='director-materials-v2';
 public function applies(MarketingRevision $r):bool{return isset($r->document['director_plan'])||isset($r->document['material_package'])||($r->context['workspace_planning']??false)||($r->context['material_policy']??'')==='ai-prepared-v1';}
 public function build(MarketingProject $p,MarketingRevision $r):array{
  abort_unless($p->current_revision_id===$r->id&&$p->confirmed_revision_id===$r->id&&$p->status==='prepared'&&$r->confirmed_by===$p->owner_id&&$r->confirmed_at&&!$p->busy_run_id,409,'请先确认当前分镜与素材版本，再进行制作预检。');
  abort_unless(($r->context['brief']??null)===$p->brief&&($r->context['snapshot']??null)===$p->snapshot&&($r->context['concept']??null)===($p->concepts[$p->selected_concept]??null),409,'项目资料或创意已变化，请重新确认分镜。');
  app(MarketingDirectorWorkflow::class)->assertReady($p,$r);
  abort_if(count(app(MarketingRequirementReview::class)->pending($p,$r)['gaps'])>0,422,'仍有真实资料需要确认。');
  $pkg=app(MarketingMaterialPackage::class)->assertFresh($p,$r);$doc=$r->document;$count=count($doc['scenes']);
  abort_unless(count($pkg['outputs'])===$count*3&&count($doc['director_plan']['shots'])===$count,422,'导演段落或素材数量不完整。');
  $scenes=[];foreach($doc['scenes'] as $i=>$scene){$frames=[];foreach(['start_frame','end_frame','extra_image'] as $role){$matches=collect($pkg['outputs'])->filter(fn($o)=>$o['scene_number']===$i+1&&$o['role']===$role);abort_unless($matches->count()===1,422,'每段需要且只能有一张首帧、尾帧及补充图。');$o=$matches->first();$run=MarketingAiRun::where('project_id',$p->id)->findOrFail($o['generation_run_id']);$record=collect($run->usage['outputs'])->firstWhere('id',$o['id']);
    abort_unless(($record['sha256']??null)===$o['sha256']&&($record['design']['frame_role']??null)===$role&&($record['design']['scene_number']??null)===$i+1,422,'生产图角色或校验记录不一致。');
    $qa=null;if(!empty($run->usage['auto_review'])){$qa=$record['qa']??null;abort_unless(($qa['verdict']??'')==='pass'&&($qa['candidate_sha256']??null)===$o['sha256']&&($qa['design_sha256']??null)===hash('sha256',json_encode($record['design'],JSON_THROW_ON_ERROR)),422,'自动质检证据不匹配，不能交接生产图。');}
    $frames[$role]=['output_id'=>$o['id'],'asset_id'=>$o['asset_id'],'media_library_id'=>$o['media_library_id'],'sha256'=>$o['sha256'],'generation_run_id'=>$run->id,'approved_by'=>$o['approved_by'],'approved_at'=>$o['approved_at'],'design'=>$record['design'],'visual_review'=>$qa];
   }
   $scenes[]=['number'=>$i+1,'planned_seconds'=>$scene['duration'],'visual'=>$scene['visual'],'director'=>$doc['director_plan']['shots'][$i],'frames'=>$frames,'postproduction'=>['voiceover'=>$scene['voiceover']??'','subtitle'=>$scene['subtitle']??'','owner'=>'postproduction_compositor','graphics'=>array_values(array_filter($pkg['requirements'],fn($need)=>$need['kind']==='graphic'&&in_array($i+1,$need['scene_numbers'],true)))]];
  }
  abort_unless(array_sum(array_column($scenes,'planned_seconds'))===$doc['duration'],422,'分段时长与总时长不一致。');
  $transition=app(MarketingTransitionPlanning::class)->state($p,$r);
  $h=['schema_version'=>self::VERSION,'project_id'=>$p->id,'project_version'=>$p->version,'revision_id'=>$r->id,'revision_number'=>$r->number,'source_digest'=>$pkg['source_digest'],'director_digest'=>hash('sha256',json_encode($doc['director_plan'],JSON_THROW_ON_ERROR)),'preferences'=>$p->preferences,'ratio'=>$p->concepts[$p->selected_concept]['ratio']??null,'language'=>$p->brief['language']??null,'duration'=>$doc['duration'],'cta'=>$doc['cta'],'scenes'=>$scenes,'transitions'=>['status'=>$transition['status'],'ready'=>$transition['ready'],'boundaries'=>$transition['plan']['boundaries']??[]]];
  $h['digest']=hash('sha256',json_encode($h,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));return $h;
 }
 public function compile(MarketingProject $p,MarketingRevision $r):array{
  $h=$this->build($p,$r);return ['schema_version'=>1,'recipe'=>self::VERSION,'execution_profile'=>'preflight-only','handoff'=>$h,'ratio'=>$h['ratio'],'planned_seconds'=>$h['duration'],'summary'=>$r->document['summary'],'cta'=>$h['cta'],'scenes'=>$h['scenes'],'audio'=>['requests'=>0,'new_requests'=>0],'budget'=>['currency'=>'USD','cap_microusd'=>null,'supplier_estimate_usd'=>null,'kind'=>'not_quoted','ai_video_requests'=>0],'blockers'=>array_merge($h['transitions']['ready']?[]:['跨段衔接设计或相邻首尾帧复用尚未确认，请返回Step 3。'],['逐段素材与导演稿本地预检通过；供应商视频路线、音频与后期适配尚未接通，暂不能报价或开始制作。']),'execution'=>'not_dispatched','publication_authorized'=>false];
 }
 public function preview(MarketingProject $p):array{
  try{$r=$p->currentRevision;abort_unless($r,422,'先完成分镜与素材。');return ['handoff'=>$this->build($p,$r),'error'=>null];}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){return ['handoff'=>null,'error'=>$e->getMessage()?:'当前素材尚不能交接，请返回Step 3检查。'];}
 }
}
