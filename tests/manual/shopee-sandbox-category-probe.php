<?php

// Read-only protocol probe. Run from the application root; no source is installed remotely.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\Marketplace\ShopeeTokenManager;

try {
    if (config('marketplace.shopee.environment') !== 'sandbox'
        || !config('marketplace.read_operations_enabled')) throw new RuntimeException('Sandbox read gate required');
    $store = DB::table('marketplace_stores')->where('id', 1)->first();
    if (!$store || $store->platform !== 'shopee' || $store->environment !== 'sandbox'
        || $store->status !== 'connected' || (string) $store->external_shop_id !== '227482706') {
        throw new RuntimeException('Approved Sandbox store identity required');
    }
    $token = app(ShopeeTokenManager::class)->accessToken(1);
    $config = config('marketplace.shopee');
    $path = '/api/v2/product/get_category';
    $timestamp = now()->timestamp;
    $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()->get(
        'https://openplatform.sandbox.test-stable.shopee.sg'.$path, [
            'partner_id'=>(int) $config['partner_id'], 'timestamp'=>$timestamp, 'shop_id'=>$store->external_shop_id,
            'access_token'=>$token, 'language'=>'en',
            'sign'=>hash_hmac('sha256', $config['partner_id'].$path.$timestamp.$token.$store->external_shop_id, $config['partner_key']),
        ]);
    $body = $response->json();
    $rows = $body['response']['category_list'] ?? null;
    if (!$response->successful() || !is_array($body) || !empty($body['error']) || !is_array($rows) || !array_is_list($rows)) {
        echo json_encode(['success'=>false, 'httpStatus'=>$response->status(), 'validCategoryList'=>false]).PHP_EOL;
        exit(1);
    }
    $invalid = 0;
    $leaves = 0;
    foreach ($rows as $row) {
        if (!is_array($row) || !is_int($row['category_id'] ?? null) || $row['category_id'] <= 0
            || !is_int($row['parent_category_id'] ?? null) || $row['parent_category_id'] < 0
            || !is_string($row['original_category_name'] ?? null) || !is_string($row['display_category_name'] ?? null)
            || !is_bool($row['has_children'] ?? null)) $invalid++;
        if (($row['has_children'] ?? null) === false) $leaves++;
    }
    echo json_encode(['success'=>$invalid === 0, 'environment'=>'sandbox', 'httpStatus'=>$response->status(),
        'categoryCount'=>count($rows), 'leafCount'=>$leaves, 'invalidRows'=>$invalid,
        'observedAt'=>now()->toIso8601String(), 'validation'=>'protocol probe, not deployed application endpoint']).PHP_EOL;
    $observation = DB::table('marketplace_listing_variants')->where('store_id', 1)->where('external_product_id', '846055463')->value('observed_data');
    $category = json_decode($observation ?? '{}', true)['externalCategoryId'] ?? null;
    if (!is_int($category) || $category <= 0) throw new RuntimeException('Saved source category required');
    $path = '/api/v2/product/get_attribute_tree';
    $timestamp = now()->timestamp;
    $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()->get(
        'https://openplatform.sandbox.test-stable.shopee.sg'.$path, [
            'partner_id'=>(int) $config['partner_id'], 'timestamp'=>$timestamp, 'shop_id'=>$store->external_shop_id,
            'access_token'=>$token, 'language'=>'en', 'category_id_list'=>(string) $category,
            'sign'=>hash_hmac('sha256', $config['partner_id'].$path.$timestamp.$token.$store->external_shop_id, $config['partner_key']),
        ]);
    $body = $response->json();
    $trees = $body['response']['list'] ?? null;
    $ok = $response->successful() && empty($body['error']) && is_array($trees)
        && count($trees) === 1 && ($trees[0]['category_id'] ?? null) === $category && is_array($trees[0]['attribute_tree'] ?? null);
    echo json_encode(['attributeSuccess'=>$ok, 'httpStatus'=>$response->status(), 'categoryId'=>$category,
        'parameter'=>'category_id_list', 'attributeCount'=>$ok ? count($trees[0]['attribute_tree']) : null,
        'attributeKeys'=>$ok && !empty($trees[0]['attribute_tree']) ? array_keys($trees[0]['attribute_tree'][0]) : []]).PHP_EOL;
    if ($ok) {
        echo json_encode(['attributeValidationMetadata'=>array_map(fn ($node)=>[
            'id'=>$node['attribute_id'] ?? null,'mandatory'=>$node['mandatory'] ?? null,
            'info'=>$node['attribute_info'] ?? null,'searchable'=>$node['attribute_info']['support_search_value'] ?? null,
            'valueCount'=>count($node['attribute_value_list'] ?? []),
        ],$trees[0]['attribute_tree'])]).PHP_EOL;
    }
    $path = '/api/v2/product/get_brand_list';
    $timestamp = now()->timestamp;
    $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()->get(
        'https://openplatform.sandbox.test-stable.shopee.sg'.$path, [
            'partner_id'=>(int) $config['partner_id'], 'timestamp'=>$timestamp, 'shop_id'=>$store->external_shop_id,
            'access_token'=>$token, 'language'=>'en', 'category_id'=>$category, 'offset'=>0, 'page_size'=>100, 'status'=>1,
            'sign'=>hash_hmac('sha256', $config['partner_id'].$path.$timestamp.$token.$store->external_shop_id, $config['partner_key']),
        ]);
    $body = $response->json();
    $page = $body['response'] ?? null;
    $brandOk = $response->successful() && empty($body['error']) && is_array($page)
        && is_array($page['brand_list'] ?? null) && is_bool($page['has_next_page'] ?? null)
        && is_bool($page['is_mandatory'] ?? null) && is_string($page['input_type'] ?? null);
    echo json_encode(['brandSuccess'=>$brandOk, 'httpStatus'=>$response->status(), 'categoryId'=>$category,
        'brandCount'=>$brandOk ? count($page['brand_list']) : null, 'hasNextPage'=>$page['has_next_page'] ?? null,
        'nextOffset'=>$page['next_offset'] ?? null, 'mandatory'=>$page['is_mandatory'] ?? null,
        'inputType'=>$page['input_type'] ?? null]).PHP_EOL;
    $path = '/api/v2/logistics/get_channel_list';
    $timestamp = now()->timestamp;
    $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()->get(
        'https://openplatform.sandbox.test-stable.shopee.sg'.$path, [
            'partner_id'=>(int) $config['partner_id'], 'timestamp'=>$timestamp, 'shop_id'=>$store->external_shop_id,
            'access_token'=>$token,
            'sign'=>hash_hmac('sha256', $config['partner_id'].$path.$timestamp.$token.$store->external_shop_id, $config['partner_key']),
        ]);
    $body = $response->json();
    $channels = $body['response']['logistics_channel_list'] ?? null;
    $logisticsOk = $response->successful() && empty($body['error']) && is_array($channels) && array_is_list($channels);
    $invalidChannels = 0;
    foreach ($channels ?? [] as $channel) {
        if (!is_array($channel) || !is_int($channel['logistics_channel_id'] ?? null) || $channel['logistics_channel_id'] <= 0
            || !is_string($channel['logistics_channel_name'] ?? null) || !is_bool($channel['enabled'] ?? null)
            || !is_string($channel['fee_type'] ?? null)) $invalidChannels++;
    }
    echo json_encode(['logisticsSuccess'=>$logisticsOk && !$invalidChannels, 'httpStatus'=>$response->status(),
        'channelCount'=>$logisticsOk ? count($channels) : null, 'invalidChannels'=>$invalidChannels,
        'enabledCount'=>$logisticsOk ? count(array_filter($channels, fn ($row)=>($row['enabled'] ?? null) === true)) : null]).PHP_EOL;
    if ($logisticsOk) echo json_encode(['logisticsConstraints'=>array_map(fn($channel)=>array_intersect_key($channel,array_flip([
        'logistics_channel_id','enabled','fee_type','weight_limit','item_max_dimension','volume_limit','size_list',
        'force_enable','mask_channel_id','compulsory_channel','channel_relation_rules','block_seller_cover_shipping_fee',
    ])),$channels)]).PHP_EOL;
    exit($invalid === 0 && $ok && $brandOk && $logisticsOk && !$invalidChannels ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "Sandbox category probe failed; private details suppressed.\n");
    exit(1);
}
