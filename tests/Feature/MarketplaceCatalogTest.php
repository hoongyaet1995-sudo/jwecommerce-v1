<?php

namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\ShopeeCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.operations_enabled'=>true]);
    }

    public function test_grouped_catalog_paginates_products_and_keeps_all_variants_together(): void
    {
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Catalog',
            'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected', 'created_at'=>now(), 'updated_at'=>now()]);
        foreach (range(1, 3) as $product) foreach (range(1, 4) as $variant) {
            DB::table('marketplace_listing_variants')->insert(['store_id'=>$store, 'external_product_id'=>(string) $product,
                'external_variant_id'=>(string) $variant, 'observed_data'=>json_encode(['productTitle'=>'Product '.$product, 'sku'=>'SKU-'.$product.'-'.$variant]),
                'fetched_at'=>now(), 'created_at'=>now(), 'updated_at'=>now()]);
        }
        $this->getJson('/admin/api/marketplace/catalog/products')->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->getJson('/admin/api/marketplace/catalog/products?per_page=2&page=1')->assertOk()
            ->assertJsonPath('total', 3)->assertJsonCount(2, 'data')->assertJsonCount(4, 'data.0.variants')->assertJsonCount(4, 'data.1.variants');
        $this->getJson('/admin/api/marketplace/catalog/products?per_page=2&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.external_product_id', '3');
        $this->getJson('/admin/api/marketplace/catalog/products?q=SKU-2-3')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.external_product_id', '2')->assertJsonCount(4, 'data.0.variants');
        $this->getJson('/admin/api/marketplace/catalog/products?platform=lazada')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_catalog_page_is_idempotent_and_does_not_create_master_products(): void
    {
        config(['marketplace.operations_enabled'=>false, 'marketplace.read_operations_enabled'=>true]);
        config(['marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'secret',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        Http::preventStrayRequests();
        $id = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected',
            'created_at'=>now(), 'updated_at'=>now()]);
        MarketplaceStoreToken::create(['store_id'=>$id, 'application_id'=>'123', 'access_token'=>'access', 'refresh_token'=>'refresh', 'access_expires_at'=>now()->addHours(4)]);
        $stockInfo = [
            'summary_info'=>['total_reserved_stock'=>0, 'total_available_stock'=>97],
            'seller_stock'=>[['location_id'=>'MYZ', 'stock'=>97, 'if_saleable'=>true]],
            'shopee_stock'=>[['location_id'=>'', 'stock'=>0]],
            'advance_stock'=>['sellable_advance_stock'=>0, 'in_transit_advance_stock'=>0],
        ];
        Http::fake([
            '*/get_item_list*'=>Http::response(['response'=>['item'=>[['item_id'=>100]], 'has_next_page'=>false]]),
            '*/get_item_base_info*'=>Http::response(['response'=>['item_list'=>[['item_id'=>100, 'has_model'=>true, 'item_name'=>'Headphones',
                'description_type'=>'normal', 'description'=>'Source description',
                'brand'=>['brand_id'=>123, 'original_brand_name'=>'Source Brand'], 'category_id'=>555,
                'image'=>['image_url_list'=>['https://down-my.img.susercontent.com/file/front', 'https://down-my.img.susercontent.com/file/back']]]]]]),
            '*/get_model_list*'=>function () use (&$stockInfo) {
                return Http::response(['response'=>[
                    'tier_variation'=>[['name'=>'Color','option_list'=>[['option'=>'Red','image'=>['image_url'=>'https://cf.shopee.com.my/file/red']],['option'=>'Blue','image'=>['image_url'=>'https://cf.shopee.com.my/file/blue']]]]],
                    'model'=>[['model_id'=>200, 'model_sku'=>'BLUE', 'model_name'=>'Blue', 'tier_index'=>[1], 'weight'=>'1', 'dimension'=>['package_length'=>1,'package_width'=>2,'package_height'=>3], 'stock_info_v2'=>$stockInfo]]]]);
            },
        ]);
        $before = DB::table('products')->count();
        $service = app(ShopeeCatalogService::class);
        $service->pullPage($id, 0);
        $service->pullPage($id, 0);
        $snapshot=json_decode(DB::table('marketplace_listing_variants')->where('store_id',$id)->value('observed_data'),true);
        $this->assertSame('https://cf.shopee.com.my/file/blue',$snapshot['variantImage']);
        $this->assertSame(1000,$snapshot['weightGrams']);
        $this->assertSame(3,$snapshot['packageHeightCm']);
        $this->assertDatabaseCount('marketplace_listing_variants', 1);
        \Illuminate\Support\Facades\Queue::fake();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $first = $this->postJson('/admin/api/marketplace/stores/'.$id.'/catalog-fetch')->assertStatus(202)->json('runId');
        $this->postJson('/admin/api/marketplace/stores/'.$id.'/catalog-fetch')->assertStatus(202)->assertJsonPath('runId', $first);
        $this->getJson('/admin/api/marketplace/catalog-runs/'.$first)->assertOk()->assertJsonPath('status', 'queued');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PullShopeeCatalog::class, 1);
        (new \App\Jobs\PullShopeeCatalog($first))->handle($service);
        $this->assertDatabaseHas('marketplace_catalog_runs', ['id'=>$first, 'status'=>'succeeded']);
        $this->assertDatabaseHas('marketplace_listing_variants', ['store_id'=>$id, 'external_product_id'=>'100', 'external_variant_id'=>'200']);
        $data = json_decode(DB::table('marketplace_listing_variants')->value('observed_data'), true);
        $this->assertSame('BLUE', $data['sku']);
        $this->assertSame('normal', $data['descriptionType']);
        $this->assertSame('Source description', $data['description']);
        $this->assertSame('Source Brand', $data['brand']);
        $this->assertSame(555, $data['externalCategoryId']);
        $this->assertSame(['https://down-my.img.susercontent.com/file/front', 'https://down-my.img.susercontent.com/file/back'], $data['productImages']);
        $this->assertNull($data['price']);
        $this->assertSame(97, $data['stock']);
        $this->assertSame($stockInfo, $data['stockInfo']);
        $this->assertSame($before, DB::table('products')->count());
        $this->assertDatabaseCount('marketplace_variant_mappings', 0);

        $runId = DB::table('marketplace_catalog_runs')->insertGetId([
            'store_id'=>$id, 'status'=>'queued', 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        (new \App\Jobs\PullShopeeCatalog($runId))->handle($service);
        $this->assertDatabaseHas('marketplace_catalog_runs', ['id'=>$runId, 'status'=>'succeeded', 'pages_completed'=>1]);
        $this->assertDatabaseCount('marketplace_listing_variants', 1);

        $stockInfo = null;
        $service->pullPage($id, 0);
        $missing = json_decode(DB::table('marketplace_listing_variants')->value('observed_data'), true);
        $this->assertNull($missing['stock']);
        $this->assertNull($missing['stockInfo']);
    }

    public function test_catalog_rejects_an_environment_change_during_fetch_without_advancing_run(): void
    {
        config(['marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'secret',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        Http::preventStrayRequests();
        $id = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
        MarketplaceStoreToken::create(['store_id'=>$id, 'application_id'=>'123', 'access_token'=>'access',
            'refresh_token'=>'refresh', 'access_expires_at'=>now()->addHours(4)]);
        Http::fake([
            '*/get_item_list*'=>Http::response(['response'=>['item'=>[['item_id'=>100]], 'has_next_page'=>false]]),
            '*/get_item_base_info*'=>function () use ($id) {
                DB::table('marketplace_stores')->where('id', $id)->update(['environment'=>'live']);
                return Http::response(['response'=>['item_list'=>[['item_id'=>100, 'has_model'=>false, 'item_name'=>'Sandbox item']]]]);
            },
        ]);
        $run = DB::table('marketplace_catalog_runs')->insertGetId(['store_id'=>$id, 'status'=>'queued',
            'pages_completed'=>1, 'next_offset'=>50, 'items_processed'=>50]);
        (new \App\Jobs\PullShopeeCatalog($run, 1))->handle(app(ShopeeCatalogService::class));
        $this->assertDatabaseHas('marketplace_catalog_runs', ['id'=>$run, 'status'=>'failed',
            'pages_completed'=>1, 'next_offset'=>50, 'items_processed'=>50, 'error_code'=>'catalog_page_failed']);
        $this->assertDatabaseCount('marketplace_listing_variants', 0);
    }

    public function test_retry_resumes_failed_offset_without_clearing_progress(): void
    {
        config(['marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'secret',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        $id = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected',
            'created_at'=>now(), 'updated_at'=>now()]);
        $run = DB::table('marketplace_catalog_runs')->insertGetId(['store_id'=>$id, 'status'=>'failed',
            'next_offset'=>50, 'pages_completed'=>1, 'error_code'=>'catalog_page_failed', 'created_at'=>now(), 'updated_at'=>now()]);
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        \Illuminate\Support\Facades\Queue::fake();
        $this->postJson('/admin/api/marketplace/catalog-runs/'.$run.'/retry')->assertStatus(202);
        $this->assertDatabaseHas('marketplace_catalog_runs', ['id'=>$run, 'status'=>'queued', 'next_offset'=>50, 'pages_completed'=>1, 'error_code'=>null]);
        $this->postJson('/admin/api/marketplace/catalog-runs/'.$run.'/retry')->assertStatus(409);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PullShopeeCatalog::class, 1);
    }

    public function test_latest_catalog_run_allows_a_reopened_admin_session_to_resume_observing_the_same_store_task(): void
    {
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada', 'shop_name'=>'Official test',
            'external_shop_id'=>'300163725140', 'region'=>'MY', 'environment'=>'live', 'status'=>'connected',
            'created_at'=>now(), 'updated_at'=>now()]);
        $run = DB::table('marketplace_catalog_runs')->insertGetId(['store_id'=>$store, 'status'=>'running',
            'next_offset'=>120, 'pages_completed'=>12, 'items_processed'=>120, 'variants_processed'=>347,
            'created_at'=>now(), 'updated_at'=>now()]);

        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->getJson('/admin/api/marketplace/stores/'.$store.'/catalog-runs/latest')->assertOk()
            ->assertJsonPath('id', $run)->assertJsonPath('status', 'running')
            ->assertJsonPath('pages_completed', 12)->assertJsonPath('items_processed', 120);
    }

    public function test_catalog_is_server_paginated_for_large_store(): void
    {
        $id = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected',
            'created_at'=>now(), 'updated_at'=>now()]);
        for ($i=1; $i<=320; $i++) {
            DB::table('marketplace_listing_variants')->insert(['store_id'=>$id, 'external_product_id'=>(string) $i,
                'external_variant_id'=>'0', 'observed_data'=>json_encode(['sku'=>'SKU-'.$i, 'productTitle'=>'Product '.$i]),
                'fetched_at'=>now(), 'created_at'=>now(), 'updated_at'=>now()]);
        }
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->getJson('/admin/api/marketplace/catalog?storeId='.$id.'&per_page=50&page=7')
            ->assertOk()->assertJsonPath('total', 320)->assertJsonPath('last_page', 7)->assertJsonCount(20, 'data');
        $this->getJson('/admin/api/marketplace/catalog?platform=lazada')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/admin/api/marketplace/catalog?q=SKU-320')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/admin/api/marketplace/catalog?per_page=1000')->assertUnprocessable();
    }
}
