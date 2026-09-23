<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\ShopeeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class MarketplaceHubShopeeVerificationController extends Controller
{
    public function __invoke(Request $request, ShopeeClient $shopee): JsonResponse
    {
        abort_unless(config('marketplace.hub.verification_endpoint_enabled') === true, 404);
        $body = $request->getContent();
        $timestamp = (string) $request->header('X-Hub-Timestamp');
        $nonce = (string) $request->header('X-Hub-Nonce');
        $signature = (string) $request->header('X-Hub-Signature');
        $clientSecret = (string) config('marketplace.hub.client_secret');
        abort_unless($clientSecret !== '' && hash_equals((string) config('marketplace.hub.client_id'), (string) $request->header('X-Hub-Client'))
            && ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300
            && preg_match('/^[a-zA-Z0-9_-]{12,100}$/D', $nonce)
            && preg_match('/^[a-f0-9]{64}$/D', $signature)
            && hash_equals(hash_hmac('sha256', "$timestamp.$nonce.$body", $clientSecret), $signature), 401);
        abort_unless(Cache::add('hub-verify-shopee:'.$nonce, true, 600), 409);
        $data = json_decode($body, true);
        abort_unless(is_array($data) && ($data['domain'] ?? null) === parse_url((string) config('app.url'), PHP_URL_HOST)
            && in_array($data['environment'] ?? null, ['production', 'sandbox'], true)
            && is_string($data['applicationId'] ?? null) && preg_match('/^[1-9][0-9]*$/D', $data['applicationId'])
            && is_string($data['clientStoreId'] ?? null) && preg_match('/^[1-9][0-9]*$/D', $data['clientStoreId'])
            && is_string($data['challenge'] ?? null) && preg_match('/^[a-f0-9]{32}$/D', $data['challenge'])
            && is_string($data['applicationProof'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $data['applicationProof']), 422);
        $environment = $data['environment'];
        $partnerId = (string) config('marketplace.shopee.applications.'.$environment.'.partner_id');
        $partnerKey = (string) config('marketplace.shopee.applications.'.$environment.'.partner_key');
        $expected = hash_hmac('sha256', 'shopee|'.$environment.'|'.$data['applicationId'].'|'.$data['challenge'], $partnerKey);
        if ($partnerId !== $data['applicationId'] || $partnerKey === ''
            || !hash_equals($expected, $data['applicationProof'])) {
            return response()->json(['status' => 'application_mismatch'], 409)->header('Cache-Control', 'no-store');
        }
        $store = DB::table('marketplace_stores')->where('id', $data['clientStoreId'])
            ->where('platform', 'shopee')->where('environment', $environment)
            ->where('status', 'connected')->first();
        $token = $store ? MarketplaceStoreToken::where('store_id', $store->id)->first() : null;
        if (!$store || !$token || $token->application_id !== $partnerId || !$token->access_expires_at
            || $token->access_expires_at->lte(now()->addMinutes(2))) {
            return response()->json(['status' => 'store_reauthorization_required'], 409)->header('Cache-Control', 'no-store');
        }
        try {
            $identity = $shopee->shopInfoWithApplication($token->access_token, (string) $store->external_shop_id,
                $environment, $partnerId, $partnerKey);
            if (($identity['shop_id'] ?? null) !== (string) $store->external_shop_id) {
                throw new \RuntimeException('Store identity mismatch.');
            }
        } catch (\Throwable) {
            return response()->json(['status' => 'provider_rejected'], 409)->header('Cache-Control', 'no-store');
        }
        return response()->json(['status' => 'verified', 'environment' => $environment,
            'applicationId' => $partnerId, 'clientStoreId' => (string) $store->id,
            'shopId' => (string) $store->external_shop_id, 'verifiedAt' => now()->toIso8601String()])
            ->header('Cache-Control', 'no-store');
    }
}
