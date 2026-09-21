<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaTestConnectionService;
use App\Services\Marketplace\LazadaTokenManager;
class LazadaTestConnectionTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {
  parent::setUp();
  require_once __DIR__.'/../../app/Services/Marketplace/LazadaTestConnectionService.php';
  if (!Schema::hasTable('marketplace_store_capabilities')) (require __DIR__.'/../../database/migrations/2026_09_18_000001_add_account_mode_and_store_capabilities.php')->up();
 }
 public function test_official_token_is_encrypted_with_lease_not_fabricated_provider_expiry():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'production-secret',
   'marketplace.lazada.test_app_key'=>'139041','marketplace.lazada.test_app_secret'=>'test-secret']);
  Http::preventStrayRequests();Http::fake(['*'=>Http::response(['code'=>'0','data'=>['seller_id'=>300163725140,'short_code'=>'MY4NA1T7CK','name'=>'Test','status'=>'ACTIVE']])]);
  $id=app(LazadaTestConnectionService::class)->connect('test-access');
  $this->assertDatabaseHas('inventory_store_accounts',[
   'marketplace_store_id'=>$id,'management_status'=>'setup_required',
  ]);
  $this->assertDatabaseHas('marketplace_stores',['id'=>$id,'account_mode'=>'test']);
  $this->assertDatabaseHas('marketplace_store_capabilities',[
   'store_id'=>$id,
   'catalog_read'=>true,
   'product_publish'=>true,
   'order_receive'=>true,
   'inventory_update'=>true,
   'fulfillment_write'=>false,
  ]);
  $row=DB::table('marketplace_store_tokens')->where('store_id',$id)->first();
  $this->assertSame('official_test',$row->credential_source);
  $this->assertSame('139041',$row->application_id);
  $this->assertNull($row->access_expires_at);$this->assertNull($row->refresh_token);
  $this->assertNotSame('test-access',$row->access_token);
  $this->assertSame('test-access',app(LazadaTokenManager::class)->accessToken($id));
  $this->app->instance('env','production');
  try { app(LazadaTokenManager::class)->accessToken($id);$this->fail('Online test access enabled implicitly.'); }
  catch(\RuntimeException $error){$this->assertStringContainsString('unavailable',$error->getMessage());}
  config(['marketplace.lazada.online_test_access_enabled'=>true,'marketplace.lazada.test_store_id'=>$id]);
  try { app(LazadaTokenManager::class)->accessToken($id);$this->fail('Unclassified test store accepted online.'); }
  catch(\RuntimeException $error){$this->assertStringContainsString('unavailable',$error->getMessage());}
  DB::table('marketplace_stores')->where('id',$id)->update(['data_classification'=>'test']);
  $this->assertSame('test-access',app(LazadaTokenManager::class)->accessToken($id));
  config([
   'marketplace.read_operations_enabled'=>true,
   'marketplace.publish_operations_enabled'=>true,
   'marketplace.stock_sync_operations_enabled'=>true,
  ]);
  $actor=\App\Models\User::factory()->create(['is_admin'=>true]);
  $this->actingAs($actor)->getJson('/admin/api/marketplace/connections')->assertOk()
   ->assertJsonPath('stores.0.accountMode','test')
   ->assertJsonPath('stores.0.credentialSource','official_test')
   ->assertJsonPath('stores.0.capabilities.catalogRead',true)
   ->assertJsonPath('stores.0.capabilities.productPublish',true)
   ->assertJsonPath('stores.0.capabilities.orderReceive',true)
   ->assertJsonPath('stores.0.capabilities.inventoryUpdate',true)
   ->assertJsonPath('stores.0.capabilities.fulfillmentWrite',false)
   ->assertJsonPath('capabilities.lazada.catalog',true)
   ->assertJsonPath('capabilities.lazada.publish',true)
   ->assertJsonPath('capabilities.lazada.orders',true)
   ->assertJsonPath('capabilities.lazada.inventory',true)
   ->assertJsonPath('capabilities.lazada.fulfillment',false)
   ->assertDontSee('test-access');
  config(['marketplace.lazada.test_store_id'=>$id+1]);
  try { app(LazadaTokenManager::class)->accessToken($id);$this->fail('Non-designated store accepted online.'); }
  catch(\RuntimeException $error){$this->assertStringContainsString('unavailable',$error->getMessage());}
  config(['marketplace.lazada.test_store_id'=>$id,'marketplace.lazada.test_app_key'=>'another-app']);
  try { app(LazadaTokenManager::class)->accessToken($id);$this->fail('Different app accepted.'); }
  catch(\RuntimeException $error){$this->assertStringContainsString('credentials changed',$error->getMessage());}
  config(['marketplace.lazada.test_app_key'=>'139041']);
  Http::assertSentCount(1);
  $this->travel(61)->minutes();
  $this->expectException(\RuntimeException::class);
  app(LazadaTokenManager::class)->accessToken($id);
 }

 public function test_hidden_console_import_connects_only_the_verified_test_seller_without_echoing_token():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'production-secret',
   'marketplace.lazada.test_app_key'=>'139041','marketplace.lazada.test_app_secret'=>'test-secret']);
  Http::preventStrayRequests();
  Http::fake(['*'=>Http::response(['code'=>'0','data'=>[
   'seller_id'=>300163725140,'short_code'=>'MY4NA1T7CK','name'=>'Lazada MY Test Seller','status'=>'ACTIVE',
  ]])]);

  $this->artisan('marketplace:lazada-test-connect')
   ->expectsQuestion('Lazada official test access token','private-console-token')
   ->expectsOutputToContain('Connected verified Lazada MY Test Seller as store #1.')
   ->doesntExpectOutputToContain('private-console-token')
   ->assertSuccessful();

  $this->assertDatabaseHas('marketplace_stores',[
   'id'=>1,'external_shop_id'=>'300163725140','account_mode'=>'test','status'=>'connected',
  ]);
  $this->assertDatabaseHas('marketplace_store_tokens',['store_id'=>1,'application_id'=>'139041','credential_source'=>'official_test']);
  $this->assertNotSame('private-console-token',DB::table('marketplace_store_tokens')->where('store_id',1)->value('access_token'));
  $this->assertSame('private-console-token',MarketplaceStoreToken::where('store_id',1)->firstOrFail()->access_token);
 }
}
