<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\MarketingAsset;
use App\Models\MarketingRevision;
use App\Models\MarketingScene;
use App\Models\MarketingSceneRevision;
use App\Models\MarketingStoryboardProviderCall;
use App\Models\MarketingStoryboardWorkspace as Workspace;
use App\Services\Marketing\MarketingMaterialVisionClient;
use App\Services\Marketing\MarketingStoryboardDecisions;
use App\Services\Marketing\MarketingStoryboardOpenAi;
use App\Services\Marketing\MarketingStoryboardProvider;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardDecisionsTest extends TestCase
{
    use MarketingResearchFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->run();
    }

    private function sample(): array
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['visual' => 'KOL in warehouse', 'duration' => 5, 'voiceover' => '', 'subtitle' => '']]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $s = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('candidate.png'), true);
        $r = MarketingSceneRevision::findOrFail($s['scenes'][0]['revision_id']);
        $c = $r->content;
        $checks = array_fill_keys(MarketingMaterialVisionClient::CHECKS, 'pass');
        $checks['composition'] = 'fail';
        foreach ($c['frames'] as &$f) {
            $f['required'] = true;
        }unset($f);
        $c['frames']['start_frame']['candidate_asset'] = $ref;
        $c['frames']['start_frame']['qa'] = ['status' => 'revise', 'evidence' => ['checks' => $checks, 'summary' => '取景比导演稿更宽']];
        // Immutable fixture revision, never update historical content.
        $next = MarketingSceneRevision::create(['scene_id' => $r->scene_id, 'parent_id' => $r->id, 'content' => $c, 'signature' => $r->signature]);
        MarketingScene::find($r->scene_id)->update(['current_revision_id' => $next->id]);

        return [$p, app(MarketingStoryboardWorkspace::class)->snapshot($p->id, $p->owner_id), $ref];
    }

    public function test_candidate_acceptance_preserves_qa_history_and_only_syncs_framing(): void
    {
        [$p,$s,$ref] = $this->sample();
        $scene = $s['scenes'][0];
        $id = (string) Str::uuid();
        $command = ['scene_id' => $scene['scene_id'], 'revision_id' => $scene['revision_id'], 'role' => 'start_frame', 'sha256' => $ref['sha256'], 'sync_framing' => true];
        $out = app(MarketingStoryboardDecisions::class)->adopt($p->id, $p->owner_id, $id, $s['story_version'], $command);
        $next = $out['scenes'][0];
        $this->assertSame($ref['sha256'], $next['frames']['start_frame']['asset']['sha256']);
        $this->assertSame('revise', $next['frames']['start_frame']['qa']['status']);
        $this->assertSame($p->owner_id, $next['frames']['start_frame']['employee_acceptance']['actor_id']);
        $this->assertSame($scene['voiceover'], $next['voiceover']);
        $this->assertSame($scene['seconds'], $next['seconds']);
        $this->assertNotSame($scene['director']['framing'], $next['director']['framing']);
        $this->assertSame($out, app(MarketingStoryboardDecisions::class)->adopt($p->id, $p->owner_id, $id, $s['story_version'], $command));
        $this->assertNotNull(MarketingSceneRevision::find($scene['revision_id']));
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_image_only_acceptance_does_not_hide_director_difference(): void
    {
        [$p,$s,$ref] = $this->sample();
        $scene = $s['scenes'][0];
        $out = app(MarketingStoryboardDecisions::class)->adopt($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], ['scene_id' => $scene['scene_id'], 'revision_id' => $scene['revision_id'], 'role' => 'start_frame', 'sha256' => $ref['sha256'], 'sync_framing' => false]);
        $this->assertSame($scene['director'], $out['scenes'][0]['director']);
        $this->assertSame('pending_sync', $out['scenes'][0]['frames']['start_frame']['readiness']);
        $this->assertFalse($out['readiness']);
    }

    public function test_corrupt_candidate_cannot_be_adopted(): void
    {
        [$p,$s,$ref] = $this->sample();
        $scene = $s['scenes'][0];
        $asset = MarketingAsset::find($ref['asset_id']);
        Storage::disk('local')->put($asset->path, 'broken');
        $this->expectException(HttpException::class);
        app(MarketingStoryboardDecisions::class)->adopt($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], ['scene_id' => $scene['scene_id'], 'revision_id' => $scene['revision_id'], 'role' => 'start_frame', 'sha256' => $ref['sha256'], 'sync_framing' => true]);
    }

    public function test_apply_preview_preserves_all_assets_and_does_not_generate(): void
    {
        [$p,$s,$ref] = $this->sample();
        $svc = app(MarketingStoryboardDecisions::class);
        $this->mock(MarketingStoryboardOpenAi::class, function ($m) use ($s) {
            $m->shouldReceive('connection')->andReturn([null, []]);
            $c = $s['scenes'][0];
            $c['material_plan'] = ['mode' => 'single_image', 'roles' => ['start_frame'], 'reason' => '动作由导演稿执行'];
            $m->shouldReceive('materialPlan')->once()->andReturn(['answer' => ['plans' => [['scene_id' => $c['scene_id'], 'material_plan' => $c['material_plan']]]]]);
        });
        $this->mock(MarketingStoryboardProvider::class, function ($m) {
            $m->shouldReceive('allowed')->andReturn(true);
            $m->shouldReceive('role')->andReturn(['model' => 'fake', 'version' => '1', 'cap_microusd' => 100]);
        });
        config(['marketing.storyboard.live_grant.max_total_microusd' => 1000]);
        $preview = $svc->preview($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version']);
        $this->assertSame($preview, $svc->preview($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version']));
        $this->assertSame($s['story_version'], Workspace::where('project_id', $p->id)->first()->version);
        $out = $svc->applyPlan($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], $preview['key']);
        $this->assertFalse($out['scenes'][0]['frames']['end_frame']['required']);
        $this->assertSame($ref['sha256'], $out['scenes'][0]['frames']['start_frame']['candidate_asset']['sha256']);
        $this->assertSame($s['scenes'][0]['director'], $out['scenes'][0]['director']);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_controller_rejects_a_stale_candidate_and_unapproved_analysis(): void
    {
        [$p,$s,$ref] = $this->sample();
        $scene = $s['scenes'][0];
        $url = route('marketing.workspace.storyboard.write', [$p->id, 'adopt-candidate']);
        $this->postJson($url, ['request_id' => (string) Str::uuid(), 'version' => $s['story_version'] + 1, 'command' => ['scene_id' => $scene['scene_id'], 'revision_id' => $scene['revision_id'], 'role' => 'start_frame', 'sha256' => $ref['sha256'], 'sync_framing' => true]])->assertStatus(409);
        $this->postJson(route('marketing.workspace.storyboard.write', [$p->id, 'material-plan-preview']), ['request_id' => (string) Str::uuid(), 'version' => $s['story_version']])->assertStatus(423);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_non_composition_issues_cannot_be_silently_accepted(): void
    {
        [$p,$s,$ref] = $this->sample();
        $scene = $s['scenes'][0];
        $r = MarketingSceneRevision::find($scene['revision_id']);
        $c = $r->content;
        $c['frames']['start_frame']['qa']['evidence']['checks']['subject_identity'] = 'fail';
        $r = MarketingSceneRevision::create(['scene_id' => $r->scene_id, 'parent_id' => $r->id, 'content' => $c, 'signature' => $r->signature]);
        MarketingScene::find($r->scene_id)->update(['current_revision_id' => $r->id]);
        $this->expectException(HttpException::class);
        app(MarketingStoryboardDecisions::class)->adopt($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], ['scene_id' => $scene['scene_id'], 'revision_id' => $r->id, 'role' => 'start_frame', 'sha256' => $ref['sha256'], 'sync_framing' => true]);
    }

    public function test_explicit_user_review_can_adopt_failed_ai_checks_without_calls_and_preserves_evidence(): void
    {
        [$p,$s,$ref] = $this->sample();
        $scene=$s['scenes'][0];$old=MarketingSceneRevision::findOrFail($scene['revision_id']);$c=$old->content;
        $c['frames']['start_frame']['qa']['evidence']['checks']=array_fill_keys(MarketingMaterialVisionClient::CHECKS,'fail');
        $r=MarketingSceneRevision::create(['scene_id'=>$old->scene_id,'parent_id'=>$old->id,'content'=>$c,'signature'=>$old->signature]);
        MarketingScene::findOrFail($old->scene_id)->update(['current_revision_id'=>$r->id]);
        $view=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);
        $this->assertTrue($view['scenes'][0]['frames']['start_frame']['qa_override_required']);
        $this->assertNull($view['scenes'][0]['frames']['start_frame']['adoption_technical_issue']);
        $command=['scene_id'=>$scene['scene_id'],'revision_id'=>$r->id,'role'=>'start_frame','sha256'=>$ref['sha256'],'sync_framing'=>true,'accept_ai_findings'=>true];
        $id=(string)Str::uuid();$out=app(MarketingStoryboardDecisions::class)->adopt($p->id,$p->owner_id,$id,$s['story_version'],$command);
        $f=$out['scenes'][0]['frames']['start_frame'];
        $this->assertSame('employee_declared_local_validated',$f['readiness']);
        $this->assertSame($c['frames']['start_frame']['qa'],$f['qa']);
        $this->assertSame($f['qa'],$f['employee_acceptance']['qa']);
        $this->assertTrue($f['employee_acceptance']['ai_findings_accepted']);
        $this->assertSame($ref['sha256'],$f['employee_acceptance']['sha256']);
        $this->assertSame($r->id,$f['employee_acceptance']['revision_id']);
        $this->assertSame($out,app(MarketingStoryboardDecisions::class)->adopt($p->id,$p->owner_id,$id,$s['story_version'],$command));
        Http::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_user_review_never_overrides_corrupt_image(): void
    {
        [$p,$s,$ref]=$this->sample();$scene=$s['scenes'][0];
        $asset=MarketingAsset::findOrFail($ref['asset_id']);Storage::disk('local')->put($asset->path,'corrupt');
        $this->expectException(HttpException::class);
        app(MarketingStoryboardDecisions::class)->adopt($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['scene_id'=>$scene['scene_id'],'revision_id'=>$scene['revision_id'],'role'=>'start_frame','sha256'=>$ref['sha256'],'sync_framing'=>true,'accept_ai_findings'=>true]);
    }
    public function test_pending_sync_original_can_be_reviewed_and_reused_once_without_generation():void
    {
        [$p,$s,$ref]=$this->sample();$scene=$s['scenes'][0];$old=MarketingSceneRevision::findOrFail($scene['revision_id']);$c=$old->content;
        unset($c['frames']['start_frame']['candidate_asset']);$c['visual']='中景 Warehouse product';$c['frames']['start_frame']['asset']=$ref;$c['frames']['start_frame']['readiness']='pending_sync';$c['frames']['start_frame']['qa']=null;$c['frames']['start_frame']['employee_acceptance']=['sync_framing'=>true];$c['asset']=$ref;$c['readiness']='pending_sync';
        $r=MarketingSceneRevision::create(['scene_id'=>$old->scene_id,'parent_id'=>$old->id,'content'=>$c,'signature'=>$old->signature]);MarketingScene::findOrFail($old->scene_id)->update(['current_revision_id'=>$r->id]);
        $snap=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);$this->assertTrue($snap['scenes'][0]['frames']['start_frame']['existing_asset_review_required']);$this->assertFalse($snap['scenes'][0]['frames']['start_frame']['accepted_framing_needs_sync']);$this->assertSame($c['frames']['start_frame']['state']??null,$snap['scenes'][0]['frames']['start_frame']['state']??null);$this->assertSame($c['frames']['start_frame']['instruction'],$snap['scenes'][0]['frames']['start_frame']['instruction']);
        $cmd=['scene_id'=>$scene['scene_id'],'revision_id'=>$r->id,'role'=>'start_frame','sha256'=>$ref['sha256'],'sync_framing'=>false,'accept_ai_findings'=>true];$id=(string)Str::uuid();
        $out=app(MarketingStoryboardDecisions::class)->adopt($p->id,$p->owner_id,$id,$s['story_version'],$cmd);$f=$out['scenes'][0]['frames']['start_frame'];
        $this->assertSame('employee_declared_local_validated',$f['readiness']);$this->assertSame($ref['sha256'],$f['asset']['sha256']);$this->assertTrue($f['employee_acceptance']['reused_existing_image']);$this->assertSame($scene['director'],$out['scenes'][0]['director']);
        $this->assertSame($out,app(MarketingStoryboardDecisions::class)->adopt($p->id,$p->owner_id,$id,$s['story_version'],$cmd));$this->assertNotNull(MarketingSceneRevision::find($r->id));Http::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_only_required_frames_gate_handoff_after_explicit_adoption(): void
    {
        [$p,$s,$ref] = $this->sample();
        $scene = $s['scenes'][0];
        $r = MarketingSceneRevision::find($scene['revision_id']);
        $c = $r->content;
        $c['material_plan'] = ['mode' => 'single_image', 'roles' => ['start_frame'], 'reason' => '只需开场参考'];
        $r = MarketingSceneRevision::create(['scene_id' => $r->scene_id, 'parent_id' => $r->id, 'content' => $c, 'signature' => $r->signature]);
        MarketingScene::find($r->scene_id)->update(['current_revision_id' => $r->id]);
        $out = app(MarketingStoryboardDecisions::class)->adopt($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], ['scene_id' => $scene['scene_id'], 'revision_id' => $r->id, 'role' => 'start_frame', 'sha256' => $ref['sha256'], 'sync_framing' => true]);
        $this->assertTrue($out['readiness']);
        $this->assertNull($out['last_confirmation']);
        $this->assertNull($out['scenes'][0]['frames']['end_frame']['asset']);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_fake_http_normal_endpoints_plan_apply_adopt_and_handoff_once(): void
    {
        [$p,$s,$ref] = $this->sample();
        $scene = $s['scenes'][0];
        AiConnection::where('provider', 'openai')->update(['models' => json_encode([['id' => 'fixture-model']])]);
        config(['marketing.storyboard.live_enabled' => true, 'marketing.storyboard.live_grant' => ['reference' => 'FAKE HTTP UNBLOCK TEST', 'project_id' => $p->id, 'owner_id' => $p->owner_id, 'expires_at' => now()->addHour()->toIso8601String(), 'roles' => ['semantic'], 'max_total_microusd' => 1000, 'max_calls' => 1], 'marketing.storyboard.roles.semantic' => ['model' => 'fixture-model', 'version' => '1', 'cap_microusd' => 100, 'pricing_verified_at' => now()->toIso8601String(), 'input_usd_per_million' => 0.2, 'output_usd_per_million' => 1.2]]);
        $answer = ['plans' => [['scene_id' => $scene['scene_id'], 'material_plan' => ['mode' => 'single_image', 'roles' => ['start_frame'], 'reason' => '保留现有候选，只需开场参考']]]];
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['id' => 'fake-response', 'status' => 'completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 10], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($answer)]]]]])]);
        $url = fn ($action) => route('marketing.workspace.storyboard.write', [$p->id, $action]);
        $body = ['request_id' => (string) Str::uuid(), 'version' => $s['story_version']];
        $preview = $this->postJson($url('material-plan-preview'), $body)->assertOk()->json('material_preview');
        $this->postJson($url('material-plan-preview'), $body)->assertOk()->assertJsonPath('material_preview.key', $preview['key']);
        $applied = $this->postJson($url('material-plan-apply'), ['request_id' => (string) Str::uuid(), 'version' => $s['story_version'], 'preview_key' => $preview['key']])->assertOk()->json('snapshot');
        $scene = $applied['scenes'][0];
        $body = ['request_id' => (string) Str::uuid(), 'version' => $applied['story_version'], 'command' => ['scene_id' => $scene['scene_id'], 'revision_id' => $scene['revision_id'], 'role' => 'start_frame', 'sha256' => $ref['sha256'], 'sync_framing' => true]];
        $adopted = $this->postJson($url('adopt-candidate'), $body)->assertOk()->assertJsonPath('snapshot.readiness', true)->json('snapshot');
        $this->postJson($url('adopt-candidate'), $body)->assertOk();
        $this->postJson($url('confirm'), ['request_id' => (string) Str::uuid(), 'version' => $adopted['story_version']])->assertOk()->assertJsonPath('snapshot.last_confirmation.snapshot.stage', 'step3-prepared');
        $this->assertSame(1, MarketingStoryboardProviderCall::count());
        $this->assertSame(14, MarketingStoryboardProviderCall::first()->actual_microusd);
        $this->assertSame($p->current_revision_id, $p->fresh()->current_revision_id);
        Http::assertSentCount(1);
        Queue::assertNothingPushed();
    }

    public function test_preflight_failure_does_not_freeze_a_never_sent_analysis(): void
    {
        [$p,$s] = $this->sample();
        $scene = $s['scenes'][0];
        $this->mock(MarketingStoryboardProvider::class, function ($m) {
            $m->shouldReceive('allowed')->andReturn(true);
            $m->shouldReceive('role')->andReturn(['model' => 'fake', 'version' => '1', 'cap_microusd' => 100]);
        });
        $this->mock(MarketingStoryboardOpenAi::class, function ($m) use ($scene) {
            $m->shouldReceive('connection')->andReturn([null, ['model' => 'fake', 'version' => '1', 'cap_microusd' => 100]]);
            $m->shouldReceive('materialPlan')->once()->andReturn(['answer' => ['plans' => [['scene_id' => $scene['scene_id'], 'material_plan' => ['mode' => 'single_image', 'roles' => ['start_frame'], 'reason' => '复用开场']]]]]);
        });
        config(['marketing.storyboard.live_grant.max_total_microusd' => 0]);
        try {
            app(MarketingStoryboardDecisions::class)->preview($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version']);
            $this->fail('Missing budget must reject');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        Http::assertNothingSent();
        config(['marketing.storyboard.live_grant.max_total_microusd' => 1000]);
        $out = app(MarketingStoryboardDecisions::class)->preview($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version']);
        $this->assertCount(1, $out['plans']);
    }

    public function test_unknown_usage_freezes_analysis_without_retry(): void
    {
        [$p,$s] = $this->sample();
        $scene = $s['scenes'][0];
        AiConnection::where('provider', 'openai')->update(['models' => json_encode([['id' => 'fixture-model']])]);
        config(['marketing.storyboard.live_enabled' => true, 'marketing.storyboard.live_grant' => ['reference' => 'FAKE UNKNOWN COST', 'project_id' => $p->id, 'owner_id' => $p->owner_id, 'expires_at' => now()->addHour()->toIso8601String(), 'roles' => ['semantic'], 'max_total_microusd' => 1000, 'max_calls' => 2], 'marketing.storyboard.roles.semantic' => ['model' => 'fixture-model', 'version' => '1', 'cap_microusd' => 100, 'pricing_verified_at' => now()->toIso8601String(), 'input_usd_per_million' => 0.2, 'output_usd_per_million' => 1.2]]);
        $answer = ['plans' => [['scene_id' => $scene['scene_id'], 'material_plan' => ['mode' => 'single_image', 'roles' => ['start_frame'], 'reason' => '开场参考']]]];
        $response = ['id' => 'fake-unknown-usage', 'status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($answer)]]]]];
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($response)]);
        $url = route('marketing.workspace.storyboard.write', [$p->id, 'material-plan-preview']);
        $this->postJson($url, ['request_id' => (string) Str::uuid(), 'version' => $s['story_version']])->assertStatus(409);
        $this->postJson($url,['request_id' => (string) Str::uuid(), 'version' => $s['story_version']])->assertStatus(409);
        Http::assertSentCount(1);
        Queue::assertNothingPushed();
        $this->assertSame('unknown',MarketingStoryboardProviderCall::first()->status);
    }
}
