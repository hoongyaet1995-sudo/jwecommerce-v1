<?php

namespace App\Jobs;

use App\Models\ShippingWebhookReceipt;
use App\Services\Shipping\EasyParcelWebhookReconciliationService;
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

    public function handle(EasyParcelWebhookReconciliationService $reconciliation): void
    {
        $receipt = ShippingWebhookReceipt::query()->findOrFail($this->receiptId);
        $reconciliation->reconcile($receipt);
    }

    public function failed(Throwable $exception): void
    {
        ShippingWebhookReceipt::query()->whereKey($this->receiptId)->update([
            'processing_state' => 'failed',
            'processed_at' => now(),
            'last_error' => 'retry_exhausted',
        ]);
    }
}
