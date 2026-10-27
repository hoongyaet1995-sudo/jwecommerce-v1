<?php
namespace App\Services\Marketing;

use App\Models\MarketingTemplateRun;
use App\Services\Marketing\Contracts\MarketingToolAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/** Executes only this owned template from an immutable server-bound run snapshot. */
class MarketingProductTemplateRenderer implements MarketingToolAdapter
{
 private const SOURCE_HASH='b25dfc665301fbe9331b3bf7e8015caa86ced8ecfa9fc9b529d95f2be1b13cde';
 public function execute(array $task):array
 {
  $s=$task['subject']??[];
  if(($task['version']??null)!=='product-template-task-v1'||($s['type']??null)!=='template_run'||!is_string($s['id']??null)||!preg_match('/^[a-f0-9-]{36}$/i',$s['id']))throw new \RuntimeException('template_task_invalid');
  $run=MarketingTemplateRun::find($s['id']);
  if(!$run||(string)$run->project_id!==(string)($s['project_id']??'')||(string)$run->owner_id!==(string)($s['owner_id']??'')||!hash_equals((string)$run->input_digest,(string)($task['source_digest']??'')))throw new \RuntimeException('template_task_invalid');
  $input=$run->input;$prefs=$input['preferences']??[];
  foreach(['packet','asset_index'] as $key)if(!isset($task[$key])||$task[$key]!==($input[$key]??null))throw new \RuntimeException('template_task_invalid');
  if(($task['draft']??null)!==$run->draft||($task['formats']??null)!==($prefs['formats']??null)||($task['subtitles']??null)!==($prefs['subtitles']??null)||($task['music']??null)!==($prefs['music']??null)||($task['voice']??null)!==($run->result['voice']??null))throw new \RuntimeException('template_task_invalid');
  if(!is_bool($task['music'])||!is_bool($task['subtitles'])||!is_array($task['formats'])||!count($task['formats'])||count(array_unique($task['formats']))!==count($task['formats'])||array_diff($task['formats'],['landscape','portrait']))throw new \RuntimeException('template_task_invalid');
  if((bool)($prefs['narration']??false)!==!empty($task['voice'])||($task['subtitles']&&empty($task['voice'])))throw new \RuntimeException('template_task_invalid');
  if(hash_file('sha256',resource_path('marketing-renderer/product-template/component.tsx'))!==self::SOURCE_HASH)throw new \RuntimeException('template_source_changed');
  $adapted=app(MarketingTemplateContract::class)->call('props',['packet'=>$task['packet'],'draft'=>$task['draft'],'asset_index'=>$task['asset_index']]);
  $disk=Storage::disk('local');$attempt=(int)($run->result['local_attempt']??0);if($attempt<0||$attempt>2)throw new \RuntimeException('template_task_invalid');$relative='marketing-template-runs/'.$run->id.'/render-v1'.($attempt?'-recovery-'.$attempt:'');$disk->makeDirectory($relative);$root=realpath($disk->path($relative));$allowed=realpath($disk->path(''));
  if(!$root||!$allowed||!str_starts_with(strtolower($root),strtolower($allowed.DIRECTORY_SEPARATOR)))throw new \RuntimeException('template_storage_invalid');
  $voice=$task['voice']??null;
  if($voice){$voice['file']=app(MarketingVideoFiles::class)->file($voice['path'],$voice['sha256']);$voiceProbe=app(MarketingMediaTools::class)->probe($voice['file']);$duration=(float)($voiceProbe['format']['duration']??0);if(!is_finite($duration)||$duration<=0||count($voiceProbe['streams']??[])!==1||($voiceProbe['streams'][0]['codec_type']??'')!=='audio')throw new \RuntimeException('template_voice_invalid');if($duration+(float)($task['draft']['narration']['planned_start']??0)>14.7)throw new \RuntimeException('voice_overrun');}
  $this->assertSources($adapted['asset_files']);
  $request=['brief'=>$adapted['brief'],'formats'=>$task['formats'],'subtitles'=>$task['subtitles'],'music'=>$task['music']&&(bool)($task['draft']['music']['enabled']??false),'energy'=>$task['draft']['music']['energy']??.5,'planned_start'=>$task['draft']['narration']['planned_start']??0,'narration_text'=>$task['draft']['narration']['text']??'','voice'=>$voice,'browser'=>(string)config('marketing.production.browser_binary'),'ffmpeg'=>(string)config('marketing.template.ffmpeg_binary',config('marketing.production.ffmpeg_binary')),'ffprobe'=>(string)config('marketing.production.ffprobe_binary'),'source_digest'=>$run->input_digest,'draft_hash'=>$adapted['draft_hash'],'source_hash'=>self::SOURCE_HASH];
  $serialized=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  $requestPath=$root.'/request.json';$receiptPath=$root.'/receipt.json';
  if(is_file($requestPath)){
   if(!hash_equals(hash('sha256',$serialized),hash_file('sha256',$requestPath)))throw new \RuntimeException('template_existing_output_mismatch');
   if(is_file($receiptPath)){ $this->assertSources($adapted['asset_files']);$out=$this->receipt($receiptPath,$relative);if(array_keys($out['formats'])!==$task['formats'])throw new \RuntimeException('template_output_invalid');$this->verify($out);return $out; }
   // An interrupted local attempt is preserved; caller explicitly decides whether to start a new run.
   throw new \RuntimeException('template_local_attempt_incomplete');
  }
  $disk->makeDirectory($relative.'/assets');
  foreach($adapted['asset_files'] as $asset){
   $source=realpath($asset['source_path']);$public=realpath(public_path());
   if(!$source||!$public||!str_starts_with(strtolower($source),strtolower($public.DIRECTORY_SEPARATOR))||!is_file($source)||!hash_equals($asset['sha256'],hash_file('sha256',$source))||basename($asset['staged_name'])!==$asset['staged_name'])throw new \RuntimeException('template_asset_invalid');
   $target=$root.'/assets/'.$asset['staged_name'];
   if(is_file($target)){if(!hash_equals($asset['sha256'],hash_file('sha256',$target)))throw new \RuntimeException('template_existing_output_mismatch');}
   else {$handle=fopen($target,'xb');if(!$handle)throw new \RuntimeException('template_storage_invalid');try{$read=fopen($source,'rb');if(!$read)throw new \RuntimeException('template_asset_invalid');stream_copy_to_stream($read,$handle);fclose($read);}finally{fclose($handle);}if(!hash_equals($asset['sha256'],hash_file('sha256',$target)))throw new \RuntimeException('template_asset_invalid');}
  }
  $this->writeFresh($requestPath,$serialized);
  $this->process([(string)config('marketing.production.node_binary','node'),resource_path('marketing-renderer/product-template/render.mjs'),$allowed,$root],1800);
  $this->process([(string)config('marketing.editing.python_binary'),'-X','utf8',resource_path('marketing-renderer/product-template/audio.py'),$allowed,$root],600);
  $this->assertSources($adapted['asset_files']);$out=$this->receipt($receiptPath,$relative);if(array_keys($out['formats'])!==$task['formats'])throw new \RuntimeException('template_output_invalid');$this->verify($out);return $out;
 }
 /** Font-only Chromium check before quoting or any paid speech call. */
 public function validateDraft(MarketingTemplateRun $r):void
 {
  if(!$r->exists||!preg_match('/^[a-f0-9-]{36}$/i',(string)$r->id)||!is_array($r->input)||!is_array($r->draft))throw new \RuntimeException('template_task_invalid');
  $i=$r->input;$prefs=$i['preferences']??[];
  if(!isset($i['packet'],$i['asset_index'])||!is_array($prefs['formats']??null)||!count($prefs['formats'])||array_diff($prefs['formats'],['landscape','portrait'])||!is_bool($prefs['subtitles']??null))throw new \RuntimeException('template_task_invalid');
  $adapted=app(MarketingTemplateContract::class)->call('props',['packet'=>$i['packet'],'draft'=>$r->draft,'asset_index'=>$i['asset_index']]);$this->assertSources($adapted['asset_files']);
  $hash=$adapted['draft_hash']??'';if(!preg_match('/^[a-f0-9]{64}$/',$hash))throw new \RuntimeException('template_task_invalid');
  $disk=Storage::disk('local');$relative='marketing-template-runs/'.$r->id.'/preflight-'.$hash;$disk->makeDirectory($relative);$root=realpath($disk->path($relative));$allowed=realpath($disk->path(''));
  if(!$root||!$allowed||!str_starts_with(strtolower($root),strtolower($allowed.DIRECTORY_SEPARATOR)))throw new \RuntimeException('template_storage_invalid');
  $request=['brief'=>$adapted['brief'],'formats'=>$prefs['formats'],'subtitles'=>$prefs['subtitles'],'browser'=>(string)config('marketing.production.browser_binary')];$json=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$requestHash=hash('sha256',$json);$requestPath=$root.'/request.json';$receiptPath=$root.'/preflight-receipt.json';
  if(is_file($requestPath)){if(!hash_equals($requestHash,hash_file('sha256',$requestPath)))throw new \RuntimeException('template_existing_output_mismatch');}
  else $this->writeFresh($requestPath,$json);
  if(is_file($receiptPath)){
   $receipt=json_decode(file_get_contents($receiptPath),true);
   if(($receipt['request_hash']??null)!==$requestHash||($receipt['source_hash']??null)!==self::SOURCE_HASH||($receipt['draft_hash']??null)!==$hash||($receipt['text_fit']??null)!==true)throw new \RuntimeException('template_existing_output_mismatch');
   return;
  }
  $this->process([(string)config('marketing.production.node_binary','node'),resource_path('marketing-renderer/product-template/render.mjs'),$allowed,$root,'validate'],60);
  $this->assertSources($adapted['asset_files']);$this->writeFresh($receiptPath,json_encode(['text_fit'=>true,'request_hash'=>$requestHash,'draft_hash'=>$hash,'source_hash'=>self::SOURCE_HASH],JSON_THROW_ON_ERROR));
 }
 private function assertSources(array $assets):void
 {
  if(hash_file('sha256',resource_path('marketing-renderer/product-template/component.tsx'))!==self::SOURCE_HASH)throw new \RuntimeException('template_source_changed');
  $public=realpath(public_path());
  foreach($assets as $asset){$file=realpath($asset['source_path']);if(!$file||!$public||!str_starts_with(strtolower($file),strtolower($public.DIRECTORY_SEPARATOR))||!is_file($file)||!hash_equals($asset['sha256'],hash_file('sha256',$file)))throw new \RuntimeException('template_asset_invalid');}
 }
 private function receipt(string $path,string $relative):array
 {
  try{$out=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new \RuntimeException('template_output_invalid');}
  if(!is_array($out)||!is_array($out['formats']??null)||!count($out['formats']))throw new \RuntimeException('template_output_invalid');
  foreach($out['formats'] as $fmt=>&$row){if(($row['filename']??null)!==$fmt.'.mp4')throw new \RuntimeException('template_output_invalid');$row['path']=$relative.'/'.$row['filename'];}unset($row);
  return $out;
 }
 private function writeFresh(string $path,string $contents):void
 {
  $h=fopen($path,'xb');if(!$h)throw new \RuntimeException('template_storage_invalid');try{if(fwrite($h,$contents)!==strlen($contents))throw new \RuntimeException('template_storage_invalid');}finally{fclose($h);}
 }
 private function process(array $args,int $timeout):void
 {
  if(!$args[0])throw new \RuntimeException('template_runtime_not_configured');
  $env=[];$allow=['PATH','SYSTEMROOT','WINDIR','TEMP','TMP','PATHEXT','COMSPEC','SYSTEMDRIVE','USERPROFILE','LOCALAPPDATA','APPDATA','HOME'];foreach(array_merge(getenv(),$_ENV,$_SERVER) as $name=>$value)if(!in_array(strtoupper($name),$allow,true))$env[$name]=false;
  $moduleConfig=config('marketing.template.python_module_path');if($moduleConfig!==null){$modulePath=is_string($moduleConfig)?realpath($moduleConfig):false;if(!$modulePath||!is_dir($modulePath))throw new \RuntimeException('template_runtime_not_configured');$env['PYTHONPATH']=$modulePath;}
  $env['PYTHONIOENCODING']='utf-8';$env['PYTHONDONTWRITEBYTECODE']='1';
  $p=new Process($args,base_path(),$env);$p->setTimeout($timeout);
  try{$p->run();}catch(\Throwable){throw new \RuntimeException('template_local_render_failed');}
  if(!$p->isSuccessful()){
   $result=json_decode($p->getOutput(),true);$code=$result['error']??null;
   if(str_contains($p->getErrorOutput(),'text_does_not_fit_template'))throw new \RuntimeException('text_does_not_fit_template');
   throw new \RuntimeException(in_array($code,['voice_overrun','caption_alignment_required','caption_alignment_invalid'],true)?$code:'template_local_render_failed');
  }
 }
 public function verify(array $output):void
 {
  if(($output['adapter']??null)!=='product_template_v1'||($output['source_unchanged']??null)!==true||!is_array($output['formats']??null)||!count($output['formats']))throw new \RuntimeException('template_output_invalid');
  foreach($output['formats'] as $fmt=>$row){
   [$w,$h]=match($fmt){'landscape'=>[1920,1080],'portrait'=>[1080,1920],default=>throw new \RuntimeException('template_output_invalid')};
   if(($row['width']??null)!==$w||($row['height']??null)!==$h||($row['frames']??null)!==360||($row['seconds']??null)!==15||!is_bool($row['audio']??null)||!preg_match('~^marketing-template-runs/[a-f0-9-]{36}/render-v1(?:-recovery-[12])?/'.preg_quote($fmt,'~').'\\.mp4$~i',$row['path']??''))throw new \RuntimeException('template_output_invalid');
   try{$file=app(MarketingVideoFiles::class)->file($row['path'],$row['sha256']);$probe=app(MarketingMediaTools::class)->probe($file);}catch(\Throwable){throw new \RuntimeException('template_output_invalid');}
   $vs=array_values(array_filter($probe['streams']??[],fn($s)=>$s['codec_type']==='video'));$as=array_values(array_filter($probe['streams']??[],fn($s)=>$s['codec_type']==='audio'));$v=$vs[0]??[];
   if(count($vs)!==1||count($as)!==($row['audio']?1:0)||($v['width']??0)!==$w||($v['height']??0)!==$h||($v['codec_name']??'')!=='h264'||(int)($v['nb_read_frames']??0)!==360||($v['r_frame_rate']??'')!=='24/1'||abs((float)($v['duration']??0)-15)>.045||abs((float)($probe['format']['duration']??0)-15)>.15||($row['audio']&&(($as[0]['codec_name']??'')!=='aac'||abs((float)($as[0]['duration']??0)-15)>.15)))throw new \RuntimeException('template_output_invalid');
  }
 }
}
