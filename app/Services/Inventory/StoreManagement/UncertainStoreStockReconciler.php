<?php

namespace App\Services\Inventory\StoreManagement;

use App\Jobs\ReconcileUncertainStoreStock;
use Illuminate\Support\Facades\DB;

/** Schedules bounded readback for exact store and warehouse tasks. */
final class UncertainStoreStockReconciler
{
    private const MAX_READBACKS = 4;

    public function __construct(
        private readonly TaskStockReadback $readback,
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
        $this->readback->check($taskId);
    }
}
