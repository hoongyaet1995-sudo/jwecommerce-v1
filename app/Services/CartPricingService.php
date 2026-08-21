<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\StoreSetting;
use Illuminate\Support\Collection;

class CartPricingService
{
    /** @param Collection<int, array{variant: \App\Models\ProductVariant, quantity: int}> $items */
    public function summarize(Collection $items, ?Coupon $coupon = null, string $delivery = 'standard', ?array $shippingAddress = null): array
    {
        $subtotal = $items->sum(fn (array $item): int => $item['variant']->price * $item['quantity']);
        $discount = $this->discountFor($subtotal, $coupon);
        $settings = StoreSetting::query()->pluck('value', 'key');
        $setting = fn (string $key, float $default = 0): float => (float) data_get($settings->get($key), 'data', $default);
        $shippingRate = $this->configuredShipping($settings->get('shipping_rates'), $items, $delivery, $shippingAddress);
        if ($shippingRate === null) $shippingRate = (int) max(0, $setting('shipping_rate_cents'));
        $freeShippingThreshold = (int) max(0, $setting('free_shipping_threshold_cents') ?: $setting('shipping_threshold'));
        $configuredFreeThreshold = $this->configuredFreeThreshold($settings->get('shipping_rates'), $delivery, $shippingAddress);
        if ($configuredFreeThreshold !== null) $freeShippingThreshold = $configuredFreeThreshold;
        $standardShipping = $coupon?->discount_type === 'free_shipping' || ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold) ? 0 : $shippingRate;
        $shipping = match ($delivery) {
            'express' => $shippingRate > 0 ? $shippingRate : 0,
            'white_glove' => $shippingRate > 0 ? $shippingRate : 3500,
            default => $standardShipping,
        };
        $taxable = max(0, $subtotal - $discount + $shipping);
        $taxRate = $setting('tax_rate_percent') ?: $setting('tax_rate');
        $tax = (int) floor($taxable * min(100, max(0, $taxRate)) / 100);

        return ['subtotal' => $subtotal, 'discount' => $discount, 'shipping' => $shipping, 'tax' => $tax, 'total' => max(0, $subtotal - $discount + $shipping + $tax)];
    }

    public function deliveryLabel(string $delivery): string
    {
        return match ($delivery) {
            'express' => 'Express Air Courier',
            'white_glove' => 'White-Glove VIP Same Day Courier',
            default => 'Standard Carbon-Neutral Delivery',
        };
    }

    private function discountFor(int $subtotal, ?Coupon $coupon): int
    {
        if (! $coupon || ! $coupon->isCurrentlyValid() || ($coupon->min_spend && $subtotal < $coupon->min_spend)) return 0;
        return match ($coupon->discount_type) {
            'percentage' => (int) floor($subtotal * min(100, $coupon->value) / 100),
            'fixed_amount' => min($subtotal, $coupon->value),
            'free_shipping' => 0,
            default => 0,
        };
    }

    private function configuredShipping(mixed $document, Collection $items, string $delivery, ?array $shippingAddress): ?int
    {
        $rate = $this->configuredRate($document, $delivery, $shippingAddress);
        if (! $rate) return null;
        if (($rate['calculationMode'] ?? null) === 'per_order' || ($rate['rateType'] ?? null) === 'flat') return (int) round(max(0, (float) ($rate['flatRateCost'] ?? 0)) * 100);
        $measure = ($rate['calculationMode'] ?? null) === 'quantity'
            ? $items->sum(fn (array $item): int => $item['quantity'])
            : $items->sum(fn (array $item): float => (($item['variant']->weight_grams ?? 0) / 1000) * $item['quantity']);
        foreach (($rate['tiers'] ?? []) as $tier) {
            if ($measure >= (float) ($tier['min'] ?? 0) && $measure <= (float) ($tier['max'] ?? PHP_FLOAT_MAX)) return (int) round(max(0, (float) ($tier['cost'] ?? 0)) * 100);
        }
        return 0;
    }

    private function configuredFreeThreshold(mixed $document, string $delivery, ?array $shippingAddress): ?int
    {
        $rate = $this->configuredRate($document, $delivery, $shippingAddress);
        return isset($rate['freeShippingThreshold']) ? (int) round(max(0, (float) $rate['freeShippingThreshold']) * 100) : null;
    }

    private function configuredRate(mixed $document, string $delivery, ?array $shippingAddress): ?array
    {
        $rates = data_get($document, 'data', []);
        if (! is_array($rates)) return null;
        $matches = fn (array $rate): bool => match ($delivery) {
            'express' => str_contains(strtolower((string) ($rate['name'] ?? '')), 'express'),
            'white_glove' => str_contains(strtolower((string) ($rate['name'] ?? '')), 'white'),
            default => ! str_contains(strtolower((string) ($rate['name'] ?? '')), 'express') && ! str_contains(strtolower((string) ($rate['name'] ?? '')), 'white'),
        };
        foreach ($rates as $rate) if (is_array($rate) && ($rate['isActive'] ?? false) && $matches($rate) && $this->matchesZone($rate, $shippingAddress)) return $rate;
        return null;
    }

    private function matchesZone(array $rate, ?array $address): bool
    {
        if (! $address) return ($rate['zoneRegion'] ?? 'all') === 'all';
        $zone = $rate['zoneRegion'] ?? 'all';
        $country = strtoupper((string) ($address['country'] ?? 'MY'));
        $state = strtolower((string) ($address['state'] ?? ''));
        $isSabah = str_contains($state, 'sabah');
        $isSarawak = str_contains($state, 'sarawak');
        return match ($zone) {
            'all' => true,
            'international' => $country !== 'MY',
            'sabah' => $country === 'MY' && $isSabah,
            'sarawak' => $country === 'MY' && $isSarawak,
            'east_malaysia' => $country === 'MY' && ($isSabah || $isSarawak),
            'peninsular' => $country === 'MY' && ! $isSabah && ! $isSarawak,
            'custom' => in_array($state, array_map('strtolower', $rate['customStates'] ?? []), true),
            default => false,
        };
    }
}
