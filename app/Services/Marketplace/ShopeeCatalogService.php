<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopeeCatalogService
{
    public function __construct(private ShopeeClient $client, private ShopeeTokenManager $tokens) {}

    public function pullPage(int $storeId, int $offset, string $status = 'NORMAL'): array
    {
        $token = $this->tokens->accessToken($storeId);
        $store = DB::table('marketplace_stores')->find($storeId);
        $page = $this->client->catalogPage($token, $store->external_shop_id, $offset, $status);
        $ids = array_column($page['item'], 'item_id');
        if (count($ids) !== count($page['item'])) {
            throw new RuntimeException('Shopee catalog contains missing product identities.');
        }
        $items = $ids ? $this->client->itemDetails($token, $store->external_shop_id, $ids) : [];
        $rows = [];
        foreach ($items as $item) {
            if (!is_bool($item['has_model'] ?? null)) {
                throw new RuntimeException('Shopee product variant structure is missing.');
            }
            $models = $item['has_model'] ? $this->client->models($token, $store->external_shop_id, (string) $item['item_id']) : [$item];
            foreach ($models as $model) {
                $rows[] = ['external_product_id'=>(string) $item['item_id'],
                    'external_variant_id'=>$item['has_model'] ? (string) $model['model_id'] : '0',
                    'observed_data'=>json_encode([
                        'productTitle'=>$item['item_name'] ?? null, 'productImage'=>data_get($item, 'image.image_url_list.0'),
                        'title'=>$model['model_name'] ?? $item['item_name'] ?? null,
                        'sku'=>$model['model_sku'] ?? $item['item_sku'] ?? null,
                        'price'=>data_get($model, 'price_info.0.current_price'),
                        'currency'=>data_get($model, 'price_info.0.currency'),
                        'stock'=>data_get($model, 'stock_info_v2.summary_info.total_available_stock'),
                        'status'=>$item['item_status'] ?? $status,
                    ], JSON_THROW_ON_ERROR)];
            }
        }
        $next = $page['next_offset'] ?? ($offset + count($ids));
        if ($page['has_next_page'] && (!is_numeric($next) || (int) $next <= $offset)) {
            throw new RuntimeException('Shopee pagination did not advance.');
        }
        DB::transaction(function () use ($storeId, $store, $rows) {
            $current = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (!$current || $current->status !== 'connected' || $current->external_shop_id !== $store->external_shop_id) {
                throw new RuntimeException('Store disconnected during catalog fetch.');
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

        return ['items'=>count($ids), 'variants'=>count($rows), 'has_more'=>$page['has_next_page'], 'next_offset'=>(int) $next];
    }
}
