<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class FacilityBalanceReader
{
    public function __construct(private readonly FacilityRegistry $registry) {}

    public function read(int $storeId, int $variantId): array
    {
        abort_unless(DB::table('marketplace_stores')->where('id', $storeId)
            ->whereIn('platform', ['shopee', 'lazada'])->exists(), 404);
        abort_unless(DB::table('product_variants')->where('id', $variantId)->exists(), 404);

        $result = $this->registry->read($storeId);
        $warehouseIds = collect($result['facilities'])->pluck('inventory_warehouse_id')->filter()->unique()->all();
        $balances = DB::table('inventory_pools as pool')
            ->join('inventory_warehouses as warehouse', 'warehouse.id', '=', 'pool.warehouse_id')
            ->leftJoin('inventory_balances as balance', function ($join) use ($variantId): void {
                $join->on('balance.inventory_pool_id', '=', 'pool.id')
                    ->where('balance.product_variant_id', '=', $variantId);
            })
            ->where('pool.kind', 'facility')->whereIn('pool.warehouse_id', $warehouseIds)
            ->get(['pool.warehouse_id', 'warehouse.is_active', 'balance.id as balance_id',
                'balance.physical_stock', 'balance.reserved_stock', 'balance.safety_stock',
                'balance.counted_at', 'balance.version'])->keyBy('warehouse_id');

        $knownPhysical = 0;
        $knownReserved = 0;
        $knownAvailable = 0;
        $unknown = 0;
        $result['facilities'] = array_map(function (array $facility) use ($balances, &$knownPhysical,
            &$knownReserved, &$knownAvailable, &$unknown): array {
            $balance = $balances->get($facility['inventory_warehouse_id']);
            $counted = $balance && (bool) $balance->is_active && $balance->counted_at !== null
                && $balance->physical_stock !== null && $balance->reserved_stock !== null
                && $balance->safety_stock !== null;
            if (! $counted) {
                $unknown++;

                return $facility + ['balance_id' => null, 'physical' => null, 'reserved' => null,
                    'available' => null, 'counted' => false, 'balance_version' => null];
            }
            $physical = (int) $balance->physical_stock;
            $reserved = (int) $balance->reserved_stock;
            $available = max(0, $physical - $reserved - (int) $balance->safety_stock);
            $knownPhysical += $physical;
            $knownReserved += $reserved;
            $knownAvailable += $available;

            return $facility + ['balance_id' => (int) $balance->balance_id, 'physical' => $physical,
                'reserved' => $reserved, 'available' => $available, 'counted' => true,
                'balance_version' => (int) $balance->version];
        }, $result['facilities']);
        $result['variant_id'] = $variantId;
        $result['totals'] = ['known_physical' => $knownPhysical, 'known_reserved' => $knownReserved,
            'known_available' => $knownAvailable, 'unknown_facilities' => $unknown];

        return $result;
    }
}
