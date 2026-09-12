<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Services\Marketplace\LazadaTestConnectionService;
use App\Services\Marketplace\LazadaTokenManager;
class LazadaTestConnectionTest extends TestCase {
 use RefreshDatabase;
 public function test_official_token_is_encrypted_with_lease_not_fabricated_provider_expiry():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'test-secret']);
  Http::preventStrayRequests();Http::fake(['*'=>Http::response(['code'=>'0','data'=>['seller_id'=>300163725140,'short_code'=>'MY4NA1T7CK','name'=>'Test','status'=>'ACTIVE']])]);
  $id=app(LazadaTestConnectionService::class)->connect('test-access');
  $row=DB::table('marketplace_store_tokens')->where('store_id',$id)->first();
  $this->assertSame('official_test',$row->credential_source);
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
  config(['marketplace.lazada.test_store_id'=>$id+1]);
  try { app(LazadaTokenManager::class)->accessToken($id);$this->fail('Non-designated store accepted online.'); }
  catch(\RuntimeException $error){$this->assertStringContainsString('unavailable',$error->getMessage());}
  config(['marketplace.lazada.test_store_id'=>$id,'marketplace.lazada.app_key'=>'another-app']);
  try { app(LazadaTokenManager::class)->accessToken($id);$this->fail('Different app accepted.'); }
  catch(\RuntimeException $error){$this->assertStringContainsString('credentials changed',$error->getMessage());}
  config(['marketplace.lazada.app_key'=>'139209']);
  Http::assertSentCount(1);
  $this->travel(61)->minutes();
  $this->expectException(\RuntimeException::class);
  app(LazadaTokenManager::class)->accessToken($id);
 }
}
