<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use App\Services\Inventory\CatalogInventoryState;
use App\Services\Inventory\CatalogStockProjection;
use App\Services\Inventory\CurrentStockLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** First Count of one already selected stock location. Import never sends stock to the provider. */
final class MarketplaceBaselineFirstCount
{
    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly MarketplaceStoreInventoryPublisher $publisher,
        private readonly SyncOutbox $outbox,
    ) {}

    public function observe(User $actor, string $storeKey, ?int $facilityId, int $variantId): array
    {
        $this->authorize($actor);
        $target = $this->target($storeKey, $facilityId, $variantId);
        if ($target['store'] === null) {
            throw ValidationException::withMessages(['source' => 'JW Storefront uses a manual First Count.']);
        }
        $listing = $target['listing'];
        if ($target['facility']) {
            $listing->sync_provider_location_id = $target['facility']->provider_facility_id;
            $listing->sync_strict_facility = true;
        }
        $read = $this->publisher->observe($target['store'], $listing);
        if (! is_int($read['observed_quantity'] ?? null) || $read['observed_quantity'] < 0
            || ($target['facility'] && ($read['provider_location_id'] ?? null) !== $target['facility']->provider_facility_id)) {
            throw ValidationException::withMessages(['stock' => 'Marketplace did not return a valid count for the selected location.']);
        }

        return ['quantity' => $read['observed_quantity'], 'observed_at' => $read['observed_at'] ?? now()->toIso8601String(),
            'provider_location_id' => $read['provider_location_id'] ?? null,
            'store_id' => $target['store']?->id, 'facility_id' => $facilityId, 'variant_id' => $variantId];
    }

    /** Import one exact provider location. A counted location is adjusted only if its JW revision stayed unchanged. */
    public function reconcile(User $actor, int $storeId, ?int $facilityId, int $variantId, int $itemId): array
    {
        $this->authorize($actor);
        $storeKey = (string) $storeId;
        $eventKey = 'bulk-retrieve:'.$itemId;
        $prior = DB::table('inventory_current_stock_movements')->whereIn('event_key', [$eventKey, 'baseline:'.$eventKey])->first();
        if ($prior) {
            $firstCount = str_starts_with($prior->reason, 'Marketplace First Count');
            return ['action' => $firstCount ? 'first_count' : 'audit_adjustment',
                'quantity' => (int) $prior->current_after,
                'previous_quantity' => $firstCount ? null : (int) $prior->current_after - (int) $prior->delta,
                'observed_at' => $prior->created_at];
        }
        $target = $this->target($storeKey, $facilityId, $variantId);
        $balanceId = $this->balanceId($storeKey, $facilityId, $variantId);
        $before = DB::table('inventory_balances')->find($balanceId);
        $first = $this->observe($actor, $storeKey, $facilityId, $variantId);
        $quantity = $first['quantity'];
        if ($before->current_stock === null && $before->counted_at === null) {
            $this->commit($actor, $storeKey, $facilityId, $variantId, 'marketplace', $quantity,
                $quantity, 'bulk-retrieve-'.$itemId);
            return ['action' => 'first_count', 'quantity' => $quantity,
                'previous_quantity' => null, 'observed_at' => $first['observed_at']];
        }
        $second = $this->observe($actor, $storeKey, $facilityId, $variantId);
        if ($second['quantity'] !== $quantity) {
            throw ValidationException::withMessages(['stock' => 'Marketplace quantity changed during retrieval. Retry this location.']);
        }
        return DB::transaction(function () use ($balanceId, $before, $quantity, $eventKey, $target,
            $storeId, $facilityId, $variantId, $second): array {
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->first();
            if (! $balance || (int) $balance->version !== (int) $before->version
                || $balance->current_stock === null || $balance->counted_at === null) {
                throw ValidationException::withMessages(['stock' => 'JW Current Stock changed during retrieval. Retry this location.']);
            }
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || $configuration->operating_mode !== 'independent_stores'
                || $configuration->active_rebaseline_epoch_id !== null) {
                throw ValidationException::withMessages(['inventory' => 'Independent Current Stock mode is required.']);
            }
            $movement = app(CurrentStockLedger::class)->setAbsolute($balanceId, $quantity, $eventKey,
                'Marketplace bulk stock audit adjustment');
            // The absolute provider snapshot already includes earlier orders at this location.
            DB::table('inventory_balances')->where('id', $balanceId)->update([
                'counted_at' => CarbonImmutable::parse($second['observed_at'])->utc()->format('Y-m-d H:i:s'),
            ]);
            app(CatalogStockProjection::class)->refresh($variantId);
            DB::table('inventory_store_sync_tasks')->insert([
                'id' => (string) Str::uuid(), 'marketplace_store_id' => $storeId,
                'product_variant_id' => $variantId, 'inventory_balance_id' => $balanceId,
                'marketplace_store_facility_id' => $facilityId, 'listing_variant_id' => $target['listing']->id,
                'provider_external_product_id' => $target['listing']->external_product_id,
                'provider_external_variant_id' => $target['listing']->external_variant_id,
                'provider_location_id' => $second['provider_location_id'], 'stage' => 'provider_import',
                'status' => 'confirmed', 'target_quantity' => $quantity,
                'balance_version' => $movement['balance_version'], 'event_key' => $eventKey,
                'provider_receipt' => json_encode(['observed_quantity' => $quantity,
                    'provider_location_id' => $second['provider_location_id'],
                    'observed_at' => $second['observed_at'],
                    'confirmation_method' => 'provider_import_no_write'], JSON_THROW_ON_ERROR),
                'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['action' => 'audit_adjustment', 'quantity' => $quantity,
                'previous_quantity' => (int) $before->current_stock, 'observed_at' => $second['observed_at']];
        }, 3);
    }

    /** Apply one location from a twice-confirmed item snapshot without another provider call. */
    public function reconcileObserved(User $actor, int $storeId, ?int $facilityId, int $variantId,
        int $itemId, int $quantity, string $observedAt, int $expectedVersion): array
    {
        $this->authorize($actor);
        if ($quantity < 0) {
            throw ValidationException::withMessages(['stock' => 'Marketplace returned an invalid stock quantity.']);
        }
        $eventKey = 'bulk-retrieve:'.$itemId;
        $prior = DB::table('inventory_current_stock_movements')
            ->whereIn('event_key', [$eventKey, 'baseline:'.$eventKey])->first();
        if ($prior) {
            $first = str_starts_with($prior->reason, 'Marketplace First Count');
            return ['action' => $first ? 'first_count' : 'audit_adjustment',
                'quantity' => (int) $prior->current_after,
                'previous_quantity' => $first ? null : (int) $prior->current_after - (int) $prior->delta,
                'observed_at' => $prior->created_at];
        }
        $target = $this->target((string) $storeId, $facilityId, $variantId);
        $balanceId = $this->balanceId((string) $storeId, $facilityId, $variantId);
        $providerLocation = $target['facility']?->provider_facility_id;
        return DB::transaction(function () use ($target, $actor, $storeId, $facilityId, $variantId,
            $itemId, $quantity, $observedAt, $expectedVersion, $eventKey, $balanceId, $providerLocation): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || $configuration->operating_mode !== 'independent_stores'
                || $configuration->active_rebaseline_epoch_id !== null) {
                throw ValidationException::withMessages(['inventory' => 'Independent Current Stock mode is required.']);
            }
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->first();
            if (! $balance || (int) $balance->version !== $expectedVersion) {
                throw ValidationException::withMessages(['stock' => 'JW Current Stock changed during retrieval. Retry this location.']);
            }
            $first = $balance->current_stock === null && $balance->counted_at === null;
            $previous = $first ? null : (int) $balance->current_stock;
            $movement = app(CurrentStockLedger::class)->setAbsolute($balanceId, $quantity,
                $first ? 'baseline:'.$eventKey : $eventKey,
                $first ? 'Marketplace First Count baseline' : 'Marketplace bulk stock audit adjustment');
            DB::table('inventory_balances')->where('id', $balanceId)->update([
                'counted_at' => CarbonImmutable::parse($observedAt)->utc()->format('Y-m-d H:i:s'),
            ]);
            if ($first) {
                DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)
                    ->update(['management_status' => 'managed', 'updated_at' => now()]);
                app(CatalogInventoryState::class)->markVerified($variantId, 'bulk-retrieve-'.$itemId);
            }
            app(CatalogStockProjection::class)->refresh($variantId);
            DB::table('inventory_store_sync_tasks')->insert([
                'id' => (string) Str::uuid(), 'marketplace_store_id' => $storeId,
                'product_variant_id' => $variantId, 'inventory_balance_id' => $balanceId,
                'marketplace_store_facility_id' => $facilityId, 'listing_variant_id' => $target['listing']->id,
                'provider_external_product_id' => $target['listing']->external_product_id,
                'provider_external_variant_id' => $target['listing']->external_variant_id,
                'provider_location_id' => $providerLocation, 'stage' => $first ? 'baseline' : 'provider_import',
                'status' => 'confirmed', 'target_quantity' => $quantity,
                'balance_version' => $movement['balance_version'],
                'event_key' => $first ? 'baseline:'.$eventKey : $eventKey,
                'provider_receipt' => json_encode(['observed_quantity' => $quantity,
                    'provider_location_id' => $providerLocation, 'observed_at' => $observedAt,
                    'confirmation_method' => 'provider_import_no_write'], JSON_THROW_ON_ERROR),
                'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['action' => $first ? 'first_count' : 'audit_adjustment', 'quantity' => $quantity,
                'previous_quantity' => $previous, 'observed_at' => $observedAt];
        }, 3);
    }

    public function revision(User $actor, int $storeId, ?int $facilityId, int $variantId): int
    {
        $this->authorize($actor);
        $this->target((string) $storeId, $facilityId, $variantId);
        $balanceId = $this->balanceId((string) $storeId, $facilityId, $variantId);
        return (int) DB::table('inventory_balances')->where('id', $balanceId)->value('version');
    }

    public function commit(User $actor, string $storeKey, ?int $facilityId, int $variantId,
        string $source, int $quantity, ?int $expectedProviderQuantity, string $operationKey,
        bool $allowProviderOverwrite = false): array
    {
        $this->authorize($actor);
        if (! config('inventory.current_stock_only') || ! in_array($source, ['marketplace', 'manual'], true)
            || $quantity < 0 || $quantity > 2147483647
            || ! preg_match('/\A[A-Za-z0-9:_-]{1,120}\z/D', $operationKey)) {
            throw ValidationException::withMessages(['count' => 'Use a valid Current Stock First Count.']);
        }
        // A repeated browser submission must return its committed result even if the provider has changed since.
        $prior = DB::table('inventory_current_stock_movements')->where('event_key', 'baseline:'.$operationKey)->first();
        if ($prior) {
            $target = $this->target($storeKey, $facilityId, $variantId);
            if ((int) $prior->current_after !== $quantity || (int) $prior->inventory_balance_id
                !== $this->balanceId($storeKey, $facilityId, $variantId)) {
                throw ValidationException::withMessages(['operation_key' => 'This First Count key was already used.']);
            }
            return ['status' => 'already_saved', 'current_stock' => $quantity,
                'source' => $source, 'store_id' => $target['store']?->id, 'facility_id' => $facilityId,
                'provider_sync_status' => 'already_saved'];
        }
        // Provider I/O happens before the database transaction; no network call holds a row lock.
        $read = null;
        if ($storeKey !== 'website') {
            try {
                $read = $this->observe($actor, $storeKey, $facilityId, $variantId);
            } catch (\Throwable $error) {
                if ($source === 'marketplace') {
                    throw $error;
                }
                // Manual count remains possible; no provider write is queued without a readback.
            }
        }
        if ($source === 'marketplace' && ($expectedProviderQuantity !== $quantity
            || $read['quantity'] !== $quantity)) {
            throw ValidationException::withMessages(['stock' => 'Marketplace stock changed. Read again and confirm the new quantity.']);
        }
        if ($source === 'manual' && $read && $read['quantity'] !== $quantity) {
            if ($expectedProviderQuantity === null || $expectedProviderQuantity !== $read['quantity']) {
                throw ValidationException::withMessages(['stock' => 'Marketplace stock changed. Read again before confirming the overwrite.']);
            }
            if (! $allowProviderOverwrite) {
                throw ValidationException::withMessages(['stock' => 'Manual count differs from Marketplace. Confirm the overwrite before saving.']);
            }
        }
        if ($source === 'manual' && $read && $expectedProviderQuantity !== null
            && $expectedProviderQuantity !== $read['quantity']) {
            throw ValidationException::withMessages(['stock' => 'Marketplace stock changed. Read again before confirming the overwrite.']);
        }
        $observedAt = $read['observed_at'] ?? now()->toIso8601String();

        return DB::transaction(function () use ($actor, $storeKey, $facilityId, $variantId, $source,
            $quantity, $operationKey, $read, $observedAt): array {
            $prior = DB::table('inventory_current_stock_movements')->where('event_key', 'baseline:'.$operationKey)->first();
            if ($prior) {
                if ((int) $prior->current_after !== $quantity || (int) $prior->inventory_balance_id
                    !== $this->balanceId($storeKey, $facilityId, $variantId)) {
                    throw ValidationException::withMessages(['operation_key' => 'This First Count key was already used.']);
                }
                return ['status' => 'already_saved', 'current_stock' => $quantity, 'provider_sync_status' => 'already_saved'];
            }
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || $configuration->operating_mode !== 'independent_stores'
                || $configuration->active_rebaseline_epoch_id !== null) {
                throw ValidationException::withMessages(['inventory' => 'Independent Current Stock mode is required.']);
            }
            $target = $this->target($storeKey, $facilityId, $variantId);
            $balanceId = $this->balanceId($storeKey, $facilityId, $variantId);
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->first();
            if (! $balance || $balance->current_stock !== null || $balance->counted_at !== null) {
                throw ValidationException::withMessages(['stock' => 'This location already has a First Count. Use Stock Audit Adjustment.']);
            }
            $movement = app(CurrentStockLedger::class)->setAbsolute($balanceId, $quantity,
                'baseline:'.$operationKey, $source === 'marketplace' ? 'Marketplace First Count baseline' : 'Manual First Count');
            // The order boundary is the provider read time, not a later browser acknowledgement.
            // MySQL DATETIME needs a timezone-free value; preserve the provider instant in UTC.
            DB::table('inventory_balances')->where('id', $balanceId)->update([
                'counted_at' => CarbonImmutable::parse($observedAt)->utc()->format('Y-m-d H:i:s'),
            ]);
            if ($target['store']) {
                DB::table('inventory_store_accounts')->where('marketplace_store_id', $target['store']->id)
                    ->update(['management_status' => 'managed', 'updated_at' => now()]);
            }
            app(CatalogInventoryState::class)->markVerified($variantId, $operationKey);
            app(CatalogStockProjection::class)->refresh($variantId);
            $sync = ['status' => 'local_only'];
            if ($target['store'] && $source === 'manual' && $read && $read['quantity'] !== $quantity) {
                $fresh = DB::table('inventory_balances')->find($balanceId);
                $sync = $target['facility']
                    ? $this->outbox->enqueueFacilityBalance('baseline-manual:'.$operationKey, $fresh, $target['facility'])
                    : $this->outbox->enqueueBalance(null, $fresh, 'baseline', 'baseline-manual:'.$operationKey);
            }
            if ($target['store'] && $read && $read['quantity'] === $quantity) {
                DB::table('inventory_store_sync_tasks')->insert([
                    'id' => (string) Str::uuid(), 'marketplace_store_id' => $target['store']->id,
                    'product_variant_id' => $variantId, 'inventory_balance_id' => $balanceId,
                    'marketplace_store_facility_id' => $facilityId,
                    'listing_variant_id' => $target['listing']->id,
                    'provider_external_product_id' => $target['listing']->external_product_id,
                    'provider_external_variant_id' => $target['listing']->external_variant_id,
                    'provider_location_id' => $read['provider_location_id'],
                    'stage' => 'baseline', 'status' => 'confirmed', 'target_quantity' => $quantity,
                    'balance_version' => $movement['balance_version'], 'event_key' => 'baseline:'.$operationKey,
                    'provider_receipt' => json_encode(['observed_quantity' => $quantity,
                        'provider_location_id' => $read['provider_location_id'],
                        'observed_at' => $observedAt, 'confirmation_method' => 'provider_import_no_write'], JSON_THROW_ON_ERROR),
                    'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $sync = ['status' => 'matched_at_import'];
            }

            return ['status' => 'saved', 'current_stock' => $quantity, 'source' => $source,
                'store_id' => $target['store']?->id, 'facility_id' => $facilityId,
                'provider_sync_status' => $sync['status'], 'provider_observed_at' => $read['observed_at'] ?? null];
        }, 3);
    }

    private function target(string $storeKey, ?int $facilityId, int $variantId): array
    {
        abort_unless(DB::table('product_variants')->where('id', $variantId)->exists(), 404);
        if ($storeKey === 'website' && $facilityId === null) {
            return ['store' => null, 'facility' => null, 'listing' => null];
        }
        if (! ctype_digit($storeKey) || (int) $storeKey < 1) {
            throw ValidationException::withMessages(['store' => 'Select a valid stock location.']);
        }
        $store = DB::table('marketplace_stores')->where('id', (int) $storeKey)->where('status', 'connected')->first();
        $listing = DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
            ->where('mapping.store_id', (int) $storeKey)->where('listing.store_id', (int) $storeKey)
            ->where('mapping.product_variant_id', $variantId)->select('listing.*')->get();
        if (! $store || ! in_array($store->platform, ['lazada', 'shopee'], true) || $listing->count() !== 1) {
            throw ValidationException::withMessages(['store' => 'Connect and map exactly one Marketplace listing first.']);
        }
        $facility = null;
        if ($facilityId !== null) {
            $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $store->id)->first();
            $facility = DB::table('marketplace_store_facilities')->where('id', $facilityId)
                ->where('marketplace_store_id', $store->id)->where('is_active', true)->first();
            if ($scan?->status !== 'verified' || ! $facility?->provider_facility_id
                || ! $facility->inventory_warehouse_id) {
                throw ValidationException::withMessages(['facility' => 'Verify this exact provider warehouse first.']);
            }
        } elseif (DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('is_active', true)->whereNotNull('provider_facility_id')->exists()) {
            throw ValidationException::withMessages(['facility' => 'Choose the exact provider warehouse.']);
        }

        return ['store' => $store, 'facility' => $facility, 'listing' => $listing->first()];
    }

    private function balanceId(string $storeKey, ?int $facilityId, int $variantId): int
    {
        if ($facilityId === null) {
            return $this->accounts->balance($variantId,
                $storeKey === 'website' ? 'website' : 'marketplace:'.$storeKey)['id'];
        }
        $facility = DB::table('marketplace_store_facilities')->where('id', $facilityId)->first();
        $pool = DB::table('inventory_pools')->where('pool_key', 'facility:'.$facility->inventory_warehouse_id)->first();
        if (! $pool) {
            DB::table('inventory_pools')->insertOrIgnore(['pool_key' => 'facility:'.$facility->inventory_warehouse_id,
                'kind' => 'facility', 'warehouse_id' => $facility->inventory_warehouse_id,
                'created_at' => now(), 'updated_at' => now()]);
            $pool = DB::table('inventory_pools')->where('pool_key', 'facility:'.$facility->inventory_warehouse_id)->first();
        }
        DB::table('inventory_balances')->insertOrIgnore(['inventory_pool_id' => $pool->id,
            'product_variant_id' => $variantId, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        return (int) DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
            ->where('product_variant_id', $variantId)->value('id');
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->is_admin && ($actor->is_super_admin
            || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }
}
