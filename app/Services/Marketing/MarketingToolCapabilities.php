<?php
namespace App\Services\Marketing;

use Symfony\Component\Process\ExecutableFinder;

/** Owned capability contracts, not general-purpose software or script access. */
class MarketingToolCapabilities
{
 public const VERSION='local-tool-capabilities-v1';
 public function catalog():array{
  $forms=array_keys(app(MarketingVideoFormPolicy::class)->catalog());
  $base=['version'=>self::VERSION,'status'=>'implemented','executable'=>true,'adapter'=>MarketingLocalFilmToolAdapter::class,'adapter_version'=>MarketingLocalFilmToolAdapter::VERSION,'forms'=>$forms,'input_contract'=>'owned_frozen_film_recipe','submission'=>'existing_film_task','limits'=>['width'=>720,'height'=>1280,'fps'=>24,'max_seconds'=>120],'cost'=>['external_api_microusd'=>0,'compute_microusd'=>null,'pricing_status'=>'local_compute_unpriced','quote_eligible'=>false],'quality_verified'=>false];
  $rows=[
   'film.compose'=>['tools'=>['remotion','ffmpeg'],'purpose'=>'Compose existing authorized clips, captions and CTA','dependencies'=>['node','browser','ffmpeg','ffprobe','remotion','sequence_template']],
   'film.package'=>['tools'=>['remotion','ffmpeg'],'purpose'=>'Apply existing approved packaging recipe and preserve base version','dependencies'=>['node','browser','ffmpeg','ffprobe','python','remotion','subtitle_template','outro_template','mixer']],
   'remotion.captions'=>['tools'=>['remotion'],'purpose'=>'Plain/highlight/phrase captions from verified alignment','dependencies'=>['node','browser','remotion','subtitle_template']],
   'remotion.information'=>['tools'=>['remotion'],'purpose'=>'Existing information cards, framing, emphasis and authorized overlays','dependencies'=>['node','browser','remotion','subtitle_template']],
   'remotion.brand_outro'=>['tools'=>['remotion'],'purpose'=>'Owned minimal/CTA/product end-card templates','dependencies'=>['node','browser','remotion','outro_template']],
   'ffmpeg.cut_concat'=>['tools'=>['ffmpeg'],'purpose'=>'Existing frame-bound cuts, remux and composition export','dependencies'=>['ffmpeg','ffprobe']],
   'ffmpeg.audio_mix'=>['tools'=>['ffmpeg'],'purpose'=>'Existing normalization/music/ducking with local Python mixer','dependencies'=>['ffmpeg','ffprobe','python','mixer']],
   'ffmpeg.retime'=>['tools'=>['ffmpeg'],'purpose'=>'Existing bounded 0.5/1/1.5/2x retiming with speech protection','dependencies'=>['speed_ffmpeg','ffprobe']],
   'ffmpeg.validate_media'=>['tools'=>['ffmpeg'],'purpose'=>'Codec/frame/duration/audio/hash validation; no perceptual certification','dependencies'=>['ffmpeg','ffprobe']],
  ];
  foreach($rows as &$row)$row+=$base;unset($row);
  foreach(['ffmpeg.source_locked'=>'原图保真合成','ffmpeg.conservative_motion'=>'最多2%的整图镜头推进'] as $id=>$purpose)$rows[$id]=array_replace($base,['tools'=>['ffmpeg'],'purpose'=>$purpose,'adapter'=>MarketingLocalSceneRenderer::class,'adapter_version'=>MarketingSceneExecutionPolicy::VERSION,'input_contract'=>'owned_frozen_clip_recipe','submission'=>'existing_clip_task','dependencies'=>['speed_ffmpeg','ffprobe']]);
  foreach(['blender.product_turntable'=>['blender','Template-based product/space rendering; requires reliable authorized 3D assets'],'comfyui.masked_image'=>['comfyui','Fixed approved image/background/mask/upscale workflows; requires model/workflow/GPU validation']] as $id=>[$tool,$purpose])$rows[$id]=array_replace($base,['status'=>'planned','executable'=>false,'adapter'=>null,'adapter_version'=>null,'tools'=>[$tool],'purpose'=>$purpose,'submission'=>'none','dependencies'=>[],'cost'=>['external_api_microusd'=>null,'compute_microusd'=>null,'pricing_status'=>'unquoted','quote_eligible'=>false]]);
  return $rows;
 }
 public function requireExecutable(string $id):array{
  $cap=$this->catalog()[$id]??null;abort_unless($cap&&$cap['executable']&&$cap['status']==='implemented',423,'制作能力尚未接入或验证，不会安装软件、执行任意命令或增加费用。');return $cap;
 }
 public function runtime(string $id):array{
  $cap=$this->catalog()[$id]??null;if(!$cap||!$cap['executable'])return ['available'=>false,'basis'=>'planned_or_unknown','missing'=>[]];
  $paths=['node'=>config('marketing.production.node_binary'),'browser'=>config('marketing.production.browser_binary'),'ffmpeg'=>config('marketing.production.ffmpeg_binary'),'ffprobe'=>config('marketing.production.ffprobe_binary'),'speed_ffmpeg'=>config('marketing.production.speed_ffmpeg_binary'),'python'=>config('marketing.editing.python_binary'),'remotion'=>base_path('resources/marketing-renderer/node_modules/remotion/package.json'),'sequence_template'=>base_path('resources/marketing-renderer/workspace-video/render.mjs'),'subtitle_template'=>base_path('resources/marketing-renderer/subtitles/render.mjs'),'outro_template'=>base_path('resources/marketing-renderer/packaging/render.mjs'),'mixer'=>base_path('resources/marketing-renderer/packaging/mix.py')];
  $missing=[];foreach($cap['dependencies'] as $key){$path=(string)($paths[$key]??'');if(!is_file($path)&&!(in_array($key,['node','python'],true)&&$path!==''&&(new ExecutableFinder)->find($path)))$missing[]=$key;}
  return ['available'=>!$missing,'basis'=>'configured_files_only','missing'=>$missing,'render_verified'=>false];
 }
 public function available(string $form):array{
  abort_unless(isset(app(MarketingVideoFormPolicy::class)->catalog()[$form]),422,'视频形式无效。');$out=[];foreach($this->catalog() as $id=>$cap)if($cap['executable']&&in_array($form,$cap['forms'],true)&&$this->runtime($id)['available'])$out[$id]=$cap;return $out;
 }
 public function filmCapability(\App\Models\MarketingVideoFilm $film):string{
  return match($film->input['version']??''){MarketingFilmPackaging::VERSION=>'film.package',MarketingWorkspaceFilm::VERSION,MarketingStoryboardProduction::FILM,MarketingEditPlanService::VERSION=>'film.compose',default=>abort(422,'成片配方尚未接入已验证工具适配器。')};
 }
 public function operations(\App\Models\MarketingVideoFilm $f):array{
  $i=$f->input;$ops=['ffmpeg.validate_media'];if($this->filmCapability($f)==='film.compose')return ['ffmpeg.cut_concat','remotion.captions','ffmpeg.audio_mix','ffmpeg.validate_media'];
  if(isset($i['visual_recipe']))$ops[]='remotion.information';elseif(isset($i['subtitles']))$ops[]='remotion.captions';
  if($i['settings']['enabled']??false)$ops[]='remotion.brand_outro';if($i['speed']??null)$ops[]='ffmpeg.retime';$ops[]='ffmpeg.audio_mix';return array_values(array_unique($ops));
 }
 public function executionIdentity():array{
  static $hashes=[];$binary=function(string $key)use(&$hashes){$path=(string)config('marketing.production.'.$key);if(!is_file($path))return null;$stamp=$path.':'.filemtime($path).':'.filesize($path);return $hashes[$stamp]??=hash_file('sha256',$path);};
  $package=base_path('resources/marketing-renderer/node_modules/remotion/package.json');$version=is_file($package)?(json_decode(file_get_contents($package),true)['version']??null):null;
  $templates=[];foreach(['workspace-video/render.mjs','subtitles/render.mjs','packaging/render.mjs','packaging/mix.py'] as $file){$path=base_path('resources/marketing-renderer/'.$file);$templates[$file]=is_file($path)?hash_file('sha256',$path):null;}
  return ['registry_version'=>self::VERSION,'remotion_package_version'=>$version,'ffmpeg_binary_sha256'=>$binary('ffmpeg_binary'),'ffprobe_binary_sha256'=>$binary('ffprobe_binary'),'template_entry_sha256'=>$templates];
 }
 public function instruction():string{
  $ready=[];foreach($this->catalog() as $id=>$cap)if($cap['executable']&&$this->runtime($id)['available'])$ready[]=$id;
  return ' OWNED TOOLS '.self::VERSION.': available configured capabilities '.implode(',',$ready).'. Blender and ComfyUI are planned, never executable or quotable. Choose capability by shot purpose across all six forms; do not change narrative to fit a tool. Only existing validated film-recipe parameters, no arbitrary scripts/nodes/models/downloads. Local compute is unpriced, not inherently free; existing local edits add no provider API charge. Runtime presence is not rendered quality proof.';
 }
}
