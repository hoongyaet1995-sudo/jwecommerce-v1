<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Reviewed local ledger mutations for one verified provider facility and one JW physical owner. */
final class FacilityStockReview
{
    public function review(User $actor, int $storeId, int $facilityId, int $variantId,
        string $kind, int $quantity, string $reason): array
    {
        $this->authorize($actor);
        $this->validateInput($kind, $quantity, $reason);

        return DB::transaction(function () use ($actor, $storeId, $facilityId, $variantId, $kind, $quantity, $reason): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            $this->assertTestAcceptance($storeId, $variantId, $facilityId);
            $facility = $this->facility($storeId, $facilityId, $variantId, $configuration, $scan);
            $balance = $this->balance($facility, $variantId, true);
            $this->assertEpochCountTarget($configuration, $balance, $kind);
            $after = $this->project($kind, $balance, $quantity);
            $before = $this->state($balance);
            $id = (string) Str::uuid();
            DB::table('marketplace_facility_stock_reviews')->insert([
                'id' => $id, 'marketplace_store_id' => $storeId,
                'marketplace_store_facility_id' => $facilityId, 'product_variant_id' => $variantId,
                'inventory_balance_id' => $balance->id, 'actor_id' => $actor->id,
                'kind' => $kind, 'configuration_version' => $configuration->version,
                'topology_revision' => $scan->revision, 'balance_version' => $balance->version,
                'input' => json_encode(['quantity' => $quantity, 'reason' => trim($reason)], JSON_THROW_ON_ERROR),
                'before' => json_encode($before, JSON_THROW_ON_ERROR),
                'after' => json_encode($after, JSON_THROW_ON_ERROR),
                'status' => 'pending', 'expires_at' => now()->addMinutes(10),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['id' => $id, 'status' => 'pending', 'kind' => $kind,
                'facility_id' => $facilityId, 'balance_id' => (int) $balance->id,
                'before' => $before, 'after' => $after, 'expires_at' => now()->addMinutes(10)->toIso8601String()];
        }, 3);
    }

    public function confirm(User $actor, string $reviewId, string $operationKey): array
    {
        $this->authorize($actor);
        if (! preg_match('/\A[A-Za-z0-9:_-]{1,120}\z/D', $operationKey)) {
            throw ValidationException::withMessages(['operation_key' => 'Use a valid operation key.']);
        }

        return DB::transaction(function () use ($actor, $reviewId, $operationKey): array {
            $review = DB::table('marketplace_facility_stock_reviews')->where('id', $reviewId)->lockForUpdate()->first();
            abort_unless($review, 404);
            if ($review->status === 'confirmed') {
                if ($review->operation_key !== $operationKey) {
                    $this->conflict('review_already_confirmed');
                }

                return json_decode($review->receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($review->status !== 'pending' || now()->gte($review->expires_at)) {
                $this->conflict('review_expired');
            }
            if ((int) $review->actor_id !== (int) $actor->id && ! $actor->is_super_admin) {
                abort(403);
            }
            if (DB::table('marketplace_facility_stock_reviews')->where('operation_key', $operationKey)
                ->where('id', '!=', $reviewId)->exists()) {
                $this->conflict('operation_key_reused');
            }
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $scan = DB::table('marketplace_store_facility_scans')
                ->where('marketplace_store_id', $review->marketplace_store_id)->lockForUpdate()->first();
            if (! $configuration || (int) $configuration->version !== (int) $review->configuration_version
                || ! $scan || (int) $scan->revision !== (int) $review->topology_revision) {
                $this->conflict('configuration_or_topology_changed');
            }
            $this->assertTestAcceptance((int) $review->marketplace_store_id,
                (int) $review->product_variant_id, (int) $review->marketplace_store_facility_id);
            $facility = $this->facility((int) $review->marketplace_store_id,
                (int) $review->marketplace_store_facility_id, (int) $review->product_variant_id,
                $configuration, $scan);
            DB::table('product_variants')->where('id', $review->product_variant_id)->lockForUpdate()->first();
            $balance = $this->balance($facility, (int) $review->product_variant_id, false);
            if (! $balance || (int) $balance->id !== (int) $review->inventory_balance_id
                || (int) $balance->version !== (int) $review->balance_version) {
                $this->conflict('balance_changed');
            }
            $this->assertEpochCountTarget($configuration, $balance, $review->kind);
            $input = json_decode($review->input, true, 512, JSON_THROW_ON_ERROR);
            $after = $this->project($review->kind, $balance, (int) $input['quantity']);
            if ($after !== json_decode($review->after, true, 512, JSON_THROW_ON_ERROR)) {
                $this->conflict('balance_changed');
            }
            $version = (int) $balance->version + 1;
            DB::table('inventory_balances')->where('id', $balance->id)->update([
                'physical_stock' => $after['physical'], 'reserved_stock' => $after['reserved'],
                'safety_stock' => $after['safety'], 'counted_at' => $balance->counted_at ?? now(),
                'version' => $version, 'updated_at' => now(),
            ]);
            $countingEpoch = $configuration->active_rebaseline_epoch_id !== null;
            $sync = $countingEpoch
                ? ['status' => 'awaiting_rebaseline_release', 'id' => null]
                : app(SyncOutbox::class)->enqueueFacilityBalance(
                    'facility-stock:'.$reviewId,
                    DB::table('inventory_balances')->find($balance->id), $facility
                );
            $activation = null;
            if (! $countingEpoch && $review->kind === 'count' && config('marketplace.multiwarehouse_orders_enabled') === true
                && DB::table('marketplace_stores')->where('id', $review->marketplace_store_id)
                    ->value('platform') === 'lazada') {
                $activation = app(FacilityOrderActivation::class)->activate($actor,
                    (int) $review->marketplace_store_id, (int) $facility->id,
                    (int) $review->product_variant_id, 'Verified first count: '.$reviewId);
            }
            $receipt = ['review_id' => $reviewId, 'store_id' => (int) $review->marketplace_store_id,
                'facility_id' => (int) $facility->id, 'provider_facility_id' => $facility->provider_facility_id,
                'warehouse_id' => (int) $facility->inventory_warehouse_id,
                'variant_id' => (int) $review->product_variant_id, 'balance_id' => (int) $balance->id,
                'kind' => $review->kind, 'before' => $this->state($balance), 'after' => $after,
                'balance_version' => $version, 'ledger_status' => 'applied',
                'provider_sync_status' => $sync['status'], 'provider_sync_task' => $sync,
                'order_activation' => $activation];
            DB::table('marketplace_facility_stock_reviews')->where('id', $reviewId)->update([
                'status' => 'confirmed', 'operation_key' => $operationKey,
                'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'confirmed_at' => now(), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);
    }

    private function facility(int $storeId, int $facilityId, int $variantId, ?object $configuration, ?object $scan): object
    {
        if (! $configuration || ($configuration->operating_mode !== 'independent_stores'
                && ! ($configuration->active_rebaseline_epoch_id !== null
                    && $configuration->operating_mode === 'shared_pool'))
            || ! $scan || $scan->status !== 'verified'
            || ! in_array($scan->topology, ['single_warehouse', 'multi_warehouse'], true)) {
            throw ValidationException::withMessages(['facility_id' => 'Verify warehouse topology and Independent Stock mode first.']);
        }
        $facility = DB::table('marketplace_store_facilities as facility')
            ->join('marketplace_stores as store', 'store.id', '=', 'facility.marketplace_store_id')
            ->where('facility.id', $facilityId)->where('facility.marketplace_store_id', $storeId)
            ->where('facility.is_active', true)->where('store.status', 'connected')
            ->first(['facility.*']);
        if (! $facility || $facility->inventory_warehouse_id === null
            || ! DB::table('inventory_warehouses')->where('id', $facility->inventory_warehouse_id)
                ->where('is_active', true)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['facility_id' => 'Link this provider facility to an active JW physical warehouse.']);
        }
        if (DB::table('marketplace_variant_mappings')->where('store_id', $storeId)
            ->where('product_variant_id', $variantId)->count() !== 1) {
            throw ValidationException::withMessages(['variant_id' => 'Map exactly one listing to this JW SKU.']);
        }
        if (DB::table('inventory_order_activations')->where('channel_key', 'marketplace:'.$storeId)
            ->where('product_variant_id', $variantId)->exists()) {
            throw ValidationException::withMessages(['variant_id' => 'Reconcile the existing store-level order route before facility stock operations.']);
        }
        $legacy = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'store')->where('pool.marketplace_store_id', $storeId)
            ->where('balance.product_variant_id', $variantId)
            ->first(['balance.physical_stock', 'balance.reserved_stock']);
        if ($configuration->active_rebaseline_epoch_id === null
            && $legacy && ((int) $legacy->physical_stock > 0 || (int) $legacy->reserved_stock > 0)) {
            throw ValidationException::withMessages(['variant_id' => 'Migrate the existing store stock and reservations before counting a facility.']);
        }
        if ($configuration->active_rebaseline_epoch_id === null
            && DB::table('inventory_balances as balance')
                ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                ->where('pool.kind', 'warehouse')
                ->where('pool.warehouse_id', $facility->inventory_warehouse_id)
                ->where('balance.product_variant_id', $variantId)
                ->whereNotNull('balance.counted_at')->exists()) {
            throw ValidationException::withMessages(['variant_id' => 'Archive the counted legacy warehouse owner before First Count.']);
        }

        return $facility;
    }

    private function assertEpochCountTarget(?object $configuration, ?object $balance, string $kind): void
    {
        $epochId = $configuration?->active_rebaseline_epoch_id;
        if ($epochId === null) {
            return;
        }
        if ($kind !== 'count' || ! $balance || $balance->rebaseline_epoch_id !== $epochId
            || DB::table('inventory_rebaseline_epochs')->where('id', $epochId)->value('status') !== 'counting') {
            throw ValidationException::withMessages(['inventory' => 'Only First Count on a reviewed new-epoch facility is available during rebaseline.']);
        }
    }

    private function balance(object $facility, int $variantId, bool $create): ?object
    {
        $warehouseId = (int) $facility->inventory_warehouse_id;
        if ($create) {
            DB::table('inventory_pools')->insertOrIgnore([
                'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility',
                'warehouse_id' => $warehouseId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $pool = DB::table('inventory_pools')->where('warehouse_id', $warehouseId)
            ->where('kind', 'facility')->first();
        if (! $pool || $pool->kind !== 'facility') {
            throw ValidationException::withMessages(['facility_id' => 'The physical warehouse already belongs to another inventory owner.']);
        }
        if ($create) {
            DB::table('inventory_balances')->insertOrIgnore([
                'inventory_pool_id' => $pool->id, 'product_variant_id' => $variantId,
                'version' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
            ->where('product_variant_id', $variantId)->lockForUpdate()->first();
    }

    private function project(string $kind, object $balance, int $quantity): array
    {
        $physical = $balance->physical_stock === null ? null : (int) $balance->physical_stock;
        $reserved = (int) ($balance->reserved_stock ?? 0);
        $safety = (int) ($balance->safety_stock ?? 0);
        if (($kind === 'count' && $physical !== null) || ($kind !== 'count' && $physical === null)) {
            throw ValidationException::withMessages(['quantity' => $kind === 'count'
                ? 'This facility has already been counted.' : 'Complete first count for this facility.']);
        }
        $target = match ($kind) {
            'count', 'audit' => $quantity,
            'intake' => $physical + $quantity,
            default => throw ValidationException::withMessages(['kind' => 'Choose first count, intake or audit.']),
        };
        if ($target < $reserved || $target > 2147483647) {
            throw ValidationException::withMessages(['quantity' => 'Physical stock must cover reservations and fit the allowed range.']);
        }

        return ['physical' => $target, 'reserved' => $reserved, 'safety' => $safety,
            'available' => max(0, $target - $reserved - $safety)];
    }

    private function state(object $balance): array
    {
        $physical = $balance->physical_stock === null ? null : (int) $balance->physical_stock;
        $reserved = $balance->reserved_stock === null ? null : (int) $balance->reserved_stock;
        $safety = $balance->safety_stock === null ? null : (int) $balance->safety_stock;

        return ['physical' => $physical, 'reserved' => $reserved, 'safety' => $safety,
            'available' => $physical === null ? null : max(0, $physical - ($reserved ?? 0) - ($safety ?? 0))];
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && $actor->is_admin
            && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
        abort_unless(config('marketplace.multiwarehouse_operations_enabled') === true
            || config('marketplace.multiwarehouse_test_acceptance_enabled') === true, 404);
    }

    private function assertTestAcceptance(int $storeId, int $variantId, int $facilityId): void
    {
        if (config('marketplace.multiwarehouse_test_acceptance_enabled') === true) {
            app(LazadaTestFacilityAcceptance::class)->assertEligible($storeId, $variantId, $facilityId);
        }
    }

    private function validateInput(string $kind, int $quantity, string $reason): void
    {
        if (! in_array($kind, ['count', 'intake', 'audit'], true)
            || $quantity < ($kind === 'intake' ? 1 : 0) || $quantity > 2147483647
            || trim($reason) === '' || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['inventory' => 'Choose a valid action, quantity and reason.']);
        }
    }

    private function conflict(string $code): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code, 'message' => 'Facility inventory changed after review. Review current stock again.',
        ]], 409));
    }
}
