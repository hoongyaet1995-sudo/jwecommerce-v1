<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    if (!$store) throw new RuntimeException('Test store missing');
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
    $item=app(App\Services\Marketplace\LazadaClient::class)->read('/product/item/get',$token,['item_id'=>'14984118945']);
    $skus=[];
    foreach ($item['skus']??[] as $sku) {
        $skus[]=array_intersect_key($sku,array_flip(['SellerSku','SkuId','price','package_height','package_length','package_width','package_weight','package_content','color_family','Images']));
    }
    echo json_encode(['category'=>$item['primary_category']??null,'attributes'=>$item['attributes']??[],
        'images'=>$item['images']??[],'skus'=>$skus],JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'Source read failed: '.get_class($error).PHP_EOL); exit(1); }
