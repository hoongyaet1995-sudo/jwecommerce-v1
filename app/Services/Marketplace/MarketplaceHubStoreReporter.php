<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class MarketplaceHubStoreReporter
{
    public function run(): array
    {
        if (config('marketplace.hub.store_reporting_enabled') !== true) {
            return ['reported' => 0, 'failed' => 0, 'disabled' => true];
        }
        $base = rtrim((string) config('marketplace.hub.url'), '/');
        $clientId = (string) config('marketplace.hub.client_id');
        $secret = (string) config('marketplace.hub.client_secret');
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($base !== 'https://marketplace.jwaistudio.online' || $clientId === '' || $secret === '' || ! is_string($domain)) {
            throw new RuntimeException('Marketplace Hub store reporting is not configured.');
        }
        $counts = ['reported' => 0, 'failed' => 0, 'disabled' => false];
        DB::table('marketplace_stores as s')->join('marketplace_store_tokens as t', 't.store_id', '=', 's.id')
            ->select('s.id', 's.platform', 's.environment', 's.external_shop_id', 's.shop_name', 's.region', 's.status',
                't.application_id', 't.credential_source', 't.access_expires_at')
            ->whereIn('s.platform', ['shopee', 'lazada'])->whereNotNull('s.external_shop_id')
            ->whereNotNull('t.application_id')->orderBy('s.id')->chunk(100, function ($stores) use ($base, $clientId, $secret, $domain, &$counts) {
                foreach ($stores as $store) {
                    $environment = $store->platform === 'lazada'
                        && $store->environment === 'live'
                        && $store->credential_source === 'official_test'
                        && (string) $store->application_id === (string) config('marketplace.lazada.test_app_key')
                        ? 'sandbox'
                        : ($store->environment === 'live' ? 'production' : $store->environment);
                    if (! in_array($environment, ['sandbox', 'production'], true)) continue;
                    $body = json_encode(['domain' => strtolower($domain), 'store' => [
                        'clientStoreId' => (string) $store->id,
                        'provider' => $store->platform, 'environment' => $environment,
                        'applicationId' => (string) $store->application_id,
                        'shopId' => (string) $store->external_shop_id,
                        'name' => (string) $store->shop_name,
                        'region' => (string) $store->region,
                        'status' => in_array($store->status, ['connected', 'disconnected'], true)
                            ? $store->status : 'reauthorization_required',
                        'authExpiresAt' => $store->access_expires_at,
                    ]], JSON_THROW_ON_ERROR);
                    $timestamp = (string) now()->timestamp;
                    $nonce = Str::random(32);
                    try {
                        $response = Http::timeout(10)->withoutRedirecting()->withHeaders([
                            'X-Hub-Client' => $clientId, 'X-Hub-Timestamp' => $timestamp,
                            'X-Hub-Nonce' => $nonce,
                            'X-Hub-Signature' => hash_hmac('sha256', "$timestamp.$nonce.$body", $secret),
                        ])->withBody($body, 'application/json')->post($base.'/api/stores/receipt');
                        $response->successful() ? $counts['reported']++ : $counts['failed']++;
                    } catch (\Throwable) {
                        $counts['failed']++;
                    }
                }
            });
        return $counts;
    }
}
