<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/** Narrow, read-only eligibility gate for the designated Lazada test seller. */
final class LazadaTestFacilityAcceptance
{
    public static function appliesToStore(int $storeId): bool
    {
        return config('marketplace.multiwarehouse_test_acceptance_enabled') === true
            && DB::table('marketplace_stores')->where('id', $storeId)
                ->where('platform', 'lazada')->where('account_mode', 'test')->exists();
    }

    public static function stockSyncAllowed(int $storeId): bool
    {
        return config('marketplace.multiwarehouse_stock_sync_enabled') === true
            || self::appliesToStore($storeId);
    }

    public function inspect(int $storeId, int $variantId, ?int $facilityId = null,
        ?string $ignoreSyncTaskId = null): array
    {
        $blockers = [];
        $warnings = [];
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
        if (DB::table('inventory_order_activations')->where('product_variant_id', $variantId)
            ->where('channel_key', 'marketplace:'.$storeId)->exists()
            || DB::table('inventory_store_order_lines')->where('product_variant_id', $variantId)
                ->where('reserved_quantity', '>', 0)->exists()
            || DB::table('stock_allocations')->where('product_variant_id', $variantId)
                ->where('status', 'reserved')->exists()) {
            $warnings[] = ['code' => 'active_stock_obligation'];
        }
        if ($mappings->count() === 1) {
            $listing = DB::table('marketplace_listing_variants')->find($mappings[0]->listing_variant_id);
            $selectedProviderId = $facilityId === null ? null : DB::table('marketplace_store_facilities')
                ->where('id', $facilityId)->where('marketplace_store_id', $storeId)->value('provider_facility_id');
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
                            && (string) $item['sku_id'] === (string) $listing->external_variant_id
                            && ($selectedProviderId === null || ! isset($item['warehouse_code'])
                                || (string) $item['warehouse_code'] === (string) $selectedProviderId)) {
                            $lineKey = $item['order_item_id'] ?? null;
                            $historical = $lineKey !== null && DB::table('marketplace_inventory_items')
                                ->where('order_id', $order->id)->where('line_key', (string) $lineKey)
                                ->where('external_product_id', (string) $listing->external_product_id)
                                ->where('external_variant_id', (string) $listing->external_variant_id)
                                ->where('status', 'skipped_historical')->whereNull('stock_allocation_id')->exists();
                            if (! $historical) {
                                $warnings[] = ['code' => 'open_test_store_order'];
                                break 2;
                            }
                        }
                    }
                } catch (\Throwable) {
                    $warnings[] = ['code' => 'unreadable_test_store_order'];
                    break;
                }
            }
        }
        $batch = $facilityId === null ? null : $this->countBatchFor($storeId, $variantId, $facilityId, $ignoreSyncTaskId);
        $currentSync = $facilityId === null ? null : DB::table('inventory_store_sync_tasks')
            ->where('marketplace_store_id', $storeId)
            ->where('marketplace_store_facility_id', $facilityId)
            ->where('product_variant_id', $variantId)
            ->when($batch, fn ($query) => $query->whereNotIn('event_key', $batch['event_keys']))
            ->where('status', '!=', 'superseded')
            ->orderByDesc('created_at')
            ->orderByRaw("CASE WHEN status IN ('failed', 'facility_blocked') THEN 0 ELSE 1 END")
            ->orderByDesc('id')->first();
        $currentBalanceVersion = $currentSync?->inventory_balance_id === null ? null
            : DB::table('inventory_balances')->where('id', $currentSync->inventory_balance_id)->value('version');
        if ($currentSync && $currentSync->id !== $ignoreSyncTaskId && $currentBalanceVersion !== null
            && (int) $currentSync->balance_version === (int) $currentBalanceVersion
            && in_array($currentSync->status,
                ['queued', 'waiting_source', 'facility_pending', 'processing', 'uncertain'], true)) {
            $warnings[] = ['code' => 'unfinished_stock_sync'];
        }
        $targetWarehouseId = $facilityId === null ? null : DB::table('marketplace_store_facilities')
            ->where('id', $facilityId)->where('marketplace_store_id', $storeId)
            ->value('inventory_warehouse_id');
        if (DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('balance.product_variant_id', $variantId)
            ->where(function ($query) use ($batch, $storeId, $facilityId, $targetWarehouseId) {
                $query->where(function ($warehouse) use ($facilityId, $targetWarehouseId) {
                    $warehouse->where('pool.kind', 'warehouse');
                    if ($facilityId !== null) {
                        $warehouse->where('pool.warehouse_id', $targetWarehouseId ?? -1);
                    }
                    $warehouse->where(fn ($balance) => $balance->whereNotNull('balance.counted_at')
                        ->orWhere('balance.physical_stock', '>', 0)
                        ->orWhere('balance.reserved_stock', '>', 0));
                })->orWhere(function ($shared) {
                    $shared->where('pool.kind', 'shared')
                        ->where(fn ($balance) => $balance->where('balance.physical_stock', '>', 0)
                            ->orWhere('balance.reserved_stock', '>', 0));
                });
                if (! $batch) {
                    $query->orWhere(function ($storeQuery) use ($storeId) {
                        $storeQuery->where('pool.kind', 'store')
                            ->where('pool.marketplace_store_id', $storeId)
                            ->where(fn ($balance) => $balance->where('balance.physical_stock', '>', 0)
                                ->orWhere('balance.reserved_stock', '>', 0));
                    });
                }
            })
            ->exists()) {
            $warnings[] = ['code' => 'legacy_stock_present'];
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
            'eligible' => $blockers === [], 'blockers' => $blockers, 'warnings' => $warnings,
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
        $resyncTask = null;
        if ($taskId !== null) {
            $task = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->first();
            $eventKey = $task?->event_key;
            if (! is_string($eventKey)) return null;
            if (str_starts_with($eventKey, 'facility-stock:')) {
                $taskReviewId = substr($eventKey, strlen('facility-stock:'));
            } elseif (str_starts_with($eventKey, 'facility-resync:')) {
                $resyncTask = $task;
            } else {
                return null;
            }
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
            if ($resyncTask) {
                $child = collect($children)->firstWhere('key', 'facility:'.$storeId.':'.$facilityId);
                $facilityReview = DB::table('marketplace_facility_stock_reviews')->where('id', $child['id'])->first();
                $receipt = json_decode($facilityReview?->receipt ?? '{}', true) ?: [];
                if ($facilityReview?->status !== 'confirmed'
                    || (int) ($receipt['balance_id'] ?? 0) !== (int) $resyncTask->inventory_balance_id
                    || (int) ($receipt['balance_version'] ?? -1) !== (int) $resyncTask->balance_version) {
                    continue;
                }
            }
            return ['event_keys' => array_map(fn ($child) => 'facility-stock:'.$child['id'],
                array_values(array_filter($children, fn ($child) => ($child['kind'] ?? null) === 'facility')))];
        }
        return null;
    }
}
