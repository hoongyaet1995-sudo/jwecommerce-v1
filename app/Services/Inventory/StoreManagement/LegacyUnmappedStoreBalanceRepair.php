<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LegacyUnmappedStoreBalanceRepair
{
    /** @return array{candidates:int,balance_ids:list<int>,phantom_units:int} */
    public function inspect(): array
    {
        $rows = $this->candidates()->get(['balance.id', 'balance.physical_stock']);

        return [
            'candidates' => $rows->count(),
            'balance_ids' => $rows->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'phantom_units' => (int) $rows->sum('physical_stock'),
        ];
    }

    /** @return array{repaired:int,balance_ids:list<int>,removed_phantom_units:int} */
    public function apply(User $actor): array
    {
        abort_unless($actor->exists && $actor->is_admin && $actor->is_super_admin, 403);

        return DB::transaction(function () use ($actor): array {
            $candidateIds = $this->candidates()->pluck('balance.id')->map(fn ($id): int => (int) $id);
            $balances = DB::table('inventory_balances')->whereIn('id', $candidateIds)->orderBy('id')->lockForUpdate()->get();
            $removed = 0;
            $repaired = [];
            foreach ($balances as $balance) {
                $operationKey = 'repair-unmapped-legacy:'.$balance->id.':v'.$balance->version;
                if (DB::table('inventory_reconciliations')->where('operation_key', $operationKey)->exists()) {
                    continue;
                }
                $before = ['physical' => (int) $balance->physical_stock, 'reserved' => 0, 'safety' => 0, 'version' => (int) $balance->version];
                $after = ['physical' => 0, 'reserved' => 0, 'safety' => 0, 'version' => (int) $balance->version + 1];
                DB::table('inventory_balances')->where('id', $balance->id)->update([
                    'physical_stock' => 0, 'reserved_stock' => 0, 'safety_stock' => 0,
                    'version' => DB::raw('version + 1'), 'updated_at' => now(),
                ]);
                DB::table('inventory_reconciliations')->insert([
                    'inventory_balance_id' => $balance->id, 'actor_id' => $actor->id,
                    'operation_key' => $operationKey, 'request_hash' => hash('sha256', json_encode($before, JSON_THROW_ON_ERROR)),
                    'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR),
                    'reason' => 'Repair legacy mode split for unmapped marketplace store', 'created_at' => now(),
                ]);
                $removed += (int) $balance->physical_stock;
                $repaired[] = (int) $balance->id;
            }

            return ['repaired' => count($repaired), 'balance_ids' => $repaired, 'removed_phantom_units' => $removed];
        }, 3);
    }

    private function candidates()
    {
        return DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'store')->whereNotNull('pool.marketplace_store_id')
            ->where('balance.physical_stock', '>', 0)->where('balance.reserved_stock', 0)->where('balance.safety_stock', 0)
            ->where('balance.version', 1)->whereColumn('balance.counted_at', 'balance.created_at')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('marketplace_variant_mappings as mapping')
                ->whereColumn('mapping.product_variant_id', 'balance.product_variant_id')
                ->whereColumn('mapping.store_id', 'pool.marketplace_store_id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('inventory_physical_movements as movement')
                ->whereColumn('movement.inventory_balance_id', 'balance.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('inventory_reconciliations as reconciliation')
                ->whereColumn('reconciliation.inventory_balance_id', 'balance.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('stock_allocations as allocation')
                ->whereColumn('allocation.inventory_balance_id', 'balance.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('inventory_transfers as transfer')
                ->whereColumn('transfer.source_balance_id', 'balance.id')->orWhereColumn('transfer.destination_balance_id', 'balance.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('inventory_store_sync_tasks as sync')
                ->whereColumn('sync.product_variant_id', 'balance.product_variant_id')
                ->whereColumn('sync.marketplace_store_id', 'pool.marketplace_store_id'));
    }
}
