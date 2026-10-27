<?php

namespace Tests\Feature;

use App\Models\MarketingAsset;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\User;
use App\Services\Marketing\MarketingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingProductionPreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['marketing.production.audio_cap_usd' => '1.00']);
        Storage::fake('local');
    }

    private function prepared(): array
    {
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($u);
        $p = app(MarketingWorkflow::class)->create($u, ['source_type' => 'theme', 'theme' => 'A factual product introduction']);
        $path = UploadedFile::fake()->image('real-product.png', 100, 100)->store('marketing/'.$p->id, 'local');
        $a = MarketingAsset::create(['project_id' => $p->id, 'name' => 'Our product', 'path' => $path, 'mime' => 'image/png', 'size' => Storage::disk('local')->size($path), 'authorized' => true]);
        $brief = array_replace($p->brief, ['source_confirmed' => true, 'facts_confirmed' => true, 'goal_confirmed' => true, 'audience_confirmed' => true, 'language' => 'English', 'market' => 'Malaysia', 'platforms' => ['Instagram']]);
        $concept = ['title' => 'Product detail', 'ratio' => '9:16'];
        $p->update(['brief' => $brief, 'concepts' => [$concept], 'selected_concept' => 0]);
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $u->id, 'number' => 1, 'document' => ['summary' => 'A factual introduction', 'duration' => 10, 'cta' => 'Learn more', 'gaps' => [], 'changes' => [], 'scenes' => [['visual' => 'Show our real product photo', 'voiceover' => 'Meet our product.', 'subtitle' => 'Our product', 'material' => 'Our real photo', 'duration' => 10, 'asset_ids' => [$a->id]]]], 'context' => ['brief' => $brief, 'concept' => $concept, 'snapshot' => $p->snapshot], 'confirmed_at' => now(), 'confirmed_by' => $u->id]);
        $p->update(['current_revision_id' => $r->id, 'confirmed_revision_id' => $r->id, 'stage' => 'confirmed', 'status' => 'prepared']);

        return [$p->fresh(), $r, $a];
    }

    private function prepare(MarketingProject $p, MarketingRevision $r, ?string $request = null)
    {
        return $this->post('/admin/marketing/projects/'.$p->id.'/production', ['version' => $p->version, 'revision_id' => $r->id, 'request_id' => $request ?? (string) Str::uuid()]);
    }

    private function budget(MarketingProject $p, string $id, array $extra = [])
    {
        return $this->post('/admin/marketing/projects/'.$p->id.'/production/'.$id.'/confirm', array_replace(['version' => $p->version, 'budget_confirmation' => 1], $extra));
    }

    public function test_confirmed_storyboard_prepares_reopenable_material_and_budget_snapshot_without_paid_calls(): void
    {
        [$p, $r, $a] = $this->prepared();
        $this->prepare($p, $r)->assertRedirect();
        $row = DB::table('marketing_production_plans')->first();
        $manifest = json_decode($row->manifest, true);
        $this->assertSame('awaiting_budget', $row->status);
        $this->assertSame($r->id, $row->revision_id);
        $this->assertSame($a->id, $manifest['scenes'][0]['images'][0]['asset_id']);
        $this->assertSame('Meet our product.', $manifest['scenes'][0]['voiceover']);
        $this->assertSame(1000000, $manifest['budget']['cap_microusd']);
        $this->assertSame(0, $manifest['budget']['ai_video_requests']);
        $this->get('/admin/marketing/projects/'.$p->id.'/production')->assertOk()->assertSee('Production preparation')->assertSee('Our product')->assertSee('US$1.00');
        $this->assertSame($p->version, $p->fresh()->version);
        Http::assertNothingSent();
    }

    public function test_unconfirmed_or_outdated_storyboard_cannot_prepare(): void
    {
        [$p, $r] = $this->prepared();
        $p->update(['current_revision_id' => null]);
        $this->prepare($p, $r)->assertStatus(409);
        $this->assertDatabaseCount('marketing_production_plans', 0);
    }

    public function test_duplicate_prepare_and_budget_confirmation_are_idempotent_and_never_dispatch(): void
    {
        [$p, $r] = $this->prepared();
        $id = (string) Str::uuid();
        $this->prepare($p, $r, $id)->assertRedirect();
        $this->prepare($p, $r, $id)->assertRedirect();
        $this->prepare($p, $r)->assertRedirect();
        $this->assertDatabaseCount('marketing_production_plans', 1);
        $row = DB::table('marketing_production_plans')->first();
        $this->budget($p, $row->id, ['cap_microusd' => 1])->assertRedirect();
        $confirmed = DB::table('marketing_production_plans')->first();
        $this->assertSame($row->expires_at, $confirmed->expires_at);
        $this->budget($p, $row->id)->assertRedirect();
        $this->assertSame($confirmed->confirmed_at, DB::table('marketing_production_plans')->first()->confirmed_at);
        $this->assertSame('budget_confirmed', $confirmed->status);
        $this->assertSame($p->owner_id, (int) $confirmed->confirmed_by);
        $this->assertSame(1000000, json_decode($confirmed->manifest, true)['budget']['cap_microusd']);
        $this->assertDatabaseCount('marketing_ai_runs', 0);
        Http::assertNothingSent();
    }

    public function test_content_confirmation_does_not_count_as_budget_authorization(): void
    {
        [$p, $r] = $this->prepared();
        $this->prepare($p, $r)->assertRedirect();
        $row = DB::table('marketing_production_plans')->first();
        $this->budget($p, $row->id, ['budget_confirmation' => null, 'content_confirmation' => 1])->assertSessionHasErrors('budget_confirmation');
        $this->assertNull(DB::table('marketing_production_plans')->first()->confirmed_at);
    }

    public function test_cross_owner_and_non_super_admin_are_rejected(): void
    {
        [$p, $r] = $this->prepared();
        $this->prepare($p, $r)->assertRedirect();
        $id = DB::table('marketing_production_plans')->value('id');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $this->get('/admin/marketing/projects/'.$p->id.'/production')->assertNotFound();
        $this->prepare($p, $r)->assertNotFound();
        $this->budget($p, $id)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]));
        $this->get('/admin/marketing/projects/'.$p->id.'/production')->assertForbidden();
    }

    public function test_missing_or_unauthorized_image_is_a_blocker_not_an_invented_replacement(): void
    {
        [$p, $r, $a] = $this->prepared();
        Storage::disk('local')->delete($a->path);
        $this->prepare($p, $r)->assertRedirect();
        $row = DB::table('marketing_production_plans')->first();
        $this->assertSame('blocked', $row->status);
        $this->budget($p, $row->id)->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_media_bytes_or_permission_changed_after_plan_requires_new_preparation(): void
    {
        [$p, $r, $a] = $this->prepared();
        $this->prepare($p, $r)->assertRedirect();
        $id = DB::table('marketing_production_plans')->value('id');
        $replacement = UploadedFile::fake()->image('replacement.png', 101, 100);
        Storage::disk('local')->put($a->path, file_get_contents($replacement->getRealPath()));
        $this->budget($p, $id)->assertStatus(409);
        $a->update(['authorized' => false]);
        $this->budget($p, $id)->assertStatus(409);
        $this->assertNull(DB::table('marketing_production_plans')->value('confirmed_at'));
    }

    public function test_expired_or_repriced_budget_cannot_be_confirmed(): void
    {
        [$p, $r] = $this->prepared();
        $this->prepare($p, $r)->assertRedirect();
        $id = DB::table('marketing_production_plans')->value('id');
        config(['marketing.production.audio_cap_usd' => '2.00']);
        $this->budget($p, $id)->assertStatus(409);
        config(['marketing.production.audio_cap_usd' => '1.00']);
        $this->travel(25)->hours();
        $this->budget($p, $id)->assertStatus(409);
        $this->prepare($p, $r)->assertRedirect();
        $this->assertDatabaseCount('marketing_production_plans', 2);
    }

    public function test_unsupported_language_or_unset_cap_is_reported_without_changing_storyboard(): void
    {
        [$p, $r] = $this->prepared();
        config(['marketing.production.audio_cap_usd' => null]);
        $this->prepare($p, $r)->assertRedirect();
        $this->assertSame('blocked', DB::table('marketing_production_plans')->value('status'));
        $this->get('/admin/marketing/projects/'.$p->id.'/production')->assertSee('spending limit');
        $this->assertSame('English', $p->fresh()->brief['language']);
    }

    public function test_a_planned_ai_clip_is_not_silently_replaced_with_a_photo(): void
    {
        [$p, $r] = $this->prepared();
        $doc = $r->document;
        $doc['scenes'][0]['material'] = 'Our real photo plus planned AI generated warehouse footage';
        $r->update(['document' => $doc]);
        $this->prepare($p, $r)->assertRedirect();
        $this->assertSame('blocked', DB::table('marketing_production_plans')->value('status'));
    }

    public function test_foreign_asset_and_private_path_escape_cannot_be_used(): void
    {
        [$p, $r, $a] = $this->prepared();
        $a->update(['path' => '../public/real-product.png']);
        $this->prepare($p, $r)->assertRedirect();
        $this->assertSame('blocked', DB::table('marketing_production_plans')->value('status'));
        $r->update(['document' => array_replace($r->document, ['scenes' => [array_replace($r->document['scenes'][0], ['asset_ids' => [(string) Str::uuid()]])]])]);
        $this->prepare($p, $r)->assertRedirect();
        $this->assertSame(0, DB::table('marketing_production_plans')->where('status', 'awaiting_budget')->count());
        Http::assertNothingSent();
    }

    public function test_project_changes_revoke_the_ability_to_confirm_an_old_budget_without_overwriting_it(): void
    {
        [$p, $r] = $this->prepared();
        $this->prepare($p, $r)->assertRedirect();
        $id = DB::table('marketing_production_plans')->value('id');
        $this->budget($p, $id)->assertRedirect();
        $old = DB::table('marketing_production_plans')->first();
        $p->update(['version' => $p->version + 1]);
        $this->budget($p->fresh(), $id)->assertStatus(409);
        $this->assertSame($old->confirmed_at, DB::table('marketing_production_plans')->first()->confirmed_at);
        $this->prepare($p->fresh(), $r)->assertRedirect();
        $this->assertDatabaseCount('marketing_production_plans', 2);
    }

    public function test_unverified_language_and_ratio_are_blocked_and_not_rewritten(): void
    {
        [$p, $r] = $this->prepared();
        $brief = array_replace($p->brief, ['language' => 'Chinese']);
        $concept = ['title' => 'Product detail', 'ratio' => '16:9'];
        $p->update(['brief' => $brief, 'concepts' => [$concept]]);
        $r->update(['context' => array_replace($r->context, ['brief' => $brief, 'concept' => $concept])]);
        $this->prepare($p, $r)->assertRedirect();
        $row = DB::table('marketing_production_plans')->first();
        $this->assertSame('blocked', $row->status);
        $this->assertCount(2, json_decode($row->manifest, true)['blockers']);
        $this->assertSame('16:9', $p->fresh()->concepts[0]['ratio']);
    }

    public function test_a_truncated_image_header_cannot_be_approved_as_usable_material(): void
    {
        [$p, $r, $a] = $this->prepared();
        $bytes = Storage::disk('local')->get($a->path);
        Storage::disk('local')->put($a->path, substr($bytes, 0, 33));
        $this->prepare($p, $r)->assertRedirect();
        $row = DB::table('marketing_production_plans')->first();
        $this->assertSame('blocked', $row->status);
        $this->budget($p, $row->id)->assertStatus(422);
        Http::assertNothingSent();
    }
}
