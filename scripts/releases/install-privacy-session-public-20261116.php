<?php
/** Approved www tracking asset only. No settings, consent or analytics data writes. */
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$web='/home/u762348670/domains/jwaistudio.online/public_html';
if(base_path()!=='/home/u762348670/apps/jw-corporate'||public_path()!==$web||parse_url(config('app.url'),PHP_URL_HOST)!=='www.jwaistudio.online')throw new RuntimeException('Restricted www target.');
$source=base_path('public/marketing-studio/tracking.js');$dest=$web.'/marketing-studio/tracking.js';$hash=hash_file('sha256',$source);
if(hash_file('sha256',$dest)===$hash){echo json_encode(['status'=>'already-installed','sha256'=>$hash]);exit;}
if(hash_file('sha256',$dest)!=='bd75276759e099fb840f2e942ce86509028732b31c991fc4fe795ceeffbebb0e')throw new RuntimeException('Live tracking asset changed since preflight.');
$dir=storage_path('app/releases/privacy-session-20261116');if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Backup directory failed.');
if(!is_file($dir.'/tracking.before.js')&&!copy($dest,$dir.'/tracking.before.js'))throw new RuntimeException('Backup failed.');chmod($dir.'/tracking.before.js',0600);
$temp=$dest.'.privacy-session-20261116.tmp';
if(!copy($source,$temp)||hash_file('sha256',$temp)!==$hash||!rename($temp,$dest))throw new RuntimeException('Asset installation failed.');
echo json_encode(['status'=>'installed','sha256'=>hash_file('sha256',$dest)],JSON_THROW_ON_ERROR);
