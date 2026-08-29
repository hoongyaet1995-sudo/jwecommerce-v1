<?php

namespace App\Services\Shipping;

use App\Models\Shipment;

final class EasyParcelWebhookCorrelationService
{
    /** @param array<string, int|string> $snapshot */
    public function shipment(array $snapshot): ?Shipment
    {
        $providerReference = $snapshot['shipment_number'] ?? null;
        $awb = $snapshot['awb_number'] ?? null;
        $byReference = is_string($providerReference)
            ? Shipment::query()
                ->where('source', ShippingProviderConfigurationService::PROVIDER)
                ->where('provider_reference', $providerReference)
                ->first()
            : null;
        $byAwb = is_string($awb)
            ? Shipment::query()
                ->where('source', ShippingProviderConfigurationService::PROVIDER)
                ->where('tracking_number', $awb)
                ->first()
            : null;

        if (is_string($providerReference) && is_string($awb)) {
            return $byReference !== null && $byAwb !== null && $byReference->is($byAwb)
                ? $byReference
                : null;
        }

        return $byReference ?? $byAwb;
    }
}
