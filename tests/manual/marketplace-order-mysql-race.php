<?php

// Explicit local acceptance utility: isolated schema, no network/provider calls.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\Marketplace\MarketplaceOrderSnapshotService;
use Symfony\Component\Process\Process;

if (!app()->environment('local') || !in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true)) exit(2);
$worker = ($argv[1] ?? '') === 'worker';
$schema = $worker ? ($argv[2] ?? '') : 'jw_order_race_'.bin2hex(random_bytes(6));
if (!preg_match('/^jw_order_race_[a-f0-9]{12}$/D', $schema) || $schema === config('database.connections.mysql.database')) exit(2);
config(['database.default'=>'mysql', 'database.connections.mysql.url'=>null]);
$created = false;
$processes = [];
$failed = false;
try {
    if (!$worker) {
        $ddl = [];
        foreach (['marketplace_stores', 'marketplace_orders'] as $table) {
            $row = (array) DB::selectOne('SHOW CREATE TABLE '.$table);
            $ddl[] = $row['Create Table'];
        }
        DB::statement("CREATE DATABASE `$schema`");
        $created = true;
    }
    config(['database.connections.mysql.database'=>$schema]);
    DB::purge('mysql');
    if (DB::selectOne('SELECT DATABASE() AS db')->db !== $schema) throw new RuntimeException('Isolation failed');
    $snapshot = ['order_sn'=>'CONCURRENT-FIXTURE', 'order_status'=>'READY_TO_SHIP', 'update_time'=>200,
        'currency'=>'MYR', 'total_amount'=>1407.31, 'recipient_address'=>['name'=>'LOCAL PRIVATE FIXTURE'],
        'item_list'=>[['item_id'=>846055463, 'model_id'=>12983425, 'model_quantity_purchased'=>1]]];
    if ($worker) {
        echo "ready\n"; flush();
        $id = app(MarketplaceOrderSnapshotService::class)->saveShopee(1, $snapshot);
        echo 'saved:'.$id."\n";
        exit(0);
    }
    foreach ($ddl as $statement) DB::statement($statement);
    DB::table('marketplace_stores')->insert(['id'=>1, 'platform'=>'shopee', 'shop_name'=>'Local race fixture',
        'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected', 'created_at'=>now(), 'updated_at'=>now()]);
    DB::beginTransaction();
    DB::table('marketplace_stores')->where('id', 1)->lockForUpdate()->first();
    for ($i=0; $i<2; $i++) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $schema], base_path());
        $process->setTimeout(20);
        $process->start();
        $processes[] = $process;
    }
    $deadline = microtime(true)+10;
    while (count(array_filter($processes, fn ($p)=>str_contains($p->getOutput(), 'ready'))) !== 2) {
        if (microtime(true)>$deadline) throw new RuntimeException('Workers not ready');
        usleep(20000);
    }
    DB::commit();
    $ids = [];
    foreach ($processes as $process) {
        $process->wait();
        if (!$process->isSuccessful() || !preg_match('/saved:(\d+)/', $process->getOutput(), $match)) throw new RuntimeException('Worker failed');
        $ids[] = $match[1];
    }
    if ($ids[0] !== $ids[1] || DB::table('marketplace_orders')->count() !== 1) throw new RuntimeException('Duplicate order');
    $service = app(MarketplaceOrderSnapshotService::class);
    $service->saveShopee(1, array_replace($snapshot, ['update_time'=>300, 'order_status'=>'SHIPPED']));
    $service->saveShopee(1, $snapshot);
    $row = DB::table('marketplace_orders')->sole();
    if ($row->platform_status !== 'SHIPPED' || $row->platform_updated_at != 300) throw new RuntimeException('State regressed');
    if (str_contains($row->encrypted_payload, 'LOCAL PRIVATE FIXTURE')) throw new RuntimeException('Payload not encrypted');
    echo "PASS: concurrent MySQL snapshots share one order ID; older event cannot regress state; payload encrypted.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.get_class($e)." (details suppressed)\n");
    $failed = true;
} finally {
    if (!$worker && $created) {
        foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        if (DB::transactionLevel()) DB::rollBack();
        DB::statement("DROP DATABASE `$schema`");
        echo "Removed this run's disposable database; application records unchanged.\n";
    }
}
exit($failed ? 1 : 0);
