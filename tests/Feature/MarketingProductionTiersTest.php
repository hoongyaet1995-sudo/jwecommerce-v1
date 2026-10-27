<?php

namespace Tests\Feature;

use App\Models\MarketingRevision;
use App\Models\MarketingStoryboardWorkspace as Workspace;
use App\Models\User;
use App\Services\Marketing\MarketingProductionPreferences;
use App\Services\Marketing\MarketingStoryboardContract;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use App\Services\Marketing\MarketingWorkspacePreparation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingProductionTiersTest extends TestCase
{
    use MarketingResearchFixtures,RefreshDatabase;

    private function sample(): array
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['visual' => 'KOL in warehouse', 'voiceover' => '', 'subtitle' => '', 'duration' => 5]]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $s = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);

        return [$p, $s];
    }

    public function test_tier_change_preserves_content_assets_and_invalidates_confirmation_without_generation(): void
    {
        [$p,$s] = $this->sample();
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('scene.png'), true);
        $s = app(MarketingStoryboardWorkspace::class)->apply($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => $s['scenes'][0], 'asset_id' => $ref['asset_id']]);
        Workspace::where('project_id', $p->id)->update(['last_confirmation' => json_encode(['snapshot' => ['story_version' => $s['story_version']]])]);
        $id = (string) Str::uuid();
        $service = app(MarketingProductionPreferences::class);
        $out = $service->save($p->id, $p->owner_id, $id, $s['story_version'], 'advanced');
        $this->assertSame('advanced', $out['profile']['production_tier']);
        $this->assertSame($s['scenes'], $out['scenes']);
        $this->assertNull($out['last_confirmation']);
        $this->assertTrue($out['source_fresh']);
        $this->assertSame($p->current_revision_id, $p->fresh()->current_revision_id);
        $this->assertSame('advanced', $p->fresh()->preferences['production_tier']);
        $this->assertSame($out, $service->save($p->id, $p->owner_id, $id, $s['story_version'], 'advanced'));
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_single_image_ai_plan_does_not_require_unused_tail_or_extra(): void
    {
        [$p,$s] = $this->sample();
        $c = $s['scenes'][0];
        $c['material_plan'] = ['mode' => 'single_image', 'reason' => '本段只需明确场景和人物，动作由导演稿执行。', 'roles' => ['start_frame']];
        foreach ($c['frames'] as &$f) {
            $f['required'] = true;
        }unset($f);
        $out = app(MarketingStoryboardContract::class)->content($c);
        $this->assertSame('single_image', $out['material_plan']['mode']);
        $this->assertTrue($out['frames']['start_frame']['required']);
        $this->assertFalse($out['frames']['end_frame']['required']);
        $this->assertFalse($out['frames']['extra_image']['required']);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('ready.png'), true);
        $next = app(MarketingStoryboardWorkspace::class)->apply($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], ['type' => 'edit', 'scene_id' => $c['scene_id'], 'content' => $c, 'asset_id' => $ref['asset_id']]);
        $this->assertTrue($next['readiness']);
        Http::assertNothingSent();
    }

    public function test_tier_is_validated_and_model_cannot_set_prices_or_execute_permissions(): void
    {
        [$p,$s] = $this->sample();
        $this->expectException(ValidationException::class);
        app(MarketingProductionPreferences::class)->save($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], 'unlimited');
    }

    public function test_step_one_tier_only_preserves_existing_preferences_and_story(): void
    {
        [$p,$s] = $this->sample();
        $prefs = ['complete' => true, 'language' => 'English', 'platforms' => ['Facebook'], 'narration' => true, 'subtitles' => true, 'voice' => 'ai', 'music' => 'none', 'duration' => 'auto', 'production_tier' => 'ordinary'];
        $p->update(['preferences' => $prefs, 'planning_state' => ['existing' => 'keep']]);
        // Align source fingerprint with the fixture's initial preferences.
        $w = Workspace::where('project_id', $p->id)->first();
        $profile = $w->profile;
        $profile['source_fingerprint'] = app(MarketingStoryboardWorkspace::class)->sourceFingerprint($p->fresh());
        $w->update(['profile' => $profile]);
        $before = app(MarketingStoryboardWorkspace::class)->snapshot($p->id, $p->owner_id);
        $saved = app(MarketingWorkspacePreparation::class)->preferences($p->id, $p->owner_id, $p->version, (string) Str::uuid(), array_replace($prefs, ['production_tier' => 'professional']));
        $after = app(MarketingStoryboardWorkspace::class)->snapshot($p->id, $p->owner_id);
        $this->assertSame($before['scenes'], $after['scenes']);
        $this->assertSame('professional', $after['profile']['production_tier']);
        $this->assertSame(['existing' => 'keep'], $saved->planning_state);
        $this->assertSame($p->current_revision_id, $saved->current_revision_id);
        $this->assertSame(array_diff_key($prefs, ['production_tier' => 1]), array_diff_key($saved->preferences, ['production_tier' => 1]));
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_tier_endpoint_is_owned_and_does_not_prepare_images(): void
    {
        [$p,$s] = $this->sample();
        $this->postJson(route('marketing.workspace.storyboard', $p->id).'/tier', ['version' => $s['story_version'], 'request_id' => (string) Str::uuid(), 'profile' => ['production_tier' => 'advanced']])->assertOk()->assertJsonPath('snapshot.profile.production_tier', 'advanced');
        Http::assertNothingSent();
        Queue::assertNothingPushed();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $this->postJson(route('marketing.workspace.storyboard',$p->id).'/tier',['version' => $s['story_version'] + 1, 'request_id' => (string) Str::uuid(), 'profile' => ['production_tier' => 'ordinary']])->assertNotFound();
    }
    public function test_production_without_explicit_consent_stops_before_database_or_provider_work():void
    {
        Http::preventStrayRequests();
        try {
            app(\App\Services\Marketing\MarketingStoryboardProduction::class)->start('unused-project',1,1,'unused-plan',false);
            $this->fail('Production needs explicit consent');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            $this->assertSame(422,$e->getStatusCode());
        }
        Http::assertNothingSent();
    }
    public function test_tier_save_does_not_hide_an_unrelated_source_change():void
    {
        [$p,$s]=$this->sample();$p->update(['brief'=>array_replace($p->brief,['theme'=>'Changed independently'])]);
        $after=app(MarketingProductionPreferences::class)->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],'advanced');
        $this->assertFalse($after['source_fresh']);Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_legacy_null_preferences_fingerprint_remains_compatible():void
    {
        [$p,$s]=$this->sample();$p->update(['preferences'=>null]);$p=$p->fresh();
        $legacy=hash('sha256',json_encode([$p->current_revision_id,$p->currentRevision->document,$p->snapshot,$p->brief,null,$p->selected_concept],JSON_THROW_ON_ERROR));
        $this->assertSame($legacy,app(MarketingStoryboardWorkspace::class)->sourceFingerprint($p));
    }
}
