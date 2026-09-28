<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use App\Services\Inventory\CatalogInventoryState;
use App\Services\Inventory\CatalogStockProjection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** One absolute JW allocation and its provider outbox, committed together. */
final class DirectStockAllocation
{
    public function __construct(private readonly AccountResolver $accounts, private readonly SyncOutbox $outbox) {}

    public function apply(User $actor, int $variantId, int $total, array $allocations, string $reason, string $operationKey): array
    {
        abort_unless($actor->exists && $actor->is_admin
            && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
        if (! preg_match('/\A[A-Za-z0-9:_-]{1,120}\z/D', $operationKey)) {
            throw ValidationException::withMessages(['operation_key' => 'Use a valid operation key.']);
        }
        if ($allocations === [] || ! array_key_exists('website', $allocations)
            || array_sum($allocations) !== $total) {
            throw ValidationException::withMessages(['allocations' => 'Allocate every unit before saving.']);
        }

        return DB::transaction(function () use ($actor, $variantId, $total, $allocations, $reason, $operationKey): array {
            $existing = DB::table('inventory_store_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing) {
                $review = DB::table('inventory_store_reviews')->find($existing->review_id);
                $payload = $review ? json_decode($review->payload, true) : [];
                if ($existing->kind !== 'batch_direct_allocation' || ($payload['variant_id'] ?? null) !== $variantId
                    || ($payload['physical_count'] ?? null) !== $total || ($payload['allocations'] ?? null) !== $allocations
                    || ($payload['reason'] ?? null) !== $reason) {
                    throw ValidationException::withMessages(['operation_key' => 'This operation key belongs to another allocation.']);
                }

                return json_decode($existing->receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || $configuration->operating_mode !== 'independent_stores'
                || $configuration->active_rebaseline_epoch_id !== null) {
                throw ValidationException::withMessages(['inventory' => 'Independent Stock mode is required for direct allocation.']);
            }
            abort_unless(DB::table('product_variants')->where('id', $variantId)->lockForUpdate()->exists(), 404);

            $targets = [];
            foreach ($allocations as $key => $quantity) {
                if (! is_int($quantity) || $quantity < 0 || $quantity > 2147483647) {
                    throw ValidationException::withMessages(['allocations' => 'Use non-negative whole numbers.']);
                }
                if ($key === 'website') {
                    $resolved = $this->accounts->balance($variantId, 'website');
                    $targets[] = ['key' => $key, 'balance_id' => $resolved['id'], 'store_id' => null, 'facility' => null, 'quantity' => $quantity];
                    continue;
                }
                if (preg_match('/\Amarketplace:([1-9][0-9]*)\z/D', $key, $match)) {
                    $storeId = (int) $match[1];
                    $this->assertMappedStore($storeId, $variantId);
                    if (DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
                        ->where('is_active', true)->whereNotNull('provider_facility_id')->exists()) {
                        if ($quantity === 0) {
                            continue;
                        }
                        throw ValidationException::withMessages(['allocations' => 'Choose the exact provider warehouse for this store.']);
                    }
                    $resolved = $this->accounts->balance($variantId, $key);
                    DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)
                        ->where('management_status', '!=', 'managed')
                        ->update(['management_status' => 'managed', 'updated_at' => now()]);
                    $targets[] = ['key' => $key, 'balance_id' => $resolved['id'], 'store_id' => $storeId, 'facility' => null, 'quantity' => $quantity];
                    continue;
                }
                if (preg_match('/\Afacility:([1-9][0-9]*):([1-9][0-9]*)\z/D', $key, $match)) {
                    $storeId = (int) $match[1];
                    $this->assertMappedStore($storeId, $variantId);
                    $facility = DB::table('marketplace_store_facilities')->where('id', (int) $match[2])
                        ->where('marketplace_store_id', $storeId)->where('is_active', true)->first();
                    if (! $facility || ! $facility->provider_facility_id || ! $facility->inventory_warehouse_id
                        || ! DB::table('inventory_warehouses')->where('id', $facility->inventory_warehouse_id)
                            ->where('is_active', true)->where('status', 'active')->exists()) {
                        if ($quantity === 0) {
                            continue;
                        }
                        throw ValidationException::withMessages(['allocations' => 'Choose an active mapped provider warehouse.']);
                    }
                    DB::table('inventory_pools')->insertOrIgnore([
                        'pool_key' => 'facility:'.$facility->inventory_warehouse_id, 'kind' => 'facility',
                        'warehouse_id' => $facility->inventory_warehouse_id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $pool = DB::table('inventory_pools')->where('pool_key', 'facility:'.$facility->inventory_warehouse_id)->first();
                    DB::table('inventory_balances')->insertOrIgnore([
                        'inventory_pool_id' => $pool->id, 'product_variant_id' => $variantId,
                        'version' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $balanceId = DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                        ->where('product_variant_id', $variantId)->value('id');
                    $targets[] = ['key' => $key, 'balance_id' => (int) $balanceId, 'store_id' => $storeId,
                        'facility' => $facility, 'quantity' => $quantity];
                    continue;
                }
                throw ValidationException::withMessages(['allocations' => 'Choose a valid stock destination.']);
            }
            if (count(array_unique(array_column($targets, 'balance_id'))) !== count($targets)) {
                throw ValidationException::withMessages(['allocations' => 'The same JW stock location was selected twice.']);
            }

            $reviewId = (string) Str::uuid();
            $operationId = (string) Str::uuid();
            DB::table('inventory_store_reviews')->insert([
                'id' => $reviewId, 'kind' => 'batch_direct_allocation', 'actor_id' => $actor->id,
                'configuration_version' => $configuration->version, 'balance_versions' => '{}',
                'payload' => json_encode(['variant_id' => $variantId, 'physical_count' => $total,
                    'allocations' => $allocations, 'reason' => $reason], JSON_THROW_ON_ERROR),
                'before' => '{}', 'after' => json_encode(['physical_total' => $total], JSON_THROW_ON_ERROR),
                'blockers' => '[]', 'status' => 'confirmed', 'expires_at' => now()->addMinutes(10),
                'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('inventory_store_operations')->insert([
                'id' => $operationId, 'review_id' => $reviewId, 'operation_key' => $operationKey,
                'kind' => 'batch_direct_allocation', 'actor_id' => $actor->id,
                'ledger_status' => 'applied', 'receipt' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);

            $destinations = [];
            foreach ($targets as $target) {
                $balance = DB::table('inventory_balances')->where('id', $target['balance_id'])->lockForUpdate()->first();
                $version = (int) $balance->version + 1;
                DB::table('inventory_balances')->where('id', $balance->id)->update([
                    'physical_stock' => $target['quantity'],
                    'reserved_stock' => $balance->reserved_stock ?? 0,
                    'safety_stock' => $balance->safety_stock ?? 0,
                    'counted_at' => $balance->counted_at ?? now(), 'version' => $version, 'updated_at' => now(),
                ]);
                $fresh = DB::table('inventory_balances')->find($balance->id);
                $eventKey = 'batch-allocation:'.$operationId.':'.$target['key'];
                $sync = $target['facility']
                    ? $this->outbox->enqueueFacilityBalance($eventKey, $fresh, $target['facility'])
                    : $this->outbox->enqueueBalance($operationId, $fresh, 'allocation', $eventKey);
                DB::table('inventory_store_sync_tasks')->where('id', $sync['id'])
                    ->update(['target_quantity' => $target['quantity'], 'updated_at' => now()]);
                $sync['target_quantity'] = $target['quantity'];
                $destinations[$target['key']] = ['physical' => $target['quantity'], 'sync' => $sync];
            }
            app(CatalogInventoryState::class)->markVerified($variantId, $operationId);
            app(CatalogStockProjection::class)->refresh($variantId);
            $receipt = ['operation_id' => $operationId, 'review_id' => $reviewId,
                'ledger_status' => 'applied', 'physical_total' => $total, 'destinations' => $destinations];
            DB::table('inventory_store_operations')->where('id', $operationId)->update([
                'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);
    }

    private function assertMappedStore(int $storeId, int $variantId): void
    {
        if (! DB::table('marketplace_stores')->where('id', $storeId)->exists()
            || DB::table('marketplace_variant_mappings as mapping')
                ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
                ->where('mapping.store_id', $storeId)->where('listing.store_id', $storeId)
                ->where('mapping.product_variant_id', $variantId)->count() !== 1) {
            throw ValidationException::withMessages(['allocations' => 'Choose one connected store listing mapped to this JW SKU.']);
        }
    }
}
