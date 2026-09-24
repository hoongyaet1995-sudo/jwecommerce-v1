<?php

namespace App\Services\Inventory\StoreManagement;

use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Services\Marketplace\LazadaClient;
use App\Services\Marketplace\LazadaTokenManager;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class FacilityDiscovery
{
    public function __construct(
        private readonly FacilityRegistry $registry,
        private readonly LazadaTokenManager $lazadaTokens,
        private readonly LazadaClient $lazada,
    ) {}

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
            // pickupWarehouses() requests warehouse_type=1 only. It cannot prove
            // that every sellable stock location has been discovered.
            $this->registry->fail($storeId, 'complete_location_api_unverified');

            return $this->registry->read($storeId);
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
