<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderInventoryRouter
{
    public function __construct(private readonly AccountResolver $accounts) {}

    public function register(string $channelKey, string $orderLineKey, int $variantId, int $orderedQuantity): array
    {
        if (! preg_match('/^[A-Za-z0-9:_-]{1,160}$/D', $orderLineKey) || $orderedQuantity < 1) {
            throw new RuntimeException('Invalid inventory order line.');
        }
        $balance = $this->accounts->balance($variantId, $channelKey);

        return DB::transaction(function () use ($channelKey, $orderLineKey, $variantId, $orderedQuantity, $balance): array {
            DB::table('inventory_store_order_lines')->insertOrIgnore([
                'line_key' => $orderLineKey, 'channel_key' => $channelKey,
                'product_variant_id' => $variantId, 'inventory_balance_id' => $balance['id'],
                'ordered_quantity' => $orderedQuantity, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $line = DB::table('inventory_store_order_lines')->where('line_key', $orderLineKey)->lockForUpdate()->first();
            if (! $line || $line->channel_key !== $channelKey || (int) $line->product_variant_id !== $variantId
                || (int) $line->inventory_balance_id !== $balance['id'] || (int) $line->ordered_quantity !== $orderedQuantity) {
                throw new RuntimeException('Inventory order line identity conflicts with an existing order.');
            }

            return $this->lineState($line);
        }, 3);
    }

    public function apply(string $channelKey, string $orderLineKey, string $eventKey, string $event, int $cumulativeQuantity): array
    {
        if (! preg_match('/^[A-Za-z0-9:_-]{1,160}$/D', $eventKey) || $cumulativeQuantity < 0
            || ! in_array($event, ['reserved', 'shipped', 'cancelled', 'refunded', 'returned_inspected'], true)) {
            throw new RuntimeException('Invalid inventory lifecycle event.');
        }
        $hash = hash('sha256', json_encode([$channelKey, $orderLineKey, $event, $cumulativeQuantity], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($channelKey, $orderLineKey, $eventKey, $event, $cumulativeQuantity, $hash): array {
            DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $existing = DB::table('inventory_store_order_events')->where('event_key', $eventKey)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals((string) $existing->payload_hash, $hash)) {
                    throw new RuntimeException('Inventory event key conflicts with another payload.');
                }

                return json_decode($existing->result, true, 512, JSON_THROW_ON_ERROR);
            }
            $identity = DB::table('inventory_store_order_lines')->where('line_key', $orderLineKey)->first();
            if (! $identity || $identity->channel_key !== $channelKey) {
                throw new RuntimeException('Inventory order line is not registered for this account.');
            }
            DB::table('product_variants')->where('id', $identity->product_variant_id)->lockForUpdate()->first();
            $line = DB::table('inventory_store_order_lines')->where('id', $identity->id)->lockForUpdate()->first();
            $balance = DB::table('inventory_balances')->where('id', $line->inventory_balance_id)->lockForUpdate()->first();
            if (! $balance || $balance->physical_stock === null || $balance->reserved_stock === null || $balance->safety_stock === null) {
                throw new RuntimeException('JW inventory requires a verified physical count.');
            }

            $physical = (int) $balance->physical_stock;
            $reserved = (int) $balance->reserved_stock;
            $lineReserved = (int) $line->reserved_quantity;
            $shipped = (int) $line->shipped_quantity;
            $cancelled = (int) $line->cancelled_quantity;
            $refunded = (int) $line->refunded_quantity;
            $returned = (int) $line->returned_quantity;
            $ordered = (int) $line->ordered_quantity;

            if ($event === 'reserved') {
                $desired = min($cumulativeQuantity, max(0, $ordered - $shipped - $cancelled));
                $delta = $desired - $lineReserved;
                if ($delta > 0 && $physical - $reserved - (int) $balance->safety_stock < $delta) {
                    throw new RuntimeException('JW inventory is insufficient for this store account.');
                }
                $reserved += $delta;
                $lineReserved = $desired;
            } elseif ($event === 'shipped') {
                if ($cumulativeQuantity > $ordered - $cancelled) {
                    throw new RuntimeException('Shipped quantity exceeds the active order quantity.');
                }
                $delta = max(0, $cumulativeQuantity - $shipped);
                $release = min($delta, $lineReserved);
                $physical -= $delta;
                $reserved -= $release;
                $lineReserved -= $release;
                $shipped = max($shipped, $cumulativeQuantity);
            } elseif ($event === 'cancelled') {
                if ($cumulativeQuantity > $ordered - $shipped) {
                    throw new RuntimeException('Cancelled quantity exceeds the unshipped order quantity.');
                }
                $delta = max(0, $cumulativeQuantity - $cancelled);
                $release = min($delta, $lineReserved);
                $reserved -= $release;
                $lineReserved -= $release;
                $cancelled = max($cancelled, $cumulativeQuantity);
            } elseif ($event === 'refunded') {
                if ($cumulativeQuantity > $shipped) {
                    throw new RuntimeException('Refunded quantity exceeds shipped quantity.');
                }
                $refunded = max($refunded, $cumulativeQuantity);
            } else {
                if ($cumulativeQuantity > $shipped) {
                    throw new RuntimeException('Returned quantity exceeds shipped quantity.');
                }
                $delta = max(0, $cumulativeQuantity - $returned);
                $physical += $delta;
                $returned = max($returned, $cumulativeQuantity);
            }
            if ($physical < 0 || $reserved < 0) {
                throw new RuntimeException('Inventory lifecycle would create a negative ledger quantity.');
            }

            $balanceVersion = (int) $balance->version + 1;
            DB::table('inventory_balances')->where('id', $balance->id)->update([
                'physical_stock' => $physical, 'reserved_stock' => $reserved,
                'version' => $balanceVersion, 'updated_at' => now(),
            ]);
            DB::table('inventory_store_order_lines')->where('id', $line->id)->update([
                'reserved_quantity' => $lineReserved, 'shipped_quantity' => $shipped,
                'cancelled_quantity' => $cancelled, 'refunded_quantity' => $refunded,
                'returned_quantity' => $returned, 'updated_at' => now(),
            ]);
            $result = ['line_key' => $orderLineKey, 'event' => $event, 'cumulative_quantity' => $cumulativeQuantity,
                'physical' => $physical, 'reserved' => $reserved,
                'available' => max(0, $physical - $reserved - (int) $balance->safety_stock),
                'balance_version' => $balanceVersion,
                'line' => ['ordered' => $ordered, 'reserved' => $lineReserved, 'shipped' => $shipped,
                    'cancelled' => $cancelled, 'refunded' => $refunded, 'returned' => $returned]];
            DB::table('inventory_store_order_events')->insert([
                'event_key' => $eventKey, 'order_line_key' => $orderLineKey, 'channel_key' => $channelKey,
                'event' => $event, 'cumulative_quantity' => $cumulativeQuantity,
                'order_line_id' => $line->id, 'payload_hash' => $hash,
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $result;
        }, 3);
    }

    private function lineState(object $line): array
    {
        return ['id' => (int) $line->id, 'line_key' => $line->line_key, 'channel_key' => $line->channel_key,
            'variant_id' => (int) $line->product_variant_id, 'balance_id' => (int) $line->inventory_balance_id,
            'ordered' => (int) $line->ordered_quantity];
    }
}
