<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class InventoryObligationReadTest extends TestCase
{
 use RefreshDatabase;
 public function test_shared_review_preserves_website_statuses_and_only_reads_stock():void
 {
  $p=Product::create(['title'=>'Review','handle'=>'review','base_price'=>100]);
  $v=$p->variants()->create(['sku'=>'REVIEW','title'=>'One','price'=>100,'stock'=>8]);
  foreach(['pending_payment'=>2,'paid'=>3,'shipped'=>4,'cancelled'=>5,'future_status'=>6] as $status=>$quantity){
   $id=DB::table('orders')->insertGetId(['number'=>'REVIEW-'.$status,'email'=>'private@example.test','status'=>$status,'subtotal'=>100,'total'=>100]);
   DB::table('order_items')->insert(['order_id'=>$id,'product_variant_id'=>$v->id,'sku'=>'REVIEW','title'=>'One','unit_price'=>100,'quantity'=>$quantity,'line_total'=>100*$quantity]);
  }
  $url='/admin/api/inventory/obligations?variantId='.$v->id;
  $this->getJson($url)->assertUnauthorized();
  $result=$this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson($url)->assertOk()
   ->assertJsonPath('physical_stock',null)->assertJsonPath('review_required',true)->assertJsonPath('coverage_verified',false)
   ->assertJsonPath('legacy_available_stock',8)->assertJsonCount(5,'website_orders')->assertJsonCount(0,'marketplace_allocations');
  $byStatus=collect($result->json('website_orders'))->keyBy('status');
  $this->assertSame(2,$byStatus['pending_payment']['quantity']);
  $this->assertSame(6,$byStatus['future_status']['quantity']);
  $this->assertStringNotContainsString('private@example.test',$result->getContent());
  $this->assertSame(8,(int)$v->fresh()->stock);
  $this->assertDatabaseCount('inventory_movements',0);
 }
}
