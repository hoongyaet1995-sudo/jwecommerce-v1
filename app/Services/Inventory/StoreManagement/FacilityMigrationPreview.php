<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class FacilityMigrationPreview
{
    public function __construct(private readonly FacilityRegistry $registry) {}

    /** @param array<string, int> $physicalByProviderId */
    public function preview(int $storeId, int $variantId, array $physicalByProviderId): array
    {
        abort_unless(DB::table('marketplace_stores')->where('id', $storeId)
            ->whereIn('platform', ['shopee', 'lazada'])->exists(), 404);
        abort_unless(DB::table('product_variants')->where('id', $variantId)->exists(), 404);

        $scan = $this->registry->read($storeId);
        $blockers = [];
        if ($scan['status'] !== 'verified' || ! in_array($scan['topology'], ['single_warehouse', 'multi_warehouse'], true)) {
            $blockers[] = 'facility_topology_unverified';
        }
        $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
        if ($mode !== 'independent_stores') {
            $blockers[] = 'shared_mode_requires_global_migration_review';
        }
        if (! DB::table('marketplace_variant_mappings')->where('store_id', $storeId)
            ->where('product_variant_id', $variantId)->exists()) {
            $blockers[] = 'sku_unmapped';
        }
        $source = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'store')->where('pool.marketplace_store_id', $storeId)
            ->where('balance.product_variant_id', $variantId)
            ->first(['balance.id', 'balance.physical_stock', 'balance.reserved_stock',
                'balance.safety_stock', 'balance.counted_at', 'balance.version']);
        if (! $source || $source->counted_at === null || $source->physical_stock === null
            || $source->reserved_stock === null || $source->safety_stock === null) {
            $blockers[] = 'legacy_balance_not_verified';
        }

        $facilityIds = array_column($scan['facilities'], 'provider_id');
        if (array_diff($facilityIds, array_keys($physicalByProviderId)) !== []
            || array_diff(array_keys($physicalByProviderId), $facilityIds) !== []
            || count($physicalByProviderId) !== count($facilityIds)
            || collect($physicalByProviderId)->contains(fn ($quantity): bool => ! is_int($quantity) || $quantity < 0)) {
            $blockers[] = 'facility_allocation_incomplete';
        }
        $allocated = collect($physicalByProviderId)->filter(fn ($quantity): bool => is_int($quantity) && $quantity >= 0)->sum();
        if ($source && $source->physical_stock !== null && $allocated !== (int) $source->physical_stock) {
            $blockers[] = 'physical_total_mismatch';
        }

        $warehouseIds = [];
        foreach ($scan['facilities'] as $facility) {
            $warehouseId = $facility['inventory_warehouse_id'];
            if ($warehouseId === null || in_array($warehouseId, $warehouseIds, true)
                || ! DB::table('inventory_warehouses')->where('id', $warehouseId)->where('is_active', true)->exists()) {
                $blockers[] = 'physical_facility_unlinked';

                continue;
            }
            $warehouseIds[] = $warehouseId;
        }
        if ($warehouseIds !== []) {
            if (DB::table('inventory_pools')->whereIn('warehouse_id', $warehouseIds)
                ->where('kind', '!=', 'facility')->exists()) {
                $blockers[] = 'physical_owner_conflict';
            }
            $occupied = DB::table('inventory_balances as balance')
                ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                ->where('pool.kind', 'facility')->whereIn('pool.warehouse_id', $warehouseIds)
                ->where('balance.product_variant_id', $variantId)->whereNotNull('balance.counted_at')->exists();
            if ($occupied) {
                $blockers[] = 'physical_owner_already_counted';
            }
        }
        $reserved = (int) ($source?->reserved_stock ?? 0);
        if ((int) ($source?->safety_stock ?? 0) > 0) {
            $blockers[] = 'safety_stock_unassigned';
        }
        if ($source && $source->physical_stock !== null
            && (int) $source->physical_stock < $reserved + (int) $source->safety_stock) {
            $blockers[] = 'source_ledger_inconsistent';
        }
        if ($reserved > 0 || ($source && DB::table('inventory_store_order_lines')
            ->where('inventory_balance_id', $source->id)->where('reserved_quantity', '>', 0)->exists())) {
            $blockers[] = 'order_reservations_unassigned';
        }
        if ($source && DB::table('inventory_order_activations')
            ->where('channel_key', 'marketplace:'.$storeId)
            ->where('product_variant_id', $variantId)
            ->where('inventory_balance_id', $source->id)->exists()) {
            $blockers[] = 'legacy_order_activation_active';
        }
        if (DB::table('inventory_store_sync_tasks')->where('marketplace_store_id', $storeId)
            ->where('product_variant_id', $variantId)
            ->whereNotIn('status', ['confirmed', 'not_applicable', 'superseded'])->exists()) {
            $blockers[] = 'pending_provider_sync';
        }

        return [
            'ready' => $blockers === [], 'blockers' => array_values(array_unique($blockers)),
            'store_id' => $storeId, 'variant_id' => $variantId,
            'topology_revision' => $scan['revision'],
            'source' => ['balance_id' => $source?->id, 'physical' => $source?->physical_stock === null ? null : (int) $source->physical_stock,
                'version' => $source?->version === null ? null : (int) $source->version],
            'allocated_physical' => (int) $allocated, 'reserved' => $reserved,
        ];
    }
}
