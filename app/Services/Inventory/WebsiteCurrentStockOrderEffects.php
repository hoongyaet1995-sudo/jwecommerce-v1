<?php

namespace App\Services\Inventory;

use App\Models\Order;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WebsiteCurrentStockOrderEffects
{
    public function __construct(
        private readonly CurrentStockOrderLine $lines,
        private readonly AccountResolver $accounts,
    ) {}

    public function debit(Order $order): void
    {
        foreach ($order->items()->orderBy('id')->get() as $item) {
            if (! $item->product_variant_id) {
                throw new RuntimeException('Order item has no inventory SKU.');
            }
            $balance = $this->accounts->balance((int) $item->product_variant_id, 'website');
            $this->lines->apply('website', (string) $order->id, (string) $item->id,
                (int) $item->product_variant_id, (int) $balance['id'], (int) $item->quantity,
                (int) $item->quantity, 0, 'website:'.$order->id.':'.$item->id.':placed',
                false, (string) $order->created_at);
        }
    }

    public function restore(Order $order, array $quantities, string $eventReference): void
    {
        foreach ($quantities as $itemId => $quantity) {
            $item = $order->items()->whereKey((int) $itemId)->firstOrFail();
            $line = DB::table('inventory_current_stock_order_lines')
                ->where('channel_key', 'website')->where('order_reference', (string) $order->id)
                ->where('item_reference', (string) $item->id)->first();
            if (! $line) {
                // A preserved pre-cutover order must not manufacture stock on cancellation/refund.
                continue;
            }
            $desired = (int) $line->restored_quantity + (int) $quantity;
            $this->lines->apply('website', (string) $order->id, (string) $item->id,
                (int) $item->product_variant_id, (int) $line->inventory_balance_id,
                (int) $item->quantity, (int) $line->deducted_quantity, $desired,
                'website:'.$order->id.':'.$item->id.':'.$eventReference,
                $desired === (int) $item->quantity, (string) $order->created_at);
        }
    }

    public function remaining(Order $order): array
    {
        $remaining = [];
        foreach ($order->items()->orderBy('id')->get() as $item) {
            $line = DB::table('inventory_current_stock_order_lines')
                ->where('channel_key', 'website')->where('order_reference', (string) $order->id)
                ->where('item_reference', (string) $item->id)->first();
            if ($line) {
                $quantity = (int) $line->deducted_quantity - (int) $line->restored_quantity;
                if ($quantity > 0) {
                    $remaining[$item->id] = $quantity;
                }
            }
        }

        return $remaining;
    }
}
