<?php

namespace App\Domain\Orders;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ShipmentEvent;

class OrderTrackingPayloadFactory
{
    /** @return array<string, mixed> */
    public function for(Order $order): array
    {
        $order->loadMissing([
            'items',
            'addresses',
            'payments',
            'statusHistory',
            'shipments.events',
            'taxLines',
        ]);

        $shipment = $order->shipments->sortByDesc('sequence')->first();
        $shippingAddress = $order->addresses->firstWhere('type', 'shipping');
        $payment = $order->payments->sortByDesc('id')->first();

        return [
            'number' => $order->number,
            'status' => $order->status,
            'statusLabel' => $this->statusTitle($order->status),
            'createdAt' => $order->created_at?->toIso8601String(),
            'currency' => $order->currency,
            'currencyExchangeRate' => (float) ($order->currency_exchange_rate ?? 1),
            'shippingLabel' => $order->shipping_label,
            'items' => $order->items->map(fn ($item): array => [
                'title' => $item->title,
                'variantTitle' => $item->variant_title,
                'sku' => $item->sku,
                'imageUrl' => $item->image_url,
                'unitPrice' => $item->unit_price,
                'quantity' => $item->quantity,
                'lineTotal' => $item->line_total,
            ])->values()->all(),
            'address' => $shippingAddress ? [
                'firstName' => $shippingAddress->first_name,
                'lastName' => $shippingAddress->last_name,
                'phone' => $shippingAddress->phone,
                'line1' => $shippingAddress->line_1,
                'line2' => $shippingAddress->line_2,
                'city' => $shippingAddress->city,
                'state' => $shippingAddress->state,
                'postalCode' => $shippingAddress->postal_code,
                'country' => $shippingAddress->country,
            ] : null,
            'payment' => $payment ? [
                'method' => str_replace('_', ' ', $payment->gateway),
                'status' => $payment->status,
            ] : null,
            'totals' => [
                'subtotal' => $order->subtotal,
                'discount' => $order->discount_amount,
                'shipping' => $order->shipping_amount,
                'tax' => $order->tax_amount,
                'total' => $order->total,
            ],
            'tracking' => [
                'carrier' => $shipment?->carrier_name ?: $order->tracking_carrier,
                'number' => $shipment?->tracking_number ?: $order->tracking_number,
                'status' => $shipment?->status,
                'estimatedDelivery' => null,
            ],
            'timeline' => $this->timeline($order),
        ];
    }

    /** @return list<array{status: string, title: string, description: string, timestamp: ?string, location: ?string}> */
    private function timeline(Order $order): array
    {
        $orderEvents = $order->statusHistory->map(fn (OrderStatusHistory $history): array => [
            'status' => $history->to_status,
            'title' => $this->statusTitle($history->to_status),
            'description' => $history->note ?: 'Order status updated.',
            'timestamp' => $history->created_at?->toIso8601String(),
            'location' => null,
        ]);
        $shipmentEvents = $order->shipments->flatMap(fn ($shipment) => $shipment->events)
            ->map(fn (ShipmentEvent $event): array => [
                'status' => $event->status,
                'title' => $this->shipmentTitle($event->status),
                'description' => $event->description,
                'timestamp' => $event->occurred_at?->toIso8601String(),
                'location' => $event->location,
            ]);

        return $orderEvents->merge($shipmentEvents)
            ->sortBy('timestamp')
            ->values()
            ->all();
    }

    private function statusTitle(string $status): string
    {
        return match ($status) {
            'pending_payment' => 'Payment Pending',
            'paid' => 'Payment Confirmed',
            'processing' => 'Preparing Your Order',
            'shipped' => 'Handed to Carrier',
            'delivered' => 'Delivered',
            'cancelled' => 'Order Cancelled',
            default => 'Order Update',
        };
    }

    private function shipmentTitle(string $status): string
    {
        return match ($status) {
            'pending' => 'Shipment Confirmed',
            'shipped' => 'Handed to Carrier',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'failed_delivery', 'booking_failed', 'cancelled', 'on_hold' => 'Delivery Exception',
            'returned' => 'Return Update',
            default => 'Shipment Update',
        };
    }
}
