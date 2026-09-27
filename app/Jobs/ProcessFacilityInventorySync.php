<?php

namespace App\Jobs;

use App\Contracts\StoreInventoryPublisher;
use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Exceptions\StoreInventoryProviderRejected;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/** Writes one versioned balance to one verified provider location, then relies on exact provider readback. */
final class ProcessFacilityInventorySync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $taskId)
    {
        $this->onConnection('marketplace')->onQueue('marketplace-stock');
    }

    public function handle(StoreInventoryPublisher $publisher): void
    {
        $explicitPush = DB::table('inventory_store_sync_tasks')->where('id', $this->taskId)
            ->value('event_key');
        if (! str_starts_with((string) $explicitPush, 'facility-manual-push:')
            && app(\App\Services\Inventory\StoreManagement\RebaselineFreeze::class)->isPaused()) {
            return;
        }
        if (config('marketplace.multiwarehouse_stock_sync_enabled') !== true
            && config('marketplace.multiwarehouse_test_acceptance_enabled') !== true) {
            return;
        }
        $context = DB::transaction(function (): ?array {
            $task = DB::table('inventory_store_sync_tasks')->where('id', $this->taskId)->lockForUpdate()->first();
            $manualPush = $task && str_starts_with((string) $task->event_key, 'facility-manual-push:');
            if (! $task || $task->status !== 'facility_pending' || ! $task->marketplace_store_facility_id
                || (! $manualPush && ! $task->inventory_balance_id)) {
                return null;
            }
            if (! \App\Services\Inventory\StoreManagement\LazadaTestFacilityAcceptance::stockSyncAllowed((int) $task->marketplace_store_id)) {
                return null;
            }
            $inFlight = DB::table('inventory_store_sync_tasks')->where('marketplace_store_facility_id', $task->marketplace_store_facility_id)
                ->where('product_variant_id', $task->product_variant_id)
                ->whereIn('status', ['processing', 'uncertain'])->where('id', '!=', $task->id)->exists();
            if ($inFlight) {
                // Keep the latest absolute target queued until the active write finishes.
                self::dispatch($task->id)->delay(now()->addSeconds(5))->afterCommit();
                return null;
            }
            $scan = DB::table('marketplace_store_facility_scans')
                ->where('marketplace_store_id', $task->marketplace_store_id)->first();
            $facility = DB::table('marketplace_store_facilities')
                ->where('id', $task->marketplace_store_facility_id)->first();
            $balance = DB::table('inventory_balances')->where('id', $task->inventory_balance_id)->lockForUpdate()->first();
            $pool = $balance ? DB::table('inventory_pools')->where('id', $balance->inventory_pool_id)->first() : null;
            $store = DB::table('marketplace_stores')->where('id', $task->marketplace_store_id)->first();
            $listing = DB::table('marketplace_listing_variants')->where('id', $task->listing_variant_id)
                ->where('store_id', $task->marketplace_store_id)->first();
            $mapping = $listing && DB::table('marketplace_variant_mappings')
                ->where('store_id', $task->marketplace_store_id)
                ->where('listing_variant_id', $listing->id)
                ->where('product_variant_id', $task->product_variant_id)->exists();
            $account = DB::table('inventory_store_accounts')
                ->where('marketplace_store_id', $task->marketplace_store_id)->first();
            if (! $scan || $scan->status !== 'verified' || ! $facility || ! $facility->is_active
                || (int) $facility->marketplace_store_id !== (int) $task->marketplace_store_id
                || $facility->provider_facility_id !== $task->provider_location_id
                || (! $manualPush && (! $pool || $pool->kind !== 'facility'
                    || (int) $pool->warehouse_id !== (int) $facility->inventory_warehouse_id))
                || ! $store || $store->status !== 'connected' || ! $mapping
                || (! $manualPush && $account && $account->management_status === 'paused')
                || (! $manualPush && (! $balance || $balance->counted_at === null
                    || $balance->physical_stock === null || $balance->reserved_stock === null
                    || $balance->safety_stock === null))) {
                $this->mark($task->id, 'facility_blocked', 'facility_identity_or_readiness_changed');

                return null;
            }
            if (! $manualPush && (int) $balance->version !== (int) $task->balance_version) {
                $this->mark($task->id, 'superseded', 'balance_version_changed');

                return null;
            }
            $latestTask = DB::table('inventory_store_sync_tasks')
                ->where('marketplace_store_facility_id', $task->marketplace_store_facility_id)
                ->where('product_variant_id', $task->product_variant_id)
                ->where('inventory_balance_id', $task->inventory_balance_id)
                ->where('balance_version', $task->balance_version)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('created_at')
                ->orderByRaw("CASE WHEN status IN ('failed', 'facility_blocked') THEN 0 WHEN status IN ('queued', 'waiting_source', 'facility_pending', 'processing', 'uncertain') THEN 1 ELSE 2 END")
                ->orderByDesc('id')->first(['id']);
            if ($latestTask && $latestTask->id !== $task->id) {
                $this->mark($task->id, 'superseded', 'newer_sync_task_exists');

                return null;
            }
            if (! $manualPush && ! str_starts_with((string) $task->event_key, 'facility-resync:')
                && \App\Services\Inventory\StoreManagement\LazadaTestFacilityAcceptance::appliesToStore((int) $task->marketplace_store_id)) {
                try {
                    app(\App\Services\Inventory\StoreManagement\LazadaTestFacilityAcceptance::class)
                        ->assertEligible((int) $task->marketplace_store_id,
                            (int) $task->product_variant_id,
                            (int) $task->marketplace_store_facility_id, $task->id);
                } catch (\Illuminate\Validation\ValidationException) {
                    $this->mark($task->id, 'facility_blocked', 'test_acceptance_eligibility_changed');

                    return null;
                }
            }
            if (! DB::table('inventory_warehouses')->where('id', $facility->inventory_warehouse_id)
                ->where('is_active', true)->where('status', 'active')->exists()) {
                $this->mark($task->id, 'facility_blocked', 'physical_facility_inactive');

                return null;
            }
            $target = $manualPush ? (int) $task->target_quantity
                : max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock);
            if (! $manualPush && $target !== (int) $task->target_quantity) {
                $this->mark($task->id, 'superseded', 'atp_changed');

                return null;
            }
            DB::table('inventory_store_sync_tasks')->where('id', $task->id)->update([
                'status' => 'processing', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now(),
            ]);
            $listing->sync_provider_location_id = $facility->provider_facility_id;
            $listing->sync_strict_facility = true;
            $listing->sync_task_id = $task->id;

            return ['store' => $store, 'listing' => $listing, 'target' => $target, 'event_key' => $task->event_key];
        }, 3);
        if ($context === null) {
            return;
        }

        try {
            $receipt = $publisher->publish($context['store'], $context['listing'],
                $context['target'], $context['event_key']);
            if (($receipt['confirmation_method'] ?? null) !== 'provider_readback'
                || (int) ($receipt['observed_quantity'] ?? -1) !== $context['target']
                || ($receipt['provider_location_id'] ?? null) !== $context['listing']->sync_provider_location_id) {
                throw new StoreInventoryResultUncertain('Exact facility readback did not confirm the requested quantity.');
            }
        } catch (StoreInventoryAuthorizationExpired $error) {
            $this->finish('auth_expired', $error->getMessage());

            return;
        } catch (StoreInventoryResultUncertain $error) {
            $this->finish('uncertain', $error->getMessage());

            return;
        } catch (StoreInventoryProviderRejected $error) {
            $this->finish('failed', $error->getMessage(), [
                'provider_code' => $error->providerCode,
                'provider_message' => $error->providerMessage,
                'http_status' => $error->httpStatus,
            ]);

            return;
        } catch (\Throwable) {
            $this->finish('failed', 'Marketplace rejected the facility stock update.');

            return;
        }

        DB::transaction(function () use ($receipt): void {
            $task = DB::table('inventory_store_sync_tasks')->where('id', $this->taskId)
                ->where('status', 'processing')->lockForUpdate()->first();
            if (! $task) {
                return;
            }
            $balance = DB::table('inventory_balances')->where('id', $task->inventory_balance_id)->first();
            $status = str_starts_with((string) $task->event_key, 'facility-manual-push:')
                || ($balance && (int) $balance->version === (int) $task->balance_version)
                ? 'confirmed' : 'superseded';
            DB::table('inventory_store_sync_tasks')->where('id', $task->id)->update([
                'status' => $status, 'provider_receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    private function finish(string $status, string $message, array $diagnostics = []): void
    {
        DB::table('inventory_store_sync_tasks')->where('id', $this->taskId)->where('status', 'processing')->update([
            'status' => $status,
            'provider_receipt' => json_encode(['code' => $status, 'message' => $message] + $diagnostics, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    private function mark(string $taskId, string $status, string $code): void
    {
        DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
            'status' => $status, 'provider_receipt' => json_encode(['code' => $code], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
}
