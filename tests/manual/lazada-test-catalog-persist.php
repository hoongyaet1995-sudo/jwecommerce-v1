<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
 if(!$store)throw new RuntimeException('Test connection missing');
 $result=app(App\Services\Marketplace\LazadaCatalogService::class)->pullPage($store->id,0);
 echo json_encode(['result'=>$result,'persisted_variants'=>Illuminate\Support\Facades\DB::table('marketplace_listing_variants')->where('store_id',$store->id)->count()]).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Catalog failed: '.get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
