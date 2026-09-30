<?php

namespace App\Services\Inventory\StoreManagement;

use App\Jobs\ReconcileUncertainStoreStock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Read-only provider reconciliation for one exact direct-store sync task. */
final class UncertainStoreStockReconciler
{
    private const MAX_READBACKS = 4;

    public function __construct(
        private readonly MarketplaceStoreInventoryPublisher $publisher,
        private readonly StoreSyncAttemptLedger $attempts,
    ) {}

    public function sweep(int $limit = 20): int
    {
        $shared = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode') === 'shared_pool';
        $query = DB::table('inventory_store_sync_tasks')->where('status', 'uncertain');
        if (! $shared) {
            $query->whereNull('marketplace_store_facility_id');
        }
        $ids = $query
            ->where('readback_attempts', '<', self::MAX_READBACKS)
            ->whereNull('readback_exhausted_at')
            ->where(function ($query): void {
                $query->where('next_readback_at', '<=', now())
                    ->orWhere(function ($legacy): void {
                        $legacy->whereNull('next_readback_at')->where('updated_at', '<=', now()->subSeconds(15));
                    });
            })->orderBy('updated_at')->limit(max(1, min(100, $limit)))->pluck('id');
        foreach ($ids as $id) {
            ReconcileUncertainStoreStock::dispatch((string) $id);
        }

        return $ids->count();
    }

    public function run(string $taskId): void
    {
        $candidate = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->first();
        if (! $candidate || $candidate->status !== 'uncertain'
            || ($candidate->marketplace_store_facility_id !== null
                && DB::table('inventory_configuration')->where('id', 1)->value('operating_mode') !== 'shared_pool')) {
            return;
        }
        $lock = Cache::lock('stock-auto-readback:'.$candidate->marketplace_store_id, 90);
        if (! $lock->get()) {
            return;
        }
        try {
            $context = DB::transaction(function () use ($taskId): ?array {
                $task = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->lockForUpdate()->first();
                if (! $task || $task->status !== 'uncertain'
                    || (int) $task->readback_attempts >= self::MAX_READBACKS || $task->readback_exhausted_at !== null
                    || ($task->next_readback_at !== null && now()->lt($task->next_readback_at))
                    || ($task->next_readback_at === null && now()->lt(\Carbon\Carbon::parse($task->updated_at)->addSeconds(15)))) {
                    return null;
                }
                $store = DB::table('marketplace_stores')->find($task->marketplace_store_id);
                $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
                if ($task->marketplace_store_facility_id !== null && ($mode !== 'shared_pool'
                    || ! DB::table('marketplace_store_facilities')->where('id', $task->marketplace_store_facility_id)
                        ->where('marketplace_store_id', $task->marketplace_store_id)
                        ->where('provider_facility_id', $task->provider_location_id)
                        ->where('is_active', true)->where('shared_pool_takeover_enabled', true)->exists())) {
                    DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
                        'status' => 'superseded', 'provider_receipt' => json_encode(['code' => 'facility_takeover_changed']),
                        'next_readback_at' => null, 'updated_at' => now(),
                    ]);
                    return null;
                }
                $pool = $mode === 'shared_pool' ? DB::table('inventory_pools')->where('kind', 'shared')->first()
                    : DB::table('inventory_pools')->where('pool_key', 'store:marketplace:'.$task->marketplace_store_id)->first();
                $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                    ->where('product_variant_id', $task->product_variant_id)->first() : null;
                $listing = DB::table('marketplace_variant_mappings as mapping')
                    ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
                    ->where('mapping.store_id', $task->marketplace_store_id)
                    ->where('mapping.product_variant_id', $task->product_variant_id)
                    ->where('listing.store_id', $task->marketplace_store_id)
                    ->first(['listing.*']);
                if (! $store || ! $balance || (int) $balance->version !== (int) $task->balance_version
                    || (int) ($listing?->id ?? 0) !== (int) $task->listing_variant_id
                    || (string) $listing->external_product_id !== (string) $task->provider_external_product_id
                    || (string) $listing->external_variant_id !== (string) $task->provider_external_variant_id) {
                    DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
                        'status' => 'superseded', 'provider_receipt' => json_encode(['code' => 'readback_identity_changed']),
                        'next_readback_at' => null, 'updated_at' => now(),
                    ]);

                    return null;
                }
                $attempt = (int) $task->readback_attempts + 1;
                $delay = match ($attempt) {1 => 45, 2 => 120, 3 => 300, default => null};
                DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
                    'readback_attempts' => $attempt,
                    'next_readback_at' => $delay === null ? null : now()->addSeconds($delay),
                    'readback_exhausted_at' => $delay === null ? now() : null,
                    'updated_at' => now(),
                ]);
                $listing->sync_provider_location_id = $task->provider_location_id;
                $listing->sync_strict_facility = $task->marketplace_store_facility_id !== null;

                return compact('task', 'store', 'listing');
            }, 3);
            if (! $context) {
                return;
            }
            try {
                $observed = $this->publisher->observe($context['store'], $context['listing']);
            } catch (Throwable) {
                $this->saveAttemptResult($taskId, 'provider_readback_unavailable');

                return;
            }
            if (! isset($observed['observed_quantity']) || (int) $observed['observed_quantity'] !== (int) $context['task']->target_quantity
                || ($context['task']->provider_location_id !== null
                    && ($observed['provider_location_id'] ?? null) !== $context['task']->provider_location_id)) {
                $this->saveAttemptResult($taskId, 'provider_quantity_not_yet_confirmed');

                return;
            }
            DB::transaction(function () use ($taskId, $observed): void {
                $task = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->where('status', 'uncertain')
                    ->lockForUpdate()->first();
                if (! $task) {
                    return;
                }
                $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
                $pool = $mode === 'shared_pool' ? DB::table('inventory_pools')->where('kind', 'shared')->first()
                    : DB::table('inventory_pools')->where('pool_key', 'store:marketplace:'.$task->marketplace_store_id)->first();
                $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                    ->where('product_variant_id', $task->product_variant_id)->first() : null;
                $listing = DB::table('marketplace_variant_mappings as mapping')
                    ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
                    ->where('mapping.store_id', $task->marketplace_store_id)
                    ->where('mapping.product_variant_id', $task->product_variant_id)
                    ->where('listing.store_id', $task->marketplace_store_id)
                    ->first(['listing.*']);
                if (! $balance || (int) $balance->version !== (int) $task->balance_version
                    || (int) ($listing?->id ?? 0) !== (int) $task->listing_variant_id
                    || (string) ($listing?->external_product_id ?? '') !== (string) $task->provider_external_product_id
                    || (string) ($listing?->external_variant_id ?? '') !== (string) $task->provider_external_variant_id) {
                    DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
                        'status' => 'superseded', 'provider_receipt' => json_encode(['code' => 'balance_revision_changed']),
                        'next_readback_at' => null, 'updated_at' => now(),
                    ]);

                    return;
                }
                $receipt = $observed + ['confirmation_method' => 'automatic_provider_readback', 'reconciled' => true];
                DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
                    'status' => 'confirmed', 'provider_receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                    'next_readback_at' => null, 'readback_exhausted_at' => null, 'updated_at' => now(),
                ]);
                DB::table('inventory_sync_observations')->insert([
                    'store_id' => $task->marketplace_store_id, 'product_variant_id' => $task->product_variant_id,
                    'listing_variant_id' => $task->listing_variant_id,
                    'external_product_id' => $task->provider_external_product_id,
                    'external_variant_id' => $task->provider_external_variant_id,
                    'sync_task_id' => $task->id, 'quantity' => (int) $observed['observed_quantity'],
                    'actor_id' => null, 'observed_at' => now(),
                ]);
                $this->attempts->record($task, 'confirmed', $receipt);
                if ($task->operation_id) {
                    DB::table('inventory_store_sync_tasks')->where('operation_id', $task->operation_id)
                        ->where('status', 'waiting_source')->pluck('id')
                        ->each(fn ($id) => \App\Jobs\ProcessStoreInventorySync::dispatch((string) $id)->afterCommit());
                }
            }, 3);
        } finally {
            $lock->release();
        }
    }

    private function saveAttemptResult(string $taskId, string $code): void
    {
        DB::table('inventory_store_sync_tasks')->where('id', $taskId)->where('status', 'uncertain')
            ->update(['provider_receipt' => json_encode(['code' => $code]), 'updated_at' => now()]);
    }
}
