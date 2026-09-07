<?php

// Synthetic fixtures, real concurrent MySQL connections, disposable local schema only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Inventory\StockAllocationService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if (!app()->environment('local') || !in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true)) exit(2);
$worker = ($argv[1] ?? '') === 'worker';
$mode = $worker ? ($argv[3] ?? '') : ($argv[1] ?? 'compete');
if (!in_array($mode, ['compete', 'duplicate', 'order-compete', 'order-duplicate'], true)) exit(2);
$orderMode = str_starts_with($mode, 'order-');
$duplicate = str_ends_with($mode, 'duplicate');
$schema = $worker ? ($argv[2] ?? '') : 'jw_stock_race_'.bin2hex(random_bytes(6));
if (!preg_match('/^jw_stock_race_[a-f0-9]{12}$/D', $schema) || $schema === config('database.connections.mysql.database')) exit(2);
$connection = config('database.connections.mysql');
config(['database.default'=>'mysql', 'database.connections.mysql.url'=>null]);
$created = false;
$processes = [];
$failed = false;
try {
    if (!$worker) {
        DB::statement("CREATE DATABASE `$schema`");
        $created = true;
        $migration = new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), [
            'APP_ENV'=>'testing', 'DB_CONNECTION'=>'mysql', 'DB_URL'=>'', 'DB_HOST'=>$connection['host'],
            'DB_PORT'=>(string) $connection['port'], 'DB_DATABASE'=>$schema, 'DB_USERNAME'=>$connection['username'],
            'DB_PASSWORD'=>$connection['password'], 'CACHE_STORE'=>'array', 'SESSION_DRIVER'=>'array',
        ]);
        $migration->setTimeout(120);
        $migration->run();
        if (!$migration->isSuccessful()) throw new RuntimeException('Isolated migrations failed');
    }
    config(['database.connections.mysql.database'=>$schema]);
    DB::purge('mysql');
    if (DB::selectOne('SELECT DATABASE() AS db')->db !== $schema) throw new RuntimeException('Isolation failed');
    if ($worker) {
        echo "ready\n"; flush();
        if ($orderMode) {
            config(['marketplace.inventory_accounting_enabled'=>true]);
            $id = app(\App\Services\Marketplace\MarketplaceOrderSnapshotService::class)->saveShopee((int) $argv[4], [
                'order_sn'=>'RACE-ORDER', 'order_status'=>'READY_TO_SHIP', 'create_time'=>1500, 'update_time'=>2000,
                'fulfillment_flag'=>'fulfilled_by_local_seller',
                'item_list'=>[['item_id'=>101, 'model_id'=>201, 'model_quantity_purchased'=>7]],
            ]);
            $result = DB::table('marketplace_order_inventory')->where('order_id', $id)->first();
            if ($result?->status === 'reserved') echo "allocated:$id\n";
            elseif ($result?->error_code === 'insufficient_stock') echo "unavailable\n";
            else throw new RuntimeException('Unexpected order result');
            exit(0);
        }
        try {
            $id = app(StockAllocationService::class)->reserve('marketplace_order', (int) ($argv[4] ?? 0), 'line-1', 1, 7);
            echo "allocated:$id\n";
        } catch (RuntimeException $error) {
            if ($error->getMessage() !== 'Requested quantity is unavailable.') throw $error;
            echo "unavailable\n";
        }
        exit(0);
    }
    DB::table('products')->insert(['id'=>1, 'title'=>'Fixture', 'handle'=>'stock-race-fixture', 'base_price'=>100, 'status'=>'draft']);
    DB::table('product_variants')->insert(['id'=>1, 'product_id'=>1, 'sku'=>'STOCK-RACE', 'title'=>'Fixture', 'price'=>100, 'stock'=>10]);
    if ($orderMode) {
        foreach ([1, 2] as $storeId) {
            DB::table('marketplace_stores')->insert(['id'=>$storeId, 'platform'=>'shopee', 'shop_name'=>'Fixture '.$storeId, 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
            DB::table('marketplace_inventory_policies')->insert(['store_id'=>$storeId, 'enabled'=>true, 'orders_created_after'=>1000, 'reconciled_at'=>now()]);
            $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$storeId, 'external_product_id'=>'101', 'external_variant_id'=>'201', 'observed_data'=>'{}', 'fetched_at'=>now()]);
            DB::table('marketplace_variant_mappings')->insert(['store_id'=>$storeId, 'listing_variant_id'=>$listing, 'product_variant_id'=>1]);
        }
    }
    DB::beginTransaction();
    DB::table('product_variants')->where('id', 1)->lockForUpdate()->first();
    for ($i=0; $i<2; $i++) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $schema, $mode, (string) ($duplicate ? 1 : $i+1)], base_path());
        $process->setTimeout(30);
        $process->start();
        $processes[] = $process;
    }
    $deadline = microtime(true)+10;
    while (count(array_filter($processes, fn ($process)=>str_contains($process->getOutput(), 'ready'))) !== 2) {
        if (microtime(true)>$deadline) throw new RuntimeException('Workers not ready');
        usleep(20000);
    }
    DB::commit();
    $success = 0;
    foreach ($processes as $process) {
        $process->wait();
        if (!$process->isSuccessful()) throw new RuntimeException('Worker failed');
        if (str_contains($process->getOutput(), 'allocated:')) $success++;
    }
    if ($success !== ($duplicate ? 2 : 1)
        || (int) DB::table('product_variants')->value('stock') !== 3
        || DB::table('stock_allocations')->count() !== 1
        || DB::table('stock_allocation_events')->count() !== 1) throw new RuntimeException('Inventory race invariant failed');
    if ($orderMode && (DB::table('marketplace_orders')->count() !== ($duplicate ? 1 : 2)
        || DB::table('marketplace_order_inventory')->where('status', 'reserved')->count() !== 1
        || DB::table('marketplace_order_inventory')->where('error_code', 'insufficient_stock')->count() !== ($duplicate ? 0 : 1))) {
        throw new RuntimeException('Order persistence invariant failed');
    }
    echo "PASS: $mode; two processes, one allocation, stock 3, one event.\n";
} catch (Throwable $error) {
    $failed = true;
    // Never output connection strings, credentials or raw database exceptions.
    fwrite(STDERR, 'FAIL: '.get_class($error)."\n");
} finally {
    foreach ($processes as $process) if ($process->isRunning()) $process->stop();
    if (DB::transactionLevel() > 0) DB::rollBack();
    if ($created) {
        config(['database.connections.mysql.database'=>$connection['database']]);
        DB::purge('mysql');
        DB::statement("DROP DATABASE `$schema`");
    }
}
exit($failed ? 1 : 0);
