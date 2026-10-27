<?php

namespace Tests\Feature;

use App\Models\MarketingAiRun;
use App\Models\MarketingProject;
use App\Models\User;
use App\Services\Marketing\MarketingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingWorkspacePreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function owner()
    {
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($u);
        Http::preventStrayRequests();

        return $u;
    }

    private function createProject()
    {
        $id = (string) Str::uuid();
        $body = ['request_id' => $id, 'source_kind' => 'theme', 'theme' => 'JTG factual company introduction', 'video_type' => 'presenter'];
        $this->post('/admin/marketing/workspace/projects', $body)->assertRedirect();
        $this->post('/admin/marketing/workspace/projects', $body)->assertRedirect();
        $this->assertSame(1, MarketingProject::count());

        return MarketingProject::firstOrFail();
    }

    private function preferences($p)
    {
        return ['request_id' => (string) Str::uuid(), 'version' => $p->version, 'language' => 'zh-MY', 'platforms' => ['Facebook', 'Instagram'], 'duration' => 'auto', 'subtitles' => '1', 'narration' => '1', 'voice' => 'ai', 'music' => 'later'];
    }

    public function test_preparation_is_saved_reopened_idempotent_and_does_not_call_ai(): void
    {
        $this->owner();
        $p = $this->createProject();
        $body = $this->preferences($p);
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', $body)->assertRedirect();
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', $body)->assertRedirect();
        $p->refresh();
        $this->assertSame('auto', $p->preferences['duration']);
        $this->assertSame(['Facebook', 'Instagram'], $p->preferences['platforms']);
        $this->assertFalse($p->brief['source_confirmed']);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=preferences')->assertOk()->assertSee('zh-MY');
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk()->assertSee('检查策划摘要');
        $this->assertSame(0, MarketingAiRun::count());
        $this->assertDatabaseCount('marketing_planning_events', 2);
        $this->assertNotNull($p->planning_state);
    }

    public function test_invalid_stale_and_cross_owner_updates_are_rejected_without_changing_source(): void
    {
        $this->owner();
        $p = $this->createProject();
        $before = $p->snapshot;
        $body = $this->preferences($p);
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', $body + ['unknown' => 'ignored'])->assertRedirect();
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', array_replace($body, ['request_id' => (string) Str::uuid()]))->assertStatus(409);
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', array_replace($body, ['version' => $p->fresh()->version, 'voice' => 'owner']))->assertSessionHasErrors('voice');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))->post('/admin/marketing/workspace/'.$p->id.'/preferences', $body)->assertNotFound();
        $this->assertSame($before, $p->fresh()->snapshot);
    }

    public function test_silent_video_keeps_voice_inapplicable_and_duration_is_not_invented(): void
    {
        $this->owner();
        $p = $this->createProject();
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', array_replace($this->preferences($p), ['narration' => '0', 'voice' => 'none']))->assertRedirect();
        $p->refresh();
        $this->assertSame('none', $p->preferences['voice']);
        $this->assertNull($p->planning_state['fields']['duration_seconds']['value']);
    }

    public function test_legacy_brief_change_invalidates_workspace_confirmation_and_can_be_reseeded(): void
    {
        $u = $this->owner();
        $p = $this->createProject();
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', $this->preferences($p))->assertRedirect();
        $p->refresh();
        app(MarketingWorkflow::class)->saveBrief($p->id, $u->id, $p->version, ['audience' => 'A changed intended audience']);
        $this->assertNull($p->fresh()->planning_state);
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', $this->preferences($p->fresh()))->assertRedirect();
        $this->assertSame('A changed intended audience', $p->fresh()->planning_state['fields']['audience']['value']);
    }

    public function test_opening_future_screen_does_not_mark_unfinished_steps_complete(): void
    {
        $this->owner();
        $p = $this->createProject();
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences', $this->preferences($p))->assertRedirect();
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=finished')->assertOk()->assertViewHas('completedThrough',0)->assertViewHas('progressCurrent',1);
    }
}
