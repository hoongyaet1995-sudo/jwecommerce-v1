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
        private readonly EasyParcelWebhookCorrelationService $correlation,
        private readonly EasyParcelBookingReconciliationService $bookings,
        private readonly EasyParcelFailedBookingRecoveryService $recoveries,
        private readonly EasyParcelStatusNormalizer $statuses,
    ) {}

    public function reconcile(ShippingWebhookReceipt $receipt): void
    {
        try {
            $locked = ShippingWebhookReceipt::query()->findOrFail($receipt->id);
            if (in_array($locked->processing_state, ['processed', 'ignored'], true)) {
                return;
            }

            $shipment = $locked->shipment;
            if ($shipment === null) {
                $snapshot = is_array($locked->correlation_snapshot) ? $locked->correlation_snapshot : [];
                $shipment = $this->correlation->shipment($snapshot);
                $shipmentNumber = $snapshot['shipment_number'] ?? null;
                if ($shipment === null && is_string($shipmentNumber)) {
                    $shipment = $this->bookings->reconcileShipmentNumber($shipmentNumber);
                }
                $correlated = $this->correlation->shipment($snapshot);
                if ($shipment === null || $correlated === null || ! $shipment->is($correlated)) {
                    throw new EasyParcelReconciliationPendingException('unmatched_receipt');
                }

                $locked->forceFill([
                    'shipment_id' => $shipment->id,
                    'processing_state' => 'queued',
                    'queued_at' => $locked->queued_at ?? now(),
                ])->save();
            }

            if (in_array($locked->topic, [
                'shipment.created',
                'shipment.awb.update',
                'shipment.status.update',
            ], true)) {
                $result = $this->details->reconcileWithSnapshot($shipment);
                $shipment = $result['shipment'];
                $providerDetails = $result['details'];
                if ($this->statuses->normalize($providerDetails['shipment_status_code']) === 'cancelled') {
                    $this->recoveries->recordProviderCancellation(
                        $shipment,
                        null,
                        $providerDetails['shipment_status_code'],
                        $providerDetails['shipment_status'],
                        $result['checked_at'],
                    );
                    $shipment->refresh();
                }
            }

            if ($locked->topic === 'shipment.tracking.update' || filled($shipment->tracking_number)) {
                $this->tracking->syncShipment($shipment);
            }

            $locked->forceFill([
                'processing_state' => 'processed',
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (EasyParcelReconciliationPendingException $exception) {
            ShippingWebhookReceipt::query()->whereKey($receipt->id)->update([
                'processing_state' => 'unmatched',
                'attempt_count' => DB::raw('LEAST(attempt_count + 1, 65535)'),
                'last_error' => $exception->safeCode,
                'updated_at' => now(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $unmatched = ShippingWebhookReceipt::query()->whereKey($receipt->id)->value('shipment_id') === null;
            ShippingWebhookReceipt::query()->whereKey($receipt->id)->update([
                'processing_state' => $unmatched ? 'unmatched' : 'retrying',
                'attempt_count' => DB::raw('LEAST(attempt_count + 1, 65535)'),
                'last_error' => 'provider_unavailable',
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }
}
