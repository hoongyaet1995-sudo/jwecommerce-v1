<?php
/** Install only approved Builder/public bundles and section preview HTML. No database or uploaded-media writes. */
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$targets=[
 '/home/u762348670/apps/jw-corporate'=>['www.jwaistudio.online','/home/u762348670/domains/jwaistudio.online/public_html'],
 '/home/u762348670/apps/jwecommerce'=>['ecom.jwaistudio.online','/home/u762348670/apps/jwecommerce/public'],
];
$target=$targets[base_path()]??null;
if(!$target||parse_url(config('app.url'),PHP_URL_HOST)!==$target[0]||public_path()!==$target[1])throw new RuntimeException('Restricted application and public roots.');
$manifest=json_decode(file_get_contents(base_path('public/build-admin/manifest.json')),true,flags:JSON_THROW_ON_ERROR);
$files=['build-ssr/page.cjs','build-admin/src/section-preview/index.html'];
foreach($manifest as $entry){$files[]='build-admin/'.$entry['file'];foreach($entry['css']??[] as $css)$files[]='build-admin/'.$css;}
$files=array_values(array_unique($files));$files[]='build-admin/manifest.json';
foreach($files as $file){
 if(str_contains($file,'..')||!preg_match('#\A(?:build-admin/[a-zA-Z0-9_./-]+|build-ssr/page\.cjs|images/cms/defaults/neutral/[a-z0-9-]+\.svg)\z#D',$file)||!is_file(base_path('public/'.$file)))throw new RuntimeException('Missing or unsafe static asset.');
}
$receipt=storage_path('app/releases/alignment-173-20261117');$hashes=[];
foreach($files as $file){
 $source=base_path('public/'.$file);$destination=$target[1].'/'.$file;
 if($source!==$destination){
  $backup=$receipt.'/static-before/'.$file;
  if(is_file($destination)&&!is_file($backup)){if(!is_dir(dirname($backup)))mkdir(dirname($backup),0700,true);if(!copy($destination,$backup))throw new RuntimeException('Backup failed.');}
  if(!is_dir(dirname($destination)))mkdir(dirname($destination),0755,true);
  $temporary=$destination.'.alignment.tmp';
  if(!copy($source,$temporary)||hash_file('sha256',$source)!==hash_file('sha256',$temporary)||!rename($temporary,$destination))throw new RuntimeException('Static install failed.');
 }
 $hashes[$file]=hash_file('sha256',$destination);
}
if(!is_dir($receipt))mkdir($receipt,0700,true);
file_put_contents($receipt.'/public-files.json',json_encode($hashes,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['status'=>'installed','host'=>$target[0],'files'=>count($hashes),'manifestHash'=>$hashes['build-admin/manifest.json']],JSON_THROW_ON_ERROR).PHP_EOL;
