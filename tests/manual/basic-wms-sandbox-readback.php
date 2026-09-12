<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local'))throw new RuntimeException('Local acceptance only.');
$db=Illuminate\Support\Facades\DB::class;
$attempts=$db::table('sandbox_inventory_attempts')->where('store_id',3)->where('inventory_balance_id',10)->orderByDesc('created_at')->limit(5)
 ->get(['id','status','actor_id','reason','before_quantity','observed_quantity','error_code','created_at','updated_at']);
$balance=$db::table('inventory_balances')->where('id',10)->first(['physical_stock','safety_stock','version']);
echo json_encode(['attempts'=>$attempts,'balance'=>$balance,'legacy_stock'=>$db::table('product_variants')->where('id',102)->value('stock'),
 'accounting_enabled'=>(bool)$db::table('inventory_configuration')->where('id',1)->value('accounting_enabled'),
 'production_broadcast_enabled'=>(bool)config('marketplace.stock_sync_operations_enabled')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
