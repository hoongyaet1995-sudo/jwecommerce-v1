<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (!$app->environment('local')) throw new RuntimeException('Local only.');
config(['database.connections.mysql.database'=>'jw_basic_wms_shared_acceptance_0908']);
DB::purge('mysql');
$variant=DB::table('product_variants')->where('sku','JW-QA-SHARED-0908')->first();
$balance=DB::table('inventory_balances')->where('product_variant_id',$variant->id)->first();
$configuration=DB::table('inventory_configuration')->where('id',1)->first();
if ($configuration->operating_mode!=='shared_pool' || $configuration->accounting_enabled
    || (int)$variant->stock!==17 || (int)$balance->physical_stock!==21 || (int)$balance->safety_stock!==2
    || DB::table('stock_allocations')->count()!==0) throw new RuntimeException('Shared acceptance invariant failed.');
echo json_encode(['database'=>DB::connection()->getDatabaseName(),'mode'=>$configuration->operating_mode,
    'accounting_enabled'=>(bool)$configuration->accounting_enabled,'legacy_stock'=>(int)$variant->stock,
    'balance'=>['physical'=>(int)$balance->physical_stock,'safety'=>(int)$balance->safety_stock,'version'=>(int)$balance->version],
    'recent_movements'=>DB::table('inventory_physical_movements')->orderByDesc('id')->limit(4)
        ->get(['physical_before','physical_after','physical_delta','actor_id','reason','created_at'])],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
