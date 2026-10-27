<?php
namespace App\Services\Marketing;
use App\Models\{MarketingVideoFilm,MarketingProject};use Illuminate\Support\Facades\Storage;
class MarketingOptimizationEvidence {
 public function rows(MarketingVideoFilm $f):array{$c=app(MarketingSubtitleService::class)->config($f);return $c['alignment']['scenes']??$c['scenes'];}
 public function outputFrame(int $frame,array $rows,?array $speed):int{
  if(!$speed)return $frame;$before=0;foreach($rows as $s){if($s['number']===$speed['scene_number']){return $frame<$before?$frame:$before+app(MarketingSpeed::class)->map($frame-$before,$speed);}$before+=$s['frames'];}return $frame;
 }
 public function collect(MarketingVideoFilm $f,string $runId):array {
  $files=app(MarketingVideoFiles::class);$path=$files->file($f->output_path,$f->output_sha256);$rows=$this->rows($f);$state=app(MarketingOptimizationPlan::class)->baseline($f);$sourceCount=array_sum(array_column($rows,'frames'));$total=(int)round($f->input['duration']*24);$points=[];
  foreach($rows as $s)foreach([min(24,$s['frames']-1),(int)floor($s['frames']*.7)] as $n)$points[]=$this->outputFrame($s['start_frame']+$n,$rows,$state['speed']);
  $anchors=$points;
  foreach(['overlays','information_cards','emphasis','framing'] as $cap)foreach($state[$cap] as $e){$s=collect($rows)->firstWhere('number',$e['scene_number']);if($s){$points[]=$this->outputFrame($s['start_frame']+$e['start_frame'],$rows,$state['speed']);$points[]=$this->outputFrame($s['start_frame']+(int)floor(($e['start_frame']+$e['end_frame'])/2),$rows,$state['speed']);}}
  $content=(int)($f->input['content_frames']??$total);if($total>$content){$points[]=$content+min(24,$total-$content-1);$anchors[]=$content+min(24,$total-$content-1);}$points=array_values(array_unique(array_map(fn($n)=>max(0,min($total-1,$n)),$points)));sort($points);
  // Max 36 samples, preserving all scene anchors and up to12 layer event samples.
  if(count($points)>36){$anchors=array_values(array_unique(array_map(fn($n)=>max(0,min($total-1,$n)),$anchors)));$events=array_values(array_diff($points,$anchors));$slots=36-count($anchors);$extra=$slots>0?array_map(fn($n)=>$events[(int)round($n*(count($events)-1)/max(1,$slots-1))],range(0,$slots-1)):[];$points=array_values(array_unique(array_merge($anchors,$extra)));sort($points);}
  $disk=Storage::disk('local');$dir='marketing-optimization/'.$runId;$disk->makeDirectory($dir);$samples=[];
  foreach($points as $n=>$frame){$relative=$dir.'/sample-'.($n+1).'.jpg';app(MarketingMediaTools::class)->run([(string)config('marketing.production.speed_ffmpeg_binary'),'-v','error','-y','-ss',sprintf('%.6f',$frame/24),'-i',$path,'-frames:v','1','-vf','scale=576:1024',$disk->path($relative)],60,true);$samples[]=['id'=>'S'.($n+1),'frame'=>$frame,'seconds'=>round($frame/24,3),'path'=>$relative,'sha256'=>hash_file('sha256',$disk->path($relative))];}
  $tools=app(MarketingMediaTools::class);$meta=$tools->probe($path);$audio=['provided_to_model'=>false,'has_audio'=>collect($meta['streams']??[])->contains('codec_type','audio'),'mix'=>$f->result['render']['audio_mix']??null,'limits'=>'Local signal metrics only; model has not listened. Loudness alone does not prove intelligibility or musical quality.'];
  if($audio['has_audio']){$scan=$tools->runCaptured([(string)config('marketing.production.speed_ffmpeg_binary'),'-hide_banner','-i',$path,'-vn','-af','volumedetect','-f','null','-'],90);foreach(['mean_volume','max_volume'] as $key)if(preg_match('/'.$key.':\s*(-?[\d.]+) dB/',$scan['stderr'],$m))$audio[$key.'_dbfs']=(float)$m[1];}
  $p=MarketingProject::findOrFail($f->project_id);$assets=app(MarketingOverlays::class)->catalogue($p);$assets=array_map(fn($a)=>array_intersect_key($a,array_flip(['id','name'])),$assets);
  $brief=['name'=>$p->name??$p->title??'','confirmed_cta'=>$f->input['cta']??'','director'=>[]];foreach($f->input['scenes'] as $s){$c=\App\Models\MarketingVideoClip::findOrFail($s['clip_id']);$brief['director'][]=['scene_number'=>$c->scene_number,'notes'=>mb_substr(json_encode($c->input['director']??[],JSON_UNESCAPED_UNICODE),0,700)];}
  $input=['base_film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'recipe_digest'=>MarketingEditingDigest::hash($f->input),'fps'=>24,'duration'=>$f->input['duration'],'scenes'=>array_map(fn($s)=>array_intersect_key($s,array_flip(['number','title','frames','start_frame','end_frame','spoken_text','words'])),$rows),'brief'=>$brief,'current'=>$state,'image_catalogue'=>$assets,'audio'=>$audio,'samples'=>$samples,'scope'=>'Finished rendered film samples at scene anchors and layer events, not all frames. Editing parameters use canonical SOURCE local frames; sample times use OUTPUT global seconds. No audio input. No continuous tracking.'];
  $disk->put($dir.'/evidence.json',json_encode($input,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));return $input;
 }
}
