<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\StoreSetting;
use Illuminate\Support\Collection;

class ShippingConfigurationService
{
    public function __construct(private CartPricingService $pricing) {}

    /**
     * @param Collection<int, array{variant: \App\Models\ProductVariant, quantity: int}> $items
     * @return array<string, array{label: string, detail: string, shipping: int, price: string}>
     */
    public function checkoutOptions(Collection $items, ?Coupon $coupon = null): array
    {
        $rates = data_get(StoreSetting::query()->where('key', 'shipping_rates')->value('value'), 'data', []);
        if (! is_array($rates)) return [];

        $options = [];
        foreach ($rates as $rate) {
            if (! is_array($rate) || ! ($rate['isActive'] ?? false)) continue;
            $code = $this->codeFor((string) ($rate['name'] ?? ''));
            if (isset($options[$code])) continue;
            $shipping = $this->pricing->summarize($items, $coupon, $code)['shipping'];
            $days = trim((string) ($rate['estimatedDays'] ?? ''));
            $options[$code] = [
                'label' => (string) ($rate['name'] ?? $this->pricing->deliveryLabel($code)),
                'detail' => 'Atelier dispatch'.($days !== '' ? ' • Estimated '.$days : ''),
                'shipping' => $shipping,
                'price' => $shipping > 0 ? '$'.number_format($shipping / 100, 2) : 'FREE',
            ];
        }

        return $options;
    }

    private function codeFor(string $name): string
    {
        $name = strtolower($name);
        if (str_contains($name, 'express')) return 'express';
        if (str_contains($name, 'white')) return 'white_glove';
        return 'standard';
    }
}
