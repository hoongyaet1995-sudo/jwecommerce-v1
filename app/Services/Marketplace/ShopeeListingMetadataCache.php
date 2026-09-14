<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Cache;

/** Review-only cache. Publishing preflight continues to read Shopee directly. */
final class ShopeeListingMetadataCache
{
    public function read(object $store, string $resource, array $parameters, callable $fetch, bool $refresh = false): array
    {
        $key = 'shopee-listing:'.hash('sha256', json_encode([
            $store->id, $store->external_shop_id, $store->environment, $store->region,
            config('marketplace.shopee.partner_id'), $store->updated_at, $resource, $parameters,
        ], JSON_THROW_ON_ERROR));
        if ($refresh) Cache::forget($key);
        return Cache::remember($key, now()->addMinutes(15), $fetch);
    }
}
