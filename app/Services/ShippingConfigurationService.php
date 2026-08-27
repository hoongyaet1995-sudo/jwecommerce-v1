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
     * @param array<string, mixed>|null $shippingAddress
     * @return array<string, array{id: string, label: string, detail: string, shipping: int, snapshot: array<string, mixed>}>
     */
    public function checkoutOptions(Collection $items, ?Coupon $coupon = null, ?array $shippingAddress = null): array
    {
        $document = StoreSetting::query()->where('key', 'shipping_rates')->first();
        if (! $document) {
            $option = $this->defaultOption($items, $coupon, $shippingAddress);

            return ['standard' => $option];
        }

        $rates = data_get($document->value, 'data', []);
        if (! is_array($rates)) return [];

        $options = [];
        foreach ($rates as $rate) {
            if (! is_array($rate) || ! ($rate['isActive'] ?? false) || ! $this->pricing->rateMatchesAddress($rate, $shippingAddress)) continue;
            $id = trim((string) ($rate['id'] ?? ''));
            if ($id === '') continue;
            $option = $this->optionForRate($items, $coupon, $rate, $shippingAddress);
            $options[$id] = $option;
        }

        return $options;
    }

    /** @param array<string, mixed>|null $shippingAddress */
    public function optionFor(Collection $items, ?Coupon $coupon, string $rateId, ?array $shippingAddress): ?array
    {
        $document = StoreSetting::query()->where('key', 'shipping_rates')->first();
        if (! $document) {
            return $rateId === 'standard' ? $this->defaultOption($items, $coupon, $shippingAddress) : null;
        }

        $rates = data_get($document->value, 'data', []);
        if (! is_array($rates)) return null;

        $eligible = collect($rates)
            ->filter(fn ($rate): bool => is_array($rate) && ($rate['isActive'] ?? false) && $this->pricing->rateMatchesAddress($rate, $shippingAddress))
            ->values();
        $rate = $eligible->first(fn (array $candidate): bool => (string) ($candidate['id'] ?? '') === $rateId);

        // Preserve existing checkout submissions while historical forms still post standard/express/white_glove.
        // New checkout UI posts the exact rate ID and is never collapsed by this compatibility path.
        if (! $rate && in_array($rateId, ['standard', 'express', 'white_glove'], true)) {
            $legacyMatches = $eligible->filter(fn (array $candidate): bool => $this->legacyCode($candidate) === $rateId)->values();
            $rate = $legacyMatches->count() === 1 ? $legacyMatches->first() : null;
        }

        if (is_array($rate)) return $this->optionForRate($items, $coupon, $rate, $shippingAddress);

        return null;
    }

    /**
     * Return the lowest real free-shipping threshold currently offered by an active rate.
     * Cart has no delivery address yet, so this is a non-binding progress hint only.
     */
    public function cartFreeShippingThreshold(): ?int
    {
        $document = StoreSetting::query()->where('key', 'shipping_rates')->first();
        $rates = data_get($document?->value, 'data', []);
        if (! is_array($rates)) return null;

        $thresholds = collect($rates)
            ->filter(fn ($rate): bool => is_array($rate) && ($rate['isActive'] ?? false))
            ->map(fn (array $rate): int => (int) round(max(0, (float) ($rate['freeShippingThreshold'] ?? 0)) * 100))
            ->filter(fn (int $threshold): bool => $threshold > 0);

        return $thresholds->isNotEmpty() ? $thresholds->min() : null;
    }

    /** @param array<string, mixed> $rate @param array<string, mixed>|null $shippingAddress */
    private function optionForRate(Collection $items, ?Coupon $coupon, array $rate, ?array $shippingAddress): array
    {
        $id = (string) $rate['id'];
        $shipping = $this->pricing->summarize($items, $coupon, $id, $shippingAddress)['shipping'];
        $days = trim((string) ($rate['estimatedDays'] ?? ''));

        return [
            'id' => $id,
            'label' => (string) ($rate['name'] ?? $this->pricing->deliveryLabel($id)),
            'detail' => 'Atelier dispatch'.($days !== '' ? ' • Estimated '.$days : ''),
            'shipping' => $shipping,
            'snapshot' => [
                'id' => $id,
                'label' => (string) ($rate['name'] ?? $this->pricing->deliveryLabel($id)),
                'zone' => (string) ($rate['zoneRegion'] ?? 'all'),
                'calculation_mode' => (string) ($rate['calculationMode'] ?? 'per_order'),
                'rate_type' => (string) ($rate['rateType'] ?? 'flat'),
                'flat_rate_cost' => (float) ($rate['flatRateCost'] ?? 0),
                'free_shipping_threshold' => (float) ($rate['freeShippingThreshold'] ?? 0),
                'estimated_days' => $days,
                'shipping_amount' => $shipping,
            ],
        ];
    }

    /** @param array<string, mixed>|null $shippingAddress */
    private function defaultOption(Collection $items, ?Coupon $coupon, ?array $shippingAddress): array
    {
        $shipping = $this->pricing->summarize($items, $coupon, 'standard', $shippingAddress)['shipping'];

        return [
            'id' => 'standard',
            'label' => $this->pricing->deliveryLabel('standard'),
            'detail' => 'Atelier dispatch',
            'shipping' => $shipping,
            'snapshot' => [
                'id' => 'standard',
                'label' => $this->pricing->deliveryLabel('standard'),
                'zone' => 'all',
                'calculation_mode' => 'system_default',
                'rate_type' => 'fallback',
                'flat_rate_cost' => 0,
                'free_shipping_threshold' => 0,
                'estimated_days' => '',
                'shipping_amount' => $shipping,
            ],
        ];
    }

    /** @param array<string, mixed> $rate */
    private function legacyCode(array $rate): string
    {
        $name = strtolower((string) ($rate['name'] ?? ''));

        return str_contains($name, 'express') ? 'express' : (str_contains($name, 'white') ? 'white_glove' : 'standard');
    }
}
