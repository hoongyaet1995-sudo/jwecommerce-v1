<?php

namespace App\Services\Inventory\StoreManagement;

use App\Services\Inventory\CurrentStockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CountMutation
{
    /** @return array{before:array<string,mixed>,after:array<string,mixed>} */
    public function preview(string $kind, object $balance, array $input): array
    {
        if (config('inventory.current_stock_only')) {
            $current = $balance->current_stock === null ? null : (int) $balance->current_stock;
            $target = match ($kind) {
                'count' => array_key_exists('physical_count', $input) ? (int) $input['physical_count'] : null,
                'intake' => $current === null ? null : $current + (int) ($input['quantity'] ?? 0),
                'correction' => $current === null ? null : (array_key_exists('confirmed_total', $input)
                    ? (int) $input['confirmed_total'] : $current + (int) ($input['quantity_delta'] ?? 0)),
                default => null,
            };
            if ($target === null || $target < 0 || $target > 2147483647) {
                throw ValidationException::withMessages(['current_stock' => 'Count Current Stock first and use a valid quantity.']);
            }
            if ($kind === 'correction' && $target === $current) {
                throw ValidationException::withMessages(['confirmed_total' => 'The verified total matches Current Stock; there is no adjustment to save.']);
            }

            return ['before' => ['current_stock' => $current], 'after' => ['current_stock' => $target]];
        }
        $physical = $balance->physical_stock === null ? null : (int) $balance->physical_stock;
        $reserved = $balance->reserved_stock === null ? 0 : (int) $balance->reserved_stock;
        $target = match ($kind) {
            'count' => array_key_exists('physical_count', $input) ? (int) $input['physical_count'] : null,
            'intake' => $physical === null ? null : $physical + (int) ($input['quantity'] ?? 0),
            'correction' => $physical === null ? null : $physical + (int) ($input['quantity_delta'] ?? 0),
            default => null,
        };
        if ($target === null) {
            throw ValidationException::withMessages(['physical_count' => 'Count this stock before applying a quantity change.']);
        }
        if ($target < 0 || $target > 2147483647) {
            throw ValidationException::withMessages(['physical_count' => 'The resulting physical stock is outside the allowed range.']);
        }


        return [
            'before' => $this->state($physical, $reserved, $balance->safety_stock),
            'after' => $this->state($target, $reserved, $balance->safety_stock),
        ];
    }

    /** @return array<string,mixed> */
    public function apply(object $balance, array $after, ?string $eventKey = null, ?string $reason = null): array
    {
        if (config('inventory.current_stock_only')) {
            if ($eventKey === null) {
                throw new \RuntimeException('Current Stock operation identity is required.');
            }
            $movement = app(CurrentStockLedger::class)->setAbsolute((int) $balance->id,
                (int) $after['current_stock'], $eventKey, $reason ?: 'Stock count or adjustment');

            return ['id' => (int) $balance->id, 'variant_id' => (int) $balance->product_variant_id,
                'current_stock' => $movement['after'], 'counted' => true,
                'version' => $movement['balance_version']];
        }
        $version = (int) $balance->version + 1;
        DB::table('inventory_balances')->where('id', $balance->id)->update([
            'physical_stock' => $after['physical'],
            'reserved_stock' => $after['reserved'],
            'safety_stock' => $after['safety'],
            'counted_at' => $balance->counted_at ?? now(),
            'version' => $version,
            'updated_at' => now(),
        ]);

        return ['id' => (int) $balance->id, 'variant_id' => (int) $balance->product_variant_id,
            'physical' => $after['physical'], 'reserved' => $after['reserved'],
            'available' => $after['available'], 'counted' => true, 'version' => $version];
    }

    /** @return array{physical:?int,reserved:int,safety:int,available:?int} */
    private function state(?int $physical, int $reserved, mixed $safety): array
    {
        $safe = $safety === null ? 0 : (int) $safety;

        return ['physical' => $physical, 'reserved' => $reserved, 'safety' => $safe,
            'available' => $physical === null ? null : max(0, $physical - $reserved - $safe)];
    }
}
