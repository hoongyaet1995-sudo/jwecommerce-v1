<?php
namespace App\Services\Marketing;

/** The image supplier executes this persisted design; it does not author scene facts. */
class MarketingStoryboardSceneDesign
{
 public const FIELDS=['environment','background','props','product','product_placement','person_placement','fact_boundary'];
 public function validate(array $scene,int $referenceCount):void
 {
  $d=$scene['scene_design']??[];
  foreach(self::FIELDS as $key)abort_unless(is_string($d[$key]??null)&&trim($d[$key])!==''&&mb_strlen($d[$key])<=4000,422,'场景设计尚未完整：'.$key);
  $indexes=$d['product_reference_indexes']??null;
  abort_unless(is_array($indexes)&&count($indexes)<=4&&count(array_unique($indexes,SORT_REGULAR))===count($indexes),422,'商品参考绑定无效。');
  foreach($indexes as $i)abort_unless(is_int($i)&&$i>=0&&$i<$referenceCount,422,'商品参考不存在。');
  abort_unless(($scene['frames']['start_frame']['required']??null)===true,422,'首帧必须准备，不能省略。');
  foreach(MarketingStoryboardPackage::ROLES as $role){
   $f=$scene['frames'][$role]??[];if(!($f['required']??false))continue;
   abort_unless(is_string($f['state']??null)&&trim($f['state'])!==''&&is_string($f['instruction']??null)&&trim($f['instruction'])!=='',422,'关键帧设计不完整。');
   $expected=match($role){'start_frame'=>$scene['director']['start_state']??null,'end_frame'=>$scene['director']['end_state']??null,default=>null};
   if($expected!==null)abort_unless($f['state']===$expected,422,'首尾帧状态与导演稿不一致。');
   else abort_unless(in_array($f['state'],array_column($scene['director']['beats']??[],'action'),true),422,'补充帧必须对应已保存的动作节拍。');
  }
  foreach($scene['director']['beats']??[] as $b)abort_unless($b['start']>=0&&$b['end']<=($scene['seconds']??0),422,'分镜动作超出本段时长。');
 }
}
