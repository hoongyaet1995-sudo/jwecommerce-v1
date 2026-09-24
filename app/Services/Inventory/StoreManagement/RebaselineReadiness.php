<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class RebaselineReadiness
{
    public function __construct(private readonly FacilityRegistry $registry) {}

    public function inspect(): array
    {
        $configuration = DB::table('inventory_configuration')->where('id', 1)->first();
        $blockers = [];
        $stores = [];
        $destinations = 0;

        foreach (DB::table('marketplace_stores')->where('status', 'connected')
            ->whereIn('platform', ['shopee', 'lazada'])->orderBy('id')->get() as $store) {
            $storeId = (int) $store->id;
            $scan = $this->registry->read($storeId);
            $stores[] = ['store_id' => $storeId, 'topology' => $scan['topology'],
                'topology_status' => $scan['status'], 'topology_revision' => $scan['revision']];
            if ($scan['status'] !== 'verified' || ! in_array($scan['topology'], ['no_warehouse', 'single_warehouse', 'multi_warehouse'], true)) {
                $blockers[] = ['code' => 'provider_topology_unverified', 'store_id' => $storeId];
            }

            $mappingCount = DB::table('marketplace_variant_mappings')->where('store_id', $storeId)->count();
            $routeCount = $scan['topology'] === 'no_warehouse' ? 1 : count($scan['facilities']);
            $destinations += $mappingCount * $routeCount;

            if ($scan['status'] === 'verified' && $scan['topology'] !== 'no_warehouse') {
                foreach ($scan['facilities'] as $facility) {
                    if ($facility['inventory_warehouse_id'] === null) {
                        $blockers[] = ['code' => 'physical_facility_unlinked', 'store_id' => $storeId,
                            'provider_facility_id' => $facility['provider_id']];
                    }
                }
            }

            $unmapped = DB::table('marketplace_listing_variants as listing')
                ->leftJoin('marketplace_variant_mappings as mapping', 'mapping.listing_variant_id', '=', 'listing.id')
                ->where('listing.store_id', $storeId)->whereNull('mapping.id')->count();
            if ($unmapped > 0) {
                $blockers[] = ['code' => 'unmapped_destination', 'store_id' => $storeId, 'count' => $unmapped];
            }
        }

        $open = DB::table('inventory_balances')->where('reserved_stock', '>', 0)->count()
            + DB::table('inventory_store_order_lines')->where('reserved_quantity', '>', 0)->count()
            + DB::table('stock_allocations')->where('status', 'reserved')->count()
            + DB::table('marketplace_orders as order')
                ->leftJoin('marketplace_order_inventory as inventory', 'inventory.order_id', '=', 'order.id')
                ->whereNull('inventory.terminal_status')
                ->whereNotIn('order.platform_status', ['cancelled', 'canceled', 'delivered', 'returned', 'refunded'])
                ->count();
        if ($open > 0) {
            $blockers[] = ['code' => 'open_order_obligation', 'count' => $open];
        }
        $pending = DB::table('inventory_store_sync_tasks')
            ->whereNotIn('status', ['confirmed', 'not_applicable', 'superseded'])->count();
        if ($pending > 0) {
            $blockers[] = ['code' => 'pending_sync', 'count' => $pending];
        }
        $counted = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'facility')->whereNotNull('balance.counted_at')->count();
        if ($counted > 0) {
            $blockers[] = ['code' => 'facility_owner_already_counted', 'count' => $counted];
        }
        $transfers = DB::table('inventory_store_operations')->where('kind', 'transfer')
            ->where('ledger_status', '!=', 'applied')->count();
        if ($transfers > 0) {
            $blockers[] = ['code' => 'unfinished_transfer', 'count' => $transfers];
        }
        if ($configuration?->active_rebaseline_epoch_id !== null) {
            $blockers[] = ['code' => 'rebaseline_already_active'];
        }

        return ['ready' => $blockers === [], 'blockers' => $blockers,
            'configuration_version' => (int) ($configuration?->version ?? 0),
            'stores' => $stores, 'provider_destination_count' => $destinations,
            'open_order_obligation_count' => $open, 'pending_sync_count' => $pending];
    }
}
