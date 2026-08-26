<?php

namespace App\Services;

use App\Models\TaxConfiguration;
use App\Models\TaxRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TaxQuoteService
{
    /**
     * @param Collection<int, array{variant: \App\Models\ProductVariant, quantity: int}> $items
     * @param array<string, mixed>|null $address
     * @return array{tax: int, status: string, reason: string, lines: list<array<string, mixed>>}
     */
    public function quote(Collection $items, ?array $address, int $shippingAmount = 0, int $merchandiseDiscount = 0, ?CarbonInterface $at = null): array
    {
        $configuration = TaxConfiguration::query()->first();
        if (! $configuration || ! $configuration->is_enabled || ! $configuration->is_published) {
            return $this->zeroQuote('configuration_inactive', 'Tax collection is not published by the merchant.');
        }

        $address = $this->normalizeAddress($address);
        if ($address['country'] === '') {
            return $this->zeroQuote('no_matching_rules', 'A delivery country is required to resolve tax rules.');
        }

        $rules = TaxRule::query()
            ->with(['taxZone', 'taxClass'])
            ->where('is_enabled', true)
            ->get()
            ->filter(fn (TaxRule $rule): bool => $rule->taxZone?->is_active && $rule->matchesAddress($address) && $rule->isEffectiveAt($at ?? now()));

        $merchandise = [];
        foreach ($items as $item) {
            $variant = $item['variant'];
            $product = $variant->relationLoaded('product') ? $variant->product : $variant->product()->first();
            $taxableAmount = max(0, (int) $variant->price * max(0, (int) $item['quantity']));
            $classId = $product?->tax_class_id;
            $rule = $classId ? $this->bestRule($rules->where('tax_class_id', $classId)) : null;

            $merchandise[] = [
                'gross_amount' => $taxableAmount,
                'rule' => $rule,
                'tax_class_id' => $classId,
            ];
        }

        $discounts = $this->allocateDiscount($merchandise, $merchandiseDiscount);
        $lines = [];
        foreach ($merchandise as $index => $item) {
            $rule = $item['rule'];
            $taxableAmount = max(0, $item['gross_amount'] - ($discounts[$index] ?? 0));
            $lines[] = $this->line('merchandise', $taxableAmount, $rule, $item['tax_class_id'], $this->matchedStatus($rule));
        }

        if ($shippingAmount > 0) {
            $shippingRule = $this->bestRule($rules->where('applies_to_shipping', true));
            if ($shippingRule) {
                $lines[] = $this->line('shipping', $shippingAmount, $shippingRule, $shippingRule->tax_class_id, $this->matchedStatus($shippingRule));
            }
        }

        $tax = array_sum(array_column($lines, 'tax_amount'));
        $matched = array_values(array_filter($lines, fn (array $line): bool => $line['rule_id'] !== null));
        $allMatchedRulesAreZeroRate = $matched !== [] && collect($matched)->every(fn (array $line): bool => $line['rate_percent'] === '0.0000');

        return [
            'tax' => $tax,
            'status' => $tax > 0 ? 'chargeable' : ($matched === [] ? 'no_matching_rules' : ($allMatchedRulesAreZeroRate ? 'matched_zero_rate' : 'matched_zero_amount')),
            'reason' => $tax > 0 ? 'Published merchant tax rules matched this order.' : ($matched === [] ? 'No published merchant tax rule matched the taxable lines.' : 'Published merchant tax rules matched this order with no tax due.'),
            'lines' => $lines,
        ];
    }

    /** @param list<array{gross_amount: int, rule: ?TaxRule, tax_class_id: ?int}> $merchandise @return array<int, int> */
    private function allocateDiscount(array $merchandise, int $discount): array
    {
        $eligible = array_filter($merchandise, fn (array $item): bool => $item['rule'] !== null && $item['gross_amount'] > 0);
        $total = array_sum(array_column($eligible, 'gross_amount'));
        $discount = min(max(0, $discount), $total);
        if ($discount === 0 || $total === 0) {
            return [];
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;
        foreach ($merchandise as $index => $item) {
            if ($item['rule'] === null || $item['gross_amount'] <= 0) {
                continue;
            }

            $numerator = $discount * $item['gross_amount'];
            $allocation = intdiv($numerator, $total);
            $allocations[$index] = $allocation;
            $remainders[$index] = $numerator % $total;
            $allocated += $allocation;
        }

        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $index) {
            if ($allocated >= $discount) {
                break;
            }

            $allocations[$index]++;
            $allocated++;
        }

        return $allocations;
    }

    private function matchedStatus(?TaxRule $rule): string
    {
        if (! $rule) {
            return 'no_matching_rule';
        }

        return (string) $rule->rate_percent === '0.0000' ? 'matched_zero_rate' : 'chargeable';
    }

    /** @param Collection<int, TaxRule> $rules */
    private function bestRule(Collection $rules): ?TaxRule
    {
        return $rules->sort(function (TaxRule $left, TaxRule $right): int {
            $specificity = $this->specificity($right) <=> $this->specificity($left);
            if ($specificity !== 0) {
                return $specificity;
            }

            $priority = $left->priority <=> $right->priority;

            return $priority !== 0 ? $priority : ($left->id <=> $right->id);
        })->first();
    }

    private function specificity(TaxRule $rule): int
    {
        $zone = $rule->taxZone;

        return ($zone?->postcode !== null ? 2 : 0) + ($zone?->state !== null ? 1 : 0);
    }

    /** @param array<string, mixed>|null $address @return array{country: string, state: string, postal_code: string} */
    private function normalizeAddress(?array $address): array
    {
        return [
            'country' => strtoupper(trim((string) ($address['country'] ?? ''))),
            'state' => trim((string) ($address['state'] ?? '')),
            'postal_code' => trim((string) ($address['postal_code'] ?? $address['postcode'] ?? '')),
        ];
    }

    /** @return array{tax: int, status: string, reason: string, lines: list<array<string, mixed>>} */
    private function zeroQuote(string $status, string $reason): array
    {
        return ['tax' => 0, 'status' => $status, 'reason' => $reason, 'lines' => []];
    }

    /** @return array<string, mixed> */
    private function line(string $kind, int $taxableAmount, ?TaxRule $rule, ?int $classId, string $status): array
    {
        $rate = $rule ? (string) $rule->rate_percent : '0.0000';

        return [
            'kind' => $kind,
            'label' => $rule?->label ?? ($kind === 'shipping' ? 'Shipping' : 'Merchandise'),
            'rule_id' => $rule?->id,
            'tax_class_id' => $classId,
            'tax_class' => $rule?->taxClass?->code,
            'rate_percent' => $rate,
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $rule ? $this->taxFor($taxableAmount, $rate) : 0,
            'status' => $status,
        ];
    }

    private function taxFor(int $taxableAmount, string $ratePercent): int
    {
        $rateBasisPoints = (int) round((float) $ratePercent * 10000);

        return intdiv($taxableAmount * $rateBasisPoints, 1_000_000);
    }
}
