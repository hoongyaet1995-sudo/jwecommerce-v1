<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Read provider snapshots for one uncounted Shared Pool SKU; never mutate stock. */
final class SharedFirstCountMarketplaceRead
{
    public function __construct(private readonly MarketplaceItemStockSnapshot $snapshots) {}

    public function read(User $actor, int $variantId): array
    {
        abort_unless($actor->exists && $actor->is_admin
            && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
        abort_unless(config('inventory.current_stock_only')
            && DB::table('inventory_configuration')->where('id', 1)->value('operating_mode') === 'shared_pool', 404);
        abort_unless(DB::table('product_variants')->where('id', $variantId)->exists(), 404);

        $mappings = DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_stores as store', 'store.id', '=', 'mapping.store_id')
            ->join('marketplace_listing_variants as listing', function ($join): void {
                $join->on('listing.id', '=', 'mapping.listing_variant_id')
                    ->on('listing.store_id', '=', 'store.id');
            })
            ->leftJoin('inventory_store_accounts as account', 'account.marketplace_store_id', '=', 'store.id')
            ->where('mapping.product_variant_id', $variantId)
            ->whereIn('store.platform', ['shopee', 'lazada'])
            ->orderBy('store.id')
            ->get(['store.id', 'store.platform', 'store.shop_name', 'store.status', 'store.external_shop_id',
                'account.shared_pool_takeover_enabled', 'listing.external_product_id', 'listing.external_variant_id']);

        $rows = [];
        foreach ($mappings->groupBy('id') as $storeMappings) {
            $store = $storeMappings->first();
            $row = ['store_id' => (int) $store->id, 'name' => $store->shop_name,
                'platform' => $store->platform, 'connected' => $store->status === 'connected',
                'takeover_enabled' => (bool) $store->shared_pool_takeover_enabled,
                'status' => 'unavailable', 'quantity' => null, 'warehouses' => [], 'observed_at' => null];
            if ($storeMappings->count() !== 1) {
                $row['reason'] = 'duplicate_mapping';
                $rows[] = $row;
                continue;
            }
            if ($store->status !== 'connected') {
                $row['reason'] = 'store_disconnected';
                $rows[] = $row;
                continue;
            }

            $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $store->id)->first();
            $warehouseMode = $store->platform === 'lazada' && $scan?->status === 'verified'
                && in_array($scan?->topology, ['single_warehouse', 'multi_warehouse'], true);
            $facilities = $warehouseMode ? DB::table('marketplace_store_facilities')
                ->where('marketplace_store_id', $store->id)->where('is_active', true)
                ->where('shared_pool_takeover_enabled', true)->orderBy('id')
                ->get(['id', 'name', 'provider_facility_id']) : collect();
            $row['warehouses'] = $facilities->map(fn (object $facility): array => [
                'id' => (int) $facility->id, 'name' => $facility->name,
            ])->all();
            $row['warehouse_mode'] = $warehouseMode;
            if ($warehouseMode && $facilities->isEmpty()) {
                $row['reason'] = 'no_takeover_warehouses';
                $rows[] = $row;
                continue;
            }
            try {
                $snapshot = $this->snapshots->read($store, (string) $store->external_product_id);
                $quantity = 0;
                if ($warehouseMode) {
                    foreach ($facilities as $facility) {
                        if ($this->snapshots->notApplicable($snapshot, $store, $facility, $store->platform)) {
                            throw new \RuntimeException('Mapped SKU is not applicable to a selected warehouse.');
                        }
                        $quantity += $this->snapshots->quantity($snapshot, $store, $facility, $store->platform);
                    }
                } else {
                    $quantity = $this->snapshots->quantity($snapshot, $store, null, $store->platform);
                }
                $row['status'] = 'read';
                $row['quantity'] = $quantity;
                $row['observed_at'] = $snapshot['observed_at'];
            } catch (\Throwable) {
                $row['reason'] = 'provider_read_failed';
            }
            $rows[] = $row;
        }

        return ['variant_id' => $variantId, 'stores' => $rows,
            'available_total' => array_sum(array_column($rows, 'quantity')),
            'all_read' => ! collect($rows)->contains(fn (array $row): bool => $row['status'] !== 'read')];
    }
}
