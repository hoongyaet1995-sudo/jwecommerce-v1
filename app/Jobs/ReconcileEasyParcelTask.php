<?php

namespace App\Jobs;

use App\Services\Shipping\EasyParcelReconciliationTaskProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ReconcileEasyParcelTask implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $taskId) {}

    public function uniqueId(): string
    {
        return 'easyparcel-reconciliation-task-'.$this->taskId;
    }

    public function handle(EasyParcelReconciliationTaskProcessor $processor): void
    {
        $processor->process($this->taskId);
    }

    public function failed(Throwable $exception): void
    {
        app(EasyParcelReconciliationTaskProcessor::class)->releaseCrashedTask($this->taskId);
    }
}
