<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CurrentStockOrderLine
{
    public function __construct(private readonly CurrentStockLedger $ledger) {}

    public function apply(string $channelKey, string $orderReference, string $itemReference,
        int $variantId, int $balanceId, int $orderedQuantity, int $desiredDebitedQuantity,
        int $desiredRestoredQuantity, string $eventKey, bool $terminal = false,
        ?string $orderCreatedAt = null): array
    {
        foreach ([$channelKey, $orderReference, $itemReference, $eventKey] as $value) {
            if (! preg_match('/\A[A-Za-z0-9:_-]{1,100}\z/D', $value)) {
                throw new RuntimeException('Invalid Current Stock order identity.');
            }
        }
        if ($variantId < 1 || $balanceId < 1 || $orderedQuantity < 1
            || $desiredDebitedQuantity < 0 || $desiredDebitedQuantity > $orderedQuantity
            || $desiredRestoredQuantity < 0 || $desiredRestoredQuantity > $desiredDebitedQuantity) {
            throw new RuntimeException('Invalid Current Stock order quantities.');
        }
        $historical = $orderCreatedAt !== null
            && app(CurrentStockCutover::class)->isHistorical($channelKey, $orderReference, $orderCreatedAt, $balanceId);
        // A pre-baseline active order is already reflected in the imported provider
        // available quantity. Seed its debit without a JW movement so a later
        // cancellation can restore it once. A first-seen terminal order remains 0/0.
        $hash = hash('sha256', json_encode([$channelKey, $orderReference, $itemReference, $variantId,
            $balanceId, $orderedQuantity, $desiredDebitedQuantity, $desiredRestoredQuantity, $terminal], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($channelKey, $orderReference, $itemReference, $variantId,
            $balanceId, $orderedQuantity, $desiredDebitedQuantity, $desiredRestoredQuantity,
            $eventKey, $terminal, $hash, $historical): array {
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->first();
            if (! $balance || (int) $balance->product_variant_id !== $variantId) {
                throw new RuntimeException('Current Stock order owner does not match SKU.');
            }
            $key = ['channel_key' => $channelKey, 'order_reference' => $orderReference,
                'item_reference' => $itemReference];
            DB::table('inventory_current_stock_order_lines')->insertOrIgnore($key + [
                'product_variant_id' => $variantId, 'inventory_balance_id' => $balanceId,
                'ordered_quantity' => $orderedQuantity,
                'deducted_quantity' => $historical && ! $terminal ? $orderedQuantity : 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $line = DB::table('inventory_current_stock_order_lines')->where($key)->lockForUpdate()->first();
            if ((int) $line->product_variant_id !== $variantId || (int) $line->inventory_balance_id !== $balanceId
                || (int) $line->ordered_quantity !== $orderedQuantity) {
                throw new RuntimeException('Current Stock order identity conflicts with an earlier line.');
            }
            $priorEvent = DB::table('inventory_current_stock_order_events')->where('event_key', $eventKey)->first();
            if ($priorEvent) {
                if ((int) $priorEvent->order_line_id !== (int) $line->id
                    || ! hash_equals($priorEvent->request_hash, $hash)) {
                    throw new RuntimeException('Current Stock order event conflicts with an earlier event.');
                }

                return json_decode($priorEvent->result, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($line->terminal && ($desiredDebitedQuantity > (int) $line->deducted_quantity
                || $desiredRestoredQuantity > (int) $line->restored_quantity)) {
                throw new RuntimeException('Terminal Current Stock order cannot reopen.');
            }
            if ($desiredDebitedQuantity < (int) $line->deducted_quantity
                || $desiredRestoredQuantity < (int) $line->restored_quantity) {
                throw new RuntimeException('Current Stock order cumulative quantity cannot decrease.');
            }
            $debit = $desiredDebitedQuantity - (int) $line->deducted_quantity;
            $credit = $desiredRestoredQuantity - (int) $line->restored_quantity;
            if ($debit !== 0 || $credit !== 0) {
                $state = $channelKey === 'website' && $balance->current_stock === null
                    ? app(CatalogInventoryState::class)->snapshot($variantId) : null;
                $opening = $state && $state['initialized'] && !$state['verified'] && !$state['repair_required'];
                if ($opening) {
                    $after = (int) $balance->physical_stock + $credit - $debit;
                    if ($after < (int) $balance->reserved_stock + (int) $balance->safety_stock) {
                        throw new RuntimeException('Catalog opening stock is insufficient.');
                    }
                    DB::table('inventory_balances')->where('id', $balanceId)->update([
                        'physical_stock' => $after, 'version' => (int) $balance->version + 1, 'updated_at' => now(),
                    ]);
                    $current = $after;
                    app(CatalogStockProjection::class)->refresh($variantId);
                } else {
                $movement = $this->ledger->change($balanceId, $credit - $debit,
                    'order-line:'.$line->id.':'.$desiredDebitedQuantity.':'.$desiredRestoredQuantity,
                    'Order line '.$line->id);
                $current = $movement['after'];
                DB::table('inventory_current_stock_sync_intents')->insertOrIgnore([
                    'inventory_balance_id' => $balanceId,
                    'balance_version' => $movement['balance_version'],
                    'event_key' => 'current-order-line:'.$line->id.':'.$desiredDebitedQuantity.':'.$desiredRestoredQuantity,
                    'status' => 'pending', 'attempts' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $pool = DB::table('inventory_pools')->where('id', $balance->inventory_pool_id)->first();
                if ($pool?->kind === 'shared' || $pool?->pool_key === 'store:website') {
                    app(CatalogStockProjection::class)->refresh($variantId);
                }
                }
            } else {
                $state = $channelKey === 'website' && $balance->current_stock === null
                    ? app(CatalogInventoryState::class)->snapshot($variantId) : null;
                $current = $state && $state['initialized'] && !$state['verified'] && !$state['repair_required']
                    ? $state['available'] : $this->ledger->quantity($balanceId);
            }
            DB::table('inventory_current_stock_order_lines')->where('id', $line->id)->update([
                'deducted_quantity' => $desiredDebitedQuantity,
                'restored_quantity' => $desiredRestoredQuantity,
                'terminal' => $line->terminal || $terminal,
                'updated_at' => now(),
            ]);
            $result = ['line_id' => (int) $line->id, 'current_stock' => $current,
                'stock_source' => $channelKey === 'website' && $balance->current_stock === null ? 'catalog_opening' : 'current_stock',
                'deducted_quantity' => $desiredDebitedQuantity,
                'restored_quantity' => $desiredRestoredQuantity,
                'replayed' => $debit === 0 && $credit === 0,
                'status' => $historical && $debit === 0 && $credit === 0 ? 'historical' : 'applied'];
            DB::table('inventory_current_stock_order_events')->insert([
                'order_line_id' => $line->id, 'event_key' => $eventKey,
                'request_hash' => $hash, 'result' => json_encode($result, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $result;
        }, 3);
    }
}
