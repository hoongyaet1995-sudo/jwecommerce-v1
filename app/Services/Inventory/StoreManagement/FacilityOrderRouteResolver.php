<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

/** Read-only routing decision; marketplace ingestion owns its order exception and ledger mutation. */
final class FacilityOrderRouteResolver
{
    public function resolve(int $storeId, int $variantId, string $providerFacilityId): array
    {
        $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
        if (! $store || ! in_array($store->platform, ['lazada', 'shopee'], true) || $store->status !== 'connected') {
            return ['status' => 'store_not_connected'];
        }
        $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->first();
        if (! $scan || $scan->status !== 'verified') {
            return ['status' => 'warehouse_verification_pending'];
        }
        if ($scan->ledger_migration_status !== 'complete') {
            return ['status' => 'facility_ledger_not_ready'];
        }
        if (! DB::table('marketplace_variant_mappings')->where('store_id', $storeId)
            ->where('product_variant_id', $variantId)->exists()) {
            return ['status' => 'sku_unmapped'];
        }
        $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
            ->where('provider_facility_id', $providerFacilityId)->where('is_active', true)->first();
        if (! $facility) {
            return ['status' => 'warehouse_unmatched'];
        }
        if ($facility->inventory_warehouse_id === null) {
            return ['status' => 'physical_stock_unknown'];
        }
        $balance = DB::table('inventory_warehouses as warehouse')
            ->join('inventory_pools as pool', 'pool.warehouse_id', '=', 'warehouse.id')
            ->join('inventory_balances as balance', 'balance.inventory_pool_id', '=', 'pool.id')
            ->where('warehouse.id', $facility->inventory_warehouse_id)
            ->where('warehouse.is_active', true)->where('warehouse.status', 'active')
            ->where('pool.kind', 'facility')->where('balance.product_variant_id', $variantId)
            ->first(['balance.id', 'balance.physical_stock', 'balance.reserved_stock',
                'balance.safety_stock', 'balance.counted_at', 'balance.version']);
        if (! $balance || $balance->counted_at === null || $balance->physical_stock === null
            || $balance->reserved_stock === null || $balance->safety_stock === null) {
            return ['status' => 'physical_stock_unknown'];
        }

        return ['status' => 'matched', 'store_id' => $storeId, 'variant_id' => $variantId,
            'provider_facility_id' => $providerFacilityId, 'facility_id' => (int) $facility->id,
            'warehouse_id' => (int) $facility->inventory_warehouse_id, 'balance_id' => (int) $balance->id,
            'balance_version' => (int) $balance->version,
            'available' => max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock)];
    }
}
