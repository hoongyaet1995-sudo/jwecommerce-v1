<?php
namespace Tests\Feature;

use App\Models\{User,Product,ProductImage,MarketingProject,MarketingTemplateGrant};
use App\Services\Marketing\{MarketingTemplateProjects,MarketingTemplateContract};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingTemplatePlanningConsentTest extends TestCase
{
 use RefreshDatabase;
 private function project():MarketingProject{
  $this->mock(\App\Services\Marketing\MarketingTemplateRuntime::class,fn($m)=>$m->shouldReceive('ready')->andReturn(true));
  Http::preventStrayRequests();Queue::fake();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);
  $product=Product::create(['title'=>'Another merchant product','handle'=>'consent-headphones','base_price'=>10,'status'=>'active']);ProductImage::create(['product_id'=>$product->id,'path'=>'http://127.0.0.1:8500/images/themes/tech/headphones.png','position'=>0]);
  [$p]=app(MarketingTemplateProjects::class)->create($u,['request_id'=>(string)Str::uuid(),'product_id'=>$product->id,'template_id'=>'product_showcase_15s','language'=>'English','formats'=>['landscape','portrait'],'narration'=>true,'subtitles'=>true,'goal'=>'introduction','duration'=>'15']);return $p;
 }
 public function test_quote_for_another_product_is_free_and_does_not_authorize_or_queue():void{
  $p=$this->project();$r=$this->postJson('/admin/marketing/templates/projects/'.$p->id.'/planning-quote',['version'=>$p->version])->assertOk()->assertJsonPath('quote.planning_cap_microusd',14800)->assertJsonPath('quote.cap_microusd',62800);
  $this->assertNotEmpty($r->json('quote.token'));$this->assertDatabaseCount('marketing_template_grants',0);$this->assertDatabaseCount('marketing_template_calls',0);Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_asset_rights_must_be_explicit_before_creating_paid_authorization():void{
  $p=$this->project();$base='/admin/marketing/templates/projects/'.$p->id;$token=$this->postJson($base.'/planning-quote',['version'=>$p->version])->assertOk()->json('quote.token');
  $this->postJson($base.'/planning-authorize',['version'=>$p->version,'token'=>$token,'consent'=>true])->assertStatus(422);$this->assertDatabaseCount('marketing_template_grants',0);Http::assertNothingSent();
 }
 public function test_explicit_revision_consent_reuses_original_cap_and_is_idempotent():void{
  $p=$this->project();$base='/admin/marketing/templates/projects/'.$p->id;$q=$this->postJson($base.'/planning-quote',['version'=>$p->version])->json('quote');$this->postJson($base.'/planning-authorize',['version'=>$p->version,'token'=>$q['token'],'consent'=>true,'rights_confirmed'=>true])->assertOk();
  $g=MarketingTemplateGrant::firstOrFail();$g->update(['expires_at'=>now()->subMinute(),'policy'=>array_replace($g->policy,['corrections'=>['en'=>1]])]);
  $old=\App\Models\MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('a',64),'input'=>['project_version'=>$p->version],'draft'=>['copy'=>['hook'=>'Old']],'status'=>'paused']);
  $quote=$this->postJson($base.'/revision-quote',['version'=>$p->version,'run_id'=>$old->id,'request'=>'Write a shorter complete hook.'])->assertOk()->assertJsonPath('quote.planning_cap_microusd',7400)->json('quote');
  $body=['version'=>$p->version,'token'=>$quote['token'],'consent'=>true,'rights_confirmed'=>true];
  $this->mock(MarketingTemplateContract::class,fn($m)=>$m->shouldReceive('call')->with('packet',\Mockery::any())->once()->andReturn(['language'=>'en']));
  $this->postJson($base.'/revision-authorize',$body)->assertOk()->assertJsonPath('run.status','queued');$this->postJson($base.'/revision-authorize',$body)->assertOk();
  $this->assertSame(62800,$g->fresh()->cap_microusd);$this->assertSame(2,$g->fresh()->policy['corrections']['en']);$this->assertSame(['copy'=>['hook'=>'Old']],$old->fresh()->draft);$this->assertDatabaseCount('marketing_template_grants',1);$this->assertDatabaseCount('marketing_template_calls',0);Queue::assertPushed(\App\Jobs\MarketingTemplateJob::class,1);Http::assertNothingSent();
 }
 public function test_consent_is_explicit_bound_and_idempotent_and_local_image_url_is_supported():void{
  $p=$this->project();$base='/admin/marketing/templates/projects/'.$p->id;$q=$this->postJson($base.'/planning-quote',['version'=>$p->version])->assertOk()->json('quote');
  $this->postJson($base.'/planning-authorize',['version'=>$p->version,'token'=>$q['token'],'consent'=>false])->assertStatus(422);$this->assertDatabaseCount('marketing_template_grants',0);
  $d=['version'=>$p->version,'token'=>$q['token'],'consent'=>true,'rights_confirmed'=>true];$this->postJson($base.'/planning-authorize',$d)->assertOk()->assertJsonPath('grant.available',true);$this->postJson($base.'/planning-authorize',$d)->assertOk();$this->assertDatabaseCount('marketing_template_grants',1);Queue::assertNothingPushed();
  $g=MarketingTemplateGrant::firstOrFail();$this->assertSame(62800,$g->cap_microusd);$this->assertSame($p->id,$g->policy['projects']['en']);$this->assertSame(4,$g->max_llm);$this->assertSame(2,$g->max_tts);
  $this->mock(MarketingTemplateContract::class,fn($m)=>$m->shouldReceive('call')->with('packet',\Mockery::any())->once()->andReturn(['language'=>'en']));$this->postJson($base.'/plan',['version'=>$p->version,'request_id'=>(string)Str::uuid()])->assertOk();Queue::assertPushed(\App\Jobs\MarketingTemplateJob::class,1);$this->assertDatabaseCount('marketing_template_calls',0);Http::assertNothingSent();
 }
 public function test_changed_project_or_tampered_quote_cannot_authorize():void{
  $p=$this->project();$base='/admin/marketing/templates/projects/'.$p->id;$token=$this->postJson($base.'/planning-quote',['version'=>$p->version])->assertOk()->json('quote.token');
  $this->postJson($base.'/planning-authorize',['version'=>$p->version,'token'=>'tampered','consent'=>true,'rights_confirmed'=>true])->assertStatus(422);
  $p->update(['version'=>$p->version+1]);$this->postJson($base.'/planning-authorize',['version'=>$p->version,'token'=>$token,'consent'=>true,'rights_confirmed'=>true])->assertStatus(409);$this->assertDatabaseCount('marketing_template_grants',0);
 }
 public function test_quote_is_owner_scoped_and_client_cannot_set_price():void{
  $p=$this->project();$base='/admin/marketing/templates/projects/'.$p->id;$this->postJson($base.'/planning-quote',['version'=>$p->version,'cap_microusd'=>1])->assertStatus(422);
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]))->postJson($base.'/planning-quote',['version'=>$p->version])->assertNotFound();Http::assertNothingSent();
 }
 public function test_expired_quote_and_changed_catalog_cannot_authorize():void{
  $p=$this->project();$base='/admin/marketing/templates/projects/'.$p->id;$token=$this->postJson($base.'/planning-quote',['version'=>$p->version])->assertOk()->json('quote.token');
  $this->travel(21)->minutes();$this->postJson($base.'/planning-authorize',['version'=>$p->version,'token'=>$token,'consent'=>true,'rights_confirmed'=>true])->assertStatus(409);$this->travelBack();
  Product::findOrFail($p->product_id)->update(['description'=>'Updated product facts']);$this->postJson($base.'/planning-authorize',['version'=>$p->version,'token'=>$token,'consent'=>true,'rights_confirmed'=>true])->assertStatus(409);$this->assertDatabaseCount('marketing_template_grants',0);Http::assertNothingSent();
 }
 public function test_grant_for_one_project_is_not_reused_by_another_project_for_same_product():void{
  $p=$this->project();$base='/admin/marketing/templates/projects/'.$p->id;$token=$this->postJson($base.'/planning-quote',['version'=>$p->version])->assertOk()->json('quote.token');$this->postJson($base.'/planning-authorize',['version'=>$p->version,'token'=>$token,'consent'=>true,'rights_confirmed'=>true])->assertOk();
  $other=$p->replicate();$other->save();$this->getJson('/admin/marketing/templates/projects/'.$other->id)->assertOk()->assertJsonPath('grant.available',false)->assertJsonPath('grant.consent_required',true);$this->assertDatabaseCount('marketing_template_grants',1);
 }
 public function test_external_asset_or_stale_prices_fail_before_consent_or_transport():void{
  $p=$this->project();$base='/admin/marketing/templates/projects/'.$p->id;ProductImage::where('product_id',$p->product_id)->update(['path'=>'https://external.invalid/images/product.png']);$this->postJson($base.'/planning-quote',['version'=>$p->version])->assertStatus(422);
  $this->travel(4)->days();$this->postJson($base.'/planning-quote',['version'=>$p->version])->assertStatus(409);$this->travelBack();$this->assertDatabaseCount('marketing_template_grants',0);$this->assertDatabaseCount('marketing_template_calls',0);Http::assertNothingSent();Queue::assertNothingPushed();
 }
}
