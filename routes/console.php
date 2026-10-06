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

Artisan::command('inventory:drain-current-stock-sync {--limit=100}', function () {
    $result = app(\App\Services\Inventory\CurrentStockSyncIntentDrain::class)
        ->run((int) $this->option('limit'));
    $this->line(json_encode($result, JSON_THROW_ON_ERROR));
})->purpose('Queue latest Current Stock order changes for marketplace sync');

Schedule::command('inventory:drain-current-stock-sync')->everyMinute()->withoutOverlapping()
    ->when(fn () => config('inventory.current_stock_only') === true);

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
})->purpose('Run Marketplace Hub installation reports; order events drain every minute separately');

Artisan::command('marketplace:hub-process-events', function () {
    return $this->call('queue:work', [
        'connection' => 'marketplace', '--queue' => 'marketplace-hub-orders',
        '--stop-when-empty' => true, '--max-jobs' => 100, '--tries' => 3, '--timeout' => 120,
    ]);
})->purpose('Drain only Hub-delivered marketplace order events');

Artisan::command('marketplace:store-stock-process', function () {
    $lock = \Illuminate\Support\Facades\Cache::lock('marketplace:store-stock-process', 180);
    if (! $lock->get()) {
        return 0;
    }
    try {
        return $this->call('queue:work', [
            'connection' => 'marketplace', '--queue' => 'marketplace-stock',
            '--stop-when-empty' => true, '--max-jobs' => 20, '--max-time' => 45,
            '--tries' => 1, '--timeout' => 120, '--no-interaction' => true,
        ]);
    } finally {
        $lock->release();
    }
})->purpose('Drain managed marketplace stock updates separately from catalog and order workers');

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

Schedule::command('marketplace:store-stock-process')->everyMinute()->withoutOverlapping(3)
    ->when(fn () => config('marketplace.stock_sync_operations_enabled') === true);

Artisan::command('marketplace:store-stock-readback-sweep', function () {
    $this->line((string) app(\App\Services\Inventory\StoreManagement\UncertainStoreStockReconciler::class)->sweep());
})->purpose('Queue read-only reconciliation for uncertain marketplace stock updates');

Schedule::command('marketplace:store-stock-readback-sweep')->everyMinute()->withoutOverlapping()
    ->when(fn () => config('marketplace.stock_sync_operations_enabled') === true);

Artisan::command('marketplace:discover-facilities', function () {
    $this->line(json_encode(app(\App\Services\Inventory\StoreManagement\FacilityDiscoverySweep::class)->run(), JSON_THROW_ON_ERROR));
})->purpose('Verify one connected marketplace store warehouse topology without changing inventory');

Schedule::command('marketplace:discover-facilities')->everyFiveMinutes()->withoutOverlapping()
    ->when(fn () => config('marketplace.multiwarehouse_discovery_enabled') === true);

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

\Illuminate\Support\Facades\Artisan::command('social:heartbeat', function () {
 $lock=\Illuminate\Support\Facades\Cache::lock('social-heartbeat',30);
 if(!$lock->get()){ $this->warn('Heartbeat already running.'); return 1; }
 try { $r=app(\App\Services\Social\SocialHubInstallation::class)->heartbeat(); $this->line($r['reason']); return $r['ok']?0:1; } finally { $lock->release(); }
});

Schedule::command('social:heartbeat')->everyFiveMinutes()->withoutOverlapping(2)
    ->when(fn () => \Illuminate\Support\Facades\Schema::hasTable('social_hub_settings')
        && \Illuminate\Support\Facades\DB::table('social_hub_settings')->where('id', 1)->where('enabled', true)->exists());


Artisan::command('support:prune-uploads', function () {
    $count=0;
    \App\Models\SupportAttachment::whereNull('message_id')->where('created_at','<',now()->subDay())->chunkById(100,function($uploads)use(&$count){
        foreach($uploads as $a) \Illuminate\Support\Facades\DB::transaction(function()use($a,&$count){
            $current=\App\Models\SupportAttachment::whereKey($a->id)->lockForUpdate()->first();
            if(!$current||$current->message_id)return;
            if($current->path)\Illuminate\Support\Facades\Storage::disk($current->disk)->delete($current->path);
            $current->delete();$count++;
        });
    });
    $this->info('Expired uploads removed: '.$count);
})->purpose('Remove abandoned support image uploads after 24 hours');
Schedule::command('support:prune-uploads')->hourly()->withoutOverlapping();
