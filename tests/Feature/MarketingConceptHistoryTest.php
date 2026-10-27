<?php

namespace Tests\Feature;

use App\Models\MarketingPlanningEvent;
use App\Models\User;
use App\Services\Marketing\MarketingConceptHistory;
use App\Services\Marketing\MarketingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketingConceptHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function sample()
    {
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($u);
        $p = app(MarketingWorkflow::class)->create($u, ['source_type' => 'theme', 'theme' => 'Container introduction']);
        $p->update(['concepts' => array_map(fn ($i) => ['title' => 'Story '.$i, 'hook' => 'Opening '.$i, 'story' => 'Show the actual product in a warehouse and explain storage needs '.$i, 'approach' => 'use_scenario', 'duration' => 15], range(1, 3))]);

        return $p->fresh();
    }

    public function test_history_restores_group_or_selects_old_story_without_restoring_approvals_and_is_idempotent(): void
    {
        $p = $this->sample();
        $s = app(MarketingConceptHistory::class);
        $first = $s->capture($p, 'baseline');
        $old = $p->concepts;
        $p->update(['concepts' => [['title' => 'Different story', 'hook' => 'New', 'story' => 'Other narrative']], 'version' => $p->version + 1]);
        $request = (string) Str::uuid();
        $s->restore($p->id, $p->owner_id, $p->version, $first->id, $request, null);
        $p->refresh();
        $this->assertSame($old, $p->concepts);
        $this->assertNull($p->current_revision_id);
        $this->assertNull($p->confirmed_revision_id);
        $this->assertNull($p->selected_concept);
        $version = $p->version;
        $s->restore($p->id, $p->owner_id, 1, $first->id, $request, null);
        $this->assertSame($version, $p->fresh()->version);
        $s->restore($p->id, $p->owner_id, $version, $first->id, (string) Str::uuid(), 1);
        $this->assertSame(1, $p->fresh()->selected_concept);
        $this->assertGreaterThanOrEqual(3, MarketingPlanningEvent::where('kind', 'concept_snapshot')->count());
    }

    public function test_foreign_and_changed_context_cannot_restore(): void
    {
        $p = $this->sample();
        $s = app(MarketingConceptHistory::class);
        $first = $s->capture($p, 'baseline');
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($u)->post('/admin/marketing/workspace/'.$p->id.'/concept-history/'.$first->id.'/restore', ['version' => $p->version, 'request_id' => (string) Str::uuid()])->assertNotFound();
        $this->actingAs(User::find($p->owner_id));
        $p->update(['brief' => array_replace($p->brief, ['audience' => 'A new audience'])]);
        $this->post('/admin/marketing/workspace/'.$p->id.'/concept-history/'.$first->id.'/restore', ['version' => $p->version, 'request_id' => (string) Str::uuid()])->assertSessionHasErrors();
    }

    public function test_history_endpoint_captures_once_and_displays_safe_readonly_previews(): void
    {
        $p = $this->sample();
        $url = '/admin/marketing/workspace/'.$p->id.'/concept-history';
        $this->post($url, ['version' => $p->version])->assertOk()->assertSee('Story 1')->assertSee('恢复这一组三个创意');
        $this->post($url, ['version' => $p->version])->assertOk();
        $this->assertSame(1, MarketingPlanningEvent::where('kind', 'concept_snapshot')->count());
        $this->get($url)->assertOk();
        $this->assertSame(1, MarketingPlanningEvent::count());
        $this->assertSame($p->version, $p->fresh()->version);
    }

    public function test_context_matching_ignores_json_object_key_order_but_not_values(): void
    {
        $p = $this->sample();
        $s = app(MarketingConceptHistory::class);
        $context = $s->context($p);
        $context = array_reverse($context, true);
        $this->assertTrue($s->compatible($context, $p));
        $context['brief']['audience'] = 'Changed audience';
        $this->assertFalse($s->compatible($context, $p));
    }

    public function test_same_story_with_cosmetic_title_changes_is_rejected_without_a_provider_retry(): void
    {
        $p = $this->sample();
        $s = app(MarketingConceptHistory::class);
        $s->capture($p, 'baseline');
        $candidate = $p->concepts;
        $candidate[0]['title'] = 'A NEW heading';
        $this->expectException(ValidationException::class);
        $s->assertNovel($p,$candidate);
    }
}
