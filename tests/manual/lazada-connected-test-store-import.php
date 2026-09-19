<?php

// Real read-only acceptance path: provider pages are saved through production services.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $storeId=(int)config('marketplace.lazada.test_store_id');
    $beforeListings=Illuminate\Support\Facades\DB::table('marketplace_listing_variants')->where('store_id',$storeId)->count();
    $beforeOrders=Illuminate\Support\Facades\DB::table('marketplace_orders')->where('store_id',$storeId)->count();
    $catalog=app(App\Services\Marketplace\LazadaCatalogService::class)->pullPage($storeId,0);
    $orders=app(App\Services\Marketplace\LazadaOrderService::class)->pullPage(
        $storeId,now()->subDays(7)->timestamp,now()->timestamp,0
    );
    echo json_encode([
        'store_id'=>$storeId,
        'catalog'=>$catalog,
        'orders'=>$orders,
        'listing_variants_before'=>$beforeListings,
        'listing_variants_after'=>Illuminate\Support\Facades\DB::table('marketplace_listing_variants')->where('store_id',$storeId)->count(),
        'orders_before'=>$beforeOrders,
        'orders_after'=>Illuminate\Support\Facades\DB::table('marketplace_orders')->where('store_id',$storeId)->count(),
    ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Import failed: '.get_class($error).' '.preg_replace('/[^A-Za-z0-9 ._-]/','',$error->getMessage()).PHP_EOL);
    exit(1);
}
