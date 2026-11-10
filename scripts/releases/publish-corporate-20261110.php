<?php
/** Scoped, www-only content promotion. Run after deploying this release and uploading its verified media. */
use App\Domain\Cms\{PageCrudService,PageDraftService,PagePublicationService,PageDocumentContract,PageRuntimePayloadFactory,HomepageSelection,RuntimeAdapterRegistry};
use App\Models\{Page,PageDocument,Menu,MenuItem,CmsMediaAsset};
use Illuminate\Support\Facades\{DB,Storage};

require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$check=in_array('--check',$argv,true);
$rehearse=in_array('--rehearse',$argv,true)&&app()->environment('testing')&&DB::connection()->getDriverName()==='sqlite';
if(!$rehearse&&(parse_url(config('app.url'),PHP_URL_HOST)!=='www.jwaistudio.online'||basename(base_path())!=='jw-corporate'))throw new RuntimeException('This content operation is restricted to the www corporate application.');
$bundle=json_decode(file_get_contents(base_path('resources/cms/releases/20261110-corporate-pages.json')),true,512,JSON_THROW_ON_ERROR);
$dir=storage_path('app/releases/corporate-20261110');if(!is_dir($dir))mkdir($dir,0700,true);
$home=app(HomepageSelection::class)->id();
$landing=PageDocument::where('stage','published')->where('slug','jw-studio')->firstOrFail()->page;
if((string)$landing->id!==$home)throw new RuntimeException('Unexpected active corporate homepage; inspect before continuing.');
$feeFacts=function(array $document):array {
 $facts=[];foreach($document['sections']??[] as $s)if(($s['type']??'')==='pricing_table'&&($s['pricingPreset']??'')==='saas-packages')foreach($s['items']??[] as $i){$row=['currency'=>$s['pricingCurrency']??null];foreach(['title','price','billingLabel','pricingMode','pricingLabel'] as $k)$row[$k]=$i[$k]??null;$facts[]=$row;}return $facts;
};
$oldFees=$feeFacts($landing->publishedDocument->document);$newFees=$feeFacts($bundle['pages']['59']['document']);
if(!$oldFees||$oldFees!==$newFees)throw new RuntimeException('Commercial facts differ from the existing corporate homepage.');
foreach($bundle['assets'] as $a){if($a['disk']!=='public'||str_contains($a['path'],'..')||!Storage::disk('public')->exists($a['path'])||hash_file('sha256',Storage::disk('public')->path($a['path']))!==$a['checksum'])throw new RuntimeException('Missing or mismatched release media: '.$a['path']);}
foreach($bundle['pages'] as $p)app(PageDocumentContract::class)->validate($p['document']);
if($check){echo json_encode(['status'=>'preflight-passed','homepage'=>$home,'assets'=>count($bundle['assets']),'commercialFactsUnchanged'=>true,'fees'=>$newFees],JSON_PRETTY_PRINT);exit;}
$existingFeature=PageDocument::where('stage','draft')->where('slug','website-builder')->first()?->page;
$backup=['createdAt'=>date(DATE_ATOM),'homepage'=>$home,'pages'=>Page::with('documents')->whereIn('id',array_filter([$landing->id,$existingFeature?->id]))->get()->toArray(),'shell'=>app(App\Services\StorefrontShellSettingsService::class)->live(),'menus'=>Menu::with('items')->whereIn('key',['header','footer'])->get()->toArray(),'media'=>CmsMediaAsset::whereIn('url',array_column($bundle['assets'],'url'))->get()->toArray()];
$backupFile=$dir.'/before-'.date('Ymd-His').'.json';file_put_contents($backupFile,json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));chmod($backupFile,0600);
$receipt=DB::transaction(function()use($bundle,$landing,$existingFeature,$home,$newFees,$feeFacts){
 foreach($bundle['assets'] as $a){$same=CmsMediaAsset::where('url',$a['url'])->first();if($same&&$same->checksum!==$a['checksum'])throw new RuntimeException('Asset URL collision');if(!$same)CmsMediaAsset::create([...$a,'uploader_id'=>null,'orphaned_at'=>now()]);}
 $doc=$bundle['pages']['59']['document'];$landing->load('draftDocument');$draft=app(PageDraftService::class)->save($landing,$doc,$landing->draftDocument->lock_version);$landing=app(PagePublicationService::class)->publish($landing,$draft->lock_version);
 $doc=$bundle['pages']['60']['document'];foreach($doc['sections'] as &$s)if(isset($s['pricingSourcePageId']))$s['pricingSourcePageId']=(string)$landing->id;unset($s);
 if($existingFeature){$draft=app(PageDraftService::class)->save($existingFeature,$doc,$existingFeature->draftDocument->lock_version);$feature=app(PagePublicationService::class)->publish($existingFeature,$draft->lock_version);}else{$feature=app(PageCrudService::class)->create($doc);$feature=app(PagePublicationService::class)->publish($feature,$feature->draftDocument->lock_version);}
 foreach($bundle['menus'] as $m){$menu=Menu::updateOrCreate(['key'=>$m['key']],['name'=>$m['name']]);$menu->items()->delete();$pending=$m['items'];$ids=[];while($pending){$next=[];$progress=false;foreach($pending as $i){if($i['parent_id']!==null&&!isset($ids[$i['parent_id']])){$next[]=$i;continue;}$oldId=$i['id'];unset($i['id'],$i['menu_id']);$i['parent_id']=$i['parent_id']!==null?$ids[$i['parent_id']]:null;$created=$menu->items()->create($i);$ids[$oldId]=$created->id;$progress=true;}if(!$progress)throw new RuntimeException('Invalid menu hierarchy');$pending=$next;}}
 $shell=app(App\Services\StorefrontShellSettingsService::class)->live();app(App\Services\StorefrontShellSettingsService::class)->save(array_replace($shell,$bundle['shell']));
 app(HomepageSelection::class)->select($landing,$landing->draftDocument->lock_version);
 if(app(HomepageSelection::class)->id()!==$home||$feeFacts($landing->publishedDocument->document)!==$newFees)throw new RuntimeException('Homepage/commercial preservation failed');
 $facts=app(PageRuntimePayloadFactory::class)->forOfficialPublishedSlug($feature->publishedDocument->slug);$pricing=collect($facts['runtime']['sections'])->pluck('starterPricing')->filter()->first();
 if(($pricing['status']??'')!=='ready'||$pricing['price']!=299)throw new RuntimeException('Published source pricing failed');
 return ['release'=>$bundle['release'],'status'=>'published','homepage'=>(string)$landing->id,'featurePage'=>(string)$feature->id,'landingHash'=>$landing->publishedDocument->content_hash,'featureHash'=>$feature->publishedDocument->content_hash,'feesUnchanged'=>true,'starterPricing'=>$pricing,'assets'=>count($bundle['assets']),'landingSections'=>count($landing->publishedDocument->document['sections']),'featureSections'=>count($feature->publishedDocument->document['sections'])];
});
$receipt['backup']=$backupFile;$receipt['gitSha']=trim(shell_exec('git rev-parse HEAD'));file_put_contents($dir.'/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));echo json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
