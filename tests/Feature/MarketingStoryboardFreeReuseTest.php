<?php

namespace Tests\Feature;

use App\Models\MarketingLibraryAsset;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\User;
use App\Services\Marketing\MarketingGenerationQuotes;
use App\Services\Marketing\MarketingMediaReuse;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardFreeReuseTest extends TestCase
{
    use MarketingResearchFixtures,RefreshDatabase;

    private function story(): array
    {
        Http::preventStrayRequests();
        Queue::fake();
        Storage::fake('local');
        config(['marketing.execution_enabled' => false, 'marketing.billing.enabled' => true, 'marketing.billing.pricing_valid_until' => '2026-10-30']);
        $this->travelTo(now()->setDate(2026, 11, 9));
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['visual' => 'Saved image', 'duration' => 5, 'voiceover' => '', 'subtitle' => '']]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $snapshot = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $this->actingAs(User::findOrFail($p->owner_id));

        return [$p, $snapshot, route('marketing.workspace.storyboard', $p->id)];
    }

    private function noExecution(): void
    {
        Http::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('marketing_generation_quotes', 0);
        $this->assertDatabaseCount('marketing_preparation_authorizations', 0);
    }

    public function test_expired_rates_do_not_block_reading_saved_storyboard(): void
    {
        [$p,$s,$api] = $this->story();
        $this->getJson($api)->assertOk()->assertJsonPath('snapshot.story_version', $s['story_version'])->assertJsonPath('billing_enabled', true);
        $this->noExecution();
    }

    public function test_expired_rates_allow_manual_library_insert_and_replacement_without_generation(): void
    {
        [$p,$s,$api] = $this->story();
        $source = MarketingProject::create(['owner_id' => $p->owner_id, 'source_type' => 'manual', 'name' => 'Owned source', 'snapshot' => [], 'brief' => []]);
        $ref = app(MarketingStoryboardUpload::class)->store($source->id, $source->owner_id, UploadedFile::fake()->image('saved.png', 320, 400), true);
        $library = MarketingLibraryAsset::where('source_id', $ref['asset_id'])->firstOrFail();
        $asset = app(MarketingMediaReuse::class)->import(User::findOrFail($p->owner_id), $p->id, $library->id, true);
        $command = ['type' => 'insert', 'mode' => 'manual', 'asset_id' => $asset['asset_id'], 'content' => ['message' => 'Saved material', 'visual' => 'Reuse original', 'voiceover' => '', 'subtitle' => '', 'seconds' => 5]];
        $r = $this->postJson($api.'/changes', ['request_id' => (string) Str::uuid(), 'version' => $s['story_version'], 'command' => $command])->assertOk()->assertJsonCount(2, 'snapshot.scenes')->assertJsonPath('snapshot.scenes.1.asset.asset_id', $asset['asset_id'])->assertJsonPath('preparation.tasks', []);
        $command['type'] = 'edit';
        $command['scene_id'] = $s['scenes'][0]['scene_id'];
        $command['update_scope'] = ['images' => false, 'director' => true];
        $this->postJson($api.'/changes', ['request_id' => (string) Str::uuid(), 'version' => $r->json('snapshot.story_version'), 'command' => $command])->assertOk()->assertJsonPath('snapshot.scenes.0.asset.asset_id', $asset['asset_id']);
        $this->noExecution();
    }

    public function test_shared_host_free_save_cannot_enter_preparation_with_billing_disabled():void
    {
        [$p,$s,$api]=$this->story();config(['marketing.billing.enabled'=>false,'marketing.execution_enabled'=>false,'marketing.storyboard.live_enabled'=>true]);
        $this->partialMock(\App\Services\Marketing\MarketingStoryboardDirector::class,fn($mock)=>$mock->shouldNotReceive('prepare'));
        $asset=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('existing.png',320,400),true);
        $command=['type'=>'insert','mode'=>'manual','asset_id'=>$asset['asset_id'],'content'=>['message'=>'Saved material','visual'=>'Use existing image','voiceover'=>'','subtitle'=>'','seconds'=>5]];
        $this->postJson($api.'/changes',['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'command'=>$command])->assertOk()->assertJsonPath('preparation.tasks',[]);$this->noExecution();
    }

    public function test_expired_rates_still_block_paid_quote_and_direct_ai_commands(): void
    {
        [$p,$s,$api] = $this->story();config(['marketing.execution_enabled'=>true]);
        $commands = [['type' => 'edit', 'mode' => 'ai', 'intent' => 'New image'], ['type' => 'edit', 'mode' => 'manual', 'refine_manual' => true], ['type' => 'edit', 'mode' => 'manual', 'update_scope' => ['images' => true, 'director' => true]], ['type' => 'insert', 'mode' => 'ai', 'asset_id' => (string) Str::uuid()]];
        foreach ($commands as $command) {
            $command['scene_id'] = $s['scenes'][0]['scene_id'];
            $this->postJson($api.'/changes', ['request_id' => (string) Str::uuid(), 'version' => $s['story_version'], 'command' => $command])->assertStatus(423);
        }
        $this->postJson($api.'/billing-quote', ['request_id' => (string) Str::uuid(), 'version' => $s['story_version'], 'operation' => 'changes', 'payload' => ['command' => $commands[0]]])->assertStatus(423);
        $this->noExecution();
    }

    public function test_valid_paid_quote_still_requires_consent_and_retains_expiry_checks(): void
    {
        [$p,$s,$api] = $this->story();
        config(['marketing.billing.pricing_valid_until' => '2026-12-30']);
        $svc = app(MarketingGenerationQuotes::class);
        $q = $svc->quote($p->id, $p->owner_id, $s['story_version'], 'changes', ['command' => ['type' => 'edit', 'mode' => 'ai', 'scene_id' => $s['scenes'][0]['scene_id'], 'intent' => 'A warmer image', 'update_scope' => ['images' => true, 'director' => true]]]);
        $this->assertSame('quoted', $q->status);
        $this->assertFalse($svc->activateProject($p->id));
        try {
            $svc->claim($p->id, $p->owner_id, $q->id, false);
            $this->fail('Consent was bypassed');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame('executing', $svc->claim($p->id, $p->owner_id, $q->id, true)['quote']->status);
        $q->update(['expires_at' => now()->subSecond()]);
        $this->assertFalse($svc->activate($q));
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_current_rates_still_require_quote_and_explicit_approval_for_ai(): void
    {
        [$p,$s,$api] = $this->story();config(['marketing.execution_enabled'=>true]);
        config(['marketing.billing.pricing_valid_until' => '2026-12-30']);
        $command = ['type' => 'edit', 'mode' => 'ai', 'scene_id' => $s['scenes'][0]['scene_id'], 'intent' => 'New image'];
        $this->postJson($api.'/changes', ['request_id' => (string) Str::uuid(), 'version' => $s['story_version'], 'command' => $command])->assertStatus(409);
        $this->noExecution();
    }
}
