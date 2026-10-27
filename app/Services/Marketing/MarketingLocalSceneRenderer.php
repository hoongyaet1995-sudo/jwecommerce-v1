<?php
namespace App\Services\Marketing;
use App\Models\{MarketingAsset,MarketingVideoClip};
use Illuminate\Support\Facades\Storage;

/** Owned source-image recipe only. No AI commands, downloads, uploads or generation. */
class MarketingLocalSceneRenderer implements \App\Services\Marketing\Contracts\MarketingToolAdapter
{
 public function execute(array $task):array{
  $clip=MarketingVideoClip::where('project_id',$task['project_id']??'')->where('owner_id',$task['owner_id']??0)->findOrFail($task['clip_id']??'');
  abort_unless(hash_equals(hash('sha256',json_encode($clip->input,JSON_THROW_ON_ERROR)),$task['input_digest']??''),409,'本地镜头任务输入已改变。');
  app(MarketingStoryboardProduction::class)->assertClip($clip);$out=$this->render($clip);$this->verify($out);return $out;
 }
 public function verify(array $output):void{app(MarketingVideoFiles::class)->file($output['path'],$output['sha256']);}
 public function route(array $scene,array $decision,string $tier):array{
  $price=config('marketing.production.local_scene_rate',[]);
  abort_unless(isset($price['cap_microusd'])&&is_int($price['cap_microusd'])&&$price['cap_microusd']>=0&&filled($price['version']??null)&&filled($price['pricing_verified_at']??null)&&now()->lt($price['valid_until']??'2000-01-01'),423,'本地原图路线已接入，但正式计算费率待核验；不会自动改用付费生成。');
  abort_unless(is_file((string)config('marketing.production.speed_ffmpeg_binary')),423,'原图合成工具不可用；不会回退到生成模型。');
  return ['local'=>true,'execution_policy'=>$decision,'video'=>$price+['model'=>'local-image-compose','credit_usd'=>0,'access_verified_at'=>$price['pricing_verified_at'],'adapt_prompt'=>false],'capability'=>['provider'=>'local','quality'=>'720p','durations'=>range(3,15),'modes'=>['single_image'],'audio_control'=>false,'max_images'=>1],'image_roles'=>['start_frame'],'mode'=>'single_image','reason'=>$decision['reason'],'tier'=>$tier];
 }
 public function render(MarketingVideoClip $clip):array{
  $i=$clip->input;$route=$i['configuration'];$recipe=$route['execution_policy']??[];
  abort_unless(($route['local']??false)&&($recipe['version']??null)===MarketingSceneExecutionPolicy::VERSION&&in_array($recipe['route']??'', ['source_locked','conservative_motion'],true)&&($i['payload']['generate_audio']??false)===false,409,'本地镜头配方无效。');
  $seconds=$i['seconds'];abort_unless(is_int($seconds)&&$seconds>=3&&$seconds<=15,422);
  $asset=MarketingAsset::where('project_id',$clip->project_id)->where('authorized',true)->findOrFail($i['image']['asset_id']);
  $source=Storage::disk('local')->path($asset->path);$info=@getimagesize($source);
  abort_unless($info&&$info[0]*$info[1]<=40000000&&is_file($source)&&hash_equals($i['image']['sha256'],hash_file('sha256',$source)),409,'原图已改变或不是有效图片。');
  $path='marketing-clips/'.$clip->id.'/local-raw.mp4';$disk=Storage::disk('local');$disk->makeDirectory(dirname($path));$target=$disk->path($path);$receipt=$target.'.json';$digest=hash('sha256',json_encode($i,JSON_THROW_ON_ERROR));
  if(is_file($receipt)){$saved=json_decode(file_get_contents($receipt),true,512,JSON_THROW_ON_ERROR);abort_unless(hash_equals($digest,$saved['input_digest']??''),409,'本地旧任务配方不一致。');app(MarketingVideoFiles::class)->file($path,$saved['media']['sha256']);return $saved['media'];}
  abort_if(is_file($target),409,'本地任务结果待核对，不会自动覆盖或重渲染。');
  $frames=$seconds*24;$vf='scale=720:1280:flags=lanczos,setsar=1';
  if($recipe['route']==='conservative_motion')$vf="scale=1440:2560:flags=lanczos,zoompan=z='1+0.02*on/".($frames-1)."':x='iw/2-iw/zoom/2':y='ih/2-ih/zoom/2':d=1:s=720x1280:fps=24,setsar=1";
  app(MarketingMediaTools::class)->run([(string)config('marketing.production.speed_ffmpeg_binary'),'-v','error','-n','-loop','1','-framerate','24','-i',$source,'-vf',$vf,'-frames:v',(string)$frames,'-an','-c:v','libx264','-preset','medium','-crf','16','-pix_fmt','yuv420p','-r','24','-movflags','+faststart',$target],180,true);
  abort_unless(hash_equals($i['image']['sha256'],hash_file('sha256',$source)),409,'渲染时原图已改变。');
  $media=app(MarketingVideoFiles::class)->verify($target,$seconds,false)+['path'=>$path,'frames'=>$frames,'fps'=>24,'source_image_sha256'=>$i['image']['sha256'],'route'=>$recipe['route'],'external_requests'=>0];
  abort_unless(file_put_contents($receipt,json_encode(['input_digest'=>$digest,'media'=>$media],JSON_THROW_ON_ERROR))!==false,409,'本地任务回执保存失败。');return $media;
 }
}
