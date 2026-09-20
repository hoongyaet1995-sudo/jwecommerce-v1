<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStoreInventorySync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class StoreInventorySyncController extends Controller
{
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

    public function retry(Request $request, string $task): JsonResponse
    {
        $this->authorizeActor($request);
        $data = $request->validate(['reconcile_outcome' => ['nullable', 'in:not_applied']]);
        $row = DB::table('inventory_store_sync_tasks')->where('id', $task)->first();
        abort_unless($row, 404);
        if ($row->status === 'uncertain' && ($data['reconcile_outcome'] ?? null) !== 'not_applied') {
            return response()->json(['error' => ['code' => 'reconciliation_required',
                'message' => 'Check the marketplace quantity first. Retry only after confirming the prior request was not applied.',
                'next_action' => 'Reconcile the provider stock, then record not_applied to retry this stage.']], 409);
        }
        if ($row->status === 'processing') {
            DB::table('inventory_store_sync_tasks')->where('id', $task)->update([
                'status' => 'uncertain',
                'provider_receipt' => json_encode(['code' => 'worker_interrupted', 'message' => 'The prior provider result requires read-back.']),
                'updated_at' => now(),
            ]);

            return response()->json(['error' => ['code' => 'reconciliation_required',
                'message' => 'The previous provider write may have completed. Read back marketplace stock before retrying.',
                'next_action' => 'Reconcile the provider stock, then record not_applied only if it was not applied.']], 409);
        }
        abort_unless(in_array($row->status, ['failed', 'auth_expired', 'uncertain'], true), 409, 'This stage is not retryable.');
        DB::table('inventory_store_sync_tasks')->where('id', $task)->update([
            'status' => 'queued', 'provider_receipt' => null, 'available_at' => now(), 'updated_at' => now(),
        ]);
        ProcessStoreInventorySync::dispatch((string) $task);

        return response()->json(['id' => $task, 'status' => 'queued']);
    }

    private function authorizeActor(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->is_admin && ($user->is_super_admin || $user->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }
}
