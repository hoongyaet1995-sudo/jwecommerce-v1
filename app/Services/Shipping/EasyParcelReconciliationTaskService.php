<?php

namespace App\Services\Shipping;

use App\Models\EasyParcelReconciliationTask;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingWebhookReceipt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EasyParcelReconciliationTaskService
{
    public const TYPE_BOOKING_REFERENCE = 'booking_reference';

    public const TYPE_SHIPMENT_DETAILS = 'shipment_details';

    public const TYPE_WEBHOOK_RECEIPT = 'webhook_receipt';

    public function ensureBooking(ShipmentQuote $quote): EasyParcelReconciliationTask
    {
        return $this->ensure('booking-reference:'.$quote->reconciliation_reference, [
            'type' => self::TYPE_BOOKING_REFERENCE,
            'shipment_quote_id' => $quote->id,
            'application_reference' => $quote->reconciliation_reference,
        ]);
    }

    public function ensureShipmentDetails(Shipment $shipment): EasyParcelReconciliationTask
    {
        $task = $this->ensure('shipment-details:'.$shipment->id, [
            'type' => self::TYPE_SHIPMENT_DETAILS,
            'shipment_id' => $shipment->id,
            'shipment_quote_id' => $shipment->shipment_quote_id,
            'application_reference' => $shipment->quote?->reconciliation_reference,
        ]);

        if ($task->state !== 'resolved') {
            return $task;
        }

        return DB::transaction(function () use ($task): EasyParcelReconciliationTask {
            $locked = EasyParcelReconciliationTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($locked->state !== 'resolved') {
                return $locked;
            }

            $locked->forceFill([
                'state' => 'pending',
                'available_at' => now(),
                'published_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error' => null,
                'completed_at' => null,
            ])->save();

            return $locked;
        });
    }

    public function ensureWebhookReceipt(ShippingWebhookReceipt $receipt): EasyParcelReconciliationTask
    {
        return $this->ensure('webhook-receipt:'.$receipt->id, [
            'type' => self::TYPE_WEBHOOK_RECEIPT,
            'shipping_webhook_receipt_id' => $receipt->id,
            'shipment_id' => $receipt->shipment_id,
        ]);
    }

    public function retryForOrder(Order $order): EasyParcelReconciliationTask
    {
        return DB::transaction(function () use ($order): EasyParcelReconciliationTask {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $shipment = Shipment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('source', ShippingProviderConfigurationService::PROVIDER)
                ->whereNotIn('status', ['delivered', 'returned', 'booking_failed', 'cancelled'])
                ->whereDoesntHave('failedBookingRecovery', function ($query): void {
                    $query->whereIn('state', ['cancelling', 'awaiting_replacement', 'complete']);
                })
                ->lockForUpdate()
                ->first();
            $quote = ShipmentQuote::query()
                ->where('order_id', $lockedOrder->id)
                ->where('provider', ShippingProviderConfigurationService::PROVIDER)
                ->whereNotNull('booking_claim_key')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($shipment === null && $quote === null) {
                throw new InvalidArgumentException('This order has no unresolved EasyParcel reconciliation.');
            }

            $task = $shipment !== null
                ? $this->ensureShipmentDetails($shipment)
                : $this->ensureBooking($quote);
            $lockedTask = EasyParcelReconciliationTask::query()->lockForUpdate()->findOrFail($task->id);
            $lockedTask->forceFill([
                'state' => 'pending',
                'available_at' => now(),
                'published_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error' => null,
                'completed_at' => null,
            ])->save();

            return $lockedTask;
        });
    }

    /** @param array<string, mixed> $values */
    private function ensure(string $key, array $values): EasyParcelReconciliationTask
    {
        $identity = [
            'provider' => ShippingProviderConfigurationService::PROVIDER,
            'environment' => ShippingProviderConfigurationService::ENVIRONMENT,
            'task_key' => $key,
        ];

        try {
            return EasyParcelReconciliationTask::query()->firstOrCreate($identity, [
                ...$values,
                'state' => 'pending',
                'available_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return EasyParcelReconciliationTask::query()->where($identity)->firstOrFail();
        }
    }
}
