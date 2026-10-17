<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Support\FaqKnowledgeSearch;
use App\Services\SupportFaqService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportImprovementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
            $t->boolean('is_super_admin')->default(false);
        });
        Schema::create('store_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_02_160000_create_support_chat_tables.php'))->up();
        (require database_path('migrations/2026_10_07_120000_add_support_reply_evidence.php'))->up();
        Schema::table('support_conversations', fn (Blueprint $t) => $t->string('channel')->default('storefront'));
        Schema::table('support_messages', fn (Blueprint $t) => $t->string('delivery_status')->nullable());
        StoreSetting::create(['key' => 'support_improvement', 'value' => ['activatedAt' => now()->subDay()->toIso8601String()]]);
        StoreSetting::create(['key' => 'support_faq', 'value' => ['version' => 0, 'autoReplyEnabled' => true, 'faq' => ['isEnabled' => true, 'categories' => [['id' => 'others', 'label' => 'Others']], 'items' => []]]]);
        (require database_path('migrations/2026_10_08_120000_create_support_faq_drafts_table.php'))->up();
        Schema::create('support_attachments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('message_id')->nullable();
        });
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
    }

    private function example(string $decision = 'no_match', string $channel = 'storefront', string $question = 'Can you assemble a bookcase?', string $state = 'resolved'): SupportMessage
    {
        $c = SupportConversation::create(['id' => (string) Str::uuid(), 'owner_hash' => Str::random(64), 'channel' => $channel, 'status' => $state]);
        $source = $c->messages()->create(['client_message_id' => (string) Str::uuid(), 'sender_role' => 'customer', 'content' => $question]);
        $c->messages()->create(['client_message_id' => (string) Str::uuid(), 'sender_role' => 'ai', 'content' => 'Reply', 'ai_trace' => ['sourceMessageId' => $source->id, 'decision' => $decision], 'delivery_status' => 'sent']);

        return $source;
    }

    private function staff(SupportMessage $source, string $answer = 'Assembly is not included. Contact support with your postcode.'): void
    {
        SupportMessage::create(['conversation_id' => $source->conversation_id, 'client_message_id' => (string) Str::uuid(), 'sender_role' => 'human_agent', 'staff_user_id' => auth()->id(), 'content' => $answer]);
    }

    public function test_open_missing_question_can_be_manually_drafted_without_resolving_or_publishing():void
    {
        $source=$this->example(state:'open');$this->getJson('/admin/api/support/improvement')->assertOk()->assertJsonPath('questions.0.canCreateDraft',true);
        $this->postJson('/admin/api/support/improvement/drafts',['sourceMessageId'=>$source->id])->assertCreated()->assertJsonPath('draft.answer','');
        $this->assertSame('open',$source->conversation->fresh()->status);$this->assertSame([],app(SupportFaqService::class)->read()['faq']['items']);
    }
    public function test_legacy_zero_fact_coverage_fallback_is_recovered_but_real_coverage_errors_stay_failures():void
    {
        $source=$this->example('coverage_incomplete','whatsapp',state:'open');$reply=$source->conversation->messages()->where('sender_role','ai')->first();
        $reply->update(['ai_trace'=>['sourceMessageId'=>$source->id,'decision'=>'coverage_incomplete','retrievedSourceIds'=>[],'tools'=>[['tool'=>'faq.search','status'=>'no_match','sourceIds'=>[]]]]]);
        $this->getJson('/admin/api/support/improvement')->assertOk()->assertJsonPath('counts.improve',1)->assertJsonPath('counts.failures',0);
        $this->postJson('/admin/api/support/improvement/drafts',['sourceMessageId'=>$source->id])->assertCreated();
        $real=$this->example('coverage_incomplete','instagram',state:'open');
        $this->getJson('/admin/api/support/improvement?channel=instagram')->assertOk()->assertJsonPath('counts.failures',1)->assertJsonPath('failures.0.canCreateDraft',true);
        $this->postJson('/admin/api/support/improvement/drafts',['sourceMessageId'=>$real->id])->assertCreated();
    }
    public function test_overview_does_not_repeat_activation_setting_query_for_each_question():void
    {
        for($i=0;$i<8;$i++)$this->example(question:'Question number '.$i,state:'open');
        DB::flushQueryLog();DB::enableQueryLog();app(\App\Services\Support\SupportImprovementService::class)->overview(7,'all');
        $queries=collect(DB::getQueryLog())->filter(fn($q)=>in_array('support_improvement',$q['bindings'],true));DB::disableQueryLog();$this->assertCount(1,$queries);
    }
    public function test_observed_counts_filters_and_missing_knowledge_are_separate_from_failures(): void
    {
        $s = $this->example();
        $this->example('generation_unavailable', 'facebook');
        $this->example('coverage_incomplete', 'whatsapp');
        $this->example('greeting', 'storefront', 'Hello');
        $this->getJson('/admin/api/support/improvement')->assertOk()->assertJsonPath('counts.received', 4)->assertJsonPath('counts.improve', 1)->assertJsonPath('counts.failures', 2)->assertJsonCount(1, 'questions')->assertJsonCount(2, 'failures');
        $this->getJson('/admin/api/support/improvement?channel=facebook')->assertOk()->assertJsonCount(0, 'questions')->assertJsonPath('counts.failures', 1);
        $this->getJson('/admin/api/support/improvement?channel=invalid')->assertUnprocessable();
        $s->update(['created_at' => now()->subDays(31)]);
        $this->getJson('/admin/api/support/improvement?days=30')->assertOk()->assertJsonCount(0, 'questions');
    }

    public function test_draft_survives_edits_and_only_published_answer_enters_faq_once(): void
    {
        $s = $this->example();
        $this->staff($s);
        $d = $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $s->id])->assertCreated()->assertJsonPath('draft.status', 'Needs review')->assertJsonPath('draft.version', 1)->json('draft');
        $this->assertSame([], app(SupportFaqService::class)->read()['faq']['items']);
        $this->assertSame([], app(FaqKnowledgeSearch::class)->search('Bookcase assembly options', app(SupportFaqService::class)->read()['faq']));
        $this->patchJson('/admin/api/support/improvement/drafts/'.$d['id'], ['version' => 1, 'question' => $s->content, 'answer' => 'Assembly is available only after our team confirms the fee.', 'category' => 'others'])->assertOk()->assertJsonPath('draft.version', 2);
        $this->getJson('/admin/api/support/improvement/drafts')->assertOk()->assertJsonPath('drafts.0.answer', 'Assembly is available only after our team confirms the fee.');
        $url = '/admin/api/support/improvement/drafts/'.$d['id'].'/publish';
        $this->postJson($url, ['version' => 2, 'policyConfirmed' => false])->assertUnprocessable();
        $this->postJson($url, ['version' => 2, 'policyConfirmed' => true])->assertOk()->assertJsonPath('draft.status', 'Published');
        $this->postJson($url, ['version' => 2, 'policyConfirmed' => true])->assertOk();
        $items = app(SupportFaqService::class)->read()['faq']['items'];
        $this->assertCount(1, $items);
        $this->assertTrue($items[0]['isActive']);
        $this->assertSame('Assembly is available only after our team confirms the fee.', $items[0]['answer']);
        $this->assertCount(1, app(FaqKnowledgeSearch::class)->exact($s->content, app(SupportFaqService::class)->read()['faq']));
        $this->assertCount(1, app(FaqKnowledgeSearch::class)->search('Bookcase assembly options', app(SupportFaqService::class)->read()['faq']));
    }

    public function test_source_rules_empty_draft_privacy_conflicts_and_dismissal(): void
    {
        $s = $this->example(question: 'Order QA-12345. My email is person@example.com. Can you assemble it?');
        $d = $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $s->id])->assertCreated()->json('draft');
        $this->assertSame('', $d['answer']);
        $this->assertStringNotContainsString('person@example.com', $d['question']);
        $this->assertStringNotContainsString('QA-12345', $d['question']);
        $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $s->id])->assertOk()->assertJsonPath('draft.id', $d['id']);
        $this->patchJson('/admin/api/support/improvement/drafts/'.$d['id'], ['version' => 2, 'question' => 'Safe', 'answer' => 'Safe', 'category' => 'others'])->assertConflict();
        $this->postJson('/admin/api/support/improvement/drafts/'.$d['id'].'/dismiss', ['version' => 1])->assertOk()->assertJsonPath('draft.status', 'Dismissed');
        $this->postJson('/admin/api/support/improvement/drafts/'.$d['id'].'/publish', ['version' => 2, 'policyConfirmed' => true])->assertConflict();
        $open = $this->example(state: 'open');
        $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $open->id])->assertCreated();
        $bad = $this->example('generation_unavailable');
        $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $bad->id])->assertUnprocessable();
    }

    public function test_segments_do_not_double_count_and_missing_source_cannot_be_published(): void
    {
        $s = $this->example();
        $this->staff($s);
        SupportMessage::create(['conversation_id' => $s->conversation_id, 'client_message_id' => (string) Str::uuid(), 'sender_role' => 'ai', 'content' => 'Second segment', 'ai_trace' => ['sourceMessageId' => $s->id, 'decision' => 'no_match'], 'delivery_status' => 'sent']);
        $this->getJson('/admin/api/support/improvement')->assertOk()->assertJsonPath('counts.received', 1)->assertJsonPath('counts.improve', 1);
        $d = $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $s->id])->assertCreated()->json('draft');
        $s->delete();
        $this->postJson('/admin/api/support/improvement/drafts/'.$d['id'].'/publish', ['version' => 1, 'policyConfirmed' => true])->assertUnprocessable();
        $this->assertSame([], app(SupportFaqService::class)->read()['faq']['items']);
    }

    public function test_blank_answers_are_saveable_but_not_publishable_and_settings_are_preserved(): void
    {
        $s = $this->example();
        $d = $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $s->id])->assertCreated()->json('draft');
        $this->patchJson('/admin/api/support/improvement/drafts/'.$d['id'], ['version' => 1, 'question' => 'Assembly arrangements', 'answer' => '', 'category' => 'others'])->assertOk();
        $url = '/admin/api/support/improvement/drafts/'.$d['id'].'/publish';
        $this->postJson($url, ['version' => 2, 'policyConfirmed' => true])->assertUnprocessable();
        $this->patchJson('/admin/api/support/improvement/drafts/'.$d['id'], ['version' => 2, 'question' => 'Assembly arrangements', 'answer' => 'Please contact our team to confirm assembly availability.', 'category' => 'others'])->assertOk();
        $row = StoreSetting::where('key', 'support_faq')->first();
        $value = $row->value;
        $value['personality'] = ['displayName' => 'Keep this'];
        $row->update(['value' => $value]);
        $this->postJson($url, ['version' => 3, 'policyConfirmed' => true])->assertOk();
        $this->assertSame('Keep this', app(SupportFaqService::class)->read()['personality']['displayName']);
    }

    public function test_permissions_and_activation_boundary(): void
    {
        $s = $this->example();
        StoreSetting::where('key', 'support_improvement')->update(['value' => ['activatedAt' => now()->addHour()->toIso8601String()]]);
        $this->getJson('/admin/api/support/improvement')->assertOk()->assertJsonPath('counts.received', 0);
        $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $s->id])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]));
        $this->getJson('/admin/api/support/improvement')->assertForbidden();
        $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $s->id])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => false, 'is_super_admin' => false]));
        $this->getJson('/admin/api/support/improvement')->assertUnauthorized();
    }

    public function test_common_private_references_addresses_and_paged_older_drafts(): void
    {
        $s = $this->example(question: 'Order 12345; Tracking AB123456789MY; Address: 123 Main Street');
        $this->staff($s, 'Send it to 123 Main Street. Tracking AB123456789MY.');
        $d = $this->postJson('/admin/api/support/improvement/drafts', ['sourceMessageId' => $s->id])->assertCreated()->json('draft');
        foreach (['12345', 'AB123456789MY', '123 Main Street'] as $private) {
            $this->assertStringNotContainsString($private, $d['question']);
            $this->assertStringNotContainsString($private, $d['answer']);
        }
        $rows = [];
        for ($i = 0; $i < 200; $i++) {
            $rows[] = ['question' => 'Reviewed example '.$i, 'answer' => 'Example', 'category' => 'others', 'channel' => 'storefront', 'status' => 'Dismissed', 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('support_faq_drafts')->insert($rows);
        $first = $this->getJson('/admin/api/support/improvement/drafts')->assertOk()->assertJsonPath('pending',1)->assertJsonPath('limited',true)->json();
        $this->getJson('/admin/api/support/improvement/drafts?before='.$first['nextCursor'])->assertOk()->assertJsonPath('drafts.0.id',$d['id'])->assertJsonPath('nextCursor',null);
    }
}
