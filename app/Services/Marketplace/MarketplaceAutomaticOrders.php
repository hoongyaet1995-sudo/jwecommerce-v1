<?php

namespace App\Services\Marketplace;

use App\Jobs\PullShopeeOrders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\DatabaseQueue;
use RuntimeException;

class MarketplaceAutomaticOrders
{
    public function __construct(private ShopeeClient $shopee) {}

    public function enqueueDue(): int
    {
        if (config('marketplace.automatic_orders_enabled') !== true
            || (config('marketplace.operations_enabled') !== true && config('marketplace.read_operations_enabled') !== true)
            || !$this->shopee->isConfigured()) return 0;
        $queue = Queue::connection('marketplace');
        if (!$queue instanceof DatabaseQueue || $queue->getDatabase() !== DB::connection()) {
            throw new RuntimeException('Automatic orders require the Marketplace database queue on the application database connection.');
        }
        $count = 0;
        DB::table('marketplace_stores')->where('platform', 'shopee')->where('status', 'connected')
            ->where('environment', config('marketplace.shopee.environment'))->orderBy('id')->chunkById(100, function ($stores) use (&$count) {
                foreach ($stores as $candidate) {
                    $count += DB::transaction(function () use ($candidate) {
                        $store = DB::table('marketplace_stores')->where('id', $candidate->id)->lockForUpdate()->first();
                        if (!$store || $store->status !== 'connected' || $store->platform !== 'shopee'
                            || $store->environment !== config('marketplace.shopee.environment')) return 0;
                        $runs = DB::table('marketplace_order_runs')->where('store_id', $store->id);
                        if ((clone $runs)->whereIn('status', ['queued', 'running'])->exists()) return 0;
                        $last = (clone $runs)->where('origin', 'automatic')->orderByDesc('id')->first();
                        // Never advance past failed work. Explicit retry retains the same range/cursor.
                        if ($last && ($last->status !== 'succeeded' || !$last->requested_time_to)) return 0;
                        $now = now()->timestamp;
                        if ($last && $now - (int) $last->requested_time_to < 300) return 0;
                        $from = $last ? max(0, (int) $last->requested_time_to - 300) : max(0, $now - 30 * 86400);
                        $to = min($now, $from + 30 * 86400);
                        $id = DB::table('marketplace_order_runs')->insertGetId([
                            'store_id'=>$store->id, 'origin'=>'automatic', 'status'=>'queued',
                            'requested_time_from'=>$from, 'requested_time_to'=>$to,
                            'time_from'=>$from, 'time_to'=>min($to, $from + 14 * 86400),
                            'created_at'=>now(), 'updated_at'=>now(),
                        ]);
                        // The database job and run commit together; workers cannot see either before commit.
                        PullShopeeOrders::dispatch($id)->beforeCommit();
                        return 1;
                    }, 3);
                }
            });
        return $count;
    }
}
