<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;
class MarketingSpeedDiscussion {
 public function requested(string $t):bool{return (bool)preg_match('/变速|倍速|加速|慢放|快一点|慢一点|speed|speedup|slow.?motion/iu',$t);}
 public function context(MarketingVideoFilm $f):array{$d=app(MarketingSubtitleService::class)->config($f);return ['current'=>$f->input['speed']??null,'scenes'=>array_map(fn($s)=>array_intersect_key($s,array_flip(['number','title','frames','spoken_text','words'])),$d['alignment']['scenes']??$d['scenes']),'limits'=>'One source-local interval only,minimum12frames, rates0.5/1/1.5/2. All other frames normal. Keep music normal speed,outro fixed. Protected speech must be explicitly auditioned and opted in by user in dialog; never claim executed. All timings SOURCE time, not sped output time. No automatic generation.'];}
 public function suggestion(MarketingVideoFilm $f,array $c):array{$d=app(MarketingSubtitleService::class)->config($f);$c['protect']=false;$p=app(MarketingSpeed::class)->normalize($c,$d['alignment']['scenes']??$d['scenes']);$p['protect']=true;return ['base_film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'settings'=>$p];}
 public static function schema():array{$p=['scene_number'=>['type'=>'integer'],'start_frame'=>['type'=>'integer'],'end_frame'=>['type'=>'integer'],'rate'=>['type'=>'number','enum'=>[0.5,1,1.5,2]]];return ['anyOf'=>[['type'=>'object','additionalProperties'=>false,'required'=>array_keys($p),'properties'=>$p],['type'=>'null']]];}
}
