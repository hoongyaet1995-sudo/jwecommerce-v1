<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class ReadinessReader
{
    public function takeover(int $storeId): array
    {
        $store = DB::table('marketplace_stores')->find($storeId);
        $blockers = [];
        if (! $store || ! in_array($store->platform ?? null, ['shopee', 'lazada'], true)) {
            $blockers[] = ['code' => 'store_not_found', 'next_action' => 'Choose a Shopee or Lazada store.'];
        } elseif ($store->status !== 'connected') {
            $blockers[] = ['code' => 'connection_'.$store->status, 'next_action' => 'Reconnect this store.'];
        }
        $mappings = DB::table('marketplace_variant_mappings')->where('store_id', $storeId)->get();
        if ($mappings->isEmpty()) {
            $blockers[] = ['code' => 'no_mapped_skus', 'next_action' => 'Map at least one marketplace SKU to a JW Master SKU.'];
        }
        $unmapped = DB::table('marketplace_listing_variants as l')->leftJoin('marketplace_variant_mappings as m', 'm.listing_variant_id', '=', 'l.id')
            ->where('l.store_id', $storeId)->whereNull('m.id')->pluck('l.id');
        if ($unmapped->isNotEmpty()) {
            $blockers[] = ['code' => 'unmapped_listings', 'ids' => $unmapped->all(), 'next_action' => 'Map every active store listing before takeover.'];
        }
        $ambiguous = $mappings->groupBy('listing_variant_id')->filter(fn ($rows) => $rows->count() !== 1)->keys();
        if ($ambiguous->isNotEmpty()) {
            $blockers[] = ['code' => 'ambiguous_mappings', 'ids' => $ambiguous->all(), 'next_action' => 'Resolve duplicate SKU mappings.'];
        }
        $missingSnapshots = DB::table('marketplace_listing_variants')->where('store_id', $storeId)
            ->where(fn ($q) => $q->whereNull('observed_data')->orWhereNull('fetched_at'))->pluck('id');
        if ($missingSnapshots->isNotEmpty()) {
            $blockers[] = ['code' => 'provider_snapshot_required', 'ids' => $missingSnapshots->all(), 'next_action' => 'Fetch the current marketplace catalog before takeover.'];
        }
        $openOrders = DB::table('marketplace_orders as o')->leftJoin('marketplace_order_inventory as i', 'i.order_id', '=', 'o.id')
            ->where('o.store_id', $storeId)->whereNull('i.terminal_status')->pluck('o.id');
        if ($openOrders->isNotEmpty()) {
            $blockers[] = ['code' => 'historical_orders_unresolved', 'ids' => $openOrders->all(), 'next_action' => 'Reconcile open marketplace orders and capture the ingestion boundary.'];
        }
        $balances = [];
        foreach ($mappings as $mapping) {
            $pool = DB::table('inventory_pools')->where('marketplace_store_id', $storeId)->first();
            $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                ->where('product_variant_id', $mapping->product_variant_id)->first() : null;
            if (! $balance || $balance->counted_at === null || $balance->physical_stock === null) {
                $blockers[] = ['code' => 'initial_count_required', 'variant_id' => (int) $mapping->product_variant_id,
                    'next_action' => 'Enter and review the physical count for this store SKU.'];
            } else {
                $balances[] = $balance;
            }
        }

        return ['ready' => $blockers === [], 'store' => $store, 'balances' => $balances, 'blockers' => $blockers];
    }

    public function mode(): array
    {
        $blockers = [];
        $pending = DB::table('inventory_store_sync_tasks')->whereNotIn('status', ['confirmed', 'not_applicable'])->pluck('id');
        if ($pending->isNotEmpty()) {
            $blockers[] = ['code' => 'pending_sync', 'ids' => $pending->all(), 'next_action' => 'Resolve inventory sync stages first.'];
        }
        $activeBalances = fn () => DB::table('inventory_balances as b')->join('inventory_pools as p', 'p.id', '=', 'b.inventory_pool_id')
            ->leftJoin('inventory_store_accounts as a', 'a.marketplace_store_id', '=', 'p.marketplace_store_id')
            ->where(fn ($query) => $query->where('p.kind', 'shared')->orWhere(fn ($store) => $store->where('p.kind', 'store')
                ->where(fn ($managed) => $managed->whereNull('p.marketplace_store_id')->orWhere('a.management_status', 'managed'))));
        $reservations = $activeBalances()->where('b.reserved_stock', '>', 0)->pluck('b.id');
        if ($reservations->isNotEmpty()) {
            $blockers[] = ['code' => 'active_reservations', 'ids' => $reservations->all(), 'next_action' => 'Complete or release active order reservations first.'];
        }
        $unknown = $activeBalances()->whereNull('b.counted_at')->pluck('b.id');
        if ($unknown->isNotEmpty()) {
            $blockers[] = ['code' => 'unknown_balances', 'ids' => $unknown->all(), 'next_action' => 'Complete first counts for every active account.'];
        }
        $safety = $activeBalances()->where('b.safety_stock', '>', 0)->pluck('b.id');
        if ($safety->isNotEmpty()) {
            $blockers[] = ['code' => 'legacy_safety_stock', 'ids' => $safety->all(), 'next_action' => 'Reconcile legacy safety buffers explicitly.'];
        }
        $openTransfers = DB::table('inventory_store_operations')->where('kind', 'transfer')->where('ledger_status', '!=', 'applied')->pluck('id');
        if ($openTransfers->isNotEmpty()) {
            $blockers[] = ['code' => 'unfinished_transfers', 'ids' => $openTransfers->all(), 'next_action' => 'Resolve unfinished transfer operations.'];
        }

        return ['ready' => $blockers === [], 'blockers' => $blockers];
    }
}
