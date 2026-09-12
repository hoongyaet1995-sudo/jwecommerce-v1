<?php
namespace Tests\Feature;
use App\Services\Marketplace\MarketplaceOrderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class LazadaInventoryIngestionTest extends TestCase
{
 use RefreshDatabase;
 public function test_newer_item_snapshot_is_saved_when_parent_timestamp_is_unchanged():void
 {
  config(['marketplace.inventory_accounting_enabled'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  DB::table('marketplace_inventory_policies')->insert(['store_id'=>$store,'enabled'=>true,'orders_created_after'=>1,'reconciled_at'=>now()]);
  $payload=['order_id'=>'100','created_at'=>'2026-09-08 14:00:00 +0800','updated_at'=>'2026-09-08 14:01:00 +0800','statuses'=>['unpaid'],
   'order_items'=>[['order_id'=>'100','order_item_id'=>'101','product_id'=>'200','sku_id'=>'300','warehouse_code'=>'MY-WH-1','is_fbl'=>0,'semi_managed'=>false,
    'updated_at'=>'2026-09-08 14:01:00 +0800','status'=>'unpaid']]];
  $service=app(MarketplaceOrderSnapshotService::class);
  $id=$service->saveLazada($store,$payload);
  $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$id,'line_key'=>'101','status'=>'waiting']);
  $new=$payload; $new['statuses']=['canceled']; $new['order_items'][0]['status']='canceled';
  $new['order_items'][0]['updated_at']='2026-09-08 14:02:00 +0800';
  $service->saveLazada($store,$new);
  $this->assertDatabaseHas('marketplace_orders',['id'=>$id,'platform_status'=>'canceled']);
  $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$id,'line_key'=>'101','status'=>'released']);
  $service->saveLazada($store,$payload);
  $this->assertDatabaseHas('marketplace_orders',['id'=>$id,'platform_status'=>'canceled']);
  $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$id,'line_key'=>'101','status'=>'released']);
  $this->assertDatabaseCount('stock_allocations',0);
 }
 public function test_mixed_newer_and_older_item_versions_do_not_replace_the_snapshot():void
 {
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  $payload=['order_id'=>'100','updated_at'=>'2026-09-08 14:01:00 +0800','statuses'=>['pending'],
   'order_items'=>[['order_item_id'=>'101','updated_at'=>'2026-09-08 14:02:00 +0800','status'=>'pending'],
    ['order_item_id'=>'102','updated_at'=>'2026-09-08 14:02:00 +0800','status'=>'pending']]];
  $service=app(MarketplaceOrderSnapshotService::class); $id=$service->saveLazada($store,$payload);
  $new=$payload; $new['statuses']=['canceled'];
  $new['order_items'][0]['updated_at']='2026-09-08 14:03:00 +0800';
  $new['order_items'][1]['updated_at']='2026-09-08 14:01:00 +0800';
  $service->saveLazada($store,$new);
  $stored=\Illuminate\Support\Facades\Crypt::decryptString(DB::table('marketplace_orders')->where('id',$id)->value('encrypted_payload'));
  $this->assertSame($payload,json_decode($stored,true));
 }
 public function test_historical_orders_and_disabled_stores_never_enter_item_ledger():void
 {
  config(['marketplace.inventory_accounting_enabled'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  $created=\Carbon\CarbonImmutable::parse('2026-09-08 14:00:00 +0800')->timestamp;
  DB::table('marketplace_inventory_policies')->insert(['store_id'=>$store,'enabled'=>true,'orders_created_after'=>$created,'reconciled_at'=>now()]);
  $payload=['order_id'=>'100','created_at'=>'2026-09-08 14:00:00 +0800','updated_at'=>'2026-09-08 14:01:00 +0800','statuses'=>['canceled'],
   'order_items'=>[['order_id'=>'100','order_item_id'=>'101','product_id'=>'200','sku_id'=>'300','status'=>'canceled','warehouse_code'=>'MY-WH-1','is_fbl'=>0,'semi_managed'=>false]]];
  $service=app(MarketplaceOrderSnapshotService::class);
  $id=$service->saveLazada($store,$payload);
  $this->assertDatabaseHas('marketplace_order_inventory',['order_id'=>$id,'error_code'=>'historical_order']);
  $this->assertDatabaseCount('marketplace_inventory_items',0);
  DB::table('marketplace_inventory_policies')->where('store_id',$store)->update(['orders_created_after'=>1,'enabled'=>false]);
  $payload['order_id']='200'; $payload['order_items'][0]['order_id']='200';
  $paused=$service->saveLazada($store,$payload);
  $this->assertDatabaseMissing('marketplace_order_inventory',['order_id'=>$paused]);
  DB::table('marketplace_inventory_policies')->where('store_id',$store)->update(['enabled'=>true]);
  DB::table('marketplace_stores')->where('id',$store)->update(['status'=>'disconnected']);
  app(\App\Services\Marketplace\MarketplaceOrderInventoryService::class)->process($paused);
  try {
   $service->saveLazada($store,$payload);
   $this->fail('Disconnected store accepted ingestion');
  } catch (\RuntimeException $exception) {
   $this->assertSame('Store is not connected for Lazada order ingestion.',$exception->getMessage());
  }
  $this->assertDatabaseMissing('marketplace_order_inventory',['order_id'=>$paused]);
  $this->assertDatabaseCount('marketplace_inventory_items',0);
  $this->assertDatabaseCount('stock_allocations',0);
 }
 public function test_lazada_warehouse_exception_can_be_filtered_without_payload_leak():void
 {
  config(['marketplace.read_operations_enabled'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>'private-order-data','fetched_at'=>now()]);
  DB::table('marketplace_order_inventory')->insert(['order_id'=>$order,'status'=>'exception','error_code'=>'warehouse_route_mismatch','platform_updated_at'=>100,'updated_at'=>now()]);
  $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]))
   ->getJson('/admin/api/marketplace/inventory/exceptions?error=warehouse_route_mismatch')
   ->assertOk()->assertJsonPath('data.0.platform','lazada')->assertJsonPath('data.0.error_code','warehouse_route_mismatch')
   ->assertDontSee('private-order-data');
 }
 public function test_ingestion_enforces_policy_and_records_cancel_tombstone():void
 {
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  $payload=['order_id'=>'100','created_at'=>'2026-09-08 14:00:00 +0800','updated_at'=>'2026-09-08 14:01:00 +0800','statuses'=>['canceled'],
   'order_items'=>[['order_id'=>'100','order_item_id'=>'101','product_id'=>'200','sku_id'=>'300','status'=>'canceled','warehouse_code'=>'MY-WH-1','is_fbl'=>0,'semi_managed'=>false]]];
  $service=app(MarketplaceOrderSnapshotService::class);
  $id=$service->saveLazada($store,$payload);
  $this->assertDatabaseCount('marketplace_inventory_items',0);
  config(['marketplace.inventory_accounting_enabled'=>true]);
  DB::table('marketplace_inventory_policies')->insert(['store_id'=>$store,'enabled'=>true,'orders_created_after'=>1,'reconciled_at'=>now()]);
  $service->saveLazada($store,$payload);
  $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$id,'line_key'=>'101','status'=>'released']);
  $this->assertDatabaseHas('marketplace_order_inventory',['order_id'=>$id,'status'=>'released','terminal_status'=>null]);
  $this->assertDatabaseCount('stock_allocations',0);
  $payload['updated_at']='2026-09-08 14:02:00 +0800'; $payload['statuses']=['pending']; $payload['order_items'][0]['status']='pending';
  $service->saveLazada($store,$payload);
  $this->assertDatabaseHas('marketplace_order_inventory',['order_id'=>$id,'status'=>'exception','error_code'=>'inventory_lifecycle_conflict']);
  $this->assertDatabaseCount('stock_allocations',0);
 }
}
