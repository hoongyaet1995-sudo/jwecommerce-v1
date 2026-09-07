<?php

// Real concurrent MySQL scheduling; synthetic store, disposable local schema, no provider calls.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Marketplace\MarketplaceAutomaticOrders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

if (!app()->environment('local') || !in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true)) exit(2);
$worker = ($argv[1] ?? '') === 'worker';
$schema = $worker ? ($argv[2] ?? '') : 'jw_auto_race_'.bin2hex(random_bytes(6));
$connection = config('database.connections.mysql');
if (!preg_match('/^jw_auto_race_[a-f0-9]{12}$/D', $schema) || $schema === $connection['database']) exit(2);
config(['database.default'=>'mysql', 'database.connections.mysql.url'=>null]);
$created = false;
$failed = false;
$processes = [];
try {
    if (!$worker) {
        DB::statement("CREATE DATABASE `$schema`");
        $created = true;
        $migration = new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), [
            'APP_ENV'=>'testing', 'DB_CONNECTION'=>'mysql', 'DB_URL'=>'', 'DB_HOST'=>$connection['host'],
            'DB_PORT'=>(string)$connection['port'], 'DB_DATABASE'=>$schema, 'DB_USERNAME'=>$connection['username'],
            'DB_PASSWORD'=>$connection['password'], 'CACHE_STORE'=>'array', 'SESSION_DRIVER'=>'array',
        ]);
        $migration->setTimeout(120);
        $migration->run();
        if (!$migration->isSuccessful()) throw new RuntimeException('Isolated migrations failed');
    }
    config(['database.connections.mysql.database'=>$schema]);
    DB::purge('mysql');
    if (DB::selectOne('SELECT DATABASE() AS db')->db !== $schema) throw new RuntimeException('Isolation failed');
    Http::preventStrayRequests();
    config([
        'marketplace.automatic_orders_enabled'=>true, 'marketplace.read_operations_enabled'=>true,
        'marketplace.operations_enabled'=>false, 'marketplace.shopee.enabled'=>true,
        'marketplace.shopee.environment'=>'sandbox', 'marketplace.shopee.partner_id'=>'123',
        'marketplace.shopee.partner_key'=>'fixture', 'marketplace.shopee.callback_url'=>'https://example.test/callback',
        'queue.connections.marketplace'=>['driver'=>'database', 'connection'=>'mysql', 'table'=>'jobs',
            'queue'=>'marketplace', 'retry_after'=>1500, 'after_commit'=>true],
    ]);
    if ($worker) {
        echo "ready\n";
        flush();
        echo 'enqueued:'.app(MarketplaceAutomaticOrders::class)->enqueueDue()."\n";
        exit(0);
    }
    DB::table('marketplace_stores')->insert(['id'=>1, 'platform'=>'shopee', 'shop_name'=>'Concurrent scheduling fixture',
        'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
    DB::beginTransaction();
    DB::table('marketplace_stores')->where('id', 1)->lockForUpdate()->first();
    for ($i=0; $i<2; $i++) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $schema], base_path());
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
    $winners = 0;
    $skipped = 0;
    foreach ($processes as $process) {
        $process->wait();
        if (!$process->isSuccessful()) throw new RuntimeException('Worker failed');
        $winners += str_contains($process->getOutput(), 'enqueued:1') ? 1 : 0;
        $skipped += str_contains($process->getOutput(), 'enqueued:0') ? 1 : 0;
    }
    if ($winners !== 1 || $skipped !== 1 || DB::table('marketplace_order_runs')->count() !== 1
        || DB::table('jobs')->count() !== 1) throw new RuntimeException('Scheduling race invariant failed');
    $run = DB::table('marketplace_order_runs')->sole();
    $job = DB::table('jobs')->sole();
    $payload = json_decode($job->payload, true, 512, JSON_THROW_ON_ERROR);
    if ($run->origin !== 'automatic' || $run->status !== 'queued' || (int)$run->store_id !== 1
        || (int)$run->requested_time_to-(int)$run->requested_time_from !== 30*86400
        || $job->queue !== 'marketplace' || (int)$job->attempts !== 0
        || ($payload['displayName'] ?? '') !== App\Jobs\PullShopeeOrders::class
        || app(MarketplaceAutomaticOrders::class)->enqueueDue() !== 0
        || DB::table('jobs')->count() !== 1) throw new RuntimeException('Durable queue invariant failed');
    echo "PASS: two scheduler processes; one automatic 30-day run, one unconsumed database job, repeat skipped.\n";
} catch (Throwable $error) {
    $failed = true;
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
