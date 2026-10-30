<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
class MarketingContentTemplatesTest extends TestCase {
 use RefreshDatabase;
 private function owner():User{$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);Http::preventStrayRequests();Queue::fake();return $u;}
 private function draft(array $more=[]):array{return array_replace(['version'=>0,'channel'=>'email','name'=>'Welcome newsletter','language'=>'en','body'=>'Hello, discover our collection.','subject'=>'Welcome','format'=>'image','assets'=>[]],$more);}
 public function test_draft_persists_and_stale_version_is_rejected_without_sending():void {$this->owner();$r=$this->postJson('/admin/marketing/templates/data',$this->draft())->assertCreated()->assertJsonPath('template.state','Draft');$id=$r->json('template.id');$this->getJson('/admin/marketing/templates/data')->assertOk()->assertJsonPath('templates.0.id',$id);$this->postJson('/admin/marketing/templates/data/'.$id,$this->draft())->assertStatus(409);Http::assertNothingSent();Queue::assertNothingPushed();}
 public function test_other_owner_cannot_read_or_update_template():void {$this->owner();$id=$this->postJson('/admin/marketing/templates/data',$this->draft())->json('template.id');$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->postJson('/admin/marketing/templates/data/'.$id,$this->draft(['version'=>1]))->assertNotFound();$this->getJson('/admin/marketing/templates/data')->assertJsonCount(0,'templates');}
 public function test_view_only_employee_cannot_save():void {$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$this->actingAs($u);$this->postJson('/admin/marketing/templates/data',$this->draft())->assertForbidden();}
 public function test_whatsapp_requires_real_owned_account_and_variable_examples():void {$this->owner();$this->postJson('/admin/marketing/templates/data',$this->draft(['channel'=>'whatsapp','name'=>'new_collection','body'=>'Explore {{2}}','account_id'=>99999]))->assertUnprocessable();Http::assertNothingSent();}

 private function wa():array {
  config(['services.whatsapp.app_id'=>'123']);$a=\App\Models\ManagedSocialAccount::create(['merchant_key'=>app(\App\Services\Marketing\MarketingPosts::class)->merchant(),'platform'=>'whatsapp','provider_account_id'=>'456','waba_id'=>'789','app_id'=>'123','name'=>'Test store','access_token'=>'PRIVATE-TOKEN','permissions'=>['whatsapp_business_management','whatsapp_business_messaging'],'tasks'=>[],'state'=>'connected','authorized_by'=>auth()->id()]);
  return $this->draft(['channel'=>'whatsapp','name'=>'new_collection','account_id'=>$a->id,'body'=>'Explore {{1}}.','examples'=>['our collection']]);
 }
 public function test_whatsapp_draft_variables_and_unknown_cost_submission_gate():void {$this->owner();$d=$this->wa();$this->postJson('/admin/marketing/templates/data',array_replace($d,['body'=>'Explore {{2}}']))->assertUnprocessable();$id=$this->postJson('/admin/marketing/templates/data',$d)->assertCreated()->json('template.id');$this->postJson('/admin/marketing/templates/'.$id.'/submit',['version'=>1])->assertStatus(409);$this->assertDatabaseHas('marketing_content_templates',['id'=>$id,'state'=>'Draft']);Http::assertNothingSent();}
 public function test_template_submission_is_explicit_and_unknown_result_cannot_repeat():void {$this->owner();$d=$this->wa();config(['marketing.whatsapp_template_submission_enabled'=>true]);$id=$this->postJson('/admin/marketing/templates/data',$d)->json('template.id');Http::fake(['*'=>Http::response(['error'=>['message'=>'PRIVATE-TOKEN diagnostic']],400)]);$this->postJson('/admin/marketing/templates/'.$id.'/submit',['version'=>1])->assertStatus(409)->assertDontSee('PRIVATE-TOKEN');$this->assertDatabaseHas('marketing_content_templates',['id'=>$id,'state'=>'Status check required']);$this->postJson('/admin/marketing/templates/'.$id.'/submit',['version'=>3])->assertStatus(409);Http::assertSentCount(1);Queue::assertNothingPushed();}
 public function test_approval_checks_name_language_and_remote_content():void {$this->owner();$d=$this->wa();$id=$this->postJson('/admin/marketing/templates/data',$d)->json('template.id');Http::fake(['*'=>Http::response(['data'=>[['id'=>'321','name'=>'new_collection','language'=>'en','category'=>'MARKETING','status'=>'APPROVED','components'=>[['type'=>'BODY','text'=>'Different message']]]]])]);$this->postJson('/admin/marketing/templates/'.$id.'/sync',['version'=>1])->assertOk()->assertJsonPath('template.state','Status check required');Http::assertSentCount(1);}
 public function test_post_template_reuses_real_editor_as_draft_only():void {$this->owner();$id=$this->postJson('/admin/marketing/templates/data',$this->draft(['channel'=>'post']))->json('template.id');$this->postJson('/admin/marketing/templates/'.$id.'/use-post',['version'=>1])->assertOk()->assertJsonPath('post.status','Draft')->assertJsonPath('post.copy','Hello, discover our collection.');$this->assertDatabaseCount('marketing_posts',1);Http::assertNothingSent();Queue::assertNothingPushed();}
 public function test_formal_template_page_loads_owned_ui():void {$this->owner();$this->get('/admin/marketing/campaigns/templates')->assertOk()->assertSee('templates.js')->assertSee('campaign-navigation.js')->assertDontSee('Sample data');Http::assertNothingSent();}

 public function test_campaign_preparation_uses_real_opt_in_counts_and_blocks_unknown_price():void {
  $u=$this->owner();$t=$this->postJson('/admin/marketing/templates/data',$this->wa())->json('template.id');
  $c=\App\Models\MarketingCampaign::create(['merchant_key'=>app(\App\Services\Marketing\MarketingPosts::class)->merchant(),'user_id'=>$u->id,'name'=>'Local acceptance','channel'=>'whatsapp','objective'=>'Product introduction','status'=>'Draft','starts_on'=>'2026-10-29','ends_on'=>'2026-11-01','timezone'=>'Asia/Kuala_Lumpur','destination_url'=>'https://example.com','cta'=>'Learn more','version'=>1]);
  \App\Models\MarketingContentTemplate::whereKey($t)->update(['state'=>'Approved','provider_id'=>'321']);$merchant=$c->merchant_key;foreach([['a','subscribed',null],['b','unsubscribed',now()]] as [$hash,$status,$revoked])\Illuminate\Support\Facades\DB::table('marketing_whatsapp_subscriptions')->insert(['id'=>(string)\Illuminate\Support\Str::uuid(),'merchant_key'=>$merchant,'phone_hash'=>hash('sha256',$hash),'encrypted_phone'=>\Illuminate\Support\Facades\Crypt::encryptString('+60123456789'),'status'=>$status,'source'=>'explicit_test_opt_in','consented_at'=>now(),'revoked_at'=>$revoked,'created_at'=>now(),'updated_at'=>now()]);
  $r=$this->postJson('/admin/marketing/templates/messages',['version'=>0,'campaign_id'=>$c->id,'template_id'=>$t,'values'=>['new arrivals'],'timezone'=>'Asia/Kuala_Lumpur'])->assertCreated();$id=$r->json('draft.id');
  $this->getJson('/admin/marketing/templates/messages/'.$id.'/review')->assertOk()->assertJsonPath('eligible',1)->assertJsonPath('excluded',1)->assertJsonPath('cost',null)->assertJsonPath('can_confirm',false)->assertJsonPath('body','Explore new arrivals.');
  $this->assertDatabaseCount('marketing_whatsapp_drafts',1);Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_successful_provider_submission_remains_pending_and_full_revision_must_match():void {
  $this->owner();$d=$this->wa();config(['marketing.whatsapp_template_submission_enabled'=>true]);$id=$this->postJson('/admin/marketing/templates/data',$d)->json('template.id');Http::fake(['*'=>Http::response(['id'=>'321','status'=>'APPROVED'])]);
  $this->postJson('/admin/marketing/templates/'.$id.'/submit',['version'=>1])->assertOk()->assertJsonPath('template.state','Pending approval');
  Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(['*'=>Http::response(['data'=>[['id'=>'321','name'=>'new_collection','language'=>'en','category'=>'MARKETING','status'=>'APPROVED','components'=>[['type'=>'BODY','text'=>'Explore {{1}}'],['type'=>'FOOTER','text'=>'Changed footer']]]]])]);
  $this->postJson('/admin/marketing/templates/'.$id.'/sync',['version'=>3])->assertOk()->assertJsonPath('template.state','Status check required');
 }

 public function test_templates_can_be_prepared_when_video_renderer_is_disabled():void {
  $this->owner();config(['marketing.execution_enabled'=>false]);$id=$this->postJson('/admin/marketing/templates/data',$this->draft())->assertCreated()->json('template.id');$this->postJson('/admin/marketing/templates/'.$id.'/duplicate',['version'=>1])->assertOk()->assertJsonPath('template.state','Draft');Http::assertNothingSent();Queue::assertNothingPushed();
 }

 public function test_crm_contract_preserves_category_header_buttons_and_provider_name():void {
  $this->owner();$d=$this->wa();$d=array_replace($d,['name'=>'Order ready','meta_name'=>'order_ready','category'=>'UTILITY','header_type'=>'TEXT','header_text'=>'Order update','header_examples'=>[],'body'=>'Your order {{1}} is ready.','examples'=>['AB123'],'buttons'=>[['type'=>'URL','text'=>'View order','value'=>'https://example.com/orders/{{1}}','example'=>'AB123'],['type'=>'PHONE_NUMBER','text'=>'Call us','value'=>'+60123456789','example'=>'']]]);
  $r=$this->postJson('/admin/marketing/templates/data',$d)->assertCreated()->assertJsonPath('template.category','UTILITY')->assertJsonPath('template.document.meta_name','order_ready')->assertJsonPath('template.document.header_text','Order update')->assertJsonCount(2,'template.document.buttons');$id=$r->json('template.id');config(['marketing.whatsapp_template_submission_enabled'=>true]);Http::fake(['*'=>Http::response(['id'=>'321'])]);$this->postJson('/admin/marketing/templates/'.$id.'/submit',['version'=>1])->assertOk();Http::assertSent(fn($r)=>$r['name']==='order_ready'&&$r['category']==='UTILITY'&&$r['components'][0]['type']==='HEADER'&&$r['components'][2]['buttons'][0]['example']===['https://example.com/orders/AB123']);
 }
 public function test_crm_contract_rejects_bad_header_and_more_than_three_buttons():void {
  $this->owner();$d=$this->wa();$this->postJson('/admin/marketing/templates/data',array_replace($d,['header_type'=>'TEXT','header_text'=>'Hi {{1}} {{2}}','header_examples'=>['A','B']]))->assertUnprocessable();$this->postJson('/admin/marketing/templates/data',array_replace($d,['buttons'=>array_fill(0,4,['type'=>'QUICK_REPLY','text'=>'Yes','value'=>'','example'=>''])]))->assertUnprocessable();Http::assertNothingSent();
 }

 public function test_unapproved_template_cannot_be_used_for_campaign_preparation():void {
  $u=$this->owner();$t=$this->postJson('/admin/marketing/templates/data',$this->wa())->json('template.id');$c=\App\Models\MarketingCampaign::create(['merchant_key'=>app(\App\Services\Marketing\MarketingPosts::class)->merchant(),'user_id'=>$u->id,'name'=>'Test','objective'=>'Product introduction','status'=>'Draft','starts_on'=>'2026-10-29','ends_on'=>'2026-11-01','timezone'=>'Asia/Kuala_Lumpur','destination_url'=>'https://example.com','cta'=>'Learn more','version'=>1]);
  $this->postJson('/admin/marketing/templates/messages',['version'=>0,'campaign_id'=>$c->id,'template_id'=>$t,'values'=>['Collection'],'timezone'=>'Asia/Kuala_Lumpur'])->assertUnprocessable();$this->assertDatabaseCount('marketing_whatsapp_drafts',0);Http::assertNothingSent();Queue::assertNothingPushed();
 }

 public function test_archive_is_owned_versioned_and_preserves_provider_state():void {
  $this->owner();$id=$this->postJson('/admin/marketing/templates/data',$this->wa())->json('template.id');\App\Models\MarketingContentTemplate::whereKey($id)->update(['state'=>'Approved','provider_id'=>'321']);
  $this->postJson('/admin/marketing/templates/'.$id.'/archive',['version'=>1])->assertOk()->assertJsonPath('template.archived',true)->assertJsonPath('template.state','Approved');$this->assertDatabaseHas('marketing_content_templates',['id'=>$id,'provider_id'=>'321','state'=>'Approved']);$this->postJson('/admin/marketing/templates/'.$id.'/restore',['version'=>1])->assertStatus(409);$this->postJson('/admin/marketing/templates/'.$id.'/restore',['version'=>2])->assertOk()->assertJsonPath('template.archived',false);Http::assertNothingSent();Queue::assertNothingPushed();
 }

 public function test_archive_is_private_and_duplicate_makes_an_unapproved_active_copy():void {
  $u=$this->owner();$id=$this->postJson('/admin/marketing/templates/data',$this->wa())->json('template.id');$this->postJson('/admin/marketing/templates/'.$id.'/archive',['version'=>1])->assertOk();
  $copy=$this->postJson('/admin/marketing/templates/'.$id.'/duplicate',['version'=>2])->assertOk()->assertJsonPath('template.archived',false)->assertJsonPath('template.state','Draft')->json('template.id');$this->assertNotSame($id,$copy);
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->postJson('/admin/marketing/templates/'.$id.'/restore',['version'=>2])->assertNotFound();Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_removed_header_variable_discards_stale_example_before_submission():void {
  $this->owner();$d=$this->wa();config(['marketing.whatsapp_template_submission_enabled'=>true]);
  $d=array_replace($d,['header_type'=>'TEXT','header_text'=>'A birthday voucher for you','header_examples'=>['']]);
  $id=$this->postJson('/admin/marketing/templates/data',$d)->assertCreated()->json('template.id');
  Http::fake(['*'=>Http::response(['id'=>'987'])]);
  $this->postJson('/admin/marketing/templates/'.$id.'/submit',['version'=>1])->assertOk()->assertJsonPath('template.state','Pending approval');
  Http::assertSent(fn($r)=>!isset($r['components'][0]['example'])&&$r['components'][1]['example']['body_text'][0]===['our collection']);
  Http::assertSentCount(1);Queue::assertNothingPushed();
 } public function test_delete_is_versioned_scoped_and_retains_the_record_without_provider_calls():void {$this->owner();$id=$this->postJson('/admin/marketing/templates/data',$this->draft())->json('template.id');$this->postJson('/admin/marketing/templates/'.$id.'/delete',['version'=>9])->assertStatus(409);$this->postJson('/admin/marketing/templates/'.$id.'/delete',['version'=>1])->assertOk()->assertJsonPath('template.deleted',true);$this->getJson('/admin/marketing/templates/data')->assertJsonCount(0,'templates');$this->assertDatabaseHas('marketing_content_templates',['id'=>$id]);$this->postJson('/admin/marketing/templates/'.$id.'/restore',['version'=>2])->assertNotFound();Http::assertNothingSent();Queue::assertNothingPushed();}
}
