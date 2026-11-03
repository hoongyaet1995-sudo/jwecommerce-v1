<?php

namespace App\Services\Promotions;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/** Historical minor-unit amounts; never consults current offers or product prices. */
final class OrderPromotionAmounts
{
    public function snapshot(array $summary, Collection $items): array
    {
        $snapshot = $summary['promotion_snapshot'];
        $gross = [];
        foreach ($items as $item) {
            $gross[(string) $item['variant']->id] = (int) $item['variant']->price * (int) $item['quantity'];
        }
        $discounts = in_array($snapshot['type'], ['mix_match','addon'], true)
            ? $snapshot['allocations'] : $this->spread((int) $summary['discount'], $gross);
        $net = [];
        foreach ($gross as $key => $amount) $net[$key] = $amount - ($discounts[$key] ?? 0);
        $tax = $this->spread((int) $summary['tax'], $net);
        foreach ($items as $item) {
            $key = (string) $item['variant']->id;
            $snapshot['lines'][$key] = ['quantity' => (int) $item['quantity'],
                'gross' => $gross[$key], 'discount' => $discounts[$key] ?? 0,
                'net' => $net[$key], 'tax' => $tax[$key] ?? 0];
        }
        return $snapshot;
    }

    public function itemAmount(Order $order, OrderItem $item, int $quantity, int $offset = 0): ?int
    {
        $line = $order->promotion_snapshot['lines'][(string) $item->product_variant_id] ?? null;
        if (!$line) return null; // Preserved pre-snapshot orders retain their existing rules.
        if ($quantity < 1 || $offset < 0 || $quantity + $offset > $line['quantity']) {
            throw new \InvalidArgumentException('Invalid historical item quantity.');
        }
        $paid = $line['net'] + $line['tax'];
        return intdiv($paid * ($offset + $quantity), $line['quantity'])
            - intdiv($paid * $offset, $line['quantity']);
    }

    private function spread(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        $result = array_fill_keys(array_keys($weights), 0);
        if ($total <= 0) return $result;
        $remainders = [];
        foreach ($weights as $key => $weight) {
            $result[$key] = intdiv($amount * $weight, $total);
            $remainders[$key] = ($amount * $weight) % $total;
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $amount - array_sum($result);
        foreach ($remainders as $key => $_) { if ($left-- <= 0) break; $result[$key]++; }
        return $result;
    }
}
