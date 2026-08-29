<?php

namespace App\Jobs;

use App\Models\ShippingWebhookReceipt;
use App\Services\Shipping\EasyParcelReconciliationTaskProcessor;
use App\Services\Shipping\EasyParcelReconciliationTaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ReconcileEasyParcelWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $receiptId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function uniqueId(): string
    {
        return 'easyparcel-webhook-receipt-'.$this->receiptId;
    }

    public function handle(
        EasyParcelReconciliationTaskService $tasks,
        EasyParcelReconciliationTaskProcessor $processor,
    ): void {
        $receipt = ShippingWebhookReceipt::query()->findOrFail($this->receiptId);
        $processor->process($tasks->ensureWebhookReceipt($receipt)->id);
    }

    public function failed(Throwable $exception): void
    {
        $receipt = ShippingWebhookReceipt::query()->find($this->receiptId);
        if ($receipt instanceof ShippingWebhookReceipt) {
            $task = app(EasyParcelReconciliationTaskService::class)->ensureWebhookReceipt($receipt);
            app(EasyParcelReconciliationTaskProcessor::class)->releaseCrashedTask($task->id);
        }
    }
}
