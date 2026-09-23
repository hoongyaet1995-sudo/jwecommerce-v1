<?php
namespace App\Jobs;

use App\Services\Marketplace\{ShopeeClient,ShopeeTokenManager,MarketplaceListingMetadataCache,ShopeeApplicationResolver};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{DB,Cache};

class WarmShopeeListingMetadata implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 120;
    public function __construct(public int $storeId)
    {
        $this->onConnection('marketplace')->onQueue('marketplace');
    }
    public function backoff(): array { return [30,120]; }
    public function handle(ShopeeClient $client, ShopeeTokenManager $tokens, MarketplaceListingMetadataCache $cache,
        ShopeeApplicationResolver $applications): void
    {
        $store=DB::table('marketplace_stores')->find($this->storeId);
        if (!$store || $store->status!=='connected' || $store->platform!=='shopee') return;
        $token=$tokens->accessToken($this->storeId);
        $client=$applications->clientForStore($this->storeId,$client);
        $key='shopee-listing-warm:'.$this->storeId;
        Cache::put($key,['status'=>'running'],now()->addDay());
        try {
            $cache->read($store,'categories',[],fn()=>$client->categoryTree($token,$store->external_shop_id),true);
            $cache->read($store,'logistics',[],fn()=>$client->logisticsChannels($token,$store->external_shop_id),true);
            Cache::put($key,['status'=>'ready','checked_at'=>now()->toIso8601String()],now()->addDay());
        } catch (\Throwable $e) {
            Cache::put($key,['status'=>'retrying'],now()->addDay());
            throw $e;
        }
    }
    public function failed(?\Throwable $exception): void
    {
        Cache::put('shopee-listing-warm:'.$this->storeId,['status'=>'failed'],now()->addDay());
    }
}
