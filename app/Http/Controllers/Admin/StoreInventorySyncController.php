<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStoreInventorySync;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\ImmediateStoreInventorySync;
use App\Services\Inventory\StoreManagement\OverviewReader;
use App\Services\Inventory\StoreManagement\StockSyncRecovery;
use App\Services\Inventory\StoreManagement\StoreSyncAttemptLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class StoreInventorySyncController extends Controller
{
    public function checkOperation(Request $request, string $operation, StockSyncRecovery $recovery, OverviewReader $reader): JsonResponse
    {
        $this->authorizeActor($request);
        $record = DB::table('inventory_store_operations')->where('id', $operation)->first();
        abort_unless($record && $record->kind === 'transfer', 404);

        $tasks = DB::table('inventory_store_sync_tasks')->where('operation_id', $operation)->get();
        foreach ($tasks->whereIn('status', ['uncertain']) as $task) {
            if ($task->marketplace_store_id !== null) {
                try {
                    $recovery->check((int) $task->marketplace_store_id, (int) $task->product_variant_id, (int) $request->user()->id, false);
                } catch (\Throwable) {
                    // The operation response below remains the truthful persisted fallback.
                }
            }
        }

        DB::transaction(function () use ($operation): void {
            $source = DB::table('inventory_store_sync_tasks')->where('operation_id', $operation)->where('stage', 'source')->lockForUpdate()->first();
            $destination = DB::table('inventory_store_sync_tasks')->where('operation_id', $operation)->where('stage', 'destination')->lockForUpdate()->first();
            if ($source?->status === 'superseded' && $destination?->status === 'waiting_source') {
                DB::table('inventory_store_sync_tasks')->where('id', $destination->id)->update([
                    'status' => 'failed',
                    'provider_receipt' => json_encode([
                        'code' => 'source_update_replaced',
                        'message' => 'The source update was replaced by newer stock. Send the latest destination stock when ready.',
                    ], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }
        }, 3);

        return response()->json($reader->operation($operation))->header('Cache-Control', 'no-store');
    }

    public function syncAll(Request $request): JsonResponse
    {
        $this->authorizeActor($request);
        $ids = DB::table('inventory_store_sync_tasks')->whereIn('status', ['queued', 'waiting_source', 'failed'])
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))->pluck('id');
        foreach ($ids as $id) {
            ProcessStoreInventorySync::dispatch((string) $id);
        }

        return response()->json(['queued' => $ids->count(), 'task_ids' => $ids]);
    }

    public function retry(Request $request, string $task, MarketplaceStoreInventoryPublisher $publisher, StoreSyncAttemptLedger $attempts, ImmediateStoreInventorySync $immediateSync): JsonResponse
    {
        $this->authorizeActor($request);
        $row = DB::table('inventory_store_sync_tasks')->where('id', $task)->first();
        abort_unless($row, 404);
        if ($row->status === 'uncertain') {
            $store = DB::table('marketplace_stores')->find($row->marketplace_store_id);
            $listing = DB::table('marketplace_listing_variants as listing')
                ->where('listing.store_id', $row->marketplace_store_id)
                ->where('listing.id', $row->listing_variant_id)->select('listing.*')->first();
            abort_unless($store && $listing, 409, 'The mapped provider identity changed. Create a fresh sync task.');
            $listing->external_product_id = $row->provider_external_product_id;
            $listing->external_variant_id = $row->provider_external_variant_id;
            $listing->sync_provider_location_id = $row->provider_location_id;
            try {
                $observation = $publisher->observe($store, $listing);
            } catch (\Throwable) {
                return response()->json(['error' => ['code' => 'reconciliation_required',
                    'message' => 'Marketplace stock could not be read back safely.',
                    'next_action' => 'Reconnect the store if needed, then run provider reconciliation again.']], 409);
            }
            if ((int) $observation['observed_quantity'] === (int) $row->target_quantity) {
                DB::table('inventory_store_sync_tasks')->where('id', $task)->update([
                    'status' => 'confirmed', 'provider_receipt' => json_encode($observation + ['reconciled' => true]), 'updated_at' => now(),
                ]);
                $attempts->record($row, 'reconciled_confirmed', $observation, (int) $request->user()->id, 'admin');
                $this->dispatchNext($row);

                return response()->json(['id' => $task, 'status' => 'confirmed', 'reconciled' => true]);
            }

            $attempts->record($row, 'reconciliation_mismatch', $observation, (int) $request->user()->id, 'admin');
            DB::table('inventory_store_sync_tasks')->where('id', $task)->update(['status' => 'superseded', 'updated_at' => now()]);
            $this->dispatchNext($row);

            return response()->json(['error' => [
                'code' => 'provider_quantity_differs',
                'message' => 'Marketplace stock differs from the unresolved target. No write was sent.',
                'next_action' => 'Review the observed marketplace quantity and create a new reviewed inventory action only after reconciliation.',
                'observed_quantity' => (int) $observation['observed_quantity'],
                'target_quantity' => (int) $row->target_quantity,
            ]], 409);
        }
        if ($row->status === 'processing') {
            $receipt = ['code' => 'worker_interrupted', 'message' => 'The prior provider result requires read-back.'];
            DB::table('inventory_store_sync_tasks')->where('id', $task)->update([
                'status' => 'uncertain',
                'provider_receipt' => json_encode($receipt),
                'updated_at' => now(),
            ]);
            $attempts->record($row, 'uncertain', $receipt, (int) $request->user()->id, 'admin');

            return response()->json(['error' => ['code' => 'reconciliation_required',
                'message' => 'The previous provider write may have completed. Read back marketplace stock before retrying.',
                'next_action' => 'Run provider reconciliation. Do not send another stock write until the result is known.']], 409);
        }
        abort_if($row->status === 'auth_expired', 409, 'Reconnect the store before retrying this stock update.');
        abort_unless($row->status === 'failed', 409, 'This stage is not retryable.');
        DB::table('inventory_store_sync_tasks')->where('id', $task)->update([
            'status' => 'queued', 'provider_receipt' => null, 'available_at' => now(), 'updated_at' => now(),
        ]);
        $attempts->record($row, 'retry_started', null, (int) $request->user()->id, 'admin');
        if ($row->operation_id) {
            $immediateSync->run((string) $row->operation_id);
        } else {
            ProcessStoreInventorySync::dispatch((string) $task);
        }

        $status = DB::table('inventory_store_sync_tasks')->where('id', $task)->value('status');

        return response()->json(['id' => $task, 'status' => $status]);
    }

    private function authorizeActor(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->is_admin && ($user->is_super_admin || $user->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }

    private function dispatchNext(object $task): void
    {
        if ($task->operation_id) {
            DB::table('inventory_store_sync_tasks')->where('operation_id', $task->operation_id)
                ->where('status', 'waiting_source')->pluck('id')
                ->each(fn ($id) => ProcessStoreInventorySync::dispatch((string) $id));
        }
        DB::table('inventory_store_sync_tasks')->where('marketplace_store_id', $task->marketplace_store_id)
            ->where('product_variant_id', $task->product_variant_id)->where('status', 'queued')
            ->orderByDesc('created_at')->limit(1)->pluck('id')
            ->each(fn ($id) => ProcessStoreInventorySync::dispatch((string) $id));
    }
}
