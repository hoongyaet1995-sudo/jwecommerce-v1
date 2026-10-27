<?php

namespace Tests\Feature;

use App\Models\MarketingAsset;
use App\Models\MarketingRevision;
use App\Models\MarketingStoryboardTask;
use App\Services\Marketing\MarketingCreativeProfiles;
use App\Services\Marketing\MarketingPreparationBudget;
use App\Services\Marketing\MarketingPresenterLibrary;
use App\Services\Marketing\MarketingStoryboardContract;
use App\Services\Marketing\MarketingStoryboardHandoff;
use App\Services\Marketing\MarketingStoryboardPreparation;
use App\Services\Marketing\MarketingStoryboardProduction;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardVoice;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardBoundaryTest extends TestCase
{
    use MarketingResearchFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->run();
        Queue::fake();
    }

    private function scene(array $extra = []): array
    {
        return $extra + ['seconds' => 5, 'message' => 'Service detail', 'visual' => 'Show approved detail', 'voiceover' => 'Approved text', 'subtitle' => 'Approved text'];
    }

    private function sample(): array
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['title' => 'Detail', 'duration' => 5, 'visual' => 'Show approved detail', 'voiceover' => 'Approved text', 'subtitle' => 'Approved text']]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $w = app(MarketingStoryboardWorkspace::class);
        $s = $w->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);

        return [$p, $w, $s];
    }

    public function test_step3_can_handoff_spoken_story_without_any_tts_receipt(): void
    {
        [$p,$w,$s] = $this->sample();
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('ready.png', 720, 1280), true);
        $s = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => $this->scene(), 'asset_id' => $ref['asset_id']]);
        $this->assertTrue($s['readiness']);
        $wout = app(MarketingStoryboardHandoff::class)->confirm($p->id, $p->owner_id, (string) Str::uuid(), 2);
        $h = app(MarketingStoryboardHandoff::class)->build($p->id, $p->owner_id, $s['story_id']);
        $this->assertSame('step3-prepared', $h['stage']);
        $this->assertSame('Approved text', $h['scene_map'][0]['voiceover']);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_director_and_frame_roles_survive_contract_normalization(): void
    {
        $director = ['subject' => 'Approved product', 'framing' => 'Medium', 'motion' => 'Slow push', 'lighting' => 'Soft', 'continuity' => 'Same identity', 'start_state' => 'Wide', 'end_state' => 'Detail', 'beats' => [['start' => 0, 'end' => 5, 'action' => 'Show detail']]];
        $c = app(MarketingStoryboardContract::class)->content($this->scene(['director' => $director, 'frames' => ['start_frame' => ['required' => true, 'instruction' => 'Opening'], 'end_frame' => ['required' => true, 'instruction' => 'Settled ending'], 'extra_image' => ['required' => true, 'instruction' => 'Product reference']]]));
        $this->assertSame($director, $c['director']);
        $this->assertTrue($c['frames']['end_frame']['required']);
        $this->assertSame('Product reference', $c['frames']['extra_image']['instruction']);
    }

    public function test_required_end_frame_blocks_handoff_even_when_start_ready(): void
    {
        [$p,$w,$s] = $this->sample();
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('ready.png', 720, 1280), true);
        $s = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => $this->scene(['voiceover' => '', 'frames' => ['start_frame' => ['required' => true, 'instruction' => 'Start'], 'end_frame' => ['required' => true, 'instruction' => 'End']]]), 'asset_id' => $ref['asset_id']]);
        $this->assertFalse($s['readiness']);
        $this->assertContains('frame_required', array_column($s['blockers'], 'code'));
        Http::assertNothingSent();
    }

    public function test_step3_prepares_all_required_roles_and_never_tts(): void
    {
        [$p,$w,$s] = $this->sample();
        $c = $this->scene(['frames' => ['start_frame' => ['required' => true, 'instruction' => 'Start'], 'end_frame' => ['required' => true, 'instruction' => 'Finish'], 'extra_image' => ['required' => true, 'instruction' => 'Reference']]]);
        $s = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => $c]);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('fixture.png', 720, 1280), true);
        $roles = [];
        app()->instance('marketing.storyboard.image_transport', function ($compiled) use (&$roles, $ref) {
            $roles[] = $compiled['role'];

            return ['asset' => $ref, 'actual_microusd' => 1];
        });
        app()->instance('marketing.storyboard.qa_transport', fn () => ['status' => 'pass', 'evidence' => ['fake' => true]]);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 2, [$s['scenes'][0]['revision_id']], 1000);
        $prep = app(MarketingStoryboardPreparation::class);
        $task = $prep->prepare($p->id, $a['id']);
        $prep->process($task['tasks'][0]);
        $this->assertSame('queued', MarketingStoryboardTask::findOrFail($task['tasks'][0])->status);
        $this->assertCount(1, $roles);
        for ($i = 0; $i < 3; $i++) {
            $prep->process($task['tasks'][0]);
        }
        $s = $w->snapshot($p->id, $p->owner_id);
        $this->assertSame(['start_frame', 'end_frame', 'extra_image'], $roles);
        $this->assertTrue($s['readiness']);
        $h = app(MarketingStoryboardHandoff::class)->build($p->id, $p->owner_id, $s['story_id']);
        $this->assertCount(3, $h['frame_inventory'][0]['roles']);
        $this->assertSame($s['scenes'][0]['director'], $h['director_plan']['shots'][0]['design']);
        $this->assertArrayNotHasKey('voice_asset', $s['scenes'][0]);
        Http::assertNothingSent();
        $asset = MarketingAsset::findOrFail($ref['asset_id']);
        Storage::disk('local')->put($asset->path, 'tampered');
        $this->assertFalse($w->snapshot($p->id, $p->owner_id)['readiness']);
    }

    public function test_formal_voice_service_rejects_step3_authorization(): void
    {
        [$p,$w,$s] = $this->sample();
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 1, [$s['scenes'][0]['revision_id']], 1000);
        try {
            app(MarketingStoryboardVoice::class)->obtain($p->id, $p->owner_id, $s['scenes'][0]['scene_id'], $a['id']);
            $this->fail('must require approved Step4 run');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }Http::assertNothingSent();
    }

    public function test_switching_presenter_invalidates_all_frame_roles(): void
    {
        [$p,$w,$s] = $this->sample();
        $u = app(MarketingStoryboardUpload::class);
        $ref = $u->store($p->id, $p->owner_id, UploadedFile::fake()->image('person.png', 720, 1280), true);
        $lib = app(MarketingPresenterLibrary::class);
        $a = $lib->register($p->id, $p->owner_id, (string) Str::uuid(), 'A', 'synthetic', $ref['asset_id'], true);
        $b = $lib->register($p->id, $p->owner_id, (string) Str::uuid(), 'B', 'synthetic', $ref['asset_id'], true);
        $profile = app(MarketingCreativeProfiles::class);
        $profile->save($p->id, $p->owner_id, 1, ['presenter' => ['mode' => 'preset', 'id' => $a['id'], 'version' => 1], 'voice' => ['mode' => 'none']]);
        $c = $this->scene(['presenter_required' => true, 'frames' => ['start_frame' => ['required' => true], 'end_frame' => ['required' => true], 'extra_image' => ['required' => true]]]);
        $s = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), 2, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => $c, 'frame_assets' => array_fill_keys(['start_frame', 'end_frame', 'extra_image'], $ref['asset_id'])]);
        $this->assertTrue($s['readiness']);
        $s = $profile->save($p->id, $p->owner_id, 3, ['presenter' => ['mode' => 'preset', 'id' => $b['id'], 'version' => 1], 'voice' => ['mode' => 'none']]);
        $this->assertFalse($s['readiness']);
        foreach ($s['scenes'][0]['frames'] as $f) {
            $this->assertNull($f['asset']);
        }Http::assertNothingSent();
    }

    public function test_replacing_start_requires_continuity_recheck_of_existing_end(): void
    {
        [$p,$w,$s] = $this->sample();
        $u = app(MarketingStoryboardUpload::class);
        $one = $u->store($p->id, $p->owner_id, UploadedFile::fake()->image('one.png', 720, 1280), true);
        $two = $u->store($p->id, $p->owner_id, UploadedFile::fake()->image('two.png', 864, 1536), true);
        $c = $this->scene(['frames' => ['start_frame' => ['required' => true], 'end_frame' => ['required' => true]]]);
        $s = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => $c, 'frame_assets' => ['start_frame' => $one['asset_id'], 'end_frame' => $one['asset_id']]]);
        $this->assertTrue($s['readiness']);
        $s = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), 2, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => $this->scene(), 'asset_id' => $two['asset_id']]);
        $this->assertFalse($s['readiness']);
        $this->assertSame($one['asset_id'], $s['scenes'][0]['frames']['end_frame']['asset']['asset_id']);
        $this->assertSame('pending_qa', $s['scenes'][0]['frames']['end_frame']['readiness']);
        Http::assertNothingSent();
        app()->instance('marketing.storyboard.image_transport', fn () => throw new \LogicException('existing end frame must be checked, not regenerated'));
        $qaCalls = 0;
        app()->instance('marketing.storyboard.qa_transport', function () use (&$qaCalls) {
            $qaCalls++;

            return ['status' => 'pass', 'evidence' => ['fixture' => true]];
        });
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 3, [$s['scenes'][0]['revision_id']], 1000);
        $prep = app(MarketingStoryboardPreparation::class);
        $batch = $prep->prepare($p->id, $a['id']);
        $prep->process($batch['tasks'][0]);
        $this->assertTrue($w->snapshot($p->id, $p->owner_id)['readiness']);
        $this->assertSame(1, $qaCalls);
        Http::assertNothingSent();
    }

    public function test_end_frame_hash_changes_video_visual_cache_key(): void
    {
        $service = app(MarketingStoryboardProduction::class);
        $method = new \ReflectionMethod($service, 'input');
        $clip = $this->scene(['scene_id'=>'fixture-scene','opening_asset' => ['sha256' => 'start'], 'director' => ['motion' => 'Finish'], 'frames' => ['start_frame'=>['required'=>true,'asset'=>['sha256'=>'start'],'readiness'=>'qa_passed'],'end_frame' => ['required' => true, 'asset' => ['sha256' => 'end-a'],'readiness'=>'qa_passed']]]);
        $cfg = ['video'=>['model'=>'seedance-2.0-image-to-video'],'connection'=>'fixture','clip_routes'=>['fixture-scene'=>['video'=>['model'=>'seedance-2.0-image-to-video'],'capability'=>app(\App\Services\Marketing\MarketingVideoRoutes::class)->catalog()['seedance-2.0-image-to-video'],'image_roles'=>['start_frame','end_frame'],'mode'=>'first_last_frames','reason'=>'Control ending','tier'=>'ordinary']]];
        $one = $method->invoke($service, $clip, $cfg);
        $clip['frames']['end_frame']['asset']['sha256'] = 'end-b';
        $two = $method->invoke($service, $clip, $cfg);
        $this->assertNotSame($one['visual_fingerprint'], $two['visual_fingerprint']);
    }
}
