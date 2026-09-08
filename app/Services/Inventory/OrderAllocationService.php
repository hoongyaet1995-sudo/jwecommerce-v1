<?php

namespace App\Services\Inventory;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderAllocationService
{
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
                } elseif (DB::table('inventory_configuration')->where('id', 1)->value('accounting_enabled')) {
                    throw new InvalidArgumentException('Existing order requires warehouse reconciliation before dispatch.');
                }
            }
        });
    }
}
