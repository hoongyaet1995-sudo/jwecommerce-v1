<?php

namespace Tests\Feature;

use App\Models\MarketingPreparationAuthorization;
use App\Models\MarketingRevision;
use App\Models\MarketingSceneRevision;
use App\Services\Marketing\MarketingPreparationBudget;
use App\Services\Marketing\MarketingStoryboardPreparation;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardPreparationTest extends TestCase
{
    use MarketingResearchFixtures,RefreshDatabase;

    private function sample()
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['visual' => 'Photo', 'subtitle' => 'Product', 'voiceover' => '', 'duration' => 5]]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $s = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);

        return [$p, $s];
    }

    public function test_budget_claims_are_durable_and_unknown_stays_frozen()
    {
        [$p,$s] = $this->sample();
        $b = app(MarketingPreparationBudget::class);
        $a = $b->authorize($p->id, $p->owner_id, (string) Str::uuid(), 1, [$s['scenes'][0]['revision_id']], 100);
        $b->reserve($a['id'], 'image', 70);
        $b->reserve($a['id'], 'image', 70);
        $b->settle($a['id'], 'image', null);
        $row = MarketingPreparationAuthorization::findOrFail($a['id']);
        $this->assertSame(70, $row->unknown_microusd);
        $this->assertSame(0, $row->reserved_microusd);
        $this->expectException(HttpException::class);
        $b->reserve($a['id'], 'other', 40);
    }

    public function test_disabled_preparation_never_dispatches_external_request()
    {
        [$p,$s] = $this->sample();
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 1, [$s['scenes'][0]['revision_id']], 100);
        try {
            app(MarketingStoryboardPreparation::class)->prepare($p->id, $a['id']);
            $this->fail('must block');
        } catch (HttpException $e) {
            $this->assertSame(423, $e->getStatusCode());
        }Http::assertNothingSent();
    }

    public function test_offline_image_qa_promotes_only_matching_revision_and_duplicate_job_is_noop()
    {
        [$p,$s] = $this->sample();
        $asset = UploadedFile::fake()->image('first.png', 90, 160);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, $asset, true);
        $calls = 0;
        app()->instance('marketing.storyboard.image_transport', function ($compiled) use ($ref, &$calls) {
            $calls++;

            return ['asset' => $ref, 'actual_microusd' => 10];
        });
        app()->instance('marketing.storyboard.qa_transport', fn () => ['status' => 'pass', 'evidence' => ['fixture' => 'deterministic offline only']]);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 1, [$s['scenes'][0]['revision_id']], 100);
        $service = app(MarketingStoryboardPreparation::class);
        $tasks = $service->prepare($p->id, $a['id']);
        $service->process($tasks['tasks'][0]);
        $service->process($tasks['tasks'][0]);
        $after = app(MarketingStoryboardWorkspace::class)->snapshot($p->id, $p->owner_id);
        $this->assertTrue($after['readiness']);
        $this->assertSame($ref['sha256'], $after['scenes'][0]['asset']['sha256']);
        $this->assertSame(1, $calls);
        $this->assertNull(MarketingSceneRevision::find($s['scenes'][0]['revision_id'])->content['asset']);
        $edited = app(MarketingStoryboardWorkspace::class)->apply($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => ['seconds' => 5, 'message' => 'Product', 'visual' => 'Photo', 'voiceover' => 'Updated approved voice', 'subtitle' => '']]);
        $this->assertSame($ref['sha256'], $edited['scenes'][0]['asset']['sha256']);
    }

    public function test_zero_repair_policy_stops_after_first_quality_failure(): void
    {
        [$p,$s] = $this->sample();
        config(['marketing.storyboard.max_repairs'=>0]);
        $ref = app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('first.png',90,160),true);
        $calls=0;
        app()->instance('marketing.storyboard.image_transport',function()use($ref,&$calls){$calls++;return ['asset'=>$ref,'actual_microusd'=>10];});
        app()->instance('marketing.storyboard.qa_transport',fn()=>['status'=>'repair','evidence'=>['fixture'=>true]]);
        $a=app(MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),1,[$s['scenes'][0]['revision_id']],200);
        $svc=app(MarketingStoryboardPreparation::class);$tasks=$svc->prepare($p->id,$a['id']);
        $svc->process($tasks['tasks'][0]);$svc->process($tasks['tasks'][0]);
        $this->assertSame(1,$calls);
        $after=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);$this->assertFalse($after['readiness']);$this->assertNull($after['scenes'][0]['frames']['start_frame']['asset']);$this->assertSame($ref['sha256'],$after['scenes'][0]['frames']['start_frame']['candidate_asset']['sha256']);
        $this->assertSame('blocked',\App\Models\MarketingStoryboardTask::findOrFail($tasks['tasks'][0])->status);
    }

    public function test_qa_repair_is_limited_to_one_and_unknown_is_not_retried()
    {
        [$p,$s] = $this->sample();
        $ref = app(MarketingStoryboardUpload::class)->store($p->id, $p->owner_id, UploadedFile::fake()->image('first.png', 90, 160), true);
        $calls = 0;
        app()->instance('marketing.storyboard.image_transport', function () use ($ref, &$calls) {
            $calls++;

            return ['asset' => $ref, 'actual_microusd' => 10];
        });
        app()->instance('marketing.storyboard.qa_transport', fn () => ['status' => 'repair', 'evidence' => ['fixture' => 'offline repair request']]);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), 1, [$s['scenes'][0]['revision_id']], 200);
        $svc = app(MarketingStoryboardPreparation::class);
        $tasks = $svc->prepare($p->id, $a['id']);
        $svc->process($tasks['tasks'][0]);
        $svc->process($tasks['tasks'][0]);
        $this->assertSame(2,$calls);
        $this->assertFalse(app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id)['readiness']);
    }
    public function test_review_removed_inflight_scene_does_not_trigger_repair():void {
        [$p,$s]=$this->sample();$w=app(MarketingStoryboardWorkspace::class);$w->apply($p->id,$p->owner_id,(string)Str::uuid(),1,['type'=>'insert','content'=>['seconds'=>5,'message'=>'Other','visual'=>'Other','voiceover'=>'','subtitle'=>'']]);$ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('old.png',90,160),true);$calls=0;app()->instance('marketing.storyboard.image_transport',function()use($w,$p,$s,$ref,&$calls){$calls++;if($calls===1)$w->apply($p->id,$p->owner_id,(string)Str::uuid(),2,['type'=>'remove','scene_id'=>$s['scenes'][0]['scene_id']]);return ['asset'=>$ref,'actual_microusd'=>10];});app()->instance('marketing.storyboard.qa_transport',fn()=>['status'=>'repair','evidence'=>['fixture'=>true]]);$a=app(MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),2,[$s['scenes'][0]['revision_id']],200);$svc=app(MarketingStoryboardPreparation::class);$r=$svc->prepare($p->id,$a['id']);$svc->process($r['tasks'][0]);$this->assertSame(1,$calls);$this->assertSame('historical',\App\Models\MarketingStoryboardTask::find($r['tasks'][0])->status);
    }
}
