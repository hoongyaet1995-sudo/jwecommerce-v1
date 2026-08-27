<?php

namespace App\Services\Admin;

use App\Models\OrderAddress;
use App\Models\Shipment;
use Illuminate\Support\Collection;

class ShipmentDataService
{
    public function collection(): Collection
    {
        return Shipment::query()
            ->with(['order.customer', 'order.items', 'order.addresses', 'order.payments', 'events'])
            ->latest('id')
            ->get()
            ->map(fn (Shipment $shipment): array => $this->data($shipment));
    }

    public function data(Shipment $shipment): array
    {
        $shipment->loadMissing(['order.customer', 'order.items', 'order.addresses', 'order.payments', 'events']);
        $order = $shipment->order;
        $address = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        $name = trim(($order->customer?->first_name ?? $address?->first_name ?? '').' '.($order->customer?->last_name ?? $address?->last_name ?? ''));

        return [
            'id' => (string) $shipment->id,
            'orderId' => (string) $order->id,
            'orderNumber' => $order->number,
            'customerName' => $name !== '' ? $name : $order->email,
            'customerPhone' => $order->customer?->phone ?? $address?->phone ?? '',
            'customerEmail' => $order->email,
            'recipientAddress' => $this->address($address),
            'items' => $order->items->map(fn ($item): array => [
                'sku' => $item->sku,
                'name' => $item->title,
                'qty' => $item->quantity,
                'unitPrice' => $item->unit_price / 100,
                'weightKg' => null,
            ])->values(),
            'courier' => $shipment->carrier_code ?? 'manual',
            'serviceType' => $shipment->service_name ?? $shipment->carrier_name,
            'trackingNumber' => $shipment->tracking_number ?? '',
            'status' => $shipment->status,
            'paymentMode' => $order->payments->first()?->gateway === 'cod' ? 'cod' : 'prepaid',
            'codAmount' => $order->payments->first()?->gateway === 'cod' ? $order->total / 100 : null,
            'createdAt' => $shipment->created_at?->toIso8601String(),
            'dispatchedAt' => $shipment->dispatched_at?->toIso8601String(),
            'deliveredAt' => $shipment->delivered_at?->toIso8601String(),
            'actualCourierCharge' => $shipment->shipping_cost === null ? null : $shipment->shipping_cost / 100,
            'totalWeightKg' => null,
            'volumetricWeightKg' => null,
            'dimensionsCm' => null,
            'routingSortCode' => '',
            'isEastMalaysiaAirRestricted' => false,
            'postcodeValidationStatus' => 'unknown',
            'systemQuotedCharge' => null,
            'isWeightDiscrepancy' => false,
            'trackingEvents' => $shipment->events->map(fn ($event): array => [
                'id' => (string) $event->id,
                'timestamp' => $event->occurred_at?->toIso8601String(),
                'location' => $event->location,
                'status' => $event->status,
                'description' => $event->description,
                'source' => $event->source,
            ])->values(),
        ];
    }

    private function address(?OrderAddress $address): array
    {
        $state = $address?->state ?? '';
        $normalizedState = strtolower($state);
        $region = str_contains($normalizedState, 'sabah') ? 'sabah' : (str_contains($normalizedState, 'sarawak') ? 'sarawak' : 'peninsular');

        return [
            'street' => trim(($address?->line_1 ?? '').' '.($address?->line_2 ?? '')),
            'city' => $address?->city ?? '',
            'state' => $state,
            'postcode' => $address?->postal_code ?? '',
            'country' => $address?->country ?? '',
            'region' => $region,
        ];
    }
}
