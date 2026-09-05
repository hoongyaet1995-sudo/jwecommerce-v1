<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Page;
use App\Domain\Cms\HomepageSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug): array
    {
        return $this->postJson('/admin/api/pages', ['title' => $slug, 'slug' => $slug, 'template' => 'landing', 'sections' => [
            ['id' => 'hero', 'type' => 'hero_banner', 'title' => 'Published '.$slug, 'isVisible' => true],
        ]])->assertCreated()->json('data');
    }

    public function test_switch_publishes_draft_and_root_renders_published_snapshot_with_one_home(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $a = $this->page('first-home'); $b = $this->page('second-home');
        $this->postJson('/admin/api/pages/'.$a['id'].'/homepage', ['lockVersion' => $a['lockVersion']])->assertOk()->assertJsonPath('data.isHomepage', true)->assertJsonPath('data.status', 'published');
        $this->get('/')->assertOk()->assertSee('Published first-home');
        $this->postJson('/admin/api/pages/'.$b['id'].'/homepage', ['lockVersion' => $b['lockVersion']])->assertOk();
        $this->assertSame($b['id'], app(HomepageSelection::class)->id());
        $this->assertCount(1, array_filter($this->getJson('/admin/api/pages')->json('data'), fn ($p) => $p['isHomepage']));
        $this->get('/')->assertOk()->assertSee('Published second-home')->assertDontSee('Published first-home');
        $page = Page::findOrFail($b['id']);
        $draft = $page->draftDocument;
        $doc = $draft->document; $doc['sections'][0]['title'] = 'UNPUBLISHED SECRET';
        $this->patchJson('/admin/api/pages/'.$b['id'].'/draft', ['lockVersion' => $draft->lock_version, 'document' => $doc])->assertOk();
        $this->get('/')->assertOk()->assertDontSee('UNPUBLISHED SECRET')->assertSee('Published second-home');
        foreach (['archive', 'unpublish'] as $action) $this->postJson('/admin/api/pages/'.$b['id'].'/'.$action)->assertUnprocessable();
        $this->deleteJson('/admin/api/pages/'.$b['id'])->assertUnprocessable();
        $this->postJson('/admin/api/pages/'.$b['id'].'/duplicate')->assertCreated()->assertJsonPath('data.isHomepage', false);
    }

    public function test_failed_or_stale_publication_keeps_existing_home_and_guest_cannot_switch(): void
    {
        $this->postJson('/admin/api/pages/1/homepage', ['lockVersion' => 0])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $a = $this->page('stable');
        $this->postJson('/admin/api/pages/'.$a['id'].'/homepage', ['lockVersion' => $a['lockVersion']])->assertOk();
        $b = $this->page('invalid');
        $this->postJson('/admin/api/pages/'.$b['id'].'/homepage', ['lockVersion' => 999])->assertConflict();
        $page = Page::find($b['id']); $doc = $page->draftDocument->document; $doc['sections'][0]['title'] = '';
        $updated = $this->patchJson('/admin/api/pages/'.$b['id'].'/draft', ['lockVersion' => $b['lockVersion'], 'document' => $doc])->assertOk()->json('data');
        $this->postJson('/admin/api/pages/'.$b['id'].'/homepage', ['lockVersion' => $updated['lockVersion']])->assertUnprocessable();
        $this->assertSame($a['id'], app(HomepageSelection::class)->id());
    }
}
