<?php
namespace App\Services\Support;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
class SupportPdfProcessor {
 public const MAX_BYTES=10485760;
 private function inspect(array $args):int {
  $p=new Process($args);$p->setTimeout(10);$size=0;
  try {$p->run(function($type,$buffer)use(&$size){$size+=strlen($buffer);if($size>262144)throw new \RuntimeException();});}
  catch(\Throwable){throw ValidationException::withMessages(['document'=>'PDF inspection is unavailable or timed out. Contact your administrator.']);}
  return $p->getExitCode()??-1;
 }
 public function validate(string $bytes):string {
  if(strlen($bytes)>self::MAX_BYTES||!str_starts_with($bytes,'%PDF-')||(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes)!=='application/pdf')throw ValidationException::withMessages(['document'=>'Use a valid PDF up to 10 MB.']);
  $dir=storage_path('app/support-private/processing/'.Str::uuid());if(!mkdir($dir,0700,true))throw new \RuntimeException('Media processing unavailable.');$path=$dir.'/document.pdf';
  try{file_put_contents($path,$bytes);$bin=config('support_media.qpdf');
   if($this->inspect([$bin,'--is-encrypted',$path])!==2||$this->inspect([$bin,'--check',$path])!==0)throw ValidationException::withMessages(['document'=>'Use a valid PDF without password protection.']);
   return $bytes;
  }finally{if(is_file($path))unlink($path);rmdir($dir);}
 }
}
