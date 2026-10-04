<?php

namespace Tests\Feature;

use App\Models\ManagedSocialAccount;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessengerInboundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:',
            'services.facebook.client_id'=>'app-test','services.facebook.client_secret'=>'secret-test',
            'services.facebook.inbound_routing_ready'=>false,'services.facebook.webhook_verify_token'=>'verify-test','services.facebook.merchant_key'=>'shop-test']);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin')->default(false);$t->boolean('is_super_admin')->default(false);$t->timestamp('email_verified_at')->nullable();$t->rememberToken();$t->timestamps();});
        Schema::create('customers',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable();});
        Schema::create('store_settings',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->json('value');$t->timestamps();});
        foreach(['2026_10_02_160000_create_support_chat_tables.php','2026_10_03_100000_add_support_message_actions.php','2026_10_03_180000_create_notifications_table.php','2026_10_04_120000_create_managed_social_accounts.php'] as $file)(require database_path('migrations/'.$file))->up();
        if(file_exists(database_path('migrations/2026_10_04_180000_add_messenger_inbound.php')))(require database_path('migrations/2026_10_04_180000_add_messenger_inbound.php'))->up();
        Http::preventStrayRequests();
    }

    private function page(string $id='111', array $extra=[]): ManagedSocialAccount
    {
        return ManagedSocialAccount::unguarded(fn()=>ManagedSocialAccount::create($extra+['merchant_key'=>'shop-test','platform'=>'facebook','app_id'=>'app-test','provider_account_id'=>$id,'name'=>'Page '.$id,'access_token'=>'private','permissions'=>[],'tasks'=>[],'state'=>'connected','inbound_enabled'=>true,'authorized_by'=>User::factory()->create()->id]));
    }

    private function payload(string $page='111',string $mid='mid-one',string $text='Hello'): array
    {
        return ['object'=>'page','entry'=>[['id'=>$page,'messaging'=>[['sender'=>['id'=>'222'],'recipient'=>['id'=>$page],'timestamp'=>now()->timestamp*1000,'message'=>['mid'=>$mid,'text'=>$text]]]]]];
    }

    private function deliver(array $payload, bool $valid=true)
    {
        $body=json_encode($payload,JSON_UNESCAPED_UNICODE);
        return $this->call('POST','/webhooks/facebook/messenger',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_HUB_SIGNATURE_256'=>'sha256='.hash_hmac('sha256',$body,$valid?'secret-test':'wrong')],$body);
    }

    public function test_challenge_requires_configured_verify_token(): void
    {
        $this->get('/webhooks/facebook/messenger?hub_mode=subscribe&hub_verify_token=verify-test&hub_challenge=123')->assertOk()->assertContent('123');
        $this->get('/webhooks/facebook/messenger?hub_mode=subscribe&hub_verify_token=bad&hub_challenge=123')->assertForbidden();
    }

    public function test_signed_text_is_deduplicated_and_updates_the_existing_inbox(): void
    {
        $this->page();$staff=User::factory()->create(['is_admin'=>true]);
        $this->deliver($this->payload())->assertOk();$this->deliver($this->payload())->assertOk();
        $this->assertSame(1,DB::table('support_messages')->count());
        $this->assertSame(1,DB::table('notifications')->count());
        $conversation=SupportConversation::firstOrFail();
        $this->assertSame('waiting_human',$conversation->status);
        $this->actingAs($staff)->getJson('/admin/api/support/conversations')->assertOk()->assertJsonFragment(['channel'=>'facebook','title'=>'Page 111','unreadCount'=>1,'content'=>'Hello']);
        $this->deliver($this->payload('111','mid-two','Again'))->assertOk();
        $this->assertSame(1,SupportConversation::count());$this->assertSame(2,DB::table('support_messages')->count());
    }

    public function test_bad_signature_unmapped_disconnected_and_echo_events_are_not_ingested(): void
    {
        $this->page('111',['state'=>'disconnected']);
        $this->deliver($this->payload(),false)->assertForbidden();
        $this->deliver($this->payload())->assertOk();$this->deliver($this->payload('999'))->assertOk();
        $this->page('333');$echo=$this->payload('333');$echo['entry'][0]['messaging'][0]['message']['is_echo']=true;
        $this->deliver($echo)->assertOk();
        $this->assertSame(0,SupportConversation::count());
    }

    public function test_same_sender_is_isolated_by_page_and_resolved_archived_threads_reopen(): void
    {
        $this->page();$this->page('333');$this->deliver($this->payload())->assertOk();$this->deliver($this->payload('333'))->assertOk();
        $this->assertSame(2,SupportConversation::count());
        $conversation=SupportConversation::where('provider_page_id','111')->firstOrFail();
        $conversation->update(['status'=>'resolved']);$conversation->notes()->create(['content'=>'[Archive] true']);
        $this->deliver($this->payload('111','mid-next'))->assertOk();
        $this->assertSame('waiting_human',$conversation->fresh()->status);
        $this->assertSame('[Archive] false',$conversation->notes()->latest('id')->value('content'));
    }

    public function test_receive_only_thread_cannot_fake_a_sent_reply_or_switch_to_ai(): void
    {
        $this->page();$this->deliver($this->payload())->assertOk();$conversation=SupportConversation::firstOrFail();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->postJson('/admin/api/support/conversations/'.$conversation->id.'/messages',['content'=>'Reply','client_message_id'=>(string)\Illuminate\Support\Str::uuid()])->assertStatus(409);
        $this->patchJson('/admin/api/support/conversations/'.$conversation->id.'/status',['status'=>'open'])->assertStatus(409);
        $this->assertSame(1,DB::table('support_messages')->count());
        $conversation->update(['status'=>'resolved']);
        $this->patchJson('/admin/api/support/conversations/'.$conversation->id.'/status',['status'=>'open'])->assertOk()->assertJsonPath('conversation.status','waiting_human');
    }

    public function test_activation_requires_routing_and_grants_and_preserves_shared_subscription_fields(): void
    {
        $page=$this->page('111',['inbound_enabled'=>false,'permissions'=>['pages_messaging','pages_manage_metadata']]);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $url='/admin/api/social/facebook/'.$page->id.'/inbound';
        $this->postJson($url)->assertStatus(409);Http::assertNothingSent();
        config(['services.facebook.inbound_routing_ready'=>true]);
        Http::fake(['graph.facebook.com/*/111/subscribed_apps*'=>Http::sequence()
            ->push(['data'=>[['id'=>'app-test','subscribed_fields'=>['feed']]]])
            ->push(['success'=>true])
            ->push(['data'=>[['id'=>'app-test','subscribed_fields'=>['feed','messages']]]])]);
        $this->postJson($url)->assertOk()->assertJsonPath('account.inboundEnabled',true);
        Http::assertSent(fn($r)=>$r->method()==='POST'&&$r['subscribed_fields']==='feed,messages'&&!isset($r['override_callback_uri']));
        $this->assertTrue($page->fresh()->inbound_enabled);
    }

    public function test_activation_denied_without_page_permissions_or_admin_ownership(): void
    {
        config(['services.facebook.inbound_routing_ready'=>true]);
        $page=$this->page('111',['inbound_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $this->postJson('/admin/api/social/facebook/'.$page->id.'/inbound')->assertStatus(422);
        $page->update(['merchant_key'=>'another-shop']);
        $this->postJson('/admin/api/social/facebook/'.$page->id.'/inbound')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_disabled_wrong_app_and_wrong_merchant_pages_are_ignored_but_a_transient_check_does_not_drop_inbound(): void
    {
        $this->page('111',['inbound_enabled'=>false]);$this->page('333',['app_id'=>'other-app']);$this->page('444',['merchant_key'=>'other-shop']);
        foreach(['111','333','444'] as $id)$this->deliver($this->payload($id))->assertOk();
        $this->assertSame(0,SupportConversation::count());
        $this->page('555',['state'=>'unknown']);$this->deliver($this->payload('555'))->assertOk();
        $this->assertSame(1,SupportConversation::count());
    }

    public function test_retained_ingress_bridge_encrypts_queue_body_and_keeps_the_original_signature(): void
    {
        require_once base_path('integrations/legacy-meta-relay/ForwardMetaMessengerToEcommerce.php');
        require_once base_path('integrations/legacy-meta-relay/RelayMetaMessengerToEcommerce.php');
        config(['services.facebook.ecommerce_relay_enabled'=>true,'services.facebook.ecommerce_relay_page_ids'=>['111']]);
        \Illuminate\Support\Facades\Queue::fake();
        $body=json_encode($this->payload());$signature='sha256='.hash_hmac('sha256',$body,'secret-test');
        $request=\Illuminate\Http\Request::create('/api/webhooks/meta-social','POST',[],[],[],['HTTP_X_HUB_SIGNATURE_256'=>$signature],$body);
        app(\App\Services\Social\RelayMetaMessengerToEcommerce::class)->capture($request);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ForwardMetaMessengerToEcommerce::class,function($job)use($body,$signature){
            $this->assertSame('database',$job->connection);$this->assertSame('meta-ecommerce-relay',$job->queue);
            $this->assertStringNotContainsString('Hello',serialize($job));
            Http::fake(['ecom.jwaistudio.online/webhooks/facebook/messenger'=>Http::response(['processed'=>1])]);
            $job->handle();
            Http::assertSent(fn($r)=>$r->body()===$body&&$r->header('X-Hub-Signature-256')===[$signature]);
            return true;
        });
    }
}
