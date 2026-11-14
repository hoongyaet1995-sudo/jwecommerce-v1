<?php
/** Install reviewed immutable CMS assets into the separate www public root. No database writes. */
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$web='/home/u762348670/domains/jwaistudio.online/public_html';
if(base_path()!=='/home/u762348670/apps/jw-corporate'||public_path()!==$web||parse_url(config('app.url'),PHP_URL_HOST)!=='www.jwaistudio.online')throw new RuntimeException('Restricted www paths.');
$manifest=json_decode(file_get_contents(base_path('public/build-admin/manifest.json')),true,flags:JSON_THROW_ON_ERROR);
$files=['build-ssr/page.cjs','css/cms-shell-design.css'];
foreach($manifest as $entry){$files[]='build-admin/'.$entry['file'];foreach($entry['css']??[] as $css)$files[]='build-admin/'.$css;}
foreach(json_decode(file_get_contents(base_path('resources/cms/connected-media.v1.json')),true,flags:JSON_THROW_ON_ERROR) as $url){if(!str_starts_with($url,'/cms/connected/assets/'))throw new RuntimeException('Invalid CMS media.');$files[]=ltrim($url,'/');}
$files[]='build-admin/manifest.json';
$backup=storage_path('app/releases/cms-connected-20261115/static-before');
foreach(array_unique($files) as $file){if(str_contains($file,'..')||str_starts_with($file,'/')||!is_file(base_path('public/'.$file)))throw new RuntimeException('Missing or unsafe asset.');}
$hashes=[];
foreach(array_unique($files) as $file){
 $source=base_path('public/'.$file);$dest=$web.'/'.$file;
 if(is_file($dest)&&!is_file($backup.'/'.$file)){if(!is_dir(dirname($backup.'/'.$file)))mkdir(dirname($backup.'/'.$file),0700,true);if(!copy($dest,$backup.'/'.$file))throw new RuntimeException('Backup failed.');}
 if(!is_dir(dirname($dest)))mkdir(dirname($dest),0755,true);
 $temp=$dest.'.cms-connected-20261115.tmp';
 if(!copy($source,$temp)||hash_file('sha256',$source)!==hash_file('sha256',$temp)||!rename($temp,$dest))throw new RuntimeException('Asset install failed.');
 $hashes[$file]=hash_file('sha256',$dest);
}
file_put_contents(storage_path('app/releases/cms-connected-20261115/public-files.json'),json_encode($hashes,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['status'=>'installed','files'=>count($hashes),'manifestHash'=>$hashes['build-admin/manifest.json']],JSON_THROW_ON_ERROR).PHP_EOL;
