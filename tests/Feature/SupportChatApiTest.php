<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\AI\AiConnectionService;
use App\Services\Support\SupportChatService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportChatApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(\App\Services\Support\SupportActionResolver::class)->shouldReceive('resolve')->andReturnUsing(fn($r,$c)=>['content'=>$c,'actions'=>[]]);
        app(\App\Services\Support\SupportActionResolver::class)->shouldReceive('references')->andReturn([]);
        $this->mock(\App\Services\Support\SupportKnowledgeRetriever::class)->shouldReceive("retrieve")->andReturn([]);
        $this->mock(\App\Services\Support\UnifiedKnowledgeService::class)->shouldReceive('tools')->andReturn(['faq.search'=>'Policies']);
        app(\App\Services\Support\UnifiedKnowledgeService::class)->shouldReceive('redact')->andReturnUsing(fn($s)=>$s);
        app(\App\Services\Support\UnifiedKnowledgeService::class)->shouldReceive('retrieve')->andReturnUsing(function($q,$settings,$history){
            return array_merge(array_map(fn($i)=>$i+['id'=>'faq'],array_values(array_filter(($settings['faq']['isEnabled']??false)?($settings['faq']['items']??[]):[],fn($i)=>$i['isActive']))),app(\App\Services\Support\DiscountKnowledgeService::class)->facts($settings['discountKnowledge']??[],$q),app(\App\Services\Support\SupportKnowledgeRetriever::class)->retrieve($q,$settings['productCatalog']??[],$history));
        });
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('is_admin')->default(false);
            $t->boolean('is_super_admin')->default(false);
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('phone')->nullable();
            $t->timestamps();
        });
        Schema::create('store_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_02_160000_create_support_chat_tables.php'))->up();
        (require database_path('migrations/2026_10_03_100000_add_support_message_actions.php'))->up();
    }

    public function test_private_order_reply_never_reaches_model():void
    {
        $this->app->instance(\App\Services\Support\UnifiedKnowledgeService::class,new \App\Services\Support\UnifiedKnowledgeService());
        StoreSetting::create(['key'=>'support_faq','value'=>['autoReplyEnabled'=>true,'productCatalog'=>['isEnabled'=>false],'faq'=>['isEnabled'=>false,'items'=>[]],'orderLookup'=>['isEnabled'=>true,'allowedFields'=>['orderStatus'=>true]]]]);
        $this->mock(\App\Services\Support\OrderKnowledgeService::class)->shouldReceive('reply')->once()->andReturn('LOCAL_PRIVATE_ORDER_STATUS');
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($p,$prompt,$n)=>str_contains($prompt,'order.lookup')&&!str_contains($prompt,'LOCAL_PRIVATE_ORDER_STATUS')&&!str_contains($prompt,'person@example.com'))->andReturn('{"tools":[{"name":"order.lookup","query":"order status"}]}');
        $this->postJson('/support/chat/messages',$this->payload('My order status, person@example.com'))->assertOk()->assertJsonPath('conversation.messages.1.content','LOCAL_PRIVATE_ORDER_STATUS');
    }

    public function test_unified_route_runs_one_plan_then_one_grounded_reply(): void
    {
        $this->app->instance(\App\Services\Support\UnifiedKnowledgeService::class,new \App\Services\Support\UnifiedKnowledgeService());
        StoreSetting::create(['key'=>'support_faq','value'=>['autoReplyEnabled'=>true,'productCatalog'=>['isEnabled'=>false],'faq'=>['isEnabled'=>true,'items'=>[
            ['id'=>'shipping','category'=>'shipping','question'=>'Shipping coverage','answer'=>'We only deliver within Malaysia; no overseas delivery.','keywords'=>['shipping'],'isActive'=>true],
            ['id'=>'payment','category'=>'payment','question'=>'Payment methods','answer'=>'PRIVATE_UNRELATED_PAYMENT','keywords'=>['payment'],'isActive'=>true]
        ]]]]);
        $ai=$this->mock(AiConnectionService::class);
        $ai->shouldReceive('generateText')->once()->ordered()->withArgs(fn($purpose,$prompt,$tokens)=>$tokens===250&&str_contains($prompt,'available_tools')&&!str_contains($prompt,'We only deliver'))->andReturn('{"tools":[{"name":"faq.search","query":"shipping coverage"}]}');
        $ai->shouldReceive('generateText')->once()->ordered()->withArgs(fn($purpose,$prompt,$tokens,$temp)=>str_contains($prompt,'no overseas delivery')&&!str_contains($prompt,'PRIVATE_UNRELATED_PAYMENT'))->andReturn('{"answer":"We only deliver within Malaysia, so we cannot ship to China.","faq_ids":["shipping"]}');
        $this->postJson('/support/chat/messages',$this->payload('Do you deliver to China?'))->assertOk()->assertJsonPath('conversation.messages.1.content','We only deliver within Malaysia, so we cannot ship to China.');
    }

    public function test_official_discount_question_reaches_model_with_grounded_rules(): void
    {
        StoreSetting::create(['key'=>'support_faq','value'=>['autoReplyEnabled'=>true,'productCatalog'=>['isEnabled'=>false],'faq'=>['isEnabled'=>false,'items'=>[]],'discountKnowledge'=>['isEnabled'=>true,'activeIds'=>['1']]]]);
        $this->mock(\App\Services\Support\DiscountKnowledgeService::class)->shouldReceive('facts')->once()->with(['isEnabled'=>true,'activeIds'=>['1']], 'What discounts are available?')->andReturn([['id'=>'discount:1','question'=>'Welcome offer','answer'=>'WELCOME10 gives 10% off with RM200 minimum. Eligibility unverified.']]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($purpose,$prompt,$limit,$temp)=>$purpose==='support'&&str_contains($prompt,'WELCOME10')&&str_contains($prompt,'never guarantee customer eligibility'))->andReturn('{' . '"answer":"WELCOME10 offers 10% off with RM200 minimum; checkout confirms eligibility.","faq_ids":["discount:1"]}');
        $this->postJson('/support/chat/messages',$this->payload('What discounts are available?'))->assertOk()->assertJsonPath('conversation.messages.1.content','WELCOME10 offers 10% off with RM200 minimum; checkout confirms eligibility.');
    }

    public function test_catalogue_zero_result_is_rendered_without_second_model_call(): void
    {
        StoreSetting::create(['key'=>'support_faq','value'=>['autoReplyEnabled'=>true,'faq'=>['isEnabled'=>true,'items'=>[]]]]);
        $mock=\Mockery::mock(\App\Services\Support\SupportKnowledgeRetriever::class);
        $mock->shouldReceive('retrieve')->once()->andReturn([['id'=>'catalogue:search','question'=>'Catalogue search result','answer'=>'{"matches":0}']]);
        $this->app->instance(\App\Services\Support\SupportKnowledgeRetriever::class,$mock);
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $this->postJson('/support/chat/messages',$this->payload('do you sell PS5?'))->assertOk()->assertJsonPath('conversation.messages.1.content','I could not find a matching product in our current published online catalogue. Please contact support to confirm availability.');
    }

    public function test_product_facts_reach_preset_model_with_source_and_price_boundaries(): void
    {
        StoreSetting::create(['key'=>'support_faq','value'=>['autoReplyEnabled'=>true,'faq'=>['isEnabled'=>false,'items'=>[]]]]);
        $mock=\Mockery::mock(\App\Services\Support\SupportKnowledgeRetriever::class);
        $mock->shouldReceive('retrieve')->once()->andReturn([['id'=>'product:1','question'=>'Speaker One','answer'=>'{"name":"Speaker One","base_price":"RM 100.00"}']]);
        $this->app->instance(\App\Services\Support\SupportKnowledgeRetriever::class,$mock);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($feature,$prompt,$limit)=>$feature==='support' && str_contains($prompt,'RM 100.00') && str_contains($prompt,'Never offer negotiated prices') && str_contains($prompt,'product:1'))->andReturn('{"answer":"Speaker One is RM 100.00.","faq_ids":["product:1"]}');
        $this->postJson('/support/chat/messages',$this->payload('How much is Speaker One?'))->assertOk()->assertJsonPath('conversation.messages.1.content','Speaker One is RM 100.00.');
    }

    public function test_bargaining_is_blocked_without_a_model_call(): void
    {
        StoreSetting::create(['key'=>'support_faq','value'=>['autoReplyEnabled'=>true,'faq'=>['isEnabled'=>true,'items'=>[]]]]);
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $this->postJson('/support/chat/messages', $this->payload('Can you give a cheaper price?'))->assertOk()->assertJsonPath('conversation.messages.1.content', 'I can share listed prices, but cannot negotiate prices or promise an unauthorized discount.');
    }

    public function test_brand_context_and_selected_language_fallbacks_are_enforced(): void
    {
        StoreSetting::create(['key' => 'support_faq', 'value' => ['autoReplyEnabled' => true, 'personality' => ['brandMissionPrompt' => 'Our brand serves with care.', 'unknownAnswerMessage' => ['zh' => '请向客服确认。'], 'serviceUnavailableMessage' => ['zh' => '服务暂时不可用。']], 'faq' => ['isEnabled' => true, 'items' => [['id' => 's', 'question' => 'Shipping?', 'answer' => 'RM8', 'isActive' => true]]]]]);
        $mock = $this->mock(AiConnectionService::class);
        $mock->shouldReceive('generateText')->once()->withArgs(fn ($f, $p, $l) => str_contains($p, 'Our brand serves with care.') && str_contains($p, 'Mandatory rules override') && !str_contains($p, '请向客服确认。'))->andReturn('{"answer":"","faq_ids":[]}');
        $this->postJson('/support/chat/messages', $this->payload('Unknown question') + ['language' => 'zh'])->assertOk()->assertJsonPath('conversation.messages.1.content', '请向客服确认。');
        $mock->shouldReceive('generateText')->once()->andThrow(new \RuntimeException('provider down'));
        $this->postJson('/support/chat/messages', $this->payload('Explain delivery') + ['language' => 'zh'])->assertOk()->assertJsonPath('conversation.messages.3.content', '服务暂时不可用。');
    }

    public function test_faq_reply_is_free_idempotent_and_stops_after_human_reply(): void
    {
        StoreSetting::create(['key' => 'support_faq', 'value' => ['autoReplyEnabled' => true, 'faq' => ['isEnabled' => true, 'items' => [['id' => 'shipping', 'question' => 'What is shipping?', 'answer' => 'Shipping is RM8.', 'keywords' => ['shipping fee', 'sabah'], 'isActive' => true]]]]]);
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $p = $this->payload('What is shipping?');
        $id = $this->postJson('/support/chat/messages', $p)->assertOk()->assertJsonPath('conversation.messages.1.content', 'Shipping is RM8.')->json('conversation.id');
        $this->postJson('/support/chat/messages', $p)->assertOk();
        $this->assertDatabaseCount('support_messages', 2);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->postJson("/admin/api/support/conversations/$id/messages", $this->payload('I will help.'))->assertOk();
        $this->postJson('/support/chat/messages', $this->payload('shipping fee sabah'))->assertOk();
        $this->assertDatabaseCount('support_messages', 4);
    }

    public function test_each_nonexact_question_uses_one_call_and_unknown_has_no_supported_answer(): void
    {
        StoreSetting::create(['key' => 'support_faq', 'value' => ['autoReplyEnabled' => true, 'faq' => ['isEnabled' => true, 'items' => [['id' => 'shipping', 'question' => 'Shipping cost?', 'answer' => 'Shipping is RM8.', 'keywords' => ['shipping'], 'isActive' => true]]]]]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->twice()->withArgs(fn ($feature, $prompt, $limit) => $feature === 'support' && str_contains($prompt, 'Shipping is RM8.') && $limit === 350)->andReturn(json_encode(['answer' => 'Delivery costs RM8.', 'faq_ids' => ['shipping']]), json_encode(['answer' => '', 'faq_ids' => []]));
        $this->postJson('/support/chat/messages', $this->payload('Please explain shipping'))->assertOk()->assertJsonPath('conversation.messages.1.content', 'Delivery costs RM8.');
        $this->postJson('/support/chat/messages', $this->payload('Tell me about astronomy'))->assertOk();
        $this->assertDatabaseCount('support_messages', 4);
    }

    public function test_provider_failure_saves_customer_message_and_honest_fallback(): void
    {
        StoreSetting::create(['key' => 'support_faq', 'value' => ['autoReplyEnabled' => true, 'faq' => ['isEnabled' => true, 'items' => [['question' => 'Shipping?', 'answer' => 'RM8', 'keywords' => ['postage'], 'isActive' => true]]]]]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andThrow(new \RuntimeException('private provider failure'));
        $this->postJson('/support/chat/messages', $this->payload('Explain postage'))->assertOk()->assertJsonPath('conversation.messages.1.content', 'Automatic assistance is temporarily unavailable. Your message has been saved for our support team.')->assertJsonMissing(['content' => 'private provider failure']);
    }

    public function test_human_reply_during_generation_suppresses_generated_answer(): void
    {
        StoreSetting::create(['key' => 'support_faq', 'value' => ['autoReplyEnabled' => true, 'faq' => ['isEnabled' => true, 'items' => [['question' => 'Shipping?', 'answer' => 'RM8', 'keywords' => ['postage'], 'isActive' => true]]]]]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturnUsing(function () {
            $c = SupportConversation::firstOrFail();
            app(SupportChatService::class)->append($c, $this->payload('Human took over'), 'human_agent');

            return 'This AI answer must not be sent';
        });
        $this->postJson('/support/chat/messages', $this->payload('Explain postage'))->assertOk()->assertJsonCount(2, 'conversation.messages')->assertJsonMissing(['content' => 'This AI answer must not be sent']);
    }

    public function test_selected_language_overrides_default_even_for_direct_faq_match(): void
    {
        StoreSetting::create(['key' => 'support_faq', 'value' => ['primaryLanguage' => 'en', 'autoReplyEnabled' => true, 'faq' => ['isEnabled' => true, 'items' => [['question' => 'Shipping cost?', 'answer' => 'Shipping is RM8.', 'keywords' => ['shipping', 'cost'], 'isActive' => true]]]]]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn ($f, $p, $n) => str_contains($p, 'Reply only in Malay'))->andReturn(json_encode(['answer' => 'Kos penghantaran ialah RM8.', 'faq_ids' => ['faq']]));
        $this->postJson('/support/chat/messages', $this->payload('shipping cost') + ['language' => 'ms'])->assertOk()->assertJsonPath('conversation.messages.1.content', 'Kos penghantaran ialah RM8.');
        $this->postJson('/support/chat/messages', $this->payload('shipping cost') + ['language' => 'invalid'])->assertUnprocessable();
    }

    public function test_retrieved_faqs_reach_model_and_settings_are_style_only(): void
    {
        StoreSetting::create(['key' => 'support_faq', 'value' => ['autoReplyEnabled' => true, 'personality' => ['toneOfVoice' => 'concise_efficient', 'customToneInstructions' => 'Be brief', 'maxOutputTokens' => 240], 'faq' => ['isEnabled' => true, 'items' => [['id' => 'overseas', 'question' => 'Oversea shipping fee?', 'answer' => 'We only deliver within Malaysia.', 'keywords' => ['oversea'], 'isActive' => true], ['id' => 'hidden', 'question' => 'Hidden', 'answer' => 'SECRET_INACTIVE', 'keywords' => [], 'isActive' => false]]]]]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn ($f, $p, $n) => str_contains($p, 'We only deliver within Malaysia.') && ! str_contains($p, 'SECRET_INACTIVE') && str_contains($p, 'style only') && $n === 240)->andReturn(json_encode(['answer' => 'We do not ship to China.', 'faq_ids' => ['overseas']]));
        $this->postJson('/support/chat/messages', $this->payload('i am from china, how long do i receive the parcel?'))->assertOk()->assertJsonPath('conversation.messages.1.content', 'We do not ship to China.');
    }

    public function test_invalid_grounding_sources_cannot_publish_model_answer(): void
    {
        StoreSetting::create(['key' => 'support_faq', 'value' => ['autoReplyEnabled' => true, 'faq' => ['isEnabled' => true, 'items' => [['id' => 'local', 'question' => 'Delivery?', 'answer' => 'Local only', 'isActive' => true, 'keywords' => []]]]]]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn(json_encode(['answer' => 'Free worldwide delivery', 'faq_ids' => ['fabricated']]));
        $this->postJson('/support/chat/messages', $this->payload('Ship overseas?'))->assertOk()->assertJsonMissing(['content' => 'Free worldwide delivery']);
    }

    private function payload(string $content = 'Hello from storefront'): array
    {
        return ['content' => $content, 'client_message_id' => (string) Str::uuid()];
    }

    public function test_guest_roundtrip_is_real_persistent_and_private(): void
    {
        $this->withSession(['support_guest_key' => 'guest-a']);
        $this->getJson('/support/chat')->assertOk()->assertJsonPath('conversation', null);
        $payload = $this->payload();
        $id = $this->postJson('/support/chat/messages', $payload)->assertOk()->json('conversation.id');
        $this->postJson('/support/chat/messages', $payload)->assertOk();
        $this->assertDatabaseCount('support_messages', 1);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->getJson('/admin/api/support/conversations')->assertOk()
            ->assertJsonPath('conversations.0.customer.name', 'Guest')->assertJsonPath('conversations.0.unreadCount', 1);
        $this->postJson("/admin/api/support/conversations/$id/messages", $this->payload('Real staff reply'))->assertOk();
        $this->postJson("/admin/api/support/conversations/$id/notes", ['content' => 'Private note'])->assertOk();
        $public = $this->getJson('/support/chat')->assertOk()->assertJsonPath('conversation.messages.1.content', 'Real staff reply')->json();
        $this->assertArrayNotHasKey('internalNotes', $public['conversation']);
        $this->assertStringNotContainsString('Private note', json_encode($public));
        $this->assertStringNotContainsString('owner_hash', json_encode($public));
        $this->assertDatabaseHas('support_staff_notes', ['content' => 'Private note']);
    }

    public function test_other_guest_cannot_choose_or_read_a_conversation(): void
    {
        $this->withSession(['support_guest_key' => 'a']);
        $id = $this->postJson('/support/chat/messages', $this->payload('Secret A'))->json('conversation.id');
        $this->withSession(['support_guest_key' => 'b']);
        $this->getJson('/support/chat?conversation_id='.$id)->assertJsonPath('conversation', null);
        $this->postJson('/support/chat/messages', $this->payload('Guest B') + ['conversation_id' => $id, 'name' => 'Spoofed'])->assertOk();
        $this->assertDatabaseCount('support_conversations', 2);
        $admin = User::factory()->create(['is_admin' => true]);
        foreach ($this->actingAs($admin)->getJson('/admin/api/support/conversations')->json('conversations') as $conversation) {
            $this->assertSame('Guest', $conversation['customer']['name']);
        }
    }

    public function test_admin_authentication_and_authorization_protect_every_action(): void
    {
        $this->getJson('/admin/api/support/conversations')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->getJson('/admin/api/support/conversations')->assertUnauthorized();
        $id = (string) Str::uuid();
        $this->postJson("/admin/api/support/conversations/$id/messages", $this->payload())->assertUnauthorized();
        $this->patchJson("/admin/api/support/conversations/$id/status", ['status' => 'resolved'])->assertUnauthorized();
        $this->postJson("/admin/api/support/conversations/$id/read", ['through_message_id' => 1])->assertUnauthorized();
        $this->postJson("/admin/api/support/conversations/$id/notes", ['content' => 'private'])->assertUnauthorized();
    }

    public function test_member_identity_is_server_owned_and_does_not_merge_guest_threads(): void
    {
        $this->withSession(['support_guest_key' => 'a']);
        $this->postJson('/support/chat/messages', $this->payload('Guest message'))->assertOk();
        $member = User::factory()->create(['name' => 'Real Member']);
        $this->actingAs($member, 'customer')->getJson('/support/chat')->assertJsonPath('conversation', null);
        $this->postJson('/support/chat/messages', $this->payload('Member message'))->assertOk();
        $this->assertDatabaseHas('support_conversations', ['customer_user_id' => $member->id]);
        $other = User::factory()->create();
        $this->actingAs($other, 'customer')->getJson('/support/chat')->assertJsonPath('conversation', null);
    }

    public function test_read_watermark_resolution_reopening_and_idempotency(): void
    {
        $this->withSession(['support_guest_key' => 'a']);
        $id = $this->postJson('/support/chat/messages', $this->payload())->json('conversation.id');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->postJson("/admin/api/support/conversations/$id/read", ['through_message_id' => 1])->assertJsonPath('conversation.unreadCount', 0);
        $this->postJson('/support/chat/messages', $this->payload('New after read'))->assertOk();
        $this->postJson("/admin/api/support/conversations/$id/read", ['through_message_id' => 1])->assertJsonPath('conversation.unreadCount', 1);
        $reply = $this->payload('Reply');
        $this->postJson("/admin/api/support/conversations/$id/messages", $reply)->assertOk();
        $this->postJson("/admin/api/support/conversations/$id/messages", $reply)->assertOk();
        $this->assertDatabaseCount('support_messages', 3);
        $this->postJson("/admin/api/support/conversations/$id/messages", array_replace($reply, ['content' => 'Changed']))->assertUnprocessable();
        $this->patchJson("/admin/api/support/conversations/$id/status", ['status' => 'resolved'])->assertOk();
        $this->postJson("/admin/api/support/conversations/$id/messages", $this->payload())->assertUnprocessable();
        $this->patchJson("/admin/api/support/conversations/$id/status", ['status' => 'open'])->assertOk();
        $this->patchJson("/admin/api/support/conversations/$id/status", ['status' => 'resolved'])->assertOk();
        $this->postJson('/support/chat/messages', $this->payload('Reopen from guest'))->assertJsonPath('conversation.status', 'open');
    }

    public function test_validation_and_safe_text_storage(): void
    {
        $this->postJson('/support/chat/messages', $this->payload(''))->assertUnprocessable();
        $this->postJson('/support/chat/messages', $this->payload(str_repeat('x', 2001)))->assertUnprocessable();
        $this->postJson('/support/chat/messages', ['content' => 'Hi', 'client_message_id' => 'bad'])->assertUnprocessable();
        $this->postJson('/support/chat/messages', $this->payload('<img src=x onerror=alert(1)>'))->assertOk()
            ->assertJsonPath('conversation.messages.0.content', '<img src=x onerror=alert(1)>');
    }

    public function test_incremental_inbox_keeps_timestamp_boundary_and_note_changes(): void
    {
        $this->withSession(['support_guest_key' => 'incremental']);
        $id = $this->postJson('/support/chat/messages', $this->payload())->json('conversation.id');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $since = $this->getJson('/admin/api/support/conversations')->assertOk()->json('syncedAt');
        $this->getJson('/admin/api/support/conversations?since='.urlencode($since))->assertOk()->assertJsonCount(1, 'conversations');
        $this->travel(2)->seconds();
        $this->postJson("/admin/api/support/conversations/$id/notes", ['content' => 'Delta note'])->assertOk();
        $this->getJson('/admin/api/support/conversations?since='.urlencode($since))->assertOk()
            ->assertJsonPath('conversations.0.internalNotes.0.content', 'Delta note');
        $this->getJson('/admin/api/support/conversations?since=invalid')->assertUnprocessable();
    }

    public function test_real_csrf_gate_rejects_missing_token(): void
    {
        $this->app->instance('env', 'local');
        $this->withSession(['_token' => 'support-csrf-test']);
        $this->postJson('/support/chat/messages', $this->payload())->assertStatus(419);
        $this->postJson('/support/chat/messages', $this->payload(), ['X-CSRF-TOKEN' => 'support-csrf-test'])->assertOk();
    }
}
