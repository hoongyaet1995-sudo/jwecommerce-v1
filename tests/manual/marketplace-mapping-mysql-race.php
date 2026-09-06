<?php

// Local-only service concurrency acceptance. All fixtures live in a disposable schema.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Services\Marketplace\MarketplaceFoundationService;

if (!app()->environment('local') || !in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true)) exit(2);
$worker = ($argv[1] ?? '') === 'worker';
$schema = $worker ? ($argv[2] ?? '') : 'jw_mapping_race_'.bin2hex(random_bytes(6));
if (!preg_match('/^jw_mapping_race_[a-f0-9]{12}$/D', $schema) || $schema === config('database.connections.mysql.database')) exit(2);
config(['database.default'=>'mysql', 'database.connections.mysql.url'=>null]);
$created = false;
$processes = [];
$failed = false;
try {
    if (!$worker) {
        // Copy structure only, including production constraints; never copy application rows.
        $ddl = [];
        foreach (['users', 'categories', 'products', 'product_variants', 'marketplace_stores', 'marketplace_listing_variants', 'marketplace_variant_mappings', 'marketplace_mapping_events'] as $table) {
            $ddl[] = ((array) DB::selectOne('SHOW CREATE TABLE '.$table))['Create Table'];
        }
        DB::statement("CREATE DATABASE `$schema`");
        $created = true;
    }
    config(['database.connections.mysql.database'=>$schema]);
    DB::purge('mysql');
    if (DB::selectOne('SELECT DATABASE() AS db')->db !== $schema) throw new RuntimeException('Isolation failed');
    if ($worker) {
        echo "ready\n"; flush();
        try {
            app(MarketplaceFoundationService::class)->map((int) $argv[3], 1, 1, 1);
            echo "mapped\n";
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() !== 409) throw $e;
            echo "conflict409\n";
        }
        exit(0);
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ($ddl as $statement) DB::statement($statement);
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    DB::table('users')->insert(['id'=>1, 'name'=>'Local fixture', 'email'=>'race@example.invalid', 'password'=>'not-a-login']);
    DB::table('products')->insert(['id'=>1, 'title'=>'Local fixture', 'handle'=>'local-race', 'base_price'=>1000]);
    DB::table('product_variants')->insert(['id'=>1, 'product_id'=>1, 'sku'=>'LOCAL-RACE', 'title'=>'One', 'price'=>1000, 'stock'=>7, 'enabled'=>true]);
    foreach ([1, 2] as $id) DB::table('marketplace_stores')->insert(['id'=>$id, 'platform'=>'shopee', 'shop_name'=>'Local fixture '.$id, 'region'=>'MY', 'environment'=>'sandbox']);
    foreach ([1, 2, 3] as $id) DB::table('marketplace_listing_variants')->insert(['id'=>$id, 'store_id'=>$id === 3 ? 2 : 1, 'external_product_id'=>'fixture', 'external_variant_id'=>(string) $id, 'observed_data'=>'{}', 'fetched_at'=>now()]);
    DB::beginTransaction();
    DB::table('marketplace_stores')->where('id', 1)->lockForUpdate()->first();
    foreach ([1, 2] as $listing) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $schema, (string) $listing], base_path());
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
    $outcomes = [];
    foreach ($processes as $process) {
        $process->wait();
        if (!$process->isSuccessful()) throw new RuntimeException('Worker failed');
        $outcomes[] = trim(str_replace('ready', '', $process->getOutput()));
    }
    sort($outcomes);
    if ($outcomes !== ['conflict409', 'mapped']) throw new RuntimeException('Unexpected outcomes');
    if (DB::table('marketplace_variant_mappings')->count() !== 1 || DB::table('marketplace_mapping_events')->count() !== 1) throw new RuntimeException('Duplicate mapping or audit event');
    app(MarketplaceFoundationService::class)->map(3, 1, 1, 1);
    if (DB::table('marketplace_variant_mappings')->count() !== 2 || DB::table('marketplace_mapping_events')->count() !== 2 || (int) DB::table('product_variants')->where('id', 1)->value('stock') !== 7) throw new RuntimeException('Cross-store or stock invariant failed');
    echo "PASS: concurrent same-store mapping yields one success and one 409; cross-store reuse succeeds; stock remains 7; audit counts exact.\n";
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
