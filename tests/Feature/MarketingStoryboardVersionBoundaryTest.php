<?php

namespace Tests\Feature;

use App\Models\{MarketingRevision,MarketingProductionPlan,MarketingVideoFilm,MarketingStoryboardWorkspace as Workspace};
use App\Services\Marketing\{MarketingStoryboardWorkspace,MarketingStoryboardHandoff,MarketingProductionPreparation,MarketingStoryboardUpload,MarketingProductionPreferences};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http,Queue};
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardVersionBoundaryTest extends TestCase
{
    use MarketingResearchFixtures;
    protected function setUp(): void { parent::setUp();$this->artisan('migrate:fresh')->run(); }
    private function sample(bool $ready=false): array
    {
        config(['marketing.workspace_video.enabled'=>true]);
        $p=$this->researchProject();
        $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>['scenes'=>[['title'=>'Current warehouse story','visual'=>'Current warehouse story','duration'=>5,'voiceover'=>'','subtitle'=>'']]],'context'=>[]]);
        $p->update(['current_revision_id'=>$r->id]);
        $w=app(MarketingStoryboardWorkspace::class);$s=$w->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
        if($ready){$ref=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('ready.png'),true);$s=$w->apply($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','scene_id'=>$s['scenes'][0]['scene_id'],'content'=>$s['scenes'][0],'asset_id'=>$ref['asset_id']]);}
        return [$p,$s];
    }
    public function test_ordinary_confirmation_without_route_renders_instead_of_leaking_blade_or_crashing(): void
    {
        [$p,$s]=$this->sample(true);
        $this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'confirmation','storyboard'=>1]))
            ->assertOk()->assertDontSee('if($selectedRoute)',false)->assertDontSee('@else',false);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_completed_batch_displays_settled_cost_even_when_new_quote_is_blocked(): void
    {
        [$p,$s]=$this->sample(true);
        app(MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $auth=(string)Str::uuid();
        \App\Models\MarketingProduction::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'plan_id'=>$plan->id,'status'=>'completed','result'=>['authorization_id'=>$auth]]);
        \App\Models\MarketingStoryboardProviderCall::create(['id'=>hash('sha256',$auth),'project_id'=>$p->id,'authorization_id'=>$auth,'role'=>'video','grant_reference'=>'fake','cap_microusd'=>300000,'actual_microusd'=>273600,'status'=>'completed','input'=>[]]);
        $this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'confirmation']))->assertOk()
            ->assertSee('USD 0.27')->assertSee('实际请求：1 次 POST')->assertDontSee('未调用收费生成')->assertDontSee('没有输入方式、时长');
        // Navigating back through Step3 must not silently reprice or shadow completed results.
        $count=MarketingProductionPlan::count();
        $this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'production-entry']),['request_id'=>(string)Str::uuid(),'version'=>$s['story_version']])->assertOk()->assertJsonPath('preview_url',route('marketing.workspace.show',['project'=>$p->id,'screen'=>'confirmation','storyboard'=>1]));
        $this->assertSame($count,MarketingProductionPlan::count());
        $navigationPlan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $this->assertNotSame($plan->id,$navigationPlan->id);
        $this->assertSame($plan->id,app(MarketingProductionPreparation::class)->storyboardPreview($p->id,$p->owner_id)['plan']->id);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_normal_confirmation_navigation_uses_current_workspace_without_query_flag(): void
    {
        [$p,$s]=$this->sample();
        $this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'confirmation']))->assertOk()->assertSee('当前工作稿')->assertSee('Current warehouse story')->assertSee('尚未交接')->assertSee('画面待准备')->assertDontSee('方案准备好了，确认后开始制作');
        $this->assertSame($s['story_version'],Workspace::where('project_id',$p->id)->first()->version);
        $this->assertSame(0,MarketingProductionPlan::count());Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_changed_draft_rejects_old_handoff_and_keeps_old_plan_in_history(): void
    {
        [$p,$s]=$this->sample(true);
        app(MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $old=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $next=app(MarketingProductionPreferences::class)->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],'advanced');
        $out=app(MarketingProductionPreparation::class)->storyboardPreview($p->id,$p->owner_id);
        $this->assertFalse($out['handoff_current']);$this->assertNull($out['plan']);
        $this->assertTrue($out['history']->contains('id',$old->id));
        $this->assertSame($next['story_version'],$out['snapshot']['story_version']);
        $this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'start-production']),['request_id'=>(string)Str::uuid(),'version'=>$next['story_version'],'plan_id'=>$old->id,'consent'=>true])->assertStatus(409);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_expired_quote_cannot_be_confirmed_in_ui(): void
    {
        [$p,$s]=$this->sample(true);app(MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $plan->update(['expires_at'=>now()->subMinute()]);
        $out=app(MarketingProductionPreparation::class)->storyboardPreview($p->id,$p->owner_id);
        $this->assertTrue($out['handoff_current']);$this->assertFalse($out['quote_current']);
        $this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'confirmation']))->assertOk()->assertSee('重新核价')->assertSee('报价已过期');
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_legacy_film_is_historical_when_new_workspace_exists(): void
    {
        [$p,$s]=$this->sample();
        $film=MarketingVideoFilm::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'fingerprint'=>str_repeat('a',64),'status'=>'completed','input'=>['version'=>\App\Services\Marketing\MarketingWorkspaceFilm::VERSION,'scenes'=>[],'duration'=>15],'result'=>[]]);
        $this->mock(\App\Services\Marketing\MarketingWorkspaceFilm::class,function($m)use($p){$m->shouldReceive('assertCurrent')->andReturn($p);});
        $out=app(\App\Services\Marketing\MarketingVideoWorkbench::class)->storyboardFilmData($p,$film->id);
        $this->assertFalse($out['film_current']);$this->assertSame('历史成片',$out['film_binding']['label']);
        $this->assertSame($film->id,$out['film']->id);$this->assertCount(1,$out['film_history']);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_approved_historical_download_keeps_owner_hash_and_approval_guards(): void
    {
        [$p]=$this->sample();$path='marketing/'.$p->id.'/historical-output.mp4';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path,'FAKE immutable film bytes');$hash=hash('sha256','FAKE immutable film bytes');
        $film=MarketingVideoFilm::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'fingerprint'=>str_repeat('b',64),'status'=>'completed','output_path'=>$path,'output_sha256'=>$hash,'approved_sha256'=>$hash,'approved_at'=>now(),'input'=>['version'=>\App\Services\Marketing\MarketingWorkspaceFilm::VERSION,'scenes'=>[],'duration'=>15],'result'=>[]]);
        $file=app(\App\Services\Marketing\MarketingWorkspaceFilm::class)->download($p->id,$p->owner_id,$film->id);
        $this->assertSame($hash,hash_file('sha256',$file));
        \Illuminate\Support\Facades\Storage::disk('local')->put($path,'tampered');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\Marketing\MarketingWorkspaceFilm::class)->download($p->id,$p->owner_id,$film->id);
    }

    public function test_rate_change_invalidates_quote_without_changing_handoff(): void
    {
        [$p,$s]=$this->sample(true);app(MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $manifest=$plan->manifest;$manifest['execution_profile']=\App\Services\Marketing\MarketingStoryboardProduction::PROFILE;$manifest['blockers']=[];$manifest['configuration']=['fake_price'=>1];
        $plan->update(['manifest'=>$manifest,'status'=>'awaiting_budget']);
        $price=1;
        $this->partialMock(\App\Services\Marketing\MarketingStoryboardProduction::class,function($m)use(&$price){$m->shouldReceive('configuration')->andReturnUsing(function()use(&$price){return ['fake_price'=>$price];});});
        $service=app(MarketingProductionPreparation::class);$this->assertTrue($service->storyboardPreview($p->id,$p->owner_id)['quote_current']);
        $price=2;$out=$service->storyboardPreview($p->id,$p->owner_id);
        $this->assertTrue($out['handoff_current']);$this->assertFalse($out['quote_current']);
        $this->assertSame($plan->id,$out['plan']->id);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_new_handoff_builds_new_plan_and_preserves_old_snapshot(): void
    {
        [$p,$s]=$this->sample(true);$handoff=app(MarketingStoryboardHandoff::class);$prep=app(MarketingProductionPreparation::class);
        $handoff->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $old=$prep->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);$oldSnapshot=$old->snapshot;
        $next=app(MarketingProductionPreferences::class)->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],'advanced');
        $handoff->confirm($p->id,$p->owner_id,(string)Str::uuid(),$next['story_version']);
        $new=$prep->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$next['story_version']);
        $this->assertNotSame($old->id,$new->id);$this->assertSame($oldSnapshot,$old->fresh()->snapshot);
        $this->assertSame($next['story_version'],$new->manifest['story_version']);
        $this->assertSame($new->id,$prep->storyboardPreview($p->id,$p->owner_id)['plan']->id);
        $this->assertCount(2,$prep->storyboardPreview($p->id,$p->owner_id)['history']);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_current_film_is_preferred_and_derived_films_keep_root_version(): void
    {
        [$p,$s]=$this->sample();
        $root=MarketingVideoFilm::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'fingerprint'=>str_repeat('c',64),'status'=>'completed','input'=>['version'=>\App\Services\Marketing\MarketingStoryboardProduction::FILM,'story_id'=>$s['story_id'],'story_version'=>$s['story_version'],'readiness_digest'=>hash('sha256',json_encode($s['scenes'],JSON_THROW_ON_ERROR)),'scenes'=>[],'duration'=>5],'result'=>[],'created_at'=>now()->subMinutes(2)]);
        $history=MarketingVideoFilm::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'fingerprint'=>str_repeat('d',64),'status'=>'completed','input'=>['version'=>\App\Services\Marketing\MarketingWorkspaceFilm::VERSION,'scenes'=>[],'duration'=>15],'result'=>[]]);
        $this->mock(\App\Services\Marketing\MarketingWorkspaceFilm::class,function($m)use($p){$m->shouldReceive('assertCurrent')->andReturn($p);});
        $out=app(\App\Services\Marketing\MarketingVideoWorkbench::class)->storyboardFilmData($p);
        $this->assertSame($root->id,$out['film']->id);$this->assertTrue($out['film_current']);
        $derived=MarketingVideoFilm::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'fingerprint'=>str_repeat('e',64),'status'=>'completed','input'=>['version'=>\App\Services\Marketing\MarketingFilmPackaging::VERSION,'content_film_id'=>$root->id,'scenes'=>[],'duration'=>8],'result'=>[]]);
        $binding=app(\App\Services\Marketing\MarketingStoryboardVersions::class)->filmBinding($derived,$s);
        $this->assertTrue($binding['current']);$this->assertSame($root->id,$binding['root_film_id']);
        $next=app(MarketingProductionPreferences::class)->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],'advanced');
        $this->assertFalse(app(\App\Services\Marketing\MarketingStoryboardVersions::class)->filmBinding($derived,$next)['current']);
        $this->assertSame($history->id,app(\App\Services\Marketing\MarketingVideoWorkbench::class)->storyboardFilmData($p,$history->id)['film']->id);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
}
