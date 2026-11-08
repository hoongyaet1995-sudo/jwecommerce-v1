<?php

namespace App\Jobs;

use App\Services\Marketing\MarketingProductionProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMarketingProduction implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $timeout = 900;

    public function __construct(public string $productionId)
    {
        $this->onConnection('marketing-production')->onQueue('marketing-production');
    }

    public function handle(MarketingProductionProcessor $processor): void
    {
        $processor->process($this->productionId);
    }

    public function failed(?\Throwable $error): void
    {
        app(MarketingProductionProcessor::class)->interrupted($this->productionId);
    }
}
