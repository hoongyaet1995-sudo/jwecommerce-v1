<?php
/** Authorized www publication only. Never run against ecom or import local QA drafts. */
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(base_path()!=='/home/u762348670/apps/jw-corporate'||parse_url(config('app.url'),PHP_URL_HOST)!=='www.jwaistudio.online')throw new RuntimeException('Restricted to www corporate application.');
$bundle=json_decode(file_get_contents(base_path('resources/cms/releases/20261115-www-connected-publication.json')),true,flags:JSON_THROW_ON_ERROR);$publisher=app(App\Domain\Cms\ConnectedWwwPublication::class);
if(in_array('--check',$argv,true)){$publisher->ready($bundle);echo json_encode(['status'=>'preflight-passed','pages'=>array_column($bundle['pages'],'id'),'oldHome'=>$bundle['expectedHome'],'newHome'=>$bundle['homePage'],'menuUpdates'=>count($bundle['updates'])],JSON_THROW_ON_ERROR).PHP_EOL;exit;}
if(!in_array('--apply',$argv,true))throw new RuntimeException('Use --check or --apply explicitly.');
$dir=storage_path('app/releases/www-connected-publication-20261115');if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Cannot create backup directory.');$lock=fopen($dir.'/publish.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another publication is running.');
try{
 $backupFile=$dir.'/before.json';if(!is_file($backupFile)){
  $publisher->ready($bundle);$ids=array_merge(array_column($bundle['pages'],'id'),[(int)$bundle['expectedHome']]);
  $backup=['at'=>date(DATE_ATOM),'pages'=>App\Models\Page::with('documents')->whereIn('id',$ids)->get()->toArray(),'menus'=>App\Models\Menu::with('items')->whereIn('key',['header','footer'])->get()->toArray(),'selection'=>App\Models\StoreSetting::where('key',App\Domain\Cms\HomepageSelection::KEY)->first()?->toArray()];
  if(file_put_contents($backupFile,json_encode($backup,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('Backup failed.');chmod($backupFile,0600);
 }
 $receipt=$publisher->run($bundle);$receipt['backup']=$backupFile;$tmp=$dir.'/receipt.tmp.json';if(file_put_contents($tmp,json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false||!rename($tmp,$dir.'/receipt.json'))throw new RuntimeException('Cannot persist publication receipt.');chmod($dir.'/receipt.json',0600);
 Illuminate\Support\Facades\Cache::forget('search:sitemap');echo json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{flock($lock,LOCK_UN);fclose($lock);}
