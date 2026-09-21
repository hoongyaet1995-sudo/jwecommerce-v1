<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class LegacyWarehouseStockNormalizer
{
    /** @return array{moved:int,physical_units:int,conflicts:int,conflict_variant_ids:list<int>} */
    public function normalize(): array
    {
        return DB::transaction(function (): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || $configuration->operating_mode !== 'independent_stores') {
                return ['moved' => 0, 'physical_units' => 0, 'conflicts' => 0, 'conflict_variant_ids' => []];
            }

            DB::table('inventory_store_accounts')->insertOrIgnore([
                'account_key' => 'website', 'marketplace_store_id' => null, 'management_status' => 'managed',
                'version' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('inventory_store_accounts')->where('account_key', 'website')->update([
                'management_status' => 'managed', 'updated_at' => now(),
            ]);
            DB::table('inventory_pools')->insertOrIgnore([
                'pool_key' => 'store:website', 'kind' => 'store', 'warehouse_id' => null,
                'marketplace_store_id' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $websitePool = DB::table('inventory_pools')->where('pool_key', 'store:website')->lockForUpdate()->first();

            $variantIds = DB::table('inventory_balances as balance')
                ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                ->where('pool.kind', 'warehouse')->whereNotNull('balance.counted_at')
                ->where(function ($query): void {
                    $query->where('balance.physical_stock', '>', 0)
                        ->orWhere('balance.reserved_stock', '>', 0)
                        ->orWhere('balance.safety_stock', '>', 0);
                })
                ->distinct()->orderBy('balance.product_variant_id')->pluck('balance.product_variant_id');

            $moved = 0;
            $physicalUnits = 0;
            $conflicts = [];
            foreach ($variantIds as $variantId) {
                $sources = DB::table('inventory_balances as balance')
                    ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                    ->where('pool.kind', 'warehouse')->where('balance.product_variant_id', $variantId)
                    ->whereNotNull('balance.counted_at')->lockForUpdate()
                    ->get(['balance.*']);
                $target = DB::table('inventory_balances')
                    ->where('inventory_pool_id', $websitePool->id)->where('product_variant_id', $variantId)
                    ->lockForUpdate()->first();

                $targetIsEmptyProvision = $target
                    && (int) ($target->physical_stock ?? 0) === 0
                    && (int) ($target->reserved_stock ?? 0) === 0
                    && (int) ($target->safety_stock ?? 0) === 0
                    && (int) $target->version === 0;
                if ($target && $target->counted_at !== null && ! $targetIsEmptyProvision) {
                    $conflicts[] = (int) $variantId;

                    continue;
                }

                $physical = (int) $sources->sum(fn (object $row): int => (int) ($row->physical_stock ?? 0));
                $reserved = (int) $sources->sum(fn (object $row): int => (int) ($row->reserved_stock ?? 0));
                $safety = (int) $sources->sum(fn (object $row): int => (int) ($row->safety_stock ?? 0));
                $countedAt = $sources->max('counted_at');
                $values = [
                    'physical_stock' => $physical, 'reserved_stock' => $reserved, 'safety_stock' => $safety,
                    'counted_at' => $countedAt, 'version' => $target ? (int) $target->version + 1 : 0,
                    'updated_at' => now(),
                ];
                if ($target) {
                    DB::table('inventory_balances')->where('id', $target->id)->update($values);
                } else {
                    DB::table('inventory_balances')->insert($values + [
                        'inventory_pool_id' => $websitePool->id, 'product_variant_id' => $variantId,
                        'created_at' => now(),
                    ]);
                }
                DB::table('inventory_balances')->whereIn('id', $sources->pluck('id'))->update([
                    'physical_stock' => 0, 'reserved_stock' => 0, 'safety_stock' => 0,
                    'version' => DB::raw('version + 1'), 'updated_at' => now(),
                ]);
                $moved++;
                $physicalUnits += $physical;
            }

            return ['moved' => $moved, 'physical_units' => $physicalUnits,
                'conflicts' => count($conflicts), 'conflict_variant_ids' => $conflicts];
        }, 3);
    }
}
