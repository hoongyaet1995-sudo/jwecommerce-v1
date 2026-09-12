<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local'))throw new RuntimeException('Local acceptance only.');
$rows=Illuminate\Support\Facades\DB::table('inventory_physical_movements')
    ->where('reason','like','%BASIC-WMS-20260908 browser%')->orderBy('id')
    ->get(['id','inventory_balance_id','actor_id','physical_before','physical_after','physical_delta','balance_version','reason','created_at']);
echo json_encode(['movements'=>$rows,'accounting_enabled'=>(bool)Illuminate\Support\Facades\DB::table('inventory_configuration')->where('id',1)->value('accounting_enabled'),
    'stock_sync_enabled'=>(bool)config('marketplace.stock_sync_enabled')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
