<?php

namespace App\Jobs;

use App\Services\Shipping\EasyParcelReconciliationSweeper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SweepEasyParcelReconciliation implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('easyparcel');
    }

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 240;

    public function uniqueId(): string
    {
        return 'easyparcel-reconciliation-sweeper';
    }

    public function handle(EasyParcelReconciliationSweeper $sweeper): void
    {
        $sweeper->sweep();
    }
}
