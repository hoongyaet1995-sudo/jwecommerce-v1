<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\MarketingAiRun;
use App\Models\MarketingConversationMessage;
use App\Models\MarketingProject;
use App\Models\User;
use App\Services\Marketing\MarketingConversation;
use App\Services\Marketing\MarketingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketing.conversation.open_testing' => false]);
    }

    private function project(): MarketingProject
    {
        Http::preventStrayRequests();
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($u);
        $p = app(MarketingWorkflow::class)->create($u, ['source_type' => 'theme', 'theme' => 'JTG genuine company introduction']);
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', ['request_id' => (string) Str::uuid(), 'version' => $p->version, 'language' => 'zh-MY', 'platforms' => ['Facebook'], 'duration' => '15', 'subtitles' => 1, 'narration' => 1, 'voice' => 'ai', 'music' => 'none'])->assertRedirect();

        return $p->fresh();
    }

    public function test_saved_conversation_reopens_across_steps_without_business_changes_or_paid_calls(): void
    {
        $p = $this->project();
        $before = $p->getRawOriginal();
        $body = ['request_id' => (string) Str::uuid(), 'screen' => 'guided', 'message' => '这支视频主要给第一次购买货柜的人看。'];
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        $this->postJson($url, $body)->assertOk()->assertJsonPath('messages.0.role', 'user')->assertJsonPath('messages.1.role', 'system');
        $this->postJson($url, $body)->assertOk();
        $this->assertSame(2, MarketingConversationMessage::count());
        $this->assertSame($before, $p->fresh()->getRawOriginal());
        foreach (['guided', 'brief', 'concepts', 'materials', 'confirmation', 'finished'] as $s) {
            $this->get('/admin/marketing/workspace?project='.$p->id.'&screen='.$s)->assertOk()->assertSee($body['message']);
        }
        $this->assertSame(0, MarketingAiRun::count());
        Http::assertNothingSent();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))->getJson($url)->assertNotFound();
        $this->postJson($url, $body)->assertNotFound();
    }

    private function enable($p): void
    {
        config(['marketing.conversation.enabled' => true, 'marketing.agent' => ['enabled' => true, 'project' => $p->id, 'owner' => $p->owner_id, 'authorization' => (string) Str::uuid(), 'budget_usd' => '0.02', 'max_calls' => 1, 'pricing_verified_at' => now()->toIso8601String()]]);
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'verified_at' => now(), 'text_model' => 'gpt-5.6-luna', 'api_key' => 'fixture-only']);
    }

    public function test_scoped_ready_notice_does_not_claim_unlimited_testing(): void
    {
        $p = $this->project();
        $this->enable($p);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')
            ->assertOk()->assertSee('data-chat-ready="1"',false)->assertDontSee('真实AI已开放测试')->assertDontSee('本轮按已授权金额与次数测试');
    }

    public function test_off_topic_and_later_step_discussion_cannot_propose_brief_mutations(): void
    {
        $p = $this->project();
        $this->enable($p);
        config(['marketing.conversation.open_testing' => true]);
        $sequence = Http::fakeSequence();
        foreach (['off_topic', 'later'] as $scope) {
            $payload = $this->response();
            $value = json_decode($payload['output'][0]['content'][0]['text'], true);
            $value['topic_scope'] = $scope;
            $payload['output'][0]['content'][0]['text'] = json_encode($value);
            $sequence->push($payload);
            $before = $p->fresh()->getRawOriginal();
            $this->postJson('/admin/marketing/workspace/'.$p->id.'/conversation', ['request_id' => (string) Str::uuid(), 'screen' => 'guided', 'message' => $scope === 'off_topic' ? '今晚吃什么？' : '之后能用老板照片出镜吗？'])->assertOk();
            $reply = MarketingConversationMessage::where('project_id', $p->id)->latest('sequence')->first();
            $this->assertNull($reply->proposal);
            $this->assertStringContainsString('营销视频', $reply->content);
            $this->assertSame($before, $p->fresh()->getRawOriginal());
        }
    }

    public function test_concept_actions_have_one_feedback_region_without_internal_testing_copy(): void
    {
        $p=$this->project();$this->enable($p);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=concepts')->assertOk()
            ->assertSee('ws-concept-feedback')->assertDontSee('真实AI已开放测试')->assertDontSee('换Hook只调整开场');
    }

    public function test_creative_discussion_scope_is_explicit_and_does_not_delete_history():void
    {
        $p=$this->project();
        foreach(range(1,12) as $i)MarketingConversationMessage::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'request_id'=>(string)Str::uuid(),'screen'=>'concepts','sequence'=>$i,'role'=>'user','content'=>'Discussion '.$i,'status'=>'completed','base_version'=>$p->version]);
        $context=app(MarketingConversation::class)->planningDiscussion($p);
        $this->assertSame(12,$context['conversation_scope']['total_records']);$this->assertCount(4,$context['conversation']);
        $this->assertSame('Discussion 9',$context['conversation'][0]['content']);$this->assertSame(12,MarketingConversationMessage::count());
    }

    private function response(): array
    {
        return ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['topic_scope' => 'current', 'reply' => '建议将受众改为初次采购者；会使现有创意与分镜需要重新确认，尚未修改。', 'changes' => [['field' => 'audience', 'value' => '第一次采购货柜的企业客户']]])]]]]];
    }

    public function test_ai_proposal_is_discussion_until_separate_acceptance_and_cannot_reuse_stale_authority(): void
    {
        $p = $this->project();
        $this->enable($p);
        Http::fake(['api.openai.com/*' => Http::response($this->response())]);
        $body = ['request_id' => (string) Str::uuid(), 'screen' => 'concepts', 'message' => '改成给初次采购的人看'];
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        $this->postJson($url, $body)->assertOk()->assertJsonPath('messages.1.role', 'assistant');
        $reply = MarketingConversationMessage::where('role', 'assistant')->firstOrFail();
        $this->assertNotSame('第一次采购货柜的企业客户', $p->fresh()->planning_state['fields']['audience']['value']);
        $this->assertSame('pending', $reply->proposal_status);
        $this->postJson($url, $body)->assertOk();
        Http::assertSentCount(1);
        $this->postJson($url.'/'.$reply->id.'/decision', ['decision' => 'accept'])->assertOk();
        $this->assertSame('第一次采购货柜的企业客户', $p->fresh()->planning_state['fields']['audience']['value']);
        $this->assertSame('draft', $p->fresh()->planning_state['status']);
        $version = $p->fresh()->version;
        $this->postJson($url.'/'.$reply->id.'/decision', ['decision' => 'accept'])->assertOk();
        $this->assertSame($version, $p->fresh()->version);
        $this->postJson($url, array_replace($body, ['request_id' => (string) Str::uuid()]))->assertOk()->assertJsonPath('messages.3.role', 'system');
        Http::assertSentCount(1);
    }

    public function test_failed_response_is_persisted_without_retry_and_stale_proposal_is_blocked(): void
    {
        $p = $this->project();
        $this->enable($p);
        Http::fake(['api.openai.com/*' => Http::response($this->response())]);
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'finished', 'message' => '换受众'])->assertOk();
        $reply = MarketingConversationMessage::where('role', 'assistant')->firstOrFail();
        $this->post('/admin/marketing/workspace/'.$p->id.'/answer', ['request_id' => (string) Str::uuid(), 'version' => $p->fresh()->version, 'field' => 'tone', 'value' => '新的已确认调性'])->assertRedirect();
        $this->postJson($url.'/'.$reply->id.'/decision', ['decision' => 'accept'])->assertStatus(409);
        $this->postJson($url.'/'.$reply->id.'/decision', ['decision' => 'reject'])->assertOk();
        $this->assertSame('rejected', $reply->fresh()->proposal_status);
    }

    public function test_invalid_output_and_duplicate_message_do_not_retry(): void
    {
        $p = $this->project();
        $this->enable($p);
        $before = $p->getRawOriginal();
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'usage' => ['input_tokens' => 100, 'output_tokens' => 10, 'total_tokens' => 110], 'output' => [['content' => [['type' => 'output_text', 'text' => 'invalid']]]]])]);
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        $body = ['request_id' => (string) Str::uuid(), 'screen' => 'brief', 'message' => '帮我看看'];
        $this->postJson($url, $body)->assertOk()->assertJsonPath('messages.1.role', 'system');
        $this->postJson($url, $body)->assertOk();
        Http::assertSentCount(1);
        $this->assertSame($before, $p->fresh()->getRawOriginal());
        $this->assertSame('failed', MarketingAiRun::firstOrFail()->status);
        $this->assertSame(100, MarketingAiRun::firstOrFail()->usage['provider_usage']['input_tokens']);
    }

    public function test_goal_dependencies_are_shown_and_explicit_cta_wins(): void
    {
        $p = $this->project();
        $this->enable($p);
        $payload = $this->response();
        $payload['output'][0]['content'][0]['text'] = json_encode(['topic_scope' => 'current', 'reply' => '目标改为预约。', 'changes' => [['field' => 'goal', 'value' => 'appointment']]]);
        Http::fake(['api.openai.com/*' => Http::response($payload)]);
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'brief', 'message' => '改预约'])->assertOk();
        $reply = MarketingConversationMessage::where('role', 'assistant')->firstOrFail();
        $this->assertContains('cta', array_column($reply->proposal, 'field'));
        $this->postJson($url.'/'.$reply->id.'/decision', ['decision' => 'accept'])->assertOk()->assertJsonFragment(['proposal_status' => 'accepted'])->assertJsonPath('summary_html', fn ($v) => str_contains($v, 'data-workspace-summary'));
        $this->assertSame('预约咨询', $p->fresh()->planning_state['fields']['cta']['value']);
    }

    public function test_abandoned_pending_can_be_closed_without_retrying_provider(): void
    {
        $p = $this->project();
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        $m = MarketingConversationMessage::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'request_id' => (string) Str::uuid(), 'sequence' => 1, 'role' => 'system', 'screen' => 'brief', 'base_version' => $p->version, 'status' => 'pending', 'content' => '等待中']);
        $this->postJson($url.'/reconcile')->assertStatus(409);
        $this->travel(4)->minutes();
        $this->postJson($url.'/reconcile')->assertOk();
        $this->assertSame('completed', $m->fresh()->status);
        $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'brief', 'message' => '现在继续讨论'])->assertOk();
        Http::assertNothingSent();
    }

    public function test_explicit_cta_is_not_overwritten_by_goal_dependency(): void
    {
        $p = $this->project();
        $this->enable($p);
        $payload = $this->response();
        $payload['output'][0]['content'][0]['text'] = json_encode(['topic_scope' => 'current', 'reply' => '建议预约目标和指定行动出口。', 'changes' => [['field' => 'cta', 'value' => '预约看货柜'], ['field' => 'goal', 'value' => 'appointment']]]);
        Http::fake(['api.openai.com/*' => Http::response($payload)]);
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'brief', 'message' => '预约看货柜'])->assertOk();
        $reply = MarketingConversationMessage::where('role', 'assistant')->firstOrFail();
        $response = $this->postJson($url.'/'.$reply->id.'/decision', ['decision' => 'accept'])->assertOk();
        $this->assertSame('预约看货柜', $p->fresh()->planning_state['fields']['cta']['value']);
        $html = $response->json('summary_html');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xp = new \DOMXPath($dom);
        $this->assertSame(1, $xp->query('//*[@data-workspace-summary]')->length);
        $this->assertSame(2, $xp->query('//dl')->length);
        $this->assertSame('预约看货柜', $xp->query('//*[@id="edit-cta"]//textarea')->item(0)->textContent);
    }

    public function test_history_paginates_and_context_rejects_excess_instead_of_silently_forgetting(): void
    {
        $p = $this->project();
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        for ($i = 1; $i <= 52; $i++) {
            MarketingConversationMessage::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'request_id' => (string) Str::uuid(), 'sequence' => $i, 'role' => 'user', 'screen' => 'brief', 'base_version' => $p->version, 'content' => 'History '.$i]);
        }
        $this->getJson($url)->assertOk()->assertJsonCount(50, 'messages')->assertJsonPath('messages.0.sequence', 3);
        $this->getJson($url.'?before=3')->assertOk()->assertJsonCount(2, 'messages')->assertJsonPath('messages.0.sequence', 1);
        $other = app(MarketingWorkflow::class)->create(User::findOrFail($p->owner_id), ['source_type' => 'theme', 'theme' => 'Different project private facts']);
        $context = app(MarketingConversation::class)->context($p);
        $this->assertStringNotContainsString('Different project private facts', json_encode($context));
        $this->assertCount(52, $context['conversation']);
        $this->getJson('/admin/marketing/workspace/'.$other->id.'/conversation')->assertJsonCount(0, 'messages');
        MarketingConversationMessage::where('project_id', $p->id)->update(['content' => str_repeat('x', 500)]);
        $this->enable($p);
        $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'brief', 'message' => '继续'])->assertOk()->assertJsonFragment(['content' => '消息已保存。项目资料或对话已超过当前可靠上下文长度，本次未调用AI；需要先整理摘要，不会静默丢弃历史。']);
        Http::assertNothingSent();
        $this->assertSame(0, MarketingAiRun::count());
    }

    public function test_preparation_screens_have_no_conversation_and_guided_actions_follow_composer(): void
    {
        $p = $this->project();
        foreach (['source', 'preferences'] as $screen) {
            $this->get('/admin/marketing/workspace?project='.$p->id.'&screen='.$screen)->assertOk()->assertDontSee('id="ws-chat-config"', false)->assertDontSee('id="projectConversation"', false);
            $this->postJson('/admin/marketing/workspace/'.$p->id.'/conversation', ['request_id' => (string) Str::uuid(), 'screen' => $screen, 'message' => '不能在准备页面启动AI'])->assertUnprocessable();
        }
        $response = $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk();
        $response->assertDontSee('data-planning-actions',false)->assertSee('data-planning-guide',false)->assertSee('data-planning-confirmation',false);
        $this->assertDatabaseCount('marketing_conversation_messages', 0);
        Http::assertNothingSent();
    }

    public function test_unified_guided_view_uses_one_chat_input_and_no_direct_field_answer_box(): void
    {
        $p = $this->project();
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk()->assertDontSee('id="ws-question-value"', false)->assertDontSee('id="ws-goal-value"', false)->assertSee('data-chat-quick', false);
        $context = app(MarketingConversation::class)->context($p);
        $this->assertArrayHasKey('planning_checklist', $context);
        $this->assertNotEmpty($context['planning_checklist']['missing']);
    }

    public function test_accepting_final_missing_fields_refreshes_confirmation_readiness(): void
    {
        $p = $this->project();
        $this->enable($p);
        $payload = $this->response();
        $payload['output'][0]['content'][0]['text'] = json_encode(['topic_scope' => 'current', 'reply' => '请确认这组资料建议。', 'changes' => [['field' => 'audience', 'value' => '初次采购客户'], ['field' => 'market', 'value' => 'Malaysia'], ['field' => 'pain_point', 'value' => '不确定哪种规格适合需求'], ['field' => 'differentiator', 'value' => '通用封闭式货柜']]]);
        Http::fake(['api.openai.com/*' => Http::response($payload)]);
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'brief', 'message' => '请建议'])->assertOk();
        $m = MarketingConversationMessage::where('role', 'assistant')->firstOrFail();
        $r = $this->postJson($url.'/'.$m->id.'/decision', ['decision' => 'accept', 'screen' => 'brief'])->assertOk();
        $this->assertTrue($r->json('confirmation_ready'));
        $this->assertStringContainsString('data-planning-confirmation', $r->json('confirmation_html'));
        $html = $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=brief')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xp = new \DOMXPath($dom);
        $this->assertSame(0, $xp->query('//form[input[@name="field" and @value="tone"] and not(ancestor::*[@data-workspace-summary])]')->length);
        $this->assertSame(1, $xp->query('//input[@name="message"]')->length);
    }

    public function test_open_local_testing_is_not_limited_to_two_calls_and_distinguishes_balance_from_rate_limit(): void
    {
        $p = $this->project();
        $this->enable($p);
        config(['marketing.conversation.open_testing' => true, 'marketing.agent.enabled' => false]);
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->response())->push($this->response())->push($this->response())->push(['error' => ['code' => 'insufficient_quota']], 429)->push(['error' => ['code' => 'rate_limit_exceeded']], 429)]);
        $url = '/admin/marketing/workspace/'.$p->id.'/conversation';
        for ($i = 0; $i < 3; $i++) {
            $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'guided', 'message' => '第'.$i.'轮请给建议'])->assertOk();
        }
        Http::assertSentCount(3);
        $this->assertSame(3, MarketingAiRun::where('status', 'completed')->count());
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk()->assertSee('data-chat-ready="1"', false)->assertDontSee('本轮两次验收已结束');
        $r = $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'guided', 'message' => '检查额度'])->assertOk();
        $this->assertStringContainsString('充值', $r->json('messages')[7]['content']);
        $r = $this->postJson($url, ['request_id' => (string) Str::uuid(), 'screen' => 'guided', 'message' => '检查频率'])->assertOk();
        $this->assertStringContainsString('稍后', $r->json('messages')[9]['content']);
        $this->assertStringNotContainsString('充值', $r->json('messages')[9]['content']);
    }
}
