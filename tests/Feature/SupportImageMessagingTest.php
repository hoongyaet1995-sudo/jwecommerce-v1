<?php
namespace Tests\Feature;

use App\Models\SupportConversation;
use App\Models\User;
use App\Services\AI\AiConnectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportImageMessagingTest extends SupportChatApiTest
{
    private function upload(): array
    {
        return $this->post('/support/chat/attachments', ['image'=>UploadedFile::fake()->image('screen.png', 20, 20)], ['Accept'=>'application/json'])->assertCreated()->json('attachment');
    }

    public function test_image_only_message_persists_and_does_not_call_ai(): void
    {
        Storage::fake('support-private');
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $a=$this->upload();
        $payload=['content'=>'','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()];
        $response=$this->postJson('/support/chat/messages',$payload)->assertOk();
        $id=$response->json('conversation.id');
        $response->assertJsonPath('conversation.messages.0.attachments.0.id',$a['id']);
        $this->assertSame(1,SupportConversation::findOrFail($id)->messages()->count());
        $this->postJson('/support/chat/messages',$payload)->assertOk();
        $this->assertSame(1,SupportConversation::findOrFail($id)->messages()->count());
        $this->get($response->json('conversation.messages.0.attachments.0.url'))->assertOk()->assertHeader('Content-Type','image/png');
    }

    public function test_staff_image_reply_and_cross_owner_access(): void
    {
        Storage::fake('support-private');
        $a=$this->upload();
        $id=$this->postJson('/support/chat/messages',['content'=>'','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertOk()->json('conversation.id');
        $this->withSession(['support_guest_key'=>(string)Str::uuid()])->get('/support/chat/attachments/'.$a['id'])->assertNotFound();
        $this->postJson('/support/chat/messages',['content'=>'','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertNotFound();
        $staff=User::create(['name'=>'Staff','email'=>'images@example.test','password'=>bcrypt('test'),'is_admin'=>true]);
        $reply=$this->actingAs($staff)->post('/admin/api/support/conversations/'.$id.'/attachments',['image'=>UploadedFile::fake()->image('reply.jpg',20,20)],['Accept'=>'application/json'])->assertCreated()->json('attachment');
        $this->postJson('/admin/api/support/conversations/'.$id.'/messages',['content'=>'','attachment_id'=>$reply['id'],'client_message_id'=>(string)Str::uuid()])->assertOk()->assertJsonPath('conversation.messages.1.attachments.0.id',$reply['id']);
    }

    public function test_invalid_image_and_expired_upload_rejected(): void
    {
        Storage::fake('support-private');
        $this->post('/support/chat/attachments',['image'=>UploadedFile::fake()->createWithContent('fake.png','<svg onload="evil()"/>')],['Accept'=>'application/json'])->assertUnprocessable();
        $a=$this->upload();
        $this->travel(25)->hours();
        $this->postJson('/support/chat/messages',['content'=>'','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertNotFound();
    }
    public function test_cleanup_removes_only_expired_unbound_uploads(): void
    {
        Storage::fake('support-private');$expired=$this->upload();
        $sent=$this->upload();
        $this->postJson('/support/chat/messages',['content'=>'','attachment_id'=>$sent['id'],'client_message_id'=>(string)Str::uuid()])->assertOk();
        $this->travel(25)->hours();$fresh=$this->upload();
        $this->artisan('support:prune-uploads')->assertSuccessful();
        $this->assertNull(\App\Models\SupportAttachment::find($expired['id']));
        $this->assertNotNull(\App\Models\SupportAttachment::find($sent['id']));
        $this->assertNotNull(\App\Models\SupportAttachment::find($fresh['id']));
        $this->assertSame(2,count(Storage::disk('support-private')->allFiles()));
    }
    public function test_downloader_rejects_non_meta_and_private_destination_syntax(): void
    {
        $d=app(\App\Services\Support\SupportMediaDownload::class);
        foreach(['http://scontent.xx.fbcdn.net/a','https://127.0.0.1/a','https://fbcdn.net.evil.test/a','https://user:secret@fbcdn.net/a','https://fbcdn.net:8080/a'] as $url){
            try{$d->host($url);$this->fail('Unsafe URL allowed');}catch(\RuntimeException){$this->addToAssertionCount(1);}
        }
        $this->assertSame('scontent.xx.fbcdn.net',$d->host('https://scontent.xx.fbcdn.net/a'));
    }
}
