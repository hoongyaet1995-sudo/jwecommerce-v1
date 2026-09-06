<?php

namespace App\Services\Marketplace;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopeeClient
{
    public function isAuthorizationReady(): bool
    {
        // Direct callbacks only. A shared authorization gateway needs a separate reviewed contract.
        $expected = rtrim((string) config('app.url'), '/').'/admin/marketplace/shopee/callback';
        return $this->isConfigured() && config('marketplace.shopee.callback_url') === $expected;
    }

    public function isConfigured(): bool
    {
        try {
            $this->configuration();
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function configuration(): array
    {
        $config = config('marketplace.shopee', []);
        if (empty($config['enabled']) || !ctype_digit((string) ($config['partner_id'] ?? ''))
            || empty($config['partner_key']) || !in_array($config['environment'] ?? '', ['sandbox', 'production'], true)
            || filter_var($config['callback_url'] ?? '', FILTER_VALIDATE_URL) === false
            || parse_url($config['callback_url'], PHP_URL_SCHEME) !== 'https'
            || parse_url($config['callback_url'], PHP_URL_QUERY) !== null
            || parse_url($config['callback_url'], PHP_URL_FRAGMENT) !== null) {
            throw new RuntimeException('Shopee is not configured for authorization.');
        }

        return $config;
    }

    private function host(array $config): string
    {
        return $config['environment'] === 'production'
            ? 'https://partner.shopeemobile.com' : 'https://partner.test-stable.shopeemobile.com';
    }

    private function signedQuery(array $config, string $path): array
    {
        $timestamp = now()->timestamp;

        return ['partner_id' => (int) $config['partner_id'], 'timestamp' => $timestamp,
            'sign' => hash_hmac('sha256', $config['partner_id'].$path.$timestamp, $config['partner_key'])];
    }

    public function authorizationUrl(string $state): string
    {
        $config = $this->configuration();
        $path = '/api/v2/shop/auth_partner';
        $query = $this->signedQuery($config, $path);
        $query['redirect'] = $config['callback_url'].'?'.http_build_query(['state' => $state]);

        // Sandbox seller authorization uses its Open Platform gateway. Keep API routing separate.
        $host = $config['environment'] === 'sandbox'
            ? 'https://openplatform.sandbox.test-stable.shopee.sg' : $this->host($config);
        return $host.$path.'?'.http_build_query($query);
    }

    public function shopInfo(#[\SensitiveParameter] string $accessToken, string $shopId): array
    {
        $config = $this->configuration();
        if ($accessToken === '' || !ctype_digit($shopId) || (int) $shopId <= 0) {
            throw new RuntimeException('Shopee store identity could not be verified.');
        }
        $path = '/api/v2/shop/get_shop_info';
        $timestamp = now()->timestamp;
        try {
            $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()
                ->get($this->host($config).$path, [
                    'partner_id' => (int) $config['partner_id'], 'timestamp' => $timestamp,
                    'shop_id' => $shopId, 'access_token' => $accessToken,
                    'sign' => hash_hmac('sha256', $config['partner_id'].$path.$timestamp.$accessToken.$shopId, $config['partner_key']),
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Shopee store identity could not be verified.');
        }
        $payload = $response->json();
        if (!$response->successful() || !is_array($payload) || !empty($payload['error'])
            || !is_string($payload['shop_name'] ?? null) || trim($payload['shop_name']) === ''
            || !is_string($payload['region'] ?? null) || $payload['region'] !== 'MY'
            || (isset($payload['shop_id']) && (string) $payload['shop_id'] !== $shopId)) {
            throw new RuntimeException('Shopee store identity could not be verified.');
        }

        // The successful shop-bound request verifies identity even when the endpoint omits shop_id.
        return ['shop_id' => $shopId, 'shop_name' => $payload['shop_name'], 'region' => $payload['region']];
    }

    public function catalogPage(#[\SensitiveParameter] string $accessToken, string $shopId, int $offset, string $status = 'NORMAL'): array
    {
        if ($offset < 0 || !in_array($status, ['NORMAL', 'BANNED', 'DELETED', 'UNLIST'], true)) {
            throw new RuntimeException('Invalid catalog filter.');
        }
        $payload = $this->readShop('/api/v2/product/get_item_list', $accessToken, $shopId,
            ['offset'=>$offset, 'page_size'=>50, 'item_status'=>$status]);
        $page = $payload['response'] ?? null;
        if (!is_array($page) || !is_array($page['item'] ?? null) || !is_bool($page['has_next_page'] ?? null)) {
            throw new RuntimeException('Shopee catalog response was incomplete.');
        }

        return $page;
    }

    public function orderPage(#[\SensitiveParameter] string $accessToken, string $shopId, int $from, int $to, string $cursor = ''): array
    {
        if ($from < 0 || $to <= $from || $to - $from > 14 * 86400 || strlen($cursor) > 2000) {
            throw new RuntimeException('Invalid Shopee order time window or cursor.');
        }
        $payload = $this->readShop('/api/v2/order/get_order_list', $accessToken, $shopId, [
            'time_range_field'=>'update_time', 'time_from'=>$from, 'time_to'=>$to, 'page_size'=>50, 'cursor'=>$cursor,
        ]);
        $page = $payload['response'] ?? null;
        if (!is_array($page) || !is_array($page['order_list'] ?? null) || !is_bool($page['more'] ?? null)) {
            throw new RuntimeException('Shopee order response was incomplete.');
        }
        if ($page['more'] && (!is_string($page['next_cursor'] ?? null) || $page['next_cursor'] === '' || $page['next_cursor'] === $cursor)) {
            throw new RuntimeException('Shopee order pagination did not advance.');
        }
        foreach ($page['order_list'] as $order) {
            if (!is_array($order) || !is_string($order['order_sn'] ?? null) || $order['order_sn'] === '') {
                throw new RuntimeException('Shopee order identity was missing.');
            }
        }

        return $page;
    }

    public function orderDetails(#[\SensitiveParameter] string $accessToken, string $shopId, array $orderNumbers): array
    {
        if (!$orderNumbers || count($orderNumbers) > 50) {
            throw new RuntimeException('Request between 1 and 50 order numbers.');
        }
        foreach ($orderNumbers as $number) {
            if (!is_string($number) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $number)) {
                throw new RuntimeException('Invalid Shopee order number.');
            }
        }
        $payload = $this->readShop('/api/v2/order/get_order_detail', $accessToken, $shopId, [
            'order_sn_list'=>implode(',', $orderNumbers),
            'response_optional_fields'=>'item_list,total_amount,payment_method,recipient_address,shipping_carrier,package_list',
        ]);
        $orders = $payload['response']['order_list'] ?? null;
        if (!is_array($orders) || array_diff($orderNumbers, array_column($orders, 'order_sn'))) {
            throw new RuntimeException('Shopee did not return every requested order.');
        }

        return $orders;
    }

    public function itemDetails(#[\SensitiveParameter] string $accessToken, string $shopId, array $itemIds): array
    {
        if (!$itemIds || count($itemIds) > 50) {
            throw new RuntimeException('Request between 1 and 50 product IDs.');
        }
        foreach ($itemIds as $id) {
            if (!ctype_digit((string) $id) || (int) $id <= 0) {
                throw new RuntimeException('Invalid Shopee product ID.');
            }
        }
        $payload = $this->readShop('/api/v2/product/get_item_base_info', $accessToken, $shopId,
            ['item_id_list'=>implode(',', $itemIds)]);
        $items = $payload['response']['item_list'] ?? null;
        if (!is_array($items) || array_diff(array_map('strval', $itemIds), array_map('strval', array_column($items, 'item_id')))) {
            throw new RuntimeException('Shopee did not return every requested product.');
        }

        return $items;
    }

    public function models(#[\SensitiveParameter] string $accessToken, string $shopId, string $itemId): array
    {
        if (!ctype_digit($itemId) || (int) $itemId <= 0) {
            throw new RuntimeException('Invalid Shopee product ID.');
        }
        $payload = $this->readShop('/api/v2/product/get_model_list', $accessToken, $shopId, ['item_id'=>$itemId]);
        $models = $payload['response']['model'] ?? null;
        if (!is_array($models) || !$models) {
            throw new RuntimeException('Shopee variant response was incomplete.');
        }
        foreach ($models as $model) {
            if (!is_array($model) || !ctype_digit((string) ($model['model_id'] ?? '')) || (int) $model['model_id'] <= 0) {
                throw new RuntimeException('Shopee variant response was incomplete.');
            }
        }

        return $models;
    }

    private function readShop(string $path, #[\SensitiveParameter] string $accessToken, string $shopId, array $parameters): array
    {
        $config = $this->configuration();
        if ($accessToken === '' || !ctype_digit($shopId) || (int) $shopId <= 0) {
            throw new RuntimeException('Invalid Shopee store credentials.');
        }
        $timestamp = now()->timestamp;
        try {
            $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()
                ->get($this->host($config).$path, [
                    'partner_id'=>(int) $config['partner_id'], 'timestamp'=>$timestamp,
                    'access_token'=>$accessToken, 'shop_id'=>$shopId,
                    'sign'=>hash_hmac('sha256', $config['partner_id'].$path.$timestamp.$accessToken.$shopId, $config['partner_key']),
                ] + $parameters);
        } catch (ConnectionException) {
            throw new RuntimeException('Shopee read request could not reach the platform.');
        }
        $payload = $response->json();
        if (!$response->successful() || !is_array($payload) || !empty($payload['error'])) {
            throw new RuntimeException('Shopee read request failed. Check store authorization and retry.');
        }

        return $payload;
    }

    public function exchange(#[\SensitiveParameter] string $code, string $shopId): array
    {
        return $this->tokenRequest('/api/v2/auth/token/get', 'code', $code, $shopId);
    }

    public function refresh(#[\SensitiveParameter] string $refreshToken, string $shopId): array
    {
        return $this->tokenRequest('/api/v2/auth/access_token/get', 'refresh_token', $refreshToken, $shopId);
    }

    private function tokenRequest(string $path, string $credentialName, #[\SensitiveParameter] string $credential, string $shopId): array
    {
        $config = $this->configuration();
        if ($credential === '' || !ctype_digit($shopId) || (int) $shopId <= 0) {
            throw new RuntimeException('Invalid Shopee authorization callback.');
        }
        try {
            // Never retry one-use authorization codes automatically or follow redirects with credentials.
            $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()
                ->post($this->host($config).$path.'?'.http_build_query($this->signedQuery($config, $path)), [
                    $credentialName => $credential, 'shop_id' => (int) $shopId, 'partner_id' => (int) $config['partner_id'],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Shopee could not be reached. Re-authorize the store.');
        }
        $payload = $response->json();
        if (!$response->successful() || !is_array($payload) || !empty($payload['error'])
            || !is_string($payload['access_token'] ?? null) || $payload['access_token'] === ''
            || !is_string($payload['refresh_token'] ?? null) || $payload['refresh_token'] === ''
            || !is_numeric($payload['expire_in'] ?? null) || $payload['expire_in'] <= 0) {
            throw new RuntimeException('Shopee token exchange failed. Re-authorize the store.');
        }

        return ['access_token' => $payload['access_token'], 'refresh_token' => $payload['refresh_token'],
            'expire_in' => (int) $payload['expire_in']];
    }
}
