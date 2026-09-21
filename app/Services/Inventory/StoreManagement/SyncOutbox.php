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
        $sourceStage = $this->enqueue($operationId, $source, 'source', dispatch: $dispatch);

        return [
            $sourceStage,
            $this->enqueue(
                $operationId,
                $destination,
                'destination',
                dispatch: $dispatch,
                predecessorComplete: in_array($sourceStage['status'], ['confirmed', 'not_applicable'], true),
            ),
        ];
    }

    public function enqueueBalance(?string $operationId, object $balance, string $stage = 'account', ?string $eventKey = null): array
    {
        return $this->enqueue($operationId, $balance, $stage, $eventKey);
    }

    public function enqueueMutation(?string $operationId, object $balance, string $stage = 'account', ?string $eventKey = null): array
    {
        $pool = DB::table('inventory_pools')->find($balance->inventory_pool_id);
        if ($pool?->kind !== 'shared') {
            return [$this->enqueue($operationId, $balance, $stage, $eventKey)];
        }
        $storeIds = DB::table('inventory_store_accounts as a')->join('marketplace_variant_mappings as m', 'm.store_id', '=', 'a.marketplace_store_id')
            ->where('a.management_status', 'managed')->where('m.product_variant_id', $balance->product_variant_id)->distinct()->pluck('a.marketplace_store_id');
        if ($storeIds->isEmpty()) {
            return [$this->enqueue($operationId, $balance, $stage, $eventKey)];
        }

        return $storeIds->map(fn ($storeId) => $this->enqueue($operationId, $balance, $stage, $eventKey, (int) $storeId))->all();
    }

    public function enqueueStore(?string $operationId, object $balance, int $storeId, string $stage, string $eventKey): array
    {
        return $this->enqueue($operationId, $balance, $stage, $eventKey, $storeId);
    }

    private function enqueue(?string $operationId, object $balance, string $stage, ?string $eventKey = null,
        ?int $overrideStoreId = null, bool $dispatch = true, bool $predecessorComplete = false): array
    {
        $pool = DB::table('inventory_pools')->where('id', $balance->inventory_pool_id)->first();
        $storeId = $overrideStoreId ?? ($pool?->marketplace_store_id === null ? null : (int) $pool->marketplace_store_id);
        $managed = $storeId === null || DB::table('inventory_store_accounts')
            ->where('marketplace_store_id', $storeId)->value('management_status') === 'managed';
        $id = (string) Str::uuid();
        $status = $storeId === null ? 'confirmed' : ($managed
            ? ($stage === 'destination' && ! $predecessorComplete ? 'waiting_source' : 'queued')
            : 'not_applicable');
        $target = max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock);
        $listing = $storeId === null ? null : DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
            ->where('mapping.store_id', $storeId)->where('mapping.product_variant_id', $balance->product_variant_id)
            ->first(['listing.id', 'listing.external_product_id', 'listing.external_variant_id']);
        $providerLocationId = $storeId === null ? null : DB::table('inventory_fulfillment_routes')
            ->where('channel_key', 'marketplace:'.$storeId)->where('is_enabled', true)->value('platform_location_id');
        if ($storeId !== null) {
            $this->createBoundaryForReviewedMutation($operationId, $storeId, $balance);
            DB::table('inventory_store_sync_tasks')->where('marketplace_store_id', $storeId)
                ->where('product_variant_id', $balance->product_variant_id)->whereIn('status', ['queued', 'failed', 'waiting_source'])
                ->update(['status' => 'superseded', 'updated_at' => now()]);
        }
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => $id, 'operation_id' => $operationId, 'marketplace_store_id' => $storeId,
            'product_variant_id' => $balance->product_variant_id, 'listing_variant_id' => $listing?->id,
            'provider_external_product_id' => $listing?->external_product_id,
            'provider_external_variant_id' => $listing?->external_variant_id,
            'provider_location_id' => $providerLocationId, 'stage' => $stage,
            'status' => $status, 'target_quantity' => $target, 'balance_version' => $balance->version,
            'event_key' => ($eventKey ?? $operationId.':'.$stage).':'.($storeId ?? 'website'),
            'provider_receipt' => $storeId === null ? json_encode(['local' => true], JSON_THROW_ON_ERROR) : null,
            'available_at' => $status === 'queued' ? now() : null, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($status === 'queued' && $dispatch) {
            ProcessStoreInventorySync::dispatch($id)->afterCommit();
        }

        return ['id' => $id, 'stage' => $stage, 'store_id' => $storeId, 'status' => $status, 'target_quantity' => $target];
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
