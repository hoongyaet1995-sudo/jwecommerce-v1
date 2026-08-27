<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Collection;

class ShipmentDataService
{
    public function collection(): Collection
    {
        $shipments = Shipment::query()
            ->with(['order.customer', 'order.items', 'order.addresses', 'order.payments', 'events'])
            ->latest('id')
            ->get()
            ->map(fn (Shipment $shipment): array => $this->data($shipment));

        $pending = Order::query()
            ->with(['customer', 'items', 'addresses', 'payments'])
            ->where('status', 'processing')
            ->whereDoesntHave('shipments', fn ($query) => $query->where('source', 'easyparcel'))
            ->latest('id')
            ->get()
            ->map(fn (Order $order): array => $this->pendingData($order));

        return $shipments
            ->concat($pending)
            ->sortByDesc('createdAt')
            ->values();
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
            'customerName' => $name !== '' ? $name : 'Customer',
            'items' => $order->items->map(fn ($item): array => [
                'sku' => $item->sku,
                'name' => $item->title,
                'qty' => $item->quantity,
                'unitPrice' => $item->unit_price / 100,
                'weightKg' => $this->itemWeightKg($item->package_snapshot),
            ])->values(),
            'courier' => $shipment->source === 'easyparcel' ? 'easyparcel' : ($shipment->carrier_code ?? 'manual'),
            'serviceType' => $shipment->service_name ?? $shipment->carrier_name,
            'trackingNumber' => $shipment->tracking_number ?? '',
            'status' => $shipment->source === 'easyparcel' && filled($shipment->tracking_number)
                ? 'ready_to_ship'
                : $shipment->status,
            'paymentMode' => $order->payments->first()?->gateway === 'cod' ? 'cod' : 'prepaid',
            'codAmount' => $order->payments->first()?->gateway === 'cod' ? $order->total / 100 : null,
            'createdAt' => $shipment->created_at?->toIso8601String(),
            'awbGeneratedAt' => $shipment->source === 'easyparcel' && filled($shipment->tracking_number)
                ? $shipment->created_at?->toIso8601String()
                : null,
            'dispatchedAt' => $shipment->dispatched_at?->toIso8601String(),
            'deliveredAt' => $shipment->delivered_at?->toIso8601String(),
            'actualCourierCharge' => $shipment->shipping_cost === null ? null : $shipment->shipping_cost / 100,
            'totalWeightKg' => data_get($shipment->package_snapshot, 'weight_kg')
                ?? $this->totalWeightKg($order),
            'volumetricWeightKg' => $this->volumetricWeightKg($shipment->package_snapshot),
            'dimensionsCm' => $this->dimensions($shipment->package_snapshot),
            'routingSortCode' => '',
            'isEastMalaysiaAirRestricted' => false,
            'postcodeValidationStatus' => 'unknown',
            'systemQuotedCharge' => data_get($shipment->quote_snapshot, 'service.amount_minor') === null
                ? null
                : data_get($shipment->quote_snapshot, 'service.amount_minor') / 100,
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

    /** @return array<string, mixed> */
    private function pendingData(Order $order): array
    {
        $address = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        $name = trim(($order->customer?->first_name ?? $address?->first_name ?? '').' '.($order->customer?->last_name ?? $address?->last_name ?? ''));
        $package = $this->initialPackage($order);

        return [
            'id' => 'pending-order-'.$order->id,
            'orderId' => (string) $order->id,
            'orderNumber' => $order->number,
            'customerName' => $name !== '' ? $name : 'Customer',
            'items' => $order->items->map(fn ($item): array => [
                'sku' => $item->sku,
                'name' => $item->title,
                'qty' => $item->quantity,
                'unitPrice' => $item->unit_price / 100,
                'weightKg' => $this->itemWeightKg($item->package_snapshot),
            ])->values(),
            'courier' => 'easyparcel',
            'serviceType' => '',
            'trackingNumber' => '',
            'status' => 'pending',
            'paymentMode' => $order->payments->first()?->gateway === 'cod' ? 'cod' : 'prepaid',
            'codAmount' => $order->payments->first()?->gateway === 'cod' ? $order->total / 100 : null,
            'createdAt' => $order->created_at?->toIso8601String(),
            'awbGeneratedAt' => null,
            'dispatchedAt' => null,
            'deliveredAt' => null,
            'actualCourierCharge' => null,
            'totalWeightKg' => $this->totalWeightKg($order),
            'volumetricWeightKg' => $this->volumetricWeightKg($package),
            'dimensionsCm' => $this->dimensions($package),
            'routingSortCode' => '',
            'isEastMalaysiaAirRestricted' => false,
            'postcodeValidationStatus' => 'unknown',
            'systemQuotedCharge' => null,
            'isWeightDiscrepancy' => false,
            'trackingEvents' => [],
        ];
    }

    /** @return array<string, float>|null */
    private function initialPackage(Order $order): ?array
    {
        if ($order->items->count() !== 1 || $order->items->first()->quantity !== 1) {
            return null;
        }

        $snapshot = $order->items->first()->package_snapshot;
        if (! is_array($snapshot)) {
            return null;
        }

        foreach (['length_cm', 'width_cm', 'height_cm'] as $key) {
            if (! is_numeric($snapshot[$key] ?? null) || (float) $snapshot[$key] <= 0) {
                return null;
            }
        }

        return [
            'length_cm' => (float) $snapshot['length_cm'],
            'width_cm' => (float) $snapshot['width_cm'],
            'height_cm' => (float) $snapshot['height_cm'],
        ];
    }

    /** @param array<string, mixed>|null $snapshot */
    private function itemWeightKg(?array $snapshot): ?float
    {
        $grams = $snapshot['weight_grams'] ?? null;

        return is_numeric($grams) && (float) $grams > 0
            ? round((float) $grams / 1000, 3)
            : null;
    }

    private function totalWeightKg(Order $order): ?float
    {
        $grams = $order->items->sum(function ($item): float {
            $weight = data_get($item->package_snapshot, 'weight_grams');

            return is_numeric($weight) && (float) $weight > 0
                ? (float) $weight * $item->quantity
                : 0;
        });

        return $grams > 0 ? round($grams / 1000, 3) : null;
    }

    /** @param array<string, mixed>|null $snapshot @return array{length: float, width: float, height: float}|null */
    private function dimensions(?array $snapshot): ?array
    {
        if (! is_array($snapshot)) {
            return null;
        }

        $length = $snapshot['length_cm'] ?? null;
        $width = $snapshot['width_cm'] ?? null;
        $height = $snapshot['height_cm'] ?? null;
        if (! is_numeric($length) || ! is_numeric($width) || ! is_numeric($height)) {
            return null;
        }

        return ['length' => (float) $length, 'width' => (float) $width, 'height' => (float) $height];
    }

    /** @param array<string, mixed>|null $snapshot */
    private function volumetricWeightKg(?array $snapshot): ?float
    {
        $dimensions = $this->dimensions($snapshot);

        return $dimensions === null
            ? null
            : round(($dimensions['length'] * $dimensions['width'] * $dimensions['height']) / 5000, 2);
    }
}
