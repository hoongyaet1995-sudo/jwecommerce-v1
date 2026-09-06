<?php

// Opt-in local integration check. Never run against the application schema.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Process\Process;
use App\Services\Marketplace\OAuthStateService;
use App\Services\Marketplace\ShopeeAuthorizationService;
use Illuminate\Support\Facades\Http;

if (!app()->environment('local') || !in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "Local MySQL only.\n"); exit(2);
}
$worker = ($argv[1] ?? '') === 'worker';
$kind = $worker ? ($argv[4] ?? 'state') : (($argv[1] ?? '') === 'store' ? 'store' : 'state');
$schema = $worker ? ($argv[2] ?? '') : 'jw_oauth_race_'.bin2hex(random_bytes(6));
if (!preg_match('/^jw_oauth_race_[a-f0-9]{12}$/D', $schema)) exit(2);
$sourceDatabase = config('database.connections.mysql.database');
if ($schema === $sourceDatabase) exit(2);
config(['database.connections.mysql.url'=>null, 'database.default'=>'mysql']);
$created = false;
$processes = [];
try {
    if (!$worker) {
        // Schema only, never copy application rows into the test database.
        $storeDdl = (array) DB::connection('mysql')->selectOne('SHOW CREATE TABLE marketplace_stores');
        DB::connection('mysql')->statement("CREATE DATABASE `$schema`");
        $created = true;
    }
    config(['database.connections.mysql.database'=>$schema]);
    DB::purge('mysql');
    if (DB::selectOne('SELECT DATABASE() AS db')->db !== $schema) throw new RuntimeException('Schema isolation failed');
    // No provider requests or real application credentials are used by this state-only check.
    config(['app.url'=>'https://race.invalid', 'marketplace.shopee'=>['enabled'=>true, 'environment'=>'sandbox',
        'partner_id'=>'123', 'partner_key'=>'local-fake-only', 'callback_url'=>'https://race.invalid/admin/marketplace/shopee/callback']]);
    Http::preventStrayRequests();
    Http::fake([
        '*/auth/token/get*'=>Http::response(['access_token'=>'fake-access', 'refresh_token'=>'fake-refresh', 'expire_in'=>14400]),
        '*/shop/get_shop_info*'=>Http::response(['shop_name'=>'Concurrent fixture', 'region'=>'MY', 'shop_id'=>456]),
    ]);
    if ($worker) {
        echo "ready\n"; flush();
        if ($kind === 'store') {
            try {
                app(ShopeeAuthorizationService::class)->complete($argv[3], 42, 'fake-code', '456');
                $won = true;
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $won = false;
            } catch (RuntimeException $exception) {
                if ($exception->getMessage() !== 'This store already exists. Use Reconnect on that store.') throw $exception;
                $won = false;
            }
        } else {
            $won = app(OAuthStateService::class)->consume($argv[3], 42, 'shopee', 'https://race.invalid');
        }
        echo $won ? "won\n" : "rejected\n";
        exit(0);
    }
    DB::statement($storeDdl['Create Table']);
    (require __DIR__.'/../../database/migrations/2026_09_06_000003_create_marketplace_oauth_states.php')->up();
    (require __DIR__.'/../../database/migrations/2026_09_06_000006_bind_oauth_state_configuration.php')->up();
    (require __DIR__.'/../../database/migrations/2026_09_06_000004_create_marketplace_store_tokens.php')->up();
    (require __DIR__.'/../../database/migrations/2026_09_06_000009_bind_marketplace_tokens_to_application.php')->up();
    $state = app(OAuthStateService::class)->issue(42, 'shopee', 'https://race.invalid', null);
    $secondState = $kind === 'store' ? app(OAuthStateService::class)->issue(42, 'shopee', 'https://race.invalid', null) : $state;
    DB::beginTransaction();
    DB::table('marketplace_oauth_states')->lockForUpdate()->get();
    for ($i = 0; $i < 2; $i++) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $schema, $i === 0 ? $state : $secondState, $kind], base_path());
        $process->setTimeout(20);
        $process->start();
        $processes[] = $process;
    }
    $deadline = microtime(true) + 10;
    do {
        $ready = count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), 'ready')));
        if ($ready === 2) break;
        if (microtime(true) > $deadline) throw new RuntimeException('Workers did not reach concurrent barrier');
        usleep(20000);
    } while (true);
    DB::commit();
    foreach ($processes as $process) if ($process->wait() !== 0) throw new RuntimeException('Worker failed');
    $outputs = array_map(fn ($p) => trim(str_replace("ready\n", '', $p->getOutput())), $processes);
    sort($outputs);
    if ($outputs !== ['rejected', 'won'] || DB::table('marketplace_oauth_states')->whereNotNull('consumed_at')->count() !== ($kind === 'store' ? 2 : 1)) {
        throw new RuntimeException('Single-use invariant failed');
    }
    if ($kind === 'store') {
        if (DB::table('marketplace_stores')->count() !== 1 || DB::table('marketplace_store_tokens')->count() !== 1
            || DB::table('marketplace_store_tokens')->value('access_token') === 'fake-access') {
            throw new RuntimeException('Store/token uniqueness or encryption failed');
        }
        echo "PASS: concurrent authorizations; one store and one encrypted token record; duplicate rejected. Provider HTTP simulated.\n";
    } else {
        echo "PASS: two concurrent MySQL workers; one accepted, one rejected; one consumed state.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: '.$error::class." (details suppressed)\n");
    $failed = true;
} finally {
    if (!$worker && $created) {
        foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        if (DB::transactionLevel()) DB::rollBack();
        // Drop only the random schema created by this invocation, never a configured schema.
        DB::statement("DROP DATABASE `$schema`");
        echo "Temporary isolated test database removed; application database unchanged.\n";
    }
}
exit(isset($failed) ? 1 : 0);
