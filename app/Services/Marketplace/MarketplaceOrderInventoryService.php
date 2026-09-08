<?php

namespace App\Services\Marketplace;

use App\Models\ProductVariant;
use App\Services\Inventory\StockAllocationService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MarketplaceOrderInventoryService
{
    public function __construct(private StockAllocationService $allocations) {}

    public function process(int $orderId): void
    {
        if (config('marketplace.inventory_accounting_enabled') !== true) return;
        DB::transaction(function () use ($orderId) {
            $storeId = DB::table('marketplace_orders')->where('id', $orderId)->value('store_id');
            $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (!$store || $store->status !== 'connected' || $store->platform !== 'shopee') return;
            $policy = DB::table('marketplace_inventory_policies')->where('store_id', $storeId)->lockForUpdate()->first();
            if (!$policy || !$policy->enabled || !$policy->reconciled_at || !$policy->orders_created_after) return;
            $order = DB::table('marketplace_orders')->where('id', $orderId)->lockForUpdate()->first();
            $payload = json_decode(Crypt::decryptString($order->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
            try {
                // Savepoint: an exception on any line rolls back all allocations,
                // while retaining the fetched platform order and exception record.
                $status = DB::transaction(fn () => $this->apply($order, $payload, (int) $policy->orders_created_after));
                $error = null;
            } catch (MarketplaceInventoryException $exception) {
                $status = 'exception';
                $error = $exception->getMessage();
            } catch (\App\Services\Inventory\WarehouseAllocationException $exception) {
                $status = 'exception';
                $error = 'warehouse_allocation_requires_review';
            }
            $values = [
                'platform_updated_at'=>$order->platform_updated_at, 'status'=>$status, 'error_code'=>$error,
                'updated_at'=>now(),
            ];
            if (in_array($status, ['released', 'consumed'], true)) $values['terminal_status'] = $status;
            DB::table('marketplace_order_inventory')->updateOrInsert(['order_id'=>$orderId], $values);
        }, 3);
    }

    private function apply(object $order, array $payload, int $cutoff): string
    {
        if (($payload['fulfillment_flag'] ?? null) !== 'fulfilled_by_local_seller'
            || !empty($payload['advance_package'])) throw new MarketplaceInventoryException('unsupported_fulfillment');
        if (!is_int($payload['create_time'] ?? null) || $payload['create_time'] <= $cutoff) {
            throw new MarketplaceInventoryException('historical_order');
        }
        $target = match ($order->platform_status) {
            'READY_TO_SHIP', 'PROCESSED' => 'reserved',
            'SHIPPED', 'COMPLETED' => 'consumed',
            'CANCELLED' => 'released',
            'UNPAID', 'IN_CANCEL' => 'waiting',
            default => throw new MarketplaceInventoryException('unsupported_order_status'),
        };
        $terminal = DB::table('marketplace_order_inventory')->where('order_id', $order->id)->value('terminal_status');
        if ($terminal !== null && $terminal !== $target) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
        if ($target === 'waiting') return 'waiting'; // A cancellation request never releases stock.
        $held = DB::table('stock_allocations')->where('owner_type', 'marketplace_order')->where('owner_id', $order->id)->get()->keyBy('line_key');
        if ($target === 'released' && $held->isEmpty()) return 'released';
        if ($target === 'released') {
            foreach ($held as $allocation) $this->transition((int) $allocation->id, $target);
            return $target;
        }
        $items = $payload['item_list'] ?? null;
        if (!is_array($items) || !array_is_list($items) || !$items) throw new MarketplaceInventoryException('invalid_order_lines');
        $plan = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_int($item['item_id'] ?? null) || $item['item_id'] < 1
                || !is_int($item['model_id'] ?? null) || $item['model_id'] < 0
                || !is_int($item['model_quantity_purchased'] ?? null) || $item['model_quantity_purchased'] < 1) {
                throw new MarketplaceInventoryException('invalid_order_lines');
            }
            $key = $item['item_id'].':'.$item['model_id'];
            if (!empty($item['is_fulfillment_mapping']) || !empty($item['components'])
                || (isset($item['active_qty']) && $item['active_qty'] !== $item['model_quantity_purchased'])
                || !empty($item['cancelled_qty']) || !empty($item['returned_qty'])) {
                throw new MarketplaceInventoryException('unsupported_order_composition');
            }
            if (isset($plan[$key])) throw new MarketplaceInventoryException('duplicate_order_line');
            $allocation = $held->get($key);
            $variantId = $allocation?->product_variant_id ?? DB::table('marketplace_listing_variants as l')
                ->join('marketplace_variant_mappings as m', 'm.listing_variant_id', '=', 'l.id')
                ->where('l.store_id', $order->store_id)->where('l.external_product_id', (string) $item['item_id'])
                ->where('l.external_variant_id', (string) $item['model_id'])->value('m.product_variant_id');
            if (!$variantId) throw new MarketplaceInventoryException('unmapped_order_line');
            if ($allocation && ((int) $allocation->quantity !== $item['model_quantity_purchased'] || $allocation->status === 'released'
                || ($allocation->status === 'consumed' && $target === 'reserved'))) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
            $plan[$key] = ['variant'=>(int) $variantId, 'quantity'=>$item['model_quantity_purchased']];
        }
        if ($held->isNotEmpty() && ($held->count() !== count($plan)
            || array_diff($held->keys()->all(), array_keys($plan)))) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
        uasort($plan, fn ($a, $b) => $a['variant'] <=> $b['variant']);
        foreach ($plan as $key=>$line) {
            $variant = ProductVariant::query()->lockForUpdate()->find($line['variant']);
            if (!$variant || (!$held->has($key) && $variant->stock < $line['quantity'])) throw new MarketplaceInventoryException('insufficient_stock');
            $id = $this->allocations->reserve('marketplace_order', (int) $order->id, $key, $line['variant'], $line['quantity']);
            if ($target === 'consumed') $this->transition($id, $target);
        }
        return $target;
    }

    private function transition(int $id, string $target): void
    {
        $current = DB::table('stock_allocations')->where('id', $id)->value('status');
        if ($current !== 'reserved' && $current !== $target) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
        $target === 'consumed' ? $this->allocations->consume($id) : $this->allocations->release($id);
    }
}
