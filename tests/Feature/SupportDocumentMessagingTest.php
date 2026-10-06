<?php
namespace Tests\Feature;
use App\Models\{SupportConversation,User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class SupportDocumentMessagingTest extends SupportChatApiTest {
 public function test_pdf_only_guest_message_is_private_and_never_calls_ai():void {
  Storage::fake('support-private');$this->mock(\App\Services\AI\AiConnectionService::class)->shouldNotReceive('generateText');
  $a=$this->post('/support/chat/attachments',['document'=>UploadedFile::fake()->createWithContent('receipt.pdf',file_get_contents(base_path('tests/Fixtures/support-document.pdf')))],['Accept'=>'application/json'])->assertCreated()->json('attachment');
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
  $a=$this->post('/support/chat/attachments',['document'=>$upload()],['Accept'=>'application/json'])->assertCreated()->json('attachment');
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
  $a=$this->post('/support/chat/attachments',['document'=>UploadedFile::fake()->createWithContent('receipt.pdf',file_get_contents(base_path('tests/Fixtures/support-document.pdf')))],['Accept'=>'application/json'])->assertCreated()->json('attachment');
  $this->postJson('/support/chat/messages',['content'=>'DOCUMENT_SECRET','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertOk();
  $this->postJson('/support/chat/messages',['content'=>'What is your shipping policy?','client_message_id'=>(string)Str::uuid()])->assertOk();
 }
}
