<?php

// Read-only provider probe for the configured Lazada borrowed test seller.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $storeId=(int)config('marketplace.lazada.test_store_id');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->find($storeId);
    if (!$store || $store->external_shop_id!=='300163725140' || $store->account_mode!=='test') {
        throw new RuntimeException('Configured borrowed test store is unavailable.');
    }
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($storeId);
    $client=app(App\Services\Marketplace\LazadaClient::class);
    $seller=$client->read('/seller/get',$token,[],$storeId);
    if ((string)($seller['seller_id']??'')!==$store->external_shop_id || ($seller['short_code']??'')!=='MY4NA1T7CK') {
        throw new RuntimeException('Provider seller identity mismatch.');
    }
    $products=$client->read('/products/get',$token,['filter'=>'all','limit'=>1,'offset'=>0],$storeId);
    $from=now('Asia/Kuala_Lumpur')->subDays(30)->format('Y-m-d\\TH:i:sP');
    $to=now('Asia/Kuala_Lumpur')->format('Y-m-d\\TH:i:sP');
    $orders=$client->read('/orders/get',$token,['created_after'=>$from,'created_before'=>$to,'status'=>'all','limit'=>1,'offset'=>0],$storeId);
    echo json_encode([
        'store_id'=>$storeId,
        'seller_id'=>(string)$seller['seller_id'],
        'short_code'=>$seller['short_code'],
        'seller_status'=>$seller['status']??null,
        'catalog_total'=>(int)($products['total_products']??$products['total']??$products['countTotal']??0),
        'order_total'=>(int)($orders['countTotal']??0),
        'app_identity'=>'testing_app',
    ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Probe failed: '.get_class($error).' '.preg_replace('/[^A-Za-z0-9 ._-]/','',$error->getMessage()).PHP_EOL);
    exit(1);
}
