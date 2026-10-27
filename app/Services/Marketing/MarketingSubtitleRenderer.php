<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;
use Illuminate\Support\Facades\Storage;
class MarketingSubtitleRenderer {
 public function render(MarketingVideoFilm $f,bool $preservePackaging):array{
  $i=$f->input;$plan=$i['subtitles'];app(MarketingSubtitleService::class)->assertPlan($f,$plan);
  $files=app(MarketingVideoFiles::class);$disk=Storage::disk('local');$tools=app(MarketingMediaTools::class);$base='marketing-films/'.$f->id.'/subtitles';$disk->makeDirectory($base.'/public');$dir=$disk->path($base);$rows=$plan['scenes'];
  foreach($rows as $n=>&$s){$source=$i['scenes'][$n];$keep=$s['spoken_text']===''||($plan['settings']['scope']==='scene'&&$plan['settings']['scene']!==$s['number']);$s['preserve_original']=$keep;if($keep){$reference=MarketingVideoFilm::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->findOrFail($plan['reference_film_id']);$path=$files->file($reference->output_path,$plan['reference_sha256']);$s['in_frame']=$s['start_frame'];}else $path=$files->file($source['raw_path'],$s['raw_sha256']);$s['file']=($n+1).'.mp4';abort_unless(copy($path,$dir.'/public/'.$s['file']),409);}unset($s);
  $source=$preservePackaging?MarketingVideoFilm::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->findOrFail($i['subtitle_preserve']['film_id']):app(MarketingFilmPackaging::class)->content($f);
  $hash=$preservePackaging?$i['subtitle_preserve']['sha256']:$i['content_sha256'];$path=$files->file($source->output_path,$hash);$frames=$preservePackaging?$i['frames']:$i['content_frames'];$tail=null;
  if($frames>$i['content_frames']){abort_unless(copy($path,$dir.'/public/base.mp4'),409);$tail=['from'=>$i['content_frames'],'frames'=>$frames-$i['content_frames']];}
  $disk->put($base.'/subtitles.json',json_encode(['scenes'=>$rows,'settings'=>$plan['settings'],'tail'=>$tail],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
  $tools->run([(string)config('marketing.production.node_binary'),base_path('resources/marketing-renderer/subtitles/render.mjs'),$dir,(string)config('marketing.production.browser_binary')],700,true);
  $output=$dir.'/output.mp4';$tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-y','-i',$dir.'/subtitled.mp4','-i',$path,'-map','0:v:0','-map','1:a:0?','-c','copy','-movflags','+faststart',$output],90,true);
  $hasAudio=collect($tools->probe($path)['streams']??[])->contains('codec_type','audio');$meta=$files->verifyFrames($output,$frames,24,$hasAudio);
  return $meta+['path'=>$base.'/output.mp4','frames'=>$frames,'fps'=>24,'subtitle_owner'=>'shared_dynamic_caption_layer','native_audio'=>$hasAudio,'audio_preserved_from'=>$source->id,'outro_preserved'=>$preservePackaging,'publication_authorized'=>false];
 }
}
