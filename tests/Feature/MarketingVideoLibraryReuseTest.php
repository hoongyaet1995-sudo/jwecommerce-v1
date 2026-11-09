<?php

namespace Tests\Feature;

use App\Models\MarketingAsset;
use App\Models\MarketingLibraryAsset;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\MarketingVideoFilm;
use App\Models\User;
use App\Services\Marketing\MarketingMediaTools;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingVideoLibraryReuseTest extends TestCase
{
    use RefreshDatabase;

    private function setupCase(?string $bytes = null): array
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Queue::fake();
        config(['marketing.execution_enabled' => false, 'marketing.billing.enabled' => true, 'marketing.billing.pricing_valid_until' => '2026-10-30', 'marketing.production.ffprobe_binary' => null]);
        $this->mock(MarketingMediaTools::class, function ($m) {
            $m->shouldNotReceive('probe');
            $m->shouldNotReceive('run');
        });
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $source = $this->project($u);
        $target = $this->project($u);
        $path = 'marketing/'.$source->id.'/existing.mp4';
        Storage::disk('local')->put($path, $bytes ?? file_get_contents(base_path('tests/Fixtures/marketing/reuse-silent.mp4')));
        $asset = MarketingAsset::create(['project_id' => $source->id, 'name' => 'Existing video QA', 'path' => $path, 'mime' => 'video/mp4', 'size' => Storage::disk('local')->size($path), 'authorized' => true]);
        $entry = MarketingLibraryAsset::where('source_id', $asset->id)->firstOrFail();
        $this->actingAs($u);

        return [$u, $asset, $entry, $target];
    }

    private function project(User $u): MarketingProject
    {
        return MarketingProject::create(['owner_id' => $u->id, 'source_type' => 'manual', 'name' => 'Video reuse QA', 'snapshot' => [], 'brief' => []]);
    }

    private function endpoint($p): string
    {
        return '/admin/marketing/workspace/'.$p->id.'/storyboard/library';
    }

    public function test_mp4_import_manual_save_and_refresh_need_no_media_tools_or_generation(): void
    {
        [$u,$origin,$entry,$target] = $this->setupCase();
        $r = MarketingRevision::create(['project_id' => $target->id, 'actor_id' => $u->id, 'number' => 1, 'document' => ['scenes' => [['visual' => 'QA placeholder', 'voiceover' => '', 'subtitle' => '', 'duration' => 5]]], 'context' => []]);
        $target->update(['current_revision_id' => $r->id]);
        $snapshot = app(MarketingStoryboardWorkspace::class)->initialize($target->id, $u->id, (string) Str::uuid(), $target->fresh()->version);
        $response = $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertOk();
        $id = $response->json('asset.asset_id');
        $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertOk()->assertJsonPath('asset.asset_id', $id);
        $this->assertDatabaseCount('marketing_media_reuses', 1);
        $this->assertDatabaseCount('cms_media_assets', 0);
        $command = ['type' => 'insert', 'mode' => 'manual', 'asset_id' => $id, 'content' => ['message' => 'Existing video', 'visual' => 'Use original video without generation', 'voiceover' => '', 'subtitle' => '', 'seconds' => 5]];
        $api = '/admin/marketing/workspace/'.$target->id.'/storyboard';
        $this->postJson($api.'/changes', ['request_id' => (string) Str::uuid(), 'version' => $snapshot['story_version'], 'command' => $command])->assertOk()->assertJsonPath('snapshot.scenes.1.asset.asset_id', $id)->assertJsonPath('preparation.tasks', []);
        $this->getJson($api)->assertOk()->assertJsonPath('snapshot.scenes.1.asset.asset_id', $id);
        $copy = MarketingAsset::findOrFail($id);
        $this->assertSame($entry->sha256, hash_file('sha256', Storage::disk('local')->path($copy->path)));
        $this->assertNull($target->fresh()->confirmed_revision_id);
        $origin->update(['authorized' => false]);
        $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertNotFound();
        $this->get(route('marketing.asset', [$target->id, $id]))->assertNotFound();
        Http::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('marketing_generation_quotes', 0);
        $this->assertDatabaseCount('marketing_preparation_authorizations', 0);
    }

    public function test_truncated_video_is_rejected_even_with_registered_matching_hash(): void
    {
        $bytes = substr(file_get_contents(base_path('tests/Fixtures/marketing/reuse-silent.mp4')), 0, 500);
        [$u,$origin,$entry,$target] = $this->setupCase($bytes);
        $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('marketing_media_reuses', 0);
    }

    public function test_audio_handler_cannot_masquerade_as_reusable_video(): void
    {
        $bytes = str_replace('vide', 'soun', file_get_contents(base_path('tests/Fixtures/marketing/reuse-silent.mp4')));
        [$u,$origin,$entry,$target] = $this->setupCase($bytes);
        $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('marketing_media_reuses', 0);
    }

    public function test_missing_media_data_and_oversized_files_are_rejected(): void
    {
        $bytes = str_replace('mdat', 'free', file_get_contents(base_path('tests/Fixtures/marketing/reuse-silent.mp4')));
        [$u,$origin,$entry,$target] = $this->setupCase($bytes);
        $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('marketing_media_reuses', 0);
    }

    public function test_library_mp4_still_obeys_thirty_megabyte_limit(): void
    {
        $bytes = file_get_contents(base_path('tests/Fixtures/marketing/reuse-silent.mp4')).str_repeat('x', 30 * 1024 * 1024);
        [$u,$origin,$entry,$target] = $this->setupCase($bytes);
        $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('marketing_media_reuses', 0);
    }

    public function test_other_video_formats_fail_clearly_without_media_tools(): void
    {
        $bytes = substr_replace(file_get_contents(base_path('tests/Fixtures/marketing/reuse-silent.mp4')), 'qt  ', 8, 4);
        [$u,$origin,$entry,$target] = $this->setupCase($bytes);
        $this->assertSame('video/quicktime', $entry->mime);
        $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertUnprocessable()->assertJsonPath('message', 'This video format requires configured media tools. Select an MP4 for manual reuse on this host.');
        $this->assertDatabaseCount('marketing_media_reuses', 0);
    }

    public function test_hash_consent_and_owner_checks_still_run_before_import(): void
    {
        [$u,$origin,$entry,$target] = $this->setupCase();
        $this->postJson($this->endpoint($target), ['library_asset_id' => $entry->id])->assertUnprocessable();
        $other = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $foreignTarget = $this->project($other);
        $this->actingAs($other)->postJson($this->endpoint($foreignTarget), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertNotFound();
        Storage::disk('local')->put($origin->path, 'changed bytes');
        $this->actingAs($u)->postJson($this->endpoint($target), ['library_asset_id' => $entry->id, 'authorized' => 1])->assertStatus(409);
        $this->assertDatabaseCount('marketing_media_reuses', 0);
    }

    public function test_final_film_requires_current_approval_even_without_ffprobe(): void
    {
        [$u,$origin,$entry,$target] = $this->setupCase();
        $r = MarketingRevision::create(['project_id' => $origin->project_id, 'actor_id' => $u->id, 'number' => 1, 'document' => [], 'context' => []]);
        $film = MarketingVideoFilm::create(['owner_id' => $u->id, 'project_id' => $origin->project_id, 'revision_id' => $r->id, 'fingerprint' => str_repeat('f', 64), 'status' => 'completed', 'input' => [], 'output_path' => $origin->path, 'output_sha256' => $entry->sha256]);
        $library = MarketingLibraryAsset::where('source_type', 'marketing_video_films')->where('source_id', $film->id)->firstOrFail();
        $this->postJson($this->endpoint($target), ['library_asset_id' => $library->id, 'authorized' => 1])->assertStatus(409);
        $film->update(['approved_at' => now(), 'approved_sha256' => $entry->sha256]);
        $this->postJson($this->endpoint($target), ['library_asset_id' => $library->id, 'authorized' => 1])->assertOk();
        $film->update(['approved_sha256' => str_repeat('a',64)]);
        $this->postJson($this->endpoint($target),['library_asset_id' => $library->id, 'authorized' => 1])->assertStatus(409);
    }
}
