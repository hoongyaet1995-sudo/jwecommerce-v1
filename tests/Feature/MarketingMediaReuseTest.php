<?php

namespace Tests\Feature;

use App\Models\MarketingAsset;
use App\Models\MarketingLibraryAsset;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\MarketingVideoFilm;
use App\Models\User;
use App\Services\Marketing\MarketingAvatarLibrary;
use App\Services\Marketing\MarketingMediaReuse;
use App\Services\Marketing\MarketingPostContent;
use App\Services\Marketing\MarketingPosts;
use App\Services\Marketing\MarketingProductionCompiler;
use App\Services\Marketing\MarketingProductionFailure;
use App\Services\Marketing\MarketingStoryboardUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class MarketingMediaReuseTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    private function project(User $u): MarketingProject
    {
        return MarketingProject::create(['owner_id' => $u->id, 'source_type' => 'manual', 'name' => 'Reuse proof', 'snapshot' => [], 'brief' => []]);
    }

    private function image(User $u): array
    {
        Storage::fake('local');
        $p = $this->project($u);
        $f = UploadedFile::fake()->image('candidate.png', 320, 400);
        $path = 'marketing/'.$p->id.'/generated-candidate.png';
        Storage::disk('local')->put($path, file_get_contents($f->getRealPath()));
        $a = MarketingAsset::create(['project_id' => $p->id, 'name' => 'Unadopted reusable image', 'path' => $path, 'mime' => 'image/png', 'size' => Storage::disk('local')->size($path), 'authorized' => true]);

        return [$a, MarketingLibraryAsset::where('source_id', $a->id)->firstOrFail()];
    }

    public function test_posts_resolve_saved_library_key_and_revalidate_revoked_origin(): void
    {
        $u = $this->owner();
        [$source,$item] = $this->image($u);
        $s = app(MarketingPosts::class);
        $key = 'library:'.$item->id;
        $this->assertSame('image', $s->asset($u->id, $key)['kind'] ?? null);
        $path = app(MarketingPostContent::class)->path($u->id, $key);
        $this->assertSame($item->sha256, hash_file('sha256', $path));
        $this->actingAs($u)->get(route('marketing.posts.media', ['asset' => $key]))->assertOk();
        $source->update(['authorized' => false]);
        $this->assertNull($s->asset($u->id, $key));
        $this->get(route('marketing.posts.media', ['asset' => $key]))->assertNotFound();
    }

    public function test_project_reuse_is_idempotent_and_retains_a_live_authorization_link(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        $u = $this->owner();
        [$source,$item] = $this->image($u);
        $target = $this->project($u);
        $this->actingAs($u);
        $url = '/admin/marketing/workspace/'.$target->id.'/storyboard/library';
        $input = ['library_asset_id' => $item->id, 'authorized' => 1];
        $response = $this->postJson($url, $input)->assertOk();
        $id = $response->json('asset.asset_id');
        $this->postJson($url, $input)->assertOk()->assertJsonPath('asset.asset_id', $id);
        $this->assertDatabaseHas('marketing_media_reuses', ['project_id' => $target->id, 'library_asset_id' => $item->id, 'project_asset_id' => $id]);
        $a = MarketingAsset::findOrFail($id);
        $this->assertSame($item->sha256, hash_file('sha256', Storage::disk('local')->path($a->path)));
        $this->assertNull($target->fresh()->confirmed_revision_id);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
        $source->update(['authorized' => false]);
        $this->expectException(HttpExceptionInterface::class);
        app(MarketingStoryboardUpload::class)->reference($target->id, $id);
    }

    public function test_saved_draft_retains_library_key_without_approving_or_publishing(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        $u = $this->owner();
        [$source,$item] = $this->image($u);
        $key = 'library:'.$item->id;
        $saved = app(MarketingPosts::class)->write($u->id, 'draft', ['id' => (string) Str::uuid(), 'version' => 0, 'title' => 'Library draft', 'copy' => 'Saved original', 'targets' => [], 'asset' => $key, 'assets' => [$key], 'format' => 'image', 'type' => 'Post', 'tz' => 'Asia/Kuala_Lumpur']);
        $this->assertSame([$key], $saved['assets']);
        $this->assertSame('Draft', $saved['status']);
        $this->assertSame([], $saved['approvals']);
        $this->assertSame($source->path, $source->fresh()->path);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_tampered_origin_blocks_both_posts_and_existing_project_copy(): void
    {
        $u = $this->owner();
        [$source,$item] = $this->image($u);
        $target = $this->project($u);
        $service = app(MarketingMediaReuse::class);
        $reference = $service->import($u, $target->id, $item->id, true);
        Storage::disk('local')->put($source->path, 'changed bytes');
        $this->assertNull(app(MarketingPosts::class)->asset($u->id, 'library:'.$item->id));
        $this->actingAs($u)->postJson('/admin/marketing/workspace/'.$target->id.'/storyboard/library', ['library_asset_id' => $item->id, 'authorized' => 1])->assertStatus(409);
        $copy = MarketingLibraryAsset::where('source_id', $reference['asset_id'])->firstOrFail();
        $this->get(route('marketing.library.file', $copy->id))->assertStatus(409);
    }

    public function test_final_video_requires_matching_approval_and_revocation_stops_reuse(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $p = $this->project($u);
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $u->id, 'number' => 1, 'document' => [], 'context' => []]);
        $bytes = hex2bin('000000186674797069736f6d0000020069736f6d69736f32').str_repeat("\0", 40);
        $path = 'marketing/'.$p->id.'/proof/final.mp4';
        Storage::disk('local')->put($path, $bytes);
        $hash = hash('sha256', $bytes);
        $film = MarketingVideoFilm::create(['owner_id' => $u->id, 'project_id' => $p->id, 'revision_id' => $r->id, 'fingerprint' => str_repeat('a', 64), 'status' => 'completed', 'input' => [], 'output_path' => $path, 'output_sha256' => $hash]);
        $item = MarketingLibraryAsset::where('source_id', $film->id)->firstOrFail();
        $service = app(MarketingPosts::class);
        $key = 'library:'.$item->id;
        $this->assertNull($service->asset($u->id, $key));
        $this->assertFalse(app(MarketingMediaReuse::class)->listing($u)['assets'][0]['selectable']);
        $film->update(['approved_at' => now(), 'approved_sha256' => $hash]);
        $this->assertSame('video', $service->asset($u->id, $key)['kind']);
        $this->assertSame($hash, hash_file('sha256', $service->videoFile($u->id, $key)));
        $film->update(['approved_sha256' => str_repeat('b', 64)]);
        $this->assertNull($service->asset($u->id, $key));
    }

    public function test_prepared_image_deliveries_and_legacy_execution_recheck_library_origin(): void
    {
        $u = $this->owner();
        [$source,$item] = $this->image($u);
        $target = $this->project($u);
        $ref = app(MarketingMediaReuse::class)->import($u, $target->id, $item->id, true);
        $copy = MarketingAsset::findOrFail($ref['asset_id']);
        $source->update(['authorized' => false]);
        try {
            app(MarketingProductionCompiler::class)->sourcePath(['source' => 'private', 'asset_id' => $copy->id, 'path' => $copy->path, 'mime' => 'image/png', 'sha256' => $ref['sha256']]);
            $this->fail('Revoked copy accepted');
        } catch (MarketingProductionFailure $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->expectException(HttpExceptionInterface::class);
        app(MarketingMediaReuse::class)->assertPostSources($u->id, ['library:'.$item->id]);
    }

    public function test_archived_avatar_copy_retains_existing_use_but_cannot_be_selected_again(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $lib = app(MarketingAvatarLibrary::class);
        $avatar = $lib->saveDraft($u, $lib->home($u)->id, (string) Str::uuid(), ['name' => 'Published host', 'kind' => 'synthetic']);
        $lib->reference($u, $avatar->id, UploadedFile::fake()->image('person.png', 300, 400), true);
        $version = $lib->publish($u, $avatar->id, (string) Str::uuid(), true);
        $item = MarketingLibraryAsset::where('source_type', 'marketing_avatar_versions')->where('source_id', $version->id)->firstOrFail();
        $target = $this->project($u);
        $ref = app(MarketingMediaReuse::class)->import($u, $target->id, $item->id, true);
        $copy = MarketingLibraryAsset::where('source_id', $ref['asset_id'])->firstOrFail();
        $lib->state($u, $avatar->id, 'archived');
        $this->assertSame($ref['asset_id'], app(MarketingStoryboardUpload::class)->reference($target->id, $ref['asset_id'])['asset_id']);
        $next = $this->project($u);
        $this->actingAs($u)->postJson('/admin/marketing/workspace/'.$next->id.'/storyboard/library', ['library_asset_id' => $copy->id, 'authorized' => 1])->assertStatus(409);
    }

    public function test_foreign_actor_and_missing_consent_cannot_import(): void
    {
        $u = $this->owner();
        [$source,$item] = $this->image($u);
        $other = $this->owner();
        $target = $this->project($other);
        $this->actingAs($other);
        $url = '/admin/marketing/workspace/'.$target->id.'/storyboard/library';
        $this->postJson($url, ['library_asset_id' => $item->id, 'authorized' => 1])->assertNotFound();
        $this->actingAs($u)->postJson('/admin/marketing/workspace/'.$source->project_id.'/storyboard/library', ['library_asset_id' => $item->id])->assertUnprocessable();
        $this->assertDatabaseCount('marketing_media_reuses',0);
    }
}
