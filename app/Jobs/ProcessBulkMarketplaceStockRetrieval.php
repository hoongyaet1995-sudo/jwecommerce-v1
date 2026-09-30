<?php

namespace App\Jobs;

use App\Models\User;
use App\Exceptions\MarketplaceStockLocationNotApplicable;
use App\Services\Inventory\StoreManagement\MarketplaceBaselineFirstCount;
use App\Services\Inventory\StoreManagement\MarketplaceItemStockSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Processes item groups within one bounded worker run; resumes from persisted location progress. */
final class ProcessBulkMarketplaceStockRetrieval implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1200;
    public bool $failOnTimeout = true;

    private ?int $claimedItemId = null;

    public function __construct(public string $runId)
    {
        $this->onConnection('marketplace')->onQueue('marketplace');
    }

    public function handle(MarketplaceBaselineFirstCount $baseline, ?MarketplaceItemStockSnapshot $snapshots = null): void
    {
        $snapshots ??= app(MarketplaceItemStockSnapshot::class);
        $started = microtime(true);
        for ($processed = 0; $processed < 250; $processed++) {
            if (microtime(true) - $started > 900) {
                self::dispatch($this->runId);
                return;
            }
            if ($this->handleItemGroup($baseline, $snapshots)) continue;
            if (! $this->processSingleLocation($baseline)) return;
        }
        self::dispatch($this->runId);
    }

    private function processSingleLocation(MarketplaceBaselineFirstCount $baseline): bool
    {
        $claim = DB::transaction(function (): ?array {
            $run = DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->lockForUpdate()->first();
            if (! $run || ! in_array($run->status, ['queued', 'running'], true)) {
                return null;
            }
            if (DB::table('marketplace_stock_retrieval_items')->where('run_id', $this->runId)
                ->where('status', 'processing')->exists()) {
                return null;
            }
            $item = DB::table('marketplace_stock_retrieval_items')->where('run_id', $this->runId)
                ->where('status', 'pending')->orderBy('id')->first();
            if (! $item) {
                DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->update([
                    'status' => $run->failed_locations > 0 ? 'completed_with_errors' : 'succeeded',
                    'finished_at' => now(), 'heartbeat_at' => now(), 'updated_at' => now(),
                ]);
                return null;
            }
            DB::table('marketplace_stock_retrieval_items')->where('id', $item->id)->update([
                'status' => 'processing', 'attempts' => (int) $item->attempts + 1, 'updated_at' => now(),
            ]);
            DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->update([
                'status' => 'running', 'heartbeat_at' => now(), 'updated_at' => now(),
            ]);
            return [(int) $item->id, (int) $item->product_variant_id,
                $item->marketplace_store_id === null ? null : (int) $item->marketplace_store_id,
                $item->marketplace_store_facility_id === null ? null : (int) $item->marketplace_store_facility_id,
                (int) $run->actor_id];
        }, 3);
        if (! $claim) {
            return false;
        }
        [$itemId, $variantId, $storeId, $facilityId, $actorId] = $claim;
        $this->claimedItemId = $itemId;
        $errorCode = null;
        $result = null;
        try {
            if ($storeId === null || str_starts_with((string) DB::table('marketplace_stock_retrieval_items')
                ->where('id', $itemId)->value('location_key'), 'unverified:')) {
                throw ValidationException::withMessages(['location' => 'Map the SKU and verify its exact provider warehouse first.']);
            }
            $actor = User::findOrFail($actorId);
            $result = $baseline->reconcile($actor, $storeId, $facilityId, $variantId, $itemId);
            if ($facilityId !== null) {
                $this->clearFacilityScope((object) ['product_variant_id' => $variantId,
                    'marketplace_store_facility_id' => $facilityId]);
            }
        } catch (Throwable $error) {
            if ($error instanceof MarketplaceStockLocationNotApplicable && $facilityId !== null) {
                $facility = DB::table('marketplace_store_facilities')->where('id', $facilityId)->first();
                $hasLedger = DB::table('inventory_balances as balance')
                    ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                    ->where('balance.product_variant_id', $variantId)
                    ->where('pool.warehouse_id', $facility?->inventory_warehouse_id)
                    ->whereNotNull('balance.current_stock')->exists();
                if (! $hasLedger) {
                    $this->recordFacilityScope((object) ['product_variant_id' => $variantId,
                        'marketplace_store_id' => $storeId, 'marketplace_store_facility_id' => $facilityId],
                        'not_applicable', now()->toIso8601String());
                    $result = ['action' => 'not_applicable', 'observed_at' => now()->toIso8601String()];
                } else {
                    $errorCode = 'provider_omitted_counted_warehouse';
                }
            } elseif ($error instanceof ValidationException) {
                $field = array_key_first($error->errors());
                $errorCode = match ($field) {
                    'store' => 'listing_mapping_unavailable',
                    'facility', 'location' => 'warehouse_unverified',
                    'stock' => 'stock_changed_during_retrieval',
                    'inventory' => 'inventory_mode_changed',
                    default => 'location_conflict_or_unavailable',
                };
            } else {
                report($error);
                $errorCode = str_contains($error->getMessage(), 'store is not connected')
                    ? 'store_not_connected' : 'provider_or_ledger_error';
            }
        }

        DB::transaction(function () use ($itemId, $result, $errorCode): void {
            $run = DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->lockForUpdate()->first();
            $item = DB::table('marketplace_stock_retrieval_items')->where('id', $itemId)->lockForUpdate()->first();
            if (! $run || ! $item || $item->status !== 'processing') {
                return;
            }
            DB::table('marketplace_stock_retrieval_items')->where('id', $itemId)->update([
                'status' => $errorCode ? 'failed' : (($result['action'] ?? null) === 'not_applicable' ? 'skipped' : 'succeeded'),
                'action' => $result['action'] ?? null,
                'observed_quantity' => $result['quantity'] ?? null,
                'previous_quantity' => $result['previous_quantity'] ?? null,
                'observed_at' => isset($result['observed_at'])
                    ? CarbonImmutable::parse($result['observed_at'])->utc()->format('Y-m-d H:i:s') : null,
                'error_code' => $errorCode, 'updated_at' => now(),
            ]);
            DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->update([
                'completed_locations' => (int) $run->completed_locations + ($errorCode ? 0 : 1),
                'failed_locations' => (int) $run->failed_locations + ($errorCode ? 1 : 0),
                'last_error_code' => $errorCode ?: $run->last_error_code,
                'heartbeat_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
        return true;
    }

    /** Claim all pending locations of one store item and share two item-level reads. */
    private function handleItemGroup(MarketplaceBaselineFirstCount $baseline, MarketplaceItemStockSnapshot $snapshots): bool
    {
        $claim = DB::transaction(function (): ?array {
            $run = DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->lockForUpdate()->first();
            if (! $run || ! in_array($run->status, ['queued', 'running'], true)
                || DB::table('marketplace_stock_retrieval_items')->where('run_id', $this->runId)
                    ->where('status', 'processing')->exists()) return null;
            $first = DB::table('marketplace_stock_retrieval_items as item')
                ->join('marketplace_variant_mappings as mapping', function ($join): void {
                    $join->on('mapping.product_variant_id', '=', 'item.product_variant_id')
                        ->on('mapping.store_id', '=', 'item.marketplace_store_id');
                })->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
                ->where('item.run_id', $this->runId)->where('item.status', 'pending')
                ->whereNotNull('item.marketplace_store_id')->orderBy('item.id')
                ->first(['item.id', 'item.marketplace_store_id', 'listing.external_product_id']);
            if (! $first) return null;
            $items = DB::table('marketplace_stock_retrieval_items as item')
                ->join('marketplace_variant_mappings as mapping', function ($join): void {
                    $join->on('mapping.product_variant_id', '=', 'item.product_variant_id')
                        ->on('mapping.store_id', '=', 'item.marketplace_store_id');
                })->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
                ->where('item.run_id', $this->runId)->where('item.status', 'pending')
                ->where('item.marketplace_store_id', $first->marketplace_store_id)
                ->where('listing.external_product_id', $first->external_product_id)
                ->orderBy('item.id')->distinct()->get(['item.id', 'item.product_variant_id', 'item.marketplace_store_id',
                    'item.marketplace_store_facility_id', 'listing.id as listing_id',
                    'listing.external_variant_id', 'listing.external_product_id']);
            if ($items->count() < 2) return null;
            $ids = $items->pluck('id')->all();
            DB::table('marketplace_stock_retrieval_items')->whereIn('id', $ids)->update([
                'status' => 'processing', 'updated_at' => now(),
            ]);
            DB::table('marketplace_stock_retrieval_items')->whereIn('id', $ids)->increment('attempts');
            DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->update([
                'status' => 'running', 'heartbeat_at' => now(), 'updated_at' => now(),
            ]);
            return ['items' => $items, 'store_id' => (int) $first->marketplace_store_id,
                'item_id' => (string) $first->external_product_id, 'actor_id' => (int) $run->actor_id];
        }, 3);
        if (! $claim) return false;
        $this->claimedItemId = (int) $claim['items']->first()->id;
        $store = DB::table('marketplace_stores')->where('id', $claim['store_id'])->first();
        $actor = User::find($claim['actor_id']);
        $prepared = [];
        foreach ($claim['items'] as $item) {
            try {
                if (! $store || ! $actor) throw new \RuntimeException('Store or actor is unavailable.');
                $facility = $item->marketplace_store_facility_id === null ? null
                    : DB::table('marketplace_store_facilities')->where('id', $item->marketplace_store_facility_id)->first();
                $prepared[] = ['item' => $item, 'facility' => $facility,
                    'version' => $baseline->revision($actor, $claim['store_id'],
                        $item->marketplace_store_facility_id === null ? null : (int) $item->marketplace_store_facility_id,
                        (int) $item->product_variant_id)];
            } catch (Throwable $error) {
                $this->finishGroupedItem((int) $item->id, null, $error instanceof ValidationException
                    ? 'location_conflict_or_unavailable' : 'provider_or_ledger_error');
            }
        }
        if ($prepared !== []) {
            try {
                $first = $snapshots->read($store, $claim['item_id']);
                $second = $snapshots->read($store, $claim['item_id']);
                foreach ($prepared as $row) {
                    $item = $row['item'];
                    $listing = (object) ['external_variant_id' => $item->external_variant_id];
                    try {
                        $notApplicable = $snapshots->notApplicable($first, $listing, $row['facility'], $store->platform)
                            && $snapshots->notApplicable($second, $listing, $row['facility'], $store->platform);
                        if ($notApplicable) {
                            $hasLedger = DB::table('inventory_balances as balance')
                                ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                                ->where('balance.product_variant_id', $item->product_variant_id)
                                ->where('pool.warehouse_id', $row['facility']->inventory_warehouse_id)
                                ->whereNotNull('balance.current_stock')->exists();
                            if ($hasLedger) {
                                throw ValidationException::withMessages(['stock' => 'Marketplace omitted a warehouse with an existing JW stock ledger. Review it manually.']);
                            }
                            $this->recordFacilityScope($item, 'not_applicable', $second['observed_at']);
                            $this->finishGroupedItem((int) $item->id, ['action' => 'not_applicable',
                                'observed_at' => $second['observed_at']], null);
                            continue;
                        }
                        $one = $snapshots->quantity($first, $listing, $row['facility'], $store->platform);
                        $two = $snapshots->quantity($second, $listing, $row['facility'], $store->platform);
                        if ($one !== $two) {
                            throw ValidationException::withMessages(['stock' => 'Marketplace stock changed during retrieval.']);
                        }
                        $result = $baseline->reconcileObserved($actor, $claim['store_id'],
                            $item->marketplace_store_facility_id === null ? null : (int) $item->marketplace_store_facility_id,
                            (int) $item->product_variant_id, (int) $item->id, $two,
                            $second['observed_at'], $row['version']);
                        $this->clearFacilityScope($item);
                        $this->finishGroupedItem((int) $item->id, $result, null);
                    } catch (Throwable $error) {
                        if (! $error instanceof ValidationException) report($error);
                        $this->finishGroupedItem((int) $item->id, null, $error instanceof ValidationException
                            ? 'stock_changed_during_retrieval' : 'provider_or_ledger_error');
                    }
                }
            } catch (Throwable $error) {
                report($error);
                foreach ($prepared as $row) $this->finishGroupedItem((int) $row['item']->id, null, 'provider_or_ledger_error');
            }
        }
        return true;
    }

    private function recordFacilityScope(object $item, string $status, string $observedAt): void
    {
        if ($item->marketplace_store_facility_id === null) return;
        DB::table('marketplace_variant_facility_scope')->upsert([[
            'product_variant_id' => (int) $item->product_variant_id,
            'marketplace_store_id' => (int) $item->marketplace_store_id,
            'marketplace_store_facility_id' => (int) $item->marketplace_store_facility_id,
            'status' => $status,
            'observed_at' => CarbonImmutable::parse($observedAt)->utc()->format('Y-m-d H:i:s'),
            'created_at' => now(), 'updated_at' => now(),
        ]], ['product_variant_id', 'marketplace_store_facility_id'], ['status', 'observed_at', 'updated_at']);
    }

    private function clearFacilityScope(object $item): void
    {
        if ($item->marketplace_store_facility_id === null) return;
        DB::table('marketplace_variant_facility_scope')
            ->where('product_variant_id', $item->product_variant_id)
            ->where('marketplace_store_facility_id', $item->marketplace_store_facility_id)->delete();
    }

    private function finishGroupedItem(int $itemId, ?array $result, ?string $errorCode): void
    {
        DB::transaction(function () use ($itemId, $result, $errorCode): void {
            $run = DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->lockForUpdate()->first();
            $item = DB::table('marketplace_stock_retrieval_items')->where('id', $itemId)->lockForUpdate()->first();
            if (! $run || ! $item || $item->status !== 'processing') return;
            DB::table('marketplace_stock_retrieval_items')->where('id', $itemId)->update([
                'status' => $errorCode ? 'failed' : (($result['action'] ?? null) === 'not_applicable' ? 'skipped' : 'succeeded'),
                'action' => $result['action'] ?? null,
                'observed_quantity' => $result['quantity'] ?? null,
                'previous_quantity' => $result['previous_quantity'] ?? null,
                'observed_at' => isset($result['observed_at'])
                    ? CarbonImmutable::parse($result['observed_at'])->utc()->format('Y-m-d H:i:s') : null,
                'error_code' => $errorCode, 'updated_at' => now(),
            ]);
            DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->update([
                'completed_locations' => (int) $run->completed_locations + ($errorCode ? 0 : 1),
                'failed_locations' => (int) $run->failed_locations + ($errorCode ? 1 : 0),
                'last_error_code' => $errorCode ?: $run->last_error_code,
                'heartbeat_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function failed(?Throwable $error): void
    {
        DB::transaction(function (): void {
            $run = DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->lockForUpdate()->first();
            if (! $run || ! in_array($run->status, ['queued', 'running'], true)) {
                return;
            }
            if ($this->claimedItemId !== null) {
                DB::table('marketplace_stock_retrieval_items')->where('id', $this->claimedItemId)
                    ->where('status', 'processing')->update(['status' => 'failed',
                        'error_code' => 'worker_timeout', 'updated_at' => now()]);
            }
            DB::table('marketplace_stock_retrieval_runs')->where('id', $this->runId)->update([
                'status' => 'paused', 'last_error_code' => 'worker_timeout',
                'heartbeat_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }
}
