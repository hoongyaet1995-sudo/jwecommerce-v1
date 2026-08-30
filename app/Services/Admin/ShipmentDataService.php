<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Collection;

class ShipmentDataService
{
    private const DEFAULT_ITEM_WEIGHT_KG = 1.0;

    private const DEFAULT_PACKAGE_DIMENSION_CM = 1.0;

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
            ->whereDoesntHave('shipments', fn ($query) => $query
                ->where('source', 'easyparcel')
                ->whereNotIn('status', ['booking_failed', 'cancelled']))
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
            'source' => $shipment->source,
            'orderId' => (string) $order->id,
            'orderNumber' => $order->number,
            'customerName' => $name !== '' ? $name : 'Customer',
            'destinationPostcode' => $address?->postal_code,
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
            'status' => $this->fulfillmentStatus($shipment),
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
            'source' => 'pending',
            'orderId' => (string) $order->id,
            'orderNumber' => $order->number,
            'customerName' => $name !== '' ? $name : 'Customer',
            'destinationPostcode' => $address?->postal_code,
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

    private function fulfillmentStatus(Shipment $shipment): string
    {
        if ($shipment->source !== 'easyparcel') {
            return $shipment->status;
        }

        if (in_array($shipment->status, ['booking_failed', 'cancelled', 'on_hold'], true)) {
            return $shipment->status;
        }

        if (blank($shipment->tracking_number)) {
            return 'pending';
        }

        return in_array($shipment->status, ['pending', 'provider_update'], true)
            ? 'ready_to_ship'
            : $shipment->status;
    }

    /** @return array<string, float> */
    private function initialPackage(Order $order): array
    {
        if ($order->items->count() !== 1 || $order->items->first()->quantity !== 1) {
            return $this->defaultDimensionsSnapshot();
        }

        $snapshot = $order->items->first()->package_snapshot;
        if (! is_array($snapshot)) {
            return $this->defaultDimensionsSnapshot();
        }

        foreach (['length_cm', 'width_cm', 'height_cm'] as $key) {
            if (! is_numeric($snapshot[$key] ?? null) || (float) $snapshot[$key] <= 0) {
                return $this->defaultDimensionsSnapshot();
            }
        }

        return [
            'length_cm' => (float) $snapshot['length_cm'],
            'width_cm' => (float) $snapshot['width_cm'],
            'height_cm' => (float) $snapshot['height_cm'],
        ];
    }

    /** @param array<string, mixed>|null $snapshot */
    private function itemWeightKg(?array $snapshot): float
    {
        $grams = $snapshot['weight_grams'] ?? null;

        return is_numeric($grams) && (float) $grams > 0
            ? round((float) $grams / 1000, 3)
            : self::DEFAULT_ITEM_WEIGHT_KG;
    }

    private function totalWeightKg(Order $order): float
    {
        $weightKg = $order->items->sum(fn ($item): float => $this->itemWeightKg($item->package_snapshot) * $item->quantity);

        return $weightKg > 0 ? round($weightKg, 3) : self::DEFAULT_ITEM_WEIGHT_KG;
    }

    /** @return array{length_cm: float, width_cm: float, height_cm: float} */
    private function defaultDimensionsSnapshot(): array
    {
        return [
            'length_cm' => self::DEFAULT_PACKAGE_DIMENSION_CM,
            'width_cm' => self::DEFAULT_PACKAGE_DIMENSION_CM,
            'height_cm' => self::DEFAULT_PACKAGE_DIMENSION_CM,
        ];
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
