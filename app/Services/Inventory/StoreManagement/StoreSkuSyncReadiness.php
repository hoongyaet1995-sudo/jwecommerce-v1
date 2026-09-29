<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class StoreSkuSyncReadiness
{
    public function __construct(
        private readonly StockSyncPolicy $policy,
    ) {}

    /** @return array<string, mixed> */
    public function read(int $storeId, int $variantId): array
    {
        $store = DB::table('marketplace_stores')->find($storeId);
        $blockers = [];
        $mappingRows = collect();

        if (! $store || ! in_array($store->platform ?? null, ['shopee', 'lazada'], true)) {
            $blockers[] = $this->blocker('store_not_found', 'Choose a connected Shopee or Lazada store.');
        } else {
            $mappingRows = DB::table('marketplace_variant_mappings as mapping')
                ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
                ->where('mapping.store_id', $storeId)
                ->where('mapping.product_variant_id', $variantId)
                ->get(['mapping.id as mapping_id', 'listing.*']);

            if ($mappingRows->isEmpty()) {
                $blockers[] = $this->blocker('sku_not_mapped', 'Map this store listing to the JW SKU.');
            } elseif ($mappingRows->count() > 1) {
                $blockers[] = $this->blocker('duplicate_sku_mapping', 'Keep exactly one active listing mapping for this store SKU.');
            }

            $listing = $mappingRows->count() === 1 ? $mappingRows->first() : null;
            if ($listing && ! $this->participates($listing)) {
                $blockers[] = $this->blocker('listing_not_participating', 'Activate the mapped listing before stock sync.');
            }

            $token = DB::table('marketplace_store_tokens')->where('store_id', $storeId)->first();
            if (! $token) {
                $blockers[] = $this->blocker('authorization_required', 'Connect the store account.');
            } elseif ($token->credential_source === 'official_test' &&
                ($token->test_lease_expires_at === null || now()->gte($token->test_lease_expires_at))) {
                $blockers[] = $this->blocker('authorization_expired', 'Renew the store connection before synchronizing.');
            } elseif ($token->credential_source !== 'official_test' && $token->access_expires_at !== null && now()->gte($token->access_expires_at)
                && (! $token->refresh_token || ($token->refresh_expires_at !== null && now()->gte($token->refresh_expires_at)))) {
                $blockers[] = $this->blocker('authorization_expired', 'Reconnect the store account.');
            }

            $account = DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->first();
            if (($account?->management_status ?? null) === 'paused') {
                $blockers[] = $this->blocker('sync_paused', 'Resume inventory sync for this store.');
            }

            $balance = $this->balance($storeId, $variantId);
            if (! $balance || (config('inventory.current_stock_only')
                ? $balance->current_stock === null
                : ($balance->counted_at === null || $balance->physical_stock === null
                    || $balance->reserved_stock === null || $balance->safety_stock === null))) {
                $blockers[] = $this->blocker('initial_count_required', 'Complete the first count for this store SKU.');

            }

            if ($listing) {
                $policy = $this->policy->review($store, $listing);
                if (! $policy['allowed']) {
                    $blockers[] = $this->blocker($policy['code'], $policy['next_action']);
                }
            }
        }

        $blockers = collect($blockers)->unique('code')->values()->all();
        $unmapped = $store ? $this->unmappedActiveCount($storeId) : 0;
        $paused = collect($blockers)->contains(fn (array $item): bool => $item['code'] === 'sync_paused');

        return [
            'ready' => $blockers === [],
            'effective_state' => $blockers === [] ? 'ready' : ($paused ? 'paused' : 'blocked'),
            'store_id' => $storeId,
            'variant_id' => $variantId,
            'listing_variant_id' => $mappingRows->count() === 1 ? (int) $mappingRows->first()->id : null,
            'blockers' => $blockers,
            'setup_info' => ['unmapped_active_listings' => $unmapped],
        ];
    }

    private function balance(int $storeId, int $variantId): ?object
    {
        $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
        $pool = $mode === 'shared_pool'
            ? DB::table('inventory_pools')->where('kind', 'shared')->first()
            : DB::table('inventory_pools')->where('marketplace_store_id', $storeId)->first();

        return $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
            ->where('product_variant_id', $variantId)->first() : null;
    }

    private function unmappedActiveCount(int $storeId): int
    {
        return DB::table('marketplace_listing_variants as listing')
            ->leftJoin('marketplace_variant_mappings as mapping', 'mapping.listing_variant_id', '=', 'listing.id')
            ->where('listing.store_id', $storeId)
            ->whereNull('mapping.id')
            ->get(['listing.observed_data'])
            ->filter(fn (object $listing): bool => $this->participates($listing))
            ->count();
    }

    private function participates(object $listing): bool
    {
        $observed = json_decode($listing->observed_data ?? '{}', true) ?: [];
        $status = strtolower((string) ($observed['status'] ?? $observed['productStatus'] ?? 'active'));

        return ! in_array($status, ['inactive', 'draft', 'archived', 'deleted', 'deactivated'], true);
    }

    private function blocker(string $code, string $nextAction): array
    {
        return ['code' => $code, 'next_action' => $nextAction];
    }
}
