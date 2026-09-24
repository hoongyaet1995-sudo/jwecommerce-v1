<?php

namespace App\Services\Inventory;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderAllocationService
{
    public function reservePaid(Order $order): void
    {
        DB::transaction(function() use($order){
            $locked=Order::query()->lockForUpdate()->findOrFail($order->id);
            if (!in_array($locked->status,['paid','processing'],true)) return;
            try {
                DB::transaction(function() use($locked){
                    foreach ($locked->items()->orderBy('product_variant_id')->orderBy('id')->get() as $item) {
                        if (!$item->inventory_order_activation_id) continue;
                        app(StockAllocationService::class)->reserve('order',(int)$locked->id,'item:'.$item->id,
                            (int)$item->product_variant_id,(int)$item->quantity,(int)$item->inventory_order_activation_id);
                    }
                });
                DB::table('orders')->where('id',$locked->id)->update(['inventory_error'=>null]);
            } catch (WarehouseAllocationException $error) {
                // A successful payment remains successful; no partial line reservation survives.
                DB::table('orders')->where('id',$locked->id)->update(['inventory_error'=>'managed_stock_unavailable']);
            }
        });
    }

    public function consume(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            foreach ($locked->items()->orderBy('product_variant_id')->orderBy('id')->get() as $item) {
                if (!$item->product_variant_id) {
                    continue;
                }
                $allocation = DB::table('stock_allocations')->where('owner_type', 'order')
                    ->where('owner_id', $locked->id)->where('line_key', 'item:'.$item->id)->first();
                if ($allocation) {
                    app(StockAllocationService::class)->consume((int) $allocation->id);
                } elseif ($item->inventory_order_activation_id || DB::table('inventory_configuration')->where('id', 1)->value('accounting_enabled')) {
                    throw new InvalidArgumentException('Existing order requires warehouse reconciliation before dispatch.');
                }
            }
        });
    }
}
