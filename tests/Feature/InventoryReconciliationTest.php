<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class InventoryReconciliationTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();config(['marketplace.advanced_wms_enabled'=>true]);}
 public function test_reservation_order_identity_is_resolved_without_customer_data():void {
  $order=\App\Models\Order::create(['number'=>'WMS-ORDER-1','email'=>'private@example.test','status'=>'pending','currency'=>'MYR','subtotal'=>10,'total'=>10]);
  $rows=collect([(object)['id'=>1,'owner_type'=>'order','owner_id'=>$order->id,'quantity'=>2],
    (object)['id'=>2,'owner_type'=>'order','owner_id'=>999999,'quantity'=>1]]);
  $result=app(\App\Services\Inventory\InventoryReservationOrderReader::class)->enrich($rows);
  $this->assertSame(['id'=>$order->id,'number'=>'WMS-ORDER-1','status'=>'pending','channel'=>'webstore','store_id'=>null],$result[0]->order);
  $this->assertNull($result[1]->order);
  $this->assertStringNotContainsString('private@example.test',json_encode($result));
 }
 public function test_reconciliation_api_uses_session_actor_and_returns_server_receipt():void {
  $actor=User::factory()->create(['is_admin'=>true]);$this->actingAs($actor);
  $variant=Product::create(['title'=>'API reconcile','handle'=>'api-reconcile','base_price'=>100])->variants()->create(['sku'=>'API-REC','title'=>'One','price'=>100,'stock'=>9]);
  $id=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
  DB::table('inventory_balances')->where('id',$id)->update(['physical_stock'=>9,'counted_at'=>now()]);
  $payload=['balance_id'=>$id,'balance_version'=>0,'stock_version'=>0,'safety_stock'=>1,'operation_key'=>'api-reconcile','reason'=>'Initial verified buffer'];
  $this->postJson('/admin/api/inventory/reconciliations',$payload+['actor_id'=>999])->assertUnprocessable();
  $this->postJson('/admin/api/inventory/reconciliations',$payload)->assertOk()->assertJsonPath('status','reconciled')
    ->assertJsonPath('physical_stock',9)->assertJsonPath('reserved_stock',0)->assertJsonPath('safety_stock',1)->assertJsonPath('atp',8);
  $receipt=$this->postJson('/admin/api/inventory/reconciliations',$payload)->assertOk()->assertJsonPath('atp',8)
    ->assertJsonPath('operator_id',$actor->id)->assertJsonPath('operator',$actor->name)->json();
  $this->travel(5)->minutes();
  $this->postJson('/admin/api/inventory/reconciliations',$payload)->assertOk()->assertJsonPath('timestamp',$receipt['timestamp']);
  $this->assertDatabaseCount('inventory_reconciliations',1);
  $this->assertDatabaseHas('inventory_reconciliations',['actor_id'=>$actor->id]);
 }
 public function test_unowned_reservation_blocks_reconciliation_without_writes():void {
  $actor=User::factory()->create(['is_admin'=>true]);
  $variant=Product::create(['title'=>'Unowned','handle'=>'unowned','base_price'=>100])->variants()->create(['sku'=>'UNOWNED','title'=>'One','price'=>100,'stock'=>10]);
  $id=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
  DB::table('inventory_balances')->where('id',$id)->update(['physical_stock'=>10,'counted_at'=>now()]);
  app(\App\Services\Inventory\StockAllocationService::class)->reserve('order',1,'item:1',$variant->id,2);
  $this->actingAs($actor)->getJson('/admin/api/inventory/balances/'.$id.'/obligations')->assertOk()
   ->assertJsonPath('unowned_reserved_stock',2)->assertJsonPath('derived_reserved_stock',0)
   ->assertJsonPath('balance.reserved_stock',null)->assertJsonPath('coverage_verified',false)
   ->assertJsonPath('pool_kind','shared')->assertJsonPath('warehouse_id',null);
  try{app(InventoryReconciliationService::class)->reconcile($id,0,1,2,'blocked','Review',$actor);$this->fail('Unowned obligation accepted');}
  catch(\Illuminate\Validation\ValidationException $error){$this->assertStringContainsString('ownership',$error->getMessage());}
  $this->assertDatabaseCount('inventory_reconciliations',0);
  $this->assertDatabaseHas('inventory_balances',['id'=>$id,'reserved_stock'=>null,'safety_stock'=>null,'version'=>0]);
  $allocation=(int)DB::table('stock_allocations')->value('id');
  $ownership=app(\App\Services\Inventory\InventoryAllocationOwnershipService::class);
  $receipt=$ownership->assign($allocation,$id,0,'Reviewed original reservation',$actor);
  $this->assertSame($receipt,$ownership->assign($allocation,$id,0,'Reviewed original reservation',$actor));
  $result=app(InventoryReconciliationService::class)->reconcile($id,1,1,2,'assigned','Reviewed ownership',$actor);
  $this->assertSame(2,$result['reserved_stock']);$this->assertSame(6,$result['atp']);
  $this->assertSame(8,(int)$variant->fresh()->stock);
  $this->assertDatabaseCount('inventory_movements',1);
  $this->assertDatabaseCount('inventory_allocation_ownership_changes',1);
 }
 public function test_counted_balance_reconciles_server_reservations_and_retries_once():void {
  $actor=User::factory()->create(['is_admin'=>true]);
  $variant=Product::create(['title'=>'Reconcile','handle'=>'reconcile','base_price'=>100])->variants()->create(['sku'=>'REC','title'=>'One','price'=>100,'stock'=>97]);
  $id=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
  DB::table('inventory_balances')->where('id',$id)->update(['physical_stock'=>10,'counted_at'=>now()]);
  $service=app(InventoryReconciliationService::class);
  $first=$service->reconcile($id,0,0,2,'review-one','Initial safety buffer',$actor);
  $this->assertSame(8,$first['atp']);$this->assertSame(0,$first['reserved_stock']);
  $this->assertSame($first,$service->reconcile($id,0,0,2,'review-one','Initial safety buffer',$actor));
  $this->assertDatabaseCount('inventory_reconciliations',1);
  $this->assertSame(97,(int)$variant->fresh()->stock);
 }
}
