<?php
namespace Tests\Feature;

use App\Models\{User,Product,ProductImage,MarketingProject};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingTemplateIntegrationTest extends TestCase
{
 use RefreshDatabase;
 private function admin():User{return User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);}
 private function product():Product{return Product::create(['title'=>'Template test tea','handle'=>'template-test-tea','description'=>'A tea product','base_price'=>10,'status'=>'active']);}
 private function input(Product $p):array{return ['request_id'=>(string)Str::uuid(),'product_id'=>$p->id,'template_id'=>'product_showcase_15s','language'=>'zh-MY','formats'=>['landscape','portrait'],'narration'=>true,'subtitles'=>true,'goal'=>'introduction','request'=>'介绍外观','duration'=>'15'];}
 public function test_catalog_is_bounded_searchable_and_requires_existing_admin_permission():void{
  $u=$this->admin();for($i=0;$i<26;$i++)Product::create(['title'=>'Query tea '.$i,'handle'=>'query-tea-'.$i,'base_price'=>1,'status'=>'active']);
  $this->actingAs($u)->getJson('/admin/marketing/templates/products?q=Query&limit=999')->assertOk()->assertJsonCount(20,'data')->assertJsonPath('meta.has_more',true);
  $chosen=Product::firstOrFail();$this->get('/admin/marketing/templates?product_id='.$chosen->id)->assertOk()->assertSee('selected_product',false)->assertSee($chosen->title,false);$this->assertDatabaseCount('marketing_projects',0);
  $this->getJson('/admin/marketing/templates/products?q=no-such-product')->assertOk()->assertJsonCount(0,'data');
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]))->getJson('/admin/marketing/templates/products')->assertForbidden();
 }
 public function test_template_creation_is_atomic_idempotent_and_does_not_change_legacy_project():void{
  Queue::fake();$u=$this->admin();$p=$this->product();$data=$this->input($p);
  $first=$this->actingAs($u)->postJson('/admin/marketing/templates/projects',$data)->assertCreated()->json('project.id');
  $this->postJson('/admin/marketing/templates/projects',$data)->assertOk()->assertJsonPath('project.id',$first);
  $stored=MarketingProject::findOrFail($first);$this->assertSame('product_showcase_15s',$stored->template_id);$this->assertSame('zh-MY',$stored->preferences['language']);$this->assertSame('15',$stored->preferences['duration']);
  $this->assertDatabaseCount('marketing_projects',1);Queue::assertNothingPushed();
  $data['language']='English';$this->postJson('/admin/marketing/templates/projects',$data)->assertStatus(409);
  $this->get('/admin/marketing/workspace?project='.$first.'&screen=guided')->assertOk()->assertSee('template-workflow.js?v=',false)->assertDontSee('本地交互小样');
 }
 public function test_templates_reject_unsupported_language_duration_and_forged_authority():void{
  $u=$this->admin();$p=$this->product();$this->actingAs($u);
  foreach([['language'=>'ms-MY'],['duration'=>'30'],['formats'=>[]],['authorized'=>true],['template_id'=>'arbitrary'],['narration'=>false,'subtitles'=>true]] as $change){$this->postJson('/admin/marketing/templates/projects',array_replace($this->input($p),$change))->assertStatus(422);}
  $this->assertDatabaseCount('marketing_projects',0);
 }
 public function test_project_status_is_owner_scoped_and_unknown_project_is_not_created():void{
  $u=$this->admin();$p=$this->product();$id=$this->actingAs($u)->postJson('/admin/marketing/templates/projects',$this->input($p))->assertCreated()->json('project.id');
  $this->actingAs($this->admin())->getJson('/admin/marketing/templates/projects/'.$id)->assertNotFound();
 }
 public function test_large_catalog_lookup_reaches_items_beyond_legacy_first_hundred():void{
  $u=$this->admin();$rows=[];for($i=0;$i<10000;$i++)$rows[]=['title'=>'Catalog scale item '.$i,'handle'=>'scale-'.$i,'base_price'=>1,'status'=>'active'];foreach(array_chunk($rows,1000) as $chunk)Product::insert($chunk);
  $this->actingAs($u)->getJson('/admin/marketing/templates/products?q=Catalog%20scale%20item%209999')->assertOk()->assertJsonPath('data.0.name','Catalog scale item 9999');
  $this->getJson('/admin/marketing/templates/products?q=Catalog&limit=9999')->assertOk()->assertJsonCount(20,'data')->assertJsonPath('meta.has_more',true);
  $this->get('/admin/marketing/workspace')->assertOk()->assertSee('ws-product-search',false);
 }
}
