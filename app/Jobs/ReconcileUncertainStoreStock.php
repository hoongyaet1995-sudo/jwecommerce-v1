<?php

namespace App\Jobs;

use App\Services\Inventory\StoreManagement\UncertainStoreStockReconciler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ReconcileUncertainStoreStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $taskId)
    {
        $this->onConnection('marketplace')->onQueue('marketplace-stock');
    }

    public function handle(UncertainStoreStockReconciler $reconciler): void
    {
        $reconciler->run($this->taskId);
    }
}
