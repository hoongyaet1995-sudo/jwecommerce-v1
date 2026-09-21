<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CatalogStockService
{
    public function __construct(
        private readonly CatalogInventoryState $states,
        private readonly CatalogStockProjection $projection,
    ) {}

    /** @return array<string,mixed> */
    public function setPhysical(int $variantId, int $quantity, ?string $expectedRevision, string $operationKey, int $actorId): array
    {
        if ($quantity < 0 || $quantity > 2147483647 || $actorId < 1 || ! preg_match('/\A[A-Za-z0-9:_-]{1,160}\z/D', $operationKey)) {
            throw ValidationException::withMessages(['inventoryQuantity' => 'Enter a valid non-negative stock quantity.']);
        }
        $requestHash = hash('sha256', json_encode([$variantId, $quantity, $expectedRevision, $actorId], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($variantId, $quantity, $expectedRevision, $operationKey, $actorId, $requestHash): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || ! in_array($configuration->operating_mode, [null, 'shared_pool', 'independent_stores'], true)) {
                throw ValidationException::withMessages(['inventoryQuantity' => 'Reconcile the legacy inventory mode before editing stock.']);
            }
            $variant = DB::table('product_variants')->where('id', $variantId)->lockForUpdate()->first();
            abort_unless($variant, 404);
            $previous = DB::table('catalog_stock_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($previous) {
                if (! hash_equals((string) $previous->request_hash, $requestHash)) {
                    throw new RuntimeException('Catalog stock operation key conflicts with an earlier request.');
                }
                return json_decode((string) $previous->receipt, true, 512, JSON_THROW_ON_ERROR);
            }

            $state = $this->states->snapshot($variantId);
            if ($state['verified'] || $state['repair_required']) {
                throw ValidationException::withMessages(['inventoryQuantity' => 'Edit this stock in Inventory Management.']);
            }
            if ($state['initialized'] && ($expectedRevision === null || ! hash_equals($state['revision'], $expectedRevision))) {
                throw ValidationException::withMessages(['inventoryQuantity' => 'Inventory changed. Reload the product before saving.']);
            }

            $poolKey = $configuration->operating_mode === 'independent_stores' ? 'store:website' : 'shared';
            $kind = $configuration->operating_mode === 'independent_stores' ? 'store' : 'shared';
            DB::table('inventory_pools')->insertOrIgnore([
                'pool_key' => $poolKey, 'kind' => $kind, 'warehouse_id' => null, 'marketplace_store_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $pool = DB::table('inventory_pools')->where('pool_key', $poolKey)->lockForUpdate()->first();
            if (! $pool || $pool->kind !== $kind) {
                throw ValidationException::withMessages(['inventoryQuantity' => 'Inventory account ownership requires repair.']);
            }
            $balance = DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                ->where('product_variant_id', $variantId)->lockForUpdate()->first();
            if (! $balance) {
                DB::table('inventory_balances')->insert([
                    'inventory_pool_id' => $pool->id, 'product_variant_id' => $variantId,
                    'physical_stock' => null, 'reserved_stock' => 0, 'safety_stock' => 0,
                    'counted_at' => null, 'version' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $balance = DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                    ->where('product_variant_id', $variantId)->lockForUpdate()->first();
            }
            $reserved = (int) ($balance->reserved_stock ?? 0);
            if ($quantity < $reserved) {
                throw ValidationException::withMessages(['inventoryQuantity' => 'Physical stock cannot be lower than reserved stock.']);
            }

            $before = $balance->physical_stock === null ? null : (int) $balance->physical_stock;
            $reason = $state['initialized'] ? 'Catalog stock update' : 'Initial catalog stock';
            DB::table('inventory_balances')->where('id', $balance->id)->update([
                'physical_stock' => $quantity, 'reserved_stock' => $reserved,
                'safety_stock' => (int) ($balance->safety_stock ?? 0), 'counted_at' => null,
                'version' => (int) $balance->version + 1, 'updated_at' => now(),
            ]);
            DB::table('product_variants')->where('id', $variantId)->update([
                'inventory_initialized_at' => $variant->inventory_initialized_at ?? now(), 'updated_at' => now(),
            ]);
            $this->projection->refresh($variantId);
            $receipt = $this->states->snapshot($variantId);
            DB::table('catalog_stock_operations')->insert([
                'operation_key' => $operationKey, 'request_hash' => $requestHash,
                'product_variant_id' => $variantId, 'inventory_balance_id' => $balance->id,
                'physical_before' => $before, 'physical_after' => $quantity, 'actor_id' => $actorId,
                'reason' => $reason, 'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $receipt;
        }, 3);
    }
}
