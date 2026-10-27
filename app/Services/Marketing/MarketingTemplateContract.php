<?php
namespace App\Services\Marketing;
use Symfony\Component\Process\Process;

/** Pure local JSON bridge; no transport, credentials, or client-controlled code. */
class MarketingTemplateContract
{
 private const MODES=['packet','creative','checker','validate','check_result','props','music'];
 public function call(string $mode,array $input):array
 {
  if(!in_array($mode,self::MODES,true))throw new \RuntimeException('template_contract_mode_invalid');
  foreach(['assets','asset_index','index'] as $key){
   if(!isset($input[$key]))continue;
   if(!is_array($input[$key]))throw new \RuntimeException('template_asset_path_invalid');
   foreach($input[$key] as &$asset){
    if(!is_array($asset)||!is_string($asset['path']??null))throw new \RuntimeException('template_asset_path_invalid');
    $real=realpath($asset['path']);$public=realpath(public_path());
    if(!$real||!$public||!is_file($real)||!str_starts_with(strtolower($real),strtolower($public.DIRECTORY_SEPARATOR))||!in_array(strtolower(pathinfo($real,PATHINFO_EXTENSION)),['png','jpg','jpeg','webp'],true)||filesize($real)>8*1024*1024)throw new \RuntimeException('template_asset_path_invalid');
    $asset['path']=$real;
   }unset($asset);
  }
  if(isset($input['index'])&&!isset($input['asset_index']))$input['asset_index']=$input['index'];unset($input['index']);
  $binary=(string)config('marketing.editing.python_binary');if(!$binary)throw new \RuntimeException('template_contract_runtime_missing');
  $json=json_encode($input,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(strlen($json)>2000000)throw new \RuntimeException('template_contract_input_invalid');
  // Symfony otherwise inherits process environment. Retain only OS runtime essentials.
  $env=[];$allow=['PATH','SYSTEMROOT','WINDIR','TEMP','TMP','PATHEXT','COMSPEC','SYSTEMDRIVE','USERPROFILE','LOCALAPPDATA','APPDATA','HOME'];
  foreach(array_merge(getenv(),$_ENV,$_SERVER) as $name=>$value)if(!in_array(strtoupper($name),$allow,true))$env[$name]=false;
  $env['PYTHONIOENCODING']='utf-8';$env['PYTHONDONTWRITEBYTECODE']='1';
  $modules=config('marketing.template.python_module_path');
  if($modules!==null){$modulePath=is_string($modules)?realpath($modules):false;if(!$modulePath||!is_dir($modulePath))throw new \RuntimeException('template_contract_runtime_missing');$env['PYTHONPATH']=$modulePath;}
  $p=new Process([$binary,'-X','utf8',resource_path('marketing-template-contract/worker.py'),$mode,realpath(public_path())],base_path(),$env);$p->setInput($json);$p->setTimeout(30);
  try{$p->run();}catch(\Throwable){throw new \RuntimeException('template_contract_failed');}
  if(!$p->isSuccessful()){
   $stderr=$p->getErrorOutput();$errorClass='process_failure';$module=null;
   if(preg_match("/ModuleNotFoundError: No module named '([a-zA-Z0-9_.]{1,64})'/",$stderr,$matches)){$errorClass='ModuleNotFoundError';$module=$matches[1];}
   elseif(preg_match('/(?:^|\n)([A-Za-z_][A-Za-z0-9_]{0,63}(?:Error|Exception)):/',$stderr,$matches))$errorClass=$matches[1];
   \Illuminate\Support\Facades\Log::warning('template_contract_process_failed',['mode'=>$mode,'exit_code'=>$p->getExitCode(),'error_class'=>$errorClass,'missing_module'=>$module,'stderr_bytes'=>strlen($stderr),'stderr_sha256'=>hash('sha256',$stderr)]);
   throw new \RuntimeException('template_contract_failed');
  }
  try{$out=json_decode($p->getOutput(),true,128,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new \RuntimeException('template_contract_failed');}
  if(!is_array($out)||isset($out['worker_error']))throw new \RuntimeException('template_contract_failed');
  return $out;
 }
}
