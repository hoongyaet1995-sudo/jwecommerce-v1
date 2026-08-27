<?php

namespace App\Services;

use Illuminate\Support\Collection;

class TaxQuoteService
{
    public function __construct(private readonly StoreTaxClassService $storeTaxClassService)
    {
    }

    /**
     * The address and shipping inputs are compatibility inputs only. Store-wide
     * Tax Classes tax discounted merchandise and never shipping or payment fees.
     *
     * @param Collection<int, array{variant: \App\Models\ProductVariant, quantity: int}> $items
     * @return array<string, mixed>
     */
    public function quote(Collection $items, ?array $address = null, int $shippingAmount = 0, int $merchandiseDiscount = 0): array
    {
        $taxClass = $this->storeTaxClassService->active();
        if (! $taxClass) {
            return [
                'tax' => 0,
                'status' => 'not_configured',
                'reason' => 'No active Tax Class is configured by the merchant.',
                'label' => null,
                'rate_percent' => null,
                'taxable_amount' => 0,
                'lines' => [],
            ];
        }

        $subtotal = $items->sum(fn (array $item): int => max(0, (int) $item['variant']->price) * max(0, (int) $item['quantity']));
        $taxableAmount = max(0, $subtotal - max(0, $merchandiseDiscount));
        $ratePercent = (string) $taxClass->rate_percent;
        $tax = $this->taxFor($taxableAmount, $ratePercent);
        $status = $this->rateBasisPoints($ratePercent) === 0 ? 'matched_zero_rate' : 'chargeable';

        return [
            'tax' => $tax,
            'status' => $status,
            'reason' => 'The active store-wide Tax Class was applied to discounted merchandise.',
            'label' => $taxClass->name,
            'rate_percent' => $ratePercent,
            'taxable_amount' => $taxableAmount,
            'lines' => [[
                'kind' => 'merchandise',
                'variant_id' => null,
                'label' => $taxClass->name,
                'rule_id' => null,
                'tax_class_id' => $taxClass->id,
                'tax_class' => $taxClass->name,
                'rate_percent' => $ratePercent,
                'taxable_amount' => $taxableAmount,
                'tax_amount' => $tax,
                'status' => $status,
                'calculation_mode' => 'store_wide_percentage',
            ]],
        ];
    }

    private function taxFor(int $taxableAmount, string $ratePercent): int
    {
        $numerator = max(0, $taxableAmount) * $this->rateBasisPoints($ratePercent);

        return intdiv($numerator + 500_000, 1_000_000);
    }

    private function rateBasisPoints(string $ratePercent): int
    {
        $normalized = number_format(max(0, min(100, (float) $ratePercent)), 4, '.', '');

        return (int) str_replace('.', '', $normalized);
    }
}
