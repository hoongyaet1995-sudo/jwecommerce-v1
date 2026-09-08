<?php
// Approved test-shop read only. No provider mutation or raw payload output.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('id',1)->first();
    if (config('marketplace.shopee.environment')!=='sandbox' || !config('marketplace.read_operations_enabled')
        || !$store || $store->platform!=='shopee' || $store->environment!=='sandbox' || $store->status!=='connected'
        || (string)$store->external_shop_id!=='227482706') throw new RuntimeException('Approved sandbox store required');
    $token=app(App\Services\Marketplace\ShopeeTokenManager::class)->accessToken(1);
    $client=app(App\Services\Marketplace\ShopeeClient::class);
    $items=$client->itemDetails($token,$store->external_shop_id,['846055463']);
    foreach ($items as $item) {
        if ((string)($item['item_id'] ?? '')!=='846055463') throw new RuntimeException('Unexpected item');
        echo json_encode(['item_id'=>$item['item_id'],'has_model'=>$item['has_model'] ?? null,
            'stock_info_v2'=>$item['stock_info_v2'] ?? null],JSON_PRETTY_PRINT)."\n";
        if (($item['has_model'] ?? false)===true) {
            foreach ($client->models($token,$store->external_shop_id,'846055463') as $model) {
                echo json_encode(['model_id'=>$model['model_id'],'stock_info_v2'=>$model['stock_info_v2'] ?? null],JSON_PRETTY_PRINT)."\n";
            }
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR,'Probe failed: '.get_class($error)."\n"); exit(1);
}
