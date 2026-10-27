<?php
namespace App\Services\Marketing;
use App\Models\{MarketingVideoFilm,MarketingVideoClip,MarketingClipAnalysis};
class MarketingOverlaysDiscussion {
 public function requested(string $text):bool{return (bool)preg_match('/多层排版|图片叠加|叠加.*图|图.*叠加|画中画|展示.*[三3]个产品|overlay|picture.in.picture/iu',$text);}
 public function context(MarketingVideoFilm $f):array{
  $rows=app(MarketingSubtitleService::class)->scenes($f);$out=[];
  foreach($rows as $row){$c=MarketingVideoClip::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->findOrFail($row['clip_id']);$a=MarketingClipAnalysis::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->where('clip_id',$c->id)->where('status','completed')->latest()->first();$e=$a?->evidence??[];
   $out[]=array_intersect_key($row,array_flip(['number','title','frames','spoken_text','original_subtitle']))+['director_excerpt'=>mb_substr(json_encode($c->input['director']??[],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),0,650),'saved_visual_observations'=>($e['raw_sha256']??null)===$row['raw_sha256']?mb_substr($e['vision']['observations']??'',0,650):'','visual_limit'=>'Existing sampled observations only; no new visual analysis, no subject bounding boxes or continuous tracking.'];
  }
  return ['images'=>array_map(fn($a)=>array_intersect_key($a,array_flip(['id','name'])),app(MarketingOverlays::class)->catalogue(\App\Models\MarketingProject::findOrFail($f->project_id))),'scenes'=>$out,'current'=>$f->input['overlays']??[],'limits'=>'Complete ordered array max3 images TOTAL. id unique alphanumeric/hyphen/underscore,image_id from provided catalogue,enabled boolean,scene_number actual,x/y0..100 screenpercent after framing,width15..55,radius0..48,border0..8,color hex,LOCAL integer start/end min36frames. Image must fit in screen. Array order back to front. Preserve existing layers unless requested replacing. Fixed location not tracking, preview occlusion. Preserve framing/emphasis/captions/cards/music/outro.'];
 }
 public function suggestion(MarketingVideoFilm $f,array $candidate):array{
  return ['base_film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'settings'=>app(MarketingOverlays::class)->normalize($candidate,app(MarketingSubtitleService::class)->scenes($f),\App\Models\MarketingProject::where('owner_id',$f->owner_id)->findOrFail($f->project_id))];
 }
 public static function schema():array{
  $props=['id'=>['type'=>'string'],'image_id'=>['type'=>'string'],'enabled'=>['type'=>'boolean'],'scene_number'=>['type'=>'integer'],'x'=>['type'=>'number'],'y'=>['type'=>'number'],'width'=>['type'=>'number'],'radius'=>['type'=>'integer'],'border'=>['type'=>'integer'],'color'=>['type'=>'string'],'start_frame'=>['type'=>'integer'],'end_frame'=>['type'=>'integer']];
  return ['anyOf'=>[['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'required'=>array_keys($props),'properties'=>$props]],['type'=>'null']]];
 }
}
