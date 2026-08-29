<?php

namespace App\Services\Shipping;

use App\Models\EasyParcelReconciliationTask;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingWebhookReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class EasyParcelReconciliationTaskProcessor
{
    private const LEASE_MINUTES = 2;

    private const MAX_ATTEMPTS = 12;

    public function __construct(
        private readonly EasyParcelBookingReconciliationService $bookings,
        private readonly EasyParcelShipmentDetailsService $details,
        private readonly EasyParcelWebhookReconciliationService $webhooks,
    ) {}

    public function process(int $taskId): bool
    {
        $claimed = $this->claim($taskId);
        if (! $claimed instanceof EasyParcelReconciliationTask) {
            return false;
        }

        try {
            $this->perform($claimed);
            $this->resolve($claimed);
        } catch (EasyParcelReconciliationPendingException $exception) {
            $this->retry($claimed, $exception->safeCode);
        } catch (Throwable) {
            $this->retry($claimed, 'provider_unavailable');
        }

        return true;
    }

    public function releaseCrashedTask(int $taskId): void
    {
        DB::transaction(function () use ($taskId): void {
            $task = EasyParcelReconciliationTask::query()->lockForUpdate()->find($taskId);
            if (! $task instanceof EasyParcelReconciliationTask
                || $task->state !== 'processing'
                || ($task->lease_expires_at !== null && $task->lease_expires_at->isFuture())) {
                return;
            }

            $task->forceFill([
                'state' => 'retrying',
                'available_at' => now()->addMinute(),
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error' => 'worker_crashed',
            ])->save();
        });
    }

    private function claim(int $taskId): ?EasyParcelReconciliationTask
    {
        return DB::transaction(function () use ($taskId): ?EasyParcelReconciliationTask {
            $task = EasyParcelReconciliationTask::query()->lockForUpdate()->find($taskId);
            if (! $task instanceof EasyParcelReconciliationTask) {
                return null;
            }

            $now = now();
            $eligible = in_array($task->state, ['queued', 'pending', 'retrying'], true)
                && ($task->available_at === null || ! $task->available_at->isFuture());
            $expiredLease = $task->state === 'processing'
                && ($task->lease_expires_at === null || ! $task->lease_expires_at->isFuture());
            if (! $eligible && ! $expiredLease) {
                return null;
            }

            $task->forceFill([
                'state' => 'processing',
                'lease_token' => (string) Str::uuid(),
                'lease_expires_at' => $now->copy()->addMinutes(self::LEASE_MINUTES),
                'processing_attempt_count' => min(65535, $task->processing_attempt_count + 1),
                'last_error' => null,
            ])->save();

            return $task->fresh();
        });
    }

    private function perform(EasyParcelReconciliationTask $task): void
    {
        match ($task->type) {
            EasyParcelReconciliationTaskService::TYPE_BOOKING_REFERENCE => $this->reconcileBooking($task),
            EasyParcelReconciliationTaskService::TYPE_SHIPMENT_DETAILS => $this->reconcileDetails($task),
            EasyParcelReconciliationTaskService::TYPE_WEBHOOK_RECEIPT => $this->reconcileWebhook($task),
            default => throw new EasyParcelReconciliationPendingException('unsupported_task_type'),
        };
    }

    private function reconcileBooking(EasyParcelReconciliationTask $task): void
    {
        $quote = ShipmentQuote::query()->find($task->shipment_quote_id);
        if (! $quote instanceof ShipmentQuote) {
            throw new EasyParcelReconciliationPendingException('quote_missing');
        }

        $shipment = $this->bookings->reconcile($quote);
        if ($shipment instanceof Shipment && $task->shipment_id === null) {
            EasyParcelReconciliationTask::query()->whereKey($task->id)->update([
                'shipment_id' => $shipment->id,
                'updated_at' => now(),
            ]);
        }
    }

    private function reconcileDetails(EasyParcelReconciliationTask $task): void
    {
        $shipment = Shipment::query()->find($task->shipment_id);
        if (! $shipment instanceof Shipment) {
            throw new EasyParcelReconciliationPendingException('shipment_missing');
        }

        $shipment = $this->details->reconcile($shipment);
        if (blank($shipment->tracking_number)) {
            throw new EasyParcelReconciliationPendingException('awb_not_available');
        }
    }

    private function reconcileWebhook(EasyParcelReconciliationTask $task): void
    {
        $receipt = ShippingWebhookReceipt::query()->find($task->shipping_webhook_receipt_id);
        if (! $receipt instanceof ShippingWebhookReceipt) {
            throw new EasyParcelReconciliationPendingException('receipt_missing');
        }

        $this->webhooks->reconcile($receipt);
    }

    private function resolve(EasyParcelReconciliationTask $claimed): void
    {
        DB::transaction(function () use ($claimed): void {
            $task = EasyParcelReconciliationTask::query()->lockForUpdate()->find($claimed->id);
            if (! $task instanceof EasyParcelReconciliationTask
                || $task->state !== 'processing'
                || ! hash_equals((string) $claimed->lease_token, (string) $task->lease_token)) {
                return;
            }

            $task->forceFill([
                'state' => 'resolved',
                'available_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error' => null,
                'completed_at' => now(),
            ])->save();
        });
    }

    private function retry(EasyParcelReconciliationTask $claimed, string $safeCode): void
    {
        DB::transaction(function () use ($claimed, $safeCode): void {
            $task = EasyParcelReconciliationTask::query()->lockForUpdate()->find($claimed->id);
            if (! $task instanceof EasyParcelReconciliationTask
                || $task->state !== 'processing'
                || ! hash_equals((string) $claimed->lease_token, (string) $task->lease_token)) {
                return;
            }

            $failed = $task->processing_attempt_count >= self::MAX_ATTEMPTS;
            $delayMinutes = min(15, max(1, 2 ** min(4, max(0, $task->processing_attempt_count - 1))));
            $task->forceFill([
                'state' => $failed ? 'failed' : 'retrying',
                'available_at' => $failed ? null : now()->addMinutes($delayMinutes),
                'published_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error' => $failed ? 'retry_exhausted' : $safeCode,
                'completed_at' => $failed ? now() : null,
            ])->save();
        });
    }
}
