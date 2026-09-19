<?php

namespace Tests\Feature;

use App\Jobs\PullLazadaCatalog;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LazadaCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_links_confirmed_published_variant_but_does_not_restore_a_manual_unlink(): void
    {
        $store=$this->connectedStore(); $this->fakeCatalog();
        $actor=\App\Models\User::factory()->create(['is_admin'=>true]);
        $product=\App\Models\Product::create(['title'=>'Published','handle'=>'published-link','base_price'=>5990]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'LZ-ONE','price'=>5990,'stock'=>31,'enabled'=>true]);
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
        $task=app(\App\Services\Marketplace\PublishingTasks::class)->create($store,$product->id,$actor->id,
            '6285db80-1111-4111-8111-123456789012',[],$source);
        DB::table('marketplace_publishing_tasks')->where('id',$task)->update(['status'=>'succeeded','external_item_id'=>'LZ-1',
            'confirmed_result'=>json_encode(['item_id'=>'LZ-1','sku_ids'=>['LZ-ONE'=>'S-1']])]);
        app(LazadaCatalogService::class)->pullItem($store,'LZ-1');
        $listing=DB::table('marketplace_listing_variants')->where('store_id',$store)->first();
        $this->assertDatabaseHas('marketplace_variant_mappings',['listing_variant_id'=>$listing->id,'product_variant_id'=>$variant->id,'actor_id'=>$actor->id]);
        app(LazadaCatalogService::class)->pullPage($store,0);
        $this->assertDatabaseCount('marketplace_mapping_events',1);
        $replacement=$product->variants()->create(['title'=>'Replacement','sku'=>'REPLACEMENT','price'=>5990,'stock'=>9,'enabled'=>true]);
        app(\App\Services\Marketplace\MarketplaceFoundationService::class)->map($listing->id,$replacement->id,$product->id,$actor->id);
        app(LazadaCatalogService::class)->pullPage($store,0);
        $this->assertDatabaseHas('marketplace_variant_mappings',['listing_variant_id'=>$listing->id,'product_variant_id'=>$replacement->id]);
        app(\App\Services\Marketplace\MarketplaceFoundationService::class)->map($listing->id,null,null,$actor->id);
        app(LazadaCatalogService::class)->pullPage($store,0);
        $this->assertDatabaseCount('marketplace_variant_mappings',0);
        $this->assertDatabaseCount('marketplace_mapping_events',3);
        $this->assertSame(31,$variant->fresh()->stock);
    }

    private function connectedStore(): int
    {
        config([
            'app.url'=>'https://example.test',
            'marketplace.operations_enabled'=>false,
            'marketplace.read_operations_enabled'=>true,
            'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123',
            'marketplace.lazada.app_secret'=>'fixture-secret',
            'marketplace.lazada.callback_url'=>'https://example.test/admin/marketplace/oauth/lazada/callback',
        ]);
        $store=DB::table('marketplace_stores')->insertGetId([
            'platform'=>'lazada','environment'=>'live','region'=>'MY','external_shop_id'=>'2001',
            'shop_name'=>'Lazada MY','status'=>'connected','created_at'=>now(),'updated_at'=>now(),
        ]);
        MarketplaceStoreToken::create([
            'store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','refresh_token'=>'fixture-refresh',
            'access_expires_at'=>now()->addHour(),'refresh_expires_at'=>now()->addDay(),
        ]);
        return $store;
    }

    private function fakeCatalog(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/products/get')) {
                return Http::response(['code'=>'0','data'=>['total'=>1,'products'=>[['item_id'=>'LZ-1']]]]);
            }
            if (str_contains($request->url(), '/product/item/get')) {
                return Http::response(['code'=>'0','data'=>[
                    'item_id'=>'LZ-1','attributes'=>['name'=>'Lazada Headphones'],'primaryCategory'=>'123',
                    'skus'=>[['SkuId'=>'S-1','SellerSku'=>'LZ-ONE','quantity'=>7,'price'=>'59.90','currency'=>'MYR','Status'=>'active']],
                ]]);
            }
            return Http::response(['code'=>'denied'], 400);
        });
    }

    public function test_pull_page_persists_observed_lazada_listing_without_creating_jw_products(): void
    {
        $store=$this->connectedStore(); $this->fakeCatalog();
        $before=DB::table('products')->count();
        $result=app(LazadaCatalogService::class)->pullPage($store,0);
        $this->assertSame(['items'=>1,'variants'=>1,'total_products'=>1,'has_more'=>false,'next_offset'=>10],$result);
        $this->assertDatabaseHas('marketplace_listing_variants',['store_id'=>$store,'external_product_id'=>'LZ-1','external_variant_id'=>'S-1']);
        $observed=json_decode(DB::table('marketplace_listing_variants')->value('observed_data'),true);
        $this->assertSame('Lazada Headphones',$observed['productTitle']);
        $this->assertSame('LZ-ONE',$observed['sku']);
        $this->assertSame(7,$observed['stock']);
        $this->assertSame($before,DB::table('products')->count());
        $this->assertDatabaseCount('marketplace_variant_mappings',0);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/products/get')
            && str_contains($request->url(), 'limit=10') && str_contains($request->url(), 'offset=0'));
    }

    public function test_pull_page_falls_back_to_single_reads_after_a_lazada_page_timeout(): void
    {
        $store=$this->connectedStore();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/products/get') && str_contains($request->url(), 'limit=10')) {
                return Http::response(['code'=>'ServiceTimeout','message'=>'provider timeout']);
            }
            if (str_contains($request->url(), '/products/get')) {
                return Http::response(['code'=>'0','data'=>['total'=>1,'products'=>[['item_id'=>'LZ-1']]]]);
            }
            if (str_contains($request->url(), '/product/item/get')) {
                return Http::response(['code'=>'0','data'=>['item_id'=>'LZ-1','attributes'=>['name'=>'Recovered'],
                    'skus'=>[['SkuId'=>'S-1','SellerSku'=>'RECOVERED','quantity'=>0]]]]);
            }
            return Http::response(['code'=>'denied'],400);
        });
        $result=app(LazadaCatalogService::class)->pullPage($store,0);
        $this->assertSame(['items'=>1,'variants'=>1,'total_products'=>1,'has_more'=>false,'next_offset'=>10],$result);
        Http::assertSentCount(3);
    }

    public function test_pull_page_accepts_a_saved_legacy_offset_when_page_size_changes(): void
    {
        $store=$this->connectedStore(); $this->fakeCatalog();
        $result=app(LazadaCatalogService::class)->pullPage($store,350);
        $this->assertSame(360,$result['next_offset']);
    }

    public function test_official_total_products_field_allows_catalog_capture(): void
    {
        $store=$this->connectedStore();
        Http::preventStrayRequests();
        Http::fake([
            '*/products/get*'=>Http::response(['code'=>'0','data'=>['total_products'=>2132,'products'=>[['item_id'=>14984118945]]]]),
            '*/product/item/get*'=>Http::response(['code'=>'0','data'=>['item_id'=>14984118945,
                'images'=>['https://sg-test-11.slatic.net/p/test.png'],'primary_category'=>10000664,'attributes'=>['name'=>'Test item'],'skus'=>[['SkuId'=>128571056069,'SellerSku'=>'TEST-1','quantity'=>20,'Images'=>['https://sg-test-11.slatic.net/p/sku.png']]]]]),
        ]);
        $result=app(LazadaCatalogService::class)->pullPage($store,0);
        $observed=json_decode(DB::table('marketplace_listing_variants')->where('store_id',$store)->value('observed_data'),true);
        $this->assertSame(10000664,$observed['externalCategoryId']);
        $this->assertSame('MYR',$observed['currency']);
        $this->assertSame(['https://sg-test-11.slatic.net/p/test.png'],$observed['productImages']);
        $this->assertSame('https://sg-test-11.slatic.net/p/test.png',$observed['productImage']);
        $this->assertSame('https://sg-test-11.slatic.net/p/sku.png',$observed['variantImage']);
        $this->assertTrue($result['has_more']);
        $this->assertSame(1,$result['variants']);
        $this->assertDatabaseHas('marketplace_listing_variants',['store_id'=>$store,'external_product_id'=>'14984118945']);
    }

    public function test_catalog_job_and_admin_submission_are_queue_backed_and_retry_preserves_cursor(): void
    {
        $store=$this->connectedStore(); $this->fakeCatalog();
        config(['app.url'=>'http://127.0.0.1:8500']);
        $this->assertFalse(app(\App\Services\Marketplace\LazadaClient::class)->isAuthorizationReady());
        $run=DB::table('marketplace_catalog_runs')->insertGetId(['store_id'=>$store,'status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
        (new PullLazadaCatalog($run))->handle(app(LazadaCatalogService::class));
        $this->assertDatabaseHas('marketplace_catalog_runs',['id'=>$run,'status'=>'succeeded','items_processed'=>1,'variants_processed'=>1]);

        $failed=DB::table('marketplace_catalog_runs')->insertGetId(['store_id'=>$store,'status'=>'failed','next_offset'=>100,'pages_completed'=>1,'error_code'=>'lazada_catalog_page_failed','created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        Queue::fake();
        $this->postJson('/admin/api/marketplace/catalog-runs/'.$failed.'/retry')->assertStatus(202)->assertJsonPath('runId',$failed);
        $this->assertDatabaseHas('marketplace_catalog_runs',['id'=>$failed,'status'=>'queued','next_offset'=>100,'pages_completed'=>1,'error_code'=>null]);
        Queue::assertPushed(PullLazadaCatalog::class,1);

        $submitted=$this->postJson('/admin/api/marketplace/stores/'.$store.'/catalog-fetch')->assertStatus(202)->json('runId');
        $this->assertSame($failed,$submitted);
        Queue::assertPushed(PullLazadaCatalog::class,1);
    }
}
