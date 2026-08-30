<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use InvalidArgumentException;

final class EasyParcelBookingGuard
{
    private const TERMINAL_REBOOKABLE_SHIPMENT_STATUSES = ['booking_failed', 'cancelled'];

    public function assertQuoteAllowed(Order $order): void
    {
        if ($order->status !== 'processing'
            || $this->hasActiveEasyParcelShipment($order)
            || $this->hasClaimedQuote($order)) {
            throw new InvalidArgumentException('Only unbooked processing orders can be quoted.');
        }
    }

    public function assertLegacyOrderMutationAllowed(Order $order): void
    {
        if ($this->hasActiveEasyParcelShipment($order) || $this->hasClaimedQuote($order)) {
            throw new InvalidArgumentException('This order is managed by an EasyParcel booking or unresolved booking attempt.');
        }
    }

    public function assertManualDispatchAllowed(Order $order): void
    {
        $this->assertLegacyOrderMutationAllowed($order);
    }

    public function assertFinalizationAllowed(Order $order, ShipmentQuote $quote): void
    {
        $hasCompetingManualShipment = Shipment::query()
            ->where('order_id', $order->id)
            ->where('source', 'manual')
            ->exists();
        $hasCompetingClaim = ShipmentQuote::query()
            ->where('order_id', $order->id)
            ->whereKeyNot($quote->id)
            ->whereNotNull('booking_claim_key')
            ->exists();

        if ($order->status !== 'processing' || $hasCompetingManualShipment || $hasCompetingClaim) {
            throw new InvalidArgumentException('EasyParcel booking outcome is uncertain and requires reconciliation.');
        }
    }

    private function hasActiveEasyParcelShipment(Order $order): bool
    {
        return Shipment::query()
            ->where('order_id', $order->id)
            ->where('source', ShippingProviderConfigurationService::PROVIDER)
            ->whereNotIn('status', self::TERMINAL_REBOOKABLE_SHIPMENT_STATUSES)
            ->exists();
    }

    private function hasClaimedQuote(Order $order): bool
    {
        return ShipmentQuote::query()
            ->where('order_id', $order->id)
            ->whereNotNull('booking_claim_key')
            ->exists();
    }
}
