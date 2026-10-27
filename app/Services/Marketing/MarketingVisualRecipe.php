<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;
/** Resolves scoped caption ancestry to clean per-scene sources, never to burned-in information cards. */
class MarketingVisualRecipe {
 public function resolve(MarketingVideoFilm $base,?array $plan=null,array $seen=[]):array{
  $key=$base->id.':'.MarketingEditingDigest::hash($plan??($base->input['subtitles']??[]));abort_if(count($seen)>=24||in_array($key,$seen,true),409,'文字配方引用循环或过深。');$seen[]=$key;
  if($plan===null&&isset($base->input['visual_recipe']))return $base->input['visual_recipe'];
  $plan??=$base->input['subtitles']??null;
  if(!$plan)return array_map(fn($s)=>['scene'=>$s,'settings'=>['mode'=>'plain','size'=>'medium','position'=>'lower','color'=>'#d2f36b','scope'=>'all','scene'=>1],'original'=>true],app(MarketingSubtitleService::class)->scenes($base));
  app(MarketingSubtitleService::class)->assertPlan($base,$plan);$ref=MarketingVideoFilm::where('project_id',$base->project_id)->where('owner_id',$base->owner_id)->where('status','completed')->findOrFail($plan['reference_film_id']);$previous=null;$out=[];
  foreach($plan['scenes'] as $row){if($row['spoken_text']!==''&&($plan['settings']['scope']==='all'||$plan['settings']['scene']===$row['number'])){$out[]=['scene'=>$row,'settings'=>array_replace($plan['settings'],['scope'=>'all']),'original'=>false];}else{$previous??=$this->resolve($ref,null,$seen);$out[]=$previous[$row['number']-1];}}return $out;
 }
}
