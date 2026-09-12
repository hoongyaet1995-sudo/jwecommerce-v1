<?php
// Authorized borrowed seller only. Decrement one unit, observe, then restore.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$stage='connection';
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only.');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    if (!$store || App\Models\MarketplaceStoreToken::where('store_id',$store->id)->value('credential_source')!=='official_test') throw new RuntimeException('Test credential required.');
    $stage='credential';
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
    $client=app(App\Services\Marketplace\LazadaClient::class);
    $read=function () use ($client,$token) {
        $item=$client->read('/product/item/get',$token,['item_id'=>'14984118945']);
        foreach ($item['skus']??[] as $sku) {
            if ((string)($sku['SkuId']??'')!=='128571056069') continue;
            $balances=[];
            foreach ($sku['multiWarehouseInventories']??[] as $inventory) {
                $code=$inventory['warehouseCode']??null;
                $quantity=$inventory['sellableQuantity']??null;
                if (!is_string($code)||!is_int($quantity)||$quantity<0||isset($balances[$code])) throw new RuntimeException('Invalid warehouse snapshot.');
                $balances[$code]=$quantity;
            }
            if (!isset($balances['dropshipping'])) throw new RuntimeException('Target warehouse missing.');
            ksort($balances); return $balances;
        }
        throw new RuntimeException('Target SKU missing.');
    };
    $stage='initial_read';
    $before=$read();
    if ($before['dropshipping']<1) throw new RuntimeException('No stock available for decrement.');
    $expected=$before; $expected['dropshipping']--;
    echo json_encode(['step'=>'before','warehouses'=>$before]).PHP_EOL;
    config(['marketplace.stock_sync_operations_enabled'=>true]);
    $stage='decrement';
    $client->updateAbsoluteQuantity($token,'14984118945','128571056069',$expected['dropshipping'],'dropshipping');
    $stage='decrement_readback';
    $changed=$read();
    echo json_encode(['step'=>'changed','warehouses'=>$changed,'expected_match'=>$changed===$expected]).PHP_EOL;
    if ($changed!==$expected) throw new RuntimeException('Unexpected concurrent state: inspect before restoration.');
    $stage='restore';
    $client->updateAbsoluteQuantity($token,'14984118945','128571056069',$before['dropshipping'],'dropshipping');
    $stage='restore_readback';
    $restored=$read();
    echo json_encode(['step'=>'restored','warehouses'=>$restored,'original_match'=>$restored===$before]).PHP_EOL;
    if ($restored!==$before) exit(2);
} catch (Throwable $error) {
    fwrite(STDERR,'Transition probe stopped at '.$stage.' ('.get_class($error).'). Inspect printed last state before any retry.'.PHP_EOL);
    exit(1);
}
