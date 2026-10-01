<?php

namespace App\Services\Inventory\StoreManagement;

use App\Jobs\ProcessStoreInventorySync;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Observes an immutable stock task. Manual checks never resume outbound work. */
final class TaskStockReadback
{
    public function __construct(private readonly MarketplaceStoreInventoryPublisher $publisher,
        private readonly StoreSyncAttemptLedger $attempts) {}

    public function check(string $taskId, ?string $operationId = null, ?array $input = null, ?int $actorId = null): void
    {
        $manual = $input !== null;
        $candidate = DB::table('inventory_store_sync_tasks')->find($taskId);
        if (! $candidate) {
            abort_if($manual, 404);

            return;
        }
        $lock = Cache::lock('stock-auto-readback:'.$candidate->marketplace_store_id, 90);
        if (! $lock->get()) {
            abort_if($manual, 409, 'A confirmation check is already running.');

            return;
        }
        try {
            $context = DB::transaction(function () use ($taskId, $operationId, $input, $manual): ?array {
                $task = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->lockForUpdate()->first();
                if ($manual) {
                    abort_unless($task && $task->operation_id === $operationId
                        && DB::table('inventory_store_operations')->where('id', $operationId)->exists(), 404);
                    $receipt = json_decode($task->provider_receipt ?? '{}', true) ?: [];
                    foreach (['store_id' => (int) $task->marketplace_store_id, 'variant_id' => (int) $task->product_variant_id,
                        'balance_version' => (int) $task->balance_version,
                        'facility_id' => $task->marketplace_store_facility_id === null ? null : (int) $task->marketplace_store_facility_id,
                        'provider_location_id' => $task->provider_location_id,
                        'provider_shop_id' => $receipt['provider_shop_id'] ?? null] as $key => $expected) {
                        abort_unless(($input[$key] ?? null) === $expected, 409, 'The stock destination has changed. Refresh before checking.');
                    }
                }
                $identity = $task ? $this->identity($task) : null;
                if (! $identity) {
                    abort_if($manual, 409, 'The stock task is no longer current. Refresh before checking.');
                    if ($task && $task->status === 'uncertain') {
                        DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
                            'status' => 'superseded', 'next_readback_at' => null, 'updated_at' => now()]);
                    }

                    return null;
                }
                if ($manual) {
                    $seen = DB::table('inventory_store_sync_attempts')->where('task_id', $taskId)
                        ->where('source', 'manual_confirmation')->get(['provider_receipt'])
                        ->contains(fn ($row) => (json_decode($row->provider_receipt ?? '{}', true)['confirmation_check_key'] ?? null) === $input['operation_key']);
                    if ($seen || $task->status === 'confirmed') {
                        return null;
                    }
                    abort_unless(in_array($task->status, ['uncertain', 'failed'], true), 409, 'This task cannot be checked yet.');
                    $lastManual = DB::table('inventory_store_sync_attempts')->where('task_id', $taskId)
                        ->where('source', 'manual_confirmation')->orderByDesc('created_at')->value('created_at');
                    abort_if($lastManual && now()->lt(Carbon::parse($lastManual)->addSeconds(15)), 429, 'Please wait before checking again.');
                } elseif ($task->status !== 'uncertain' || (int) $task->readback_attempts >= 4 || $task->readback_exhausted_at !== null
                    || ($task->next_readback_at !== null && now()->lt($task->next_readback_at))
                    || ($task->next_readback_at === null && now()->lt(Carbon::parse($task->updated_at)->addSeconds(15)))) {
                    return null;
                }
                $count = (int) $task->readback_attempts + 1;
                $delay = match ($count) {
                    1 => 45, 2 => 120, 3 => 300, default => null
                };
                DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
                    'readback_attempts' => $count, 'next_readback_at' => $delay === null ? null : now()->addSeconds($delay),
                    'readback_exhausted_at' => $delay === null ? now() : null, 'updated_at' => now()]);
                $identity['listing']->sync_provider_location_id = $task->provider_location_id;
                $identity['listing']->sync_strict_facility = $task->marketplace_store_facility_id !== null;

                return [...$identity, 'task' => $task];
            }, 3);
            if (! $context) {
                return;
            }
            $observed = [];
            $code = null;
            try {
                $result = $this->publisher->observe($context['store'], $context['listing']);
                // Retain only inventory diagnostics, never tokens or raw provider payloads.
                $observed = array_intersect_key($result, array_flip(['provider', 'observed_quantity', 'provider_location_id', 'observed_at']));
                if (! is_int($observed['observed_quantity'] ?? null)
                    || $observed['observed_quantity'] !== (int) $context['task']->target_quantity
                    || ($observed['provider_location_id'] ?? null) !== $context['task']->provider_location_id) {
                    $code = 'provider_quantity_not_yet_confirmed';
                }
            } catch (Throwable) {
                $code = 'provider_readback_unavailable';
            }
            DB::transaction(function () use ($taskId, $observed, $code, $manual, $input, $actorId): void {
                $task = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->lockForUpdate()->first();
                if (! $task || ! in_array($task->status, ['uncertain', 'failed'], true)) {
                    return;
                }
                $current = $this->identity($task);
                $old = json_decode($task->provider_receipt ?? '{}', true) ?: [];
                $receipt = [...$old, ...$observed, 'readback_checked_at' => now()->toIso8601String(),
                    'confirmation_method' => $manual ? 'manual_provider_readback' : 'automatic_provider_readback'];
                if ($manual) {
                    $receipt['confirmation_check_key'] = $input['operation_key'];
                }
                if (! $current) {
                    $status = 'superseded';
                    $receipt['code'] = 'readback_identity_changed';
                    unset($receipt['message']);
                } elseif ($code !== null) {
                    $status = $task->status;
                    $receipt['code'] = $code;
                    unset($receipt['message']);
                    if ($code === 'provider_readback_unavailable') {
                        unset($receipt['observed_quantity'], $receipt['observed_at']);
                    }
                } else {
                    $status = 'confirmed';
                    unset($receipt['code'], $receipt['message']);
                    $receipt['reconciled'] = true;
                }
                DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update([
                    'status' => $status, 'provider_receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                    'next_readback_at' => $status === 'confirmed' || $status === 'superseded' ? null : $task->next_readback_at,
                    'readback_exhausted_at' => $status === 'confirmed' ? null : $task->readback_exhausted_at, 'updated_at' => now()]);
                $this->attempts->record($task, $status, $receipt, $actorId, $manual ? 'manual_confirmation' : 'automatic_confirmation');
                if ($status !== 'confirmed') {
                    return;
                }
                DB::table('inventory_sync_observations')->insert([
                    'store_id' => $task->marketplace_store_id, 'product_variant_id' => $task->product_variant_id,
                    'listing_variant_id' => $task->listing_variant_id, 'external_product_id' => $task->provider_external_product_id,
                    'external_variant_id' => $task->provider_external_variant_id, 'sync_task_id' => $task->id,
                    'quantity' => $observed['observed_quantity'], 'actor_id' => $actorId, 'observed_at' => now()]);
                if (! $manual && $task->operation_id) {
                    DB::table('inventory_store_sync_tasks')->where('operation_id', $task->operation_id)->where('status', 'waiting_source')
                        ->pluck('id')->each(fn ($id) => ProcessStoreInventorySync::dispatch((string) $id)->afterCommit());
                }
            }, 3);
        } finally {
            $lock->release();
        }
    }

    private function identity(object $task): ?array
    {
        $store = DB::table('marketplace_stores')->find($task->marketplace_store_id);
        $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
        $receipt = json_decode($task->provider_receipt ?? '{}', true) ?: [];
        if (! $store || (isset($receipt['provider_shop_id']) && (string) $receipt['provider_shop_id'] !== (string) $store->external_shop_id)) {
            return null;
        }
        if ($task->marketplace_store_facility_id !== null && ($mode !== 'shared_pool'
            || ! DB::table('marketplace_store_facilities')->where('id', $task->marketplace_store_facility_id)
                ->where('marketplace_store_id', $task->marketplace_store_id)->where('provider_facility_id', $task->provider_location_id)
                ->where('is_active', true)->where('shared_pool_takeover_enabled', true)->exists())) {
            return null;
        }
        $pool = $mode === 'shared_pool' ? DB::table('inventory_pools')->where('kind', 'shared')->first()
            : DB::table('inventory_pools')->where('pool_key', 'store:marketplace:'.$task->marketplace_store_id)->first();
        $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
            ->where('product_variant_id', $task->product_variant_id)->lockForUpdate()->first() : null;
        $listing = DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
            ->where('mapping.store_id', $task->marketplace_store_id)->where('mapping.product_variant_id', $task->product_variant_id)
            ->where('listing.store_id', $task->marketplace_store_id)->first(['listing.*']);
        $target = ! $balance ? null : (config('inventory.current_stock_only')
            ? ($balance->current_stock === null ? null : (int) $balance->current_stock)
            : ($balance->physical_stock === null || $balance->reserved_stock === null || $balance->safety_stock === null
                ? null : max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock)));
        if (! $balance || (int) $balance->id !== (int) $task->inventory_balance_id
            || (int) $balance->version !== (int) $task->balance_version || $target === null
            || $target !== (int) $task->target_quantity
            || (int) ($listing?->id ?? 0) !== (int) $task->listing_variant_id
            || (string) ($listing?->external_product_id ?? '') !== (string) $task->provider_external_product_id
            || (string) ($listing?->external_variant_id ?? '') !== (string) $task->provider_external_variant_id) {
            return null;
        }

        return compact('store', 'balance', 'listing');
    }
}
