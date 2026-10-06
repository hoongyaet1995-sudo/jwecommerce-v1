<?php
namespace App\Services\Support;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class SupportAudioProcessor {
 public const MAX_BYTES=16777216;
 private function run(array $args,int $timeout=30):string {
  $process=new Process($args);$process->setTimeout($timeout);$output='';
  try {$process->run(function($type,$buffer)use(&$output){$output.=$buffer;if(strlen($output)>262144)throw new \RuntimeException('Audio inspection output exceeds limit.');});}
  catch(\Throwable){throw ValidationException::withMessages(['audio'=>'Audio processing is unavailable or timed out. Contact your administrator.']);}
  if(!$process->isSuccessful())throw ValidationException::withMessages(['audio'=>'This audio could not be processed. Use a valid audio file.']);
  return $output;
 }
 public function normalize(string $bytes):array {
  if($bytes===''||strlen($bytes)>self::MAX_BYTES)throw ValidationException::withMessages(['audio'=>'Use an audio file up to 16 MB.']);
  $dir=storage_path('app/support-private/processing/'.Str::uuid());
  if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new \RuntimeException('Audio storage unavailable.');
  $input=$dir.'/input';$out=$dir.'/voice.ogg';
  try {
   file_put_contents($input,$bytes);
   $info=json_decode($this->run([config('support_media.ffprobe'),'-v','quiet','-max_alloc','67108864','-protocol_whitelist','file','-format_whitelist','ogg,matroska,webm,mov,mp3,aac,amr,wav','-show_entries','stream=codec_type,codec_name:format=duration,format_name','-of','json',$input]),true);
   $streams=$info['streams']??[];$duration=(float)($info['format']['duration']??0);
   $allowed=['opus','vorbis','aac','mp3','amr_nb','amr_wb','pcm_s16le','pcm_s24le','pcm_s32le','pcm_f32le'];
   if(count($streams)!==1||($streams[0]['codec_type']??'')!=='audio'||!in_array($streams[0]['codec_name']??'', $allowed,true)||$duration>300)throw ValidationException::withMessages(['audio'=>'Use a single audio track up to 5 minutes; video is not supported.']);
   $this->run([config('support_media.ffmpeg'),'-v','error','-nostdin','-max_alloc','67108864','-protocol_whitelist','file','-format_whitelist','ogg,matroska,webm,mov,mp3,aac,amr,wav','-threads','1','-i',$input,'-map','0:a:0','-vn','-map_metadata','-1','-ac','1','-ar','48000','-fflags','+bitexact','-flags:a','+bitexact','-c:a','libopus','-b:a','32k','-threads','1','-t','301','-fs',(string)self::MAX_BYTES,$out]);
   $clean=file_get_contents($out);if(!$clean||strlen($clean)>self::MAX_BYTES)throw new \RuntimeException('Audio conversion unavailable.');
   $normalized=json_decode($this->run([config('support_media.ffprobe'),'-v','quiet','-protocol_whitelist','file','-format_whitelist','ogg','-show_entries','format=duration','-of','json',$out],5),true);
   $duration=(float)($normalized['format']['duration']??0);
   if($duration<=0||$duration>300)throw ValidationException::withMessages(['audio'=>'Use an audio file up to 5 minutes.']);
   return [$clean,'audio/ogg; codecs=opus',round($duration,3)];
  }finally {foreach([$input,$out] as $f)if(is_file($f))unlink($f);if(is_dir($dir))rmdir($dir);}
 }
}
