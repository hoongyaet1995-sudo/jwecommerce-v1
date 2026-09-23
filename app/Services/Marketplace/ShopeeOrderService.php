<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopeeOrderService
{
    public function __construct(private ShopeeClient $client, private ShopeeTokenManager $tokens,
        private MarketplaceOrderSnapshotService $snapshots, private ShopeeApplicationResolver $applications) {}

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
