<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 if (!app()->environment('local')) exit(1);
 $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
 $result=app(App\Services\Marketplace\LazadaPackingPreflight::class)->inspect($store->id,'512827127743475',['512827127843475','512827127943475'],'TFS');
 echo json_encode(['order_id'=>$result['order_id'],'eligible_items'=>count($result['items']),
   'platform_default'=>$result['platform_default'],'allocation_type'=>$result['shipping_allocate_type']]).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'Packing preflight failed: '.get_class($error).PHP_EOL); exit(1); }
