<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CurrentStockLedger
{
    public function quantity(int $balanceId): ?int
    {
        $quantity = DB::table('inventory_balances')->where('id', $balanceId)->value('current_stock');

        return $quantity === null ? null : (int) $quantity;
    }

    public function setAbsolute(int $balanceId, int $quantity, string $eventKey, string $reason): array
    {
        return $this->apply($balanceId, $quantity, true, $eventKey, $reason);
    }

    public function change(int $balanceId, int $delta, string $eventKey, string $reason): array
    {
        return $this->apply($balanceId, $delta, false, $eventKey, $reason);
    }

    private function apply(int $balanceId, int $amount, bool $absolute, string $eventKey, string $reason): array
    {
        if ($balanceId < 1 || ! preg_match('/\A[A-Za-z0-9:_-]{1,160}\z/D', $eventKey)
            || trim($reason) === '' || strlen($reason) > 255
            || ($absolute && ($amount < 0 || $amount > 2147483647))
            || (! $absolute && abs($amount) > 2147483647)) {
            throw new RuntimeException('Invalid Current Stock change.');
        }
        $hash = hash('sha256', json_encode([$balanceId, $amount, $absolute, $reason], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($balanceId, $amount, $absolute, $eventKey, $reason, $hash): array {
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->first();
            if (! $balance) {
                throw new RuntimeException('Current Stock location does not exist.');
            }
            $previous = DB::table('inventory_current_stock_movements')->where('event_key', $eventKey)->first();
            if ($previous) {
                if ((int) $previous->inventory_balance_id !== $balanceId
                    || ! hash_equals($previous->request_hash, $hash)) {
                    throw new RuntimeException('Current Stock event key conflicts with an earlier change.');
                }

                return ['before' => (int) $previous->current_after - (int) $previous->delta,
                    'after' => (int) $previous->current_after,
                    'balance_version' => (int) $previous->balance_version, 'replayed' => true];
            }
            if (! $absolute && $balance->current_stock === null) {
                throw new RuntimeException('First count Current Stock before changing it.');
            }
            $before = $balance->current_stock === null ? null : (int) $balance->current_stock;
            $after = $absolute ? $amount : $before + $amount;
            if ($after < 0 || $after > 2147483647) {
                throw new RuntimeException('Current Stock would exceed its valid range.');
            }
            $delta = $after - ($before ?? 0);
            $version = (int) $balance->version + 1;
            DB::table('inventory_balances')->where('id', $balanceId)->update([
                'current_stock' => $after, 'version' => $version,
                'counted_at' => $balance->counted_at ?? now(), 'updated_at' => now(),
            ]);
            DB::table('inventory_current_stock_movements')->insert([
                'inventory_balance_id' => $balanceId, 'event_key' => $eventKey,
                'request_hash' => $hash, 'delta' => $delta, 'current_after' => $after,
                'balance_version' => $version, 'reason' => $reason, 'created_at' => now(),
            ]);

            return ['before' => $before, 'after' => $after, 'balance_version' => $version, 'replayed' => false];
        }, 3);
    }
}
