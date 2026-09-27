<?php

namespace App\Services\Inventory\StoreManagement;

use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Services\Marketplace\LazadaClient;
use App\Services\Marketplace\LazadaTokenManager;
use App\Services\Marketplace\ShopeeApplicationResolver;
use App\Services\Marketplace\ShopeeClient;
use App\Services\Marketplace\ShopeeTokenManager;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class FacilityDiscovery
{
    public function __construct(
        private readonly FacilityRegistry $registry,
        private readonly LazadaTokenManager $lazadaTokens,
        private readonly LazadaClient $lazada,
        private readonly ShopeeTokenManager $shopeeTokens,
        private readonly ShopeeClient $shopee,
    ) {}

    public function inventoryLocations(int $storeId): array
    {
        $result = $this->registry->read($storeId);
        if ($result['status'] !== 'verified'
            && DB::table('marketplace_stores')->where('id', $storeId)->value('platform') === 'shopee') {
            return $this->refresh($storeId);
        }
        return $result;
    }

    public function refresh(int $storeId): array
    {
        $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
        if (! $store || ! in_array($store->platform, ['lazada', 'shopee'], true)) {
            throw new InvalidArgumentException('Marketplace store not found.');
        }
        if ($store->status !== 'connected') {
            $this->registry->fail($storeId, 'store_disconnected');

            return $this->registry->read($storeId);
        }
        if ($store->platform === 'shopee') {
            return $this->refreshShopee($store);
        }

        try {
            $token = $this->lazadaTokens->accessToken($storeId);
            $response = $this->lazada->read('/seller/warehouse/get', $token,
                ['addressTypes' => '["warehouse"]'], $storeId);
            $facilities = $this->normalizeLazada($response);

            return $this->registry->confirm($storeId, $facilities);
        } catch (StoreInventoryAuthorizationExpired) {
            $this->registry->fail($storeId, 'authorization_expired');
        } catch (InvalidArgumentException) {
            $this->registry->fail($storeId, 'provider_response_incomplete');
        } catch (RuntimeException $error) {
            $code = str_contains(strtolower($error->getMessage()), 'expired')
                || str_contains(strtolower($error->getMessage()), 'authorize')
                ? 'authorization_expired' : 'provider_unavailable';
            $this->registry->fail($storeId, $code);
        }

        return $this->registry->read($storeId);
    }

    private function refreshShopee(object $store): array
    {
        $storeId = (int) $store->id;
        try {
            $token = $this->shopeeTokens->accessToken($storeId);
            $client = app(ShopeeApplicationResolver::class)->clientForStore($storeId, $this->shopee);
            $rows = $client->stockLocations($token, (string) $store->external_shop_id);
            $facilities = [];
            foreach ($rows as $row) {
                $id = trim((string) ($row['location_id'] ?? ''));
                if ($id === '') {
                    throw new InvalidArgumentException('Shopee stock location is missing.');
                }
                $facilities[] = ['provider_id' => $id,
                    'name' => trim((string) ($row['warehouse_name'] ?? '')) ?: $id];
            }
            return $this->registry->confirm($storeId, $facilities);
        } catch (RuntimeException|InvalidArgumentException) {
            // A usable default route is a JW policy, not a verified provider warehouse.
            $this->registry->confirm($storeId, []);
            DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)
                ->update(['last_error_code' => 'default_inventory_fallback', 'verified_at' => null]);
            return $this->registry->read($storeId);
        }
    }

    /** @return list<array{provider_id:string,name:string}> */
    private function normalizeLazada(array $rows): array
    {
        if (! array_is_list($rows)) {
            throw new InvalidArgumentException('Lazada warehouse list is incomplete.');
        }
        $facilities = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Lazada warehouse row is invalid.');
            }
            $code = $row['code'] ?? $row['warehouseCode'] ?? $row['warehouse_code'] ?? null;
            $name = $row['name'] ?? $row['warehouseName'] ?? $row['warehouse_name'] ?? $code;
            $status = $row['status'] ?? null;
            if (! is_string($code) || trim($code) === '' || ! is_string($name) || trim($name) === ''
                || ! is_string($status) || ! in_array(strtoupper($status), ['ACTIVE', 'INACTIVE'], true)) {
                throw new InvalidArgumentException('Lazada warehouse row is incomplete.');
            }
            if (strtoupper($status) === 'ACTIVE') {
                $facilities[] = ['provider_id' => $code, 'name' => $name];
            }
        }

        return $facilities;
    }
}
