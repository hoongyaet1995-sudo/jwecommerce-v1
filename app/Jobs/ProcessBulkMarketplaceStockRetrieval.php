<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Inventory\StoreManagement\MarketplaceBaselineFirstCount;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Processes one location per job, then automatically queues the next location. */
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

    public function handle(MarketplaceBaselineFirstCount $baseline): void
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
            return;
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
        } catch (Throwable $error) {
            if ($error instanceof ValidationException) {
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
                'status' => $errorCode ? 'failed' : 'succeeded',
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
            self::dispatch($this->runId)->afterCommit();
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
