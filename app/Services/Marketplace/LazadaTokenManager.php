<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStoreToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaTokenManager
{
    public function __construct(private LazadaClient $client) {}

    /** Server-only credential. Never serialize into merchant API responses. */
    public function accessToken(int $storeId): string
    {
        // Lock order agrees with disconnect/reconnect. No retry of a potentially rotated token.
        return DB::transaction(function () use ($storeId) {
            $configuration=config('marketplace.lazada');
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            if (!$store || $store->platform!=='lazada' || $store->environment!=='live' || $store->region!=='MY'
                || $store->status!=='connected' || config('marketplace.lazada.enabled')!==true) {
                throw new RuntimeException('Lazada store is not connected.');
            }
            $token=MarketplaceStoreToken::where('store_id',$storeId)->lockForUpdate()->first();
            if (!$token || !$token->application_id) {
                throw new RuntimeException('Lazada application credentials changed. Re-authorize the store.');
            }
            if ($token->credential_source==='official_test') {
                if ($token->application_id!==(string)config('marketplace.lazada.test_app_key')) {
                    throw new RuntimeException('Lazada application credentials changed. Re-authorize the store.');
                }
                if (!LazadaOfficialTestAccess::environmentAllows($store, $token->application_id)
                    || !$token->test_lease_expires_at?->isFuture() || !$token->access_token) {
                    throw new RuntimeException('Official test credential lease expired or unavailable in this environment.');
                }
                return $token->access_token;
            }
            if ($token->application_id!==(string)config('marketplace.lazada.app_key')) {
                throw new RuntimeException('Lazada application credentials changed. Re-authorize the store.');
            }
            if ($token->access_token && $token->access_expires_at?->gt(now()->addMinutes(5))) return $token->access_token;
            if (!$token->refresh_token || !$token->refresh_expires_at || $token->refresh_expires_at->lte(now())) {
                throw new RuntimeException('Lazada credentials expired. Re-authorize the store.');
            }
            $new=$this->client->refresh($token->refresh_token);
            if ($new['seller_id']!==$store->external_shop_id || config('marketplace.lazada')!==$configuration) {
                throw new RuntimeException('Lazada refresh identity changed. Re-authorize the original store.');
            }
            $token->fill(['access_token'=>$new['access_token'],'refresh_token'=>$new['refresh_token'],
                'access_expires_at'=>now()->addSeconds($new['expires_in']),
                'refresh_expires_at'=>now()->addSeconds($new['refresh_expires_in'])])->save();
            return $token->access_token;
        });
    }
}
