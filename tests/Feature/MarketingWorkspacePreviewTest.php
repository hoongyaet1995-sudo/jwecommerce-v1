<?php

namespace Tests\Feature;

use App\Models\MarketingProduction;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingRevision;
use App\Models\User;
use App\Services\Marketing\MarketingWorkflow;
use App\Services\Marketing\MarketingWorkspaceView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingWorkspacePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_private_read_only_and_all_eight_screens_render(): void
    {
        Http::preventStrayRequests();
        $this->get('/admin/marketing/workspace-preview')->assertRedirect();
        $owner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $p = app(MarketingWorkflow::class)->create($owner, ['source_type' => 'theme', 'theme' => 'Actual saved project title']);
        $before = $p->getRawOriginal();
        $this->actingAs($owner);
        foreach (['source', 'preferences', 'guided', 'brief', 'concepts', 'materials', 'confirmation', 'finished'] as $screen) {
            $this->get('/admin/marketing/workspace-preview?project='.$p->id.'&screen='.$screen)->assertOk()->assertSee('Actual saved project title')->assertSee('workspace-preview.css')->assertDontSee('US$2.80')->assertDontSee('/build-admin/');
        }
        $this->assertSame($before, $p->fresh()->getRawOriginal());
        $this->get('/admin/marketing/workspace-preview?screen=invalid')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))->get('/admin/marketing/workspace-preview?project='.$p->id)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))->get('/admin/marketing/workspace-preview')->assertForbidden();
    }

    public function test_old_completed_video_is_identified_as_historical_after_project_changes(): void
    {
        $owner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $p = app(MarketingWorkflow::class)->create($owner, ['source_type' => 'theme', 'theme' => 'Saved story with a historical video']);
        $revision = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $owner->id, 'number' => 1, 'document' => ['scenes' => []], 'context' => []]);
        $p->update(['current_revision_id' => $revision->id]);
        $plan = MarketingProductionPlan::create(['project_id' => $p->id, 'revision_id' => $revision->id, 'prepared_by' => $owner->id, 'request_id' => (string) Str::uuid(), 'project_version' => $p->version, 'fingerprint' => str_repeat('a', 64), 'snapshot' => [], 'manifest' => [], 'status' => 'prepared', 'expires_at' => now()->addDay()]);
        MarketingProduction::create(['project_id' => $p->id, 'plan_id' => $plan->id, 'owner_id' => $owner->id, 'status' => 'completed']);
        $p->update(['version' => $p->version + 1, 'current_revision_id' => null]);
        $this->actingAs($owner)->get('/admin/marketing/workspace-preview?screen=finished&project='.$p->id)->assertOk()->assertSee('历史成片')->assertSee('不代表当前分镜的制作结果');
    }

    public function test_asset_adapter_rejects_external_or_traversal_paths(): void
    {
        $owner = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $p = app(MarketingWorkflow::class)->create($owner, ['source_type' => 'theme', 'theme' => 'Saved source material']);
        foreach (['/images/../../.env', 'https://external.invalid/private.png', '//external.invalid/image.png'] as $path) {
            $p->snapshot = ['title' => 'Saved source', 'images' => [['path' => $path]]];
            $this->assertNull(app(MarketingWorkspaceView::class)->data($p, 'materials')['image']);
        }
    }

    public function test_assistant_samples_are_read_only_and_separate_from_live_workspace():void
    {
        Http::preventStrayRequests();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $p=app(MarketingWorkflow::class)->create($u,['source_type'=>'theme','theme'=>'Read-only assistant sample']);$before=$p->getRawOriginal();$this->actingAs($u);
        foreach(['guided','concepts'] as $screen){
            $this->get('/admin/marketing/workspace-preview?sample=assistant&project='.$p->id.'&screen='.$screen)->assertOk()->assertSee('对话交互样本')->assertSee('assistant-sample.js')->assertSee('data-assistant-sample',false)->assertDontSee('workspace-conversation.js')->assertDontSee('data-project-chat-form',false);
        }
        $this->assertSame($before,$p->fresh()->getRawOriginal());Http::assertNothingSent();
    }
    public function test_material_review_sample_has_one_entry_and_cannot_write_or_call_ai():void
    {
        Http::preventStrayRequests();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $p=app(MarketingWorkflow::class)->create($u,['source_type'=>'theme','theme'=>'Sample material review']);$before=$p->getRawOriginal();$this->actingAs($u);
        $this->get('/admin/marketing/workspace-preview?sample=material-review&screen=materials&project='.$p->id)->assertOk()->assertSee('开始审阅与准备')->assertSee('data-material-review-sample',false)->assertSee('material-review-sample.js')->assertSee('仅在本页预览')->assertDontSee('workspace-conversation.js')->assertDontSee('data-creative-action',false)->assertDontSee('<form',false);
        $this->assertSame($before,$p->fresh()->getRawOriginal());$this->assertDatabaseCount('marketing_ai_runs',0);Http::assertNothingSent();
        $this->get('/admin/marketing/workspace-preview?sample=material-review&screen=concepts&project='.$p->id)->assertNotFound();
    }}
