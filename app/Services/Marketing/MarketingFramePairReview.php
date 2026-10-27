<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingRevision,MarketingAiRun};
use Illuminate\Support\Facades\Storage;

/** Read-only pixel review. Never invokes image generation or changes an existing asset. */
class MarketingFramePairReview
{
 public const VERSION='scene-pairs-v1';
 public function digest(MarketingProject $p,MarketingRevision $r,int $scene):string{
  $frames=app(MarketingTransitionPlanning::class)->inventory($p,$r);$pkg=isset($r->document['material_package'])?app(MarketingMaterialPackage::class)->inspectReferences($p,$r->document['material_package']):['requirements'=>[]];
  return hash('sha256',json_encode([self::VERSION,$frames[$scene.'-start_frame']??null,$frames[$scene.'-end_frame']??null,$r->document['director_plan']['shots'][$scene-1]??null,$pkg['requirements']],JSON_THROW_ON_ERROR));
 }
 public function inspect(MarketingProject $p,MarketingRevision $r,MarketingAiRun $run):array{
  $planning=app(MarketingTransitionPlanning::class);$frames=$planning->inventory($p,$r);$pkg=isset($r->document['material_package'])?app(MarketingMaterialPackage::class)->inspectReferences($p,$r->document['material_package']):['requirements'=>[]];$reports=[];
  foreach($r->document['scenes'] as $i=>$scene){
   $n=$i+1;$digest=$this->digest($p,$r,$n);$old=$r->document['transition_plan']['pair_reviews'][$n]??null;
   if(($old['source_digest']??null)===$digest&&in_array($old['verdict']??'', ['pass','revise','needs_input'],true)){$reports[$n]=$old;continue;}
   $start=$frames[$n.'-start_frame']??null;$end=$frames[$n.'-end_frame']??null;
   if(($start['status']??'')!=='approved'||($end['status']??'')!=='approved'){$reports[$n]=['verdict'=>'needs_input','summary'=>'同段首尾图尚未就绪，未调用视觉检查。','source_digest'=>$digest,'version'=>self::VERSION];continue;}
   $images=[];
   foreach(['candidate'=>$end,'scene_start'=>$start] as $label=>$frame){$a=$p->assets()->where('authorized',true)->findOrFail($frame['asset_id']);$path=Storage::disk('local')->path($a->path);abort_unless(is_file($path)&&hash_file('sha256',$path)===$frame['sha256'],409,'首尾帧文件或授权已变化。');$images[$label]=$path;}
   foreach($pkg['requirements'] as $need){if(!in_array($n,$need['scene_numbers'],true)||!in_array($need['kind'],['real_person','product_view','brand_reference'],true)||empty($need['reference']))continue;
    $ref=$need['reference'];$path=match($ref['type']){'catalog_image'=>app(MarketingMaterialPackage::class)->catalogPath($p,$ref['index']),'project_asset'=>Storage::disk('local')->path($p->assets()->where('authorized',true)->findOrFail($ref['id'])->path),default=>null};if($path)$images[$need['kind'].'_'.$need['id']]=$path;
   }
   // Claim before each external call. A failed request is recorded, never silently retried.
   $u=$run->fresh()->usage;$u['pair_review_claims'][$n]=['status'=>'submitting','source_digest'=>$digest];$run->update(['usage'=>$u]);
   try{$report=app(MarketingMaterialVisionClient::class)->inspect(['frame_role'=>'end_frame','shot'=>$r->document['director_plan']['shots'][$i]??[], 'review_scope'=>'Compare actual scene_start and candidate pixels. Same-scene identity, wardrobe, visible product geometry and scene must remain consistent except explicitly directed changes. A different shirt color is a failure. Do not require identical pose; natural directed movement is allowed.'], $images);}
   catch(\Throwable $e){$u=$run->fresh()->usage;$u['pair_review_claims'][$n]['status']='unknown';$run->update(['usage'=>$u]);throw $e;}
   $report+=['source_digest'=>$digest,'version'=>self::VERSION,'checked_at'=>now()->toIso8601String()];$reports[$n]=$report;
   $u=$run->fresh()->usage;$u['pair_review_claims'][$n]['status']='completed';$u['pair_reviews'][$n]=$report;$run->update(['usage'=>$u]);
   abort_unless($this->digest($p->fresh(),$r,$n)===$digest,409,'质检期间素材或参考已变化，未采用结果。');
  }
  return $reports;
 }
}
