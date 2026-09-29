<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Cache;

/** Pace item stock snapshots across workers for one Shopee store. */
final class ShopeeStockCallGate
{
    public function run(int $storeId, callable $request): mixed
    {
        $key = 'shopee:stock-read:'.$storeId;
        return Cache::lock($key.':lock', 180)->block(45, function () use ($key, $request) {
            $interval = max(0, (int) config('marketplace.shopee.stock_call_interval_ms', 1000));
            $wait = (float) Cache::get($key.':next', 0) - microtime(true);
            if ($wait > 0) usleep((int) ceil($wait * 1_000_000));
            try {
                return $request();
            } finally {
                Cache::put($key.':next', microtime(true) + $interval / 1000, 180);
            }
        });
    }
}
