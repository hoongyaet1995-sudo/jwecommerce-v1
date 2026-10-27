<?php
namespace App\Services\Marketing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
/** Canonical source-time recipe. A fresh export always retimes from source, never compounds speed. */
class MarketingSpeed {
 public function normalize(?array $p,array $rows):?array {
  if(!$p)return null;
  $d=Validator::make(['p'=>$p],['p'=>'array:scene_number,start_frame,end_frame,rate,protect','p.scene_number'=>'required|integer|min:1|max:12','p.start_frame'=>'required|integer|min:0','p.end_frame'=>'required|integer|min:12','p.rate'=>'required|numeric|in:0.5,1,1.5,2','p.protect'=>'required|boolean'])->validate()['p'];
  $s=collect($rows)->firstWhere('number',(int)$d['scene_number']);
  if(!$s||$d['end_frame']>$s['frames']||$d['end_frame']-$d['start_frame']<12)throw ValidationException::withMessages(['speed'=>'请选择有效分镜及至少0.50秒的范围。']);
  if($d['protect']&&(float)$d['rate']!==1.0&&trim($s['spoken_text']??'')!==''){
   $words=$s['words']??[];if(!$words||collect($words)->contains(fn($w)=>preg_match('/[\p{L}\p{N}]/u',$w['text'])&&$w['start_frame']<$d['end_frame']&&$w['end_frame']>$d['start_frame']))throw ValidationException::withMessages(['speed'=>'此区间包含口播或尚无可靠对齐。请先试听并明确允许口播变速，或保持原速。']);
  }
  $d['scene_number']=(int)$d['scene_number'];$d['start_frame']=(int)$d['start_frame'];$d['end_frame']=(int)$d['end_frame'];$d['rate']=(float)$d['rate'];$d['protect']=(bool)$d['protect'];return $d;
 }
 public function map(int $n,array $p):int{$a=$p['start_frame'];$b=$p['end_frame'];return (int)round($n<=$a?$n:($n<$b?$a+($n-$a)/$p['rate']:$n-($b-$a)+($b-$a)/$p['rate']));}
 public function frames(array $rows,?array $p):int{$sum=0;foreach($rows as $s)$sum+=($p&&$s['number']===$p['scene_number'])?$this->map($s['frames'],$p):$s['frames'];return $sum;}
 public function render(string $input,string $dir,array $rows,array $p):string {
  $scene=collect($rows)->firstWhere('number',$p['scene_number']);$start=0;foreach($rows as $s){if($s['number']===$p['scene_number'])break;$start+=$s['frames'];}
  $a=$start+$p['start_frame'];$b=$start+$p['end_frame'];$total=array_sum(array_column($rows,'frames'));$segments=[];if($a>0)$segments[]=[0,$a,1];$segments[]=[$a,$b,$p['rate']];if($b<$total)$segments[]=[$b,$total,1];
  $tools=app(MarketingMediaTools::class);$has=collect($tools->probe($input)['streams']??[])->contains('codec_type','audio');$filters=[];$joins='';
  foreach($segments as $n=>[$from,$to,$rate]){$frames=(int)round(($to-$from)/$rate);$filters[]="[0:v]trim=start_frame=$from:end_frame=$to,setpts=(PTS-STARTPTS)/$rate,fps=24,tpad=stop_mode=clone:stop_duration=1,trim=end_frame=$frames,setpts=PTS-STARTPTS[v$n]";if($has){$f=$from/24;$t=$to/24;$dur=$frames/24;$filters[]="[0:a]atrim=start=$f:end=$t,asetpts=PTS-STARTPTS,atempo=$rate,apad,atrim=duration={$dur}[a$n]";}$joins.="[v$n]".($has?"[a$n]":'');}
  $filters[]=$joins.'concat=n='.count($segments).':v=1:a='.($has?1:0).'[v]'.($has?'[a]':'');$out=$dir.'/speed-content.mp4';$args=[(string)config('marketing.production.speed_ffmpeg_binary'),'-v','error','-y','-i',$input,'-filter_complex',implode(';',$filters),'-map','[v]'];if($has)$args=array_merge($args,['-map','[a]','-c:a','aac','-b:a','192k','-ar','48000']);$args=array_merge($args,['-c:v','libx264','-crf','18','-preset','fast','-pix_fmt','yuv420p','-r','24','-frames:v',(string)$this->frames($rows,$p),'-movflags','+faststart',$out]);$tools->run($args,300,true);app(MarketingVideoFiles::class)->verifyFrames($out,$this->frames($rows,$p),24,$has);return $out;
 }
}
