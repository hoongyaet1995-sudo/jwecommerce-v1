<?php

use App\Jobs\SweepEasyParcelReconciliation;
use App\Jobs\SyncEasyParcelTracking;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
