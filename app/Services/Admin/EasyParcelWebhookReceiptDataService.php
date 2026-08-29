<?php

namespace App\Services\Admin;

use App\Models\ShippingWebhookReceipt;
use Illuminate\Support\Collection;

final class EasyParcelWebhookReceiptDataService
{
    /** @return Collection<int, array<string, mixed>> */
    public function collection(): Collection
    {
        return ShippingWebhookReceipt::query()
            ->where('provider', 'easyparcel')
            ->with('shipment.order')
            ->latest('received_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (ShippingWebhookReceipt $receipt): array => [
                'id' => (string) $receipt->id,
                'topic' => $receipt->topic,
                'state' => $receipt->processing_state,
                'shipmentId' => $receipt->shipment_id === null ? null : (string) $receipt->shipment_id,
                'orderNumber' => $receipt->shipment?->order?->number,
                'trackingNumber' => data_get($receipt->correlation_snapshot, 'awb_number'),
                'receivedAt' => $receipt->received_at?->toIso8601String(),
                'processedAt' => $receipt->processed_at?->toIso8601String(),
                'attemptCount' => $receipt->attempt_count,
                'errorCode' => $receipt->last_error,
            ]);
    }
}
