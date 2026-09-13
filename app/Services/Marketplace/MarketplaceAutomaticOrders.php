<?php

namespace App\Services\Marketplace;

use App\Jobs\PullLazadaOrders;
use App\Jobs\PullShopeeOrders;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

/** Schedules bounded, read-only order capture for each eligible provider store. */
class MarketplaceAutomaticOrders
{
    public function __construct(private ShopeeClient $shopee, private LazadaClient $lazada) {}

    public function enqueueDue(): int
    {
        if (config('marketplace.automatic_orders_enabled') !== true
            || (config('marketplace.operations_enabled') !== true && config('marketplace.read_operations_enabled') !== true)) {
            return 0;
        }
        $queue = Queue::connection('marketplace');
        if (!$queue instanceof DatabaseQueue || $queue->getDatabase() !== DB::connection()) {
            throw new RuntimeException('Automatic orders require the Marketplace database queue on the application database connection.');
        }

        $count = 0;
        foreach (['shopee', 'lazada'] as $platform) {
            if (!$this->providerReady($platform)) continue;
            $stores = DB::table('marketplace_stores')->where('platform', $platform)->where('status', 'connected')
                ->when($platform === 'shopee', fn ($query) => $query->where('environment', config('marketplace.shopee.environment')),
                    fn ($query) => $query->where('environment', 'live')->where('region', 'MY'))
                ->orderBy('id');
            $stores->chunkById(100, function ($candidates) use (&$count, $platform) {
                foreach ($candidates as $candidate) {
                    $count += DB::transaction(function () use ($candidate, $platform) {
                        $store = DB::table('marketplace_stores')->where('id', $candidate->id)->lockForUpdate()->first();
                        if (!$store || !$this->storeEligible($store, $platform)) return 0;
                        $runs = DB::table('marketplace_order_runs')->where('store_id', $store->id);
                        if ((clone $runs)->whereIn('status', ['queued', 'running'])->exists()) return 0;
                        $last = (clone $runs)->where('origin', 'automatic')->orderByDesc('id')->first();
                        // Never advance past failed work. Explicit retry retains the exact range/cursor.
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
                        $platform === 'shopee' ? PullShopeeOrders::dispatch($id)->beforeCommit() : PullLazadaOrders::dispatch($id)->beforeCommit();
                        return 1;
                    }, 3);
                }
            });
        }
        return $count;
    }

    private function providerReady(string $platform): bool
    {
        return $platform === 'shopee' ? $this->shopee->isConfigured() : $this->lazada->isConfigured();
    }

    private function storeEligible(object $store, string $platform): bool
    {
        return $store->status === 'connected' && $store->platform === $platform
            && ($platform === 'shopee'
                ? $store->environment === config('marketplace.shopee.environment')
                : $store->environment === 'live' && $store->region === 'MY');
    }
}
