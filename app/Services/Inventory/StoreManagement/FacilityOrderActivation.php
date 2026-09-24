<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Starts accounting for new orders at one exact provider location after a verified JW count. */
final class FacilityOrderActivation
{
    public function activate(User $actor, int $storeId, int $facilityId, int $variantId, string $reason): array
    {
        abort_unless($actor->exists && $actor->is_admin && $actor->is_super_admin, 403);
        abort_unless(config('marketplace.multiwarehouse_orders_enabled') === true, 404);
        if (trim($reason) === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'An activation reason is required.']);
        }

        return DB::transaction(function () use ($actor, $storeId, $facilityId, $variantId, $reason): array {
            $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)
                ->lockForUpdate()->first();
            $facility = DB::table('marketplace_store_facilities')->where('id', $facilityId)
                ->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            if (! $store || $store->status !== 'connected'
                || ! in_array($store->platform, ['lazada', 'shopee'], true)
                || ! $scan || $scan->status !== 'verified'
                || ! $facility || ! $facility->is_active || ! $facility->inventory_warehouse_id) {
                $this->reject('Verify the connected store and exact provider facility first.');
            }
            $warehouse = DB::table('inventory_warehouses')->where('id', $facility->inventory_warehouse_id)->first();
            $pool = DB::table('inventory_pools')->where('warehouse_id', $facility->inventory_warehouse_id)->first();
            $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                ->where('product_variant_id', $variantId)->lockForUpdate()->first() : null;
            if (! $warehouse || ! $warehouse->is_active || $warehouse->status !== 'active'
                || ! $pool || $pool->kind !== 'facility' || ! $balance || $balance->counted_at === null
                || $balance->physical_stock === null || $balance->reserved_stock === null
                || $balance->safety_stock === null) {
                $this->reject('Complete a verified first count for this exact physical facility.');
            }
            if (DB::table('marketplace_variant_mappings')->where('store_id', $storeId)
                ->where('product_variant_id', $variantId)->count() !== 1) {
                $this->reject('Map exactly one listing to this JW SKU first.');
            }
            if (DB::table('inventory_order_activations')->where('channel_key', 'marketplace:'.$storeId)
                ->where('product_variant_id', $variantId)->exists()) {
                $this->reject('Reconcile the old store-level order activation before switching to facility routing.');
            }
            $old = DB::table('inventory_balances as balance')
                ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                ->where('pool.kind', 'store')->where('pool.marketplace_store_id', $storeId)
                ->where('balance.product_variant_id', $variantId)
                ->first(['balance.physical_stock', 'balance.reserved_stock']);
            if ($old && ((int) $old->physical_stock > 0 || (int) $old->reserved_stock > 0)) {
                $this->reject('Migrate and reconcile the old store balance before activating facility orders.');
            }
            if (app(InventoryIngestionBoundary::class)->unresolvedHistoricalOrderIds(
                $storeId, $variantId, now()) !== []) {
                $this->reject('Reconcile existing marketplace orders for this SKU before activating new facility orders.');
            }
            $existing = DB::table('inventory_facility_order_activations')
                ->where('marketplace_store_id', $storeId)
                ->where('marketplace_store_facility_id', $facilityId)
                ->where('product_variant_id', $variantId)->first();
            if ($existing) {
                if ((int) $existing->inventory_balance_id !== (int) $balance->id) {
                    $this->reject('An existing facility order route cannot change stock owner.');
                }

                return $this->result($existing, $facility);
            }
            $cutoff = now()->startOfSecond();
            $id = DB::table('inventory_facility_order_activations')->insertGetId([
                'marketplace_store_id' => $storeId,
                'marketplace_store_facility_id' => $facilityId,
                'product_variant_id' => $variantId,
                'inventory_balance_id' => $balance->id,
                'actor_id' => $actor->id, 'reason' => trim($reason),
                'orders_created_after' => $cutoff, 'created_at' => $cutoff,
            ]);

            return $this->result(DB::table('inventory_facility_order_activations')->find($id), $facility);
        }, 3);
    }

    public function eligible(int $storeId, int $variantId, string $providerFacilityId, CarbonInterface $createdAt): ?array
    {
        $route = DB::table('inventory_facility_order_activations as activation')
            ->join('marketplace_store_facilities as facility', 'facility.id', '=', 'activation.marketplace_store_facility_id')
            ->join('marketplace_store_facility_scans as scan', 'scan.marketplace_store_id', '=', 'activation.marketplace_store_id')
            ->where('activation.marketplace_store_id', $storeId)
            ->where('activation.product_variant_id', $variantId)
            ->where('facility.provider_facility_id', $providerFacilityId)
            ->where('facility.is_active', true)->where('scan.status', 'verified')
            ->where('activation.orders_created_after', '<', $createdAt)
            ->first(['activation.*', 'facility.provider_facility_id']);

        return $route ? (array) $route : null;
    }

    private function result(object $route, object $facility): array
    {
        return ['id' => (int) $route->id, 'store_id' => (int) $route->marketplace_store_id,
            'facility_id' => (int) $route->marketplace_store_facility_id,
            'provider_facility_id' => $facility->provider_facility_id,
            'variant_id' => (int) $route->product_variant_id,
            'inventory_balance_id' => (int) $route->inventory_balance_id,
            'orders_created_after' => $route->orders_created_after];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['activation' => $message]);
    }
}
