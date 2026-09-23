<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopeeCatalogService
{
    public function __construct(private ShopeeClient $client, private ShopeeTokenManager $tokens,
        private ShopeeApplicationResolver $applications) {}

    public function pullPage(int $storeId, int $offset, string $status = 'NORMAL'): array
    {
        $token = $this->tokens->accessToken($storeId);
        $store = DB::table('marketplace_stores')->find($storeId);
        $client = $this->applications->clientForStore($storeId, $this->client);
        $page = $client->catalogPage($token, $store->external_shop_id, $offset, $status);
        $ids = array_column($page['item'], 'item_id');
        if (count($ids) !== count($page['item'])) {
            throw new RuntimeException('Shopee catalog contains missing product identities.');
        }
        $items = $ids ? $client->itemDetails($token, $store->external_shop_id, $ids) : [];
        $next = $page['next_offset'] ?? ($offset + count($ids));
        if ($page['has_next_page'] && (!is_numeric($next) || (int) $next <= $offset)) {
            throw new RuntimeException('Shopee pagination did not advance.');
        }
        $count=$this->capture($storeId,$store,$token,$items,$status);
        return ['items'=>count($ids),'variants'=>$count,'has_more'=>$page['has_next_page'],'next_offset'=>(int)$next];
    }

    public function pullItem(int $storeId,string $itemId): array
    {
        if (!preg_match('/^[1-9][0-9]*$/D',$itemId)) throw new RuntimeException('Invalid Shopee product identity.');
        $token=$this->tokens->accessToken($storeId);
        $store=DB::table('marketplace_stores')->find($storeId);
        $items=$this->applications->clientForStore($storeId,$this->client)->itemDetails($token,$store->external_shop_id,[$itemId]);
        if (count($items)!==1 || (string)($items[0]['item_id']??'')!==$itemId) throw new RuntimeException('Shopee published product was not returned.');
        return ['items'=>1,'variants'=>$this->capture($storeId,$store,$token,$items,null)];
    }

    private function capture(int $storeId,object $store,#[\SensitiveParameter] string $token,array $items,?string $status): int
    {
        $client=$this->applications->clientForStore($storeId,$this->client);
        $rows = [];
        foreach ($items as $item) {
            if (!is_bool($item['has_model'] ?? null)) {
                throw new RuntimeException('Shopee product variant structure is missing.');
            }
            $models = $item['has_model'] ? $client->models($token, $store->external_shop_id, (string) $item['item_id']) : [$item];
            foreach ($models as $model) {
                $rows[] = ['external_product_id'=>(string) $item['item_id'],
                    'external_variant_id'=>$item['has_model'] ? (string) $model['model_id'] : '0',
                    'observed_data'=>json_encode([
                        'productTitle'=>$item['item_name'] ?? null, 'productImage'=>data_get($item, 'image.image_url_list.0'),
                        'productImages'=>data_get($item, 'image.image_url_list', []),
                        'variantImage'=>$model['variant_image_url'] ?? null,
                        'variantOptions'=>$model['variant_options'] ?? [],
                        'weightGrams'=>is_numeric($model['weight'] ?? $item['weight'] ?? null)
                            ? (int) round((float) ($model['weight'] ?? $item['weight']) * 1000) : null,
                        'packageLengthCm'=>data_get($model, 'dimension.package_length', data_get($item, 'dimension.package_length')),
                        'packageWidthCm'=>data_get($model, 'dimension.package_width', data_get($item, 'dimension.package_width')),
                        'packageHeightCm'=>data_get($model, 'dimension.package_height', data_get($item, 'dimension.package_height')),
                        'descriptionType'=>$item['description_type'] ?? null,
                        'description'=>$item['description'] ?? null,
                        'descriptionFields'=>data_get($item, 'description_info.extended_description.field_list'),
                        'brand'=>data_get($item, 'brand.original_brand_name'),
                        'externalCategoryId'=>$item['category_id'] ?? null,
                        'title'=>$model['model_name'] ?? $item['item_name'] ?? null,
                        'sku'=>$model['model_sku'] ?? $item['item_sku'] ?? null,
                        'price'=>data_get($model, 'price_info.0.current_price'),
                        'currency'=>data_get($model, 'price_info.0.currency'),
                        'stock'=>data_get($model, 'stock_info_v2.summary_info.total_available_stock'),
                        // Observed provider snapshot only; never authorization or freshness proof for a stock write.
                        'stockInfo'=>$model['stock_info_v2'] ?? null,
                        'status'=>$item['item_status'] ?? $status,
                    ], JSON_THROW_ON_ERROR)];
            }
        }
        DB::transaction(function () use ($storeId, $store, $rows) {
            $current = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (!$current || $current->status !== 'connected' || $current->external_shop_id !== $store->external_shop_id
                || $current->environment !== $store->environment) {
                throw new RuntimeException('Store connection changed during catalog fetch.');
            }
            foreach ($rows as $row) {
                $identity = ['store_id'=>$storeId, 'external_product_id'=>$row['external_product_id'], 'external_variant_id'=>$row['external_variant_id']];
                $existing = DB::table('marketplace_listing_variants')->where($identity)->first();
                DB::table('marketplace_listing_variants')->updateOrInsert($identity, [
                    'observed_data'=>$row['observed_data'], 'fetched_at'=>now(), 'updated_at'=>now(),
                    'created_at'=>$existing->created_at ?? now(),
                ]);
            }
        });

        app(PublishedCatalogMapping::class)->reconcile($storeId,$rows);
        return count($rows);
    }
}
