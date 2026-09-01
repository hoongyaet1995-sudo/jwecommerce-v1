<?php

namespace App\Services\Admin;

use App\Domain\Notifications\NotificationPermission;
use App\Domain\Payments\PaymentProofPermission;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\User;
use App\Services\Payments\PaymentProofService;
use Illuminate\Support\Collection;

class OrderDataService
{
    public function collection(): Collection
    {
        return Order::query()->with([
            'customer', 'items', 'addresses', 'payments.proofEvents.user', 'statusHistory', 'refunds', 'taxLines',
            'shipments', 'shipmentQuotes',
        ])->latest()->get()->map(fn (Order $order): array => $this->data($order));
    }

    public function data(Order $order, ?NotificationDispatch $notificationDispatch = null): array
    {
        $order->loadMissing([
            'customer', 'items', 'addresses', 'payments.proofEvents.user', 'statusHistory', 'refunds', 'taxLines',
            'shipments', 'shipmentQuotes',
        ]);
        $payment = $order->payments->sortByDesc('id')->first();
        $viewer = request()->user();
        $canViewProof = $viewer instanceof User && PaymentProofPermission::allows($viewer, PaymentProofPermission::VIEW);
        $canReviewProof = $viewer instanceof User && PaymentProofPermission::allows($viewer, PaymentProofPermission::REVIEW);
        $canViewNotifications = $viewer instanceof User && NotificationPermission::allows($viewer, NotificationPermission::DELIVERIES_VIEW);
        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        $billing = $order->addresses->firstWhere('type', 'billing') ?? $shipping;
        $shipment = $order->shipments->sortByDesc('id')->first();
        $shipmentQuote = $order->shipmentQuotes->sortByDesc('id')->first();
        $customerName = trim(($order->customer?->first_name ?? '').' '.($order->customer?->last_name ?? ''));
        $refundedAmount = (int) $order->refunds->where('status', 'completed')->sum('amount');
        $notificationDispatch = $canViewNotifications ? $notificationDispatch : null;

        $history = $order->statusHistory->map(function ($event): array {
            $type = match ($event->to_status) {
                'pending_payment' => 'created',
                'paid' => 'paid',
                'shipped' => 'tracking_added',
                default => str_contains((string) $event->note, 'Refunded ') ? 'refunded' : 'fulfillment_updated',
            };

            return ['id' => 'history-'.$event->id, 'timestamp' => $event->created_at?->toIso8601String() ?? now()->toIso8601String(), 'type' => $type, 'title' => match ($type) {
                'created' => 'Order Created', 'paid' => 'Payment Confirmed', 'tracking_added' => 'Tracking Dispatched', 'refunded' => 'Refund Processed', default => 'Fulfillment Updated: '.strtoupper((string) $event->to_status)
            }, 'description' => $event->note ?: 'Order moved to '.str_replace('_', ' ', (string) $event->to_status).'.'];
        });

        return [
            'id' => (string) $order->id,
            'orderNumber' => $order->number,
            'createdAt' => $order->created_at?->toIso8601String(),
            'customerId' => (string) ($order->customer_id ?? ''),
            'customerName' => $customerName !== '' ? $customerName : $order->email,
            'customerEmail' => $order->email,
            'customerPhone' => $order->customer?->phone,
            'paymentGateway' => $this->paymentGatewayLabel($payment?->gateway),
            'paymentGatewayStatus' => in_array($payment?->status, ['paid', 'pending', 'refunded', 'partially_refunded', 'failed'], true) ? $payment->status : 'failed',
            'paymentProvider' => $this->paymentProviderLabel($payment?->provider, $payment?->gateway),
            'paymentReference' => $payment?->reference,
            'paymentEnvironment' => $payment?->environment,
            'paymentProof' => $this->paymentProof($order, $payment, $canViewProof, $canReviewProof),
            'fulfillmentStatus' => match ($order->status) {
                'processing', 'shipped', 'delivered', 'cancelled' => $order->status, default => 'unfulfilled'
            },
            'shippingSource' => $this->shippingSource($order, $shipment, $shipmentQuote),
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
            'notificationSummary' => $notificationDispatch === null ? null : [
                'publicId' => $notificationDispatch->public_id,
                'eventKey' => $notificationDispatch->event_key,
                'channel' => $notificationDispatch->channel,
                'state' => $notificationDispatch->state,
                'source' => $notificationDispatch->source,
                'queuedAt' => $notificationDispatch->queued_at?->toIso8601String(),
            ],
            'timeline' => $history->sortByDesc('timestamp')->values(),
        ];
    }

    private function address(?OrderAddress $address, string $type): array
    {
        return ['id' => (string) ($address?->id ?? ''), 'isDefault' => true, 'type' => $type, 'firstName' => $address?->first_name ?? '', 'lastName' => $address?->last_name ?? '', 'address1' => $address?->line_1 ?? '', 'address2' => $address?->line_2, 'city' => $address?->city ?? '', 'province' => $address?->state ?? '', 'country' => $address?->country ?? '', 'postalCode' => $address?->postal_code ?? '', 'phone' => $address?->phone ?? ''];
    }

    private function paymentGatewayLabel(?string $gateway): string
    {
        return match ($gateway) {
            'bank_transfer' => 'Bank In / DuitNow',
            'paypal' => 'PayPal',
            'apple_pay' => 'Apple Pay',
            'stripe' => 'Stripe',
            'billplz' => 'Billplz',
            'simulated_card' => 'Simulated Card',
            default => 'Credit Card',
        };
    }

    private function paymentProviderLabel(?string $provider, ?string $gateway): string
    {
        if ($gateway === 'bank_transfer') {
            return 'Manual Bank Transfer';
        }

        return match ($provider) {
            null, '' => 'Not recorded',
            'simulated_card' => 'Simulated payment',
            default => ucfirst(str_replace('_', ' ', $provider)),
        };
    }

    private function paymentProof(Order $order, $payment, bool $canViewProof, bool $canReviewProof): array
    {
        if (! $payment || $payment->gateway !== 'bank_transfer') {
            return ['required' => false, 'status' => 'not_required', 'previewUrl' => null, 'downloadUrl' => null, 'canReview' => false, 'audit' => []];
        }

        $mimeType = PaymentProofService::safeMime($payment->proof_mime_type, $payment->proof_path);
        $originalFileName = PaymentProofService::safeFilename($payment->proof_original_name, $mimeType, $payment->proof_path);

        return [
            'required' => true,
            'paymentId' => (string) $payment->id,
            'status' => $payment->proof_status ?: ($payment->proof_path ? 'uploaded' : 'missing'),
            'uploadedAt' => $payment->proof_uploaded_at?->toIso8601String(),
            'reviewedAt' => $payment->proof_reviewed_at?->toIso8601String(),
            'originalFileName' => $originalFileName,
            'mimeType' => $mimeType,
            'previewUrl' => $canViewProof && $payment->proof_path ? route('admin.api.orders.payments.proof.show', [$order, $payment]) : null,
            'downloadUrl' => $canViewProof && $payment->proof_path ? route('admin.api.orders.payments.proof.download', [$order, $payment]) : null,
            'canReview' => $canReviewProof && $payment->proof_status === 'uploaded',
            'audit' => $payment->proofEvents->map(fn ($event): array => [
                'id' => (string) $event->id,
                'eventType' => $event->event_type,
                'reason' => $event->reason,
                'at' => $event->created_at?->toIso8601String(),
                'actor' => $event->user?->name,
            ])->values(),
        ];
    }

    private function shippingSource(Order $order, $shipment, $shipmentQuote): array
    {
        $snapshot = $order->shipping_snapshot ?? [];
        $fulfillmentModule = match ($shipment?->source) {
            'easyparcel' => 'EasyParcel',
            'manual' => 'Manual dispatch',
            default => $shipmentQuote ? ucfirst((string) $shipmentQuote->provider) : 'Not initiated',
        };

        return [
            'checkoutMethod' => $order->shipping_method,
            'checkoutService' => $order->shipping_label ?: data_get($snapshot, 'label'),
            'checkoutRateType' => data_get($snapshot, 'rate_type'),
            'checkoutCalculationMode' => data_get($snapshot, 'calculation_mode'),
            'fulfillmentModule' => $fulfillmentModule,
            'provider' => $shipment?->carrier_name ?? $shipmentQuote?->provider,
            'service' => $shipment?->service_name ?? $shipmentQuote?->selected_service_code,
            'quoteState' => $shipmentQuote?->booking_state ?? 'not_requested',
            'providerReference' => $shipment?->provider_reference,
        ];
    }
}
