<?php
/** Explicitly approved eight independent CMS drafts. No publication/homepage/menu/settings updates. */
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$host=parse_url(config('app.url'),PHP_URL_HOST);$roots=['www.jwaistudio.online'=>'/home/u762348670/apps/jw-corporate','ecom.jwaistudio.online'=>'/home/u762348670/apps/jwecommerce'];
$local=app()->environment('local')&&realpath(base_path())===realpath('C:/xampp/htdocs/jwstudio-ecommerce');
if(!$local&&(($roots[$host]??null)!==base_path()))throw new RuntimeException('Restricted application target.');
$bundle=json_decode(file_get_contents(base_path('resources/cms/releases/20261115-connected-starters.json')),true,flags:JSON_THROW_ON_ERROR);
$dir=storage_path('app/releases/connected-starters-20261115');$path=$dir.'/receipt.json';$receipt=is_file($path)?json_decode(file_get_contents($path),true,flags:JSON_THROW_ON_ERROR):[];
if($receipt!==[]&&($receipt['host']??null)!==$host)throw new RuntimeException('Receipt belongs to another store.');
if(in_array('--check',$argv,true)){
 $collisions=App\Models\PageDocument::whereIn('slug',array_column(array_column($bundle['pages'],'document'),'slug'))->count();
 echo json_encode(['target'=>$host,'brand'=>app(App\Support\ShopBrand::class)->name(),'planned'=>8,'receiptPresent'=>$receipt!==[],'reservedSlugCount'=>$collisions],JSON_THROW_ON_ERROR).PHP_EOL;exit;
}
if(!in_array('--apply',$argv,true))throw new RuntimeException('Use --check or --apply explicitly.');
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Cannot create private receipt directory.');
$lock=fopen($dir.'/install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another installation is running.');
try{
 $receipt=is_file($path)?json_decode(file_get_contents($path),true,flags:JSON_THROW_ON_ERROR):[];
 $result=Illuminate\Support\Facades\DB::transaction(function()use($bundle,$receipt,$path,$host){
  $result=app(App\Domain\Cms\ConnectedStarterDrafts::class)->install($bundle,app(App\Support\ShopBrand::class)->name(),$receipt);$result['host']=$host;
  if($receipt===[]){$temp=$path.'.tmp';if(file_put_contents($temp,json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false||!rename($temp,$path))throw new RuntimeException('Cannot persist receipt; drafts rolled back.');chmod($path,0600);}
  return $result;
 });
 echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{flock($lock,LOCK_UN);fclose($lock);}
