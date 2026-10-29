<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\User;
class MarketingEmailSmsTemplatesTest extends TestCase {
 use RefreshDatabase;
 private function actor(){Http::preventStrayRequests();$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));}
 public function test_catalog_and_editor_are_private_and_contain_source_builder(){
  $this->actor();$this->get('/admin/marketing/campaigns/templates/email')->assertOk()->assertSee('JW Studio Template Library')->assertSee('Seasonal Promotion');
  $this->get('/admin/marketing/campaigns/templates/email/editor')->assertOk()->assertSee('JW Studio Email Builder')->assertSee('xenibox-email-builder');Http::assertNothingSent();
 }
 public function test_email_round_trip_is_versioned_and_never_sends(){
  $this->actor();$doc=['root'=>['type'=>'EmailLayout','data'=>['childrenIds'=>['text']]],'text'=>['type'=>'Text','data'=>['props'=>['text'=>'Hello {{first_name}}']]]];
  $input=['version'=>0,'template_name'=>'Acceptance email','status'=>'active','subject'=>'Hello','preheader'=>'A preview','editor_json'=>json_encode($doc),'content_html'=>'<p>Hello {{first_name}}</p><script>alert(1)</script>'];
  $r=$this->postJson('/admin/marketing/campaigns/templates/email/save',$input)->assertCreated()->assertJsonPath('template.state','Active');$t=$r->json('template');
  $this->assertSame($doc,$t['document']['editor_document']);$this->assertStringNotContainsString('<script',$t['document']['content_html']);
  $this->get('/admin/marketing/campaigns/templates/email/editor/'.$t['id'])->assertOk()->assertSee('Acceptance email');
  $input['version']=1;$this->postJson('/admin/marketing/campaigns/templates/email/save/'.$t['id'],$input)->assertOk()->assertJsonPath('template.version',2);
  $this->postJson('/admin/marketing/campaigns/templates/email/save/'.$t['id'],$input)->assertStatus(409);
  $this->actor();$this->get('/admin/marketing/campaigns/templates/email/editor/'.$t['id'])->assertNotFound();Http::assertNothingSent();
 }
 public function test_sms_round_trip_and_encoding_estimate(){
  $this->actor();$this->get('/admin/marketing/campaigns/templates/sms')->assertOk()->assertSee('SMS Message');
  $r=$this->postJson('/admin/marketing/campaigns/templates/sms/save',['version'=>0,'template_name'=>'SMS acceptance','status'=>'draft','sms_message'=>str_repeat('A',161)])->assertCreated();
  $this->assertSame(2,$r->json('template.document.sms_analysis.estimated_credits'));Http::assertNothingSent();
 }
 public function test_sms_encoding_boundaries_include_extension_and_surrogate_units(){
  $s=new \App\Services\Marketing\Email\SmsMessageService();
  $this->assertSame(2,$s->analyse(str_repeat('^',81))['estimated_credits']);
  $this->assertSame(1,$s->analyse(str_repeat('é',160))['estimated_credits']);
  $this->assertSame(3,$s->analyse(str_repeat('中',135))['estimated_credits']);
  $this->assertSame(2,$s->analyse(str_repeat('😀',36))['estimated_credits']);
 }
 public function test_html_form_string_version_saves_and_unsafe_document_is_rejected(){
  $this->actor();$doc=['root'=>['type'=>'EmailLayout','data'=>['childrenIds'=>[]]]];
  $d=['version'=>'0','template_name'=>'Form save','status'=>'draft','subject'=>'Subject','editor_json'=>json_encode($doc),'content_html'=>'<p>Saved</p>'];
  $this->post('/admin/marketing/campaigns/templates/email/save',$d)->assertRedirect();
  $doc['bad']=['type'=>'Button','data'=>['props'=>['url'=>'javascript:alert(1)']]];$d['editor_json']=json_encode($doc);
  $this->postJson('/admin/marketing/campaigns/templates/email/save',$d)->assertUnprocessable();$doc['bad']=['type'=>'Html','data'=>['props'=>['contents'=>'<img src=x onclick=alert(1)>']]];$d['editor_json']=json_encode($doc);$this->postJson('/admin/marketing/campaigns/templates/email/save',$d)->assertUnprocessable();Http::assertNothingSent();
 }
 public function test_email_duplicate_archive_and_history_preserve_original_design(){
  $this->actor();$doc=['root'=>['type'=>'EmailLayout','data'=>['childrenIds'=>[]]]];$d=['version'=>0,'template_name'=>'Preserve design','status'=>'draft','subject'=>'Subject','editor_json'=>json_encode($doc),'content_html'=>'<p>Original</p>'];
  $t=$this->postJson('/admin/marketing/campaigns/templates/email/save',$d)->assertCreated()->json('template');
  $d['version']=1;$d['content_html']='<p>Revision</p>';
  $this->postJson('/admin/marketing/campaigns/templates/email/save/'.$t['id'],$d)->assertOk()->assertJsonPath('template.document.previous_versions.0.document.content_text','Original');
  $copy=$this->postJson('/admin/marketing/templates/'.$t['id'].'/duplicate',['version'=>2])->assertOk()->json('template');
  $this->assertNotSame($t['id'],$copy['id']);$this->assertSame('Draft',$copy['state']);
  $this->postJson('/admin/marketing/templates/'.$t['id'].'/archive',['version'=>2])->assertOk();
  $this->get('/admin/marketing/campaigns/templates/email/editor/'.$copy['id'])->assertOk();
  $this->get('/admin/marketing/campaigns/templates/email/editor/'.$t['id'])->assertStatus(409);Http::assertNothingSent();
 }
 public function test_view_only_cannot_edit_and_foreign_uploads_are_not_listed(){
  $this->actor();\Illuminate\Support\Facades\Storage::fake('public');$u=auth()->id();
  $this->getJson('/admin/marketing/campaigns/templates/email/media')->assertOk()->assertJsonPath('pagination.total_rows',0);
  $this->postJson('/admin/marketing/campaigns/templates/email/media',['image'=>\Illuminate\Http\UploadedFile::fake()->image('local-test.jpg',20,20)])->assertOk()->assertJsonPath('success',true);$this->getJson('/admin/marketing/campaigns/templates/email/media')->assertJsonPath('pagination.total_rows',1);$this->actor();$this->getJson('/admin/marketing/campaigns/templates/email/media')->assertJsonPath('pagination.total_rows',0);
  $viewer=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$viewer->permissions()->create(['permission'=>'marketing.view']);$this->actingAs($viewer);
  $this->get('/admin/marketing/campaigns/templates/email')->assertOk();$this->get('/admin/marketing/campaigns/templates/email/editor')->assertForbidden();
  $this->postJson('/admin/marketing/campaigns/templates/sms/save',[])->assertForbidden();Http::assertNothingSent();
 }
}
