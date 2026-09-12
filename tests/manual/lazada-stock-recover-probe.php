<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$stage='credential';
try {
    if (!app()->environment('local')) throw new RuntimeException('local');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    if (!$store || App\Models\MarketplaceStoreToken::where('store_id',$store->id)->value('credential_source')!=='official_test') throw new RuntimeException('test');
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
    $client=app(App\Services\Marketplace\LazadaClient::class);
    $stage='read';
    $item=$client->read('/product/item/get',$token,['item_id'=>'14984118945']);
    foreach ($item['skus']??[] as $sku) {
        if ((string)($sku['SkuId']??'')!=='128571056069') continue;
        foreach ($sku['multiWarehouseInventories']??[] as $inventory) {
            if (($inventory['warehouseCode']??'')!=='dropshipping') continue;
            $quantity=$inventory['sellableQuantity']??null;
            echo json_encode(['observed'=>$quantity]).PHP_EOL;
            if ($quantity===20) exit(0);
            if ($quantity!==19) throw new RuntimeException('Unexpected quantity');
            $stage='restore';
            config(['marketplace.stock_sync_operations_enabled'=>true]);
            $client->updateAbsoluteQuantity($token,'14984118945','128571056069',20,'dropshipping');
            echo "Restore accepted; run read recovery again to confirm.\n";
            exit(2);
        }
    }
    throw new RuntimeException('Missing warehouse');
} catch (Throwable $error) {
    fwrite(STDERR,'Recovery stopped at '.$stage.' ('.get_class($error).').'.PHP_EOL); exit(1);
}
