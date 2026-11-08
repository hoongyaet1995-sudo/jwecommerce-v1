<?php

namespace Tests\Feature;

use App\Models\MarketingAsset;
use App\Models\MarketingDailyDraftRun;
use App\Models\MarketingPost;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\MarketingVideoClip;
use App\Models\MarketingVideoFilm;
use App\Models\User;
use App\Services\Marketing\MarketingAvatarLibrary;
use App\Services\Marketing\MarketingMediaRegister;
use App\Services\Marketing\MarketingMediaSources;
use App\Services\Marketing\MarketingPostMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingContentLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    private function project(User $u): MarketingProject
    {
        return MarketingProject::create(['owner_id' => $u->id, 'source_type' => 'manual', 'name' => 'Library proof', 'snapshot' => [], 'brief' => []]);
    }

    private function candidate(User $u): MarketingAsset
    {
        $p = $this->project($u);
        $file = UploadedFile::fake()->image('candidate.png', 320, 480);
        $path = 'marketing/'.$p->id.'/storyboard-generated/candidate.png';
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return MarketingAsset::create(['project_id' => $p->id, 'name' => 'Unused candidate', 'path' => $path, 'mime' => 'image/png', 'size' => Storage::disk('local')->size($path), 'authorized' => true]);
    }

    public function test_unused_candidate_is_captured_idempotently_without_adopting_it(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $u = $this->owner();
        $a = $this->candidate($u);
        $this->assertDatabaseHas('marketing_library_assets', ['source_id' => $a->id, 'origin' => 'ai', 'kind' => 'image']);
        $before = MarketingProject::find($a->project_id)->getAttributes();
        $this->artisan('marketing:library-index', ['--owner' => $u->id])->assertSuccessful();
        $this->artisan('marketing:library-index', ['--owner' => $u->id])->assertSuccessful();
        $this->assertSame(1, DB::table('marketing_library_assets')->where('source_id', $a->id)->count());
        $this->assertSame($before, MarketingProject::find($a->project_id)->getAttributes());
        Http::assertNothingSent();
    }

    public function test_library_pages_settings_and_video_studio_use_real_routes_and_records(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $this->candidate($u);
        $this->actingAs($u);
        $this->get('/admin/marketing/content')->assertOk()->assertSee('Content Library')->assertDontSee('Sample assets');
        $this->get('/admin/marketing/content/data?kind=image')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('assets.0.title', 'Unused candidate');
        $this->get('/admin/marketing/content/data?kind=video')->assertOk()->assertJsonPath('total', 0);
        $this->get('/admin/marketing/settings')->assertOk()->assertSee('Open Email settings')->assertSee('/admin/marketing/settings/avatar', false)->assertDontSee('Related image assets');
        $this->get('/admin/marketing/settings/avatar')->assertOk()->assertSee('Add avatar template');
        $this->get('/admin/marketing/video-studio')->assertOk()->assertSee('Video projects')->assertSee('Video templates');
    }

    public function test_upload_retains_original_and_is_available_with_execution_disabled(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['marketing.execution_enabled' => false]);
        $u = $this->owner();
        $this->actingAs($u);
        $this->post('/admin/marketing/content/upload', ['files' => [UploadedFile::fake()->image('reference.png', 300, 400)]], ['Accept' => 'application/json'])->assertSuccessful();
        $this->get('/admin/marketing/content/data?kind=image&origin=uploaded')->assertOk()->assertJsonPath('total', 2);
        $this->assertDatabaseCount('marketing_post_media', 1);
        Http::assertNothingSent();
    }

    public function test_file_and_listing_enforce_owner_hash_and_current_source_authorization(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $a = $this->candidate($u);
        $id = DB::table('marketing_library_assets')->value('id');
        $this->actingAs($this->owner())->get('/admin/marketing/content/data')->assertJsonPath('total', 0);
        $this->get('/admin/marketing/content/files/'.$id)->assertNotFound();
        $this->actingAs($u)->get('/admin/marketing/content/files/'.$id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $a->update(['authorized' => false]);
        $this->get('/admin/marketing/content/files/'.$id)->assertNotFound();
        $a->update(['authorized' => true]);
        Storage::disk('local')->put($a->path, 'changed');
        $this->get('/admin/marketing/content/files/'.$id)->assertStatus(409);
    }

    public function test_readonly_marketing_user_cannot_upload_and_non_marketing_admin_cannot_read(): void
    {
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($u);
        $this->get('/admin/marketing/content')->assertForbidden();
        $u->permissions()->create(['permission' => 'marketing.view']);
        $this->get('/admin/marketing/content')->assertOk();
        $this->post('/admin/marketing/content/upload', ['files' => [UploadedFile::fake()->image('x.png')]], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_avatar_reference_versions_obey_membership_and_revocation(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $other = $this->owner();
        $lib = app(MarketingAvatarLibrary::class);
        $a = $lib->saveDraft($u, $lib->home($u)->id, (string) Str::uuid(), ['name' => 'Host', 'kind' => 'real']);
        $lib->reference($u, $a->id, UploadedFile::fake()->image('host.png', 300, 400), true);
        $id = DB::table('marketing_library_assets')->where('avatar_id', $a->id)->value('id');
        $this->assertNotNull($id);
        $this->actingAs($other)->get('/admin/marketing/content/files/'.$id)->assertNotFound();
        $lib->member($u, $a->library_id, $other->id, 'viewer');
        $this->get('/admin/marketing/content/files/'.$id)->assertOk();
        $a->update(['status' => 'revoked']);
        $this->get('/admin/marketing/content/files/'.$id)->assertNotFound();
        $this->get('/admin/marketing/content/data')->assertJsonPath('total', 0);
    }

    public function test_rejected_candidate_still_lists_and_tracks_actual_selection(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $a = $this->candidate($u);
        $r = MarketingRevision::create(['project_id' => $a->project_id, 'actor_id' => $u->id, 'number' => 1, 'document' => ['material_package' => ['outputs' => [['asset_id' => $a->id, 'status' => 'rejected']]]], 'context' => []]);
        MarketingProject::find($a->project_id)->update(['current_revision_id' => $r->id]);
        $this->actingAs($u)->get('/admin/marketing/content/data?selection=not-selected')->assertJsonPath('total', 1)->assertJsonPath('assets.0.selection', 'not-selected');
    }

    public function test_unsafe_paths_remote_urls_and_audio_are_never_registered(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $register = app(MarketingMediaRegister::class);
        $context = ['owner_id' => $u->id, 'source_type' => 'marketing_post_media', 'source_id' => 'test', 'slot' => 'file', 'title' => 'unsafe', 'origin' => 'uploaded', 'stage' => 'Original upload'];
        foreach (['../.env', 'marketing/../../.env', 'https://example.com/video.mp4', 'C:/private.png', 'marketing/evil\\file.png'] as $path) {
            $this->assertNull($register->record($context, $path));
        }
        Storage::disk('local')->put('marketing/audio.mp3', 'audio');
        $this->assertNull($register->record($context, 'marketing/audio.mp3'));
        $this->assertDatabaseCount('marketing_library_assets', 0);
    }

    public function test_generated_post_originals_are_registered_before_adoption_with_ai_origin(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $key = app(MarketingPostMedia::class)->store($u->id, UploadedFile::fake()->image('Generated slide.png', 300, 400), 'ai');
        $this->assertDatabaseCount('marketing_library_assets', 2);
        $this->assertDatabaseHas('marketing_library_assets', ['source_id' => substr($key, 7), 'origin' => 'ai', 'slot' => 'original']);
        $this->assertDatabaseCount('marketing_posts', 0);
    }

    public function test_video_raw_and_multiple_completed_versions_register_without_new_approval(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $p = $this->project($u);
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $u->id, 'number' => 1, 'document' => [], 'context' => []]);
        // Minimal ISO-BMFF fixture: real video MIME, no production or external request.
        $bytes = hex2bin('000000186674797069736f6d0000020069736f6d69736f32').str_repeat("\0", 40);
        Storage::disk('local')->put('marketing/'.$p->id.'/proof/first.mp4', $bytes);
        $hash = hash('sha256', $bytes);
        $f = MarketingVideoFilm::create(['owner_id' => $u->id, 'project_id' => $p->id, 'revision_id' => $r->id, 'fingerprint' => str_repeat('a', 64), 'status' => 'completed', 'input' => [], 'output_path' => 'marketing/'.$p->id.'/proof/first.mp4', 'output_sha256' => $hash]);
        $this->assertDatabaseHas('marketing_library_assets', ['source_id' => $f->id, 'kind' => 'video', 'selection' => 'not-reviewed']);
        $this->assertNull($f->fresh()->approved_at);
        $entry = DB::table('marketing_library_assets')->where('source_id', $f->id)->first();
        $this->actingAs($u)->get('/admin/marketing/content/files/'.$entry->id.'?download=1')->assertForbidden();
        Storage::disk('local')->put('marketing/'.$p->id.'/proof/second.mp4', $bytes.'version2');
        $f->update(['output_path' => 'marketing/'.$p->id.'/proof/second.mp4', 'output_sha256' => hash('sha256', $bytes.'version2')]);
        $this->assertSame(2, DB::table('marketing_library_assets')->where('source_id', $f->id)->count());
        $this->actingAs($u)->get('/admin/marketing/content/data?kind=video')->assertJsonPath('total', 2);
    }

    public function test_daily_background_is_captured_before_any_post_exists(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $file = UploadedFile::fake()->image('background.png', 300, 400);
        $runId = (string) Str::uuid();
        $path = 'marketing/daily-drafts/'.$runId.'/background.png';
        $bytes = file_get_contents($file->getRealPath());
        Storage::disk('local')->put($path, $bytes);
        $run = MarketingDailyDraftRun::create(['id' => $runId, 'user_id' => $u->id, 'merchant_key' => 'test', 'status' => 'image_completed', 'snapshot' => ['title' => 'New image', 'background_path' => $path, 'background_sha256' => hash('sha256', $bytes)]]);
        $this->assertDatabaseHas('marketing_library_assets', ['source_id' => $run->id, 'stage' => 'AI background', 'origin' => 'ai']);
        $this->assertDatabaseCount('marketing_posts', 0);
    }

    public function test_replacing_and_deleting_last_post_reference_clears_selection(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $key = app(MarketingPostMedia::class)->store($u->id, UploadedFile::fake()->image('proof.png', 300, 400));
        $a = MarketingPost::create(['user_id' => $u->id, 'merchant_key' => 'test', 'document' => ['assets' => [$key]]]);
        $b = MarketingPost::create(['user_id' => $u->id, 'merchant_key' => 'test', 'document' => ['assets' => [$key]]]);
        $a->update(['document' => ['assets' => []]]);
        $this->assertDatabaseHas('marketing_library_assets', ['source_id' => substr($key, 7), 'selection' => 'selected']);
        $b->update(['document' => ['assets' => [$key], 'deleted_at' => now()->toIso8601String()]]);
        $this->assertDatabaseMissing('marketing_library_assets', ['source_id' => substr($key, 7), 'selection' => 'selected']);
    }

    public function test_derived_clip_and_film_preserve_distinct_provenance_and_upstream_authorization(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $a = $this->candidate($u);
        $p = MarketingProject::find($a->project_id);
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $u->id, 'number' => 1, 'document' => [], 'context' => []]);
        $bytes = hex2bin('000000186674797069736f6d0000020069736f6d69736f32').str_repeat("\0", 40);
        $path = 'marketing/'.$p->id.'/proof/shared.mp4';
        Storage::disk('local')->put($path, $bytes);
        $hash = hash('sha256', $bytes);
        $clip = MarketingVideoClip::create(['owner_id' => $u->id, 'project_id' => $p->id, 'revision_id' => $r->id, 'request_id' => (string) Str::uuid(), 'scene_number' => 1, 'fingerprint' => str_repeat('b', 64), 'status' => 'completed', 'input' => ['image' => ['asset_id' => $a->id, 'sha256' => hash_file('sha256', Storage::disk('local')->path($a->path))]], 'quote' => [], 'connection_fingerprint' => str_repeat('c', 64), 'expires_at' => now()->addHour(), 'raw_path' => $path, 'raw_sha256' => $hash]);
        $film = MarketingVideoFilm::create(['owner_id' => $u->id, 'project_id' => $p->id, 'revision_id' => $r->id, 'fingerprint' => str_repeat('d', 64), 'status' => 'completed', 'input' => ['scenes' => [['clip_id' => $clip->id]]], 'output_path' => $path, 'output_sha256' => $hash]);
        $rows = DB::table('marketing_library_assets')->where('path', $path)->get();
        $this->assertCount(2, $rows);
        $this->actingAs($u);
        foreach ($rows as $row) {
            $this->get('/admin/marketing/content/files/'.$row->id)->assertOk();
        }
        $a->update(['authorized' => false]);
        foreach ($rows as $row) {
            $this->get('/admin/marketing/content/files/'.$row->id)->assertStatus(409);
        }
        $this->assertNull($film->fresh()->approved_at);
    }

    public function test_email_upload_and_flat_historical_sink_are_included_with_owner_scope(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['marketing.execution_enabled' => true]);
        $u = $this->owner();
        $this->actingAs($u);
        $this->post(route('marketing.email-templates.media'), ['image' => UploadedFile::fake()->image('email-banner.png', 300, 400)], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('marketing_library_assets', ['owner_id' => $u->id, 'disk' => 'public', 'stage' => 'Email image']);
        $entry = DB::table('marketing_library_assets')->where('owner_id', $u->id)->first();
        $this->get('/admin/marketing/content/files/'.$entry->id)->assertOk();
        app(MarketingMediaSources::class)->backfill($u->id);
        $this->assertDatabaseCount('marketing_library_assets', 1);
        $this->actingAs($this->owner())->get('/admin/marketing/content/files/'.$entry->id)->assertNotFound();
    }

    public function test_a_persisted_result_cannot_register_another_owners_private_path(): void
    {
        Storage::fake('local');
        $foreign = $this->candidate($this->owner());
        $actor = $this->owner();
        MarketingDailyDraftRun::create(['id' => (string) Str::uuid(), 'user_id' => $actor->id, 'merchant_key' => 'test', 'status' => 'image_completed', 'snapshot' => ['images' => [['path' => $foreign->path, 'sha256' => hash_file('sha256',Storage::disk('local')->path($foreign->path))]]]]);
        $this->assertDatabaseMissing('marketing_library_assets',['owner_id' => $actor->id]);
    }
}
