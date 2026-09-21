<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StoreSyncAttemptLedger
{
    public function record(object $task, string $status, ?array $receipt = null, ?int $actorId = null, string $source = 'worker'): void
    {
        DB::table('inventory_store_sync_attempts')->insert([
            'id' => (string) Str::uuid(),
            'task_id' => $task->id,
            'attempt_number' => max(1, (int) $task->attempts),
            'status' => $status,
            'actor_id' => $actorId,
            'source' => $source,
            'target_quantity' => $task->target_quantity === null ? null : (int) $task->target_quantity,
            'provider_receipt' => $receipt === null ? null : json_encode($receipt, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
