<?php
namespace App\Services\Marketing;
use App\Models\{MarketingVideoFilm,MarketingVideoClip,MarketingClipAnalysis};
class MarketingFramingDiscussion {
 public function requested(string $text):bool{return (bool)preg_match('/构图|聚焦|推近|放大|拉近|缩放|zoom|framing|reframe/iu',$text);}
 public function context(MarketingVideoFilm $f):array{
  $rows=app(MarketingSubtitleService::class)->scenes($f);$out=[];
  foreach($rows as $row){$c=MarketingVideoClip::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->findOrFail($row['clip_id']);$a=MarketingClipAnalysis::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->where('clip_id',$c->id)->where('status','completed')->latest()->first();$e=$a?->evidence??[];
   $out[]=array_intersect_key($row,array_flip(['number','title','frames','spoken_text','original_subtitle']))+['director_excerpt'=>mb_substr(json_encode($c->input['director']??[],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),0,650),'saved_visual_observations'=>($e['raw_sha256']??null)===$row['raw_sha256']?mb_substr($e['vision']['observations']??'',0,650):'','visual_limit'=>'Existing sampled observations only; no new visual analysis, no subject bounding boxes or continuous tracking.'];
  }
  return ['scenes'=>$out,'current'=>$f->input['framing']??[],'limits'=>'Complete replacement array max12,one effect per actual scene. mode none/fixed/push,zoom1.00..1.30,x/y integer0..100 percentages,start_frame/end_frame LOCAL24fps min36frames. Preserve other existing effects unless explicitly resetting. Recommend modest zoom or none if composition risk. Fixed focus point,not tracking. Preset/manual preview required; never promise whole-person/product visibility.'];
 }
 public function suggestion(MarketingVideoFilm $f,array $candidate):array{
  return ['base_film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'settings'=>app(MarketingFraming::class)->normalize($candidate,app(MarketingSubtitleService::class)->scenes($f))];
 }
 public static function schema():array{
  $props=['scene_number'=>['type'=>'integer'],'mode'=>['type'=>'string','enum'=>['none','fixed','push']],'zoom'=>['type'=>'number'],'x'=>['type'=>'integer'],'y'=>['type'=>'integer'],'start_frame'=>['type'=>'integer'],'end_frame'=>['type'=>'integer']];
  return ['anyOf'=>[['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'required'=>array_keys($props),'properties'=>$props]],['type'=>'null']]];
 }
}
