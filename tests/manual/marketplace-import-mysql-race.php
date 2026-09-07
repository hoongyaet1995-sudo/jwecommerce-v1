<?php

// Real competing processes, synthetic fixtures, disposable local MySQL only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use App\Services\Marketplace\MarketplaceImportService;

if (!app()->environment('local') || !in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true)) exit(2);
$worker = ($argv[1] ?? '') === 'worker';
$newParent = ($worker ? ($argv[3] ?? '') : ($argv[1] ?? '')) === 'new-parent';
$schema = $worker ? ($argv[2] ?? '') : 'jw_import_race_'.bin2hex(random_bytes(6));
if (!preg_match('/^jw_import_race_[a-f0-9]{12}$/D', $schema) || $schema === config('database.connections.mysql.database')) exit(2);
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
        $service = app(MarketplaceImportService::class);
        $id = $newParent ? $service->importVariantAsProduct(1, 1) : $service->importVariant(1, 1, 1);
        echo "variant:$id\n";
        exit(0);
    }
    DB::table('users')->insert(['id'=>1, 'name'=>'Fixture', 'email'=>'import-race@example.invalid', 'password'=>'not-a-login']);
    if (!$newParent) DB::table('products')->insert(['id'=>1, 'title'=>'Fixture parent', 'handle'=>'fixture-parent', 'base_price'=>1000, 'status'=>'draft']);
    DB::table('marketplace_stores')->insert(['id'=>1, 'platform'=>'shopee', 'shop_name'=>'Fixture store', 'region'=>'MY', 'environment'=>'sandbox']);
    DB::table('marketplace_listing_variants')->insert(['id'=>1, 'store_id'=>1, 'external_product_id'=>'fixture', 'external_variant_id'=>'one',
        'observed_data'=>json_encode(['productTitle'=>'Source product', 'title'=>'Selected', 'sku'=>'IMPORT-RACE', 'currency'=>'MYR', 'price'=>'12.34', 'stock'=>500]), 'fetched_at'=>now()]);
    DB::beginTransaction();
    DB::table('marketplace_stores')->where('id', 1)->lockForUpdate()->first();
    for ($i=0; $i<2; $i++) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $schema, $newParent ? 'new-parent' : 'existing-parent'], base_path());
        $process->setTimeout(30);
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
        if (!$process->isSuccessful() || !preg_match('/variant:(\d+)/', $process->getOutput(), $match)) {
            if (preg_match('/FAIL: ([A-Za-z\\\\]+) /', $process->getErrorOutput(), $failure)) echo 'Worker exception: '.$failure[1]."\n";
            throw new RuntimeException('Worker failed');
        }
        $ids[] = (int) $match[1];
    }
    if ($ids[0] !== $ids[1]) throw new RuntimeException('Duplicate identity');
    foreach (['products', 'product_variants', 'marketplace_variant_imports', 'marketplace_variant_mappings', 'marketplace_mapping_events'] as $table) {
        if (DB::table($table)->count() !== 1) throw new RuntimeException('Duplicate record');
    }
    if ((int) DB::table('product_variants')->sum('stock') !== 0) throw new RuntimeException('Stock copied');
    if ($newParent && (DB::table('products')->value('status') !== 'draft' || (int) DB::table('products')->value('base_price') !== 1234)) {
        throw new RuntimeException('Incorrect imported draft');
    }
    echo 'Mode: '.($newParent ? 'new-parent' : 'existing-parent')."\n";
    echo "PASS: two concurrent imports return the same variant; one variant, identity, mapping and audit event; zero stock.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.get_class($e)." (private connection details suppressed)\n");
    if (get_class($e) === RuntimeException::class) fwrite(STDERR, $e->getMessage()."\n");
    $failed = true;
} finally {
    if (!$worker && $created) {
        foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        if (DB::transactionLevel()) DB::rollBack();
        DB::statement("DROP DATABASE `$schema`");
        echo "Removed only this run's disposable schema; application data unchanged.\n";
    }
}
exit($failed ? 1 : 0);
