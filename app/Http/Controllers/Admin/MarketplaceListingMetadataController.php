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
            $channels = $client->logisticsChannels($accessToken, (string) $connection->external_shop_id);
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
        abort_unless($connection->status === 'connected' && $connection->platform === 'shopee'
            && $connection->environment === config('marketplace.shopee.environment'),
            409, 'Connect this store to the configured Shopee environment first.');
        try {
            $accessToken = $tokens->accessToken($store);
            $page = $client->brandPage($accessToken, (string) $connection->external_shop_id,
                (int) $data['categoryId'], (int) ($data['offset'] ?? 0));
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
        abort_unless($connection->status === 'connected' && $connection->platform === 'shopee'
            && $connection->environment === config('marketplace.shopee.environment'),
            409, 'Connect this store to the configured Shopee environment first.');
        try {
            $accessToken = $tokens->accessToken($store);
            \App\Services\Marketplace\ShopeeListingCategory::requireLeaf(
                $client->categoryTree($accessToken, (string) $connection->external_shop_id),
                (int) $data['categoryId']);
            $tree = $client->attributeTree($accessToken, (string) $connection->external_shop_id, (int) $data['categoryId']);
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
        abort_unless($connection->status === 'connected' && $connection->platform === 'shopee'
            && $connection->environment === config('marketplace.shopee.environment'),
            409, 'Connect this store to the configured Shopee environment first.');
        try {
            $accessToken = $tokens->accessToken($store);
            $rows = $client->categoryTree($accessToken, (string) $connection->external_shop_id);
        } catch (RuntimeException) {
            return response()->json(['message'=>'Unable to load Shopee categories. Check store authorization and retry.'], 502)
                ->header('Cache-Control', 'no-store');
        }
        $fields = array_flip(['category_id', 'parent_category_id', 'original_category_name', 'display_category_name', 'has_children']);
        return response()->json(['storeId'=>$store, 'data'=>array_map(fn ($row)=>array_intersect_key($row, $fields), $rows)])
            ->header('Cache-Control', 'no-store');
    }
}
