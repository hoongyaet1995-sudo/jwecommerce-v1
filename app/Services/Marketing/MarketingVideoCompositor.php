<?php
namespace App\Services\Marketing;
use App\Models\{MarketingVideoClip,MarketingVideoFilm};
use Illuminate\Support\Facades\Storage;

/** Pure local rendering; never invokes a generation or speech API. */
class MarketingVideoCompositor {
 public function clip(MarketingVideoClip $c):array{return $this->render('marketing-clips/'.$c->id,[['raw_path'=>$c->raw_path,'raw_sha256'=>$c->raw_sha256,'seconds'=>$c->input['seconds'],'subtitle'=>$c->input['subtitle'],'with_audio'=>MarketingClipSource::hasAudio($c)]],'');}
 public function film(MarketingVideoFilm $f):array{if(($f->input['version']??'')===MarketingFilmPackaging::VERSION)return app(MarketingPackagingRenderer::class)->render($f);return $this->render('marketing-films/'.$f->id,$f->input['scenes'],$f->input['cta'],($f->input['version']??'')===MarketingEditPlanService::VERSION,$f->input['cta_hold_frames']??72);}
 public function storyboardClip(MarketingVideoClip $clip):array {
  $path=$clip->raw_path;$hash=$clip->raw_sha256;$voice=$clip->input['voice_asset']??null;
  if(($clip->input['configuration']['audio']['route']??'independent')==='independent'&&trim($clip->input['voiceover']??'')!=='')abort_unless($voice&&!($voice['requires_extension']??true),422,'完整口播需要有效指定声线与足够时长。');
  if($voice){$target='marketing-clips/'.$clip->id.'/voiced.mp4';Storage::disk('local')->makeDirectory(dirname($target));$result=app(MarketingStoryboardVoice::class)->mux($clip->project_id,$clip->owner_id,app(MarketingVideoFiles::class)->file($path,$hash),$voice,Storage::disk('local')->path($target));$path=$target;$hash=$result['sha256'];}
  $media=$this->render('marketing-clips/'.$clip->id,[['raw_path'=>$path,'raw_sha256'=>$hash,'seconds'=>$clip->input['seconds'],'subtitle'=>$clip->input['subtitle'],'with_audio'=>MarketingClipSource::hasAudio($clip)]],'');
  return array_replace($media,['editing_source'=>['path'=>$path,'sha256'=>$hash],'native_audio'=>(bool)($clip->input['payload']['generate_audio']??false),'voice_id'=>$voice['voice_id']??null,'voice_source'=>$voice?'selected_voice':(($clip->input['payload']['generate_audio']??false)?'native':'silent')]);
 }
 private function render(string $base,array $scenes,string $cta,bool $edited=false,int $hold=72):array{
  app(MarketingVideoRenderer::class)->assertReady();$disk=Storage::disk('local');$disk->makeDirectory($base.'/public');$rows=[];$seconds=0;$audio=false;
  $totalFrames=0;foreach($scenes as $i=>$s){$source=app(MarketingVideoFiles::class)->file($s['raw_path'],$s['raw_sha256']);$file=($i+1).'.mp4';abort_unless(copy($source,$disk->path($base.'/public/'.$file))&&hash_file('sha256',$disk->path($base.'/public/'.$file))===$s['raw_sha256'],409,'片段制备校验失败。');$row=['file'=>$file,'frames'=>$edited?$s['frames']:$s['seconds']*24,'subtitle'=>$s['subtitle'],'with_audio'=>$s['with_audio']];if($edited)$row+=['source_frames'=>$s['source_frames'],'in_frame'=>$s['in_frame'],'out_frame'=>$s['out_frame']];$rows[]=$row;$totalFrames+=$row['frames'];$seconds+=$s['seconds'];$audio=$audio||$s['with_audio'];}
  $props=['scenes'=>$rows,'cta'=>$cta,'with_audio'=>$audio];if($edited)$props+=['version'=>MarketingEditPlanService::VERSION,'cta_hold_frames'=>$hold];$disk->put($base.'/input.json',json_encode($props,JSON_THROW_ON_ERROR));$directory=$disk->path($base);$tools=app(MarketingMediaTools::class);
  $tools->run([(string)config('marketing.production.node_binary'),base_path('resources/marketing-renderer/workspace-video/render.mjs'),$directory,(string)config('marketing.production.browser_binary')],700,true);
  $rendered=$directory.'/rendered.mp4';$final=$directory.'/output.mp4';
  if($audio)$tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-y','-i',$rendered,'-c:v','copy','-af','loudnorm=I=-16:TP=-1.5:LRA=11','-c:a','aac','-b:a','192k','-ar','48000','-movflags','+faststart',$final],90,true);else abort_unless(rename($rendered,$final),409);
  $meta=$edited?app(MarketingVideoFiles::class)->verifyFrames($final,$totalFrames,24,$audio):app(MarketingVideoFiles::class)->verify($final,$seconds,$audio);$args=[(string)config('marketing.production.ffmpeg_binary'),'-v','error','-xerror','-i',$final,'-map','0:v:0','-c:v','rawvideo'];if($audio)$args=array_merge($args,['-map','0:a:0','-c:a','pcm_s16le']);$tools->run(array_merge($args,['-f','null','-']),180,true);
  return $meta+['path'=>$base.'/output.mp4','frames'=>$totalFrames,'fps'=>24,'subtitle_owner'=>'local_compositor','native_audio'=>$audio,'publication_authorized'=>false];
 }
}
