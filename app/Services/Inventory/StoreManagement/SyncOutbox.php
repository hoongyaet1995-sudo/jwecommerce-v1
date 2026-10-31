<?php

namespace App\Services\Inventory\StoreManagement;

use App\Jobs\ProcessStoreInventorySync;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SyncOutbox
{
    public function __construct(
        private readonly StoreSkuSyncReadiness $readiness,
        private readonly InventoryIngestionBoundary $boundary,
    ) {}

    public function enqueueTransfer(string $operationId, object $source, object $destination, bool $dispatch = true): array
    {
        return [
            $this->enqueue($operationId, $source, 'source', dispatch: $dispatch),
            $this->enqueue($operationId, $destination, 'destination', dispatch: $dispatch),
        ];
    }

    public function enqueueBalance(?string $operationId, object $balance, string $stage = 'account', ?string $eventKey = null): array
    {
        return $this->enqueue($operationId, $balance, $stage, $eventKey);
    }

    public function enqueueMutation(?string $operationId, object $balance, string $stage = 'account', ?string $eventKey = null): array
    {
        // Catalog opening stock may serve the website; provider sync still requires First Count.
        if (config('inventory.current_stock_only') && $balance->current_stock === null) return [];
        $pool = DB::table('inventory_pools')->find($balance->inventory_pool_id);
        if ($pool?->kind === 'facility') {
            $facilities = DB::table('marketplace_store_facilities as facility')
                ->join('marketplace_variant_mappings as mapping', 'mapping.store_id', '=', 'facility.marketplace_store_id')
                ->where('facility.inventory_warehouse_id', $pool->warehouse_id)
                ->where('facility.is_active', true)
                ->where('mapping.product_variant_id', $balance->product_variant_id)
                ->distinct()->get(['facility.*']);
            if ($facilities->isEmpty()) {
                throw new \RuntimeException('The physical facility has no mapped provider stock destination.');
            }

            return $facilities->map(fn (object $facility): array => $this->enqueueFacilityBalance(
                ($eventKey ?? $operationId.':'.$stage).':'.$facility->id, $balance, $facility
            ))->all();
        }
        if ($pool?->kind !== 'shared') {
            return [$this->enqueue($operationId, $balance, $stage, $eventKey)];
        }
        $storeIds = DB::table('inventory_store_accounts as a')->join('marketplace_variant_mappings as m', 'm.store_id', '=', 'a.marketplace_store_id')
            ->where('a.shared_pool_takeover_enabled', true)->where('m.product_variant_id', $balance->product_variant_id)->distinct()->pluck('a.marketplace_store_id');
        if ($storeIds->isEmpty()) {
            return [$this->enqueue($operationId, $balance, $stage, $eventKey)];
        }

        $stages = [];
        foreach ($storeIds as $storeId) {
            $storeId = (int) $storeId;
            $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->first();
            $warehouseMode = DB::table('marketplace_stores')->where('id', $storeId)->value('platform') === 'lazada'
                && $scan?->status === 'verified'
                && in_array($scan?->topology, ['single_warehouse', 'multi_warehouse'], true);
            if (! $warehouseMode) {
                $stages[] = $this->enqueue($operationId, $balance, $stage, $eventKey, $storeId);
                continue;
            }
            $facilities = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
                ->where('is_active', true)->where('shared_pool_takeover_enabled', true)->get();
            foreach ($facilities as $facility) {
                $stages[] = $this->enqueue($operationId, $balance, $stage, $eventKey, $storeId, true, $facility);
            }
        }

        return $stages;
    }

    public function enqueueStore(?string $operationId, object $balance, int $storeId, string $stage, string $eventKey): array
    {
        return $this->enqueue($operationId, $balance, $stage, $eventKey, $storeId);
    }

    /** Persist the exact facility destination. The facility worker gate controls later dispatch. */
    public function enqueueFacilityBalance(string $eventKey, object $balance, object $facility): array
    {
        app(RebaselineFreeze::class)->assertOrdinaryPushAllowed();
        $storeId = (int) $facility->marketplace_store_id;
        $listing = DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
            ->where('mapping.store_id', $storeId)->where('listing.store_id', $storeId)
            ->where('mapping.product_variant_id', $balance->product_variant_id)
            ->first(['listing.id', 'listing.external_product_id', 'listing.external_variant_id']);
        if (! $listing) {
            throw new \RuntimeException('Map the SKU before queuing facility stock sync.');
        }
        DB::table('inventory_store_sync_tasks')->where('marketplace_store_id', $storeId)
            ->where('product_variant_id', $balance->product_variant_id)
            ->where('marketplace_store_facility_id', $facility->id)
            ->whereIn('status', ['queued', 'failed', 'facility_blocked', 'waiting_source', 'facility_pending', 'uncertain'])
            ->update(['status' => 'superseded', 'updated_at' => now()]);
        $id = (string) Str::uuid();
        $target = config('inventory.current_stock_only')
            ? \App\Services\Inventory\CurrentStockTarget::current($balance)
            : max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock);
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => $id, 'operation_id' => null, 'marketplace_store_id' => $storeId,
            'product_variant_id' => $balance->product_variant_id,
            'inventory_balance_id' => $balance->id,
            'marketplace_store_facility_id' => $facility->id,
            'listing_variant_id' => $listing->id,
            'provider_external_product_id' => $listing->external_product_id,
            'provider_external_variant_id' => $listing->external_variant_id,
            'provider_location_id' => $facility->provider_facility_id,
            'stage' => 'facility', 'status' => 'facility_pending',
            'target_quantity' => $target, 'balance_version' => $balance->version,
            'event_key' => $eventKey, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (str_starts_with($eventKey, 'batch-allocation:') || LazadaTestFacilityAcceptance::stockSyncAllowed($storeId)) {
            \App\Jobs\ProcessFacilityInventorySync::dispatch($id)->afterCommit();
        }

        return ['id' => $id, 'status' => 'facility_pending', 'store_id' => $storeId,
            'facility_id' => (int) $facility->id, 'provider_location_id' => $facility->provider_facility_id,
            'target_quantity' => $target];
    }

    private function enqueue(?string $operationId, object $balance, string $stage, ?string $eventKey = null,
        ?int $overrideStoreId = null, bool $dispatch = true, ?object $facility = null): array
    {
        app(RebaselineFreeze::class)->assertOrdinaryPushAllowed();
        $pool = DB::table('inventory_pools')->where('id', $balance->inventory_pool_id)->first();
        $storeId = $overrideStoreId ?? ($pool?->marketplace_store_id === null ? null : (int) $pool->marketplace_store_id);
        $account = $storeId === null ? null : DB::table('inventory_store_accounts')
            ->where('marketplace_store_id', $storeId)->first();
        $managed = $storeId === null || ($pool?->kind === 'shared'
            ? (bool) ($account?->shared_pool_takeover_enabled ?? false)
            : $account?->management_status === 'managed');
        $id = (string) Str::uuid();
        $status = $storeId === null ? 'confirmed' : ($managed ? 'queued' : 'not_applicable');
        $target = config('inventory.current_stock_only')
            ? \App\Services\Inventory\CurrentStockTarget::current($balance)
            : max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock);
        $listing = $storeId === null ? null : DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
            ->where('mapping.store_id', $storeId)->where('mapping.product_variant_id', $balance->product_variant_id)
            ->first(['listing.id', 'listing.external_product_id', 'listing.external_variant_id']);
        $providerLocationId = $facility?->provider_facility_id ?? ($storeId === null ? null : DB::table('inventory_fulfillment_routes')
            ->where('channel_key', 'marketplace:'.$storeId)->where('is_enabled', true)->value('platform_location_id'));
        if ($storeId !== null) {
            $this->createBoundaryForReviewedMutation($operationId, $storeId, $balance);
            DB::table('inventory_store_sync_tasks')->where('marketplace_store_id', $storeId)
                ->where('product_variant_id', $balance->product_variant_id)
                ->where('marketplace_store_facility_id', $facility?->id)
                ->whereIn('status', ['queued', 'failed', 'waiting_source', 'uncertain'])
                ->update(['status' => 'superseded', 'updated_at' => now()]);
        }
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => $id, 'operation_id' => $operationId, 'marketplace_store_id' => $storeId,
            'product_variant_id' => $balance->product_variant_id, 'inventory_balance_id' => $balance->id,
            'listing_variant_id' => $listing?->id,
            'marketplace_store_facility_id' => $facility?->id,
            'provider_external_product_id' => $listing?->external_product_id,
            'provider_external_variant_id' => $listing?->external_variant_id,
            'provider_location_id' => $providerLocationId, 'stage' => $stage,
            'status' => $status, 'target_quantity' => $target, 'balance_version' => $balance->version,
            'event_key' => ($eventKey ?? $operationId.':'.$stage).':'.($storeId ?? 'website').($facility ? ':'.$facility->id : ''),
            'provider_receipt' => $storeId === null ? json_encode(['local' => true], JSON_THROW_ON_ERROR) : null,
            'available_at' => $status === 'queued' ? now() : null, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($status === 'queued' && $dispatch) {
            ProcessStoreInventorySync::dispatch($id)->afterCommit();
        }

        return ['id' => $id, 'stage' => $stage, 'store_id' => $storeId,
            'facility_id' => $facility?->id, 'status' => $status, 'target_quantity' => $target];
    }

    private function createBoundaryForReviewedMutation(?string $operationId, int $storeId, object $balance): void
    {
        if ($operationId === null || DB::table('inventory_order_activations')->where('channel_key', 'marketplace:'.$storeId)
            ->where('product_variant_id', $balance->product_variant_id)->exists()) {
            return;
        }
        $state = $this->readiness->read($storeId, (int) $balance->product_variant_id);
        if (! $state['ready']) {
            return;
        }
        $operation = DB::table('inventory_store_operations as operation')
            ->leftJoin('inventory_store_reviews as review', 'review.id', '=', 'operation.review_id')
            ->where('operation.id', $operationId)->first(['operation.actor_id', 'operation.kind', 'review.payload']);
        $actor = $operation ? User::query()->find($operation->actor_id) : null;
        if (! $actor) {
            return;
        }
        $payload = json_decode($operation->payload ?? '{}', true) ?: [];
        $reason = trim((string) ($payload['reason'] ?? $operation->kind ?? 'Reviewed inventory change'));
        $this->boundary->create($storeId, (int) $balance->product_variant_id, (int) $balance->id, $actor, $reason);
    }
}
