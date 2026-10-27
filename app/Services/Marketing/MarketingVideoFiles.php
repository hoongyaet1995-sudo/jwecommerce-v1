<?php
namespace App\Services\Marketing;
use Illuminate\Support\Facades\{Storage,Http};

class MarketingVideoFiles {
 public function file(string $path,string $hash):string{
  $root=realpath(Storage::disk('local')->path(''));$file=realpath(Storage::disk('local')->path($path));
  abort_unless($root&&$file&&str_starts_with(strtolower($file),strtolower($root.DIRECTORY_SEPARATOR))&&is_file($file)&&hash_equals($hash,hash_file('sha256',$file)),409,'保存的视频文件已变化，不能继续采用。');return $file;
 }
 public function verify(string $file,int $seconds,bool $withAudio):array{
  $probe=app(MarketingMediaTools::class)->probe($file);$videos=collect($probe['streams']??[])->where('codec_type','video');$audios=collect($probe['streams']??[])->where('codec_type','audio');$v=$videos->first();$a=$audios->first();$duration=(float)($probe['format']['duration']??0);
  if($videos->count()!==1||$audios->count()!==($withAudio?1:0)||($v['codec_name']??'')!=='h264'||($withAudio&&($a['codec_name']??'')!=='aac')||($v['width']??0)!==720||($v['height']??0)!==1280||$duration<$seconds-.15||$duration>$seconds+.3||filesize($file)<1000||filesize($file)>100000000)throw new MarketingProductionFailure('video_file_invalid');
  return ['duration'=>$duration,'width'=>720,'height'=>1280,'video_codec'=>'h264','audio_codec'=>$a['codec_name']??null,'sha256'=>hash_file('sha256',$file),'bytes'=>filesize($file)];
 }
 public function download(string $url,string $relative,int $seconds,bool $audio):array{
  $options=app(MarketingEvolinkMedia::class)->downloadOptions($url);$disk=Storage::disk('local');$disk->makeDirectory(dirname($relative));$temp=$disk->path($relative.'.'.bin2hex(random_bytes(6)).'.part');$body=null;$handle=null;
  try{$r=Http::connectTimeout(8)->timeout(90)->withOptions($options)->get($url);if(!$r->successful()||(int)$r->header('Content-Length')>40000000)throw new MarketingProductionFailure('video_download_unavailable');
   $body=$r->toPsrResponse()->getBody();$handle=fopen($temp,'xb');$bytes=0;if(!$handle)throw new MarketingProductionFailure('video_storage_failed');
   while(!$body->eof()){$chunk=$body->read(65536);$bytes+=strlen($chunk);if($bytes>40000000||fwrite($handle,$chunk)!==strlen($chunk))throw new MarketingProductionFailure('video_storage_failed');}
   fclose($handle);$handle=null;$body->close();$body=null;$meta=$this->verify($temp,$seconds,$audio);abort_unless(rename($temp,$disk->path($relative)),409);return $meta+['path'=>$relative];
  }finally{if(is_resource($handle))fclose($handle);if($body)$body->close();if(is_file($temp))unlink($temp);}
 }
 public function verifyFrames(string $file,int $frames,int $fps,bool $audio):array{
  if($fps!==24||$frames<1||$frames>2880)throw new MarketingProductionFailure('video_file_invalid');
  $probe=app(MarketingMediaTools::class)->probe($file);$videos=collect($probe['streams']??[])->where('codec_type','video');$audios=collect($probe['streams']??[])->where('codec_type','audio');$v=$videos->first();$a=$audios->first();$seconds=$frames/$fps;$duration=(float)($probe['format']['duration']??0);
  if($videos->count()!==1||$audios->count()!==($audio?1:0)||($v['codec_name']??'')!=='h264'||($v['width']??0)!==720||($v['height']??0)!==1280||(int)($v['nb_read_frames']??0)!==$frames||($v['r_frame_rate']??'')!=='24/1'||abs((float)($v['duration']??0)-$seconds)>.045||$duration<$seconds-.045||$duration>$seconds+.15||($audio&&(($a['codec_name']??'')!=='aac'||abs((float)($a['duration']??0)-$seconds)>.15))||filesize($file)<1000||filesize($file)>100000000)throw new MarketingProductionFailure('video_file_invalid');
  return ['duration'=>$duration,'width'=>720,'height'=>1280,'video_codec'=>'h264','audio_codec'=>$a['codec_name']??null,'sha256'=>hash_file('sha256',$file),'bytes'=>filesize($file),'frames'=>$frames,'fps'=>$fps];
 }
}
