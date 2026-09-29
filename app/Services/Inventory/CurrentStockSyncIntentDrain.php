<?php

namespace App\Services\Inventory;

use App\Services\Inventory\StoreManagement\SyncOutbox;
use Illuminate\Support\Facades\DB;
use Throwable;

/** A durable bridge from an order ledger movement to the existing absolute-stock outbox. */
final class CurrentStockSyncIntentDrain
{
    public function run(int $limit = 100): array
    {
        $result = ['queued' => 0, 'superseded' => 0, 'pending' => 0];
        if (! config('inventory.current_stock_only')) {
            return $result;
        }
        $ids = DB::table('inventory_current_stock_sync_intents')
            ->where('status', 'pending')->orderBy('id')->limit(max(1, min(500, $limit)))->pluck('id');
        foreach ($ids as $id) {
            try {
                $status = DB::transaction(function () use ($id): ?string {
                    $intent = DB::table('inventory_current_stock_sync_intents')->where('id', $id)->lockForUpdate()->first();
                    if (! $intent || $intent->status !== 'pending') {
                        return null;
                    }
                    $balance = DB::table('inventory_balances')->where('id', $intent->inventory_balance_id)
                        ->lockForUpdate()->first();
                    if (! $balance || $balance->current_stock === null) {
                        throw new \RuntimeException('Current Stock balance is not counted.');
                    }
                    if ((int) $balance->version !== (int) $intent->balance_version) {
                        DB::table('inventory_current_stock_sync_intents')->where('id', $id)
                            ->update(['status' => 'superseded', 'updated_at' => now()]);

                        return 'superseded';
                    }
                    app(SyncOutbox::class)->enqueueMutation(null, $balance, 'order', $intent->event_key);
                    DB::table('inventory_current_stock_sync_intents')->where('id', $id)
                        ->update(['status' => 'queued', 'last_error' => null, 'updated_at' => now()]);

                    return 'queued';
                }, 3);
                if ($status !== null) {
                    $result[$status]++;
                }
            } catch (Throwable $error) {
                DB::table('inventory_current_stock_sync_intents')->where('id', $id)->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => mb_substr($error->getMessage(), 0, 255),
                    'updated_at' => now(),
                ]);
                $result['pending']++;
            }
        }

        return $result;
    }
}
