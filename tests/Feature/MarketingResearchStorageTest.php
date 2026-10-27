<?php

namespace Tests\Feature;

use App\Jobs\ProcessMarketingResearch;
use App\Models\MarketingResearchRun;
use App\Models\User;
use App\Services\Marketing\MarketingResearchWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingResearchStorageTest extends TestCase
{
    use RefreshDatabase, MarketingResearchFixtures;

    public function test_research_is_owned_versioned_and_append_only(): void
    {
        $p = $this->researchProject();
        $snapshot = $p->snapshot;
        $request = ['version' => $p->version, 'request_id' => (string) Str::uuid()];
        $url = '/admin/marketing/projects/'.$p->id.'/research';
        $id = $this->postJson($url, $request)->assertCreated()->assertJsonPath('execution_mode', 'simulated')->json('id');
        $this->postJson($url, $request)->assertCreated()->assertJsonPath('id', $id);
        Queue::assertPushed(ProcessMarketingResearch::class, 1);
        $this->assertSame($snapshot, $p->fresh()->snapshot);
        $this->assertNull($p->fresh()->confirmed_revision_id);
        $this->assertSame(1, MarketingResearchRun::count());
        $this->getJson($url.'/'.$id)->assertOk()->assertJsonMissing(['api_key' => 'fixture-only']);
        $other = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($other)->getJson($url.'/'.$id)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))->getJson($url.'/'.$id)->assertForbidden();
    }

    public function test_unconfirmed_stale_disabled_and_reused_input_are_rejected(): void
    {
        $p = $this->researchProject();
        $url = '/admin/marketing/projects/'.$p->id.'/research';
        $this->postJson($url, ['version' => 999, 'request_id' => (string) Str::uuid()])->assertConflict();
        config(['marketing.research.enabled' => false]);
        $this->postJson($url, ['version' => $p->version, 'request_id' => (string) Str::uuid()])->assertUnprocessable();
        config(['marketing.research.enabled' => true]);
        $request = (string) Str::uuid();
        $run = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, $request);
        $p->refresh()->update(['brief' => array_replace($p->brief, ['market' => 'Singapore'])]);
        $this->postJson($url, ['version' => $p->version, 'request_id' => $request])->assertConflict();
        $this->assertSame('Malaysia', $run->fresh()->input_snapshot['brief']['market']);
    }

    public function test_service_layer_checks_actor_and_read_requests_do_not_dispatch(): void
    {
        $p = $this->researchProject();
        $run = app(MarketingResearchWorkflow::class)->start($p->id, $p->owner_id, $p->version, (string) Str::uuid());
        Queue::assertPushed(ProcessMarketingResearch::class, 1);
        $this->getJson('/admin/marketing/projects/'.$p->id.'/research/'.$run->id)->assertOk();
        Queue::assertPushed(ProcessMarketingResearch::class, 1);
        User::findOrFail($p->owner_id)->update(['is_super_admin' => false]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(MarketingResearchWorkflow::class)->view($p->id, $p->owner_id, $run->id);
    }
}
