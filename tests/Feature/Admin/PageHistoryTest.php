<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageCrudService;
use App\Domain\Cms\PageDraftService;
use App\Models\CmsMediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageHistoryTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();config(['search.require_review'=>false]);} // History regression isolates publication review; SearchFoundationTest covers the new gate.

    private function page(string $slug = 'history-page')
    {
        $d = json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')), true);
        $d['slug'] = $slug;
        $d['sections'] = [$d['sections'][0]];

        return app(PageCrudService::class)->create($d);
    }

    public function test_history_is_authenticated_and_restore_is_new_draft_preserving_live_and_later_history(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $p = $this->page();
        $list = $this->getJson('/admin/api/pages/'.$p->id.'/history')->assertOk()->json('data');
        $first = $list[0]['id'];
        $original = $p->draftDocument->document;
        $this->postJson('/admin/api/pages/'.$p->id.'/publish', ['lockVersion' => 1])->assertOk();
        $changed = $original;
        $changed['title'] = 'Changed draft';
        $this->patchJson('/admin/api/pages/'.$p->id.'/draft', ['lockVersion' => 1, 'document' => $changed])->assertOk();
        $this->postJson('/admin/api/pages/'.$p->id.'/history/'.$first.'/restore', ['lockVersion' => 2])->assertOk();
        $p->refresh();
        $this->assertSame($original, $p->draftDocument->document);
        $this->assertSame($original, $p->publishedDocument->document);
        $this->assertSame(3, $p->draftDocument->lock_version);
        $this->assertDatabaseHas('page_revisions', ['page_id' => $p->id, 'kind' => 'restore', 'restored_from' => $first]);
        $this->assertDatabaseHas('page_revisions', ['page_id' => $p->id, 'kind' => 'autosave']);
        $this->postJson('/admin/api/pages/'.$p->id.'/history/'.$first.'/restore', ['lockVersion' => 2])->assertStatus(409);
    }

    public function test_checkpoint_requires_current_version_and_versions_cannot_cross_pages(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $p = $this->page();
        $other = $this->page('other-history');
        $id = $this->getJson('/admin/api/pages/'.$p->id.'/history')->assertOk()->json('data.0.id');
        $this->getJson('/admin/api/pages/'.$other->id.'/history/'.$id)->assertNotFound();
        $this->postJson('/admin/api/pages/'.$p->id.'/history', ['name' => 'Client approved', 'lockVersion' => 1])->assertCreated();
        $this->postJson('/admin/api/pages/'.$p->id.'/history', ['name' => 'Wrong version', 'lockVersion' => 2])->assertStatus(409);
        $this->getJson('/admin/api/pages/'.$p->id.'/history')->assertOk()->assertJsonPath('data.0.name', 'Client approved');
        $this->postJson('/admin/api/pages/'.$p->id.'/history', ['name' => 'Client approved', 'lockVersion' => 1])->assertCreated();
        $this->assertSame(1, DB::table('page_revisions')->where('page_id', $p->id)->where('name', 'Client approved')->count());
    }

    public function test_guests_and_non_admins_cannot_read_or_restore_history(): void
    {
        $p = $this->page();
        $this->getJson('/admin/api/pages/'.$p->id.'/history')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->getJson('/admin/api/pages/'.$p->id.'/history')->assertUnauthorized();
    }

    public function test_automatic_retention_keeps_100_while_protecting_checkpoints_and_ai_source(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $p = $this->page();
        $d = $p->draftDocument->document;
        $this->postJson('/admin/api/pages/'.$p->id.'/history', ['name' => 'Protected', 'lockVersion' => 1])->assertCreated();
        for ($v = 1; $v <= 103; $v++) {
            $d['title'] = 'Title '.$v;
            app(PageDraftService::class)->save($p, $d, $v);
        }
        $this->assertSame(100, DB::table('page_revisions')->where('page_id', $p->id)->where('kind', 'autosave')->count());
        $this->assertDatabaseHas('page_revisions', ['page_id' => $p->id, 'kind' => 'checkpoint', 'name' => 'Protected']);
        $d['title'] = 'AI copy';
        $this->withHeader('X-CMS-Change-Source', 'ai_apply')->patchJson('/admin/api/pages/'.$p->id.'/draft', ['lockVersion' => 104, 'document' => $d])->assertOk();
        $this->assertDatabaseHas('page_revisions', ['page_id' => $p->id, 'kind' => 'ai_apply', 'draft_version' => 105]);
        $this->getJson('/admin/api/pages/'.$p->id.'/history')->assertOk()->assertJsonCount(50, 'data');
    }

    public function test_failed_save_creates_no_revision_and_history_preserves_empty_object_tokens(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $p = $this->page();
        $before = DB::table('page_revisions')->count();
        $d = $p->draftDocument->document;
        $d['slug'] = 'INVALID slug';
        $this->patchJson('/admin/api/pages/'.$p->id.'/draft', ['lockVersion' => 1, 'document' => $d])->assertUnprocessable();
        $this->assertSame($before, DB::table('page_revisions')->count());
        $id = $this->getJson('/admin/api/pages/'.$p->id.'/history')->json('data.0.id');
        $this->getJson('/admin/api/pages/'.$p->id.'/history/'.$id)->assertOk()->assertJsonPath('data.document.title', $p->draftDocument->document['title']);
    }

    public function test_history_only_media_stays_protected_until_page_deletion(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $asset = CmsMediaAsset::query()->create(['id' => (string) Str::uuid(), 'disk' => 'public', 'path' => 'cms/media/history.png', 'url' => 'https://images.unsplash.com/history.png', 'mime_type' => 'image/png', 'size' => 10, 'checksum' => str_repeat('a', 64), 'orphaned_at' => null]);
        Storage::disk('public')->put($asset->path, 'test');
        $d = json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')), true);
        $d['slug'] = 'history-media';
        $d['sections'] = [$d['sections'][0]];
        $d['heroImage'] = $asset->url;
        $page = app(PageCrudService::class)->create($d);
        $d['heroImage'] = null;
        app(PageDraftService::class)->save($page, $d, 1);
        $this->assertDatabaseMissing('cms_media_references', ['asset_id' => $asset->id]);
        $this->assertDatabaseHas('page_revision_media', ['asset_id' => $asset->id]);
        $this->assertNull($asset->fresh()->orphaned_at);
        app(PageCrudService::class)->delete($page);
        $this->assertNotNull($asset->fresh()->orphaned_at);
        $this->assertDatabaseMissing('page_revision_media', ['asset_id' => $asset->id]);
    }
}
