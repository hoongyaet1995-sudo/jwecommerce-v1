<?php

namespace App\Services\Shipping;

use App\Models\ShippingWebhookReceipt;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EasyParcelWebhookReconciliationService
{
    public function __construct(
        private readonly EasyParcelShipmentDetailsService $details,
        private readonly EasyParcelTrackingService $tracking,
    ) {}

    public function reconcile(ShippingWebhookReceipt $receipt): void
    {
        try {
            $locked = ShippingWebhookReceipt::query()->findOrFail($receipt->id);
            if (in_array($locked->processing_state, ['processed', 'ignored'], true)) {
                return;
            }

            $shipment = $locked->shipment()->firstOrFail();

            if (in_array($locked->topic, [
                'shipment.created',
                'shipment.awb.update',
                'shipment.status.update',
            ], true)) {
                $shipment = $this->details->reconcile($shipment);
            }

            if ($locked->topic === 'shipment.tracking.update' || filled($shipment->tracking_number)) {
                $this->tracking->syncShipment($shipment);
            }

            $locked->forceFill([
                'processing_state' => 'processed',
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            ShippingWebhookReceipt::query()->whereKey($receipt->id)->update([
                'processing_state' => 'retrying',
                'attempt_count' => DB::raw('LEAST(attempt_count + 1, 65535)'),
                'last_error' => 'provider_unavailable',
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }
}
