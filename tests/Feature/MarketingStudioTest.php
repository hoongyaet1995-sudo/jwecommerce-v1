<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\MarketingAiRun;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\Product;
use App\Models\User;
use App\Services\Marketing\MarketingTextClient;
use App\Services\Marketing\MarketingWorkflow;
use App\Services\Marketing\MarketingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketingStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_studio_requires_owner_and_uses_isolated_document(): void
    {
        $this->get('/admin/marketing')->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))->get('/admin/marketing')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))->get('/admin/marketing')
            ->assertOk()->assertSee('Marketing Studio')->assertSee('/marketing-studio/')->assertDontSee('/build-admin/');
    }

    public function test_theme_project_is_saved_reopened_and_private(): void
    {
        $owner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($owner)->post('/admin/marketing/projects', ['source_type' => 'theme', 'theme' => 'An introduction to our store'])
            ->assertRedirect();
        $this->assertDatabaseHas('marketing_projects', ['owner_id' => $owner->id, 'source_type' => 'theme']);
        $project = MarketingProject::firstOrFail();
        $this->get('/admin/marketing/projects/'.$project->id)->assertOk()->assertSee('An introduction to our store');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))->get('/admin/marketing/projects/'.$project->id)->assertNotFound();
    }

    private function project(bool $ready = true): MarketingProject
    {
        $owner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($owner);
        $p = app(MarketingWorkflow::class)->create($owner, ['source_type' => 'theme', 'theme' => 'A real store introduction']);
        if ($ready) {
            $p->update(['brief' => array_replace($p->brief, ['source_confirmed' => true, 'facts_confirmed' => true, 'goal_confirmed' => true, 'audience_confirmed' => true, 'market' => 'Malaysia', 'language' => 'English', 'platforms' => ['Instagram']]), 'stage' => 'concepts']);
        }

        return $p->fresh();
    }

    private function concepts(): array
    {
        return ['concepts' => array_map(fn ($i) => ['title' => 'Direction '.$i, 'approach' => ['use_scenario', 'feature_detail', 'question_answer'][$i - 1], 'hook' => 'Opening '.$i, 'story' => 'A different story '.$i, 'materials' => 'Our real photos', 'duration' => 10, 'ratio' => '9:16', 'recommended' => $i === 1, 'reason' => 'Fits the confirmed brief'], [1, 2, 3])];
    }

    public function test_new_project_immediately_organizes_source_when_text_is_ready(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $writer = $this->mock(MarketingWriter::class);
        $writer->shouldReceive('ready')->andReturn(true);
        $writer->shouldReceive('propose')->once()->andReturn(['source_summary' => 'A factual theme introduction', 'questions' => [], 'goal' => 'introduction', 'goal_reason' => 'Start with confirmed facts', 'cta' => 'Learn more', 'audience' => 'Interested visitors', 'recommendation_reason' => 'Fits this theme']);
        $this->post('/admin/marketing/projects', ['source_type' => 'theme', 'theme' => 'Our actual store introduction'])->assertRedirect();
        $p = MarketingProject::firstOrFail();
        $this->assertSame('A factual theme introduction', $p->brief['source_summary']);
    }

    private function storyboard(): array
    {
        return ['summary' => 'A factual store introduction', 'duration' => 10, 'cta' => 'Learn more', 'changes' => [], 'gaps' => [], 'scenes' => [['visual' => 'Show the actual storefront', 'voiceover' => 'Welcome to our store', 'subtitle' => 'Visit our store', 'duration' => 10, 'material' => 'Use our real photo', 'asset_ids' => []]]];
    }

    private function generate(MarketingProject $p, string $action, array $extra = [])
    {
        return $this->from('/admin/marketing/projects/'.$p->id)->post('/admin/marketing/projects/'.$p->id.'/generate', array_replace(['version' => $p->version, 'request_id' => (string) Str::uuid(), 'action' => $action], $extra));
    }

    private function withStoryboard(): MarketingProject
    {
        $p = $this->project();
        $p->update(['concepts' => $this->concepts()['concepts'], 'selected_concept' => 0, 'stage' => 'storyboard']);
        $this->partialMock(MarketingWriter::class)->shouldReceive('propose')->andReturn($this->storyboard());
        $this->generate($p, 'storyboard')->assertRedirect();

        return $p->fresh();
    }

    public function test_new_review_note_is_visible_without_rewriting_old_gap_or_authorizing_production(): void
    {
        $p=$this->project();$p->update(['concepts'=>$this->concepts()['concepts'],'selected_concept'=>0,'stage'=>'storyboard']);
        $old=$this->storyboard();$old['gaps']=['Historical unresolved item'];
        $next=$this->storyboard();$next['notes']=['Final details are confirmed at quotation <not an availability promise>.'];
        $this->partialMock(MarketingWriter::class)->shouldReceive('propose')->twice()->andReturn($old,$next);
        $this->generate($p,'storyboard')->assertRedirect();$p->refresh();$historical=$p->currentRevision;$saved=$historical->getRawOriginal();
        $this->generate($p,'revise',['parent_id'=>$historical->id,'feedback'=>'Keep confirmed claims; review the quotation boundary.'])->assertRedirect();$p->refresh();
        $this->assertNotSame($historical->id,$p->current_revision_id);$this->assertSame($saved,$historical->fresh()->getRawOriginal());
        $this->get('/admin/marketing/projects/'.$p->id)->assertOk()->assertSee('Review notes:')->assertSee($next['notes'][0])->assertDontSee($next['notes'][0],false);
        $this->post('/admin/marketing/projects/'.$p->id.'/confirm',['version'=>$p->version,'revision_id'=>$p->current_revision_id,'content_confirmation'=>1])->assertRedirect();
        $this->assertSame('prepared',$p->fresh()->status);$this->assertNull($historical->fresh()->confirmed_at);
        $this->assertDatabaseCount('marketing_productions',0);$this->assertDatabaseCount('marketing_production_budgets',0);
    }

    public function test_brief_steps_keep_product_snapshot_and_require_explicit_confirmations(): void
    {
        $p = $this->project(false);
        $source = $p->snapshot;
        $this->post('/admin/marketing/projects/'.$p->id.'/brief', ['version' => $p->version, 'step' => 'source', 'name' => 'My introduction', 'source_confirmed' => 1, 'facts_confirmed' => 1, 'facts' => 'Only these actual facts'])->assertRedirect();
        $p->refresh();
        $this->assertSame('goal', $p->stage);
        $this->assertSame($source, $p->snapshot);
        $this->post('/admin/marketing/projects/'.$p->id.'/brief', ['version' => $p->version, 'step' => 'goal', 'goal' => 'enquiry', 'cta' => 'Ask us', 'destination' => ''])->assertRedirect();
        $p->refresh();
        $this->assertSame('audience', $p->stage);
        $this->post('/admin/marketing/projects/'.$p->id.'/brief', ['version' => $p->version, 'step' => 'audience', 'audience' => 'People needing advice', 'market' => 'Malaysia', 'language' => 'English', 'platforms' => ['Instagram', 'TikTok']])->assertRedirect();
        $p->refresh();
        $this->assertSame('concepts', $p->stage);
        $this->assertSame(['Instagram', 'TikTok'], $p->brief['platforms']);
    }

    public function test_catalog_snapshot_is_read_only_and_does_not_change_with_source(): void
    {
        $p = $this->project(false);
        $product = Product::create(['title' => 'Real product', 'handle' => 'marketing-qa', 'status' => 'active', 'base_price' => 25]);
        $before = $product->fresh()->getRawOriginal();
        $project = app(MarketingWorkflow::class)->create(User::find($p->owner_id), ['source_type' => 'product', 'product_id' => $product->id]);
        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $product->update(['title' => 'New catalog title']);
        $this->assertSame('Real product', $project->fresh()->snapshot['title']);
    }

    public function test_completed_ai_request_is_not_called_again_on_duplicate_post(): void
    {
        $p = $this->project();
        $id = (string) Str::uuid();
        $this->partialMock(MarketingWriter::class)->shouldReceive('propose')->once()->andReturn($this->concepts());
        $this->generate($p, 'concepts', ['request_id' => $id])->assertRedirect();
        $this->generate($p, 'concepts', ['request_id' => $id])->assertRedirect();
        $this->assertSame(1, MarketingAiRun::count());
        $this->assertCount(3, $p->fresh()->concepts);
    }

    public function test_provider_failure_preserves_feedback_and_creates_no_empty_revision(): void
    {
        $p = $this->withStoryboard();
        $original = $p->current_revision_id;
        $this->partialMock(MarketingWriter::class)->shouldReceive('propose')->once()->andThrow(new \RuntimeException('fixture provider failure'));
        $this->generate($p, 'revise', ['feedback' => 'Please shorten the opening'])->assertSessionHasErrors('generation');
        $p->refresh();
        $this->assertSame($original, $p->current_revision_id);
        $this->assertNull($p->busy_run_id);
        $this->assertSame('failed', $p->status);
        $this->assertSame(1, MarketingRevision::count());
        $this->assertDatabaseHas('marketing_ai_runs', ['feedback' => 'Please shorten the opening', 'status' => 'failed']);
    }

    public function test_confirmation_is_idempotent_and_later_revision_does_not_reuse_approval(): void
    {
        $p = $this->withStoryboard();
        $old = $p->currentRevision;
        $doc = $old->document;
        $data = ['version' => $p->version, 'revision_id' => $old->id, 'content_confirmation' => 1];
        $url = '/admin/marketing/projects/'.$p->id.'/confirm';
        $this->post($url, $data)->assertRedirect();
        $this->post($url, $data)->assertRedirect();
        $this->assertSame('prepared', $p->fresh()->status);
        $time = $old->fresh()->confirmed_at->toJSON();
        $p->refresh();
        $this->partialMock(MarketingWriter::class)->shouldReceive('propose')->once()->andReturn(array_replace($doc, ['changes' => ['Opening shortened']]));
        $this->generate($p, 'revise', ['feedback' => 'Shorten the opening'])->assertRedirect();
        $p->refresh();
        $this->assertNotSame($old->id, $p->current_revision_id);
        $this->assertSame($old->id, $p->confirmed_revision_id);
        $this->assertNull($p->currentRevision->confirmed_at);
        $this->assertSame($doc, $old->fresh()->document);
        $this->assertSame($time, $old->fresh()->confirmed_at->toJSON());
        $this->get('/admin/marketing/projects/'.$p->id)->assertOk()->assertSee('changes awaiting confirmation');
    }

    public function test_stale_writes_and_stale_confirmation_are_rejected(): void
    {
        $p = $this->withStoryboard();
        $this->post('/admin/marketing/projects/'.$p->id.'/brief', ['version' => 1, 'step' => 'goal', 'goal' => 'awareness', 'cta' => 'Know us'])->assertConflict();
        $this->post('/admin/marketing/projects/'.$p->id.'/confirm', ['version' => 1, 'revision_id' => $p->current_revision_id, 'content_confirmation' => 1])->assertConflict();
        $this->assertNull($p->currentRevision->fresh()->confirmed_at);
    }

    public function test_required_gaps_block_content_confirmation(): void
    {
        $p = $this->withStoryboard();
        $r = $p->currentRevision;
        $r->update(['document' => array_replace($r->document, ['gaps' => ['Provide a real product photo']])]);
        $this->post('/admin/marketing/projects/'.$p->id.'/confirm', ['version' => $p->version, 'revision_id' => $r->id, 'content_confirmation' => 1])->assertUnprocessable();
        $this->assertNull($r->fresh()->confirmed_at);
    }

    public function test_structural_contract_rejects_bad_cta_and_non_distinct_concepts(): void
    {
        $p = $this->project();
        $writer = new MarketingWriter;
        $context = ['brief' => $p->brief, 'assets' => []];
        $doc = $this->storyboard();
        $doc['cta'] = 'Invented new destination';
        try {
            $writer->validateOutput($doc, 'storyboard', $context);
            $this->fail('CTA mismatch accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('generation', $e->errors());
        }
        $concepts = $this->concepts();
        $concepts['concepts'][1] = $concepts['concepts'][0];
        try {
            $writer->validateOutput($concepts, 'concepts', $context);
            $this->fail('Duplicate directions accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('generation', $e->errors());
        }
    }

    public function test_private_media_cannot_be_read_by_another_owner(): void
    {
        Storage::fake('local');
        $p = $this->project(false);
        $file = UploadedFile::fake()->image('real-photo.jpg', 100, 100);
        $this->post('/admin/marketing/projects/'.$p->id.'/assets', ['version' => $p->version, 'permission' => 1, 'asset' => $file])->assertRedirect();
        $asset = $p->assets()->firstOrFail();
        $url = '/admin/marketing/projects/'.$p->id.'/assets/'.$asset->id;
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))->get($url)->assertNotFound();
    }

    public function test_source_switch_preserves_supplemental_facts_and_is_blocked_after_confirmation(): void
    {
        $p = $this->project(false);
        $p->update(['brief' => array_replace($p->brief, ['facts' => 'Keep these staff notes'])]);
        $this->post('/admin/marketing/projects/'.$p->id.'/source', ['version' => $p->version, 'source_type' => 'theme', 'theme' => 'A new proposed theme'])->assertRedirect();
        $p->refresh();
        $this->assertSame('A new proposed theme', $p->snapshot['title']);
        $this->assertSame('Keep these staff notes', $p->brief['facts']);
        $p->update(['brief' => array_replace($p->brief, ['source_confirmed' => true])]);
        $this->post('/admin/marketing/projects/'.$p->id.'/source', ['version' => $p->version, 'source_type' => 'theme', 'theme' => 'Must not silently replace source'])->assertConflict();
    }

    public function test_interrupted_request_is_only_released_explicitly_after_timeout_without_a_provider_call(): void
    {
        $p = $this->project();
        $run = MarketingAiRun::create(['project_id' => $p->id, 'request_id' => (string) Str::uuid(), 'action' => 'concepts', 'status' => 'running']);
        $p->update(['busy_run_id' => $run->id, 'status' => 'processing']);
        $this->post('/admin/marketing/projects/'.$p->id.'/interrupted', ['version' => $p->version, 'acknowledge' => 1])->assertConflict();
        $run->update(['created_at' => now()->subMinutes(11)]);
        $this->post('/admin/marketing/projects/'.$p->id.'/interrupted', ['version' => $p->version, 'acknowledge' => 1])->assertRedirect();
        $this->assertNull($p->fresh()->busy_run_id);
        $this->assertSame('unknown', $run->fresh()->status);
        $this->assertSame(0, MarketingRevision::count());
    }

    public function test_catalog_image_ids_are_not_offered_as_private_asset_ids_to_the_model(): void
    {
        config(['marketing.text_enabled' => true]);
        $p = $this->project();
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'verified_at' => now(), 'text_model' => 'gpt-5.6-luna', 'api_key' => 'fixture-only']);
        $run = MarketingAiRun::create(['project_id' => $p->id, 'request_id' => (string) Str::uuid(), 'action' => 'storyboard', 'status' => 'running']);
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode($this->storyboard())]]]]])]);
        $result = (new MarketingWriter)->propose(['run_id' => $run->id, 'snapshot' => ['images' => [['id' => 987654, 'path' => '/images/real-product.jpg']]], 'brief' => $p->brief, 'assets' => []], 'storyboard', '');
        $this->assertSame([], $result['scenes'][0]['asset_ids']);
        Http::assertSent(fn ($r) => str_contains($r['input'], 'catalog_path') && ! str_contains($r['input'], '987654'));
        Http::assertSent(fn ($r) => ($r['text']['format']['type'] ?? null) === 'json_object' && $r['store'] === false);
        $this->assertSame('0.0200', $run->fresh()->reserved_usd);
    }

    public function test_generated_required_gaps_are_reflected_in_project_status(): void
    {
        $p = $this->project();
        $p->update(['concepts' => $this->concepts()['concepts'], 'selected_concept' => 0]);
        $this->partialMock(MarketingWriter::class)->shouldReceive('propose')->andReturn(array_replace($this->storyboard(), ['gaps' => ['Confirm the photo permission']]));
        $this->generate($p, 'storyboard')->assertRedirect();
        $this->assertSame('needs_information', $p->fresh()->status);
    }

    public function test_reselecting_current_concept_preserves_confirmed_storyboard(): void
    {
        $p = $this->withStoryboard();
        app(MarketingWorkflow::class)->confirm($p->id, $p->owner_id, $p->version, $p->current_revision_id);
        $p->refresh();
        $before = $p->getAttributes();
        app(MarketingWorkflow::class)->selectConcept($p->id, $p->owner_id, $p->version, 0);
        $this->assertSame($before, $p->fresh()->getAttributes());
    }

    public function test_historical_revision_cannot_mix_a_different_selected_concept(): void
    {
        $p = $this->withStoryboard();
        $parent = $p->current_revision_id;
        app(MarketingWorkflow::class)->selectConcept($p->id, $p->owner_id, $p->version, 1);
        $p->refresh();
        $this->generate($p, 'storyboard')->assertRedirect();
        $p->refresh();
        $count = MarketingRevision::count();
        $this->generate($p, 'revise', ['feedback' => 'Shorten the opening', 'parent_id' => $parent])->assertSessionHasErrors('generation');
        $this->assertSame($count, MarketingRevision::count());
    }

    public function test_writer_selects_only_budget_approved_connection_even_when_another_is_enabled(): void
    {
        config(['marketing.text_enabled' => true]);
        $p = $this->project();
        AiConnection::create(['provider' => 'anthropic', 'enabled' => true, 'verified_at' => now(), 'text_model' => 'unpriced-model', 'api_key' => 'fixture-other']);
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'verified_at' => now(), 'text_model' => 'gpt-5.6-luna', 'api_key' => 'fixture-approved']);
        $run = MarketingAiRun::create(['project_id' => $p->id, 'request_id' => (string) Str::uuid(), 'action' => 'storyboard', 'status' => 'running']);
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode($this->storyboard())]]]]])]);
        (new MarketingWriter)->propose(['run_id' => $run->id, 'snapshot' => [], 'brief' => $p->brief, 'assets' => []], 'storyboard', '');
        Http::assertSent(fn ($request) => $request['model'] === 'gpt-5.6-luna');
        Http::assertSentCount(1);
    }

    public function test_exhausted_internal_budget_stops_before_any_provider_request(): void
    {
        config(['marketing.text_enabled' => true]);
        $p = $this->project();
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'verified_at' => now(), 'text_model' => 'gpt-5.6-luna', 'api_key' => 'fixture-only']);
        MarketingAiRun::create(['project_id' => $p->id, 'request_id' => (string) Str::uuid(), 'action' => 'storyboard', 'status' => 'unknown', 'reserved_usd' => 2]);
        $run = MarketingAiRun::create(['project_id' => $p->id, 'request_id' => (string) Str::uuid(), 'action' => 'storyboard', 'status' => 'running']);
        try {
            (new MarketingWriter)->propose(['run_id' => $run->id, 'snapshot' => [], 'brief' => $p->brief, 'assets' => []], 'storyboard', '');
            $this->fail('Budget ceiling was bypassed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('generation', $e->errors());
        }
        Http::assertNothingSent();
        $this->assertSame('0.0000', $run->fresh()->reserved_usd);
    }

    public function test_home_recent_limit_list_pagination_filters_and_escaped_names(): void
    {
        $p = $this->project(false);
        $p->update(['name' => '<script>alert(1)</script>']);
        for ($i = 0; $i < 16; $i++) {
            app(MarketingWorkflow::class)->create(auth()->user(), ['source_type' => 'theme', 'theme' => 'Additional theme '.$i]);
        }
        $this->get('/admin/marketing')->assertViewHas('recent', fn ($items) => $items->count() === 5)->assertViewHas('attention', fn ($items) => $items->count() === 17);
        $this->get('/admin/marketing/projects')->assertViewHas('projects', fn ($items) => $items->count() === 15 && $items->total() === 17);
        $this->get('/admin/marketing/projects?q=alert&status=needs_confirmation')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertViewHas('projects', fn ($items) => $items->total() === 1);
        $this->get('/admin/marketing/projects?status=prepared')->assertSee('No projects match your search.');
    }

    public function test_storyboard_rejects_voiceover_that_cannot_fit_the_suggested_scene(): void
    {
        $p = $this->project();
        $doc = $this->storyboard();
        $doc['scenes'][0]['voiceover'] = str_repeat('confirmed offering detail ', 30);
        $this->expectException(ValidationException::class);
        (new MarketingWriter)->validateOutput($doc, 'storyboard', ['brief' => $p->brief, 'assets' => []]);
    }

    public function test_cjk_voiceover_is_not_counted_as_one_word(): void
    {
        $p = $this->project();
        $doc = $this->storyboard();
        $doc['scenes'][0]['voiceover'] = str_repeat('这是确认的商品资料', 20);
        $this->expectException(ValidationException::class);
        (new MarketingWriter)->validateOutput($doc, 'storyboard', ['brief' => $p->brief, 'assets' => []]);
    }

    public function test_incomplete_provider_response_is_rejected_without_accepting_partial_output(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'incomplete', 'output' => [['content' => [['type' => 'output_text', 'text' => '{}']]]]])]);
        $this->expectException(ValidationException::class);
        app(MarketingTextClient::class)->text('fixture-only', 'gpt-5.6-luna', 'Return JSON');
    }

    public function test_reasonable_mixed_language_scene_and_silent_scene_remain_valid(): void
    {
        $p = $this->project();
        $doc = $this->storyboard();
        $doc['scenes'][0]['voiceover'] = '查看商品 Explore the coffee';
        $this->assertSame($doc, (new MarketingWriter)->validateOutput($doc, 'storyboard', ['brief' => $p->brief, 'assets' => []]));
        $doc['scenes'][0]['voiceover'] = null;
        $this->assertSame($doc, (new MarketingWriter)->validateOutput($doc, 'storyboard', ['brief' => $p->brief, 'assets' => []]));
    }

    public function test_malformed_json_failure_is_safe_and_does_not_trigger_an_automatic_retry(): void
    {
        config(['marketing.text_enabled' => true]);
        $p = $this->project();
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'verified_at' => now(), 'text_model' => 'gpt-5.6-luna', 'api_key' => 'fixture-only']);
        $run = MarketingAiRun::create(['project_id' => $p->id, 'request_id' => (string) Str::uuid(), 'action' => 'storyboard', 'status' => 'running']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{broken']]]]])]);
        try {
            (new MarketingWriter)->propose(['run_id' => $run->id, 'snapshot' => [], 'brief' => $p->brief, 'assets' => []], 'storyboard', '');
            $this->fail('Malformed output accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('unreadable draft', $e->errors()['generation'][0]);
        }
        Http::assertSentCount(1);
        $this->assertSame('0.0200', $run->fresh()->reserved_usd);
    }
}
