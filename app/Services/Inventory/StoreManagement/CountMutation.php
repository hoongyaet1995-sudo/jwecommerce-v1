<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CountMutation
{
    /** @return array{before:array<string,mixed>,after:array<string,mixed>} */
    public function preview(string $kind, object $balance, array $input): array
    {
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
        if ($target < $reserved) {
            throw ValidationException::withMessages(['physical_count' => 'Physical stock cannot be lower than stock reserved by active orders.']);
        }

        return [
            'before' => $this->state($physical, $reserved, $balance->safety_stock),
            'after' => $this->state($target, $reserved, $balance->safety_stock),
        ];
    }

    /** @return array<string,mixed> */
    public function apply(object $balance, array $after): array
    {
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
