<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\User;
use App\Models\MarketplaceStoreToken;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Inventory\TestDataClassificationService;
use App\Services\Marketplace\SandboxInventoryWriteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SandboxInventoryWriteGuardTest extends TestCase
{
 use RefreshDatabase;
 private function fixture():array {
  config(['marketplace.basic_wms_enabled'=>true,'marketplace.sandbox_stock_writes_enabled'=>true,'marketplace.lazada.app_key'=>'139209']);
  $actor=User::factory()->create(['is_admin'=>true]);
  $variant=Product::create(['title'=>'Sandbox','handle'=>'sandbox','base_price'=>1])->variants()->create(['sku'=>'SBX','title'=>'One','price'=>1,'stock'=>17,'enabled'=>true]);
  $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
  DB::table('inventory_balances')->where('id',$balance)->update(['physical_stock'=>12,'safety_stock'=>2,'counted_at'=>now()]);
  DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool','accounting_enabled'=>false]);
  $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'TEST','name'=>'Test','is_active'=>true,'status'=>'active']);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','environment'=>'live','region'=>'MY','external_shop_id'=>'300163725140','shop_name'=>'Test','status'=>'connected']);
  MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'139209','credential_source'=>'official_test','test_lease_expires_at'=>now()->addHour(),'access_token'=>'test-only']);
  config(['marketplace.lazada.test_store_id'=>$store]);
  DB::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$store,'warehouse_id'=>$warehouse,'platform_location_id'=>'TEST-LOCATION','is_enabled'=>false,'version'=>0]);
  $listing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'123','external_variant_id'=>'456','observed_data'=>'{}','fetched_at'=>now()]);
  DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id,'actor_id'=>$actor->id]);
  foreach([['store',$store],['warehouse',$warehouse],['sku',$variant->id]] as [$type,$id])app(TestDataClassificationService::class)->markTest($type,$id,$actor,'Bounded sandbox fixture');
  return compact('actor','variant','balance','store','warehouse','listing');
 }
 public function test_basic_test_review_uses_manual_available_and_does_not_enable_takeover():void {
  $f=$this->fixture();$review=app(SandboxInventoryWriteGuard::class)->review($f['store'],$f['balance'],$f['actor']);
  $this->assertSame(10,$review['desired_quantity']);$this->assertFalse($review['order_coverage_complete']);
  $this->assertSame(17,(int)$f['variant']->fresh()->stock);
  $this->assertDatabaseHas('inventory_fulfillment_routes',['channel_key'=>'marketplace:'.$f['store'],'is_enabled'=>false]);
  $this->assertFalse((bool)config('marketplace.stock_sync_operations_enabled'));
 }
 public function test_unknown_or_non_designated_identity_is_rejected_before_transport():void {
  $f=$this->fixture();config(['marketplace.lazada.test_store_id'=>999]);
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
  app(SandboxInventoryWriteGuard::class)->review($f['store'],$f['balance'],$f['actor']);
 }
 public function test_expired_test_lease_and_disabled_gate_are_rejected():void {
  $f=$this->fixture();MarketplaceStoreToken::where('store_id',$f['store'])->update(['test_lease_expires_at'=>now()->subMinute()]);
  try{app(SandboxInventoryWriteGuard::class)->review($f['store'],$f['balance'],$f['actor']);$this->fail('Expired lease accepted');}
  catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
  config(['marketplace.sandbox_stock_writes_enabled'=>false]);
  try{app(SandboxInventoryWriteGuard::class)->review($f['store'],$f['balance'],$f['actor']);$this->fail('Disabled gate accepted');}
  catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
 }
 public function test_attempt_requires_independent_readback_and_confirmed_retry_never_writes_again():void {
  $f=$this->fixture();$transport=\Mockery::mock(\App\Services\Marketplace\SandboxStockTransport::class);
  $transport->shouldReceive('observe')->twice()->andReturn(25,10);$transport->shouldReceive('write')->once();
  $this->app->instance(\App\Services\Marketplace\SandboxStockTransport::class,$transport);
  $service=app(\App\Services\Marketplace\SandboxInventoryAttemptService::class);$key=(string)\Illuminate\Support\Str::uuid();
  $staged=$service->stage($f['store'],$f['balance'],$key,'Controlled test',$f['actor']);
  $this->assertSame('staged',$staged['status']);
  $done=$service->dispatch($key,$f['actor']);
  $this->assertSame('confirmed',$done['status']);$this->assertSame(25,$done['before_quantity']);$this->assertSame(10,$done['observed_quantity']);
  $this->assertSame($done,$service->dispatch($key,$f['actor']));
  $this->assertDatabaseCount('sandbox_inventory_attempts',1);$this->assertSame(17,(int)$f['variant']->fresh()->stock);
 }
 public function test_uncertain_transport_is_recorded_without_secret_messages_and_cannot_be_resent():void {
  $f=$this->fixture();$transport=\Mockery::mock(\App\Services\Marketplace\SandboxStockTransport::class);
  $transport->shouldReceive('observe')->once()->andReturn(25);
  $transport->shouldReceive('write')->once()->andThrow(new \RuntimeException('secret-access-token'));
  $this->app->instance(\App\Services\Marketplace\SandboxStockTransport::class,$transport);
  $service=app(\App\Services\Marketplace\SandboxInventoryAttemptService::class);$key=(string)\Illuminate\Support\Str::uuid();
  $service->stage($f['store'],$f['balance'],$key,'Controlled test',$f['actor']);
  $result=$service->dispatch($key,$f['actor']);$this->assertSame('uncertain',$result['status']);
  $this->assertStringNotContainsString('secret-access-token',json_encode($result));
  try{$service->dispatch($key,$f['actor']);$this->fail('Uncertain request resent');}
  catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
 }
 public function test_lazada_test_transport_keeps_production_flag_closed_and_checks_provider_identity():void {
  $f=$this->fixture();config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_secret'=>'test-secret','marketplace.stock_sync_operations_enabled'=>false]);
  \Illuminate\Support\Facades\Http::preventStrayRequests();
  \Illuminate\Support\Facades\Http::fake([
   '*/seller/get*'=>\Illuminate\Support\Facades\Http::response(['code'=>'0','data'=>['seller_id'=>'300163725140','short_code'=>'MY4NA1T7CK','status'=>'ACTIVE']]),
   '*/product/stock/sellable/update'=>\Illuminate\Support\Facades\Http::response(['code'=>'0']),
  ]);
  $snapshot=app(SandboxInventoryWriteGuard::class)->review($f['store'],$f['balance'],$f['actor']);
  app(\App\Services\Marketplace\LazadaClient::class)->updateSandboxQuantity($snapshot,$f['actor']);
  \Illuminate\Support\Facades\Http::assertSentCount(2);
  \Illuminate\Support\Facades\Http::assertSent(fn($r)=>$r->method()==='POST'&&str_contains($r['payload'],'<SellableQuantity>10</SellableQuantity>'));
  $this->assertFalse(config('marketplace.stock_sync_operations_enabled'));
 }
 public function test_endpoint_uses_session_actor_and_never_accepts_client_quantity():void {
  $f=$this->fixture();$url='/admin/api/inventory/sandbox-attempts';
  $this->postJson($url,[])->assertUnauthorized();
  $payload=['store_id'=>$f['store'],'balance_id'=>$f['balance'],'operation_key'=>(string)\Illuminate\Support\Str::uuid(),'reason'=>'Sandbox acceptance'];
  $this->actingAs($f['actor'])->postJson($url,$payload+['quantity'=>999])->assertUnprocessable();
  $this->postJson($url,$payload)->assertOk()->assertJsonPath('actor_id',$f['actor']->id)->assertJsonPath('snapshot.desired_quantity',10);
  $this->getJson($url.'/'.$payload['operation_key'])->assertOk()->assertJsonPath('status','staged');
  $this->getJson($url.'?variantId='.$f['variant']->id)->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',$payload['operation_key']);
  $this->getJson($url.'?variantId=999999')->assertOk()->assertJsonPath('total',0);
  $this->actingAs(User::factory()->create(['is_admin'=>true]))->postJson($url.'/'.$payload['operation_key'].'/dispatch')->assertForbidden();
  $this->assertDatabaseHas('sandbox_inventory_attempts',['id'=>$payload['operation_key'],'status'=>'staged']);
 }
 public function test_shopee_checks_seller_location_limits_and_per_model_confirmation():void {
  $f=$this->fixture();
  config(['marketplace.shopee.enabled'=>true,'marketplace.shopee.environment'=>'sandbox','marketplace.shopee.partner_id'=>'12345',
   'marketplace.shopee.partner_key'=>'test-secret','marketplace.shopee.callback_url'=>'https://example.test/callback','marketplace.shopee.test_store_id'=>$f['store']]);
  DB::table('marketplace_stores')->where('id',$f['store'])->update(['platform'=>'shopee','environment'=>'sandbox','external_shop_id'=>'227482706']);
  MarketplaceStoreToken::where('store_id',$f['store'])->update(['application_id'=>'12345','credential_source'=>'oauth','access_expires_at'=>now()->addHour()]);
  $failure=false;
  \Illuminate\Support\Facades\Http::preventStrayRequests();
  \Illuminate\Support\Facades\Http::fake(function($request)use(&$failure){
   $path=parse_url($request->url(),PHP_URL_PATH);
   $data=match($path){
    '/api/v2/shop/get_shop_info'=>['error'=>'','shop_id'=>227482706,'shop_name'=>'Test','region'=>'MY'],
    '/api/v2/product/get_item_base_info'=>['error'=>'','response'=>['item_list'=>[['item_id'=>123,'has_model'=>true]]]],
    '/api/v2/product/get_model_list'=>['error'=>'','response'=>['model'=>[['model_id'=>456,'stock_info_v2'=>['seller_stock'=>[['location_id'=>'TEST-LOCATION','stock'=>25]]]]]]],
    '/api/v2/product/get_item_limit'=>['error'=>'','response'=>['stock_limit'=>['min_limit'=>0,'max_limit'=>1000]]],
    '/api/v2/product/update_stock'=>['error'=>'','response'=>['failure_list'=>$failure?[['model_id'=>456,'failed_reason'=>'private provider message']]:[],
      'success_list'=>$failure?[]:[['model_id'=>456,'location_id'=>'TEST-LOCATION','stock'=>10]]]],
    default=>throw new \RuntimeException('Unexpected endpoint'),
   };
   return \Illuminate\Support\Facades\Http::response($data);
  });
  $snapshot=app(SandboxInventoryWriteGuard::class)->review($f['store'],$f['balance'],$f['actor']);
  app(\App\Services\Marketplace\ShopeeClient::class)->updateSandboxQuantity($snapshot,$f['actor']);
  \Illuminate\Support\Facades\Http::assertSent(fn($r)=>$r->method()==='POST'&&$r['stock_list'][0]['seller_stock'][0]===['location_id'=>'TEST-LOCATION','stock'=>10]);
  $failure=true;
  try{app(\App\Services\Marketplace\ShopeeClient::class)->updateSandboxQuantity($snapshot,$f['actor']);$this->fail('Model failure accepted');}
  catch(\RuntimeException $error){$this->assertStringContainsString('not confirmed',$error->getMessage());}
 }
}
