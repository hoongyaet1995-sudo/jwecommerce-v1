<?php

namespace App\Services\Inventory\StoreManagement;

use App\Contracts\StoreInventoryPublisher;
use App\Jobs\ProcessStoreInventorySync;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImmediateStoreInventorySync
{
    public function __construct(private readonly StoreInventoryPublisher $publisher) {}

    public function run(string $operationId): void
    {
        $this->runStatuses($operationId, ['queued', 'failed', 'waiting_source']);
    }

    public function continuePending(string $operationId): void
    {
        $this->runStatuses($operationId, ['queued', 'waiting_source']);
    }

    /** @param array<int,string> $statuses */
    private function runStatuses(string $operationId, array $statuses): void
    {
        $taskIds = DB::table('inventory_store_sync_tasks')
            ->where('operation_id', $operationId)
            ->orderByRaw("case when stage = 'source' then 0 when stage = 'destination' then 1 else 2 end")
            ->orderBy('created_at')
            ->pluck('id');

        foreach ($taskIds as $taskId) {
            $task = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->first();
            if (! $task || ! in_array($task->status, $statuses, true)) {
                continue;
            }

            try {
                (new ProcessStoreInventorySync((string) $taskId, false))->handle($this->publisher);
            } catch (Throwable) {
                ProcessStoreInventorySync::dispatch((string) $taskId)->afterCommit();
            }
        }
    }
}
