<?php
namespace Tests\Feature;
use App\Models\{SupportConversation,User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class SupportDocumentMessagingTest extends SupportChatApiTest {
 // Seed a pre-existing document without reopening the removed storefront upload endpoint.
 private function legacyDocument(UploadedFile $file):array {
  $guest=(string)Str::uuid();$this->withSession(['support_guest_key'=>$guest]);
  $c=SupportConversation::create(['id'=>(string)Str::uuid(),'owner_hash'=>hash('sha256','guest:'.$guest),'status'=>'open','channel'=>'web_guest']);
  $a=app(\App\Services\Support\SupportAttachmentService::class)->uploadDocument($c,$file,null);
  return ['id'=>$a->id,'type'=>$a->kind,'name'=>$a->name,'url'=>'/support/chat/attachments/'.$a->id];
 }
 public function test_storefront_pdf_upload_is_disabled_before_storage():void {
  Storage::fake('support-private');
  $this->post('/support/chat/attachments',['document'=>UploadedFile::fake()->createWithContent('receipt.pdf',file_get_contents(base_path('tests/Fixtures/support-document.pdf')))],['Accept'=>'application/json'])->assertUnprocessable();
  $this->assertSame([],Storage::disk('support-private')->allFiles());
 }
 public function test_pdf_only_guest_message_is_private_and_never_calls_ai():void {
  Storage::fake('support-private');$this->mock(\App\Services\AI\AiConnectionService::class)->shouldNotReceive('generateText');
  $a=$this->legacyDocument(UploadedFile::fake()->createWithContent('receipt.pdf',file_get_contents(base_path('tests/Fixtures/support-document.pdf'))));
  $this->assertSame('document',$a['type']);$this->assertSame('receipt.pdf',$a['name']);
  $r=$this->postJson('/support/chat/messages',['content'=>'','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertOk();
  $this->get($a['url'])->assertOk()->assertHeader('Content-Type','application/pdf');
  $this->assertStringContainsString('attachment;',$this->get($a['url'])->headers->get('Content-Disposition'));
  $this->withSession(['support_guest_key'=>(string)Str::uuid()])->get($a['url'])->assertNotFound();
 }
 public function test_pdf_validation_rejects_corrupt_html_and_oversize():void {
  foreach(['<html>bad</html>','%PDF-1.4 corrupted',str_repeat('x',10485761)] as $b)$this->post('/support/chat/attachments',['document'=>UploadedFile::fake()->createWithContent('bad.pdf',$b)],['Accept'=>'application/json'])->assertUnprocessable();
 }
 public function test_capabilities_do_not_expose_documents_on_instagram_or_tiktok():void {
  $caps=app(\App\Services\Support\SupportMediaCapabilities::class);$this->assertFalse($caps->forChannel('instagram')['documents']);$this->assertFalse($caps->forChannel('tiktok')['documents']);$this->assertTrue($caps->forChannel('facebook')['documents']);
 }
 public function test_pdf_with_caption_never_calls_ai_and_instagram_server_rejects():void {
  Storage::fake('support-private');$this->mock(\App\Services\AI\AiConnectionService::class)->shouldNotReceive('generateText');
  $upload=fn()=>UploadedFile::fake()->createWithContent('receipt.pdf',file_get_contents(base_path('tests/Fixtures/support-document.pdf')));
  $a=$this->legacyDocument($upload());
  $id=$this->postJson('/support/chat/messages',['content'=>'Please inspect','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertOk()->json('conversation.id');
  $staff=User::create(['name'=>'Staff','email'=>'pdf@example.test','password'=>bcrypt('test'),'is_admin'=>true]);SupportConversation::findOrFail($id)->update(['channel'=>'instagram']);
  $this->actingAs($staff)->post('/admin/api/support/conversations/'.$id.'/attachments',['document'=>$upload()],['Accept'=>'application/json'])->assertUnprocessable();
 }
 public function test_encrypted_pdf_is_rejected_and_multiple_files_are_rejected():void {
  $this->post('/support/chat/attachments',['document'=>UploadedFile::fake()->createWithContent('encrypted.pdf',file_get_contents(base_path('tests/Fixtures/support-encrypted.pdf')))],['Accept'=>'application/json'])->assertUnprocessable();
  $this->post('/support/chat/attachments',['document'=>UploadedFile::fake()->createWithContent('a.pdf',file_get_contents(base_path('tests/Fixtures/support-document.pdf'))),'image'=>UploadedFile::fake()->image('a.png')],['Accept'=>'application/json'])->assertUnprocessable();
 }
 public function test_document_caption_is_excluded_from_followup_ai_context():void {
  Storage::fake('support-private');
  \App\Models\StoreSetting::create(['key'=>'support_faq','value'=>['autoReplyEnabled'=>true,'faq'=>['isEnabled'=>true,'items'=>[['id'=>'shipping','question'=>'Shipping?','answer'=>'RM8','isActive'=>true,'keywords'=>[]]]]]]);
  $this->mock(\App\Services\AI\AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($purpose,$prompt)=>!str_contains($prompt,'DOCUMENT_SECRET'))->andReturn('{"answer":"","faq_ids":[]}');
  $a=$this->legacyDocument(UploadedFile::fake()->createWithContent('receipt.pdf',file_get_contents(base_path('tests/Fixtures/support-document.pdf'))));
  $this->postJson('/support/chat/messages',['content'=>'DOCUMENT_SECRET','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertOk();
  $this->postJson('/support/chat/messages',['content'=>'What is your shipping policy?','client_message_id'=>(string)Str::uuid()])->assertOk();
 }
 public function test_dialogue_context_preserves_question_roles_and_excludes_attachment_turns():void {
  Storage::fake('support-private');
  $a=$this->legacyDocument(UploadedFile::fake()->createWithContent('receipt.pdf',file_get_contents(base_path('tests/Fixtures/support-document.pdf'))));
  $id=$this->postJson('/support/chat/messages',['content'=>'DOCUMENT_SECRET','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertOk()->json('conversation.id');
  $c=SupportConversation::findOrFail($id);
  $c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'ai','content'=>'Which product do you mean?']);
  $c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'The speaker. Email buyer@example.test']);
  app()->instance(\App\Services\Support\UnifiedKnowledgeService::class,new \App\Services\Support\UnifiedKnowledgeService());
  $c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'ai','content'=>'PRIVATE_TRACKING_ORDER','actions'=>[['type'=>'view_order_tracking','resource_id'=>0]]]);
  $history=app(\App\Services\Support\SupportDialogueContext::class)->recent($c);
  $this->assertSame('Assistant (context only): Which product do you mean?',$history[0]);
  $this->assertStringContainsString('[email]',$history[1]);$this->assertStringNotContainsString('DOCUMENT_SECRET',implode(' ',$history));$this->assertStringNotContainsString('PRIVATE_TRACKING_ORDER',implode(' ',$history));
 }
}
