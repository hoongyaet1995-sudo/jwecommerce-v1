<?php

namespace Tests\Feature;

use App\Models\MarketingResearchBudget;
use App\Models\MarketingResearchRun;
use App\Models\User;
use App\Services\Marketing\MarketingResearchProcessor;
use App\Services\Marketing\MarketingResearchWorkflow;
use App\Services\Marketing\MarketingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingResearchWorkflowTest extends TestCase
{
    use RefreshDatabase, MarketingResearchFixtures;

    private function network(array $overrides = []): void
    {
        Http::fake(array_replace([
            'api.openai.com/v1/responses' => function ($r) {
                $input = json_decode(explode(' DATA: ', $r['input'], 2)[1], true);
                $data = isset($input['offer']) ? ['queries' => [['query' => 'industry product tour Malaysia', 'role' => 'same_industry'], ['query' => 'industry supplier demonstration', 'role' => 'same_industry'], ['query' => 'cross industry space creative film', 'role' => 'cross_industry']]] : ['audience_hypotheses' => ['A possible use.'], 'observations' => [['source_id' => $input['sources'][0]['id'], 'supporting_excerpt' => 'Equipment needs a space.', 'observation' => 'Text explains a use.', 'inference' => false]], 'adaptation_methods' => [['source_ids' => [$input['sources'][0]['id']], 'method' => 'Start with use.', 'reason' => 'Relevant to the stated offer.', 'limitations' => 'Publisher transcript only.']], 'limitations' => ['No recent performance comparison.']];
                if (isset($input['sources'])) {
                    $data['observations'] = array_map(fn ($s) => ['source_id' => $s['id'], 'supporting_excerpt' => 'Equipment needs a space.', 'observation' => 'Text explains a use.', 'inference' => false], $input['sources']);
                }
                return Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]]);
            },
            'api.tavily.com/search' => function ($r) {
                $prefix = str_contains($r['query'], 'cross') ? 'cross' : (str_contains($r['query'], 'supplier') ? 'supplier' : 'industry');
                return Http::response(['results' => [['url' => 'https://example.com/'.$prefix.'/one', 'title' => 'Video reference'], ['url' => 'https://example.org/'.$prefix.'/two', 'title' => 'Video reference']], 'request_id' => 'search-receipt', 'usage' => ['credits' => 1]]);
            },
            'api.tavily.com/extract' => fn ($r) => Http::response(['results' => array_map(fn ($url) => ['url' => $url, 'raw_content' => "Video Transcript:\nEquipment needs a space. https://www.youtube.com/watch?v=".substr(hash('sha256', $url), 0, 11)], $r['urls']), 'request_id' => 'extract-receipt', 'usage' => ['credits' => 0]]),
        ], $overrides));
    }

    public function test_complete_pipeline_is_reopened_and_duplicate_job_does_not_repeat_calls(): void
    {
        $p = $this->researchProject();
        $approved = (string) Str::uuid();
        $p->update(['confirmed_revision_id' => $approved]);
        $this->network();
        $run = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        app(MarketingResearchProcessor::class)->process($run->id);
        $this->assertSame('available', $run->fresh()->status);
        $this->assertSame($run->id, $p->fresh()->current_research_run_id);
        $this->assertSame($approved, $p->fresh()->confirmed_revision_id);
        $this->assertNull($run->fresh()->reported_cost_microusd);
        $this->assertSame(0, $run->fresh()->analysis['coverage']['video_observations']);
        $this->getJson('/admin/marketing/projects/'.$p->id.'/research/'.$run->id)->assertOk()->assertJsonPath('input_matches_current', true)->assertJsonPath('execution_mode', 'simulated');
        app(MarketingResearchProcessor::class)->process($run->id);
        Http::assertSentCount(7);
        $this->assertCount(7, $run->fresh()->calls);
        app(MarketingWorkflow::class)->saveBrief($p->id, $p->owner_id, $p->fresh()->version, ['market' => 'Singapore']);
        $this->assertNull($p->fresh()->current_research_run_id);
        $this->assertSame('Malaysia', $run->fresh()->input_snapshot['brief']['market']);
    }

    public function test_duplicate_job_and_timeout_do_not_repeat_paid_step(): void
    {
        $p = $this->researchProject();
        $attempts = 0;
        $this->network(['api.tavily.com/search' => function () use (&$attempts) { $attempts++; throw new \Illuminate\Http\Client\ConnectionException('fixture transport timeout'); }]);
        $run = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        app(MarketingResearchProcessor::class)->process($run->id);
        $this->assertSame('unknown', $run->fresh()->status);
        $this->assertSame(80000, MarketingResearchBudget::first()->held_microusd);
        $this->assertNull($p->fresh()->active_research_run_id);
        app(MarketingResearchProcessor::class)->process($run->id);
        $this->assertSame(1, $attempts);
    }

    public function test_two_projects_cannot_overreserve_same_budget(): void
    {
        $p = $this->researchProject();
        $flow = app(MarketingResearchWorkflow::class);
        $flow->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        $other = app(MarketingWorkflow::class)->create(User::find($p->owner_id), ['source_type' => 'theme', 'theme' => 'Another independent service']);
        $other->update(['brief' => $p->brief]);
        try { $flow->start($other->id, $other->owner_id, $other->version, (string) Str::uuid()); $this->fail('Budget must be enforced across projects.'); }
        catch (ValidationException) {
            $this->assertSame(1, MarketingResearchRun::count());
            $this->assertSame(80000, MarketingResearchBudget::first()->held_microusd);
            Http::assertNothingSent();
        }
    }

    public function test_changed_input_never_receives_stale_research(): void
    {
        $p = $this->researchProject();
        $run = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        $p->refresh()->update(['brief' => array_replace($p->brief, ['market' => 'Singapore'])]);
        app(MarketingResearchProcessor::class)->process($run->id);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('stale_input', $run->fresh()->error_code);
        $this->assertNull($p->fresh()->current_research_run_id);
        Http::assertNothingSent();
    }

    public function test_interruption_is_explicit_and_current_text_changes_are_locked(): void
    {
        $p = $this->researchProject();
        $run = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        $this->postJson('/admin/marketing/projects/'.$p->id.'/brief', ['version' => $p->fresh()->version, 'step' => 'goal', 'goal' => 'introduction', 'cta' => 'Learn more'])->assertConflict();
        $url = '/admin/marketing/projects/'.$p->id.'/research/'.$run->id.'/interrupted';
        $this->postJson($url, ['version' => $p->fresh()->version, 'acknowledge' => true])->assertConflict();
        $run->update(['created_at' => now()->subMinutes(11)]);
        $this->postJson($url, ['version' => $p->fresh()->version, 'acknowledge' => true])->assertOk()->assertJsonPath('status', 'unknown');
        $this->assertSame(80000, MarketingResearchBudget::first()->held_microusd);
    }

    public function test_fixture_industry_is_not_hardcoded_to_containers(): void
    {
        $p = $this->researchProject('A photography service introduction');
        $this->network();
        $run = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        app(MarketingResearchProcessor::class)->process($run->id);
        $this->assertSame('A photography service introduction', $run->fresh()->analysis['business_facts']['snapshot']['title']);
        $this->assertStringNotContainsString('20ft', json_encode($run->fresh()->analysis));
    }

    public function test_all_failed_extracts_are_persisted_and_never_claim_available(): void
    {
        $p = $this->researchProject();
        $this->network(['api.tavily.com/extract' => fn ($r) => Http::response(['results' => [], 'failed_results' => array_map(fn ($url) => ['url' => $url, 'error' => 'do not retain upstream private body'], $r['urls'])])]);
        $run = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        app(MarketingResearchProcessor::class)->process($run->id);
        $this->assertSame('insufficient', $run->fresh()->status);
        $this->assertNull($p->fresh()->current_research_run_id);
        foreach ($run->fresh()->sources as $source) {
            $this->assertSame('failed', $source['extraction_status']);
            $this->assertSame('content_unavailable', $source['extraction_error_code']);
        }
        $this->assertStringNotContainsString('upstream private', json_encode($run->fresh()->toArray()));
        Http::assertSentCount(6);
    }
}
