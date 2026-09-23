<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ShopeeApplicationResolver
{
    public function forEnvironment(string $environment): ShopeeApplicationContext
    {
        if (! in_array($environment, ['sandbox', 'production'], true)) {
            throw new RuntimeException('Unsupported Shopee environment.');
        }
        $settings = config("marketplace.shopee.applications.$environment", []);
        if (empty($settings['partner_id']) && empty($settings['partner_key'])
            && config('marketplace.shopee.environment') === $environment) {
            if ($environment === 'sandbox'
                && (string)config('marketplace.shopee.partner_id') !== ''
                && (string)config('marketplace.shopee.partner_id') === (string)config('marketplace.shopee.applications.production.partner_id')) {
                throw new RuntimeException('Shopee Sandbox application is not configured.');
            }
            $settings = ['partner_id' => config('marketplace.shopee.partner_id'),
                'partner_key' => config('marketplace.shopee.partner_key')];
        }
        $partnerId = (string) ($settings['partner_id'] ?? '');
        $partnerKey = $settings['partner_key'] ?? null;
        $callbackUrl = $settings['callback_url'] ?? config('marketplace.shopee.callback_url');
        if (config('marketplace.shopee.enabled') !== true || ! ctype_digit($partnerId) || (int) $partnerId <= 0
            || ! is_string($partnerKey) || $partnerKey === '' || ! is_string($callbackUrl)
            || filter_var($callbackUrl, FILTER_VALIDATE_URL) === false
            || parse_url($callbackUrl, PHP_URL_SCHEME) !== 'https'
            || parse_url($callbackUrl, PHP_URL_QUERY) !== null
            || parse_url($callbackUrl, PHP_URL_FRAGMENT) !== null) {
            throw new RuntimeException('Shopee application is not configured for this environment.');
        }

        return new ShopeeApplicationContext(
            $environment, $partnerId, $partnerKey, $callbackUrl,
            $environment === 'production' ? 'https://open.shopee.com' : 'https://open.sandbox.test-stable.shopee.com',
            $environment === 'production' ? 'https://partner.shopeemobile.com' : 'https://openplatform.sandbox.test-stable.shopee.sg',
        );
    }

    public function forStore(int $storeId): ShopeeApplicationContext
    {
        $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
        if (! $store || $store->platform !== 'shopee' || $store->status !== 'connected') {
            throw new RuntimeException('Shopee store is not connected.');
        }
        $context = $this->forEnvironment($store->environment);
        $token = DB::table('marketplace_store_tokens')->where('store_id', $storeId)->first();
        if (! $token || (string) $token->application_id !== $context->partnerId) {
            throw new RuntimeException('Store application has changed. Re-authorize the store.');
        }

        return $context;
    }

    public function clientForStore(int $storeId, ShopeeClient $client): ShopeeClient
    {
        $context = $this->forStore($storeId);
        if ($context->environment === config('marketplace.shopee.environment')
            && $context->partnerId === (string) config('marketplace.shopee.partner_id')
            && hash_equals($context->partnerKey, (string) config('marketplace.shopee.partner_key'))) {
            return $client;
        }

        return $client->forApplication($context);
    }

    public function isStoreReady(int $storeId): bool
    {
        try {
            $this->forStore($storeId);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function isEnvironmentReady(string $environment): bool
    {
        try {
            $this->forEnvironment($environment);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function isEnvironmentAuthorizationReady(string $environment): bool
    {
        try {
            $context = $this->forEnvironment($environment);
            $base = config('marketplace.hub.oauth_routing_enabled') === true
                ? 'https://marketplace.jwaistudio.online' : rtrim((string) config('app.url'), '/');
            return $context->callbackUrl === $base.'/admin/marketplace/oauth/shopee/callback';
        } catch (RuntimeException) {
            return false;
        }
    }
}
