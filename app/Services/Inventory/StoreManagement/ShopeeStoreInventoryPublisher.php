<?php

namespace App\Services\Inventory\StoreManagement;

use App\Contracts\StoreInventoryPublisher;
use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Services\Marketplace\ShopeeClient;
use App\Services\Marketplace\ShopeeApplicationResolver;
use App\Services\Marketplace\ShopeeTokenManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopeeStoreInventoryPublisher implements StoreInventoryPublisher
{
    public function __construct(
        private readonly ShopeeTokenManager $tokens,
        private readonly ShopeeClient $client,
        private readonly StockSyncPolicy $policy,
    ) {}

    public function publish(object $store, object $listing, int $quantity, string $eventKey): array
    {
        $review = $this->policy->review($store, $listing);
        if (! $review['allowed']) {
            throw new RuntimeException($review['code']);
        }
        if ($quantity < 0 || $store->platform !== 'shopee') {
            throw new RuntimeException('Invalid Shopee stock request.');
        }
        try {
            $token = $this->tokens->accessToken((int) $store->id);
        } catch (RuntimeException $error) {
            if (str_contains(strtolower($error->getMessage()), 'expired') || str_contains(strtolower($error->getMessage()), 'authorize')) {
                throw new StoreInventoryAuthorizationExpired('Shopee authorization expired. Reconnect this store.');
            }
            throw $error;
        }
        $client = app(ShopeeApplicationResolver::class)->clientForStore((int)$store->id, $this->client);

        $routeLocation = property_exists($listing, 'sync_provider_location_id') ? $listing->sync_provider_location_id
            : DB::table('inventory_fulfillment_routes')->where('channel_key', 'marketplace:'.$store->id)
                ->where('is_enabled', true)->value('platform_location_id');
        $before = $client->exactStockContext($token, (string) $store->external_shop_id,
            (string) $listing->external_product_id, (string) $listing->external_variant_id,
            is_string($routeLocation) && $routeLocation !== '' ? $routeLocation : null);
        if (property_exists($listing, 'sync_task_id') && ($listing->sync_provider_location_id ?? null) === null) {
            DB::table('inventory_store_sync_tasks')->where('id', $listing->sync_task_id)
                ->whereNull('provider_location_id')->update(['provider_location_id' => $before['location_id'], 'updated_at' => now()]);
        }
        $write = $client->updateAbsoluteStock($token, (string) $store->external_shop_id,
            (string) $listing->external_product_id, (string) $listing->external_variant_id,
            $before['location_id'], $quantity);
        try {
            $observed = $client->exactStockContext($token, (string) $store->external_shop_id,
                (string) $listing->external_product_id, (string) $listing->external_variant_id, $before['location_id']);
        } catch (StoreInventoryAuthorizationExpired) {
            throw new StoreInventoryResultUncertain('Shopee write was accepted but authorization expired before read-back. Reconcile before retrying.');
        } catch (\Throwable) {
            throw new StoreInventoryResultUncertain('Shopee write succeeded but read-back could not be confirmed. Reconcile before retrying.');
        }
        if ($observed['quantity'] !== $quantity) {
            throw new StoreInventoryResultUncertain('Shopee read-back differs from the requested quantity. Reconcile before retrying.');
        }

        return ['provider' => 'shopee', 'requested_quantity' => $quantity, 'observed_quantity' => $observed['quantity'],
            'provider_location_id' => $before['location_id'],
            'event_fingerprint' => hash('sha256', $eventKey), 'request_id' => $write['request_id'],
            'confirmation_method' => 'provider_readback',
            'confirmed_at' => now()->toIso8601String()];
    }

    public function observe(object $store, object $listing): array
    {
        $review = $this->policy->reviewObservation($store, $listing);
        if (! $review['allowed']) {
            throw new RuntimeException($review['code']);
        }
        $token = $this->tokens->accessToken((int) $store->id);
        $client = app(ShopeeApplicationResolver::class)->clientForStore((int)$store->id, $this->client);
        $routeLocation = property_exists($listing, 'sync_provider_location_id') ? $listing->sync_provider_location_id
            : DB::table('inventory_fulfillment_routes')->where('channel_key', 'marketplace:'.$store->id)
                ->where('is_enabled', true)->value('platform_location_id');
        $observed = $client->exactStockContext($token, (string) $store->external_shop_id,
            (string) $listing->external_product_id, (string) $listing->external_variant_id,
            is_string($routeLocation) && $routeLocation !== '' ? $routeLocation : null);

        return ['provider' => 'shopee', 'observed_quantity' => $observed['quantity'],
            'provider_location_id' => $observed['location_id'], 'observed_at' => now()->toIso8601String()];
    }
}
