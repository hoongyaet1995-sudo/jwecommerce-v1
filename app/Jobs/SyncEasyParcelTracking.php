<?php

namespace App\Jobs;

use App\Services\Shipping\EasyParcelTrackingService;
use App\Services\Shipping\ShippingProviderConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncEasyParcelTracking implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('easyparcel');
    }

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 900;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function uniqueId(): string
    {
        return 'easyparcel-tracking-'.ShippingProviderConfigurationService::operationalEnvironment();
    }

    public function handle(EasyParcelTrackingService $tracking): void
    {
        $tracking->syncNextBatch();
    }
}
