<?php
namespace App\Services\Marketing;
use App\Models\{MarketingVideoFilm,MarketingVideoClip,MarketingClipAnalysis};
class MarketingEmphasisDiscussion {
 public function requested(string $text):bool{return (bool)preg_match('/内容强调|箭头|圈选|圈出|聚光|压暗|arrow|spotlight/iu',$text);}
 public function context(MarketingVideoFilm $f):array{
  $rows=app(MarketingSubtitleService::class)->scenes($f);$out=[];
  foreach($rows as $row){$c=MarketingVideoClip::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->findOrFail($row['clip_id']);$a=MarketingClipAnalysis::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->where('clip_id',$c->id)->where('status','completed')->latest()->first();$e=$a?->evidence??[];
   $out[]=array_intersect_key($row,array_flip(['number','title','frames','spoken_text','original_subtitle']))+['director_excerpt'=>mb_substr(json_encode($c->input['director']??[],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),0,650),'saved_visual_observations'=>($e['raw_sha256']??null)===$row['raw_sha256']?mb_substr($e['vision']['observations']??'',0,650):'','visual_limit'=>'Existing sampled observations only; no new visual analysis, no subject bounding boxes or continuous tracking.'];
  }
  return ['scenes'=>$out,'current'=>$f->input['emphasis']??[],'limits'=>'Complete replacement max12,one effect per actual scene. kind arrow/circle/spot,x/y integer5..95 screenpercent AFTER framing,size10..60 integer,angle0..360 integer,color hex,strength20..85 integer,LOCAL start/end min36frames. Fixed position NOT tracking. Preserve current other effects; approximate positions require preview. Preserve framing/captions/cards/music/outro.'];
 }
 public function suggestion(MarketingVideoFilm $f,array $candidate):array{
  return ['base_film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'settings'=>app(MarketingEmphasis::class)->normalize($candidate,app(MarketingSubtitleService::class)->scenes($f))];
 }
 public static function schema():array{
  $props=['scene_number'=>['type'=>'integer'],'kind'=>['type'=>'string','enum'=>['arrow','circle','spot']],'x'=>['type'=>'integer'],'y'=>['type'=>'integer'],'size'=>['type'=>'integer'],'angle'=>['type'=>'integer'],'color'=>['type'=>'string'],'strength'=>['type'=>'integer'],'start_frame'=>['type'=>'integer'],'end_frame'=>['type'=>'integer']];
  return ['anyOf'=>[['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'required'=>array_keys($props),'properties'=>$props]],['type'=>'null']]];
 }
}
