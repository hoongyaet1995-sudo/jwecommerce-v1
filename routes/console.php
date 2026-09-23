<?php

use App\Jobs\SweepEasyParcelReconciliation;
use App\Jobs\SyncEasyParcelTracking;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('marketplace:hub-heartbeat', function () {
    $url = rtrim((string) config('marketplace.hub.url'), '/');
    $clientId = (string) config('marketplace.hub.client_id');
    $secret = (string) config('marketplace.hub.client_secret');
    $domain = parse_url((string) config('app.url'), PHP_URL_HOST);
    if ($clientId === '' || $secret === '' || ! is_string($domain) || $domain === '' || ! str_starts_with($url, 'https://')) {
        $this->error('Marketplace Hub installation is not configured.');
        return 1;
    }

    $body = json_encode(['domain' => strtolower($domain)], JSON_THROW_ON_ERROR);
    $timestamp = (string) now()->timestamp;
    $nonce = Str::random(32);
    $signature = hash_hmac('sha256', "{$timestamp}.{$nonce}.{$body}", $secret);
    $response = Http::timeout(10)->withHeaders([
        'X-Hub-Client' => $clientId,
        'X-Hub-Timestamp' => $timestamp,
        'X-Hub-Nonce' => $nonce,
        'X-Hub-Signature' => $signature,
    ])->withBody($body, 'application/json')->post($url.'/api/installations/heartbeat');

    if (! $response->successful()) {
        $this->error('Marketplace Hub heartbeat was not accepted (HTTP '.$response->status().').');
        return 1;
    }
    $this->info('Marketplace Hub heartbeat accepted.');
    return 0;
})->purpose('Verify this JW Ecom installation with Marketplace Hub');

Schedule::command('marketplace:hub-heartbeat')->everyFiveMinutes()->withoutOverlapping()
    ->when(fn () => (bool) config('marketplace.hub.client_id') && (bool) config('marketplace.hub.client_secret'));

Artisan::command('marketplace:hub-report-stores', function () {
    $this->line(json_encode(app(\App\Services\Marketplace\MarketplaceHubStoreReporter::class)->run(), JSON_THROW_ON_ERROR));
})->purpose('Report connected marketplace store metadata to JW Marketplace Hub');

Schedule::command('marketplace:hub-report-stores')->everyFiveMinutes()->withoutOverlapping()
    ->when(fn () => config('marketplace.hub.store_reporting_enabled') === true);

Artisan::command('marketplace:hub-report-activity', function () {
    $this->line(json_encode(app(\App\Services\Marketplace\MarketplaceHubActivityReporter::class)->run(), JSON_THROW_ON_ERROR));
})->purpose('Report safe marketplace task status to JW Marketplace Hub');

Artisan::command('marketplace:hub-sync', function () {
    $this->call('marketplace:hub-heartbeat');
    $this->call('marketplace:hub-report-stores');
    $this->call('marketplace:hub-report-activity');
})->purpose('Run the enabled Marketplace Hub installation reports in dependency order');

Schedule::command('marketplace:hub-report-activity')->everyFiveMinutes()->withoutOverlapping()
    ->when(fn () => config('marketplace.hub.activity_reporting_enabled') === true);

Schedule::job(new SyncEasyParcelTracking, 'easyparcel')
    ->everyFifteenMinutes();

Schedule::job(new SweepEasyParcelReconciliation, 'easyparcel')
    ->everyMinute();

Artisan::command('marketplace:sync-managed-stock', function () {
    $this->line(json_encode(app(\App\Services\Marketplace\ManagedInventoryStockSweep::class)->run(), JSON_THROW_ON_ERROR));
})->purpose('Synchronize explicitly activated WMS balances to designated test stores');

Schedule::command('marketplace:sync-managed-stock')->everyMinute()->withoutOverlapping();

Artisan::command('marketplace:prune-oauth-seeds', function () {
    $count=\App\Models\MarketplaceOAuthBatch::whereNotNull('seed_refresh_token')
        ->where('seed_refresh_expires_at','<=',now())
        ->update(['seed_access_token'=>null,'seed_refresh_token'=>null,'updated_at'=>now()]);
    $this->line("Cleared {$count} expired OAuth recovery seed(s).");
})->purpose('Clear expired encrypted marketplace OAuth recovery seeds');

Schedule::command('marketplace:prune-oauth-seeds')->hourly()->withoutOverlapping();

// Hostinger runs Laravel's scheduler each minute; drain marketplace jobs without requiring a resident daemon.
Schedule::command('queue:work --queue=marketplace --stop-when-empty --tries=3 --timeout=120')
    ->everyMinute()->withoutOverlapping(10);
