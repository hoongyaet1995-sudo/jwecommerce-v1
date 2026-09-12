<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\MarketplaceRtsAttempts;
use App\Services\Marketplace\LazadaRtsDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaRtsDispatchTest extends TestCase
{
 use RefreshDatabase;
 public function test_lost_response_stays_uncertain_and_blocks_new_request_identity():void
 {
  config(['marketplace.fulfillment_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  $actor=User::factory()->create(['is_admin'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'packed','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $id=app(MarketplaceRtsAttempts::class)->stage($order,$actor->id,'aef9eec2-62f0-4aa6-8a4f-533ad08893b8',['PK1']);
  $sends=0;
  Http::preventStrayRequests();
  Http::fake(['*/order/items/get*'=>Http::response(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','status'=>'packed','package_id'=>'PK1','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0,'semi_managed'=>false]]]),
   '*/order/package/rts'=>function()use(&$sends){$sends++;throw new \Illuminate\Http\Client\ConnectionException('fixture-secret signed request');}]);
  try {app(LazadaRtsDispatch::class)->dispatch($id);$this->fail('Confirmed missing response');}
  catch (\RuntimeException $error) {$this->assertSame('RTS outcome is uncertain. Reconcile before retrying.',$error->getMessage());$this->assertNull($error->getPrevious());}
  $this->assertSame('uncertain',DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status'));
  $this->assertNull(DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('confirmed_result'));
  try {app(LazadaRtsDispatch::class)->dispatch($id);$this->fail('Resent');}
  catch (\RuntimeException $error) {$this->assertSame('RTS task was already claimed.',$error->getMessage());}
  try {app(MarketplaceRtsAttempts::class)->stage($order,$actor->id,'cb787d81-d83e-47cd-a9c2-c7d9df9b50cd',['PK1']);$this->fail('New identity bypass');}
  catch (\DomainException $error) {$this->assertStringContainsString('Reconcile',$error->getMessage());}
  $this->assertSame(1,$sends);
  Http::swap(new \Illuminate\Http\Client\Factory());
  Http::preventStrayRequests();
  Http::fake(['*/order/items/get*'=>Http::sequence()
   ->push(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','package_id'=>'PK1','status'=>'packed']]])
   ->push(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','package_id'=>'PK1','status'=>'ready_to_ship']]])]);
  $reconciler=app(\App\Services\Marketplace\LazadaRtsReconciliation::class);
  $this->assertFalse($reconciler->reconcile($id)['confirmed']);
  $this->assertSame('uncertain',DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status'));
  DB::table('marketplace_fulfillment_attempts')->where('id',$id)->update(['item_ids'=>json_encode(['PK1'=>['101','102']])]);
  $this->assertFalse($reconciler->reconcile($id)['confirmed']);
  $this->assertSame('uncertain',DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status'));
  Http::swap(new \Illuminate\Http\Client\Factory());
  Http::preventStrayRequests();
  Http::fake(['*/order/items/get*'=>Http::response(['code'=>'0','data'=>[
   ['order_id'=>'100','order_item_id'=>'101','package_id'=>'PK1','status'=>'ready_to_ship'],
   ['order_id'=>'100','order_item_id'=>'102','package_id'=>'PK1','status'=>'ready_to_ship'],
  ]])]);
  $this->assertTrue($reconciler->reconcile($id)['confirmed']);
  $this->assertSame('succeeded',DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status'));
  $this->assertSame(1,$sends);
 }
 public function test_dispatch_revalidates_and_persists_once():void
 {
  config(['marketplace.fulfillment_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  $actor=User::factory()->create(['is_admin'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $id=app(MarketplaceRtsAttempts::class)->stage($order,$actor->id,'4faf762a-d722-4664-b637-b067b36f296a',['PK1']);
  Http::preventStrayRequests();
  Http::fake(['*/order/items/get*'=>Http::response(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','status'=>'packed','package_id'=>'PK1','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0,'semi_managed'=>false]]]),
   '*/order/shipment/providers/get*'=>Http::response(['code'=>'0','result'=>['success'=>true,'data'=>['platform_default'=>1,'shipping_allocate_type'=>'TFS']]]),
   '*/order/package/rts'=>Http::response(['code'=>'0','result'=>['success'=>true,'data'=>['packages'=>[['item_err_code'=>'0','package_id'=>'PK1']]]]])]);
  $this->assertSame('succeeded',app(LazadaRtsDispatch::class)->dispatch($id)['status']);
  $this->assertSame('ready_to_ship',json_decode(DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('confirmed_result'),true)['packages']['PK1']['status']);
  try { app(LazadaRtsDispatch::class)->dispatch($id); $this->fail('Replayed'); }
  catch (\RuntimeException $error) { $this->assertSame('RTS task was already claimed.',$error->getMessage()); }
  Http::assertSentCount(2);
 }
}
