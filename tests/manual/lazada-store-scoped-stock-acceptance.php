<?php

// Idempotent stock-write acceptance: writes zero to an already zero test SKU.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $storeId=(int)config('marketplace.lazada.test_store_id');
    $itemId='14989313849';$skuId='128582795985';
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($storeId);
    $client=app(App\Services\Marketplace\LazadaClient::class);
    $before=$client->read('/product/item/get',$token,['item_id'=>$itemId],$storeId);
    $sku=collect($before['skus']??[])->first(fn($row)=>(string)($row['SkuId']??$row['sku_id']??'')===$skuId);
    if (!$sku || (string)($sku['SellerSku']??'')!=='JW-LZ-DUAL-APP-20260919') throw new RuntimeException('Acceptance SKU identity mismatch.');
    $client->updateAbsoluteQuantity($token,$itemId,$skuId,0,'dropshipping',$storeId);
    $after=$client->read('/product/item/get',$token,['item_id'=>$itemId],$storeId);
    $confirmed=collect($after['skus']??[])->first(fn($row)=>(string)($row['SkuId']??$row['sku_id']??'')===$skuId);
    $inventory=collect($confirmed['multiWarehouseInventories']??[])->firstWhere('warehouseCode','dropshipping');
    if (!$inventory || (int)($inventory['sellableQuantity']??-1)!==0) throw new RuntimeException('Zero stock readback was not confirmed.');
    echo json_encode(['store_id'=>$storeId,'item_id'=>$itemId,'sku_id'=>$skuId,'requested_quantity'=>0,
        'confirmed_sellable_quantity'=>(int)$inventory['sellableQuantity'],'warehouse_code'=>'dropshipping'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Stock acceptance stopped: '.get_class($error).' '.preg_replace('/[^A-Za-z0-9 ._-]/','',$error->getMessage()).PHP_EOL);
    exit(1);
}
