<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,Product,AddonDeal};
use Illuminate\Foundation\Testing\RefreshDatabase;
class AddonDealAdminTest extends TestCase {
 use RefreshDatabase;
 private function payload():array {$a=Product::create(['title'=>'Main','handle'=>'addon-main','status'=>'active','purchase_mode'=>'buy','base_price'=>10000]);$b=Product::create(['title'=>'Extra','handle'=>'addon-extra','status'=>'active','purchase_mode'=>'buy','base_price'=>2000]);return ['name'=>'Workspace extras','per_order_cap'=>2,'product_ids'=>[$a->id],'addons'=>[['product_id'=>$b->id,'price'=>1000]]];}
 public function test_admin_can_save_reload_and_edit_without_activating_a_storefront_offer():void {$this->actingAs(User::factory()->create(['is_admin'=>true]));$d=$this->payload();$id=$this->postJson('/admin/api/addon-deals',$d)->assertCreated()->json('data.id');$this->getJson('/admin/api/addon-deals')->assertOk()->assertJsonPath('data.0.addons.0.name','Extra')->assertJsonPath('data.0.per_order_cap',2);$this->patchJson('/admin/api/addon-deals/'.$id,[...$d,'per_order_cap'=>3])->assertOk()->assertJsonPath('data.per_order_cap',3);$this->assertDatabaseCount('promotion_offers',0);}
 public function test_overlapping_products_and_bad_prices_are_rejected():void {$this->actingAs(User::factory()->create(['is_admin'=>true]));$d=$this->payload();$this->postJson('/admin/api/addon-deals',[...$d,'addons'=>[['product_id'=>$d['product_ids'][0],'price'=>1000]]])->assertUnprocessable();$this->postJson('/admin/api/addon-deals',[...$d,'addons'=>[['product_id'=>$d['addons'][0]['product_id'],'price'=>0]]])->assertUnprocessable();$this->assertDatabaseCount('addon_deals',0);}
 public function test_non_admin_cannot_write_settings():void {$this->actingAs(User::factory()->create(['is_admin'=>false]))->postJson('/admin/api/addon-deals',[])->assertUnauthorized();}
}
