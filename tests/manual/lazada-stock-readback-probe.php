<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only.');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    if (!$store || App\Models\MarketplaceStoreToken::where('store_id',$store->id)->value('credential_source')!=='official_test') throw new RuntimeException('Test credential required.');
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
    $client=app(App\Services\Marketplace\LazadaClient::class);
    $read=function () use ($client,$token) {
        $item=$client->read('/product/item/get',$token,['item_id'=>'14984118945']);
        foreach ($item['skus']??[] as $sku) {
            if ((string)($sku['SkuId']??'')!=='128571056069') continue;
            foreach ($sku['multiWarehouseInventories']??[] as $inventory) {
                if (($inventory['warehouseCode']??'')==='dropshipping') {
                    $quantity=$inventory['sellableQuantity']??null;
                    if (!is_int($quantity)||$quantity<0) throw new RuntimeException('Invalid stock.');
                    return $quantity;
                }
            }
        }
        throw new RuntimeException('Exact SKU warehouse not returned.');
    };
    $before=$read();
    config(['marketplace.stock_sync_operations_enabled'=>true]);
    $client->updateAbsoluteQuantity($token,'14984118945','128571056069',$before,'dropshipping');
    $after=$read();
    echo json_encode(['item_id'=>'14984118945','sku_id'=>'128571056069','warehouse'=>'dropshipping',
        'before'=>$before,'requested'=>$before,'after'=>$after,'matched'=>$before===$after]).PHP_EOL;
    if ($before!==$after) exit(2);
} catch (Throwable $error) {
    fwrite(STDERR,'Stock readback probe failed ('.get_class($error).'). Check provider state before retry.'.PHP_EOL);
    exit(1);
}
