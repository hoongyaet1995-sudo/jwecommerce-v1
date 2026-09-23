<?php

namespace App\Jobs;

use App\Services\Marketplace\LazadaOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessMarketplaceHubOrderEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $hubEventId)
    {
        $this->onConnection('marketplace')->onQueue('marketplace');
    }

    public function handle(LazadaOrderService $orders): void
    {
        $event = DB::table('marketplace_hub_event_inbox')->where('hub_event_id', $this->hubEventId)->first();
        if (!$event || $event->status === 'completed') return;
        if (config('marketplace.operations_enabled') !== true && config('marketplace.read_operations_enabled') !== true) {
            throw new \RuntimeException('Marketplace read operations are disabled.');
        }
        $orders->pullOrderByExternalId((int) $event->store_id, $event->external_order_id);
        DB::table('marketplace_hub_event_inbox')->where('id', $event->id)
            ->update(['status' => 'completed', 'processed_at' => now(), 'updated_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('marketplace_hub_event_inbox')->where('hub_event_id', $this->hubEventId)
            ->where('status', '!=', 'completed')->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
