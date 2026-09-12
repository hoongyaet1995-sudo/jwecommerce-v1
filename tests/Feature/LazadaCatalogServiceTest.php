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
        $this->assertSame(['items'=>1,'variants'=>1,'has_more'=>false,'next_offset'=>50],$result);
        $this->assertDatabaseHas('marketplace_listing_variants',['store_id'=>$store,'external_product_id'=>'LZ-1','external_variant_id'=>'S-1']);
        $observed=json_decode(DB::table('marketplace_listing_variants')->value('observed_data'),true);
        $this->assertSame('Lazada Headphones',$observed['productTitle']);
        $this->assertSame('LZ-ONE',$observed['sku']);
        $this->assertSame(7,$observed['stock']);
        $this->assertSame($before,DB::table('products')->count());
        $this->assertDatabaseCount('marketplace_variant_mappings',0);
    }

    public function test_official_total_products_field_allows_catalog_capture(): void
    {
        $store=$this->connectedStore();
        Http::preventStrayRequests();
        Http::fake([
            '*/products/get*'=>Http::response(['code'=>'0','data'=>['total_products'=>2132,'products'=>[['item_id'=>14984118945]]]]),
            '*/product/item/get*'=>Http::response(['code'=>'0','data'=>['item_id'=>14984118945,
                'images'=>['https://sg-test-11.slatic.net/p/test.png'],'primary_category'=>10000664,'attributes'=>['name'=>'Test item'],'skus'=>[['SkuId'=>128571056069,'SellerSku'=>'TEST-1','quantity'=>20]]]]),
        ]);
        $result=app(LazadaCatalogService::class)->pullPage($store,0);
        $observed=json_decode(DB::table('marketplace_listing_variants')->where('store_id',$store)->value('observed_data'),true);
        $this->assertSame(10000664,$observed['externalCategoryId']);
        $this->assertSame('MYR',$observed['currency']);
        $this->assertSame(['https://sg-test-11.slatic.net/p/test.png'],$observed['productImages']);
        $this->assertTrue($result['has_more']);
        $this->assertSame(1,$result['variants']);
        $this->assertDatabaseHas('marketplace_listing_variants',['store_id'=>$store,'external_product_id'=>'14984118945']);
    }

    public function test_catalog_job_and_admin_submission_are_queue_backed_and_retry_preserves_cursor(): void
    {
        $store=$this->connectedStore(); $this->fakeCatalog();
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
