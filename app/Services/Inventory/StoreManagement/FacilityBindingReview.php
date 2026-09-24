<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FacilityBindingReview
{
    public function create(User $actor, int $storeId, int $facilityId, int $warehouseId, string $reason): array
    {
        $this->authorize($actor);
        $this->enabled();

        return DB::transaction(function () use ($actor, $storeId, $facilityId, $warehouseId, $reason): array {
            $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
            abort_unless($store && in_array($store->platform, ['lazada', 'shopee'], true), 404);
            $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            $facility = DB::table('marketplace_store_facilities')->where('id', $facilityId)
                ->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            abort_unless($facility, 404);
            $warehouse = DB::table('inventory_warehouses')->where('id', $warehouseId)->lockForUpdate()->first();
            abort_unless($warehouse, 404);
            $this->assertEligible($store, $scan, $facility, $warehouse, $storeId);

            $id = (string) Str::uuid();
            DB::table('marketplace_facility_binding_reviews')->insert([
                'id' => $id, 'marketplace_store_id' => $storeId, 'marketplace_store_facility_id' => $facilityId,
                'actor_id' => $actor->id, 'before_warehouse_id' => $facility->inventory_warehouse_id,
                'after_warehouse_id' => $warehouseId, 'topology_revision' => $scan->revision,
                'warehouse_version' => $warehouse->version, 'reason' => $reason,
                'status' => 'pending', 'expires_at' => now()->addMinutes(10),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['id' => $id, 'store_id' => $storeId, 'facility_id' => $facilityId,
                'before_warehouse_id' => $facility->inventory_warehouse_id === null ? null : (int) $facility->inventory_warehouse_id,
                'after_warehouse_id' => $warehouseId, 'topology_revision' => (int) $scan->revision,
                'status' => 'pending'];
        }, 3);
    }

    public function confirm(User $actor, string $reviewId, string $operationKey): array
    {
        $this->authorize($actor);
        $this->enabled();

        return DB::transaction(function () use ($actor, $reviewId, $operationKey): array {
            $review = DB::table('marketplace_facility_binding_reviews')->where('id', $reviewId)->lockForUpdate()->first();
            abort_unless($review, 404);
            if (DB::table('marketplace_facility_binding_reviews')->where('operation_key', $operationKey)
                ->where('id', '!=', $reviewId)->exists()) {
                $this->conflict('operation_key_reused');
            }
            if ($review->status === 'confirmed') {
                if ($review->operation_key !== $operationKey) {
                    $this->conflict('binding_already_confirmed');
                }

                return json_decode($review->receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            if ((int) $review->actor_id !== (int) $actor->id && ! $actor->is_super_admin) {
                abort(403);
            }
            if ($review->status !== 'pending' || now()->gte($review->expires_at)) {
                $this->conflict('binding_review_expired');
            }
            $store = DB::table('marketplace_stores')->where('id', $review->marketplace_store_id)->first();
            $scan = DB::table('marketplace_store_facility_scans')
                ->where('marketplace_store_id', $review->marketplace_store_id)->lockForUpdate()->first();
            if (! $scan || $scan->status !== 'verified' || (int) $scan->revision !== (int) $review->topology_revision) {
                $this->conflict('facility_topology_changed');
            }
            $facility = DB::table('marketplace_store_facilities')->where('id', $review->marketplace_store_facility_id)
                ->lockForUpdate()->first();
            $warehouse = DB::table('inventory_warehouses')->where('id', $review->after_warehouse_id)
                ->lockForUpdate()->first();
            if (! $warehouse || (int) $warehouse->version !== (int) $review->warehouse_version) {
                $this->conflict('physical_warehouse_changed');
            }
            if (! $facility || (int) $facility->marketplace_store_id !== (int) $review->marketplace_store_id
                || $facility->inventory_warehouse_id !== $review->before_warehouse_id) {
                $this->conflict('facility_binding_changed');
            }
            $this->assertEligible($store, $scan, $facility, $warehouse, (int) $review->marketplace_store_id);

            DB::table('marketplace_store_facilities')->where('id', $facility->id)
                ->update(['inventory_warehouse_id' => $warehouse->id, 'updated_at' => now()]);
            $receipt = ['review_id' => $reviewId, 'store_id' => (int) $review->marketplace_store_id,
                'facility_id' => (int) $facility->id, 'before_warehouse_id' => $review->before_warehouse_id === null
                    ? null : (int) $review->before_warehouse_id, 'after_warehouse_id' => (int) $warehouse->id,
                'binding_status' => 'confirmed', 'stock_changed' => false];
            DB::table('marketplace_facility_binding_reviews')->where('id', $reviewId)->update([
                'status' => 'confirmed', 'operation_key' => $operationKey,
                'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'confirmed_at' => now(), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);
    }

    private function assertEligible(?object $store, ?object $scan, object $facility, object $warehouse, int $storeId): void
    {
        if (! $store || $store->status !== 'connected' || ! $scan || $scan->status !== 'verified'
            || ! in_array($scan->topology, ['single_warehouse', 'multi_warehouse'], true)
            || $scan->ledger_migration_status !== 'pending' || ! $facility->is_active
            || ! $warehouse->is_active || $warehouse->status !== 'active') {
            throw ValidationException::withMessages(['facility_id' => 'Verify the store and active warehouse before binding.']);
        }
        if ((int) $facility->inventory_warehouse_id === (int) $warehouse->id) {
            throw ValidationException::withMessages(['warehouse_id' => 'This facility is already bound to the selected physical warehouse.']);
        }
        if (DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
            ->where('id', '!=', $facility->id)->where('inventory_warehouse_id', $warehouse->id)->exists()) {
            throw ValidationException::withMessages(['warehouse_id' => 'Another facility in this store already uses that physical warehouse.']);
        }
        if ($facility->inventory_warehouse_id !== null && DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'facility')->where('pool.warehouse_id', $facility->inventory_warehouse_id)
            ->whereNotNull('balance.counted_at')->exists()) {
            throw ValidationException::withMessages(['facility_id' => 'Reconcile this facility ledger before changing its physical warehouse.']);
        }
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && $actor->is_admin && $actor->is_super_admin, 403);
    }

    private function enabled(): void
    {
        abort_unless(config('marketplace.multiwarehouse_binding_enabled') === true, 404);
    }

    private function conflict(string $code): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code, 'message' => 'The facility binding review is no longer current. Review it again.',
        ]], 409));
    }
}
