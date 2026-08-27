<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\OrderAddress;
use Illuminate\Support\Collection;

class OrderDataService
{
    public function collection(): Collection
    {
        return Order::query()->with(['customer', 'items', 'addresses', 'payments', 'statusHistory', 'refunds', 'taxLines'])->latest()->get()->map(fn (Order $order): array => $this->data($order));
    }

    public function data(Order $order): array
    {
        $order->loadMissing(['customer', 'items', 'addresses', 'payments', 'statusHistory', 'refunds', 'taxLines']);
        $payment = $order->payments->sortByDesc('id')->first();
        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        $billing = $order->addresses->firstWhere('type', 'billing') ?? $shipping;
        $customerName = trim(($order->customer?->first_name ?? '').' '.($order->customer?->last_name ?? ''));
        $refundedAmount = (int) $order->refunds->where('status', 'completed')->sum('amount');

        $history = $order->statusHistory->map(function ($event): array {
            $type = match ($event->to_status) {
                'pending_payment' => 'created',
                'paid' => 'paid',
                'shipped' => 'tracking_added',
                default => str_contains((string) $event->note, 'Refunded ') ? 'refunded' : 'fulfillment_updated',
            };
            return ['id' => 'history-'.$event->id, 'timestamp' => $event->created_at?->toIso8601String() ?? now()->toIso8601String(), 'type' => $type, 'title' => match ($type) { 'created' => 'Order Created', 'paid' => 'Payment Confirmed', 'tracking_added' => 'Tracking Dispatched', 'refunded' => 'Refund Processed', default => 'Fulfillment Updated: '.strtoupper((string) $event->to_status) }, 'description' => $event->note ?: 'Order moved to '.str_replace('_', ' ', (string) $event->to_status).'.'];
        });

        return [
            'id' => (string) $order->id,
            'orderNumber' => $order->number,
            'createdAt' => $order->created_at?->toIso8601String(),
            'customerId' => (string) ($order->customer_id ?? ''),
            'customerName' => $customerName !== '' ? $customerName : $order->email,
            'customerEmail' => $order->email,
            'customerPhone' => $order->customer?->phone,
            'paymentGateway' => match ($payment?->gateway) { 'paypal' => 'PayPal', 'apple_pay' => 'Apple Pay', default => 'Credit Card' },
            'paymentGatewayStatus' => in_array($payment?->status, ['paid', 'pending', 'refunded', 'partially_refunded', 'failed'], true) ? $payment->status : 'failed',
            'fulfillmentStatus' => match ($order->status) { 'processing', 'shipped', 'delivered', 'cancelled' => $order->status, default => 'unfulfilled' },
            'currency' => $order->currency,
            'items' => $order->items->map(fn ($item): array => ['id' => (string) $item->id, 'productId' => '', 'variantId' => $item->product_variant_id ? (string) $item->product_variant_id : null, 'title' => $item->title, 'variantTitle' => $item->variant_title, 'sku' => $item->sku, 'price' => $item->unit_price / 100, 'quantity' => $item->quantity, 'image' => $item->image_url ?: ''])->values(),
            'subtotal' => $order->subtotal / 100,
            'taxAmount' => $order->tax_amount / 100,
            'shippingAmount' => $order->shipping_amount / 100,
            'discountAmount' => $order->discount_amount / 100,
            'totalAmount' => $order->total / 100,
            'refundedAmount' => $refundedAmount / 100,
            'remainingRefundableAmount' => max(0, ((int) $order->total - $refundedAmount)) / 100,
            'refunds' => $order->refunds->sortByDesc('id')->map(fn ($refund): array => [
                'id' => (string) $refund->id,
                'amount' => $refund->amount / 100,
                'reason' => $refund->reason,
                'status' => $refund->status,
                'createdAt' => $refund->created_at?->toIso8601String(),
            ])->values(),
            'taxLines' => $order->taxLines->sortBy('id')->map(function ($line): array {
                $snapshot = $line->configuration_snapshot ?? [];

                return [
                    'id' => (string) $line->id,
                    'kind' => data_get($snapshot, 'quote_line.kind', 'merchandise'),
                    'label' => $line->label,
                    'taxType' => $line->tax_type,
                    'ratePercent' => (float) $line->rate_percent,
                    'taxableAmount' => $line->taxable_amount / 100,
                    'taxAmount' => $line->tax_amount / 100,
                    'quotedAt' => data_get($snapshot, 'quoted_at'),
                    'taxClassName' => data_get($snapshot, 'tax_class.name'),
                    'taxClassCode' => data_get($snapshot, 'tax_class.code'),
                    'taxZoneName' => data_get($snapshot, 'tax_zone.name'),
                    'taxZoneCode' => data_get($snapshot, 'tax_zone.code'),
                ];
            })->values(),
            'trackingNumber' => $order->tracking_number,
            'trackingCarrier' => $order->tracking_carrier,
            'shippingAddress' => $this->address($shipping, 'shipping'),
            'billingAddress' => $this->address($billing, 'billing'),
            'customerNote' => $order->customer_note,
            'internalNote' => $order->internal_note,
            'timeline' => $history->sortByDesc('timestamp')->values(),
        ];
    }

    private function address(?OrderAddress $address, string $type): array
    {
        return ['id' => (string) ($address?->id ?? ''), 'isDefault' => true, 'type' => $type, 'firstName' => $address?->first_name ?? '', 'lastName' => $address?->last_name ?? '', 'address1' => $address?->line_1 ?? '', 'address2' => $address?->line_2, 'city' => $address?->city ?? '', 'province' => $address?->state ?? '', 'country' => $address?->country ?? '', 'postalCode' => $address?->postal_code ?? '', 'phone' => $address?->phone ?? ''];
    }
}
