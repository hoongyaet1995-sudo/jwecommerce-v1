<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStoreToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopeeTokenManager
{
    public function __construct(private ShopeeClient $client, private ShopeeApplicationResolver $applications) {}

    /** Server-only. Never return this value through an HTTP response. */
    public function accessToken(int $storeId): string
    {
        return DB::transaction(function () use ($storeId) {
            // Same lock order as reconnect/disconnect. The bounded HTTP request serializes rotation.
            // No transaction retries: a refresh token may already have rotated at the provider.
            $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (!$store || $store->status !== 'connected' || $store->platform !== 'shopee') {
                throw new RuntimeException('Store is not connected to the configured Shopee environment.');
            }
            $context = $this->applications->forStore($storeId);
            $token = MarketplaceStoreToken::where('store_id', $storeId)->lockForUpdate()->first();
            if (!$token) {
                throw new RuntimeException('Store credentials are missing. Re-authorize the store.');
            }
            if (!$token->application_id || $token->application_id !== $context->partnerId) {
                throw new RuntimeException('Store application has changed. Re-authorize the store.');
            }
            if ($token->access_expires_at && $token->access_expires_at->gt(now()->addMinutes(5))) {
                return $token->access_token;
            }
            if (!$token->refresh_token || ($token->refresh_expires_at && $token->refresh_expires_at->lte(now()))) {
                throw new RuntimeException('Store credentials have expired. Re-authorize the store.');
            }
            $new = $this->applications->clientForStore($storeId, $this->client)
                ->refresh($token->refresh_token, $store->external_shop_id);
            $token->fill(['access_token'=>$new['access_token'], 'refresh_token'=>$new['refresh_token'],
                'access_expires_at'=>now()->addSeconds($new['expire_in']), 'refresh_expires_at'=>null])->save();

            return $token->access_token;
        });
    }
}
