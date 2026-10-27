<?php
namespace App\Services\Marketing;

use App\Models\{MarketingAudioAsset,MarketingProject};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketingPackagingAssets
{
 public function internal():bool{return (app()->environment('local')||app()->runningUnitTests())&&(bool)config('marketing.conversation.open_testing');}
 public function publicImage(string $path):array {
  abort_unless(str_starts_with($path,'/')&&!str_starts_with($path,'//')&&!str_contains($path,'?')&&!str_contains($path,'#'),422,'请在店铺设置选择本地品牌图片。');
  $root=realpath(public_path());$full=realpath(public_path(ltrim($path,'/')));
  abort_unless($root&&$full&&str_starts_with(strtolower($full),strtolower($root.DIRECTORY_SEPARATOR))&&is_file($full)&&filesize($full)<=10000000,422,'品牌或产品图片不存在。');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($full);abort_unless(in_array($mime,['image/png','image/jpeg','image/webp','image/svg+xml'],true),422,'不支持此品牌图片类型。');
  if($mime==='image/svg+xml'){ $svg=file_get_contents($full);abort_if(preg_match('/<\s*(script|foreignObject)|\bon\w+\s*=|<!DOCTYPE|<!ENTITY|(?:href\s*=\s*["\x27]\s*(?!#))|url\s*\(/i',$svg),422,'请使用静态品牌图片。');}
  return ['path'=>$full,'sha256'=>hash_file('sha256',$full),'extension'=>['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/svg+xml'=>'svg'][$mime],'mime'=>$mime,'source'=>$path];
 }
 public function brand(MarketingProject $p):array {
  $brand=app(\App\Support\ShopBrand::class);$logo=$brand->configuredLogo();
  abort_unless(is_string($logo)&&$logo!=='',422,'请先在店铺设置保存公司Logo。');
  return $this->publicImage($logo)+['name'=>$brand->name()];
 }
 public function product(MarketingProject $p):?array {
  $image=data_get($p->snapshot,'images.0.path');if(!is_string($image))return null;
  try{return $this->publicImage($image);}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface){return null;}
 }
 public function audio(MarketingProject $p,string $id):array {
  if(str_starts_with($id,'library-'))return app(MarketingMusicLibrary::class)->audio($id);
  if(str_starts_with($id,'internal-')){
   $key=substr($id,9);abort_unless($this->internal()&&in_array($key,['calm','warm','pulse'],true),409,'此内部验收音轨不适用于当前环境。');
   $path=resource_path('marketing-audio/internal/'.$key.'.wav');abort_unless(is_file($path),409,'验收配乐文件缺少。');
   return ['id'=>$id,'path'=>$path,'sha256'=>hash_file('sha256',$path),'duration'=>24,'name'=>['calm'=>'稳步向前','warm'=>'温暖日常','pulse'=>'轻快节拍'][$key],'license_scope'=>'internal_acceptance','license'=>['source'=>'locally synthesized; no third-party samples','scope'=>'internal_acceptance','note'=>'用户授权本地验收；不是正式商用曲库'],'extension'=>'wav'];
  }
  $a=MarketingAudioAsset::where('project_id',$p->id)->where('owner_id',$p->owner_id)->where('authorized',true)->findOrFail($id);
  $file=app(MarketingVideoFiles::class)->file($a->path,$a->sha256);
  abort_unless($this->usablePrivate($a),409,'音乐文件记录不可用。');
  return ['id'=>$a->id,'path'=>$file,'sha256'=>$a->sha256,'duration'=>$a->duration,'name'=>$a->name,'license_scope'=>$a->license['scope'],'license'=>$a->license,'extension'=>'wav'];
 }
 private function usablePrivate(MarketingAudioAsset $a):bool {
  return (($a->license['scope']??'')==='project_commercial'&&($a->license['confirmed_by']??null)===$a->owner_id)||(($a->license['scope']??'')==='merchant_supplied'&&($a->license['uploaded_by']??null)===$a->owner_id);
 }
 public function privateItem(MarketingProject $p,MarketingAudioAsset $a):array {
  abort_unless($a->project_id===$p->id&&$a->owner_id===$p->owner_id&&$a->authorized&&$this->usablePrivate($a),404);
  return ['id'=>$a->id,'name'=>$a->name,'duration'=>$a->duration,'internal'=>false,'kind'=>'upload','url'=>route('marketing.workspace.packaging.audio',[$p->id,$a->id]),'mood'=>'我的上传 · 仅本项目可用','tags'=>[],'sha256'=>$a->sha256];
 }
 public function catalogue(MarketingProject $p,?string $selected=null):array {
  $out=app(MarketingMusicLibrary::class)->catalogue($p->id);
  // Existing internal acceptance recipes still open correctly; don't offer test tracks as a customer library.
  if($selected&&str_starts_with($selected,'internal-')&&$this->internal()){$a=$this->audio($p,$selected);$out[]=['id'=>$selected,'name'=>$a['name'],'duration'=>24,'internal'=>true,'kind'=>'internal','url'=>route('marketing.workspace.packaging.audio',[$p->id,$selected]),'mood'=>'内部验收配乐','tags'=>[],'sha256'=>$a['sha256']];}
  $uploads=MarketingAudioAsset::where('project_id',$p->id)->where('owner_id',$p->owner_id)->where('authorized',true)->latest('created_at')->limit(30)->get();
  if($selected&&!$uploads->contains('id',$selected)){$pinned=MarketingAudioAsset::where('project_id',$p->id)->where('owner_id',$p->owner_id)->where('authorized',true)->find($selected);if($pinned)$uploads->push($pinned);}
  foreach($uploads as $a){if($this->usablePrivate($a))$out[]=$this->privateItem($p,$a);}
  return $out;
 }
 public function upload(MarketingProject $p,UploadedFile $file,string $name,string $source,string $note):MarketingAudioAsset {
  $id=(string)Str::uuid();$disk=Storage::disk('local');$dir='marketing-audio/'.$p->id.'/'.$id;$disk->makeDirectory($dir);$temp=$disk->path($dir.'/upload.bin');$final=$disk->path($dir.'/audio.wav');
  try{
   abort_unless(copy($file->getRealPath(),$temp),409);$tools=app(MarketingMediaTools::class);
   try{$probe=$tools->probe($temp);}catch(MarketingProductionFailure $e){
    if($e->state==='blocked')throw $e;
    throw \Illuminate\Validation\ValidationException::withMessages(['file'=>'无法读取此音频，请选择可正常播放的MP3、WAV、M4A或OGG文件。']);
   }
   $streams=$probe['streams']??[];$duration=(float)($probe['format']['duration']??0);
   $formats=['mp3'=>['mp3'],'wav'=>['wav'],'m4a'=>['mov','mp4','m4a'],'ogg'=>['ogg']];
   $expected=$formats[strtolower($file->getClientOriginalExtension())]??[];
   $actual=explode(',',$probe['format']['format_name']??'');
   $hasVideo=collect($streams)->contains(fn($s)=>($s['codec_type']??'')==='video'&&!($s['disposition']['attached_pic']??false));
   if(!array_intersect($expected,$actual)||$hasVideo)throw \Illuminate\Validation\ValidationException::withMessages(['file'=>'文件内容与音频格式不符，请选择MP3、WAV、M4A或OGG音频文件。']);
   abort_unless(collect($streams)->contains(fn($s)=>($s['codec_type']??'')==='audio')&&$duration>=1&&$duration<=600,422,'请上传1至600秒的音频文件。');
   try{$tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-xerror','-y','-i',$temp,'-vn','-map','0:a:0','-ar','48000','-ac','2','-c:a','pcm_s16le',$final],90,true);}catch(MarketingProductionFailure $e){
    if($e->state==='blocked')throw $e;
    throw \Illuminate\Validation\ValidationException::withMessages(['file'=>'音频无法完整解码，请重新导出音频文件后再上传。']);
   }
   abort_unless(is_file($final)&&filesize($final)<=120000000,422,'音频制备失败或过大。');
   return MarketingAudioAsset::create(['id'=>$id,'project_id'=>$p->id,'owner_id'=>$p->owner_id,'name'=>$name,'path'=>$dir.'/audio.wav','sha256'=>hash_file('sha256',$final),'duration'=>$duration,'authorized'=>true,'license'=>['scope'=>'merchant_supplied','source'=>$source,'note'=>$note,'uploaded_by'=>$p->owner_id,'uploaded_at'=>now()->toIso8601String(),'platform_verified'=>false,'original_sha256'=>hash_file('sha256',$temp)]]);
  }catch(\Throwable $e){if(is_file($final))unlink($final);throw $e;}finally{if(is_file($temp))unlink($temp);}
 }
}
