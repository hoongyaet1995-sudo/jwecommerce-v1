<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ModeMutation
{
    public function __construct(private ReadinessReader $readiness, private AccountResolver $accounts, private SyncOutbox $outbox) {}

    public function switch(string $target, array $distribution, int $expectedVersion, User $actor): array
    {
        abort_unless($actor->is_admin && $actor->is_super_admin, 403);
        if (! in_array($target, ['shared_pool', 'independent_stores'], true)) {
            throw ValidationException::withMessages(['target' => 'Choose Shared or Independent Stock.']);
        }

        return DB::transaction(function () use ($target, $distribution, $expectedVersion): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if ((int) $configuration->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Inventory settings changed. Review again.']);
            }
            $ready = $this->readiness->mode();
            if (! $ready['ready']) {
                throw ValidationException::withMessages(['mode' => collect($ready['blockers'])->pluck('code')->all()]);
            }
            if ($target === $configuration->operating_mode) {
                throw ValidationException::withMessages(['target' => 'This inventory mode is already active.']);
            }
            $beforeTotal = (int) DB::table('inventory_balances')->whereNotNull('counted_at')->sum('physical_stock');
            if ($target === 'shared_pool') {
                $this->mergeToShared();
            } else {
                $this->splitToStores($distribution, $expectedVersion);
            }
            $afterTotal = (int) DB::table('inventory_balances')->whereNotNull('counted_at')->sum('physical_stock');
            if ($beforeTotal !== $afterTotal) {
                throw ValidationException::withMessages(['distribution' => 'Mode conversion must conserve verified physical stock.']);
            }
            DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => $target, 'version' => $expectedVersion + 1]);
            $stages = [];
            $managedStoreIds = DB::table('inventory_store_accounts')->where('management_status', 'managed')->whereNotNull('marketplace_store_id')->pluck('marketplace_store_id');
            $balances = DB::table('inventory_balances as b')->join('inventory_pools as p', 'p.id', '=', 'b.inventory_pool_id')
                ->whereNotNull('b.counted_at')->whereIn('p.marketplace_store_id', $managedStoreIds)->select('b.*')->get();
            foreach ($balances as $balance) {
                $stages = [...$stages, ...$this->outbox->enqueueMutation(null, $balance, 'mode', 'mode:'.($expectedVersion + 1).':'.$balance->id.':'.$balance->version)];
            }

            return ['operating_mode' => $target, 'configuration_version' => $expectedVersion + 1, 'physical_total' => $afterTotal, 'external_stages' => $stages];
        }, 3);
    }

    private function mergeToShared(): void
    {
        DB::table('inventory_pools')->insertOrIgnore(['pool_key' => 'shared', 'kind' => 'shared', 'warehouse_id' => null,
            'marketplace_store_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $shared = DB::table('inventory_pools')->where('pool_key', 'shared')->lockForUpdate()->first();
        $rows = DB::table('inventory_balances')->whereNotNull('counted_at')->orderBy('id')->lockForUpdate()->get()->groupBy('product_variant_id');
        foreach ($rows as $variantId => $balances) {
            $physical = (int) $balances->sum('physical_stock');
            $reserved = (int) $balances->sum('reserved_stock');
            $safety = (int) $balances->sum('safety_stock');
            $existing = DB::table('inventory_balances')->where('inventory_pool_id', $shared->id)->where('product_variant_id', $variantId)->first();
            if ($existing) {
                DB::table('inventory_balances')->where('id', $existing->id)->update([
                    'physical_stock' => $physical, 'reserved_stock' => $reserved, 'safety_stock' => $safety, 'counted_at' => now(),
                    'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            } else {
                DB::table('inventory_balances')->insert(['inventory_pool_id' => $shared->id, 'product_variant_id' => $variantId,
                    'physical_stock' => $physical, 'reserved_stock' => $reserved, 'safety_stock' => $safety, 'counted_at' => now(),
                    'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('inventory_balances')->where('product_variant_id', $variantId)->where('inventory_pool_id', '!=', $shared->id)
                ->update(['physical_stock' => null, 'reserved_stock' => null, 'safety_stock' => null, 'counted_at' => null, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        }
    }

    private function splitToStores(array $distribution, int $expectedVersion): void
    {
        $shared = DB::table('inventory_pools')->where('pool_key', 'shared')->first();
        $balances = $shared ? DB::table('inventory_balances')->where('inventory_pool_id', $shared->id)->whereNotNull('counted_at')->lockForUpdate()->get() : collect();
        foreach ($balances as $balance) {
            if ((int) ($balance->reserved_stock ?? 0) > 0) {
                throw ValidationException::withMessages(['distribution' => 'Release or explicitly reconcile shared reservations before splitting stock.']);
            }
            $allocations = $distribution[(string) $balance->product_variant_id] ?? $distribution[$balance->product_variant_id] ?? null;
            if (! is_array($allocations) || array_sum(array_map('intval', $allocations)) !== (int) $balance->physical_stock) {
                throw ValidationException::withMessages(['distribution' => 'Allocate the complete shared physical quantity for every SKU.']);
            }
            DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores', 'version' => $expectedVersion]);
            foreach ($allocations as $channel => $quantity) {
                $resolved = $this->accounts->balance((int) $balance->product_variant_id, (string) $channel);
                DB::table('inventory_balances')->where('id', $resolved['id'])->update(['physical_stock' => (int) $quantity, 'reserved_stock' => 0,
                    'safety_stock' => 0, 'counted_at' => now(), 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            }
            DB::table('inventory_balances')->where('id', $balance->id)->update(['physical_stock' => null, 'reserved_stock' => null, 'safety_stock' => null,
                'counted_at' => null, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        }
    }
}
