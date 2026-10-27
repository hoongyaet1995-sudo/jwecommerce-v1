<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;
use Illuminate\Support\Facades\Storage;

/** Uses the exact existing content film, appends native brand graphics, mixes a separate local audio stem. */
class MarketingPackagingRenderer
{
 public function render(MarketingVideoFilm $film):array {
  app(MarketingVideoRenderer::class)->assertReady();$service=app(MarketingFilmPackaging::class);$p=$service->assertCurrent($film);$i=$film->input;$s=$i['settings'];$content=$service->content($film);$files=app(MarketingVideoFiles::class);$tools=app(MarketingMediaTools::class);$disk=Storage::disk('local');$base='marketing-films/'.$film->id;$disk->makeDirectory($base.'/public');$dir=$disk->path($base);
  if(isset($i['visual_recipe'],$i['visual_preserve']))return app(MarketingInformationRenderer::class)->render($film,true);
  if(isset($i['subtitles'],$i['subtitle_preserve']))return app(MarketingSubtitleRenderer::class)->render($film,true);
  $input=isset($i['visual_recipe'])?Storage::disk('local')->path(app(MarketingInformationRenderer::class)->render($film,false)['path']):(isset($i['subtitles'])?Storage::disk('local')->path(app(MarketingSubtitleRenderer::class)->render($film,false)['path']):$files->file($content->output_path,$i['content_sha256']));if($i['speed']??null){$input=app(MarketingSpeed::class)->render($input,$dir,array_map(fn($r)=>$r['scene'],$i['visual_recipe']),$i['speed']);}$duration=$i['frames']/24;$contentDuration=$i['content_frames']/24;$args=[(string)config('marketing.production.ffmpeg_binary'),'-v','error','-y','-i',$input];$filters=[];$next=1;
  if($s['enabled']){
   foreach(['brand','product'] as $key)if($asset=$i[$key]){$source=app(MarketingPackagingAssets::class)->publicImage($asset['source']);abort_unless($source['sha256']===$asset['sha256']&&copy($source['path'],$dir.'/public/'.$key.'.'.$asset['extension']),409,'品牌图片制备失败。');}
   $disk->put($base.'/outro.json',json_encode(['settings'=>$s,'brandFile'=>'brand.'.$i['brand']['extension'],'productFile'=>$i['product']?'product.'.$i['product']['extension']:null],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
   $tools->run([(string)config('marketing.production.node_binary'),base_path('resources/marketing-renderer/packaging/render.mjs'),$dir,(string)config('marketing.production.browser_binary')],600,true);
   $args=array_merge($args,['-i',$dir.'/outro.mp4']);$next++;
   $filters[]='[0:v]trim=end_frame='.$i['content_frames'].'[v0]';$filters[]='[v0][1:v]concat=n=2:v=1:a=0[v]';
  }else $filters[]='[0:v]trim=end_frame='.$i['content_frames'].'[v]';
  $probe=$tools->probe($input);$hasVoice=collect($probe['streams']??[])->contains(fn($st)=>($st['codec_type']??'')==='audio');
  if($hasVoice)$tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-y','-i',$input,'-vn','-map','0:a:0','-ar','48000','-ac','2','-c:a','pcm_s16le',$dir.'/voice.wav'],90,true);
  if($i['audio']){$audio=app(MarketingPackagingAssets::class)->audio($p,$i['audio']['id']);$tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-y','-i',$audio['path'],'-vn','-map','0:a:0','-ar','48000','-ac','2','-c:a','pcm_s16le',$dir.'/music.wav'],90,true);}
  $disk->put($base.'/mix-input.json',json_encode(['frames'=>$i['frames'],'content_frames'=>$i['content_frames'],'volume'=>$s['volume'],'duck'=>$s['duck'],'fade'=>$s['fade'],'voice'=>$hasVoice,'music'=>(bool)$i['audio']],JSON_THROW_ON_ERROR));
  $tools->run([(string)config('marketing.editing.python_binary'),base_path('resources/marketing-renderer/packaging/mix.py'),$dir],90,true);
  $mix=json_decode(file_get_contents($dir.'/audio-mix.json'),true,16,JSON_THROW_ON_ERROR);$args=array_merge($args,['-i',$dir.'/mixed.wav']);
  $output=$dir.'/output.mp4';$args=array_merge($args,['-filter_complex',implode(';',$filters),'-map','[v]','-map',$next.':a:0','-c:v','libx264','-crf','18','-preset','fast','-pix_fmt','yuv420p','-r','24','-frames:v',(string)$i['frames'],'-c:a','aac','-b:a','192k','-ar','48000','-ac','2','-t',(string)$duration,'-movflags','+faststart',$output]);$tools->run($args,240,true);
  $meta=$files->verifyFrames($output,$i['frames'],24,true);$tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-xerror','-i',$output,'-map','0:v:0','-c:v','rawvideo','-map','0:a:0','-c:a','pcm_s16le','-f','null','-'],180,true);
  return $meta+['path'=>$base.'/output.mp4','frames'=>$i['frames'],'fps'=>24,'subtitle_owner'=>'preserved_content_film','native_audio'=>$hasVoice,'music'=>$i['audio']?['id'=>$i['audio']['id'],'license_scope'=>$i['audio']['license_scope'],'volume_percent'=>$s['volume'],'ducking'=>$s['duck']?'actual_audio_rms':'none']:null,'audio_mix'=>$mix,'outro_frames'=>$s['enabled']?$s['duration']*24:0,'speed'=>$i['speed']??null,'publication_authorized'=>false];
 }
}
