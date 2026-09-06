<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketplaceOrderSnapshotService
{
    public function saveShopee(int $storeId, #[\SensitiveParameter] array $order): int
    {
        if (!is_string($order['order_sn'] ?? null) || $order['order_sn'] === '' || strlen($order['order_sn']) > 100
            || !is_string($order['order_status'] ?? null) || $order['order_status'] === '' || strlen($order['order_status']) > 60
            || !is_int($order['update_time'] ?? null) || $order['update_time'] < 0) {
            throw new RuntimeException('Marketplace order identity, status or version is incomplete.');
        }

        return DB::transaction(function () use ($storeId, $order) {
            $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (!$store || $store->platform !== 'shopee' || $store->status !== 'connected') {
                throw new RuntimeException('Store is not connected for order ingestion.');
            }
            $key = ['store_id'=>$storeId, 'external_order_id'=>$order['order_sn']];
            $existing = DB::table('marketplace_orders')->where($key)->first();
            if ($existing && $existing->platform_updated_at >= $order['update_time']) {
                return $existing->id;
            }
            $values = ['platform_status'=>$order['order_status'], 'platform_updated_at'=>$order['update_time'],
                'currency'=>$order['currency'] ?? null, 'total_amount'=>$order['total_amount'] ?? null,
                'encrypted_payload'=>Crypt::encryptString(json_encode($order, JSON_THROW_ON_ERROR)),
                'fetched_at'=>now(), 'updated_at'=>now()];
            if ($existing) {
                DB::table('marketplace_orders')->where('id', $existing->id)->update($values);
                return $existing->id;
            }

            return DB::table('marketplace_orders')->insertGetId($key + $values + ['created_at'=>now()]);
        });
    }
}
