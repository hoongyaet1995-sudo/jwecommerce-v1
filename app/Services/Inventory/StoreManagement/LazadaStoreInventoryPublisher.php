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
        $routeLocation = property_exists($listing, 'sync_provider_location_id') ? $listing->sync_provider_location_id
            : DB::table('inventory_fulfillment_routes')->where('channel_key', 'marketplace:'.$store->id)
                ->where('is_enabled', true)->value('platform_location_id');
        $warehouse = is_string($routeLocation) && $routeLocation !== '' ? $routeLocation : null;
        $strictFacility = ($listing->sync_strict_facility ?? false) === true;
        if ($strictFacility && $warehouse === null) {
            throw new RuntimeException('A verified provider facility is required for this stock task.');
        }
        $before = $strictFacility
            ? $this->client->exactStockQuantity($token, (string) $listing->external_product_id,
                (string) $listing->external_variant_id, $warehouse, (int) $store->id)
            : $this->stockQuantity($token, $listing, $warehouse, (int) $store->id);
        if ($strictFacility && $before['warehouse_code'] !== $warehouse) {
            throw new RuntimeException('Provider stock location changed before the write.');
        }
        if (property_exists($listing, 'sync_task_id') && ($listing->sync_provider_location_id ?? null) === null
            && $before['warehouse_code'] !== null) {
            DB::table('inventory_store_sync_tasks')->where('id', $listing->sync_task_id)
                ->whereNull('provider_location_id')->update(['provider_location_id' => $before['warehouse_code'], 'updated_at' => now()]);
        }
        $writeWarehouse = $before['warehouse_code'] === 'dropshipping' ? null : $before['warehouse_code'];
        $write = $this->client->updateAbsoluteQuantity($token, (string) $listing->external_product_id,
            (string) $listing->external_variant_id, $quantity, $writeWarehouse, (int) $store->id);
        try {
            $observed = $this->client->exactStockQuantity($token, (string) $listing->external_product_id,
                (string) $listing->external_variant_id, $writeWarehouse, (int) $store->id);
        } catch (StoreInventoryAuthorizationExpired) {
            throw new StoreInventoryResultUncertain('Lazada write was accepted but authorization expired before read-back. Reconcile before retrying.');
        } catch (\Throwable) {
            throw new StoreInventoryResultUncertain('Lazada write succeeded but read-back could not be confirmed. Reconcile before retrying.');
        }
        if ($observed['quantity'] !== $quantity) {
            throw new StoreInventoryResultUncertain('Lazada read-back differs from the requested quantity. Reconcile before retrying.');
        }

        return ['provider' => 'lazada', 'requested_quantity' => $quantity, 'observed_quantity' => $observed['quantity'],
            'provider_location_id' => $before['warehouse_code'],
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
        $routeLocation = property_exists($listing, 'sync_provider_location_id') ? $listing->sync_provider_location_id
            : DB::table('inventory_fulfillment_routes')->where('channel_key', 'marketplace:'.$store->id)
                ->where('is_enabled', true)->value('platform_location_id');
        $warehouse = is_string($routeLocation) && $routeLocation !== '' ? $routeLocation : null;
        if (($listing->sync_strict_facility ?? false) === true) {
            if ($warehouse === null) {
                throw new RuntimeException('A verified provider facility is required for this stock read.');
            }
            $observed = $this->client->exactStockQuantity($token, (string) $listing->external_product_id,
                (string) $listing->external_variant_id, $warehouse, (int) $store->id);
            if ($observed['warehouse_code'] !== $warehouse) {
                throw new RuntimeException('Provider stock read returned another facility.');
            }
        } else {
            $observed = $this->stockQuantity($token, $listing, $warehouse, (int) $store->id);
        }

        return ['provider' => 'lazada', 'observed_quantity' => $observed['quantity'],
            'provider_location_id' => $observed['warehouse_code'], 'observed_at' => now()->toIso8601String()];
    }

    private function stockQuantity(string $token, object $listing, ?string $warehouse, int $storeId): array
    {
        try {
            return $this->client->exactStockQuantity($token, (string) $listing->external_product_id,
                (string) $listing->external_variant_id, $warehouse, $storeId);
        } catch (RuntimeException $error) {
            if ($warehouse === null || $error->getMessage() !== 'Lazada warehouse stock identity is ambiguous or absent.') {
                throw $error;
            }
            // A store route can name a physical warehouse while this particular SKU
            // only participates in Lazada's single-warehouse dropshipping stock.
            $single = $this->client->exactStockQuantity($token, (string) $listing->external_product_id,
                (string) $listing->external_variant_id, null, $storeId);
            if ($single['warehouse_code'] !== 'dropshipping') {
                throw $error;
            }
            return $single;
        }
    }
}
