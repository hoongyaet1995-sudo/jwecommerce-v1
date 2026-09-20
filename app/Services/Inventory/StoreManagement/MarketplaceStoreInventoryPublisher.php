<?php

namespace App\Services\Inventory\StoreManagement;

use App\Contracts\StoreInventoryPublisher;
use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Services\Marketplace\LazadaClient;
use App\Services\Marketplace\LazadaTokenManager;
use RuntimeException;

final class MarketplaceStoreInventoryPublisher implements StoreInventoryPublisher
{
    public function __construct(private LazadaTokenManager $tokens, private LazadaClient $lazada) {}

    public function publish(object $store, object $listing, int $quantity, string $eventKey): array
    {
        if (config('marketplace.stock_sync_operations_enabled') !== true) {
            throw new RuntimeException('Marketplace stock synchronization is disabled.');
        }
        if ($store->platform !== 'lazada') {
            throw new RuntimeException('Shopee managed stock publishing requires a separately verified provider adapter.');
        }
        try {
            $token = $this->tokens->accessToken((int) $store->id);
        } catch (RuntimeException $error) {
            if (str_contains(strtolower($error->getMessage()), 'expired') || str_contains(strtolower($error->getMessage()), 'authorize')) {
                throw new StoreInventoryAuthorizationExpired('Store authorization expired. Reconnect this store.');
            }
            throw $error;
        }
        try {
            $this->lazada->updateAbsoluteQuantity($token, (string) $listing->external_product_id,
                (string) $listing->external_variant_id, $quantity, null, (int) $store->id);
        } catch (RuntimeException $error) {
            if (str_contains(strtolower($error->getMessage()), 'unknown') || str_contains(strtolower($error->getMessage()), 'not confirmed')) {
                throw new StoreInventoryResultUncertain('Provider result is unknown. Reconcile before retrying.');
            }
            throw $error;
        }
        return ['provider' => 'lazada', 'confirmed_quantity' => $quantity,
            'event_fingerprint' => hash('sha256', $eventKey), 'confirmed_at' => now()->toIso8601String()];
    }
}
