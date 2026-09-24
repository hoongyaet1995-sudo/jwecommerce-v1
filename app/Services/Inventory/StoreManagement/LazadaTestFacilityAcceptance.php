<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/** Narrow, read-only eligibility gate for the designated Lazada test seller. */
final class LazadaTestFacilityAcceptance
{
    public function inspect(int $storeId, int $variantId, ?int $facilityId = null,
        ?string $ignoreSyncTaskId = null): array
    {
        $blockers = [];
        if (config('marketplace.multiwarehouse_test_acceptance_enabled') !== true) {
            $blockers[] = ['code' => 'test_acceptance_disabled'];
        }
        $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
        if (! $store || $store->platform !== 'lazada' || $store->account_mode !== 'test'
            || $store->data_classification !== 'test'
            || (string) $store->external_shop_id !== '300163725140'
            || (string) config('marketplace.lazada.test_store_id') !== (string) $storeId
            || $store->status !== 'connected') {
            $blockers[] = ['code' => 'test_seller_not_approved'];
        }
        $scan = DB::table('marketplace_store_facility_scans')
            ->where('marketplace_store_id', $storeId)->first();
        if (! $scan || $scan->status !== 'verified'
            || ! in_array($scan->topology, ['single_warehouse', 'multi_warehouse'], true)) {
            $blockers[] = ['code' => 'provider_topology_unverified'];
        }
        $mappings = DB::table('marketplace_variant_mappings')->where('store_id', $storeId)
            ->where('product_variant_id', $variantId)->get();
        if ($mappings->count() !== 1) {
            $blockers[] = ['code' => 'mapping_missing'];
        }
        if (DB::table('marketplace_variant_mappings')->where('product_variant_id', $variantId)
            ->where('store_id', '!=', $storeId)->exists()) {
            $blockers[] = ['code' => 'sku_shared_with_other_store'];
        }
        if (DB::table('inventory_order_activations')->where('product_variant_id', $variantId)->exists()
            || DB::table('inventory_store_order_lines')->where('product_variant_id', $variantId)
                ->where('reserved_quantity', '>', 0)->exists()
            || DB::table('stock_allocations')->where('product_variant_id', $variantId)
                ->where('status', 'reserved')->exists()) {
            $blockers[] = ['code' => 'active_stock_obligation'];
        }
        if ($mappings->count() === 1) {
            $listing = DB::table('marketplace_listing_variants')->find($mappings[0]->listing_variant_id);
            foreach (DB::table('marketplace_orders')->where('store_id', $storeId)
                ->whereNotIn('platform_status', ['cancelled', 'canceled', 'delivered', 'returned', 'refunded'])
                ->select('id', 'encrypted_payload')->cursor() as $order) {
                try {
                    $payload = json_decode(Crypt::decryptString($order->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
                    $items = $payload['order_items'] ?? null;
                    if (! is_array($items) || ! array_is_list($items) || $items === []) {
                        throw new \UnexpectedValueException('Missing order lines');
                    }
                    foreach ($items as $item) {
                        if (! is_array($item) || ! isset($item['product_id'], $item['sku_id'])) {
                            throw new \UnexpectedValueException('Incomplete order line');
                        }
                        if ($listing && (string) $item['product_id'] === (string) $listing->external_product_id
                            && (string) $item['sku_id'] === (string) $listing->external_variant_id) {
                            $lineKey = $item['order_item_id'] ?? null;
                            $historical = $lineKey !== null && DB::table('marketplace_inventory_items')
                                ->where('order_id', $order->id)->where('line_key', (string) $lineKey)
                                ->where('external_product_id', (string) $listing->external_product_id)
                                ->where('external_variant_id', (string) $listing->external_variant_id)
                                ->where('status', 'skipped_historical')->whereNull('stock_allocation_id')->exists();
                            if (! $historical) {
                                $blockers[] = ['code' => 'open_test_store_order'];
                                break 2;
                            }
                        }
                    }
                } catch (\Throwable) {
                    $blockers[] = ['code' => 'unreadable_test_store_order'];
                    break;
                }
            }
        }
        $batch = $facilityId === null ? null : $this->countBatchFor($storeId, $variantId, $facilityId, $ignoreSyncTaskId);
        if ($facilityId !== null && DB::table('inventory_store_sync_tasks')
            ->where('marketplace_store_id', $storeId)
            ->where('marketplace_store_facility_id', $facilityId)
            ->where('product_variant_id', $variantId)
            ->when($ignoreSyncTaskId, fn ($query) => $query->where('id', '!=', $ignoreSyncTaskId))
            ->when($batch, fn ($query) => $query->whereNotIn('event_key', $batch['event_keys']))
            ->whereNotIn('status', ['confirmed', 'not_applicable', 'superseded'])->exists()) {
            $blockers[] = ['code' => 'unfinished_stock_sync'];
        }
        if (DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('balance.product_variant_id', $variantId)
            ->whereIn('pool.kind', $batch ? ['warehouse', 'shared'] : ['warehouse', 'store', 'shared'])
            ->where(fn ($query) => $query->whereNotNull('balance.counted_at')
                ->orWhere('balance.physical_stock', '>', 0)
                ->orWhere('balance.reserved_stock', '>', 0))->exists()) {
            $blockers[] = ['code' => 'legacy_stock_present'];
        }

        $facilities = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
            ->where('is_active', true)->orderBy('id')->get();
        $rows = [];
        foreach ($facilities as $facility) {
            $warehouse = $facility->inventory_warehouse_id === null ? null
                : DB::table('inventory_warehouses')->where('id', $facility->inventory_warehouse_id)->first();
            $balance = $warehouse ? DB::table('inventory_pools as pool')
                ->join('inventory_balances as balance', 'balance.inventory_pool_id', '=', 'pool.id')
                ->where('pool.kind', 'facility')->where('pool.warehouse_id', $warehouse->id)
                ->where('balance.product_variant_id', $variantId)->first(['balance.*']) : null;
            $facilityBlockers = [];
            if (! $warehouse || ! $warehouse->is_active || $warehouse->status !== 'active') {
                $facilityBlockers[] = ['code' => 'physical_facility_unlinked'];
            }
            if ($warehouse && DB::table('marketplace_store_facilities')->where('inventory_warehouse_id', $warehouse->id)
                ->where('id', '!=', $facility->id)->where('is_active', true)->exists()) {
                $facilityBlockers[] = ['code' => 'physical_facility_shared'];
            }
            $rows[] = ['id' => (int) $facility->id,
                'provider_id' => $facility->provider_facility_id,
                'inventory_warehouse_id' => $warehouse?->id,
                'counted' => $balance?->counted_at !== null && $balance?->physical_stock !== null,
                'eligible' => $facilityBlockers === [], 'blockers' => $facilityBlockers];
        }
        if ($facilityId !== null) {
            $selected = collect($rows)->firstWhere('id', $facilityId);
            if (! $selected) {
                $blockers[] = ['code' => 'facility_not_in_store'];
            } else {
                $blockers = array_merge($blockers, $selected['blockers']);
            }
        }

        return ['test_acceptance_enabled' => config('marketplace.multiwarehouse_test_acceptance_enabled') === true,
            'eligible' => $blockers === [], 'blockers' => $blockers,
            'store_id' => $storeId, 'variant_id' => $variantId,
            'topology_revision' => (int) ($scan?->revision ?? 0),
            'mapping_id' => $mappings->count() === 1 ? (int) $mappings[0]->id : null,
            'facilities' => $rows];
    }

    public function assertEligible(int $storeId, int $variantId, int $facilityId,
        ?string $ignoreSyncTaskId = null): array
    {
        $result = $this->inspect($storeId, $variantId, $facilityId, $ignoreSyncTaskId);
        if (! $result['eligible']) {
            throw ValidationException::withMessages(['inventory' => array_column($result['blockers'], 'code')]);
        }

        return $result;
    }

    /** A confirmed batch already reviewed every destination before any child sync was queued. */
    private function countBatchFor(int $storeId, int $variantId, int $facilityId, ?string $taskId): ?array
    {
        $taskReviewId = null;
        if ($taskId !== null) {
            $eventKey = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->value('event_key');
            if (! is_string($eventKey) || ! str_starts_with($eventKey, 'facility-stock:')) return null;
            $taskReviewId = substr($eventKey, strlen('facility-stock:'));
        }
        $reviews = DB::table('inventory_store_reviews')->where('kind', 'batch_first_count')
            ->whereIn('status', $taskId === null ? ['confirming'] : ['confirmed'])
            ->orderByDesc('created_at')->limit(100)->get(['payload']);
        foreach ($reviews as $review) {
            $payload = json_decode($review->payload, true);
            if ((int) ($payload['variant_id'] ?? 0) !== $variantId) continue;
            $children = $payload['subreviews'] ?? [];
            $matchesFacility = collect($children)->contains(fn ($child) =>
                ($child['key'] ?? null) === 'facility:'.$storeId.':'.$facilityId
                && ($taskReviewId === null || ($child['id'] ?? null) === $taskReviewId));
            if (! $matchesFacility) continue;
            return ['event_keys' => array_map(fn ($child) => 'facility-stock:'.$child['id'],
                array_values(array_filter($children, fn ($child) => ($child['kind'] ?? null) === 'facility')))];
        }
        return null;
    }
}
