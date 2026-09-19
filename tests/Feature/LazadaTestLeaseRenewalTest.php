<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaTestConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LazadaTestLeaseRenewalTest extends TestCase
{
    use RefreshDatabase;

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
}
