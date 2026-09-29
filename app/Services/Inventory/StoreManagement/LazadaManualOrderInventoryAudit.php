<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use App\Services\Inventory\StockAllocationService;
use App\Services\Marketplace\MarketplaceInventoryException;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonInterface;

/** Operator-triggered reconciliation of a freshly fetched Lazada order. */
final class LazadaManualOrderInventoryAudit
{
    public function reconcile(int $orderId, array $plan, User $actor, ?CarbonInterface $createdAt = null): array
    {
        abort_unless($actor->exists && $actor->is_admin, 403);
        $results = DB::transaction(function () use ($orderId, $plan, $actor, $createdAt): array {
            $checked = [];
            foreach ($plan as $line) $checked[] = $this->reconcileLine($orderId, $line, $actor, $createdAt);
            return $checked;
        }, 3);
        foreach ($results as &$result) {
            if ($result['result'] === 'corrected') {
                try {
                    app(SyncOutbox::class)->enqueueMutation(null,
                        DB::table('inventory_balances')->find($result['balance_id']), 'order',
                        'order-audit:'.$orderId.':'.$result['line_key']);
                    $result['stock_sync'] = 'queued';
                } catch (\Throwable $exception) {
                    report($exception);
                    $result['stock_sync'] = 'requires_retry';
                }
            }
            unset($result['balance_id']);
        }
        unset($result);

        return $results;
    }

    private function reconcileLine(int $orderId, array $line, User $actor, ?CarbonInterface $createdAt): array
    {
        $order = DB::table('marketplace_orders as o')->join('marketplace_stores as s', 's.id', '=', 'o.store_id')
            ->where('o.id', $orderId)->where('s.platform', 'lazada')->where('s.status', 'connected')
            ->first(['o.id', 'o.store_id', 'o.external_order_id']);
        if (! $order) throw new MarketplaceInventoryException('order_not_connected');
        $key = (string) $line['line_key'];
        $target = $line['target'];
        $allocation = DB::table('stock_allocations')->where('owner_type', 'marketplace_order')
            ->where('owner_id', $orderId)->where('line_key', $key)->first();
        if (($target === 'waiting' && ! $allocation) || ($target === 'released' && ! $allocation)) {
            return ['line_key' => $key, 'result' => 'already_correct'];
        }
        if ($target === 'waiting') throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
        if ($target === 'released' && $allocation) {
            if ($allocation->status === 'reserved') app(StockAllocationService::class)->release((int) $allocation->id);
            elseif ($allocation->status !== 'released') throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
            return ['line_key' => $key, 'result' => $allocation->status === 'released' ? 'already_correct' : 'released'];
        }
        $mappedVariantId = DB::table('marketplace_listing_variants as l')
            ->join('marketplace_variant_mappings as m', 'm.listing_variant_id', '=', 'l.id')
            ->where('l.store_id', $order->store_id)->where('m.store_id', $order->store_id)
            ->where('l.external_product_id', $line['external_product_id'])
            ->where('l.external_variant_id', $line['external_variant_id'])->value('m.product_variant_id');
        if (! $mappedVariantId && ! $allocation) return ['line_key' => $key, 'result' => 'skipped_unmapped'];
        if (! $mappedVariantId) throw new MarketplaceInventoryException('sku_mapping_missing');
        if ($allocation && (int) $allocation->product_variant_id !== (int) $mappedVariantId) {
            throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
        }
        $variantId = (int) $mappedVariantId;
        DB::table('product_variants')->where('id', $variantId)->lockForUpdate()->first();
        $allocation = DB::table('stock_allocations')->where('owner_type', 'marketplace_order')
            ->where('owner_id', $orderId)->where('line_key', $key)->lockForUpdate()->first();
        if ($allocation && ((int) $allocation->product_variant_id !== (int) $variantId
            || (int) $allocation->quantity !== 1)) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
        $facility = DB::table('marketplace_store_facilities as f')
            ->join('marketplace_store_facility_scans as scan', 'scan.marketplace_store_id', '=', 'f.marketplace_store_id')
            ->where('f.marketplace_store_id', $order->store_id)
            ->where('f.provider_facility_id', $line['warehouse_code'])
            ->where('f.is_active', true)->where('scan.status', 'verified')->first(['f.*']);
        $balance = $facility?->inventory_warehouse_id ? DB::table('inventory_balances as b')
            ->join('inventory_pools as p', 'p.id', '=', 'b.inventory_pool_id')
            ->where('p.kind', 'facility')->where('p.warehouse_id', $facility->inventory_warehouse_id)
            ->where('b.product_variant_id', $variantId)->first(['b.*']) : null;
        if (! $balance || $balance->counted_at === null || $balance->physical_stock === null
            || $balance->reserved_stock === null || $balance->safety_stock === null) {
            throw new MarketplaceInventoryException('exact_facility_count_required');
        }
        if ($allocation && (int) $allocation->inventory_balance_id === (int) $balance->id) {
            if ($allocation->status !== $target && ! ($target === 'released' && $allocation->status === 'released')) {
                throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
            }
            $owned = $allocation->inventory_facility_order_activation_id
                ? DB::table('inventory_facility_order_activations')->where('id', $allocation->inventory_facility_order_activation_id)
                    ->where('marketplace_store_facility_id', $facility->id)
                    ->where('inventory_balance_id', $balance->id)->exists() : false;
            $held = (int) DB::table('stock_allocations')->where('inventory_balance_id', $balance->id)
                ->where('status', 'reserved')->sum('quantity');
            $shortage = $allocation->status === 'reserved' ? max(0, $held - (int) $balance->reserved_stock) : 0;
            if ($owned && $shortage === 0) return ['line_key' => $key, 'result' => 'already_correct'];
            if (DB::table('marketplace_order_inventory_corrections')->where('stock_allocation_id', $allocation->id)->exists()) {
                throw new MarketplaceInventoryException('allocation_ownership_requires_review');
            }
            if ((! $owned && $allocation->inventory_facility_order_activation_id) || $shortage > 1
                || (int) $balance->physical_stock - (int) $balance->reserved_stock
                    - (int) $balance->safety_stock < $shortage) {
                throw new MarketplaceInventoryException('allocation_ownership_requires_review');
            }
            $activationId = $owned ? (int) $allocation->inventory_facility_order_activation_id
                : $this->activation((int) $order->store_id, (int) $facility->id,
                    (int) $variantId, (int) $balance->id, (int) $actor->id, $createdAt);
            if ($shortage === 1) $this->move((int) $allocation->id, $balance, 'audit_in', 0, 1);
            DB::table('stock_allocations')->where('id', $allocation->id)->update([
                'inventory_order_activation_id' => null,
                'inventory_facility_order_activation_id' => $activationId, 'updated_at' => now(),
            ]);
            $this->receipt($allocation, $balance, $balance, $actor, $line['warehouse_code']);
            return ['line_key' => $key, 'result' => 'corrected', 'balance_id' => (int) $balance->id];
        }
        if ($allocation && ! in_array($allocation->status, ['reserved', 'consumed'], true)) {
            throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
        }
        if ($allocation && $allocation->status === 'consumed' && $target !== 'consumed') {
            throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
        }
        $activationId = $this->activation((int) $order->store_id, (int) $facility->id,
            (int) $variantId, (int) $balance->id, (int) $actor->id, $createdAt);
        if (! $allocation) {
            $id = app(StockAllocationService::class)->reserve('marketplace_order', $orderId,
                $key, (int) $variantId, 1, null, $activationId);
            if ($target === 'consumed') app(StockAllocationService::class)->consume($id);
            return ['line_key' => $key, 'result' => $target === 'consumed' ? 'consumed' : 'reserved'];
        }
        $old = DB::table('inventory_balances')->where('id', $allocation->inventory_balance_id)
            ->lockForUpdate()->first();
        $new = DB::table('inventory_balances')->where('id', $balance->id)->lockForUpdate()->first();
        if (! $old || $old->physical_stock === null || $old->reserved_stock === null
            || DB::table('inventory_allocation_movements')->where('stock_allocation_id', $allocation->id)
                ->whereIn('event', ['audit_out', 'audit_in'])->exists()) {
            throw new MarketplaceInventoryException('allocation_ownership_requires_review');
        }
        $consumed = $allocation->status === 'consumed';
        $oldReservedByAllocations = (int) DB::table('stock_allocations')->where('inventory_balance_id', $old->id)
            ->where('status', 'reserved')->sum('quantity');
        $newReservedByAllocations = (int) DB::table('stock_allocations')->where('inventory_balance_id', $new->id)
            ->where('status', 'reserved')->sum('quantity');
        if ((! $consumed && (! in_array($oldReservedByAllocations - (int) $old->reserved_stock, [0, 1], true)
                || (int) $new->reserved_stock < $newReservedByAllocations
                || (int) $new->physical_stock - (int) $new->reserved_stock - (int) $new->safety_stock < 1))
            || ($consumed && (int) $new->physical_stock - (int) $new->reserved_stock < 1)) {
            throw new MarketplaceInventoryException('exact_facility_stock_insufficient');
        }
        $oldPhysicalWasDebited = $consumed && DB::table('inventory_allocation_movements')
            ->where('stock_allocation_id', $allocation->id)->where('inventory_balance_id', $old->id)
            ->where('event', 'consumed')->exists();
        $oldReservedDelta = $consumed || $oldReservedByAllocations > (int) $old->reserved_stock ? 0 : -1;
        $this->move($allocation->id, $old, 'audit_out', $oldPhysicalWasDebited ? 1 : 0, $oldReservedDelta);
        $this->move($allocation->id, $new, 'audit_in', $consumed ? -1 : 0, $consumed ? 0 : 1);
        DB::table('stock_allocations')->where('id', $allocation->id)->update([
            'inventory_balance_id' => $new->id, 'inventory_order_activation_id' => null,
            'inventory_facility_order_activation_id' => $activationId, 'updated_at' => now(),
        ]);
        $this->receipt($allocation, $old, $new, $actor, $line['warehouse_code']);
        return ['line_key' => $key, 'result' => 'corrected', 'balance_id' => (int) $new->id];
    }

    private function receipt(object $allocation, object $old, object $new, User $actor, string $warehouseCode): void
    {
        DB::table('marketplace_order_inventory_corrections')->insert([
            'stock_allocation_id' => $allocation->id,
            'from_balance_id' => $old->id, 'to_balance_id' => $new->id,
            'actor_id' => $actor->id, 'provider_warehouse_code' => $warehouseCode,
            'allocation_status' => $allocation->status, 'created_at' => now(),
        ]);
    }

    private function activation(int $storeId, int $facilityId, int $variantId, int $balanceId, int $actorId, ?CarbonInterface $createdAt): int
    {
        $existing = DB::table('inventory_facility_order_activations')->where('marketplace_store_id', $storeId)
            ->where('marketplace_store_facility_id', $facilityId)->where('product_variant_id', $variantId)
            ->lockForUpdate()->first();
        if ($existing) {
            if ((int) $existing->inventory_balance_id !== $balanceId) throw new MarketplaceInventoryException('facility_owner_changed');
            if ($createdAt && \Carbon\CarbonImmutable::parse($existing->orders_created_after)->greaterThanOrEqualTo($createdAt)) {
                throw new MarketplaceInventoryException('order_outside_managed_activation');
            }
            return (int) $existing->id;
        }
        return DB::table('inventory_facility_order_activations')->insertGetId([
            'marketplace_store_id' => $storeId, 'marketplace_store_facility_id' => $facilityId,
            'product_variant_id' => $variantId, 'inventory_balance_id' => $balanceId,
            'actor_id' => $actorId, 'reason' => 'Manual order inventory audit',
            'orders_created_after' => ($createdAt ?? now())->copy()->subSecond(), 'created_at' => now(),
        ]);
    }

    private function move(int $allocationId, object $balance, string $event, int $physicalDelta, int $reservedDelta): void
    {
        $physical = (int) $balance->physical_stock + $physicalDelta;
        $reserved = (int) $balance->reserved_stock + $reservedDelta;
        if ($physical < 0 || $reserved < 0 || $reserved > $physical) {
            throw new MarketplaceInventoryException('allocation_ownership_requires_review');
        }
        $version = (int) $balance->version + 1;
        DB::table('inventory_balances')->where('id', $balance->id)->update([
            'physical_stock' => $physical, 'reserved_stock' => $reserved,
            'version' => $version, 'updated_at' => now(),
        ]);
        DB::table('inventory_allocation_movements')->insert([
            'stock_allocation_id' => $allocationId, 'inventory_balance_id' => $balance->id,
            'event' => $event, 'physical_delta' => $physicalDelta, 'reserved_delta' => $reservedDelta,
            'physical_after' => $physical, 'reserved_after' => $reserved,
            'balance_version' => $version, 'created_at' => now(),
        ]);
    }
}
