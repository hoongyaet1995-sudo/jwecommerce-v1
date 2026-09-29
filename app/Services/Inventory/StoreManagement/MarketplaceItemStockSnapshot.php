<?php

namespace App\Services\Inventory\StoreManagement;

use App\Services\Marketplace\LazadaClient;
use App\Services\Marketplace\LazadaTokenManager;
use App\Services\Marketplace\ShopeeApplicationResolver;
use App\Services\Marketplace\ShopeeClient;
use App\Services\Marketplace\ShopeeTokenManager;
use App\Services\Marketplace\ShopeeStockCallGate;
use RuntimeException;

/** A read-only provider snapshot keyed by provider SKU/model and warehouse ID. */
class MarketplaceItemStockSnapshot
{
    public function __construct(
        private readonly LazadaTokenManager $lazadaTokens,
        private readonly LazadaClient $lazada,
        private readonly ShopeeTokenManager $shopeeTokens,
        private readonly ShopeeClient $shopee,
        private readonly ShopeeApplicationResolver $shopeeApps,
        private readonly ShopeeStockCallGate $shopeeGate,
    ) {}

    public function read(object $store, string $itemId): array
    {
        if ($itemId === '' || ! ctype_digit($itemId) || $store->status !== 'connected') {
            throw new RuntimeException('Provider item is not available for a stock snapshot.');
        }
        $stock = match ($store->platform) {
            'lazada' => $this->lazada->itemStockSnapshot(
                $this->lazadaTokens->accessToken((int) $store->id), $itemId, (int) $store->id),
            'shopee' => $this->shopeeGate->run((int) $store->id, fn () =>
                $this->shopeeApps->clientForStore((int) $store->id, $this->shopee)
                    ->itemStockSnapshot($this->shopeeTokens->accessToken((int) $store->id),
                        (string) $store->external_shop_id, $itemId)),
            default => throw new RuntimeException('Marketplace stock provider is unsupported.'),
        };
        return ['stock' => $stock, 'observed_at' => now()->toIso8601String()];
    }

    public function quantity(array $snapshot, object $listing, ?object $facility, string $platform): int
    {
        $sku = (string) $listing->external_variant_id;
        $location = $facility?->provider_facility_id;
        $key = $location === null || $location === '' ? 'store' : (string) $location;
        if ($key === 'store' && ! isset($snapshot['stock'][$sku][$key])
            && isset($snapshot['stock'][$sku]['dropshipping'])) $key = 'dropshipping';
        // Lazada may omit zero-stock warehouses. A verified facility can then
        // safely be treated as zero only when this SKU has multiwarehouse data.
        if (! isset($snapshot['stock'][$sku][$key]) && $facility && $platform === 'lazada'
            && isset($snapshot['stock'][$sku]) && ! isset($snapshot['stock'][$sku]['dropshipping'])) return 0;
        $value = $snapshot['stock'][$sku][$key] ?? null;
        if (! is_int($value) || $value < 0) throw new RuntimeException('Exact provider SKU/warehouse stock is unavailable.');
        return $value;
    }
}
