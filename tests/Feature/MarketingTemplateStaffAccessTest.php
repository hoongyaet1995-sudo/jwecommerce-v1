<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Product,MarketingProject};
class MarketingTemplateStaffAccessTest extends TestCase{
 use RefreshDatabase;
 private function staff(array $grants):User{$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);foreach($grants as $p)$u->permissions()->create(['permission'=>$p]);return $u;}
 public function test_authorized_staff_can_read_single_shop_catalog_but_not_other_users_projects():void{
  $a=$this->staff(['marketing.view']);$b=$this->staff(['marketing.view']);$product=Product::create(['title'=>'Shared shop item','handle'=>'shared-shop-item','base_price'=>1,'status'=>'active']);
  $p=MarketingProject::create(['owner_id'=>$a->id,'source_type'=>'product','product_id'=>$product->id,'name'=>'Private project','snapshot'=>[],'brief'=>[],'template_id'=>'product_showcase_15s']);
  $this->actingAs($a)->get('/admin/marketing/templates')->assertOk();$this->getJson('/admin/marketing/templates/products?q=Shared')->assertOk()->assertJsonCount(1,'data');
  $this->actingAs($b)->getJson('/admin/marketing/templates/projects/'.$p->id)->assertNotFound();$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertNotFound();
 }
 public function test_view_only_staff_cannot_create_edit_or_approve_costs():void{
  $this->actingAs($this->staff(['marketing.view']));$this->postJson('/admin/marketing/templates/projects',[])->assertForbidden();$this->postJson('/admin/marketing/templates/projects/'.fake()->uuid().'/planning-authorize',[])->assertForbidden();
 }
 public function test_permissions_do_not_open_legacy_paid_actions_or_allow_customer_access():void{
  $this->actingAs($this->staff(['marketing.view','marketing.edit','marketing.approve_cost']))->postJson('/admin/marketing/workspace/projects',[])->assertForbidden();
  $u=User::factory()->create(['is_admin'=>false,'is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$this->actingAs($u)->getJson('/admin/marketing/templates/products')->assertUnauthorized();
 }
}
