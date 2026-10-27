<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;
class MarketingSubtitleDiscussion {
 public function requested(string $text):bool{return (bool)preg_match('/逐词|短语弹出|普通字幕|动态字幕|字幕.{0,12}(高亮|样式|颜色|字号|位置|大一点|小一点|上移|弹出|活泼)|caption|subtitle\s*(style|highlight)/iu',$text);}
 public function context(MarketingVideoFilm $f):array{return ['supported'=>['plain','highlight','phrase'],'scenes'=>array_map(fn($s)=>array_intersect_key($s,array_flip(['number','title','start_frame','end_frame','spoken_text','original_subtitle'])),app(MarketingSubtitleService::class)->scenes($f)),'settings'=>$f->input['subtitles']['settings']??null,'scope'=>'Typed subtitle style suggestions only; actual local alignment prepared in review dialog. No wording edits, translation, chat bubbles or new frames. Scope all or scene by actual scene number. Original audio/music/end card preserved; user adopts before rendering.'];}
 public function suggestion(MarketingVideoFilm $f,array $candidate):array{
  $s=app(MarketingSubtitleService::class);$settings=$s->settings(array_replace(['mode'=>'highlight','size'=>'medium','position'=>'lower','color'=>'#d2f36b','scope'=>'all','scene'=>1],$f->input['subtitles']['settings']??[],$candidate));
  abort_unless(isset($s->scenes($f)[$settings['scene']-1]),422,'字幕建议引用不存在的段落。');
  return ['base_film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'settings'=>$settings];
 }
}
