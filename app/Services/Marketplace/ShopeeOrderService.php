<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopeeOrderService
{
    public function __construct(private ShopeeClient $client, private ShopeeTokenManager $tokens,
        private MarketplaceOrderSnapshotService $snapshots, private ShopeeApplicationResolver $applications) {}

    public function refreshOrder(int $localOrderId): int
    {
        $order=DB::table('marketplace_orders')->find($localOrderId);
        $store=$order ? DB::table('marketplace_stores')->find($order->store_id) : null;
        if (!$store || $store->platform!=='shopee' || $store->status!=='connected') {
            throw new RuntimeException('Connected Shopee order required.');
        }
        $token=$this->tokens->accessToken((int)$store->id);
        $client=$this->applications->clientForStore((int)$store->id,$this->client);
        $orders=$client->orderDetails($token,$store->external_shop_id,[$order->external_order_id]);
        if (count($orders)!==1 || ($orders[0]['order_sn']??null)!==$order->external_order_id) {
            throw new RuntimeException('Shopee order identity mismatch.');
        }
        return DB::transaction(function () use ($store,$orders) {
            $current=DB::table('marketplace_stores')->where('id',$store->id)->lockForUpdate()->first();
            if (!$current || $current->status!=='connected' || $current->external_shop_id!==$store->external_shop_id
                || $current->environment!==$store->environment) throw new RuntimeException('Store connection changed during order refresh.');
            return $this->snapshots->saveShopee((int)$store->id,$orders[0]);
        });
    }

    public function pullOrderByExternalId(int $storeId, string $externalOrderId): int
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $externalOrderId)) {
            throw new RuntimeException('Shopee order identity is invalid.');
        }
        $store = DB::table('marketplace_stores')->find($storeId);
        if (! $store || $store->platform !== 'shopee' || $store->status !== 'connected') {
            throw new RuntimeException('Connected Shopee store required.');
        }
        $token = $this->tokens->accessToken($storeId);
        $client = $this->applications->clientForStore($storeId, $this->client);
        $orders = $client->orderDetails($token, $store->external_shop_id, [$externalOrderId]);
        if (count($orders) !== 1 || ($orders[0]['order_sn'] ?? null) !== $externalOrderId) {
            throw new RuntimeException('Shopee order identity mismatch.');
        }
        return DB::transaction(function () use ($store, $orders): int {
            $current = DB::table('marketplace_stores')->where('id', $store->id)->lockForUpdate()->first();
            if (! $current || $current->status !== 'connected' || $current->external_shop_id !== $store->external_shop_id
                || $current->environment !== $store->environment) {
                throw new RuntimeException('Store connection changed during order fetch.');
            }
            return $this->snapshots->saveShopee((int) $store->id, $orders[0]);
        });
    }

    public function pullPage(int $storeId, int $from, int $to, string $cursor = ''): array
    {
        $token = $this->tokens->accessToken($storeId);
        $store = DB::table('marketplace_stores')->find($storeId);
        $client = $this->applications->clientForStore($storeId, $this->client);
        $page = $client->orderPage($token, $store->external_shop_id, $from, $to, $cursor);
        $numbers = array_column($page['order_list'], 'order_sn');
        $orders = $numbers ? $client->orderDetails($token, $store->external_shop_id, $numbers) : [];
        DB::transaction(function () use ($storeId, $store, $orders) {
            $current = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (!$current || $current->status !== 'connected' || $current->external_shop_id !== $store->external_shop_id
                || $current->environment !== $store->environment) {
                throw new RuntimeException('Store connection changed during order fetch.');
            }
            foreach ($orders as $order) {
                $this->snapshots->saveShopee($storeId, $order);
            }
        });

        return ['orders'=>count($orders), 'has_more'=>$page['more'], 'next_cursor'=>$page['next_cursor'] ?? '',
            'time_from'=>$from, 'time_to'=>$to];
    }
}
