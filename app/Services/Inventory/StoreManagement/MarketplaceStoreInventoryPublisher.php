<?php

namespace App\Services\Inventory\StoreManagement;

use App\Contracts\StoreInventoryPublisher;
use RuntimeException;

class MarketplaceStoreInventoryPublisher implements StoreInventoryPublisher
{
    public function __construct(
        private readonly ShopeeStoreInventoryPublisher $shopee,
        private readonly LazadaStoreInventoryPublisher $lazada,
    ) {}

    public function publish(object $store, object $listing, int $quantity, string $eventKey): array
    {
        return $this->provider($store)->publish($store, $listing, $quantity, $eventKey);
    }

    public function observe(object $store, object $listing): array
    {
        $publisher = $this->provider($store);
        if (! method_exists($publisher, 'observe')) {
            throw new RuntimeException('Provider stock reconciliation is unavailable.');
        }

        return $publisher->observe($store, $listing);
    }

    private function provider(object $store): StoreInventoryPublisher
    {
        return match ($store->platform ?? null) {
            'shopee' => $this->shopee,
            'lazada' => $this->lazada,
            default => throw new RuntimeException('Marketplace stock provider is unsupported.'),
        };
    }
}
