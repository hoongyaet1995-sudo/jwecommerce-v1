<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

/** Read projection only. This is not authority to activate a pool or push stock. */
class PhysicalBalanceReader
{
    public function read(int $poolId, int $variantId): ?array
    {
        $row = DB::table('inventory_balances')->where('inventory_pool_id', $poolId)
            ->where('product_variant_id', $variantId)->first();
        if (!$row) return null;
        return $this->project($row);
    }

    public function project(object $row): array
    {
        $physical = $row->physical_stock === null ? null : (int) $row->physical_stock;
        $reserved = $row->reserved_stock === null ? null : (int) $row->reserved_stock;
        $safety = $row->safety_stock === null ? null : (int) $row->safety_stock;
        $verified = $physical !== null && $reserved !== null && $safety !== null
            && $physical >= 0 && $reserved >= 0 && $safety >= 0
            && $row->counted_at !== null && $row->obligations_reconciled_at !== null;
        $net = $verified ? $physical - $reserved - $safety : null;

        return [
            'balance_id' => (int) $row->id,
            'pool_id' => (int) $row->inventory_pool_id,
            'variant_id' => (int) $row->product_variant_id,
            'physical_stock' => $physical,
            'reserved_stock' => $reserved,
            'safety_stock' => $safety,
            'verified' => $verified,
            'atp' => $net === null ? null : max(0, $net),
            'shortfall' => $net === null ? null : max(0, -$net),
            'version' => (int) $row->version,
            'counted_at' => $row->counted_at,
            'obligations_reconciled_at' => $row->obligations_reconciled_at,
        ];
    }
}
