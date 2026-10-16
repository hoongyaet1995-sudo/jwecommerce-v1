<?php
namespace Tests\Feature;

use App\Models\{Product,ProductEnquiry,SupportConversation,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductEnquirySupportTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void {parent::setUp();config(['product_enquiries.enabled'=>true]);}
    private function enquiry():ProductEnquiry {
        $p=Product::create(['title'=>'Quoted service','handle'=>'quoted-service','status'=>'active','purchase_mode'=>'quote','base_price'=>0]);
        $this->post('/enquiries',['product_id'=>$p->id,'request_key'=>(string)Str::uuid(),'name'=>'Test guest','email'=>'guest@example.test','requirements'=>'Need fifty units.'])->assertRedirect();
        return ProductEnquiry::latest()->firstOrFail();
    }
    public function test_support_link_is_private_idempotent_and_separate_from_general_chat():void {
        $e=$this->enquiry();$this->withSession(['support_guest_key'=>'original-guest']);
        $normal=app(\App\Services\Support\SupportChatService::class)->owner(request());
        $c=SupportConversation::create(['id'=>(string)Str::uuid(),'owner_hash'=>$normal[0]]);
        $url='/enquiries/'.$e->id.'/support';
        $this->post($url)->assertRedirect('/enquiries/'.$e->id.'?support=1');
        $e->refresh();$this->assertNotNull($e->support_conversation_id);$this->assertNotSame($c->id,$e->support_conversation_id);
        $this->getJson('/support/chat?enquiry_id='.$e->id)->assertOk()->assertJsonPath('conversation.id',$e->support_conversation_id)->assertJsonPath('conversation.status','waiting_human')->assertSee('Need fifty units.');
        $this->post($url)->assertRedirect();$this->assertSame(1,$e->supportConversation->messages()->count());
        $this->assertSame(1,$e->events()->where('type','support_linked')->count());
        $this->flushSession();$this->post($url)->assertNotFound();$this->getJson('/support/chat')->assertOk()->assertJsonPath('conversation',null);
    }
    public function test_staff_reply_reaches_customer_but_notes_do_not_and_thread_is_persisted():void {
        $e=$this->enquiry();$this->post('/enquiries/'.$e->id.'/support')->assertRedirect();$id=$e->fresh()->support_conversation_id;
        $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->getJson('/admin/api/enquiries/'.$e->id)->assertOk()->assertJsonPath('data.support.id',$id);
        $this->postJson('/admin/api/support/conversations/'.$id.'/messages',['client_message_id'=>(string)Str::uuid(),'content'=>'Our team is reviewing your requirements.'])->assertOk();
        $this->postJson('/admin/api/support/conversations/'.$id.'/notes',['content'=>'Private price discussion'])->assertOk();
        $this->getJson('/support/chat?enquiry_id='.$e->id)->assertOk()->assertSee('Our team is reviewing your requirements.')->assertDontSee('Private price discussion');
        $this->postJson('/support/chat/messages?enquiry_id='.$e->id,['client_message_id'=>(string)Str::uuid(),'content'=>'Thank you, please include delivery.'])->assertOk()->assertJsonPath('conversation.id',$id);
        $conversation=SupportConversation::findOrFail($id);
        $this->assertSame('Test guest',app(\App\Services\Support\SupportChatService::class)->adminView($conversation)['customer']['name']);
        $this->assertDatabaseCount('orders',0);
        $this->assertSame('new',$e->fresh()->status);
    }
    public function test_two_tabs_and_new_question_keep_thread_boundaries():void {
        $normalId=$this->postJson('/support/chat/messages',['content'=>'Existing ordinary question.','client_message_id'=>(string)Str::uuid()])->assertOk()->json('conversation.id');
        $a=$this->enquiry();$this->post('/enquiries/'.$a->id.'/support')->assertRedirect();
        $p=Product::first();$this->post('/enquiries',['product_id'=>$p->id,'request_key'=>(string)Str::uuid(),'name'=>'Test guest','email'=>'guest@example.test','requirements'=>'A separate request.'])->assertRedirect();
        $b=ProductEnquiry::whereKeyNot($a->id)->firstOrFail();$this->post('/enquiries/'.$b->id.'/support')->assertRedirect();
        $this->getJson('/support/chat?enquiry_id='.$a->id)->assertOk()->assertJsonPath('conversation.id',$a->fresh()->support_conversation_id)->assertDontSee('A separate request.');
        $this->getJson('/support/chat?enquiry_id='.$b->id)->assertOk()->assertJsonPath('conversation.id',$b->fresh()->support_conversation_id)->assertDontSee('Need fifty units.');
        $this->getJson('/support/chat')->assertOk()->assertJsonPath('conversation.id',$normalId);
        $a->fresh()->supportConversation->update(['status'=>'resolved']);
        $this->postJson('/support/chat/resolved?enquiry_id='.$a->id,['action'=>'new'])->assertOk()->assertJsonPath('conversation',null);
        $this->getJson('/support/chat?support_generation=')->assertOk()->assertJsonPath('conversation.id',$normalId);
        $this->get('/enquiries/'.$a->id)->assertOk()->assertSee('data-enquiry-context=""',false);
        $this->getJson('/support/chat')->assertOk()->assertJsonPath('conversation',null);
        $this->getJson('/support/chat?enquiry_id='.$a->id)->assertOk()->assertJsonPath('conversation.status','resolved');
        $this->postJson('/support/chat/resolved?enquiry_id='.$a->id,['action'=>'reopen'])->assertOk()->assertJsonPath('conversation.status','waiting_human');
    }
    public function test_guest_claims_and_forged_context_cannot_read_another_enquiry():void {
        $staff=User::factory()->create(['is_admin'=>true]);$customer=User::factory()->create(['email'=>'guest@example.test']);
        $e=$this->enquiry();$this->post('/enquiries/'.$e->id.'/support')->assertRedirect();$id=$e->fresh()->support_conversation_id;
        $this->assertNull(SupportConversation::find($id)->customer_user_id);
        $count=$staff->notifications()->count();$this->post('/enquiries/'.$e->id.'/support')->assertRedirect();
        $this->assertSame($count,$staff->notifications()->count());
        $this->flushSession();$this->actingAs($customer,'customer');
        $this->getJson('/support/chat?enquiry_id='.$e->id)->assertNotFound();
        $this->post('/enquiries/'.$e->id.'/support')->assertNotFound();
        $this->postJson('/support/chat/messages?enquiry_id='.$e->id,['client_message_id'=>(string)Str::uuid(),'content'=>'Forged'])->assertNotFound();
        $this->getJson('/support/chat?enquiry_id=invalid')->assertNotFound();
        $this->assertSame(1,SupportConversation::find($id)->messages()->count());
    }
    public function test_customer_can_resume_after_session_rotation_but_another_customer_cannot():void {
        $customer=User::factory()->create();$this->actingAs($customer,'customer');$e=$this->enquiry();
        $this->post('/enquiries/'.$e->id.'/support')->assertRedirect();$id=$e->fresh()->support_conversation_id;$this->flushSession();
        $this->getJson('/support/chat?enquiry_id='.$e->id)->assertOk()->assertJsonPath('conversation.id',$id);
        $this->actingAs(User::factory()->create(),'customer')->getJson('/support/chat?enquiry_id='.$e->id)->assertNotFound();
    }
    public function test_link_post_retains_real_csrf_protection():void {
        $e=$this->enquiry();$this->app->instance('env','local');$this->withSession(['_token'=>'enquiry-support-csrf']);
        $this->post('/enquiries/'.$e->id.'/support')->assertStatus(419);
        $this->post('/enquiries/'.$e->id.'/support',['_token'=>'enquiry-support-csrf'])->assertRedirect();
    }
    public function test_enquiry_attachments_use_same_authorized_thread_context():void {
        \Illuminate\Support\Facades\Storage::fake('support-private');
        $e=$this->enquiry();$this->post('/enquiries/'.$e->id.'/support')->assertRedirect();
        $file=\Illuminate\Http\UploadedFile::fake()->image('enquiry.png',20,20);
        $a=$this->post('/support/chat/attachments?enquiry_id='.$e->id,['image'=>$file],['Accept'=>'application/json'])->assertCreated()->json('attachment');
        $this->get($a['url'].'?enquiry_id='.$e->id)->assertOk();
        $this->get($a['url'])->assertNotFound();
        $this->postJson('/support/chat/messages?enquiry_id='.$e->id,['client_message_id'=>(string)Str::uuid(),'attachment_id'=>$a['id']])->assertOk()->assertJsonPath('conversation.messages.1.attachments.0.id',$a['id']);
        $this->flushSession();$this->get($a['url'].'?enquiry_id='.$e->id)->assertNotFound();
    }
    public function test_chat_polling_does_not_consume_support_link_rate_limit():void {
        $e=$this->enquiry();
        for($i=0;$i<40;$i++)$this->getJson('/support/chat')->assertOk();
        $this->get('/enquiries/'.$e->id)->assertOk();
        $this->post('/enquiries/'.$e->id.'/support')->assertRedirect();
        $this->postJson('/support/chat/messages?enquiry_id='.$e->id,['content'=>'Polling must not consume message budget.','client_message_id'=>(string)Str::uuid()])->assertOk();
        for($i=0;$i<9;$i++)$this->post('/enquiries/'.$e->id.'/support')->assertRedirect();
        $this->post('/enquiries/'.$e->id.'/support')->assertStatus(429);
    }
}
