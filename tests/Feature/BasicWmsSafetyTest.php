<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class BasicWmsSafetyTest extends TestCase {
 use RefreshDatabase;
 public function test_safety_endpoint_enforces_gate_and_server_actor():void {
  $this->postJson('/admin/api/inventory/basic/safety',[])->assertUnauthorized();
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  config(['marketplace.basic_wms_enabled'=>false]);
  $this->postJson('/admin/api/inventory/basic/safety',[])->assertForbidden();
  config(['marketplace.basic_wms_enabled'=>true]);
  $this->postJson('/admin/api/inventory/basic/safety',['actor_id'=>999])->assertUnprocessable();
  $this->assertDatabaseCount('basic_wms_safety_events',0);
 }
 public function test_safety_setting_is_audited_and_does_not_reconcile_or_take_over_stock():void {
  config(['marketplace.basic_wms_enabled'=>true]);
  $actor=User::factory()->create(['is_admin'=>true]);
  $variant=Product::create(['title'=>'Safety','handle'=>'safety','base_price'=>10])->variants()->create(['sku'=>'SAFETY','title'=>'One','price'=>10,'stock'=>17]);
  $id=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
  DB::table('inventory_balances')->where('id',$id)->update(['physical_stock'=>12,'counted_at'=>now()]);
  $key=(string)Str::uuid();$service=app(\App\Services\Inventory\BasicWmsSafetyService::class);
  $receipt=$service->set($id,0,2,'Initial safety',$key,$actor);
  $this->assertSame(10,$receipt['basic_wms']['available']);
  $this->assertSame($receipt,$service->set($id,0,2,'Initial safety',$key,$actor));
  $this->assertDatabaseHas('inventory_balances',['id'=>$id,'physical_stock'=>12,'safety_stock'=>2,'reserved_stock'=>null,'obligations_reconciled_at'=>null,'version'=>1]);
  $this->assertSame(17,(int)$variant->fresh()->stock);
  $this->assertDatabaseCount('basic_wms_safety_events',1);
  $this->actingAs($actor)->postJson('/admin/api/inventory/basic/safety',[
   'balance_id'=>$id,'balance_version'=>1,'safety_stock'=>3,'operation_key'=>(string)Str::uuid(),'reason'=>'Reviewed safety',
  ])->assertOk()->assertJsonPath('safety_stock',3)->assertJsonPath('actor_id',$actor->id)->assertJsonPath('basic_wms.available',9);
  $this->getJson('/admin/api/inventory/basic/safety-history?variantId='.$variant->id)
   ->assertOk()->assertJsonPath('total',2)->assertJsonPath('data.0.product_variant_id',$variant->id);
  $other=Product::create(['title'=>'Other','handle'=>'other','base_price'=>10])->variants()->create(['sku'=>'OTHER','title'=>'One','price'=>10,'stock'=>0]);
  $this->getJson('/admin/api/inventory/basic/safety-history?variantId='.$other->id)->assertOk()->assertJsonPath('total',0);
 }
}
