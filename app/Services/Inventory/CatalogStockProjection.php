<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

final class CatalogStockProjection
{
    public function refresh(int $variantId): void
    {
        $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
        $poolKey = $mode === 'independent_stores' ? 'store:website' : 'shared';
        $balance = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('balance.product_variant_id', $variantId)
            ->where('pool.pool_key', $poolKey)
            ->select('balance.*')
            ->first();
        $available = config('inventory.current_stock_only')
            ? (int) ($balance?->current_stock ?? 0)
            : ($balance && $balance->physical_stock !== null
                ? max(0, (int) $balance->physical_stock - (int) ($balance->reserved_stock ?? 0) - (int) ($balance->safety_stock ?? 0))
                : 0);

        DB::table('product_variants')->where('id', $variantId)->update([
            'stock' => $available,
            'stock_version' => DB::raw('stock_version + 1'),
            'updated_at' => now(),
        ]);
    }
}
