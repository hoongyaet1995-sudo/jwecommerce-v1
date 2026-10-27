<?php

namespace Tests\Feature;

use App\Services\Marketing\MarketingResearchProcessor;
use App\Services\Marketing\MarketingResearchWorkflow;
use App\Services\Marketing\Research\TavilyFreeCreditGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingResearchFreeTest extends TestCase
{
    use RefreshDatabase, MarketingResearchFixtures;

    private function freeProject()
    {
        $p = $this->researchProject();
        config(['marketing.research.simulated' => false, 'marketing.research.free_probe_enabled' => true, 'marketing.research.free_probe_project' => $p->id, 'marketing.research.approved_queries' => ['queries' => [['query' => 'product tour supplier Malaysia', 'role' => 'same_industry'], ['query' => 'product uses film', 'role' => 'same_industry'], ['query' => 'space challenge creative film', 'role' => 'cross_industry']]]]);
        return $p;
    }

    public function test_free_retrieval_uses_only_tavily_and_never_claims_ai_analysis_complete(): void
    {
        $p = $this->freeProject();
        Http::fake([
            'api.tavily.com/usage' => Http::response(['account' => ['current_plan' => 'Researcher', 'plan_usage' => 0, 'plan_limit' => 1500, 'paygo_usage' => 0], 'key' => ['usage' => 0, 'limit' => null]]),
            'api.tavily.com/search' => Http::response(['results' => [['url' => 'https://example.com/video', 'content' => 'Public video listing']], 'usage' => ['credits' => 1]]),
            'api.tavily.com/extract' => Http::response(['results' => [['url' => 'https://example.com/video', 'raw_content' => "Video Transcript:\nA product use. https://youtube.com/watch?v=abcdefghijk"]], 'failed_results' => [], 'usage' => ['credits' => 0]]),
        ]);
        $flow = app(MarketingResearchWorkflow::class);
        $run = $flow->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        app(MarketingResearchProcessor::class)->process($run->id);
        $this->assertSame('partial', $run->fresh()->status);
        $this->assertSame('live_free_retrieval', $run->fresh()->pricing_snapshot['execution_mode']);
        $this->assertFalse($run->fresh()->analysis['text_ai_analyzed']);
        $this->assertSame([], $run->fresh()->analysis['observations']);
        $this->assertSame(0, $run->fresh()->reserved_microusd);
        $this->assertCount(4, $run->fresh()->calls);
        Http::assertNotSent(fn ($r) => ! str_starts_with($r->url(), 'https://api.tavily.com/'));
        app(MarketingResearchProcessor::class)->process($run->id);
        Http::assertSentCount(9); // preflight plus usage+request for four retrieval steps
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $flow->start($p->id, $p->owner_id, $p->fresh()->version, (string) Str::uuid());
    }

    public function test_nonfree_unknown_or_exhausted_accounts_are_rejected(): void
    {
        foreach ([['current_plan' => 'PAYGO', 'plan_usage' => 0, 'plan_limit' => 1500, 'paygo_usage' => 0], ['current_plan' => 'Researcher', 'plan_usage' => 1499, 'plan_limit' => 1500, 'paygo_usage' => 0], ['current_plan' => 'Researcher', 'plan_usage' => 0, 'plan_limit' => 1500], ['current_plan' => 'Researcher', 'plan_usage' => 0, 'plan_limit' => 1500, 'paygo_usage' => 1]] as $account) {
            try { app(TavilyFreeCreditGuard::class)->validateUsage(['account' => $account, 'key' => []]); $this->fail('An unverified free account must be rejected.'); }
            catch (ValidationException) { $this->addToAssertionCount(1); }
        }
    }

    public function test_key_quota_and_project_scope_are_checked(): void
    {
        $p = $this->freeProject();
        config(['marketing.research.free_probe_project' => (string) Str::uuid()]);
        $this->assertFalse(app(MarketingResearchWorkflow::class)->freeRetrieval($p->id));
        $this->expectException(ValidationException::class);
        app(TavilyFreeCreditGuard::class)->validateUsage(['account' => ['current_plan' => 'Researcher', 'plan_usage' => 0, 'plan_limit' => 1500, 'paygo_usage' => 0], 'key' => ['usage' => 99, 'limit' => 100]]);
    }
}
