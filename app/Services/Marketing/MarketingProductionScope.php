<?php
namespace App\Services\Marketing;

/** Execution selection never edits the complete Step 3 handoff. */
class MarketingProductionScope
{
 public function select(array $handoff,?string $sceneId):array
 {
  if($sceneId===null)return $handoff;
  $scene=collect($handoff['scene_map'])->firstWhere('scene_id',$sceneId);
  abort_unless($scene,422,'试生成分镜不属于当前交接版本。');
  $out=$handoff;$out['scene_map']=[$scene];$out['transitions']=[];
  $out['timeline']=[['scene_id'=>$sceneId,'start'=>0,'end'=>$scene['seconds']]];
  $out['timing']=app(MarketingStoryboardTiming::class)->calculate([$scene],['requested_seconds'=>$scene['seconds']]);
  $out['director_plan']['shots']=array_values(array_filter($handoff['director_plan']['shots'],fn($s)=>$s['scene_id']===$sceneId));
  $out['frame_inventory']=array_values(array_filter($handoff['frame_inventory'],fn($s)=>$s['scene_id']===$sceneId));
  return $out;
 }
 public function forPlan(\App\Models\MarketingProductionPlan $plan):array
 {
  $full=$plan->manifest['handoff'];$scope=$plan->manifest['scope']??['mode'=>'whole_film'];
  if($scope['mode']==='whole_film')return $full;
  abort_unless($scope['mode']==='scene_trial'&&count($scope['scene_ids']??[])===1,409,'制作范围无效。');
  $execution=$this->select($full,$scope['scene_ids'][0]);
  abort_unless(($plan->manifest['execution_handoff']??null)===$execution&&array_column($plan->manifest['clips'],'scene_id')===$scope['scene_ids'],409,'试生成范围或素材已变化。');
  return $execution;
 }
}
