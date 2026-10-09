<?php

namespace App\Services;

use App\Models\StoreSetting;

/** Public informational projection; checkout remains the authority for eligibility and charges. */
final class DeliveryInformationService
{
    public function rows(): array
    {
        $rates = data_get(StoreSetting::query()->where('key', 'shipping_rates')->first()?->value, 'data', []);
        if (! is_array($rates)) return [];
        $regions = ['all' => 'Configured delivery areas', 'peninsular' => 'Peninsular Malaysia', 'east_malaysia' => 'Sabah & Sarawak', 'sabah' => 'Sabah', 'sarawak' => 'Sarawak', 'international' => 'International', 'custom' => 'Custom delivery area'];
        $rows = [];
        foreach ($rates as $rate) {
            if (! is_array($rate) || ! ($rate['isActive'] ?? false) || ! is_string($rate['id'] ?? null) || ! is_string($rate['name'] ?? null)) continue;
            $zone = (string) ($rate['zoneRegion'] ?? 'custom');
            $region = $zone === 'custom' ? implode(', ', array_filter(is_array($rate['customStates'] ?? null) ? $rate['customStates'] : [], 'is_string')) : ($regions[$zone] ?? $zone);
            $estimate = is_string($rate['estimatedDays'] ?? null) ? trim($rate['estimatedDays']) : '';
            $rows[] = ['id' => $rate['id'], 'title' => ($region ?: $regions['custom']).' — '.$rate['name'], 'deliveryEstimate' => $estimate ?: 'Confirm at checkout', 'deliveryFee' => 'Calculated at checkout'];
        }
        return $rows;
    }
}
