<?php

namespace App\Services\Marketplace;

use App\Models\User;
use App\Services\Inventory\PhysicalBalanceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Stages an immutable WMS stock request; it never contacts a marketplace. */
final class MarketplaceStockSyncTaskService
{
    public function stage(int $storeId, int $balanceId, string $operationKey, User $actor): array
    {
        if (!$actor->exists || !User::query()->whereKey($actor->id)->where('is_admin', true)->exists()) {
            $this->reject('An authenticated inventory administrator is required.');
        }
        if (!preg_match('/\A[A-Za-z0-9:_-]{1,128}\z/', $operationKey)) {
            $this->reject('Invalid stock synchronization identity.');
        }

        return DB::transaction(function () use ($storeId, $balanceId, $operationKey, $actor) {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $managed=DB::table('inventory_order_activations')->where('channel_key','marketplace:'.$storeId)->where('inventory_balance_id',$balanceId)->exists();
            if (!$configuration || (!$managed && !$configuration->accounting_enabled) || !in_array($configuration->operating_mode, ['shared_pool', 'independent_warehouses'], true)) {
                $this->reject('Enable and reconcile WMS accounting before staging a marketplace stock update.');
            }
            $existing = DB::table('marketplace_stock_sync_tasks')->where('operation_key', $operationKey)->first();
            $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (!$store || !in_array($store->platform, ['shopee', 'lazada'], true) || $store->status !== 'connected') {
                $this->reject('A connected Shopee or Lazada store is required.');
            }
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->first();
            if (!$balance) $this->reject('Inventory balance not found.');
            $variant = DB::table('product_variants')->where('id', $balance->product_variant_id)->lockForUpdate()->first();
            $pool = DB::table('inventory_pools')->where('id', $balance->inventory_pool_id)->lockForUpdate()->first();
            $route = DB::table('inventory_fulfillment_routes')->where('channel_key', 'marketplace:'.$storeId)->lockForUpdate()->first();
            if (!$route || !$route->is_enabled || !$route->platform_location_id) {
                $this->reject('Enable a fulfillment route with a platform warehouse location before staging stock.');
            }
            $warehouse = DB::table('inventory_warehouses')->where('id', $route->warehouse_id)->lockForUpdate()->first();
            if (!$warehouse || $warehouse->status !== 'active') $this->reject('The routed warehouse must be active.');
            $expectedPool = $configuration->operating_mode === 'shared_pool' ? 'shared' : 'warehouse';
            if (!$pool || $pool->kind !== $expectedPool || ($expectedPool === 'warehouse' && (int) $pool->warehouse_id !== (int) $route->warehouse_id)) {
                $this->reject('The balance does not own stock for this store route.');
            }
            $projection = $this->projection($balance,$storeId);
            if (!$projection['verified'] || $projection['atp'] === null) $this->reject('A verified warehouse ATP is required.');
            $listing = DB::table('marketplace_listing_variants as l')->join('marketplace_variant_mappings as m', 'm.listing_variant_id', '=', 'l.id')
                ->where('m.store_id', $storeId)->where('m.product_variant_id', $variant->id)->where('l.store_id', $storeId)
                ->select('l.id', 'l.external_product_id', 'l.external_variant_id')->orderBy('l.id')->first();
            if (!$listing) $this->reject('Map this JW SKU to exactly one listing in the selected store before staging stock.');
            $duplicate = DB::table('marketplace_variant_mappings')->where('store_id', $storeId)->where('product_variant_id', $variant->id)
                ->where('listing_variant_id', '!=', $listing->id)->exists();
            if ($duplicate) $this->reject('A store SKU must have exactly one marketplace mapping before staging stock.');

            $snapshot = ['platform'=>$store->platform, 'store_id'=>$storeId, 'external_product_id'=>$listing->external_product_id,
                'external_variant_id'=>$listing->external_variant_id, 'platform_location_id'=>$route->platform_location_id,
                'inventory_balance_id'=>$balanceId, 'balance_version'=>(int) $balance->version, 'route_version'=>(int) $route->version,
                'desired_quantity'=>$projection['atp']];
            $hash = hash('sha256', json_encode($snapshot + ['actor_id'=>(int) $actor->id], JSON_THROW_ON_ERROR));
            if ($existing) {
                if (!hash_equals($existing->request_hash, $hash)) $this->reject('Stock synchronization identity conflicts with a different request.');
                return $this->receipt($existing);
            }
            $id = DB::table('marketplace_stock_sync_tasks')->insertGetId([
                'operation_key'=>$operationKey, 'request_hash'=>$hash, 'store_id'=>$storeId, 'listing_variant_id'=>$listing->id,
                'inventory_balance_id'=>$balanceId, 'requested_by'=>$actor->id, 'desired_quantity'=>$projection['atp'],
                'balance_version'=>$balance->version, 'route_version'=>$route->version, 'platform_location_id'=>$route->platform_location_id,
                'status'=>'staged', 'request_snapshot'=>json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at'=>now(), 'updated_at'=>now(),
            ]);
            return $this->receipt(DB::table('marketplace_stock_sync_tasks')->find($id));
        }, 3);
    }

    /** Read-only execution gate. A dispatcher must call this immediately before any remote request. */
    public function review(int $taskId, array $allowedStatuses = ['staged']): array
    {
        $task=DB::table('marketplace_stock_sync_tasks')->find($taskId);
        if (!$task) $this->reject('Stock synchronization task not found.');
        $blockers=[];
        if (!in_array($task->status, $allowedStatuses, true)) $blockers[]='task_not_executable';
        $store=DB::table('marketplace_stores')->find($task->store_id);
        if (!$store || $store->status !== 'connected') $blockers[]='store_not_connected';
        $balance=DB::table('inventory_balances')->find($task->inventory_balance_id);
        if (!$balance || (int) $balance->version !== (int) $task->balance_version) {
            $blockers[]='balance_changed';
        } else {
            $projection=$this->projection($balance,(int)$task->store_id);
            if (!$projection['verified']) $blockers[]='balance_not_verified';
            elseif ((int) $projection['atp'] !== (int) $task->desired_quantity) $blockers[]='atp_changed';
        }
        $route=DB::table('inventory_fulfillment_routes')->where('channel_key','marketplace:'.$task->store_id)->first();
        if (!$route || !$route->is_enabled || (int) $route->version !== (int) $task->route_version
            || $route->platform_location_id !== $task->platform_location_id) $blockers[]='route_changed';
        $mapping=DB::table('marketplace_variant_mappings as m')->join('marketplace_listing_variants as l','l.id','=','m.listing_variant_id')
            ->where('m.store_id',$task->store_id)->where('m.listing_variant_id',$task->listing_variant_id)->where('l.store_id',$task->store_id)
            ->whereExists(function($query) use ($task) {$query->selectRaw('1')->from('inventory_balances as b')
                ->whereColumn('b.product_variant_id','m.product_variant_id')->where('b.id',$task->inventory_balance_id);})
            ->exists();
        if (!$mapping) $blockers[]='sku_mapping_changed';
        return $this->receipt($task)+['current'=>$blockers===[],'blockers'=>$blockers];
    }
    private function projection(object $balance,int $storeId): array
    {
        $managed=DB::table('inventory_order_activations')->where('channel_key','marketplace:'.$storeId)
            ->where('inventory_balance_id',$balance->id)->where('product_variant_id',$balance->product_variant_id)->exists();
        if (!$managed) return app(PhysicalBalanceReader::class)->project($balance);
        $reserved=(int)DB::table('stock_allocations')->where('inventory_balance_id',$balance->id)->where('status','reserved')->sum('quantity');
        $read=app(\App\Services\Inventory\BasicWmsBalanceReader::class)->project($balance,$reserved);
        return ['verified'=>$read['available']!==null,'atp'=>$read['available']];
    }

    private function receipt(object $row): array
    {
        return ['id'=>(int) $row->id, 'status'=>$row->status, 'store_id'=>(int) $row->store_id,
            'inventory_balance_id'=>(int) $row->inventory_balance_id, 'desired_quantity'=>(int) $row->desired_quantity,
            'balance_version'=>(int) $row->balance_version, 'route_version'=>(int) $row->route_version,
            'platform_location_id'=>$row->platform_location_id];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['stock_sync'=>$message]);
    }
}
