<?php

namespace Tests\Concerns;

use App\Models\MarketingResearchBudget;
use App\Models\User;
use App\Services\Marketing\MarketingWorkflow;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

trait MarketingResearchFixtures
{
    protected function researchProject(string $title = 'A furniture store introduction')
    {
        Http::preventStrayRequests();
        Queue::fake();
        $owner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($owner);
        \App\Models\AiConnection::create(['provider' => 'openai', 'enabled' => true, 'verified_at' => now(), 'text_model' => 'gpt-5.6-luna', 'api_key' => 'fixture-only']);
        $p = app(MarketingWorkflow::class)->create($owner, ['source_type' => 'theme', 'theme' => $title]);
        $p->update(['brief' => array_replace($p->brief, ['source_confirmed' => true, 'facts_confirmed' => true, 'goal_confirmed' => true, 'audience_confirmed' => true, 'market' => 'Malaysia', 'language' => 'English', 'platforms' => ['Facebook']])]);
        $budget = MarketingResearchBudget::create(['authorization_reference' => 'SIMULATED fixture only', 'approved_microusd' => 100000, 'enabled' => true]);
        config(['marketing.research' => ['enabled' => true, 'simulated' => true, 'live_enabled' => false, 'api_key' => 'fixture-only', 'budget_id' => $budget->id, 'text_model' => 'gpt-5.6-luna', 'text_call_cap_microusd' => 20000, 'pricing_reference' => 'SIMULATED fixture prices', 'pricing_verified_at' => now()->toIso8601String()]]);
        return $p->fresh();
    }
}
