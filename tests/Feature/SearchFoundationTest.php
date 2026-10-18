<?php

namespace Tests\Feature;

use App\Domain\Cms\PageCrudService;
use App\Domain\Cms\PageDraftService;
use App\Domain\Cms\PagePublicationService;
use App\Models\AiConnection;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\AI\AiConnectionService;
use App\Services\Search\SearchMetadata;
use App\Services\Search\SearchOptimization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $values = []): Product
    {
        return Product::create(array_merge(['handle' => 'container-'.uniqid(), 'title' => '20ft Storage Container', 'subtitle' => 'Storage options for your site.', 'base_price' => 10000, 'status' => 'active'], $values));
    }

    private function enable(string $mode = 'auto'): void
    {
        StoreSetting::updateOrCreate(['key' => 'search_visibility'], ['value' => ['mode' => $mode, 'version' => 1, 'preview' => false, 'daily_limit' => 100]]);
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'search_enabled' => true, 'support_enabled' => false, 'builder_enabled' => false, 'verified_at' => now(), 'text_model' => 'fixture', 'api_key' => 'fixture-only']);
    }

    public function test_actual_catalog_name_and_description_generate_base_without_provider(): void
    {
        $product = Product::create(['handle' => '20ft-storage', 'base_price' => 10000, 'status' => 'active', 'title' => '20ft Storage Container', 'subtitle' => 'Storage options for your site.', 'seo_title' => null, 'seo_description' => null]);
        $data = app(SearchMetadata::class)->for('product', $product->id);
        $this->assertStringContainsString('20ft Storage Container', $data['title']);
        $this->assertSame('Storage options for your site.', $data['description']);
        $this->assertSame('system', $data['source']);
        $this->assertStringNotContainsString('Aurora', $data['title']);
    }

    public function test_ai_replacement_is_audited_and_actual_public_head_consumes_it(): void
    {
        $p = $this->product(['seo_title' => 'Old dummy | Aurora', 'seo_description' => 'Old description']);
        $this->enable();
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->with('search', \Mockery::type('string'), 1800)->andReturn(json_encode(['title' => '20ft Storage Container', 'description' => 'Storage options for your site.', 'suggestedTopics' => ['Site storage']]));
        $ai = app(SearchOptimization::class);
        $task = $ai->request('product', $p->id, 'live', true);
        $result = $ai->run($task);
        $this->assertSame('applied', $result->status);
        $this->assertStringContainsString('Old dummy', $result->before['title']);
        $this->assertSame('20ft Storage Container', $p->fresh()->seo_title);
        $response = $this->get('/products/'.$p->handle)->assertOk();
        $response->assertSee('rel="canonical"', false)->assertSee('Storage options for your site.', false)->assertDontSee('Old dummy');
    }

    public function test_stale_source_and_manual_change_cannot_be_overwritten(): void
    {
        $p = $this->product(['seo_title' => 'Preserved']);
        $this->enable();
        $ai = app(SearchOptimization::class);
        $task = $ai->request('product', $p->id, 'live', true);
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $p->forceFill(['title' => '40ft Changed Container'])->saveQuietly();
        $result = $ai->run($task);
        $this->assertSame('stale', $result->status);
        $this->assertSame('Preserved', $p->fresh()->seo_title);
    }

    public function test_invalid_ai_claims_keep_previous_metadata_and_dedupe_failed_task(): void
    {
        $p = $this->product(['seo_title' => 'Preserved']);
        $this->enable();
        $ai = app(SearchOptimization::class);
        $task = $ai->request('product', $p->id, 'live', true);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn(json_encode(['title' => '20ft Container', 'description' => 'Free delivery to Singapore with 10 year warranty.', 'suggestedTopics' => []]));
        $this->assertSame('rejected', $ai->run($task)->status);
        $this->assertSame('Preserved', $p->fresh()->seo_title);
        $this->assertSame($task->id, $ai->request('product', $p->id, 'live', true)->id);
    }

    public function test_review_candidate_does_not_apply_until_owner_accepts(): void
    {
        $p = $this->product(['seo_title' => 'Preserved']);
        $this->enable('review');
        $ai = app(SearchOptimization::class);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn(json_encode(['title' => '20ft Storage Container', 'description' => 'Storage options for your site.', 'suggestedTopics' => []]));
        $task = $ai->run($ai->request('product', $p->id, 'live', true));
        $this->assertSame('candidate', $task->status);
        $this->assertSame('Preserved', $p->fresh()->seo_title);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $this->postJson('/admin/api/search/tasks/'.$task->id.'/apply')->assertOk();
        $this->assertSame('20ft Storage Container', $p->fresh()->seo_title);
    }

    public function test_settings_and_overrides_are_owner_protected_and_versioned(): void
    {
        $p = $this->product();
        $this->getJson('/admin/api/search/settings')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $data = ['version' => 0, 'titleLocked' => true, 'descriptionLocked' => false, 'title' => 'Manual choice', 'description' => null, 'excluded' => true];
        $this->putJson('/admin/api/search/product/'.$p->id, $data)->assertOk()->assertJsonPath('metadata.titleLocked', true);
        $this->putJson('/admin/api/search/product/'.$p->id, $data)->assertConflict();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($p->handle);
        $this->get('/products/'.$p->handle)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/account/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_provider_authentication_failure_pauses_search_until_connection_version_changes(): void
    {
        $p = $this->product(['seo_title' => 'Preserved']);
        $this->enable();
        $ai = app(SearchOptimization::class);
        $task = $ai->request('product', $p->id, 'live', true);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andThrow(new \App\Services\AI\ProviderFailure('authentication'));
        $this->assertSame('failed', $ai->run($task)->status);
        $this->assertSame('Preserved', $p->fresh()->seo_title);
        $this->assertNull($ai->connection());
        $this->assertNull($ai->request('product', $p->id, 'live', true, null, true));
        $connection = AiConnection::firstOrFail();
        $connection->update(['version' => $connection->version + 1]);
        $this->assertNotNull($ai->connection());
    }

    public function test_batch_preview_is_exact_bounded_and_never_queues_provider_work(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $this->product()->id;
        }
        $this->enable();
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));

        $this->postJson('/admin/api/search/batch/product', ['ids' => [$ids[0], $ids[25], $ids[25]], 'confirm' => false])
            ->assertOk()->assertJsonPath('count', 2)->assertJsonPath('ids', [$ids[0], $ids[25]])->assertJsonPath('maximumRequests', 2);
        $this->postJson('/admin/api/search/batch/product', ['ids' => $ids, 'confirm' => false])
            ->assertOk()->assertJsonPath('count', 100)->assertJsonPath('maximumRequests', 100)->assertJsonPath('model', 'fixture');
        $this->postJson('/admin/api/search/batch/product', ['ids' => [...$ids, 999999], 'confirm' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->assertDatabaseCount('search_tasks', 0);
    }

    public function test_one_refresh_covers_all_kinds_reuses_active_run_and_applies_review_mode_to_drafts_only(): void
    {
        $product = $this->product();
        \App\Models\Category::create(['name' => 'Storage', 'slug' => 'refresh-storage', 'description' => 'Storage options.']);
        \App\Models\Collection::create(['title' => 'Storage', 'slug' => 'refresh-range', 'description' => 'Storage options.', 'type' => 'manual']);
        $page = $this->page();
        app(PagePublicationService::class)->publish($page, 1);
        $published = $page->fresh()->publishedDocument->content_hash;
        $this->enable('review');
        $this->getJson('/admin/api/search/refresh')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]));
        $this->postJson('/admin/api/search/refresh')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $run = $this->postJson('/admin/api/search/refresh')->assertAccepted()->assertJsonPath('total', 4)->json('id');
        $this->postJson('/admin/api/search/refresh')->assertAccepted()->assertJsonPath('id', $run);
        $this->assertDatabaseCount('search_tasks', 4);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->times(4)->andReturnUsing(function ($consumer, $prompt) {
            $facts = json_decode(explode("FACTS:\n", $prompt)[1], true);
            return json_encode(['title' => $facts['name'], 'description' => $facts['description'] ?: $facts['name'], 'suggestedTopics' => []]);
        });
        foreach (\App\Models\SearchTask::all() as $task) {
            $this->postJson('/admin/api/search/refresh/'.$run.'/advance')->assertOk();
            $this->assertSame('applied', $task->fresh()->status);
        }
        $this->getJson('/admin/api/search/refresh')->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('completed', 4);
        $this->assertSame($published, $page->fresh()->publishedDocument->content_hash);
        $this->assertSame('20ft Storage Container', $product->fresh()->seo_title);
        $next = $this->postJson('/admin/api/search/refresh')->assertAccepted()->json('id');
        $this->assertNotSame($run, $next);
        $this->assertDatabaseCount('search_tasks', 8);
    }

    public function test_system_refresh_preserves_canonical_page_locks_and_never_calls_provider(): void
    {
        $product = $this->product(['seo_title' => 'Old metadata']);
        $page = $this->page();
        $document = $page->draftDocument->document;
        $document['seoTitle'] = 'Protected title';
        $document['seoSettings'] = ['titleLocked' => true, 'descriptionLocked' => false, 'excluded' => true];
        app(PageDraftService::class)->save($page, $document, 1);
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $this->postJson('/admin/api/search/refresh')->assertAccepted()->assertJsonPath('total', 2);
        $this->travel(6)->seconds();
        $this->artisan('search:work', ['--limit' => 10])->assertExitCode(0);
        foreach (\App\Models\SearchTask::all() as $task) {
            $this->assertSame('applied', $task->status);
        }
        $this->assertSame('20ft Storage Container', $product->fresh()->seo_title);
        $this->assertSame('Protected title', $page->fresh()->draftDocument->document['seoTitle']);
        $this->assertTrue($page->fresh()->draftDocument->document['seoSettings']['excluded']);
        $this->getJson('/admin/api/search/refresh')->assertOk()->assertJsonPath('status', 'completed');
    }

    public function test_refresh_has_no_daily_cap_and_does_not_hijack_running_work(): void
    {
        $this->product();
        $this->enable();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $this->postJson('/admin/api/search/refresh')->assertAccepted();
        $task = \App\Models\SearchTask::firstOrFail();
        $task->update(['status' => 'running']);
        $this->getJson('/admin/api/search/refresh')->assertJsonPath('active', true);
        $this->postJson('/admin/api/search/refresh')->assertAccepted();
        $task->update(['refresh_id' => null]);
        $this->postJson('/admin/api/search/refresh')->assertConflict();
        $task->update(['status' => 'queued']);
        $this->postJson('/admin/api/search/refresh')->assertAccepted();
        $task->update(['attempts' => 1, 'started_at' => now(), 'error' => 'daily_task_limit', 'available_at' => now()->addDay()]);
        StoreSetting::where('key', 'search_visibility')->update(['value' => ['mode' => 'auto', 'daily_limit' => 1]]);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn(json_encode(['title' => '20ft Storage Container', 'description' => 'Storage options for your site.', 'suggestedTopics' => []]));
        $this->artisan('search:work')->assertExitCode(0);
        $this->assertSame('applied', $task->fresh()->status);
        $this->getJson('/admin/api/search/refresh')->assertOk()->assertJsonPath('active', false);
        $this->assertDatabaseCount('search_tasks', 1);
        $this->getJson('/admin/api/search/settings')->assertOk()->assertJsonMissingPath('settings.daily_limit');
        $this->putJson('/admin/api/search/settings', ['version' => 0, 'mode' => 'auto', 'preview' => false])->assertOk()->assertJsonMissingPath('settings.daily_limit');
        $this->assertArrayNotHasKey('daily_limit', StoreSetting::where('key', 'search_visibility')->firstOrFail()->value);
    }

    public function test_manual_refresh_resumes_without_scheduler_or_worker(): void
    {
        $this->product();
        $page = $this->page();
        $this->enable();
        app(\App\Services\Search\SearchProducer::class)->pageSaved($page->id, 'saved');
        $this->assertDatabaseCount('search_tasks', 0);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $id = $this->postJson('/admin/api/search/refresh')->assertAccepted()->json('id');
        $this->postJson('/admin/api/search/refresh')->assertAccepted()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('search_refreshes', 1);
        $this->assertDatabaseCount('search_tasks', 2);
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $this->assertFalse(collect($events)->contains(fn ($e) => str_contains($e->command ?? '', 'search:')));
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->times(2)->andReturnUsing(function ($consumer, $prompt) {
            $facts = json_decode(explode("FACTS:\n", $prompt)[1], true);
            return json_encode(['title' => $facts['name'], 'description' => $facts['description'] ?: $facts['name'], 'suggestedTopics' => []]);
        });
        $this->postJson('/admin/api/search/refresh/'.$id.'/advance')->assertOk()->assertJsonPath('active', true);
        $this->getJson('/admin/api/search/settings')->assertOk()->assertJsonPath('refresh.id', $id);
        $this->postJson('/admin/api/search/refresh/'.$id.'/advance')->assertOk()->assertJsonPath('status', 'completed');
        $this->postJson('/admin/api/search/refresh/'.$id.'/advance')->assertOk()->assertJsonPath('status', 'completed');
        $this->assertSame(2, \App\Models\SearchTask::where('status', 'applied')->count());
    }

    public function test_refresh_progress_polling_has_its_own_read_allowance(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        for ($i = 0; $i < 25; $i++) {
            $this->getJson('/admin/api/search/refresh')->assertOk();
        }
        $this->assertDatabaseCount('search_refreshes', 0);
    }

    public function test_refresh_migration_rolls_back_without_removing_existing_task_audit(): void
    {
        $migration = require database_path('migrations/2026_10_18_063000_create_search_refreshes.php');
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('search_refreshes'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('search_tasks'));
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('search_tasks', 'refresh_id'));
    }

    private function page()
    {
        $doc = json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')), true);
        $doc['slug'] = 'search-review';
        $doc['title'] = 'Container services';
        $doc['sections'] = [$doc['sections'][0]];
        $doc['sections'][0]['secondaryButtonText']='';
        $doc['sections'][0]['content']='Actual container information for project planning.';

        return app(PageCrudService::class)->create($doc);
    }

    public function test_publication_review_is_required_and_bound_to_current_actor_and_revision(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $p = $this->page();
        $this->postJson('/admin/api/pages/'.$p->id.'/publish', ['lockVersion' => 1])->assertUnprocessable();
        $this->getJson('/admin/api/search/pages/'.$p->id.'/review')->assertOk()->assertJsonPath('blocked', false);
        $token = $this->postJson('/admin/api/search/pages/'.$p->id.'/review', ['lockVersion' => 1, 'confirmations' => [true, true, true]])->assertOk()->json('token');
        $changed = $p->draftDocument->document;
        $changed['description'] = 'Updated source details.';
        app(PageDraftService::class)->save($p, $changed, 1);
        $this->withHeader('X-Search-Review', $token)->postJson('/admin/api/pages/'.$p->id.'/publish', ['lockVersion' => 2])->assertConflict();
        $token = $this->postJson('/admin/api/search/pages/'.$p->id.'/review', ['lockVersion' => 2, 'confirmations' => [true, true, true]])->assertOk()->json('token');
        $this->withHeader('X-Search-Review', $token)->postJson('/admin/api/pages/'.$p->id.'/publish', ['lockVersion' => 2])->assertOk();
        $this->assertDatabaseHas('search_publication_reviews', ['page_id' => $p->id, 'lock_version' => 2]);
    }

    public function test_cms_ai_only_updates_draft_and_manual_policy_is_in_history_document(): void
    {
        $p = $this->page();
        app(PagePublicationService::class)->publish($p, 1);
        $before = $p->fresh()->publishedDocument->document;
        $this->enable();
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn(json_encode(['title' => 'Container services', 'description' => 'Container services.', 'suggestedTopics' => []]));
        $ai = app(SearchOptimization::class);
        $task = $ai->run($ai->request('page', $p->id, 'draft', true));
        $this->assertSame('applied', $task->status);
        $this->assertSame($before, $p->fresh()->publishedDocument->document);
        $this->assertSame('Container services.', $p->fresh()->draftDocument->document['seoDescription']);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $meta = app(SearchMetadata::class)->for('page', $p->id);
        $this->putJson('/admin/api/search/page/'.$p->id, ['version' => $meta['version'], 'titleLocked' => true, 'descriptionLocked' => false, 'title' => 'Manual title', 'description' => '', 'excluded' => true])->assertOk();
        $this->assertTrue($p->fresh()->draftDocument->document['seoSettings']['excluded']);
        $this->assertArrayNotHasKey('seoSettings',$p->fresh()->publishedDocument->document);
    }

    public function test_cms_seo_publish_restore_and_republish_follow_reviewed_draft(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $p = $this->page();
        $doc = $p->draftDocument->document;
        $doc['seoTitle'] = 'Original search title';
        $doc['seoDescription'] = 'Original search description.';
        $doc['seoSettings'] = ['titleLocked' => true, 'descriptionLocked' => true, 'excluded' => false];
        $this->patchJson('/admin/api/pages/'.$p->id.'/draft', ['lockVersion' => 1, 'document' => $doc])->assertOk();
        $original = $this->postJson('/admin/api/pages/'.$p->id.'/history', ['lockVersion' => 2, 'name' => 'Original SEO'])->assertCreated()->json('id');
        $publish = function (int $version) use ($p): void {
            $token = $this->postJson('/admin/api/search/pages/'.$p->id.'/review', ['lockVersion' => $version, 'confirmations' => [true, true, true]])->assertOk()->json('token');
            $this->withHeader('X-Search-Review', $token)->postJson('/admin/api/pages/'.$p->id.'/publish', ['lockVersion' => $version])->assertOk();
        };
        $publish(2);
        $path = '/pages/search-review';
        $this->get($path)->assertOk()->assertSee('<title>Original search title', false)->assertSee('Original search description.');
        $doc['seoTitle'] = 'Updated search title';
        $doc['seoDescription'] = 'Updated search description.';
        $this->patchJson('/admin/api/pages/'.$p->id.'/draft', ['lockVersion' => 2, 'document' => $doc])->assertOk();
        $this->get($path)->assertOk()->assertSee('<title>Original search title', false)->assertDontSee('Updated search description.');
        $publish(3);
        $this->get($path)->assertOk()->assertSee('<title>Updated search title', false)->assertSee('Updated search description.');
        $this->postJson('/admin/api/pages/'.$p->id.'/history/'.$original.'/restore', ['lockVersion' => 3])->assertOk();
        $this->get($path)->assertOk()->assertSee('<title>Updated search title', false);
        $this->getJson('/admin/api/search/page/'.$p->id)->assertOk()->assertJsonPath('metadata.rawTitle', 'Original search title')->assertJsonPath('metadata.titleLocked', true);
        $publish(4);
        $this->get($path)->assertOk()->assertSee('<title>Original search title', false)->assertDontSee('Updated search description.');
    }
}
