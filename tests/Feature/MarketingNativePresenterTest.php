<?php

namespace Tests\Feature;

use App\Models\{MarketingProject,MarketingProductionPlan,MarketingProduction,MarketingAiRun,User};
use App\Services\Marketing\{MarketingProductionCompiler,MarketingProductionProcessor,MarketingVideoRenderer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingNativePresenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();Http::preventStrayRequests();Queue::fake();Storage::fake('local');
        config(['marketing.native.enabled'=>true,'marketing.native.live_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
    }

    private function draft(): MarketingProject
    {
        $this->post('/admin/marketing/native-presenter',['facts'=>'JTG is near Port Klang. Multiple container types; contact for suitability and price.','script'=>'巴生港附近找货柜？JTG提供多种货柜选择。告诉我们您的用途，欢迎联系我们咨询方案和报价。','facts_confirmation'=>1])->assertRedirect();
        return MarketingProject::with('currentRevision')->sole();
    }

    private function plan(bool $grant=true): array
    {
        $p=$this->draft();$r=$p->currentRevision;
        $this->post('/admin/marketing/projects/'.$p->id.'/confirm',['version'=>$p->version,'revision_id'=>$r->id,'content_confirmation'=>1])->assertRedirect();$p->refresh();$r->refresh();
        if($grant){$payload=app(\App\Services\Marketing\MarketingNativePresenter::class)->payload($p,$r);config(['marketing.native.authorization'=>['id'=>(string)Str::uuid(),'project'=>$p->id,'revision'=>$r->id,'signature'=>hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),'cap_usd'=>'3.50','expires_at'=>now()->addHour()->toIso8601String()]]);}
        $this->post('/admin/marketing/projects/'.$p->id.'/production',['version'=>$p->version,'revision_id'=>$r->id,'request_id'=>(string)Str::uuid()])->assertRedirect();
        $plan=MarketingProductionPlan::where('project_id',$p->id)->sole();
        return [$p,$plan];
    }

    public function test_native_draft_uses_no_media_and_requires_separate_content_and_paid_budget(): void
    {
        [$p,$plan]=$this->plan(false);$m=$plan->manifest;
        $this->get('/admin/marketing/projects/'.$p->id)->assertOk()->assertSee('Script and storyboard');
        $this->assertSame('native-presenter-evolink-v1',$m['recipe']);$this->assertSame(15,$m['native']['payload']['duration']);$this->assertTrue($m['native']['payload']['generate_audio']);$this->assertFalse($m['native']['payload']['model_params']['web_search']);
        $this->assertSame(0,$m['audio']['requests']);$this->assertNull($m['budget']['cap_microusd']);$this->assertNotEmpty($m['blockers']);
        $this->get('/admin/marketing/projects/'.$p->id.'/production')->assertOk()->assertSee('Native presenter checklist')->assertSee('Spending authorization: awaiting separate approval')->assertDontSee('fake-native-key');
        $this->post('/admin/marketing/projects/'.$p->id.'/production/'.$plan->id.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertStatus(422);
        $this->assertDatabaseCount('marketing_ai_runs',0);$this->assertDatabaseCount('marketing_productions',0);Http::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_native_submission_claims_once_and_queries_only_known_task_without_remotion(): void
    {
        [$p,$plan]=$this->plan();$base='/admin/marketing/projects/'.$p->id.'/production/'.$plan->id;
        $this->post($base.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertRedirect();
        $this->post($base.'/start',['version'=>$p->version])->assertRedirect();$task=MarketingProduction::sole();
        config(['marketing.native.live_enabled'=>true,'marketing.native.api_key'=>'fake-native-key']);
        Http::fake(['api.evolink.ai/v1/videos/generations'=>Http::response(['id'=>'task-native-123','status'=>'pending','usage'=>['credits_reserved'=>202.5]],201),'api.evolink.ai/v1/tasks/task-native-123'=>Http::response(['id'=>'task-native-123','status'=>'processing'])]);
        $this->mock(MarketingVideoRenderer::class)->shouldNotReceive('assertReady');
        $processor=app(MarketingProductionProcessor::class);$processor->process($task->id);$processor->process($task->id);
        $this->assertSame('external_pending',$task->fresh()->status);$this->assertSame('task-native-123',$task->fresh()->result['provider_task_id']);
        Http::assertSent(fn($r)=>$r->method()==='POST'&&$r['model']==='seedance-2.0-text-to-video'&&$r['duration']===15&&!isset($r['image_urls']));
        $this->assertCount(1,Http::recorded(fn($r)=>$r->method()==='POST'));
        $this->assertDatabaseCount('marketing_voice_assets',0);
        $this->post('/admin/marketing/projects/'.$p->id.'/productions/'.$task->id.'/check')->assertRedirect();
        $processor->process($task->id);$this->assertCount(1,Http::recorded(fn($r)=>$r->method()==='POST'));$this->assertSame('external_pending',$task->fresh()->status);
    }

    public function test_unknown_native_submission_is_never_automatically_submitted_again(): void
    {
        [$p,$plan]=$this->plan();$base='/admin/marketing/projects/'.$p->id.'/production/'.$plan->id;
        $this->post($base.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertRedirect();$this->post($base.'/start',['version'=>$p->version])->assertRedirect();
        $task=MarketingProduction::sole();config(['marketing.native.live_enabled'=>true,'marketing.native.api_key'=>'fake']);Http::fake(['api.evolink.ai/*'=>Http::response([],503)]);
        app(MarketingProductionProcessor::class)->process($task->id);app(MarketingProductionProcessor::class)->process($task->id);
        $this->assertSame('unknown',$task->fresh()->status);$this->assertTrue($task->fresh()->result['provider_submission_claimed']);Http::assertSentCount(1);
        $this->post($base.'/start',['version'=>$p->version])->assertRedirect();$this->assertSame('unknown',$task->fresh()->status);
    }

    public function test_native_completion_remains_unapproved_and_uses_existing_private_delivery_gate(): void
    {
        [$p,$plan]=$this->plan();$base='/admin/marketing/projects/'.$p->id.'/production/'.$plan->id;
        $this->post($base.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertRedirect();$this->post($base.'/start',['version'=>$p->version])->assertRedirect();$task=MarketingProduction::sole();
        config(['marketing.native.live_enabled'=>true,'marketing.native.api_key'=>'fake']);
        Http::fake(['api.evolink.ai/v1/videos/generations'=>Http::response(['id'=>'task-native-done'],201),'api.evolink.ai/v1/tasks/task-native-done'=>Http::response(['id'=>'task-native-done','status'=>'completed','results'=>['https://media.example.com/output.mp4'],'usage'=>['credits_consumed'=>202.5]])]);
        $bytes='A verified native media fixture';$hash=hash('sha256',$bytes);
        $this->mock(\App\Services\Marketing\MarketingEvolinkMedia::class)->shouldReceive('store')->once()->andReturnUsing(function($t,$url)use($bytes,$hash){Storage::disk('local')->put('marketing-productions/'.$t->id.'/output.mp4',$bytes);return ['duration_seconds'=>15,'width'=>720,'height'=>1280,'frames'=>360,'sha256'=>$hash,'bytes'=>strlen($bytes),'voice_source'=>'evolink_native_audio','voice_reused'=>false,'publication_authorized'=>false,'composition'=>'provider_native_no_remotion'];});
        $this->mock(MarketingVideoRenderer::class)->shouldNotReceive('assertReady');
        app(MarketingProductionProcessor::class)->process($task->id);$this->assertSame('completed',$task->fresh()->status);
        $url='/admin/marketing/projects/'.$p->id.'/productions/'.$task->id;
        $this->get($url.'/download')->assertForbidden();$this->post($url.'/approve',['output_sha256'=>$hash,'final_confirmation'=>1])->assertRedirect();$this->get($url.'/download')->assertOk();
        $this->assertDatabaseCount('marketing_production_approvals',1);$this->assertDatabaseCount('marketing_voice_assets',0);Http::assertSentCount(2);
    }

    public function test_native_disabled_live_expired_grant_and_foreign_check_never_generate(): void
    {
        [$p,$plan]=$this->plan();$base='/admin/marketing/projects/'.$p->id.'/production/'.$plan->id;
        $this->post($base.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertRedirect();$this->post($base.'/start',['version'=>$p->version])->assertRedirect();$task=MarketingProduction::sole();
        app(MarketingProductionProcessor::class)->process($task->id);$this->assertSame('blocked',$task->fresh()->status);$this->assertSame(0,$task->fresh()->new_reserved_microusd);
        $this->travel(2)->hours();$this->post($base.'/start',['version'=>$p->version])->assertStatus(409);$this->travelBack();
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->post('/admin/marketing/projects/'.$p->id.'/productions/'.$task->id.'/check')->assertNotFound();Http::assertNothingSent();
    }

    public function test_native_download_rejects_private_destination_before_http(): void
    {
        $media=\Mockery::mock(\App\Services\Marketing\MarketingEvolinkMedia::class)->makePartial()->shouldAllowMockingProtectedMethods();$media->shouldReceive('addresses')->andReturn(['127.0.0.1']);
        try{$media->downloadOptions('https://media.example.com/out.mp4');$this->fail('Private result destination must fail');}catch(\App\Services\Marketing\MarketingProductionFailure $e){$this->assertSame('native_result_url_invalid',$e->reason);}
        Http::assertNothingSent();
    }

    public function test_native_result_download_is_bounded_private_and_never_forwards_provider_key(): void
    {
        $p=$this->draft();$task=new MarketingProduction(['id'=>(string)Str::uuid(),'project_id'=>$p->id]);
        $media=\Mockery::mock(\App\Services\Marketing\MarketingEvolinkMedia::class)->makePartial()->shouldAllowMockingProtectedMethods();$media->shouldReceive('addresses')->andReturn(['8.8.8.8']);
        $bytes=str_repeat('private-native-media',100);
        Http::fake(['media.example.com/*'=>Http::response($bytes,200,['Content-Length'=>(string)strlen($bytes)])]);
        $tools=$this->mock(\App\Services\Marketing\MarketingMediaTools::class);$tools->shouldReceive('probe')->once()->andReturn(['streams'=>[['codec_type'=>'video','codec_name'=>'h264','width'=>720,'height'=>1280,'nb_read_frames'=>'360'],['codec_type'=>'audio','codec_name'=>'aac']],'format'=>['duration'=>15]]);$tools->shouldReceive('run')->once()->withArgs(fn($command,$timeout,$discard)=>in_array('rawvideo',$command,true)&&in_array('pcm_s16le',$command,true)&&$timeout===60&&$discard===true)->andReturn('');
        $result=$media->store($task,'https://media.example.com/out.mp4');$this->assertSame(hash('sha256',$bytes),$result['sha256']);$this->assertSame('provider_native_no_remotion',$result['composition']);Storage::disk('local')->assertExists('marketing-productions/'.$task->id.'/output.mp4');
        Http::assertSent(fn($r)=>!$r->hasHeader('Authorization'));
        Http::fake(['media.example.com/*'=>Http::response('oversize',200,['Content-Length'=>'40000001'])]);
        $other=new MarketingProduction(['id'=>(string)Str::uuid(),'project_id'=>$p->id]);
        try{$media->store($other,'https://media.example.com/huge.mp4');$this->fail('Oversize result must be blocked');}catch(\App\Services\Marketing\MarketingProductionFailure $e){$this->assertSame('native_media_unverified',$e->reason);Storage::disk('local')->assertMissing('marketing-productions/'.$other->id.'/output.mp4');}
    }
}
