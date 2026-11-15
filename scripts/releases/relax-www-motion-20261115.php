<?php
/** Only the authorized www application. No publication or ecom writes. */
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(base_path()!=='/home/u762348670/apps/jw-corporate'||parse_url(config('app.url'),PHP_URL_HOST)!=='www.jwaistudio.online')throw new RuntimeException('Restricted to www corporate application.');
$plan=json_decode(file_get_contents(base_path('resources/cms/releases/20261115-www-relaxed-default.json')),true,flags:JSON_THROW_ON_ERROR);$service=app(App\Domain\Cms\WwwRelaxedDefaults::class);
if(in_array('--check',$argv,true)){$service->ready($plan);echo json_encode(['status'=>'preflight-passed','pages'=>$plan['pageIds'],'documents'=>count($plan['documents'])],JSON_THROW_ON_ERROR);exit;}
if(!in_array('--apply',$argv,true))throw new RuntimeException('Use --check or --apply explicitly.');
$dir=storage_path('app/releases/www-relaxed-default-20261115');if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Backup directory failed.');$lock=fopen($dir.'/release.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Release already running.');
try{
 $service->ready($plan);$backup=$dir.'/before.json';
 if(!is_file($backup)){$rows=Illuminate\Support\Facades\DB::table('page_documents')->whereIn('id',array_column($plan['documents'],'id'))->orderBy('id')->get()->all();if(file_put_contents($backup,json_encode(['at'=>date(DATE_ATOM),'documents'=>$rows],JSON_THROW_ON_ERROR))===false)throw new RuntimeException('Backup failed.');chmod($backup,0600);}
 $receipt=$service->run($plan);$receipt['backup']=$backup;
 if(file_put_contents($dir.'/receipt.tmp.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false||!rename($dir.'/receipt.tmp.json',$dir.'/receipt.json'))throw new RuntimeException('Receipt write failed.');chmod($dir.'/receipt.json',0600);
 echo json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
}finally{flock($lock,LOCK_UN);fclose($lock);}
