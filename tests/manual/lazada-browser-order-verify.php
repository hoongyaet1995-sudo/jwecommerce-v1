<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
    $items=app(App\Services\Marketplace\LazadaClient::class)->read('/order/items/get',$token,['order_id'=>'512830544343475']);
    $attempt=Illuminate\Support\Facades\DB::table('marketplace_fulfillment_attempts')->where('order_id',2)->where('operation','pack')->orderByDesc('id')->first();
    $confirmed=json_decode($attempt->confirmed_result,true,512,JSON_THROW_ON_ERROR)['items'];
    $verified=[];
    foreach ($items as $item) {
        $id=(string)($item['order_item_id']??'');
        if (!isset($confirmed[$id])||isset($verified[$id])||(string)($item['order_id']??'')!=='512830544343475'
            ||($item['status']??null)!=='ready_to_ship'||($item['package_id']??null)!==$confirmed[$id]['package_id']) throw new RuntimeException('Provider package state mismatch');
        $verified[$id]=['status'=>$item['status'],'package_id'=>$item['package_id']];
    }
    if (count($verified)!==count($confirmed)) throw new RuntimeException('Missing packed items');
    echo json_encode(['provider_readback_verified'=>true,'items'=>$verified],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'RTS order verification failed: '.get_class($error).PHP_EOL); exit(1); }
