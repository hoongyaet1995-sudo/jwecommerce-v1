<?php

// Run selected feature tests against disposable local MySQL, never the configured application DB.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$connection = config('database.connections.mysql');
if (!app()->environment('local') || !in_array($connection['host'], ['localhost', '127.0.0.1'], true)) exit(2);
$schema = 'jw_mapping_acceptance_'.bin2hex(random_bytes(6));
if (!preg_match('/^jw_mapping_acceptance_[a-f0-9]{12}$/D', $schema) || $schema === $connection['database']) exit(2);
$created = false;
$result = 1;
try {
    DB::connection('mysql')->statement("CREATE DATABASE `$schema`");
    $created = true;
    $process = new Process([PHP_BINARY, 'artisan', 'test', '--filter',
        'MarketplaceCatalogSummaryTest|MarketplaceImportTest|test_order_summary_counts_all_matching_orders_without_inventing_fulfillment|test_database_rejects_a_second_mapping_for_the_same_store_and_jw_variant|test_mapping_enforces_per_store_uniqueness_and_allows_cross_store_reuse_without_touching_stock|test_duplicate_and_older_order_snapshots_do_not_duplicate_or_regress'],
        base_path(), ['APP_ENV'=>'testing', 'DB_CONNECTION'=>'mysql', 'DB_URL'=>'', 'DB_HOST'=>$connection['host'],
            'DB_PORT'=>(string) $connection['port'], 'DB_DATABASE'=>$schema, 'DB_USERNAME'=>$connection['username'],
            'DB_PASSWORD'=>$connection['password'], 'CACHE_STORE'=>'array', 'SESSION_DRIVER'=>'array']);
    $process->setTimeout(120);
    $process->run();
    // Tests contain only local synthetic fixtures. Never print connection settings.
    echo $process->getOutput();
    $result = $process->isSuccessful() ? 0 : 1;
    $tables = DB::connection('mysql')->selectOne('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = ? AND table_name IN (?, ?)',
        [$schema, 'marketplace_variant_mappings', 'marketplace_orders'])->n;
    if ((int) $tables !== 2) throw new RuntimeException('Tests did not migrate the isolated MySQL schema');
    echo "Verified marketplace mapping and order tables exist in the isolated MySQL schema.\n";
    if (!$process->isSuccessful()) echo "MySQL feature acceptance failed; inspect selected test results.\n";
} catch (Throwable $e) {
    $result = 1;
    echo 'FAIL: '.get_class($e)." (connection details suppressed)\n";
} finally {
    if ($created) {
        DB::connection('mysql')->statement("DROP DATABASE `$schema`");
        echo "Disposable MySQL acceptance schema removed; application schema unchanged.\n";
    }
}
exit($result);
