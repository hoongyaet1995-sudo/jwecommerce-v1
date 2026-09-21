<?php

namespace App\Services\Inventory\StoreManagement;

use App\Contracts\StoreInventoryPublisher;
use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Services\Marketplace\LazadaClient;
use App\Services\Marketplace\LazadaTokenManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LazadaStoreInventoryPublisher implements StoreInventoryPublisher
{
    public function __construct(
        private readonly LazadaTokenManager $tokens,
        private readonly LazadaClient $client,
        private readonly StockSyncPolicy $policy,
    ) {}

    public function publish(object $store, object $listing, int $quantity, string $eventKey): array
    {
        $review = $this->policy->review($store, $listing);
        if (! $review['allowed']) {
            throw new RuntimeException($review['code']);
        }
        if ($quantity < 0 || $store->platform !== 'lazada') {
            throw new RuntimeException('Invalid Lazada stock request.');
        }
        try {
            $token = $this->tokens->accessToken((int) $store->id);
        } catch (RuntimeException $error) {
            if (str_contains(strtolower($error->getMessage()), 'expired') || str_contains(strtolower($error->getMessage()), 'authorize')) {
                throw new StoreInventoryAuthorizationExpired('Lazada authorization expired. Reconnect this store.');
            }
            throw $error;
        }
        $routeLocation = DB::table('inventory_fulfillment_routes')->where('channel_key', 'marketplace:'.$store->id)
            ->where('is_enabled', true)->value('platform_location_id');
        $warehouse = is_string($routeLocation) && $routeLocation !== '' ? $routeLocation : null;
        $before = $this->client->exactStockQuantity($token, (string) $listing->external_product_id,
            (string) $listing->external_variant_id, $warehouse, (int) $store->id);
        $write = $this->client->updateAbsoluteQuantity($token, (string) $listing->external_product_id,
            (string) $listing->external_variant_id, $quantity, $before['warehouse_code'], (int) $store->id);
        try {
            $observed = $this->client->exactStockQuantity($token, (string) $listing->external_product_id,
                (string) $listing->external_variant_id, $before['warehouse_code'], (int) $store->id);
        } catch (StoreInventoryAuthorizationExpired $error) {
            throw $error;
        } catch (\Throwable) {
            throw new StoreInventoryResultUncertain('Lazada write succeeded but read-back could not be confirmed. Reconcile before retrying.');
        }
        if ($observed['quantity'] !== $quantity) {
            throw new StoreInventoryResultUncertain('Lazada read-back differs from the requested quantity. Reconcile before retrying.');
        }

        return ['provider' => 'lazada', 'requested_quantity' => $quantity, 'observed_quantity' => $observed['quantity'],
            'event_fingerprint' => hash('sha256', $eventKey), 'request_id' => $write['request_id'],
            'confirmed_at' => now()->toIso8601String()];
    }

    public function observe(object $store, object $listing): array
    {
        $review = $this->policy->review($store, $listing);
        if (! $review['allowed']) {
            throw new RuntimeException($review['code']);
        }
        $token = $this->tokens->accessToken((int) $store->id);
        $routeLocation = DB::table('inventory_fulfillment_routes')->where('channel_key', 'marketplace:'.$store->id)
            ->where('is_enabled', true)->value('platform_location_id');
        $observed = $this->client->exactStockQuantity($token, (string) $listing->external_product_id,
            (string) $listing->external_variant_id,
            is_string($routeLocation) && $routeLocation !== '' ? $routeLocation : null, (int) $store->id);

        return ['provider' => 'lazada', 'observed_quantity' => $observed['quantity'], 'observed_at' => now()->toIso8601String()];
    }
}
