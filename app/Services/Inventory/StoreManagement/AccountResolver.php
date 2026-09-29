<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AccountResolver
{
    /** @return array{id:int,variant_id:int,account_key:string,physical:?int,reserved:?int,available:?int,counted:bool,version:int} */
    public function balance(int $variantId, string $channelKey): array
    {
        return DB::transaction(function () use ($variantId, $channelKey): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || ! in_array($configuration->operating_mode, ['shared_pool', 'independent_stores'], true)) {
                $this->reject('Configure Shared Stock or Independent Stock before resolving an account.');
            }

            abort_unless(DB::table('product_variants')->where('id', $variantId)->lockForUpdate()->exists(), 404);
            [$requestedAccount, $storeId] = $this->validatedAccount($channelKey);

            if ($configuration->operating_mode === 'shared_pool') {
                $accountKey = 'shared';
                $poolKey = 'shared';
                $kind = 'shared';
                $poolStoreId = null;
            } else {
                $accountKey = $requestedAccount;
                $poolKey = 'store:'.$requestedAccount;
                $kind = 'store';
                $poolStoreId = $storeId;
                $this->provisionAccount($accountKey, $storeId);
            }

            DB::table('inventory_pools')->insertOrIgnore([
                'pool_key' => $poolKey,
                'kind' => $kind,
                'warehouse_id' => null,
                'marketplace_store_id' => $poolStoreId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $pool = DB::table('inventory_pools')->where('pool_key', $poolKey)->lockForUpdate()->first();
            if (! $pool || $pool->kind !== $kind || ($pool->marketplace_store_id === null ? null : (int) $pool->marketplace_store_id) !== $poolStoreId) {
                $this->reject('Inventory account ownership requires repair.');
            }

            $existing = DB::table('inventory_balances')
                ->where('inventory_pool_id', $pool->id)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();
            if (! $existing) {
                $knownElsewhere = ! config('inventory.current_stock_only') && DB::table('inventory_balances')
                    ->where('product_variant_id', $variantId)
                    ->whereNotNull('counted_at')
                    ->exists();
                DB::table('inventory_balances')->insert([
                    'inventory_pool_id' => $pool->id,
                    'product_variant_id' => $variantId,
                    'physical_stock' => $knownElsewhere ? 0 : null,
                    'reserved_stock' => $knownElsewhere ? 0 : null,
                    'safety_stock' => $knownElsewhere ? 0 : null,
                    'counted_at' => $knownElsewhere ? now() : null,
                    'version' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $existing = DB::table('inventory_balances')
                    ->where('inventory_pool_id', $pool->id)
                    ->where('product_variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();
            }

            return $this->present($existing, $accountKey);
        }, 3);
    }

    /** @return array{0:string,1:?int} */
    private function validatedAccount(string $channelKey): array
    {
        if ($channelKey === 'website') {
            return ['website', null];
        }
        if (! preg_match('/^marketplace:([1-9][0-9]*)$/D', $channelKey, $matches)) {
            $this->reject('Choose a valid JW Storefront, Shopee, or Lazada account.');
        }
        $storeId = (int) $matches[1];
        $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
        if (! $store || ! in_array($store->platform, ['shopee', 'lazada'], true)) {
            $this->reject('Choose a connected Shopee or Lazada account.');
        }

        return ['marketplace:'.$storeId, $storeId];
    }

    private function provisionAccount(string $accountKey, ?int $storeId): void
    {
        DB::table('inventory_store_accounts')->insertOrIgnore([
            'account_key' => $accountKey,
            'marketplace_store_id' => $storeId,
            'management_status' => 'setup_required',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $account = DB::table('inventory_store_accounts')->where('account_key', $accountKey)->lockForUpdate()->first();
        if (! $account || ($account->marketplace_store_id === null ? null : (int) $account->marketplace_store_id) !== $storeId) {
            $this->reject('Inventory account identity requires repair.');
        }
    }

    private function present(object $balance, string $accountKey): array
    {
        if (config('inventory.current_stock_only')) {
            $current = $balance->current_stock === null ? null : (int) $balance->current_stock;

            return [
                'id' => (int) $balance->id,
                'variant_id' => (int) $balance->product_variant_id,
                'account_key' => $accountKey,
                'physical' => null,
                'reserved' => null,
                'available' => $current,
                'current_stock' => $current,
                'counted' => $current !== null,
                'version' => (int) $balance->version,
            ];
        }
        $physical = $balance->physical_stock === null ? null : (int) $balance->physical_stock;
        $reserved = $balance->reserved_stock === null ? null : (int) $balance->reserved_stock;
        $safety = $balance->safety_stock === null ? null : (int) $balance->safety_stock;
        $available = $physical === null || $reserved === null || $safety === null
            ? null
            : max(0, $physical - $reserved - $safety);

        return [
            'id' => (int) $balance->id,
            'variant_id' => (int) $balance->product_variant_id,
            'account_key' => $accountKey,
            'physical' => $physical,
            'reserved' => $reserved,
            'available' => $available,
            'counted' => $balance->counted_at !== null,
            'version' => (int) $balance->version,
        ];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['channel_key' => $message]);
    }
}
