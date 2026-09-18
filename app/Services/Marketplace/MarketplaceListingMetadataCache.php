<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Review-only cache; publishing preflight always reads the marketplace directly. */
final class MarketplaceListingMetadataCache
{
    public function read(object $store, string $resource, array $parameters, callable $fetch, bool $refresh = false): array
    {
        if (! isset($store->platform) || ! is_string($store->platform) || $store->platform === '') {
            throw new RuntimeException('Marketplace metadata cache requires a platform identity.');
        }

        $application = match ($store->platform) {
            'shopee' => config('marketplace.shopee.partner_id'),
            'lazada' => config('marketplace.lazada.app_key'),
            default => null,
        };
        $key = 'marketplace-listing:'.hash('sha256', json_encode([
            $store->platform, $store->id, $store->external_shop_id, $store->environment, $store->region,
            $application, $store->updated_at, $resource, $parameters,
        ], JSON_THROW_ON_ERROR));

        if ($refresh) Cache::forget($key);

        return Cache::remember($key, now()->addMinutes(15), $fetch);
    }
}
