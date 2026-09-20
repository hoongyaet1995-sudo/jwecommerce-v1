<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class ReadinessReader
{
    public function takeover(int $storeId): array
    {
        $store = DB::table('marketplace_stores')->find($storeId);
        $blockers = [];
        if (! $store || ! in_array($store->platform ?? null, ['shopee', 'lazada'], true)) $blockers[] = ['code' => 'store_not_found', 'next_action' => 'Choose a Shopee or Lazada store.'];
        elseif ($store->status !== 'connected') $blockers[] = ['code' => 'connection_'.$store->status, 'next_action' => 'Reconnect this store.'];
        $mappings = DB::table('marketplace_variant_mappings')->where('store_id', $storeId)->get();
        if ($mappings->isEmpty()) $blockers[] = ['code' => 'no_mapped_skus', 'next_action' => 'Map at least one marketplace SKU to a JW Master SKU.'];
        $balances = [];
        foreach ($mappings as $mapping) {
            $pool = DB::table('inventory_pools')->where('marketplace_store_id', $storeId)->first();
            $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                ->where('product_variant_id', $mapping->product_variant_id)->first() : null;
            if (! $balance || $balance->counted_at === null || $balance->physical_stock === null) {
                $blockers[] = ['code' => 'initial_count_required', 'variant_id' => (int) $mapping->product_variant_id,
                    'next_action' => 'Enter and review the physical count for this store SKU.'];
            } else $balances[] = $balance;
        }
        return ['ready' => $blockers === [], 'store' => $store, 'balances' => $balances, 'blockers' => $blockers];
    }

    public function mode(): array
    {
        $blockers = [];
        $pending = DB::table('inventory_store_sync_tasks')->whereNotIn('status', ['confirmed', 'not_applicable'])->pluck('id');
        if ($pending->isNotEmpty()) $blockers[] = ['code' => 'pending_sync', 'ids' => $pending->all(), 'next_action' => 'Resolve inventory sync stages first.'];
        return ['ready' => $blockers === [], 'blockers' => $blockers];
    }
}
