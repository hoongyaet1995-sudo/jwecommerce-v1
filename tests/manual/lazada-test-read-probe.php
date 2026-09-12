<?php
// Official Loan Test Account read probe. Token arrives on stdin and is never persisted.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if ((string)config('marketplace.lazada.app_key') !== '139209') throw new RuntimeException('Unexpected application.');
    fwrite(STDERR, "Ready for test token on stdin.\n");
    $token=trim(fgets(STDIN));
    $client=app(App\Services\Marketplace\LazadaClient::class);
    $seller=$client->read('/seller/get',$token);
    if ((string)($seller['seller_id']??'')!=='300163725140' || ($seller['short_code']??'')!=='MY4NA1T7CK') throw new RuntimeException('Unexpected test seller.');
    $page=$client->read('/products/get',$token,['filter'=>'all','limit'=>1,'offset'=>0]);
    $id=(string)($page['products'][0]['item_id']??'');
    $item=$client->read('/product/item/get',$token,['item_id'=>$id]);
    $orders=$client->read('/orders/get',$token,['created_after'=>'2026-09-01T00:00:00+08:00','created_before'=>'2026-09-09T00:00:00+08:00','status'=>'all','limit'=>1,'offset'=>0]);
    $orderId=(string)($orders['orders'][0]['order_id']??'');
    $lines=$client->read('/order/items/get',$token,['order_id'=>$orderId]);
    echo json_encode(['source'=>'local_laravel_official_test_token','oauth_verified'=>false,
        'seller_id'=>(string)$seller['seller_id'],'catalog_total'=>$page['total_products']??null,
        'item_id'=>$id,'category_keys'=>array_values(array_filter(array_keys($item),fn($key)=>stripos($key,'category')!==false)),
        'sku_count'=>count($item['skus']??[]),'order_total'=>$orders['countTotal']??null,
        'order_id'=>$orderId,'order_line_count'=>count($lines),'order_lines_are_list'=>array_is_list($lines)],JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Probe failed: '.get_class($error).PHP_EOL); exit(1);
}
