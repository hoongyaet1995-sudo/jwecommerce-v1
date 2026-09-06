<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStoreToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopeeAuthorizationService
{
    public function __construct(private OAuthStateService $states, private ShopeeClient $client) {}

    public function complete(string $state, int $actorId, #[\SensitiveParameter] string $code, string $shopId): int
    {
        $attempt = $this->states->consume($state, $actorId, 'shopee', config('app.url'));
        if (!$attempt) {
            throw new RuntimeException('Authorization expired or invalid. Start again.');
        }
        $tokens = $this->client->exchange($code, $shopId);
        $identity = $this->client->shopInfo($tokens['access_token'], $shopId);
        $environment = config('marketplace.shopee.environment');

        return DB::transaction(function () use ($attempt, $tokens, $identity, $environment) {
            $store = $attempt->reconnect_store_id
                ? DB::table('marketplace_stores')->where('id', $attempt->reconnect_store_id)->lockForUpdate()->first()
                : null;
            if (!DB::table('marketplace_oauth_states')->where('id', $attempt->id)->where('expires_at', '>', now())->exists()) {
                throw new RuntimeException('Authorization expired or invalid. Start again.');
            }
            if ($attempt->reconnect_store_id && (!$store || $store->platform !== 'shopee'
                || $store->environment !== $environment || $store->external_shop_id !== $identity['shop_id'])) {
                throw new RuntimeException('Reconnect must authorize the original store.');
            }
            if (!$store && DB::table('marketplace_stores')->where('platform', 'shopee')
                ->where('environment', $environment)->where('external_shop_id', $identity['shop_id'])->exists()) {
                throw new RuntimeException('This store already exists. Use Reconnect on that store.');
            }
            $values = ['platform'=>'shopee', 'environment'=>$environment, 'external_shop_id'=>$identity['shop_id'],
                'shop_name'=>$identity['shop_name'], 'region'=>$identity['region'], 'status'=>'connected', 'updated_at'=>now()];
            if ($store) {
                $id = $store->id;
                DB::table('marketplace_stores')->where('id', $id)->update($values);
            } else {
                $id = DB::table('marketplace_stores')->insertGetId($values + ['created_at'=>now()]);
            }
            MarketplaceStoreToken::updateOrCreate(['store_id'=>$id], [
                'application_id'=>(string) config('marketplace.shopee.partner_id'),
                'access_token'=>$tokens['access_token'], 'refresh_token'=>$tokens['refresh_token'],
                'access_expires_at'=>now()->addSeconds($tokens['expire_in']), 'refresh_expires_at'=>null,
            ]);

            return $id;
        });
    }
}
