<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\MarketingAiRun;
use App\Models\MarketingProject;
use App\Models\User;
use App\Services\Marketing\MarketingPlanningConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingPlanningConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function project()
    {
        Http::preventStrayRequests();
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($u);
        $this->post('/admin/marketing/workspace/projects', ['request_id' => (string) Str::uuid(), 'source_kind' => 'theme', 'theme' => 'Our genuine container introduction', 'video_type' => 'presenter']);
        $p = MarketingProject::firstOrFail();
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', ['request_id' => (string) Str::uuid(), 'version' => $p->version, 'language' => 'zh-MY', 'platforms' => ['Facebook'], 'duration' => 'auto', 'subtitles' => '1', 'narration' => '1', 'voice' => 'ai', 'music' => 'later']);

        return $p->fresh();
    }

    private function answer($p, $field, $value)
    {
        return $this->post('/admin/marketing/workspace/'.$p->id.'/answer', ['request_id' => (string) Str::uuid(), 'version' => $p->fresh()->version, 'field' => $field, 'value' => $value]);
    }

    public function test_missing_information_blocks_final_confirmation_then_real_answers_confirm_snapshot(): void
    {
        $p = $this->project();
        $this->post('/admin/marketing/workspace/'.$p->id.'/confirm', ['request_id' => (string) Str::uuid(), 'version' => $p->version])->assertSessionHasErrors('planning');
        foreach (['goal' => 'appointment', 'audience' => 'Businesses needing container options', 'market' => 'Malaysia', 'duration_seconds' => 15, 'pain_point' => 'Choosing a container for a specific use', 'differentiator' => 'Only confirmed company information', 'tone' => '亲切自然', 'cta' => '预约咨询'] as $field => $value) {
            $this->answer($p, $field, $value)->assertRedirect();
        }
        $p->refresh();
        $id = (string) Str::uuid();
        $body = ['request_id' => $id, 'version' => $p->version];
        $this->post('/admin/marketing/workspace/'.$p->id.'/confirm', $body)->assertRedirect();
        $this->post('/admin/marketing/workspace/'.$p->id.'/confirm', $body)->assertRedirect();
        $p->refresh();
        $this->assertSame('confirmed', $p->planning_state['status']);
        $this->assertSame('appointment', $p->brief['goal']);
        $this->assertSame('预约咨询', $p->brief['cta']);
        $this->assertTrue($p->brief['facts_confirmed']);
        $this->assertSame('concepts', $p->stage);
        $this->assertSame(0, MarketingAiRun::count());
    }

    public function test_ai_suggestion_never_confirms_fields_and_does_not_replace_confirmed_answers(): void
    {
        $p = $this->project();
        $this->answer($p, 'tone', '商家已确认的调性')->assertRedirect();
        $service = app(MarketingPlanningConfirmation::class);
        $service->suggest($p->id, $p->owner_id, $p->fresh()->version, ['tone' => '模型的新调性', 'pain_point' => '建议的痛点'], 'saved_ai_test');
        $p->refresh();
        $this->assertSame('商家已确认的调性', $p->planning_state['fields']['tone']['value']);
        $this->assertSame('suggested', $p->planning_state['fields']['pain_point']['status']);
        $this->assertFalse($p->brief['facts_confirmed']);
    }

    public function test_budgetless_ai_route_makes_no_request_and_preview_stays_read_only(): void
    {
        $p = $this->project();
        $this->post('/admin/marketing/workspace/'.$p->id.'/suggest', ['request_id' => (string) Str::uuid(), 'version' => $p->version, 'feedback' => '帮我整理'])->assertSessionHasErrors('budget');
        $this->assertSame(0, MarketingAiRun::count());
        $this->get('/admin/marketing/workspace-preview?project='.$p->id.'&screen=guided')->assertOk();
    }

    private function enableAgent($p, int $calls = 1): void
    {
        config(['marketing.agent' => ['enabled' => true, 'project' => $p->id, 'owner' => $p->owner_id, 'authorization' => (string) Str::uuid(), 'budget_usd' => '0.20', 'max_calls' => $calls, 'pricing_verified_at' => now()->toIso8601String()]]);
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'verified_at' => now(), 'text_model' => 'gpt-5.6-luna', 'api_key' => 'fixture-only']);
    }

    private function proposal(): array
    {
        return ['profile' => ['offer' => 'Container introduction', 'audience' => 'Businesses choosing containers', 'goal' => 'enquiry', 'platforms' => ['Facebook'], 'duration_seconds' => 15, 'pain_point' => 'Finding a suitable enclosed space', 'differentiator' => 'Introduce confirmed container options', 'tone' => '亲切专业'], 'questions' => [], 'reason' => 'Based on saved business facts; merchant confirmation required'];
    }

    public function test_agent_suggestion_is_one_request_idempotent_and_bounded_by_its_own_grant(): void
    {
        $p = $this->project();
        $this->enableAgent($p);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($this->proposal())]]]]])]);
        $body = ['request_id' => (string) Str::uuid(), 'version' => $p->version, 'feedback' => '请整理建议'];
        $this->post('/admin/marketing/workspace/'.$p->id.'/suggest', $body)->assertRedirect();
        $this->assertSame('completed', MarketingAiRun::firstOrFail()->status, MarketingAiRun::firstOrFail()->error ?? '');
        $this->post('/admin/marketing/workspace/'.$p->id.'/suggest', $body)->assertRedirect();
        Http::assertSentCount(1);
        $p->refresh();
        $this->assertSame('suggested', $p->planning_state['fields']['pain_point']['status']);
        $this->assertFalse($p->brief['facts_confirmed']);
        $this->assertNull($p->busy_run_id);
        $this->assertSame('0.0200', MarketingAiRun::firstOrFail()->reserved_usd);
        $this->post('/admin/marketing/workspace/'.$p->id.'/suggest', array_replace($body, ['request_id' => (string) Str::uuid(), 'version' => $p->version]))->assertSessionHasErrors('budget');
        Http::assertSentCount(1);
    }

    public function test_invalid_ai_output_retains_prior_planning_state_and_is_not_retried(): void
    {
        $p = $this->project();
        $this->enableAgent($p);
        $before = $p->planning_state;
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => 'invalid-json']]]]])]);
        $body = ['request_id' => (string) Str::uuid(), 'version' => $p->version];
        $this->post('/admin/marketing/workspace/'.$p->id.'/suggest', $body)->assertSessionHasErrors('agent');
        Http::assertSentCount(1);
        $this->assertSame($before, $p->fresh()->planning_state);
        $this->assertNull($p->fresh()->busy_run_id);
        $this->assertSame('failed', MarketingAiRun::firstOrFail()->status);
        $this->post('/admin/marketing/workspace/'.$p->id.'/suggest', $body)->assertStatus(409);
        Http::assertSentCount(1);
    }

    public function test_ai_reducer_supports_bound_run_lock(): void
    {
        $p = $this->project();
        $request = (string) Str::uuid();
        $run = MarketingAiRun::create(['project_id' => $p->id, 'request_id' => $request, 'action' => 'agent_suggest', 'status' => 'running']);
        $p->update(['busy_run_id' => $run->id, 'version' => $p->version + 1]);
        $p = app(MarketingPlanningConfirmation::class)->suggest($p->id, $p->owner_id, $p->version, $this->proposal()['profile'], 'ai_suggested', $run->id, $request);
        $this->assertSame('suggested', $p->planning_state['fields']['pain_point']['status']);
    }

    public function test_summary_displays_the_offer_that_will_be_confirmed(): void
    {
        $p = $this->project();
        $snapshot = $p->snapshot;
        $this->answer($p, 'offer', 'Approved merchant offer')->assertRedirect();
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=brief')->assertOk()->assertViewHas('summary', fn ($summary) => in_array('Approved merchant offer', $summary, true));
        $this->assertSame($snapshot, $p->fresh()->snapshot);
    }

    public function test_proposed_goal_and_nonstandard_duration_are_presented_before_acceptance(): void
    {
        $p = $this->project();
        $this->answer($p, 'goal', 'appointment');
        $this->answer($p, 'duration_seconds', 15);
        $service = app(MarketingPlanningConfirmation::class);
        $service->suggest($p->id, $p->owner_id, $p->fresh()->version, ['goal' => 'enquiry', 'duration_seconds' => 20], 'saved_ai_test');
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk()->assertDontSee('id="ws-goal-value"', false);
        $this->assertSame('appointment', $p->fresh()->planning_state['fields']['goal']['value']);
        $this->answer($p, 'goal', 'enquiry');
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk()->assertSee('项目对话消息');
        $this->assertSame(15,$p->fresh()->planning_state['fields']['duration_seconds']['value']);
        $this->answer($p,'duration_seconds',20);
        $this->assertSame(20,$p->fresh()->planning_state['fields']['duration_seconds']['value']);
    }
}
