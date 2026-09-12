<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local')) exit(1);
$store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
if (!$store) exit(1);
$orders=Illuminate\Support\Facades\DB::table('marketplace_orders')->where('store_id',$store->id)
    ->select('id','store_id','platform_status','platform_updated_at')->orderBy('id')->paginate(25);
app(App\Services\Marketplace\MarketplaceReconciliationPage::class)->annotate($orders,'lazada');
$ok=true;
foreach ($orders as $order) {
    $ok=$ok&&$order->demand['error']===null;
    echo json_encode(['order_id'=>$order->id,'status'=>$order->platform_status,'demand'=>$order->demand]).PHP_EOL;
}
exit($ok?0:1);
