<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) exit(1);
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    if (!$store) exit(1);
    $from=Carbon\CarbonImmutable::parse('2026-09-08 16:50:00','Asia/Kuala_Lumpur')->timestamp;
    $to=Carbon\CarbonImmutable::parse('2026-09-08 17:10:00','Asia/Kuala_Lumpur')->timestamp;
    $result=app(App\Services\Marketplace\LazadaOrderService::class)->pullPage($store->id,$from,$to);
    $orders=Illuminate\Support\Facades\DB::table('marketplace_orders')->where('store_id',$store->id)
        ->whereIn('external_order_id',['509552044443475','512830544343475'])->get(['id','external_order_id','platform_status']);
    echo json_encode(['capture'=>$result,'orders'=>$orders]).PHP_EOL;
} catch(Throwable $error) {fwrite(STDERR,'Pending order capture failed: '.get_class($error).PHP_EOL);exit(1);}
