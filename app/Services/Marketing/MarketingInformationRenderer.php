<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;
use Illuminate\Support\Facades\Storage;
class MarketingInformationRenderer {
 public function render(MarketingVideoFilm $f,bool $preserve):array{
  $i=$f->input;if(!$preserve&&isset($i['source_content_frames']))$i['content_frames']=$i['source_content_frames'];$files=app(MarketingVideoFiles::class);$tools=app(MarketingMediaTools::class);$disk=Storage::disk('local');$base='marketing-films/'.$f->id.'/information';$disk->makeDirectory($base.'/public');$dir=$disk->path($base);$rows=[];
  app(MarketingInformationCards::class)->normalize($i['information_cards'],$i['scenes']?array_map(fn($r)=>$r['scene'],$i['visual_recipe']):[]);
  app(MarketingEmphasis::class)->normalize($i['emphasis']??[],array_map(fn($r)=>$r['scene'],$i['visual_recipe']));
  app(MarketingFraming::class)->normalize($i['framing']??[],array_map(fn($r)=>$r['scene'],$i['visual_recipe']));
  foreach($i['visual_recipe'] as $n=>$recipe){$row=$recipe['scene'];$source=$i['scenes'][$n];abort_unless($row['raw_sha256']===$source['raw_sha256'],409,'原片已变化。');$path=$files->file($source['raw_path'],$source['raw_sha256']);$row['file']=($n+1).'.mp4';$row['preserve_original']=false;$row['caption_settings']=$recipe['settings'];$row['caption_original']=$recipe['original'];abort_unless(copy($path,$dir.'/public/'.$row['file']),409);$rows[]=$row;}
  $source=$preserve?MarketingVideoFilm::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->where('status','completed')->findOrFail($i['visual_preserve']['film_id']):app(MarketingFilmPackaging::class)->content($f);
  $path=$files->file($source->output_path,$preserve?$i['visual_preserve']['sha256']:$i['content_sha256']);$frames=$preserve?$i['frames']:$i['content_frames'];$tail=null;if($frames>$i['content_frames']){abort_unless(copy($path,$dir.'/public/base.mp4'),409);$tail=['from'=>$i['content_frames'],'frames'=>$frames-$i['content_frames']];}
  $overlayRows=[];$p=\App\Models\MarketingProject::where('owner_id',$f->owner_id)->findOrFail($f->project_id);foreach(app(MarketingOverlays::class)->normalize($i['overlays']??[],array_map(fn($r)=>$r['scene'],$i['visual_recipe']),$p) as $n=>$l){$a=app(MarketingOverlays::class)->image($p,$l['image_id']);abort_unless(hash_equals($i['overlay_assets'][$l['image_id']]['sha256']??'',$a['sha256']),409,'叠加图片已变化，请重新审阅。');$file='overlay-'.$n.'.'.$a['extension'];abort_unless(copy($a['path'],$dir.'/public/'.$file)&&hash_equals($a['sha256'],hash_file('sha256',$dir.'/public/'.$file)),409,'叠加图片复制校验失败。');$overlayRows[]=$l+['file'=>$file];}
  $disk->put($base.'/subtitles.json',json_encode(['scenes'=>$rows,'settings'=>$rows[0]['caption_settings'],'cards'=>$i['information_cards'],'overlays'=>$overlayRows,'emphasis'=>$i['emphasis']??[],'framing'=>$i['framing']??[],'tail'=>$tail],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
  $tools->run([(string)config('marketing.production.node_binary'),base_path('resources/marketing-renderer/subtitles/render.mjs'),$dir,(string)config('marketing.production.browser_binary')],700,true);
  $output=$dir.'/output.mp4';$tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-y','-i',$dir.'/subtitled.mp4','-i',$path,'-map','0:v:0','-map','1:a:0?','-c','copy','-movflags','+faststart',$output],90,true);$hasAudio=collect($tools->probe($path)['streams']??[])->contains('codec_type','audio');$meta=$files->verifyFrames($output,$frames,24,$hasAudio);
  return $meta+['path'=>$base.'/output.mp4','frames'=>$frames,'fps'=>24,'subtitle_owner'=>'resolved_shared_caption_information_layers','native_audio'=>$hasAudio,'audio_preserved_from'=>$source->id,'outro_preserved'=>$preserve,'overlay_count'=>count($overlayRows),'emphasis_scene_count'=>count($i['emphasis']??[]),'framing_scene_count'=>count(array_filter($i['framing']??[],fn($e)=>$e['mode']!=='none')),'information_card_count'=>count($i['information_cards']),'publication_authorized'=>false];
 }
}
