<?php

namespace App\Services;

use App\Models\Coupon;

/** Display facts only; eligibility for an individual basket stays in checkout. */
final class PromotionHighlightsService
{
    public function offers(array $section): array
    {
        $selected = array_values(array_filter(array_column($section['items'] ?? [], 'sourceId'), fn ($id) => is_string($id) && preg_match('/^[1-9][0-9]{0,17}$/', $id)));
        $query = Coupon::query()->currentlyValid()->whereIn('discount_type', ['percentage', 'fixed_amount', 'free_shipping']);
        $offers = (clone $query)->orderByDesc('id')->limit(100)->get();
        if ($selected !== []) {
            $offers = $offers->concat((clone $query)->whereIn('id', $selected)->get())->unique('id')->values();
        }
        return $offers->map(fn (Coupon $c) => [
            'id' => (string) $c->id,
            'name' => $c->name ?: $c->code,
            'description' => $c->description ?? '',
            'code' => $c->code,
            'benefit' => match ($c->discount_type) {
                'percentage' => $c->value.'% off',
                'fixed_amount' => 'RM '.number_format($c->value / 100, 2, '.', '').' off',
                'free_shipping' => 'Free shipping',
            },
            'minimumSpend' => $c->min_spend === null ? null : 'RM '.number_format($c->min_spend / 100, 2, '.', ''),
            'endsAt' => $c->ends_at?->toIso8601String(),
            'status' => 'active',
        ])->all();
    }
}
