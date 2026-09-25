<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaTestConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Support\StoreInventoryFixture;
use App\Contracts\StoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\FacilityStockReview;
use App\Services\Inventory\StoreManagement\FacilitySyncRecovery;
use Illuminate\Support\Facades\Queue;

class LazadaTestLeaseRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_retries_current_failed_facility_stock_and_confirms_provider_readback(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209',
            'marketplace.lazada.app_secret'=>'test','marketplace.lazada.test_app_key'=>'139209',
            'marketplace.lazada.test_app_secret'=>'test',
            'marketplace.multiwarehouse_test_acceptance_enabled'=>true]);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_stores']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0','data'=>[
            'seller_id'=>300163725140,'short_code'=>'MY4NA1T7CK','name'=>'Test','status'=>'ACTIVE',
        ]])]);
        $service=app(LazadaTestConnectionService::class);
        $storeId=$service->connect('private-test-token');
        $actor=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        app(\App\Services\Inventory\TestDataClassificationService::class)
            ->markTest('store',$storeId,$actor,'Official test fixture');
        config(['marketplace.lazada.test_store_id'=>$storeId]);
        $variant=(new StoreInventoryFixture)->sku()['variant'];
        app(FacilityRegistry::class)->confirm($storeId,[['provider_id'=>'WH-A','name'=>'A']]);
        $facility=DB::table('marketplace_store_facilities')->where('marketplace_store_id',$storeId)->first();
        $warehouseId=DB::table('inventory_warehouses')->insertGetId([
            'code'=>'JW-RENEW-A','name'=>'Renew A','is_active'=>true,'status'=>'active',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('marketplace_store_facilities')->where('id',$facility->id)
            ->update(['inventory_warehouse_id'=>$warehouseId]);
        $listingId=DB::table('marketplace_listing_variants')->insertGetId([
            'store_id'=>$storeId,'external_product_id'=>'100','external_variant_id'=>'200',
            'observed_data'=>'{}','fetched_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id'=>$storeId,'listing_variant_id'=>$listingId,'product_variant_id'=>$variant->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        Queue::fake();
        $review=app(FacilityStockReview::class)->review($actor,$storeId,$facility->id,$variant->id,
            'count',7,'Verified warehouse count');
        $receipt=app(FacilityStockReview::class)->confirm($actor,$review['id'],'renewal-count');
        DB::table('inventory_store_sync_tasks')->where('id',$receipt['provider_sync_task']['id'])
            ->update(['status'=>'auth_expired']);
        $publisher=\Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->andReturn([
            'provider'=>'lazada','provider_location_id'=>'WH-A','observed_quantity'=>7,
            'confirmation_method'=>'provider_readback','confirmed_at'=>now()->toIso8601String(),
        ]);
        $this->app->instance(StoreInventoryPublisher::class,$publisher);

        $service->renew($storeId,$actor,'Renew and recover stock');

        $details=app(FacilitySyncRecovery::class)->details($storeId,$facility->id,$variant->id);
        $this->assertSame('synced',$details['status']);
        $this->assertSame(7,$details['marketplace_reported_stock']);
        $this->assertSame('confirmed',$details['task_status']);
    }

    public function test_online_renewal_requires_explicit_designation_and_fresh_seller_validation(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'test',
            'marketplace.lazada.test_app_key'=>'139209','marketplace.lazada.test_app_secret'=>'test']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0','data'=>['seller_id'=>300163725140,'short_code'=>'MY4NA1T7CK','name'=>'Test','status'=>'ACTIVE']])]);
        $service=app(LazadaTestConnectionService::class);
        $id=$service->connect('private-test-token');
        $actor=User::factory()->create(['is_admin'=>true]);
        app(\App\Services\Inventory\TestDataClassificationService::class)->markTest('store',$id,$actor,'Official test fixture');
        $variant=(new StoreInventoryFixture)->sku()['variant'];
        DB::table('inventory_store_sync_tasks')->insert([
            'id'=>'11111111-1111-4111-8111-111111111111',
            'marketplace_store_id'=>$id,'product_variant_id'=>$variant->id,
            'stage'=>'facility','status'=>'auth_expired','balance_version'=>1,
            'event_key'=>'expired-before-renewal','created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->travel(61)->minutes();
        $this->app->instance('env','production');
        try {$service->renew($id,$actor,'Renew for acceptance');$this->fail('Online renewal implicitly enabled.');}
        catch(\RuntimeException $error){$this->assertStringContainsString('disabled',$error->getMessage());}
        Http::assertSentCount(1);
        config(['marketplace.lazada.online_test_access_enabled'=>true,'marketplace.lazada.test_store_id'=>$id]);
        $receipt=$service->renew($id,$actor,'Renew for acceptance');
        $this->assertSame($id,$receipt['store_id']);
        $this->assertArrayNotHasKey('access_token',$receipt);
        $this->assertTrue(MarketplaceStoreToken::where('store_id',$id)->first()->test_lease_expires_at->isFuture());
        $this->assertSame('private-test-token',MarketplaceStoreToken::where('store_id',$id)->first()->access_token);
        $this->assertDatabaseHas('inventory_store_sync_tasks',[
            'id'=>'11111111-1111-4111-8111-111111111111','status'=>'failed',
        ]);
        Http::assertSentCount(2);
        $this->actingAs($actor)->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('stores.0.status','connected')
            ->assertDontSee('private-test-token');
        config(['marketplace.lazada.online_test_access_enabled'=>false]);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('stores.0.status','reauthorization_required');
        config(['marketplace.lazada.online_test_access_enabled'=>true,'marketplace.lazada.test_store_id'=>$id+1]);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('stores.0.status','reauthorization_required');
        MarketplaceStoreToken::where('store_id',$id)->update(['credential_source'=>'oauth']);
        $this->expectException(\RuntimeException::class);
        $service->renew($id,$actor,'Cannot replace OAuth');
    }

    public function test_administrator_can_reconnect_only_the_designated_lazada_test_store_with_a_fresh_api_explorer_token(): void
    {
        config([
            'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'139209',
            'marketplace.lazada.app_secret'=>'production-secret',
            'marketplace.lazada.test_app_key'=>'139041',
            'marketplace.lazada.test_app_secret'=>'test-secret',
            'marketplace.lazada.online_test_access_enabled'=>true,
        ]);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0','data'=>[
            'seller_id'=>300163725140,'short_code'=>'MY4NA1T7CK','name'=>'Lazada MY Test Seller','status'=>'ACTIVE',
        ]])]);

        $service=app(LazadaTestConnectionService::class);
        $storeId=$service->connect('stale-token');
        $actor=User::factory()->create(['is_admin'=>true]);
        app(\App\Services\Inventory\TestDataClassificationService::class)->markTest('store',$storeId,$actor,'Official test fixture');
        config(['marketplace.lazada.test_store_id'=>$storeId]);
        DB::table('marketplace_stores')->where('id',$storeId)->update(['status'=>'disconnected']);
        MarketplaceStoreToken::where('store_id',$storeId)->delete();

        $response=$this->actingAs($actor)->postJson("/admin/api/marketplace/stores/{$storeId}/lazada-test-access", [
            'accessToken'=>'fresh-api-explorer-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('store.id',(string)$storeId)
            ->assertJsonPath('store.status','connected')
            ->assertJsonPath('store.credentialSource','official_test')
            ->assertJsonMissingPath('accessToken')
            ->assertDontSee('fresh-api-explorer-token');
        $this->assertDatabaseHas('marketplace_stores',['id'=>$storeId,'status'=>'connected','account_mode'=>'test']);
        $this->assertSame('fresh-api-explorer-token',MarketplaceStoreToken::where('store_id',$storeId)->firstOrFail()->access_token);
    }
}
