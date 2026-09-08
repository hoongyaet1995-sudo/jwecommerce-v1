<?php

namespace App\Services\Marketplace;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopeeClient
{
    public function pickupWarehouses(#[\SensitiveParameter] string $accessToken, string $shopId): array
    {
        $payload=$this->readShop('/api/v2/shop/get_warehouse_detail',$accessToken,$shopId,['warehouse_type'=>1]);
        $rows=$payload['response'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException('Shopee warehouse response was incomplete.');
        $result=[];
        $seen=[];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_int($row['warehouse_id'] ?? null) || $row['warehouse_id']<1
                || isset($seen[$row['warehouse_id']]) || ($row['warehouse_type'] ?? null)!==1
                || !is_string($row['warehouse_name'] ?? null) || !is_string($row['location_id'] ?? null) || $row['location_id']===''
                || !is_string($row['region'] ?? null) || !preg_match('/\A[A-Z]{2}\z/D',$row['region'])
                || !in_array($row['holiday_mode_state'] ?? null,[0,1,2,3],true)) {
                throw new RuntimeException('Shopee warehouse response was incomplete.');
            }
            $seen[$row['warehouse_id']]=true;
            $result[]=array_intersect_key($row,array_flip(['warehouse_id','warehouse_name','warehouse_type','location_id','region','holiday_mode_state']));
        }
        return $result;
    }

    public function searchAttributeValues(#[\SensitiveParameter] string $accessToken, string $shopId,
        int $attributeId, string $keyword = '', int $cursor = 0): array
    {
        if ($attributeId<=0 || $cursor<0) throw new RuntimeException('Invalid attribute search.');
        $payload=$this->readShop('/api/v2/product/search_attribute_value_list',$accessToken,$shopId,
            ['attribute_id'=>$attributeId,'value_name'=>$keyword,'cursor'=>$cursor,'limit'=>100],true);
        $page=$payload['response'] ?? null;
        if (!is_array($page) || !is_array($page['value_list'] ?? null) || !array_is_list($page['value_list'])
            || !is_int($page['page_info']['cursor'] ?? null) || !is_bool($page['page_info']['has_next'] ?? null)
            || ($page['page_info']['has_next'] && $page['page_info']['cursor']<=$cursor)) {
            throw new RuntimeException('Shopee attribute search response was incomplete or pagination did not advance.');
        }
        $values=[];
        foreach ($page['value_list'] as $value) {
            if (!is_array($value) || !is_int($value['value_id'] ?? null) || $value['value_id']<=0
                || !is_string($value['value_name'] ?? null)) throw new RuntimeException('Shopee attribute value was incomplete.');
            $values[]=['value_id'=>$value['value_id'],'value_name'=>$value['value_name']];
        }
        return ['value_list'=>$values,'page_info'=>['cursor'=>$page['page_info']['cursor'],'has_next'=>$page['page_info']['has_next']]];
    }

    /** Transport only: the caller must validate the reviewed listing payload before dispatch. */
    public function createItem(#[\SensitiveParameter] string $accessToken, string $shopId, array $item): string
    {
        if (!config('marketplace.publish_operations_enabled',false)) throw new RuntimeException('Marketplace publishing is disabled.');
        $config=$this->configuration();
        if ($accessToken==='' || !ctype_digit($shopId) || (int)$shopId<=0) throw new RuntimeException('Invalid Shopee store credentials.');
        $path='/api/v2/product/add_item';
        $timestamp=now()->timestamp;
        $query=['partner_id'=>(int)$config['partner_id'],'timestamp'=>$timestamp,'access_token'=>$accessToken,'shop_id'=>(int)$shopId,
            'sign'=>hash_hmac('sha256',$config['partner_id'].$path.$timestamp.$accessToken.$shopId,$config['partner_key'])];
        try {
            $response=Http::acceptJson()->connectTimeout(10)->timeout(40)->withoutRedirecting()
                ->post($this->host($config).$path.'?'.http_build_query($query),$item);
        } catch (ConnectionException) {
            throw new PublishingResultUncertain('Shopee creation result is unknown. Reconcile before retrying.');
        }
        $payload=$response->json();
        // Conservatively retain ambiguity for all unverified error responses.
        if (!$response->successful() || !is_array($payload) || ($payload['error'] ?? null)!==''
            || !is_int($payload['response']['item_id'] ?? null) || $payload['response']['item_id']<=0) {
            throw new PublishingResultUncertain('Shopee creation was not confirmed. Reconcile before retrying.');
        }
        return (string)$payload['response']['item_id'];
    }

    public function uploadImage(#[\SensitiveParameter] string $bytes): string
    {
        if ($bytes==='' || strlen($bytes)>10000000) throw new RuntimeException('Image must be no larger than 10 MB.');
        $info=@getimagesizefromstring($bytes);
        $mime=$info['mime'] ?? null;
        if (!in_array($mime,['image/jpeg','image/png'],true)) throw new RuntimeException('Shopee requires a JPEG or PNG image.');
        $config=$this->configuration();
        $path='/api/v2/media_space/upload_image';
        try {
            $response=Http::acceptJson()->connectTimeout(10)->timeout(40)->withoutRedirecting()
                ->attach('image',$bytes,$mime==='image/png'?'product.png':'product.jpg',['Content-Type'=>$mime])
                ->post($this->host($config).$path.'?'.http_build_query($this->signedQuery($config,$path)),['scene'=>'normal']);
        } catch (ConnectionException) {
            throw new RuntimeException('Shopee image upload could not be confirmed.');
        }
        if (!$response->successful() || !is_array($response->json())) throw new RuntimeException('Shopee image upload could not be confirmed.');
        return ShopeeUploadedImage::identity($response->json());
    }

    public function logisticsChannels(#[\SensitiveParameter] string $accessToken, string $shopId): array
    {
        $payload = $this->readShop('/api/v2/logistics/get_channel_list', $accessToken, $shopId, []);
        $channels = $payload['response']['logistics_channel_list'] ?? null;
        if (!is_array($channels) || !array_is_list($channels)) {
            throw new RuntimeException('Shopee logistics response was incomplete.');
        }
        foreach ($channels as $channel) {
            if (!is_array($channel) || !is_int($channel['logistics_channel_id'] ?? null) || $channel['logistics_channel_id'] <= 0
                || !is_string($channel['logistics_channel_name'] ?? null) || !is_bool($channel['enabled'] ?? null)
                || !is_string($channel['fee_type'] ?? null)) {
                throw new RuntimeException('Shopee logistics response was incomplete.');
            }
        }
        return $channels;
    }

    public function brandPage(#[\SensitiveParameter] string $accessToken, string $shopId, int $categoryId, int $offset = 0): array
    {
        if ($categoryId <= 0 || $offset < 0) throw new RuntimeException('Invalid Shopee brand filter.');
        $payload = $this->readShop('/api/v2/product/get_brand_list', $accessToken, $shopId,
            ['category_id'=>$categoryId, 'offset'=>$offset, 'page_size'=>100, 'status'=>1, 'language'=>'en']);
        $page = $payload['response'] ?? null;
        if (!is_array($page) || !is_array($page['brand_list'] ?? null) || !array_is_list($page['brand_list'])
            || !is_bool($page['has_next_page'] ?? null) || !is_bool($page['is_mandatory'] ?? null)
            || !is_string($page['input_type'] ?? null)
            || ($page['has_next_page'] && (!is_int($page['next_offset'] ?? null) || $page['next_offset'] <= $offset))) {
            throw new RuntimeException('Shopee brand response was incomplete or pagination did not advance.');
        }
        foreach ($page['brand_list'] as $brand) {
            if (!is_array($brand) || !is_int($brand['brand_id'] ?? null) || $brand['brand_id'] < 0
                || !is_string($brand['original_brand_name'] ?? null) || !is_string($brand['display_brand_name'] ?? null)) {
                throw new RuntimeException('Shopee brand response was incomplete.');
            }
        }
        return $page;
    }

    public function attributeTree(#[\SensitiveParameter] string $accessToken, string $shopId, int $categoryId): array
    {
        if ($categoryId <= 0) throw new RuntimeException('Invalid Shopee attribute category.');
        $payload = $this->readShop('/api/v2/product/get_attribute_tree', $accessToken, $shopId,
            ['category_id_list'=>(string) $categoryId, 'language'=>'en']);
        $rows = $payload['response']['list'] ?? null;
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1
            || ($rows[0]['category_id'] ?? null) !== $categoryId || !empty($rows[0]['warning'])
            || !is_array($rows[0]['attribute_tree'] ?? null) || !array_is_list($rows[0]['attribute_tree'])) {
            throw new RuntimeException('Shopee attribute response was incomplete.');
        }
        // Preserve conditional child attributes, input types and units for the listing validator.
        return $rows[0]['attribute_tree'];
    }

    public function categoryTree(#[\SensitiveParameter] string $accessToken, string $shopId): array
    {
        $payload = $this->readShop('/api/v2/product/get_category', $accessToken, $shopId, ['language'=>'en']);
        $categories = $payload['response']['category_list'] ?? null;
        if (!is_array($categories) || !array_is_list($categories)) {
            throw new RuntimeException('Shopee category response was incomplete.');
        }
        foreach ($categories as $category) {
            if (!is_array($category) || !is_int($category['category_id'] ?? null) || $category['category_id'] <= 0
                || !is_int($category['parent_category_id'] ?? null) || $category['parent_category_id'] < 0
                || !is_string($category['original_category_name'] ?? null)
                || !is_string($category['display_category_name'] ?? null)
                || !is_bool($category['has_children'] ?? null)) {
                throw new RuntimeException('Shopee category response was incomplete.');
            }
        }
        return $categories;
    }

    public function isAuthorizationReady(): bool
    {
        // Direct callbacks only. A shared authorization gateway needs a separate reviewed contract.
        $expected = rtrim((string) config('app.url'), '/').'/admin/marketplace/oauth/shopee/callback';
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
            ? 'https://partner.shopeemobile.com' : 'https://openplatform.sandbox.test-stable.shopee.sg';
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

        return $this->host($config).$path.'?'.http_build_query($query);
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
        // Shopee omits item for a confirmed empty catalog (observed in Sandbox V2).
        if (is_array($page) && !array_key_exists('item', $page)
            && ($page['total_count'] ?? null) === 0 && ($page['has_next_page'] ?? null) === false) {
            $page['item'] = [];
        }
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
            'response_optional_fields'=>'item_list,total_amount,payment_method,recipient_address,shipping_carrier,package_list,fulfillment_flag',
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

    private function readShop(string $path, #[\SensitiveParameter] string $accessToken, string $shopId, array $parameters, bool $postSearch = false): array
    {
        $config = $this->configuration();
        if ($accessToken === '' || !ctype_digit($shopId) || (int) $shopId <= 0) {
            throw new RuntimeException('Invalid Shopee store credentials.');
        }
        $timestamp = now()->timestamp;
        try {
            $query = [
                    'partner_id'=>(int) $config['partner_id'], 'timestamp'=>$timestamp,
                    'access_token'=>$accessToken, 'shop_id'=>$shopId,
                    'sign'=>hash_hmac('sha256', $config['partner_id'].$path.$timestamp.$accessToken.$shopId, $config['partner_key']),
                ];
            $request = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting();
            $response = $postSearch
                ? $request->post($this->host($config).$path.'?'.http_build_query($query),$parameters)
                : $request->get($this->host($config).$path,$query + $parameters);
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
