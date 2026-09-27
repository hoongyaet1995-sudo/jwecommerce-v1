<?php

namespace App\Jobs;

use App\Services\Marketplace\LazadaOrderService;
use App\Services\Marketplace\ShopeeOrderService;
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
        $this->onConnection('marketplace')->onQueue('marketplace-hub-orders');
    }

    public function handle(LazadaOrderService $lazadaOrders, ShopeeOrderService $shopeeOrders): void
    {
        $event = DB::table('marketplace_hub_event_inbox')->where('hub_event_id', $this->hubEventId)->first();
        if (!$event || $event->status === 'completed') return;
        if (config('marketplace.operations_enabled') !== true && config('marketplace.read_operations_enabled') !== true) {
            throw new \RuntimeException('Marketplace read operations are disabled.');
        }
        $store = DB::table('marketplace_stores')->find($event->store_id);
        if (! $store || $store->status !== 'connected') throw new \RuntimeException('Connected marketplace store required.');
        match ($store->platform) {
            'lazada' => $lazadaOrders->pullOrderByExternalId((int) $event->store_id, $event->external_order_id),
            'shopee' => $shopeeOrders->pullOrderByExternalId((int) $event->store_id, $event->external_order_id),
            default => throw new \RuntimeException('Unsupported marketplace hub event platform.'),
        };
        DB::table('marketplace_hub_event_inbox')->where('id', $event->id)
            ->update(['status' => 'completed', 'processed_at' => now(), 'updated_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('marketplace_hub_event_inbox')->where('hub_event_id', $this->hubEventId)
            ->where('status', '!=', 'completed')->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
