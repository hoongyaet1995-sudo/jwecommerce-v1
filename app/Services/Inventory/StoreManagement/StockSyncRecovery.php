<?php

namespace App\Services\Inventory\StoreManagement;

use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Direct, audited recovery. Readback never schedules or sends a stock write. */
final class StockSyncRecovery
{
    public function __construct(
        private MarketplaceStoreInventoryPublisher $publisher,
        private StoreSkuSyncReadiness $readiness,
        private StoreSyncAttemptLedger $attempts,
        private ImmediateStoreInventorySync $immediateSync,
    ) {}

    private function context(int $storeId, int $variantId): array
    {
        $store = DB::table('marketplace_stores')->find($storeId);
        $variant = DB::table('product_variants')->find($variantId);
        abort_unless($store && $variant, 404);
        $listings = DB::table('marketplace_variant_mappings as m')->join('marketplace_listing_variants as l', 'l.id', '=', 'm.listing_variant_id')
            ->where('m.store_id', $storeId)->where('l.store_id', $storeId)->where('m.product_variant_id', $variantId)->select('l.*')->get();
        $listing = $listings->count() === 1 ? $listings->first() : null;
        $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
        $pool = $mode === 'shared_pool' ? DB::table('inventory_pools')->where('kind', 'shared')->first() : DB::table('inventory_pools')->where('marketplace_store_id', $storeId)->first();
        $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)->where('product_variant_id', $variantId)->first() : null;
        $quantity = $balance && $balance->counted_at !== null && $balance->physical_stock !== null && $balance->reserved_stock !== null && $balance->safety_stock !== null ? max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock) : null;

        return compact('store', 'variant', 'listing', 'balance', 'quantity');
    }

    private function tasks(int $storeId, int $variantId)
    {
        return DB::table('inventory_store_sync_tasks')->where('marketplace_store_id', $storeId)->where('product_variant_id', $variantId);
    }

    public function details(int $storeId, int $variantId): array
    {
        $c = $this->context($storeId, $variantId);
        $task = $this->tasks($storeId, $variantId)->where('status', '!=', 'superseded')->orderByDesc('created_at')->orderByDesc('id')->first()
            ?? $this->tasks($storeId, $variantId)->orderByDesc('created_at')->orderByDesc('id')->first();
        $pending = $this->tasks($storeId, $variantId)->whereIn('status', ['processing', 'uncertain'])->orderByDesc('created_at')->first();
        $observation = $c['listing'] ? DB::table('inventory_sync_observations')->where('store_id', $storeId)->where('product_variant_id', $variantId)->where('listing_variant_id', $c['listing']->id)->where('external_product_id', $c['listing']->external_product_id)->where('external_variant_id', $c['listing']->external_variant_id)->orderByDesc('id')->first() : null;
        $transferPending = $this->tasks($storeId, $variantId)->whereIn('stage', ['source', 'destination'])->whereIn('status', ['queued', 'processing', 'uncertain', 'waiting_source', 'failed', 'auth_expired'])->exists();
        $readiness = $this->readiness->read($storeId, $variantId);
        $blocker = $readiness['blockers'][0]['code'] ?? null;
        $status = 'not_synced';
        $reason = null;
        if (! $c['listing']) {
            $status = 'sku_not_mapped';
            $reason = 'Link this JW SKU to exactly one listing in this store.';
        } elseif ($pending) {
            $status = 'awaiting_confirmation';
            $reason = 'A previous update is awaiting marketplace confirmation. Check status before sending again.';
        } elseif ($transferPending) {
            $status = 'sync_failed';
            $reason = 'Resolve the outstanding stock transfer before resynchronizing this store.';
        } elseif ($blocker) {
            $status = match ($blocker) {
                'authorization_expired','authorization_required','store_not_connected' => 'auth_expired',
                'listing_not_participating' => 'listing_not_eligible',
                default => 'sync_failed',
            };
            $reason = match ($blocker) {
                'authorization_expired','authorization_required','store_not_connected' => 'Reconnect this store before synchronizing stock.',
                'initial_count_required' => 'Complete the first stock count before synchronizing.',
                'sync_paused' => 'Inventory synchronization is paused for this store.',
                'historical_orders_unresolved' => 'Reconcile existing order reservations before synchronizing this SKU.',
                'listing_not_participating' => 'This listing is not currently eligible for stock updates.',
                default => 'Stock synchronization is currently unavailable for this store SKU.',
            };
        } elseif ($observation && (! $task || $observation->sync_task_id === $task->id) && $c['quantity'] !== null && (int) $observation->quantity === $c['quantity']) {
            $status = 'synced';
        } elseif ($task && $task->status === 'auth_expired') {
            $status = 'auth_expired';
            $reason = 'Reconnect this store before synchronizing stock.';
        } elseif ($task && $task->status === 'failed') {
            $status = 'sync_failed';
            $reason = 'The marketplace stock update could not be completed.';
        } elseif ($task && $task->status === 'superseded' && (json_decode($task->provider_receipt ?? '{}', true)['code'] ?? null) === 'balance_revision_changed') {
            $status = 'sync_replaced';
        }
        $confirmedAt = DB::table('inventory_store_sync_attempts as a')->join('inventory_store_sync_tasks as t', 't.id', '=', 'a.task_id')
            ->where('t.marketplace_store_id', $storeId)->where('t.product_variant_id', $variantId)->where('a.status', 'confirmed')
            ->max('a.created_at');
        $quantity = $observation ? (int) $observation->quantity : null;

        return ['sku' => $c['variant']->sku, 'storeId' => (string) $storeId, 'status' => $status, 'jwAvailableStock' => $c['quantity'],
            'marketplaceReportedStock' => $quantity, 'difference' => $quantity === null || $c['quantity'] === null ? null : $c['quantity'] - $quantity,
            'lastConfirmedSyncAt' => $confirmedAt, 'lastCheckedAt' => $observation?->observed_at,
            'requestSentAt' => $pending?->updated_at, 'requestedQuantity' => $pending?->target_quantity,
            'failureReason' => $reason, 'failureRecoveryType' => $status === 'auth_expired' ? 'reconnect' : ($status === 'sku_not_mapped' ? 'mapping' : ($pending ? 'check_status' : 'resync')),
            'canResync' => $readiness['ready'] && ! $pending && ! $transferPending, 'canCheck' => (bool) $c['listing'],
            'physicalStock' => $c['balance']?->physical_stock, 'reservedStock' => $c['balance']?->reserved_stock];
    }

    public function check(int $storeId, int $variantId, int $actorId, bool $continueTransfer = true): array
    {
        $operationId = null;
        $result = $this->locked($storeId, $variantId, function () use ($storeId, $variantId, $actorId, &$operationId) {
            $c = $this->context($storeId, $variantId);
            abort_unless($c['listing'], 409, 'Map this SKU before checking marketplace stock.');
            $pending = $this->tasks($storeId, $variantId)->whereIn('status', ['uncertain', 'failed'])->orderByDesc('created_at')->first();
            if ($pending) {
                abort_unless((int) $pending->listing_variant_id === (int) $c['listing']->id &&
                    (string) $pending->provider_external_product_id === (string) $c['listing']->external_product_id &&
                    (string) $pending->provider_external_variant_id === (string) $c['listing']->external_variant_id, 409, 'The mapping changed after a stock update. Reconcile the original listing before continuing.');
                $c['listing']->sync_provider_location_id = $pending->provider_location_id;
            }
            try {
                $observation = $this->publisher->observe($c['store'], $c['listing']);
            } catch (\Throwable) {
                abort(409, 'Marketplace stock could not be checked. Verify the store connection and try again.');
            }
            $this->saveObservation($c, $observation, $actorId);
            // An active sender owns its processing task. Only reconcile completed uncertain sends.
            foreach ($this->tasks($storeId, $variantId)->whereIn('status', ['uncertain', 'failed'])->get() as $task) {
                if ((int) $task->listing_variant_id === (int) $c['listing']->id && (int) $task->target_quantity === (int) $observation['observed_quantity']) {
                    $this->finish($task, 'confirmed', $observation + ['confirmation_method' => 'provider_readback', 'reconciled' => true], $actorId);
                    if ($task->stage === 'source' && $task->operation_id) {
                        $operationId = (string) $task->operation_id;
                    }
                }
            }

            return $this->details($storeId, $variantId);
        });
        if ($continueTransfer && $operationId !== null) {
            $this->immediateSync->run($operationId);
        }

        return $result;
    }

    public function resync(int $storeId, int $variantId, string $key, int $actorId): array
    {
        return $this->locked($storeId, $variantId, function () use ($storeId, $variantId, $key, $actorId) {
            $event = 'direct-resync:'.$actorId.':'.$key;
            $existing = DB::table('inventory_store_sync_tasks')->where('event_key', $event)->first();
            if ($existing) {
                abort_unless((int) $existing->marketplace_store_id === $storeId && (int) $existing->product_variant_id === $variantId, 409, 'This request belongs to another stock update.');

                return $this->details($storeId, $variantId);
            }
            $c = $this->context($storeId, $variantId);
            abort_unless($this->readiness->read($storeId, $variantId)['ready'], 409, 'Resolve this SKU’s synchronization requirements first.');
            $task = DB::transaction(function () use ($storeId, $variantId, $event, $c, $actorId) {
                DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
                $balance = DB::table('inventory_balances')->where('id', $c['balance']->id)->lockForUpdate()->first();
                abort_unless((int) $balance->version === (int) $c['balance']->version, 409, 'Stock changed. Review the latest quantity before synchronizing.');
                $fresh = $this->context($storeId, $variantId);
                abort_unless($fresh['balance']?->id === $c['balance']->id && $fresh['listing']?->id === $c['listing']->id &&
                    $fresh['listing']?->external_product_id === $c['listing']->external_product_id &&
                    $fresh['listing']?->external_variant_id === $c['listing']->external_variant_id, 409, 'The stock account or mapping changed. Refresh before synchronizing.');
                abort_if($this->tasks($storeId, $variantId)->whereIn('status', ['processing', 'uncertain', 'waiting_source'])->lockForUpdate()->exists(), 409, 'A previous stock update needs confirmation first.');
                abort_if($this->tasks($storeId, $variantId)->whereIn('stage', ['source', 'destination'])->whereIn('status', ['queued', 'failed', 'auth_expired'])->exists(), 409, 'Resolve the outstanding stock transfer before resynchronizing.');
                abort_unless($this->readiness->read($storeId, $variantId)['ready'], 409, 'Synchronization requirements changed. Refresh before continuing.');
                // Keep older receipts and attempts, but only the new request is current.
                $this->tasks($storeId, $variantId)->whereIn('status', ['queued', 'failed', 'auth_expired', 'confirmed', 'not_applicable'])->update(['status' => 'superseded', 'updated_at' => now()]);
                $id = (string) Str::uuid();
                $operationId = $this->auditOperation($c, $event, $actorId);
                DB::table('inventory_store_sync_tasks')->insert(['id' => $id, 'operation_id' => $operationId, 'marketplace_store_id' => $storeId,
                    'product_variant_id' => $variantId, 'listing_variant_id' => $c['listing']->id, 'provider_external_product_id' => $c['listing']->external_product_id,
                    'provider_external_variant_id' => $c['listing']->external_variant_id, 'provider_location_id' => null, 'stage' => 'resync', 'status' => 'processing',
                    'target_quantity' => $c['quantity'], 'balance_version' => $balance->version, 'event_key' => $event, 'attempts' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $task = $this->tasks($storeId, $variantId)->where('id', $id)->first();
                $this->attempts->record($task, 'processing', null, $actorId, 'admin');

                return $task;
            }, 3);
            $c['listing']->sync_task_id = $task->id;
            try {
                $receipt = $this->publisher->publish($c['store'], $c['listing'], $c['quantity'], $event);
                if (! isset($receipt['observed_quantity']) || (int) $receipt['observed_quantity'] !== $c['quantity']) {
                    throw new StoreInventoryResultUncertain('Readback required.');
                }
            } catch (StoreInventoryAuthorizationExpired) {
                $this->finish($task, 'auth_expired', ['code' => 'authorization_expired'], $actorId);

                return $this->details($storeId, $variantId);
            } catch (StoreInventoryResultUncertain) {
                $this->finish($task, 'uncertain', ['code' => 'provider_result_unknown'], $actorId);
                // Reads only: never resend an accepted or ambiguous write.
                for ($i = 0; $i < 2; $i++) {
                    usleep(max(0, (int) config('inventory.sync_readback_delay_ms', 1000)) * 1000);
                    $c['listing']->sync_provider_location_id = DB::table('inventory_store_sync_tasks')->where('id', $task->id)->value('provider_location_id');
                    try {
                        $o = $this->publisher->observe($c['store'], $c['listing']);
                        $this->saveObservation($c, $o, $actorId);
                    } catch (\Throwable) {
                        break;
                    }
                    if ((int) $o['observed_quantity'] === $c['quantity']) {
                        $this->finish($task, 'confirmed', $o + ['reconciled' => true, 'confirmation_method' => 'provider_readback'], $actorId);
                        break;
                    }
                }

                return $this->details($storeId, $variantId);
            } catch (\Throwable) {
                $this->finish($task, 'failed', ['code' => 'provider_rejected'], $actorId);

                return $this->details($storeId, $variantId);
            }

            // A persistence failure after a successful provider write must leave the
            // task in flight, never turn it into a retryable provider rejection.
            $this->saveObservation($c, $receipt, $actorId);
            $this->finish($task, 'confirmed', $receipt, $actorId);

            return $this->details($storeId, $variantId);
        });
    }

    private function saveObservation(array $c, array $receipt, int $actorId): void
    {
        DB::table('inventory_sync_observations')->insert(['store_id' => $c['store']->id, 'product_variant_id' => $c['variant']->id,
            'listing_variant_id' => $c['listing']->id, 'external_product_id' => $c['listing']->external_product_id, 'external_variant_id' => $c['listing']->external_variant_id,
            'sync_task_id' => $c['listing']->sync_task_id ?? $this->tasks((int) $c['store']->id, (int) $c['variant']->id)->orderByDesc('created_at')->orderByDesc('id')->value('id'),
            'quantity' => $receipt['observed_quantity'], 'actor_id' => $actorId, 'observed_at' => now()]);
    }

    private function auditOperation(array $context, string $event, int $actorId): string
    {
        $reviewId = (string) Str::uuid();
        $operationId = (string) Str::uuid();
        $snapshot = ['store_id' => $context['store']->id, 'variant_id' => $context['variant']->id,
            'sku' => $context['variant']->sku, 'quantity' => $context['quantity'], 'ledger_changed' => false];
        DB::table('inventory_store_reviews')->insert([
            'id' => $reviewId, 'kind' => 'platform_sync', 'actor_id' => $actorId,
            'configuration_version' => DB::table('inventory_configuration')->where('id', 1)->value('version') ?? 0,
            'balance_versions' => json_encode([$context['balance']->id => $context['balance']->version]),
            'payload' => json_encode($snapshot + ['reason' => 'Resync latest stock']),
            'before' => json_encode($snapshot), 'after' => json_encode($snapshot), 'blockers' => '[]',
            'status' => 'confirmed', 'expires_at' => now(), 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_store_operations')->insert([
            'id' => $operationId, 'review_id' => $reviewId, 'operation_key' => $event, 'kind' => 'platform_sync',
            'actor_id' => $actorId, 'ledger_status' => 'applied',
            'receipt' => json_encode($snapshot + ['balances' => [['sku' => $context['variant']->sku]]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $operationId;
    }

    private function finish(object $task, string $status, array $receipt, int $actorId): void
    {
        DB::transaction(function () use ($task, $status, $receipt, $actorId) {
            $persisted = DB::table('inventory_store_sync_tasks')->where('id', $task->id)->lockForUpdate()->first();
            $allowedStatuses = $status === 'confirmed' ? ['processing', 'uncertain', 'failed'] : ['processing', 'uncertain'];
            if (! $persisted || ! in_array($persisted->status, $allowedStatuses, true)) {
                return;
            }
            if ($status === 'confirmed') {
                $current = $this->context((int) $task->marketplace_store_id, (int) $task->product_variant_id);
                if (! $current['balance'] || (int) $current['balance']->version !== (int) $task->balance_version) {
                    $status = 'superseded';
                    $receipt['code'] = 'balance_revision_changed';
                }
            }
            DB::table('inventory_store_sync_tasks')->where('id', $task->id)->update(['status' => $status, 'provider_receipt' => json_encode($receipt), 'updated_at' => now()]);
            $this->attempts->record($task, $status, $receipt, $actorId, 'admin');
        }, 3);
    }

    private function locked(int $storeId, int $variantId, callable $callback): array
    {
        $lock = Cache::lock('stock-recovery:'.$storeId.':'.$variantId, 600);
        abort_unless($lock->get(), 409, 'This stock update is already being checked or synchronized.');
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
