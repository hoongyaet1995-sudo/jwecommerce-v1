<?php

namespace App\Services\Marketplace;

use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopeeClient
{
    /** @return array{location_id:string,quantity:int} */
    public function exactStockContext(#[\SensitiveParameter] string $accessToken, string $shopId, string $itemId,
        string $modelId, ?string $locationId = null): array
    {
        $items = array_values(array_filter($this->itemDetails($accessToken, $shopId, [$itemId]),
            fn (array $item): bool => (string) ($item['item_id'] ?? '') === $itemId));
        if (count($items) !== 1 || ! is_bool($items[0]['has_model'] ?? null)) {
            throw new RuntimeException('Shopee item identity changed.');
        }
        $item = $items[0];
        if ($item['has_model']) {
            $models = array_values(array_filter($this->models($accessToken, $shopId, $itemId),
                fn (array $model): bool => (string) ($model['model_id'] ?? '') === $modelId));
            if (count($models) !== 1) {
                throw new RuntimeException('Shopee model identity changed.');
            }
            $stock = $models[0]['stock_info_v2'] ?? null;
        } elseif ($modelId === '0') {
            $stock = $item['stock_info_v2'] ?? null;
        } else {
            throw new RuntimeException('Shopee item/model structure changed.');
        }
        $rows = $stock['seller_stock'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('Shopee seller stock is unavailable.');
        }
        $matches = array_values(array_filter($rows, fn ($row): bool => is_array($row)
            && ($locationId === null || ($row['location_id'] ?? null) === $locationId)));
        if (count($matches) !== 1 || ! is_string($matches[0]['location_id'] ?? null)
            || $matches[0]['location_id'] === '' || ! is_int($matches[0]['stock'] ?? null) || $matches[0]['stock'] < 0) {
            throw new RuntimeException('Shopee seller location is ambiguous or unavailable.');
        }

        return ['location_id' => $matches[0]['location_id'], 'quantity' => $matches[0]['stock']];
    }

    /** @return array{request_id:?string} */
    public function updateAbsoluteStock(#[\SensitiveParameter] string $accessToken, string $shopId, string $itemId,
        string $modelId, string $locationId, int $quantity): array
    {
        if (! ctype_digit($shopId) || ! ctype_digit($itemId) || ! ctype_digit($modelId)
            || $locationId === '' || $quantity < 0) {
            throw new RuntimeException('Invalid Shopee stock identity.');
        }
        $config = $this->configuration();
        $path = '/api/v2/product/update_stock';
        $time = now()->timestamp;
        $query = ['partner_id' => (int) $config['partner_id'], 'timestamp' => $time, 'access_token' => $accessToken,
            'shop_id' => (int) $shopId, 'sign' => hash_hmac('sha256', $config['partner_id'].$path.$time.$accessToken.$shopId, $config['partner_key'])];
        $body = ['item_id' => (int) $itemId, 'stock_list' => [['model_id' => (int) $modelId,
            'seller_stock' => [['location_id' => $locationId, 'stock' => $quantity]]]]];
        try {
            $response = Http::acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()
                ->post($this->host($config).$path.'?'.http_build_query($query), $body);
        } catch (ConnectionException) {
            throw new StoreInventoryResultUncertain('Shopee stock write outcome is unknown. Reconcile before retrying.');
        }
        $payload = $response->json();
        if ($this->authorizationFailed($response->status(), $payload)) {
            throw new StoreInventoryAuthorizationExpired('Shopee authorization expired. Reconnect this store.');
        }
        if ($response->serverError() || ! is_array($payload)) {
            throw new StoreInventoryResultUncertain('Shopee did not return a conclusive stock result. Reconcile before retrying.');
        }
        $result = is_array($payload) ? ($payload['response'] ?? null) : null;
        $success = is_array($result) && is_array($result['success_list'] ?? null) ? $result['success_list'] : [];
        $failure = is_array($result) && is_array($result['failure_list'] ?? null) ? $result['failure_list'] : [];
        if ($success !== [] && $failure !== []) {
            throw new StoreInventoryResultUncertain('Shopee returned a partial stock result. Reconcile before retrying.');
        }
        if (! $response->successful() || ($payload['error'] ?? null) !== '' || $failure !== []) {
            throw new RuntimeException('Shopee rejected the stock update.');
        }
        $matches = array_values(array_filter($success, fn ($row): bool => is_array($row)
            && (string) ($row['model_id'] ?? '') === $modelId && ($row['location_id'] ?? null) === $locationId
            && ($row['stock'] ?? null) === $quantity));
        if (count($matches) !== 1) {
            throw new StoreInventoryResultUncertain('Shopee did not confirm the exact stock target. Reconcile before retrying.');
        }

        return ['request_id' => is_string($payload['request_id'] ?? null) ? $payload['request_id'] : null];
    }
    public function sandboxStockQuantity(array $snapshot,\App\Models\User $actor):int
    {
        return $this->sandboxStockContext($snapshot,$actor)['quantity'];
    }

    public function updateSandboxQuantity(array $snapshot,\App\Models\User $actor):void
    {
        $context=$this->sandboxStockContext($snapshot,$actor);
        $config=$this->configuration();$token=$context['token'];$shopId=$snapshot['external_shop_id'];
        $limits=$this->readShop('/api/v2/product/get_item_limit',$token,$shopId,[])['response']['stock_limit']??null;
        if(!is_array($limits)||!is_int($limits['min_limit']??null)||!is_int($limits['max_limit']??null)
            ||$snapshot['desired_quantity']<$limits['min_limit']||$snapshot['desired_quantity']>$limits['max_limit'])throw new RuntimeException('Sandbox stock is outside the provider limits.');
        $path='/api/v2/product/update_stock';$time=now()->timestamp;
        $query=['partner_id'=>(int)$config['partner_id'],'timestamp'=>$time,'access_token'=>$token,'shop_id'=>(int)$shopId,
            'sign'=>hash_hmac('sha256',$config['partner_id'].$path.$time.$token.$shopId,$config['partner_key'])];
        $body=['item_id'=>(int)$snapshot['external_product_id'],'stock_list'=>[['model_id'=>(int)$snapshot['external_variant_id'],
            'seller_stock'=>[['location_id'=>$snapshot['platform_location_id'],'stock'=>$snapshot['desired_quantity']]]]]];
        try{$response=Http::acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->post($this->host($config).$path.'?'.http_build_query($query),$body);}
        catch(ConnectionException){throw new RuntimeException('Shopee sandbox write outcome is unknown.');}
        $payload=$response->json();$result=$payload['response']??null;
        if(!$response->successful()||!is_array($payload)||($payload['error']??null)!==''||!is_array($result)
            ||!is_array($result['failure_list']??null)||$result['failure_list']!==[]||!is_array($result['success_list']??null))throw new RuntimeException('Shopee sandbox write was not confirmed.');
        $matches=array_values(array_filter($result['success_list'],fn($row)=>is_array($row)&&(string)($row['model_id']??'')===$snapshot['external_variant_id']
            &&($row['location_id']??null)===$snapshot['platform_location_id']&&($row['stock']??null)===$snapshot['desired_quantity']));
        if(count($matches)!==1)throw new RuntimeException('Shopee sandbox response did not confirm the requested SKU and location.');
    }

    private function sandboxStockContext(array $snapshot,\App\Models\User $actor):array
    {
        $current=app(SandboxInventoryWriteGuard::class)->review((int)$snapshot['store_id'],(int)$snapshot['inventory_balance_id'],$actor);
        if($snapshot!==$current||$current['platform']!=='shopee')throw new RuntimeException('Sandbox stock review changed.');
        $token=app(ShopeeTokenManager::class)->accessToken($current['store_id']);$shop=$current['external_shop_id'];
        $this->shopInfo($token,$shop);
        $items=$this->itemDetails($token,$shop,[$current['external_product_id']]);
        $items=array_values(array_filter($items,fn($item)=>(string)($item['item_id']??'')===$current['external_product_id']));
        if(count($items)!==1)throw new RuntimeException('Sandbox item identity changed.');
        $item=$items[0];
        if(($item['has_model']??null)===true){
            $models=array_values(array_filter($this->models($token,$shop,$current['external_product_id']),fn($model)=>(string)$model['model_id']===$current['external_variant_id']));
            if(count($models)!==1)throw new RuntimeException('Sandbox model identity changed.');
            $stock=$models[0]['stock_info_v2']??null;
        }elseif(($item['has_model']??null)===false&&$current['external_variant_id']==='0')$stock=$item['stock_info_v2']??null;
        else throw new RuntimeException('Sandbox item/model structure changed.');
        $rows=$stock['seller_stock']??null;
        if(!is_array($rows)||!array_is_list($rows))throw new RuntimeException('Sandbox seller stock is unknown.');
        $locations=array_values(array_filter($rows,fn($row)=>($row['location_id']??null)===$current['platform_location_id']));
        if(count($locations)!==1||!is_int($locations[0]['stock']??null)||$locations[0]['stock']<0)throw new RuntimeException('Sandbox seller location is unknown.');
        return ['token'=>$token,'quantity'=>$locations[0]['stock']];
    }

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

    /** Called only after the confirmed parent is durably saved and its processing delay has elapsed. */
    public function initializeModels(#[\SensitiveParameter] string $accessToken, string $shopId, string $itemId, array $variations): array
    {
        if (config('marketplace.publish_operations_enabled')!==true) throw new RuntimeException('Marketplace publishing is disabled.');
        $config=$this->configuration();
        if ($accessToken==='' || !ctype_digit($shopId) || (int)$shopId<=0 || !ctype_digit($itemId) || (int)$itemId<=0) {
            throw new RuntimeException('Invalid Shopee listing identity.');
        }
        $path='/api/v2/product/init_tier_variation';
        $timestamp=now()->timestamp;
        $query=['partner_id'=>(int)$config['partner_id'],'timestamp'=>$timestamp,'access_token'=>$accessToken,'shop_id'=>(int)$shopId,
            'sign'=>hash_hmac('sha256',$config['partner_id'].$path.$timestamp.$accessToken.$shopId,$config['partner_key'])];
        try {
            $response=Http::acceptJson()->connectTimeout(10)->timeout(40)->withoutRedirecting()
                ->post($this->host($config).$path.'?'.http_build_query($query),array_merge($variations,['item_id'=>(int)$itemId]));
        } catch (ConnectionException) {
            throw new PublishingResultUncertain('Shopee variant creation result is unknown. Reconcile the saved parent before retrying.');
        }
        $body=$response->json();
        $models=$body['response']['model']??null;
        if (!$response->successful() || !is_array($body) || ($body['error']??null)!=='' || !is_array($models)
            || !array_is_list($models) || count($models)!==count($variations['model'])) {
            throw new PublishingResultUncertain('Shopee did not confirm every requested variant. Reconcile the saved parent.');
        }
        $expected=[];
        foreach ($variations['model'] as $model) $expected[$model['model_sku']]=$model['tier_index'];
        $ids=[];
        foreach ($models as $model) {
            $sku=$model['model_sku']??null;
            $id=$model['model_id']??null;
            if (!is_string($sku) || !isset($expected[$sku]) || isset($ids[$sku]) || !is_int($id) || $id<=0
                || in_array((string)$id,$ids,true) || ($model['tier_index']??null)!==$expected[$sku]) {
                throw new PublishingResultUncertain('Shopee variant identities do not match the reviewed listing.');
            }
            $ids[$sku]=(string)$id;
        }
        return $ids;
    }

    public function uploadImage(#[\SensitiveParameter] string $bytes): string
    {
        if ($bytes==='' || strlen($bytes)>2*1024*1024) throw new RuntimeException('Shopee product images must be no larger than 2 MB.');
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
        return $this->normalizeAttributeTree($rows[0]['attribute_tree']);
    }

    private function normalizeAttributeTree(array $nodes, int $depth = 0): array
    {
        if ($depth > 20) throw new RuntimeException('Attribute dependency tree is too deep.');
        foreach ($nodes as &$node) {
            if (!is_array($node)) throw new RuntimeException('Shopee attribute metadata was incomplete.');
            // Shopee omits options for attributes with no predefined values.
            if (!array_key_exists('attribute_value_list', $node)) $node['attribute_value_list'] = [];
            if (!is_array($node['attribute_value_list']) || !array_is_list($node['attribute_value_list'])) {
                throw new RuntimeException('Shopee attribute options were incomplete.');
            }
            if (is_array($node['attribute_info'] ?? null) && is_string($node['name'] ?? null)
                && !isset($node['attribute_info']['name'])) $node['attribute_info']['name'] = $node['name'];
            foreach ($node['attribute_value_list'] as &$option) {
                if (isset($option['child_attribute_list']) && is_array($option['child_attribute_list'])) {
                    $option['child_attribute_list'] = $this->normalizeAttributeTree($option['child_attribute_list'], $depth + 1);
                }
            }
            unset($option);
        }
        unset($node);
        return $nodes;
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
        $base = config('marketplace.hub.oauth_routing_enabled') === true
            ? 'https://marketplace.jwaistudio.online' : rtrim((string) config('app.url'), '/');
        $expected = $base.'/admin/marketplace/oauth/shopee/callback';
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
        if (!preg_match('/^[a-f0-9]{64}$/D', $state)) {
            throw new RuntimeException('Invalid Shopee authorization state.');
        }
        $host = $config['environment'] === 'production'
            ? 'https://open.shopee.com' : 'https://open.sandbox.test-stable.shopee.com';
        return $host.'/auth?'.http_build_query([
            'partner_id'=>(int)$config['partner_id'], 'auth_type'=>'seller',
            'redirect_uri'=>$config['callback_url'].'?'.http_build_query(['state'=>$state]),
            'response_type'=>'code',
        ]);
    }

    public function shopInfo(#[\SensitiveParameter] string $accessToken, string $shopId): array
    {
        $config = $this->configuration();
        return $this->shopInfoWithApplication($accessToken, $shopId, $config['environment'],
            (string) $config['partner_id'], $config['partner_key']);
    }

    public function shopInfoWithApplication(#[\SensitiveParameter] string $accessToken, string $shopId,
        string $environment, string $partnerId, #[\SensitiveParameter] string $partnerKey): array
    {
        if (!in_array($environment, ['production', 'sandbox'], true) || !ctype_digit($partnerId)
            || $partnerId === '' || $partnerKey === '') {
            throw new RuntimeException('Shopee application identity could not be verified.');
        }
        $config = ['environment' => $environment, 'partner_id' => $partnerId, 'partner_key' => $partnerKey];
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

        foreach ($models as &$model) {
            $model['variant_options'] = [];
            foreach (($payload['response']['tier_variation'] ?? []) as $tier => $definition) {
                $name=$definition['name']??null;
                $index=$model['tier_index'][$tier]??null;
                $value=is_int($index)?($definition['option_list'][$index]['option']??null):null;
                if (!is_string($name)||trim($name)===''||!is_string($value)||trim($value)===''||isset($model['variant_options'][$name])) {
                    throw new RuntimeException('Shopee variant option definitions were incomplete.');
                }
                $model['variant_options'][$name]=$value;
            }

            foreach (($model['tier_index'] ?? []) as $tier => $index) {
                $image = data_get($payload, "response.tier_variation.$tier.option_list.$index.image.image_url");
                if (is_string($image) && $image !== '') {
                    $model['variant_image_url'] = $image;
                    break;
                }
            }
        }
        unset($model);
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
        if ($this->authorizationFailed($response->status(), $payload)) {
            throw new StoreInventoryAuthorizationExpired('Shopee authorization expired. Reconnect this store.');
        }
        if (!$response->successful() || !is_array($payload) || !empty($payload['error'])) {
            throw new RuntimeException('Shopee read request failed. Check store authorization and retry.');
        }

        return $payload;
    }

    private function authorizationFailed(int $status, mixed $payload): bool
    {
        $error = is_array($payload) ? strtolower((string) ($payload['error'] ?? '')) : '';

        return in_array($status, [401, 403], true)
            || str_contains($error, 'token') || str_contains($error, 'auth') || str_contains($error, 'expired');
    }

    public function exchange(#[\SensitiveParameter] string $code, string $shopId): array
    {
        return $this->exchangeShopCode($code, $shopId);
    }

    public function refresh(#[\SensitiveParameter] string $refreshToken, string $shopId): array
    {
        return $this->refreshShopCredential($refreshToken, $shopId);
    }

    public function exchangeShopCode(#[\SensitiveParameter] string $code, string $shopId): array
    {
        return $this->tokenRequest('/api/v2/auth/token/get', 'code', $code, 'shop_id', $shopId);
    }

    public function exchangeMainAccountCode(#[\SensitiveParameter] string $code, string $mainAccountId): array
    {
        $tokens=$this->tokenRequest('/api/v2/auth/token/get', 'code', $code, 'main_account_id', $mainAccountId, true);
        $shops=$this->positiveUniqueIds($tokens['shop_id_list']??null);
        if (!$shops) throw new RuntimeException('Shopee main account did not return valid stores. Re-authorize the Main Account.');
        $tokens['shop_id_list']=$shops;
        $merchantIds=$tokens['merchant_id_list']??[];
        $tokens['merchant_id_list']=$this->positiveUniqueIds($merchantIds, true);
        if (!is_array($merchantIds) || count($tokens['merchant_id_list'])!==count($merchantIds)) {
            throw new RuntimeException('Shopee main account did not return valid stores. Re-authorize the Main Account.');
        }
        return $tokens;
    }

    public function refreshShopCredential(#[\SensitiveParameter] string $refreshToken, string $shopId): array
    {
        return $this->tokenRequest('/api/v2/auth/access_token/get', 'refresh_token', $refreshToken, 'shop_id', $shopId);
    }

    private function positiveUniqueIds(mixed $ids, bool $allowEmpty=false): array
    {
        if (!is_array($ids) || (!$allowEmpty && !$ids)) return [];
        $normalized=[];
        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || !ctype_digit((string)$id) || (int)$id<=0) return [];
            $normalized[]=(string)$id;
        }
        if (count(array_unique($normalized))!==count($normalized)) return [];
        return $normalized;
    }

    private function tokenRequest(string $path, string $credentialName, #[\SensitiveParameter] string $credential,
        string $identityName, string $identity, bool $preserveLists=false): array
    {
        $config = $this->configuration();
        if ($credential === '' || !in_array($identityName,['shop_id','main_account_id'],true)
            || !ctype_digit($identity) || (int) $identity <= 0) {
            throw new RuntimeException('Invalid Shopee authorization callback.');
        }
        try {
            // Never retry one-use authorization codes automatically or follow redirects with credentials.
            $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()
                ->post($this->host($config).$path.'?'.http_build_query($this->signedQuery($config, $path)), [
                    $credentialName => $credential, $identityName => (int) $identity, 'partner_id' => (int) $config['partner_id'],
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

        $result=['access_token' => $payload['access_token'], 'refresh_token' => $payload['refresh_token'],
            'expire_in' => (int) $payload['expire_in']];
        if ($preserveLists) {
            $result['shop_id_list']=$payload['shop_id_list']??null;
            $result['merchant_id_list']=$payload['merchant_id_list']??[];
        }
        return $result;
    }
}
