<?php

namespace App\Jobs;

use App\Services\Marketing\MarketingResearchProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMarketingResearch implements ShouldQueue
{
    use Queueable;

    public $tries = 1;
    public $timeout = 600;

    public function __construct(public string $runId)
    {
        $this->onConnection('marketing-research')->onQueue('marketing-research');
    }

    public function handle(MarketingResearchProcessor $processor): void { $processor->process($this->runId); }
    public function failed(?\Throwable $error): void { app(MarketingResearchProcessor::class)->unknown($this->runId); }
}
