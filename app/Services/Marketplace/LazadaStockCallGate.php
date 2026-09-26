<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Cache;

/** Coordinates stock writes and their exact readbacks across workers for one seller. */
final class LazadaStockCallGate
{
    public function run(?int $storeId, string $appKey, callable $request): mixed
    {
        $identity = $storeId === null ? 'app:'.$appKey : 'store:'.$storeId;
        $key = 'lazada:stock-call:'.hash('sha256', $identity);

        return Cache::lock($key.':lock', 180)->block(45, function () use ($key, $request) {
            $interval = max(0, (int) config('marketplace.lazada.stock_call_interval_ms', 1100));
            $wait = (float) Cache::get($key.':next', 0) - microtime(true);
            if ($wait > 0) {
                usleep((int) ceil($wait * 1_000_000));
            }

            try {
                return $request();
            } finally {
                Cache::put($key.':next', microtime(true) + $interval / 1000, 180);
            }
        });
    }
}
