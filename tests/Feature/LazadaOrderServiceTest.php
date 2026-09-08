<?php
namespace Tests\Feature;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
class LazadaOrderServiceTest extends TestCase
{
 use RefreshDatabase;
 public function test_pull_page_fetches_order_items_and_keeps_snapshot_inventory_decoupled():void
 {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','environment'=>'live','region'=>'MY','external_shop_id'=>'2001','shop_name'=>'Lazada MY','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
  MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','refresh_token'=>'fixture-refresh','access_expires_at'=>now()->addHour(),'refresh_expires_at'=>now()->addDay()]);
  Http::preventStrayRequests();
  Http::fake(function($request){
   if(str_contains($request->url(),'/orders/get'))return Http::response(['code'=>'0','data'=>['countTotal'=>'1','orders'=>[['order_id'=>'LZ-1','updated_at'=>'2026-09-08T10:00:00+08:00','statuses'=>['pending'],'currency'=>'MYR','price'=>'25.00']]]]);
   if(str_contains($request->url(),'/order/items/get'))return Http::response(['code'=>'0','data'=>['order_items'=>[['order_item_id'=>'LI-1','status'=>'pending']]]]);
   return Http::response(['code'=>'denied'],400);
  });
  $result=app(LazadaOrderService::class)->pullPage($store,1725724800,1725811200);
  $this->assertSame(['orders'=>1,'next_offset'=>100,'has_more'=>false,'count_total'=>1],$result);
  $this->assertDatabaseHas('marketplace_orders',['store_id'=>$store,'external_order_id'=>'LZ-1','platform_status'=>'pending']);
  $this->assertDatabaseCount('marketplace_order_inventory',0);
  Http::assertSentCount(2);
 }
 public function test_lazada_read_run_and_admin_submission_are_queue_backed():void
 {
  config(['app.url'=>'https://example.test','marketplace.operations_enabled'=>false,'marketplace.read_operations_enabled'=>true,
   'marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret','marketplace.lazada.callback_url'=>'https://example.test/admin/marketplace/oauth/lazada/callback']);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','environment'=>'live','region'=>'MY','external_shop_id'=>'2002','shop_name'=>'Lazada MY','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
  MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','refresh_token'=>'fixture-refresh','access_expires_at'=>now()->addHour(),'refresh_expires_at'=>now()->addDay()]);
  Http::fake(['api.lazada.com.my/*'=>function($request){return str_contains($request->url(),'/orders/get')
   ?Http::response(['code'=>'0','data'=>['countTotal'=>0,'orders'=>[]]]) : Http::response(['code'=>'0','data'=>['order_items'=>[]]]);}]);
  $run=DB::table('marketplace_order_runs')->insertGetId(['store_id'=>$store,'status'=>'queued','time_from'=>1725724800,'time_to'=>1725811200,'created_at'=>now(),'updated_at'=>now()]);
  (new \App\Jobs\PullLazadaOrders($run))->handle(app(LazadaOrderService::class));
  $this->assertDatabaseHas('marketplace_order_runs',['id'=>$run,'status'=>'succeeded','orders_processed'=>0]);
  $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
  Queue::fake();
  $task=$this->postJson('/admin/api/marketplace/stores/'.$store.'/orders-fetch',['from'=>1725724800,'to'=>1725811200])->assertStatus(202)->json('runId');
  $this->assertNotSame($run,$task);Queue::assertPushed(\App\Jobs\PullLazadaOrders::class,1);
 }
}
