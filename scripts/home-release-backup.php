<?php
/** Produce a private dump options file; mysqldump runs through SSH, never PHP shell functions. */
require __DIR__.'/../vendor/autoload.php';$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if(!app()->environment('production')||base_path()!=='/home/u762348670/apps/jwecommerce'||parse_url(config('app.url'),PHP_URL_HOST)!=='ecom.jwaistudio.online'||!app()->isDownForMaintenance()||DB::connection()->getDatabaseName()!=='u762348670_jwstudio_ecom')throw new RuntimeException('Approved production cutover only');
$dir=storage_path('app/private/backups/home-release-20261006');$options=$dir.'/client.cnf';$dump=$dir.'/before.sql';
if(($argv[1]??'')==='--prepare'){
 if(is_dir($dir))throw new RuntimeException('Backup directory already exists; inspect before reuse');mkdir($dir,0700,true);
 $quote=fn($v)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$v).'"';$db=DB::connection();
 file_put_contents($options,"[client]\nhost=".$quote($db->getConfig('host'))."\nport=".$quote($db->getConfig('port'))."\nuser=".$quote($db->getConfig('username'))."\npassword=".$quote($db->getConfig('password'))."\n");chmod($options,0600);echo "Private dump options prepared\n";
}elseif(($argv[1]??'')==='--finish'){
 if(is_file($options))unlink($options);
 if(!is_file($dump)||filesize($dump)<1024)throw new RuntimeException('Dump missing or too small');chmod($dump,0600);
 $header=file_get_contents($dump,false,null,0,8192);if(!str_contains($header,'Current Database: `u762348670_jwstudio_ecom`')||(!str_contains($header,'MariaDB dump')&&!str_contains($header,'MySQL dump')))throw new RuntimeException('Dump database header mismatch');
 $metadata=['path'=>$dump,'sha256'=>hash_file('sha256',$dump),'bytes'=>filesize($dump),'database'=>DB::connection()->getDatabaseName()];file_put_contents(storage_path('app/private/home-release-backup.json'),json_encode($metadata));echo json_encode($metadata)."\n";
}else throw new RuntimeException('Choose --prepare or --finish');
