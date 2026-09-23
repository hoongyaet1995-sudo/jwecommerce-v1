<?php

namespace App\Http\Controllers;

use App\Services\Marketplace\LazadaTokenManager;
use App\Services\Marketplace\ShopeeTokenManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class MarketplaceHubRefreshController extends Controller
{
    public function __invoke(Request $request, ShopeeTokenManager $shopee, LazadaTokenManager $lazada): JsonResponse
    {
        abort_unless(config('marketplace.hub.refresh_endpoint_enabled') === true, 404);
        $clientId = (string) $request->header('X-Hub-Client');
        $timestamp = (string) $request->header('X-Hub-Timestamp');
        $nonce = (string) $request->header('X-Hub-Nonce');
        $signature = (string) $request->header('X-Hub-Signature');
        $secret = (string) config('marketplace.hub.client_secret');
        abort_unless($secret !== '' && hash_equals((string) config('marketplace.hub.client_id'), $clientId)
            && ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300
            && preg_match('/^[a-zA-Z0-9_-]{12,100}$/D', $nonce)
            && preg_match('/^[a-f0-9]{64}$/D', $signature), 401);
        $expected = hash_hmac('sha256', "$timestamp.$nonce.".$request->getContent(), $secret);
        abort_unless(hash_equals($expected, $signature), 401);
        abort_unless(Cache::add('hub-refresh:'.$nonce, true, 600), 409);
        $data = json_decode($request->getContent(), true);
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST);
        abort_unless(is_array($data) && is_string($domain) && ($data['domain'] ?? null) === strtolower($domain)
            && ($data['operation'] ?? null) === 'refresh_store'
            && is_string($data['clientStoreId'] ?? null)
            && preg_match('/^[1-9][0-9]*$/D', $data['clientStoreId']), 422);
        $storeId = (int) $data['clientStoreId'];
        $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
        abort_unless($store && $store->status === 'connected'
            && in_array($store->platform, ['shopee', 'lazada'], true), 404);
        $before = DB::table('marketplace_store_tokens')->where('store_id', $storeId)->value('access_expires_at');
        abort_unless($before, 409, 'Store has no renewable access expiry.');
        if (now()->addMinutes(5)->lt($before)) {
            return response()->json(['status' => 'not_due', 'storeId' => (string) $storeId,
                'accessExpiresAt' => $before])->header('Cache-Control', 'no-store');
        }
        $credential = DB::table('marketplace_store_tokens')->where('store_id', $storeId)
            ->select('credential_source', 'refresh_expires_at')
            ->selectRaw("CASE WHEN refresh_token IS NOT NULL AND refresh_token <> '' THEN 1 ELSE 0 END AS has_refresh_token")
            ->first();
        if (! $credential || $credential->credential_source === 'official_test'
            || ! $credential->has_refresh_token
            || ($credential->refresh_expires_at && now()->greaterThanOrEqualTo($credential->refresh_expires_at))) {
            return response()->json(['status' => 'reauthorization_required', 'storeId' => (string) $storeId], 409)
                ->header('Cache-Control', 'no-store');
        }
        try {
            if ($store->platform === 'shopee') $shopee->accessToken($storeId);
            else $lazada->accessToken($storeId);
        } catch (\Throwable) {
            return response()->json(['status' => 'failed', 'storeId' => (string) $storeId,
                'message' => 'Store token could not be renewed. Check the client store connection.'], 409)
                ->header('Cache-Control', 'no-store');
        }
        $expiry = DB::table('marketplace_store_tokens')->where('store_id', $storeId)->value('access_expires_at');
        return response()->json(['status' => 'refreshed', 'storeId' => (string) $storeId,
            'accessExpiresAt' => $expiry])->header('Cache-Control', 'no-store');
    }
}
