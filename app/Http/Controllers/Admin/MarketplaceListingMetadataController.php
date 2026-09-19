<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\ShopeeClient;
use App\Services\Marketplace\ShopeeTokenManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketplaceListingMetadataController extends Controller
{
    private function cached(object $store, string $resource, array $parameters, callable $fetch): array
    {
        return app(\App\Services\Marketplace\MarketplaceListingMetadataCache::class)
            ->read($store,$resource,$parameters,$fetch,request()->boolean('refresh'));
    }
    public function attributeValues(Request $request, int $store, ShopeeClient $client, ShopeeTokenManager $tokens)
    {
        $data=$request->validate(['attributeId'=>['required','integer','min:1','max:2147483647'],
            'q'=>['sometimes','nullable','string','max:255'],'cursor'=>['sometimes','integer','min:0','max:9007199254740991']]);
        $connection=DB::table('marketplace_stores')->find($store);
        abort_unless($connection,404);
        abort_unless($connection->status==='connected' && $connection->platform==='shopee'
            && $connection->environment===config('marketplace.shopee.environment'),409,
            'Connect this store to the configured Shopee environment first.');
        try {
            $page=$client->searchAttributeValues($tokens->accessToken($store),(string)$connection->external_shop_id,
                (int)$data['attributeId'],$data['q'] ?? '',(int)($data['cursor'] ?? 0));
        } catch (RuntimeException) {
            return response()->json(['message'=>'Unable to load Shopee attribute values. Check store authorization and retry.'],502)
                ->header('Cache-Control','no-store');
        }
        return response()->json(['storeId'=>$store,'attributeId'=>(int)$data['attributeId'],'data'=>$page])
            ->header('Cache-Control','no-store');
    }

    public function logistics(int $store, ShopeeClient $client, ShopeeTokenManager $tokens)
    {
        $connection = DB::table('marketplace_stores')->find($store);
        abort_unless($connection, 404);
        abort_unless($connection->status === 'connected' && $connection->platform === 'shopee'
            && $connection->environment === config('marketplace.shopee.environment'),
            409, 'Connect this store to the configured Shopee environment first.');
        try {
            $accessToken = $tokens->accessToken($store);
            $channels = $this->cached($connection, 'logistics', [], fn()=>$client->logisticsChannels($accessToken, (string) $connection->external_shop_id));
        } catch (RuntimeException) {
            return response()->json(['message'=>'Unable to load Shopee logistics channels. Check store authorization and retry.'], 502)
                ->header('Cache-Control', 'no-store');
        }
        $fields = array_flip(['logistics_channel_id', 'logistics_channel_name', 'enabled', 'fee_type',
            'size_list', 'weight_limit', 'item_max_dimension', 'volume_limit', 'force_enable',
            'mask_channel_id', 'logistics_description', 'compulsory_channel', 'block_seller_cover_shipping_fee']);
        $channels=array_map(function($channel) use ($fields) {
            $safe=array_intersect_key($channel,$fields);
            if (is_array($channel['channel_relation_rules'] ?? null)) {
                $safe['channel_relation_rules']=array_intersect_key($channel['channel_relation_rules'],array_flip([
                    'related_enabled_channels','related_disabled_channels','related_dependent_block_channels',
                ]));
            }
            return $safe;
        },$channels);
        return response()->json(['storeId'=>$store,
            'data'=>$channels])
            ->header('Cache-Control', 'no-store');
    }

    public function brands(Request $request, int $store, ShopeeClient $client, ShopeeTokenManager $tokens)
    {
        $data = $request->validate(['categoryId'=>['required', 'integer', 'min:1', 'max:2147483647'],
            'offset'=>['sometimes', 'integer', 'min:0', 'max:2147483647']]);
        $connection = DB::table('marketplace_stores')->find($store);
        abort_unless($connection, 404);
        if ($connection->platform==='lazada') {
            abort_unless($connection->status==='connected' && $connection->environment==='live' && $connection->region==='MY',409,'Connect the Lazada Malaysia store first.');
            try {
                $token=app(\App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store);
                $page=$this->cached($connection, 'brands', [(int)($data['offset'] ?? 0)],
                    fn()=>app(\App\Services\Marketplace\LazadaBrands::class)->page($token,(int)($data['offset']??0),$store));
                return response()->json(['storeId'=>$store,'categoryId'=>(int)$data['categoryId'],'data'=>$page])->header('Cache-Control','no-store');
            } catch (RuntimeException) {
                return response()->json(['message'=>'Unable to load Lazada brands. Retry the brand list.'],502)->header('Cache-Control','no-store');
            }
        }
        abort_unless($connection->status === 'connected' && $connection->platform === 'shopee'
            && $connection->environment === config('marketplace.shopee.environment'),
            409, 'Connect this store to the configured Shopee environment first.');
        try {
            $accessToken = $tokens->accessToken($store);
            $page = $this->cached($connection, 'brands', [(int)$data['categoryId'],(int)($data['offset']??0)], fn()=>$client->brandPage($accessToken, (string) $connection->external_shop_id,
                (int) $data['categoryId'], (int) ($data['offset'] ?? 0)));
        } catch (RuntimeException) {
            return response()->json(['message'=>'Unable to load Shopee brands. Check category and store authorization, then retry.'], 502)
                ->header('Cache-Control', 'no-store');
        }
        $page = array_intersect_key($page, array_flip(['brand_list', 'has_next_page', 'next_offset', 'is_mandatory', 'input_type']));
        $page['brand_list'] = array_map(fn ($brand)=>array_intersect_key($brand,
            array_flip(['brand_id', 'original_brand_name', 'display_brand_name'])), $page['brand_list']);
        return response()->json(['storeId'=>$store, 'categoryId'=>(int) $data['categoryId'], 'data'=>$page])
            ->header('Cache-Control', 'no-store');
    }

    public function attributes(Request $request, int $store, ShopeeClient $client, ShopeeTokenManager $tokens)
    {
        $data = $request->validate(['categoryId'=>['required', 'integer', 'min:1', 'max:2147483647']]);
        $connection = DB::table('marketplace_stores')->find($store);
        abort_unless($connection, 404);
        if ($connection->platform==='lazada') {
            abort_unless($connection->status==='connected' && $connection->environment==='live' && $connection->region==='MY',409,'Connect the Lazada Malaysia store first.');
            try {
                $lazada=app(\App\Services\Marketplace\LazadaClient::class);
                $token=app(\App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store);
                $categories=\App\Services\Marketplace\LazadaListingCategories::flatten($this->cached($connection, 'categories', [],
                    fn()=>$lazada->read('/category/tree/get',$token,[],$store)));
                if (!collect($categories)->contains(fn($category)=>$category['category_id']===(int)$data['categoryId'] && !$category['has_children'])) {
                    return response()->json(['message'=>'Select an available leaf category.'],422)->header('Cache-Control','no-store');
                }
                $attributes=$this->cached($connection, 'attributes', [(int)$data['categoryId']],
                    fn()=>$lazada->read('/category/attributes/get',$token,['primary_category_id'=>(string)$data['categoryId']],$store));
                if (!array_is_list($attributes)) throw new RuntimeException('Invalid attributes.');
                foreach ($attributes as $attribute) {
                    if (!is_array($attribute)||!is_string($attribute['name']??null)||trim($attribute['name'])===''
                        ||!is_string($attribute['input_type']??null)||!in_array($attribute['is_mandatory']??null,[0,1,false,true],true)) {
                        throw new RuntimeException('Invalid attribute metadata.');
                    }
                }
                return response()->json(['storeId'=>$store,'platform'=>'lazada','categoryId'=>(int)$data['categoryId'],'data'=>$attributes])->header('Cache-Control','no-store');
            } catch (RuntimeException) {
                return response()->json(['message'=>'Unable to load Lazada attributes. Check store authorization and retry.'],502)->header('Cache-Control','no-store');
            }
        }
        abort_unless($connection->status === 'connected' && $connection->platform === 'shopee'
            && $connection->environment === config('marketplace.shopee.environment'),
            409, 'Connect this store to the configured Shopee environment first.');
        try {
            $accessToken = $tokens->accessToken($store);
            \App\Services\Marketplace\ShopeeListingCategory::requireLeaf(
                $this->cached($connection, 'categories', [], fn()=>$client->categoryTree($accessToken, (string) $connection->external_shop_id)),
                (int) $data['categoryId']);
            $tree = $this->cached($connection, 'attributes', [(int)$data['categoryId']], fn()=>$client->attributeTree($accessToken, (string) $connection->external_shop_id, (int) $data['categoryId']));
        } catch (\DomainException $exception) {
            return response()->json(['message'=>$exception->getMessage()],422)->header('Cache-Control','no-store');
        } catch (RuntimeException) {
            return response()->json(['message'=>'Unable to load Shopee attributes. Check category and store authorization, then retry.'], 502)
                ->header('Cache-Control', 'no-store');
        }
        return response()->json(['storeId'=>$store, 'categoryId'=>(int) $data['categoryId'], 'data'=>$tree])
            ->header('Cache-Control', 'no-store');
    }

    public function categories(int $store, ShopeeClient $client, ShopeeTokenManager $tokens)
    {
        $connection = DB::table('marketplace_stores')->find($store);
        abort_unless($connection, 404);
        if ($connection->platform==='lazada') {
            abort_unless($connection->status==='connected' && $connection->environment==='live' && $connection->region==='MY',409,'Connect the Lazada Malaysia store first.');
            try {
                $token=app(\App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store);
                $tree=$this->cached($connection, 'categories', [],
                    fn()=>app(\App\Services\Marketplace\LazadaClient::class)->read('/category/tree/get',$token,[],$store));
                $rows=\App\Services\Marketplace\LazadaListingCategories::flatten($tree);
                return response()->json(['storeId'=>$store,'data'=>$rows])->header('Cache-Control','no-store');
            } catch (RuntimeException) {
                return response()->json(['message'=>'Unable to load Lazada categories. Check store authorization and retry.'],502)->header('Cache-Control','no-store');
            }
        }
        abort_unless($connection->status === 'connected' && $connection->platform === 'shopee'
            && $connection->environment === config('marketplace.shopee.environment'),
            409, 'Connect this store to the configured Shopee environment first.');
        try {
            $accessToken = $tokens->accessToken($store);
            $rows = $this->cached($connection, 'categories', [], fn()=>$client->categoryTree($accessToken, (string) $connection->external_shop_id));
        } catch (RuntimeException) {
            return response()->json(['message'=>'Unable to load Shopee categories. Check store authorization and retry.'], 502)
                ->header('Cache-Control', 'no-store');
        }
        $fields = array_flip(['category_id', 'parent_category_id', 'original_category_name', 'display_category_name', 'has_children']);
        return response()->json(['storeId'=>$store, 'data'=>array_map(fn ($row)=>array_intersect_key($row, $fields), $rows)])
            ->header('Cache-Control', 'no-store');
    }
}
