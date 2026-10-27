<?php

namespace Tests\Feature;

use App\Jobs\ProcessMarketingStoryboardPreparation;
use App\Jobs\ProcessMarketingStoryboardProduction;
use App\Models\MarketingPreparationAuthorization;
use App\Models\MarketingRevision;
use App\Models\MarketingStoryboardProviderCall;
use App\Models\MarketingStoryboardTask;
use App\Models\MarketingVideoClip;
use App\Services\Marketing\MarketingClipSource;
use App\Services\Marketing\MarketingPreparationBudget;
use App\Services\Marketing\MarketingProductionPreparation;
use App\Services\Marketing\MarketingStoryboardDirector;
use App\Services\Marketing\MarketingStoryboardHandoff;
use App\Services\Marketing\MarketingStoryboardProduction;
use App\Services\Marketing\MarketingStoryboardProvider;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardAutomationTest extends TestCase
{
    use MarketingResearchFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->run();
        Queue::fake();
    }

    private function sample(array $people = [false]): array
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => array_map(fn ($person) => ['duration' => 5, 'title' => 'Detail', 'visual' => 'Product detail', 'subtitle' => '', 'voiceover' => '', 'presenter_required' => $person], $people)], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        config(['marketing.storyboard.live_enabled' => true, 'marketing.storyboard.live_grant' => ['reference' => 'OFFLINE AUTOMATION APPROVAL', 'project_id' => $p->id, 'owner_id' => $p->owner_id, 'roles' => ['image', 'qa'], 'max_total_microusd' => 1000, 'max_calls' => 10, 'expires_at' => now()->addHour()->toIso8601String()]]);

        return [$p, app(MarketingStoryboardDirector::class)];
    }

    public function test_initialization_queues_missing_frames_once_without_requiring_people(): void
    {
        [$p,$director] = $this->sample();
        $id = (string) Str::uuid();
        $one = $director->initialize($p->id, $p->owner_id, $id, $p->version);
        $two = $director->initialize($p->id, $p->owner_id, $id, $p->version);
        $this->assertCount(1, $one['preparation']['tasks']);
        $this->assertSame($one['preparation']['tasks'], $two['preparation']['tasks']);
        Queue::assertPushed(ProcessMarketingStoryboardPreparation::class, 1);
        Http::assertNothingSent();
    }

    public function test_manual_insert_automatically_queues_its_new_frame_and_preserves_old_scene(): void
    {
        [$p,$director] = $this->sample();
        $before = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $id = (string) Str::uuid();
        $cmd = ['type' => 'insert', 'mode' => 'manual', 'after_id' => $before['scenes'][0]['scene_id'], 'content' => ['seconds' => 8, 'message' => 'New detail', 'visual' => 'Service process', 'voiceover' => '', 'subtitle' => '']];
        $one = $director->change($p->id, $p->owner_id, $id, 1, $cmd);
        $director->change($p->id, $p->owner_id, $id, 1, $cmd);
        $this->assertCount(1, $one['preparation']['tasks']);
        $this->assertSame(13, $one['snapshot']['timing']['content_seconds']);
        $this->assertSame($before['scenes'][0]['scene_id'], $one['snapshot']['scenes'][0]['scene_id']);
        Queue::assertPushed(ProcessMarketingStoryboardPreparation::class, 1);
        Http::assertNothingSent();
    }

    public function test_missing_presenter_blocks_only_scenes_that_require_people(): void
    {
        [$p,$director] = $this->sample([true, false]);
        $out = $director->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $this->assertCount(1, $out['preparation']['tasks']);
        $this->assertSame($out['snapshot']['scenes'][1]['revision_id'], MarketingStoryboardTask::firstOrFail()->revision_id);
        $this->assertContains('presenter_required', array_column($out['preparation']['blockers'], 'code'));
        Http::assertNothingSent();
    }

    public function test_unapproved_preparation_does_not_prevent_saving_or_enqueue_generation(): void
    {
        [$p,$director] = $this->sample();
        config(['marketing.storyboard.live_enabled' => false]);
        $out = $director->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $this->assertNotNull($out['snapshot']['story_id']);
        $this->assertContains('budget_approval_required', array_column($out['preparation']['blockers'], 'code'));
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_valid_uploaded_image_is_reused_without_generation_job(): void
    {
        [$p,$director] = $this->sample();
        $before = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('existing.png', 90, 160), true);
        $out = $director->change($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'insert', 'mode' => 'manual', 'after_id' => $before['scenes'][0]['scene_id'], 'content' => ['seconds' => 5, 'message' => 'Uploaded detail', 'visual' => 'Existing photo', 'voiceover' => '', 'subtitle' => ''], 'asset_id' => $ref['asset_id']]);
        $this->assertEmpty($out['preparation']['tasks']);
        $this->assertSame($ref['asset_id'], $out['snapshot']['scenes'][1]['asset']['asset_id']);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_async_video_reserves_until_known_task_finishes_and_never_resubmits(): void
    {
        [$p,$director] = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        config(['marketing.storyboard.live_grant.roles' => ['image', 'qa', 'video'], 'marketing.storyboard.roles.video' => ['model' => 'fixture-video', 'version' => '1', 'cap_microusd' => 100, 'pricing_verified_at' => now()->toIso8601String()]]);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 1, [$s['scenes'][0]['revision_id']], 1000);
        $calls = 0;
        $provider = app(MarketingStoryboardProvider::class);
        $payload = ['revision_id' => $s['scenes'][0]['revision_id']];
        $one = $provider->beginVideo($p->id, $a['id'], $payload, function () use (&$calls) {
            $calls++;

            return ['id' => 'fake-video-task'];
        });
        $two = $provider->beginVideo($p->id, $a['id'], $payload, function () use (&$calls) {
            $calls++;

            return ['id' => 'should-not-send'];
        });
        $this->assertSame($one, $two);
        $this->assertSame(1, $calls);
        $this->assertSame(100, MarketingPreparationAuthorization::findOrFail($a['id'])->reserved_microusd);
        $provider->completeVideo($one['provider_call_id'], 'fake-video-task', ['actual_microusd' => 7]);
        $this->assertSame(7, MarketingPreparationAuthorization::findOrFail($a['id'])->spent_microusd);
        Http::assertNothingSent();
    }

    public function test_new_storyboard_runtime_starts_once_only_after_scope_and_budget_confirmation(): void
    {
        [$p,$director] = $this->sample();
        $w = app(MarketingStoryboardWorkspace::class);
        $s = $w->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('ready.png', 720, 1280), true);
        $s = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => ['seconds' => 5, 'message' => 'Detail', 'visual' => 'Service process', 'voiceover' => '', 'subtitle' => ''], 'asset_id' => $ref['asset_id']]);
        config(['marketing.native.api_key' => 'fake-only', 'marketing.storyboard.live_grant.roles' => ['image', 'qa', 'upload', 'video'], 'marketing.storyboard.roles.upload' => ['model' => 'evolink-files', 'version' => '1', 'cap_microusd' => 10, 'fee_microusd' => 0, 'pricing_verified_at' => now()->toIso8601String()], 'marketing.storyboard.roles.video' => ['model' => MarketingClipSource::MODEL, 'version' => '1', 'cap_microusd' => 100, 'credit_usd' => 0.000001, 'pricing_verified_at' => now()->toIso8601String(), 'access_verified_at' => now()->toIso8601String()]]);
        $service = app(MarketingStoryboardProduction::class);
        app(MarketingStoryboardHandoff::class)->confirm($p->id, $p->owner_id, (string) Str::uuid(), 2);
        $plan = app(MarketingProductionPreparation::class)->prepareStoryboard($p->id, $p->owner_id, (string) Str::uuid(), 2);
        $this->assertSame('awaiting_budget', $plan->status);
        $one = $service->start($p->id, $p->owner_id, 2, $plan->id, true);
        $two = $service->start($p->id, $p->owner_id, 2, $plan->id, true);
        $this->assertSame($one->id, $two->id);
        Queue::assertPushed(ProcessMarketingStoryboardProduction::class, 1);
        Http::assertNothingSent();
    }

    public function test_video_opening_is_blocked_before_any_paid_request(): void
    {
        [$p] = $this->sample();
        $handoff = ['story_version' => 1, 'timing' => ['output_seconds' => 5, 'content_seconds' => 5]];
        $manifest = app(MarketingStoryboardProduction::class)->manifest($handoff, [['opening_asset' => ['kind' => 'video']]], $p->id);
        $this->assertStringContainsString('视频素材', $manifest['blockers'][0]);
        Http::assertNothingSent();
    }

    public function test_selected_voice_counts_as_audio_even_when_native_generation_is_silent(): void
    {
        $clip = new MarketingVideoClip(['input' => ['contract' => 'storyboard-clip-v1', 'voice_asset' => ['voice_id' => 'selected'], 'payload' => ['generate_audio' => false]]]);
        $this->assertTrue(MarketingClipSource::hasAudio($clip));
    }

    public function test_worker_interruption_freezes_only_requesting_claims(): void
    {
        [$p] = $this->sample();
        $s = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 1, [$s['scenes'][0]['revision_id']], 1000);
        $budget = app(MarketingPreparationBudget::class);
        foreach (['interrupted', 'known'] as $key) {
            $budget->reserve($a['id'], 'provider:'.$key, 100);
        }
        foreach (['interrupted' => 'requesting', 'known' => 'external_pending'] as $key => $state) {
            MarketingStoryboardProviderCall::create(['id' => $key, 'project_id' => $p->id, 'authorization_id' => $a['id'], 'role' => 'video', 'grant_reference' => 'fixture', 'cap_microusd' => 100, 'status' => $state, 'input' => ['revision_id' => $s['scenes'][0]['revision_id']]]);
        }
        app(MarketingStoryboardProvider::class)->interrupted($a['id']);
        $this->assertSame('unknown',MarketingStoryboardProviderCall::findOrFail('interrupted')->status);
        $this->assertSame('external_pending',MarketingStoryboardProviderCall::findOrFail('known')->status);
        $auth = MarketingPreparationAuthorization::findOrFail($a['id']);
        $this->assertSame(100,$auth->unknown_microusd);
        $this->assertSame(100,$auth->reserved_microusd);
        Http::assertNothingSent();
    }
}
