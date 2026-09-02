<?php

namespace App\Services\Notifications;

use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\StoreSetting;
use App\Support\ShopBrand;
use Illuminate\Support\Stringable;
use Illuminate\Validation\ValidationException;

class NotificationContextFactory
{
    /** @var array{shop_name: string, shop_email: string, shop_phone: string, shop_whatsapp: string}|null */
    private ?array $shopContext = null;

    public function __construct(private readonly ShopBrand $shopBrand) {}

    /** @param array<string, mixed> $extra
     * @return array<string, string>
     */
    public function forOrder(Order $order, array $extra = []): array
    {
        $this->validateExtra($extra);

        $order->loadMissing(['customer', 'items', 'payments', 'addresses', 'shipments']);

        $shippingAddress = $order->addresses->firstWhere('type', 'shipping');
        $payment = $order->payments->sortByDesc('id')->first();
        $shipment = $order->shipments->sortByDesc('id')->first();
        $customerName = $this->customerName($order, $shippingAddress);
        $trackingUrl = url('/order-tracking');

        return array_merge([
            'order_number' => (string) $order->number,
            'order_total' => $this->money((int) $order->total, (string) $order->currency),
            'order_date' => $order->created_at?->toDateString() ?? '',
            'order_items_summary' => $this->itemsSummary($order->items->all()),
            'order_url' => $trackingUrl,
            'tracking_url' => $trackingUrl,
            'customer_name' => $customerName,
            'customer_first_name' => $this->customerFirstName($order, $shippingAddress),
            'customer_email' => (string) ($order->customer?->email ?: $order->email),
            'customer_phone' => (string) ($order->customer?->phone ?: $shippingAddress?->phone ?: ''),
            'payment_method' => $payment ? str_replace('_', ' ', (string) $payment->gateway) : '',
            'payment_reference' => (string) ($payment?->reference ?: ''),
            'shipping_address' => $this->shippingAddress($shippingAddress),
            'carrier_name' => $this->carrierName($shipment, $order),
            'tracking_number' => (string) ($shipment?->tracking_number ?: $order->tracking_number ?: ''),
        ], $this->shopContext(), $this->stringExtra($extra));
    }

    public function fingerprintForOrder(Order $order): string
    {
        $context = $this->forOrder($order);
        ksort($context);

        return hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $extra */
    private function validateExtra(array $extra): void
    {
        $allowed = ['status_name', 'staff_note', 'payment_proof_reason'];
        $errors = [];

        foreach ($extra as $name => $value) {
            if (! in_array($name, $allowed, true)) {
                $errors[$name][] = 'This notification context value is not allowed.';
            } elseif (! is_scalar($value) && ! $value instanceof Stringable) {
                $errors[$name][] = 'This notification context value must be a string.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $extra
     * @return array<string, string>
     */
    private function stringExtra(array $extra): array
    {
        return array_map(static fn (mixed $value): string => (string) $value, $extra);
    }

    /** @param list<OrderItem> $items */
    private function itemsSummary(array $items): string
    {
        return collect($items)
            ->map(static fn (OrderItem $item): string => trim($item->title.' × '.$item->quantity))
            ->implode(', ');
    }

    private function customerName(Order $order, ?OrderAddress $shippingAddress): string
    {
        $name = trim(implode(' ', array_filter([
            $order->customer?->first_name ?: $shippingAddress?->first_name,
            $order->customer?->last_name ?: $shippingAddress?->last_name,
        ])));

        return $name !== '' ? $name : 'Customer';
    }

    private function customerFirstName(Order $order, ?OrderAddress $shippingAddress): string
    {
        return (string) ($order->customer?->first_name ?: $shippingAddress?->first_name ?: 'Customer');
    }

    private function shippingAddress(?OrderAddress $address): string
    {
        if ($address === null) {
            return 'Address not available';
        }

        return implode(', ', array_filter([
            trim($address->first_name.' '.$address->last_name),
            $address->line_1,
            $address->line_2,
            $address->city,
            $address->state,
            $address->postal_code,
            $address->country,
        ]));
    }

    private function carrierName(?Shipment $shipment, Order $order): string
    {
        return (string) ($shipment?->carrier_name ?: $order->tracking_carrier ?: '');
    }

    private function money(int $amount, string $currency): string
    {
        return trim($currency.' '.number_format($amount / 100, 2, '.', ','));
    }

    private function storeValue(string $key, string $default = ''): string
    {
        return trim((string) data_get(
            StoreSetting::query()->where('key', $key)->value('value'),
            'data',
            $default,
        ));
    }

    /** @return array{shop_name: string, shop_email: string, shop_phone: string, shop_whatsapp: string} */
    private function shopContext(): array
    {
        return $this->shopContext ??= [
            'shop_name' => $this->shopBrand->name(),
            'shop_email' => $this->storeValue('support_email', (string) config('mail.from.address')),
            'shop_phone' => $this->storeValue('support_phone'),
            'shop_whatsapp' => $this->storeValue('whatsapp'),
        ];
    }
}
