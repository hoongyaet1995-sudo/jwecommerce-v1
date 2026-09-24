<?php

namespace App\Services\Inventory;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Compatibility accounting: product_variants.stock is unallocated availability,
 * not physical-on-hand. Reservation reduces availability; consumption does not
 * reduce it again. Callers own order authorization, reconciliation and lifecycle.
 * No historical allocations are inferred from existing stock or orders.
 */
class StockAllocationService
{
    public function reserve(string $ownerType, int $ownerId, string $lineKey, int $variantId, int $quantity,
        ?int $activationId=null, ?int $facilityActivationId=null): int
    {
        if (!in_array($ownerType, ['order', 'marketplace_order'], true) || $ownerId < 1
            || !preg_match('/\A[A-Za-z0-9:_-]{1,100}\z/D', $lineKey)
            || $variantId < 1 || $quantity < 1 || $quantity > 2147483647) {
            throw new RuntimeException('Invalid inventory allocation identity or quantity.');
        }

        if ($activationId !== null && $facilityActivationId !== null) {
            throw new RuntimeException('An order line cannot have two physical stock owners.');
        }
        if ($facilityActivationId !== null && ($ownerType !== 'marketplace_order'
            || config('marketplace.multiwarehouse_orders_enabled') !== true)) {
            throw new RuntimeException('Facility order accounting is not enabled.');
        }

        return DB::transaction(function () use ($ownerType, $ownerId, $lineKey, $variantId, $quantity, $activationId, $facilityActivationId) {
            // Shared with checkout: serialize competing consumers on the SKU row.
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);
            $key = ['owner_type'=>$ownerType, 'owner_id'=>$ownerId, 'line_key'=>$lineKey];
            $existing = DB::table('stock_allocations')->where($key)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->product_variant_id !== $variantId || (int) $existing->quantity !== $quantity
                    || ($existing->inventory_order_activation_id===null?null:(int)$existing->inventory_order_activation_id)!==$activationId
                    || ($existing->inventory_facility_order_activation_id===null?null:(int)$existing->inventory_facility_order_activation_id)!==$facilityActivationId) {
                    throw new RuntimeException('An existing inventory allocation cannot change SKU or quantity.');
                }
                // Terminal allocations are tombstones: replay never reopens them.
                return (int) $existing->id;
            }
            if ($activationId===null && $facilityActivationId===null && $variant->stock < $quantity) {
                throw new RuntimeException('Requested quantity is unavailable.');
            }
            $id = DB::table('stock_allocations')->insertGetId($key + [
                'product_variant_id'=>$variantId, 'quantity'=>$quantity, 'status'=>'reserved',
                'inventory_order_activation_id'=>$activationId,
                'inventory_facility_order_activation_id'=>$facilityActivationId,
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
            if ($activationId!==null || $facilityActivationId!==null) {
                $available=app(PhysicalAllocationService::class)->managed($id,'reserved')['available'];
            } else {
                $available = app(InventoryMutationService::class)->change($variantId, -$quantity, 'allocation:'.$id.':reserve', 'Inventory reservation');
                app(PhysicalAllocationService::class)->reserve($id,$ownerType,$ownerId,$variantId,$quantity);
            }
            $this->record($id, 'reserved', -$quantity, $available);
            return $id;
        }, 3);
    }

    public function consume(int $allocationId): void
    {
        $this->finish($allocationId, 'consumed');
    }

    public function release(int $allocationId): void
    {
        $this->finish($allocationId, 'released');
    }

    private function finish(int $id, string $target): void
    {
        DB::transaction(function () use ($id, $target) {
            // SKU is immutable; obtain it before locking to preserve SKU->allocation lock order.
            $variantId = DB::table('stock_allocations')->where('id', $id)->value('product_variant_id');
            if (!$variantId) {
                throw new RuntimeException('Inventory allocation does not exist.');
            }
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);
            $allocation = DB::table('stock_allocations')->where('id', $id)->lockForUpdate()->first();
            if ($allocation->status === $target) {
                return;
            }
            if ($allocation->status !== 'reserved') {
                throw new RuntimeException('Terminal inventory allocation requires reconciliation, not another transition.');
            }
            $delta = $target === 'released' ? (int) $allocation->quantity : 0;
            if ($allocation->inventory_order_activation_id!==null || $allocation->inventory_facility_order_activation_id!==null) {
                $movement=app(PhysicalAllocationService::class)->managed($id,$target);
                DB::table('stock_allocations')->where('id',$id)->update(['status'=>$target,'updated_at'=>now()]);
                $this->record($id,$target,$movement['delta'],$movement['available']);
                return;
            }
            $physicalDelta=app(PhysicalAllocationService::class)->finish($allocation,$target);
            if($physicalDelta!==null)$delta=$physicalDelta;
            $available = (int)$variant->stock;
            if ($delta !== 0) {
                $available = app(InventoryMutationService::class)->change((int)$variantId, $delta, 'allocation:'.$id.':release', 'Inventory reservation released');
            }
            DB::table('stock_allocations')->where('id', $id)->update(['status'=>$target, 'updated_at'=>now()]);
            $this->record($id, $target, $delta, $available);
        }, 3);
    }

    private function record(int $id, string $event, int $delta, int $available): void
    {
        DB::table('stock_allocation_events')->insert([
            'stock_allocation_id'=>$id, 'event'=>$event, 'available_delta'=>$delta,
            'available_after'=>$available, 'created_at'=>now(),
        ]);
    }
}
