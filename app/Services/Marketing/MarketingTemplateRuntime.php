<?php
namespace App\Services\Marketing;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\{ExecutableFinder,Process};

/** Local-only preflight before consent/paid transport. Does not download or install tools. */
class MarketingTemplateRuntime
{
 public function ready():bool{
  $tools=[config('marketing.editing.python_binary'),config('marketing.production.node_binary','node'),config('marketing.production.browser_binary'),config('marketing.template.ffmpeg_binary',config('marketing.production.ffmpeg_binary')),config('marketing.production.ffprobe_binary')];
  $fingerprint=hash('sha256',json_encode([$tools,config('marketing.template.python_module_path')]));
  return Cache::remember('marketing-template-runtime-'.$fingerprint,30,function()use($tools){
   $finder=new ExecutableFinder;foreach($tools as $tool)if(!is_string($tool)||$tool===''||(!is_file($tool)&&!$finder->find($tool)))return false;
   $env=[];$allow=['PATH','SYSTEMROOT','WINDIR','TEMP','TMP','PATHEXT','COMSPEC','SYSTEMDRIVE','USERPROFILE','LOCALAPPDATA','APPDATA','HOME'];foreach(array_merge(getenv(),$_ENV,$_SERVER) as $k=>$v)if(!in_array(strtoupper($k),$allow,true))$env[$k]=false;
   if($path=config('marketing.template.python_module_path'))$env['PYTHONPATH']=$path;$env['PYTHONDONTWRITEBYTECODE']='1';
   try{$p=new Process([$tools[0],'-c','import jsonschema,numpy'],base_path(),$env);$p->setTimeout(10);$p->run();return $p->isSuccessful();}catch(\Throwable){return false;}
  });
 }
}
