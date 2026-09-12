<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local')) exit(1);
$warehouse=Illuminate\Support\Facades\DB::table('inventory_warehouses')->where('code','QA-ZIP36-CYB')->first();
$variant=Illuminate\Support\Facades\DB::table('product_variants')->where('sku','JW-SBX-LZ-SHIRT-001')->first();
if (!$warehouse || !$variant) throw new RuntimeException('Named local test fixtures are missing.');
$balance=app(App\Services\Inventory\InventoryBalanceProvisioner::class)->provision((int)$variant->id,'warehouse',(int)$warehouse->id);
$route=Illuminate\Support\Facades\DB::table('inventory_fulfillment_routes')->where('channel_key','marketplace:3')
    ->first(['channel_key','warehouse_id','platform_location_id','is_enabled','version']);
echo json_encode(['warehouse_id'=>(int)$warehouse->id,'variant_id'=>(int)$variant->id,'balance'=>$balance,'test_store_route'=>$route],JSON_PRETTY_PRINT).PHP_EOL;
