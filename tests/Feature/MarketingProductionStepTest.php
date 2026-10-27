<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingDynamicFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\MarketingProductionStep;
use App\Services\Marketing\{MarketingProductionProcessor,MarketingProductionSteps,MarketingProductionFailure};
class MarketingProductionStepTest extends TestCase
{
    use RefreshDatabase,MarketingDynamicFixtures;
    public function test_generation_nodes_including_shared_reference_are_rejected_without_any_provider_call():void
    {
        [$p,$r]=$this->dynamicFixture();[$plan,$t]=$this->dynamicStart($p,$r);$t->update(['status'=>'preparing']);$m=$plan->manifest;$m['strategy']['nodes']=[['key'=>'same-generation','kind'=>'generate_video','depends_on'=>[]],['key'=>'same-generation','kind'=>'generate_video','depends_on'=>[]]];$plan->update(['manifest'=>$m]);
        config(['marketing.native.live_enabled'=>true,'marketing.native.api_key'=>'fixture-only']);
        try{app(MarketingProductionSteps::class)->process($t->id);$this->fail('Paid node must remain disabled');}catch(MarketingProductionFailure $e){$this->assertSame('dynamic_generation_disabled',$e->reason);}
        $this->assertDatabaseCount('marketing_production_steps',0);Http::assertNothingSent();
    }
    public function test_expired_plan_or_revoked_role_stops_before_any_step_claim():void
    {
        [$p,$r]=$this->dynamicFixture();[$plan,$t]=$this->dynamicStart($p,$r);$plan->update(['expires_at'=>now()->subMinute()]);app(MarketingProductionProcessor::class)->process($t->id);
        $this->assertSame('blocked',$t->fresh()->status);$this->assertDatabaseCount('marketing_production_steps',0);Http::assertNothingSent();
    }
    public function test_unknown_step_is_not_reclaimed_or_automatically_rendered_again():void
    {
        [$p,$r]=$this->dynamicFixture();[$plan,$t]=$this->dynamicStart($p,$r);$node=collect($plan->manifest['strategy']['nodes'])->firstWhere('kind','compose');
        MarketingProductionStep::create(['production_id'=>$t->id,'step_key'=>$node['key'],'kind'=>'compose','input_signature'=>hash('sha256',json_encode([$plan->fingerprint,$node],JSON_THROW_ON_ERROR)),'depends_on'=>$node['depends_on'],'status'=>'unknown','attempts'=>1]);
        $this->mock(\App\Services\Marketing\MarketingVideoRenderer::class)->shouldNotReceive('render');$processor=app(MarketingProductionProcessor::class);$processor->process($t->id);$processor->process($t->id);
        $this->assertSame('unknown',$t->fresh()->status);$this->assertSame(1,MarketingProductionStep::where('kind','compose')->sole()->attempts);Http::assertNothingSent();
    }
    public function test_interrupted_worker_marks_its_claimed_local_step_unknown():void
    {
        [$p,$r]=$this->dynamicFixture();[$plan,$t]=$this->dynamicStart($p,$r);$s=MarketingProductionStep::create(['production_id'=>$t->id,'step_key'=>'compose','kind'=>'compose','input_signature'=>str_repeat('a',64),'depends_on'=>[],'status'=>'running']);
        app(MarketingProductionProcessor::class)->interrupted($t->id);$this->assertSame('unknown',$t->fresh()->status);$this->assertSame('unknown',$s->fresh()->status);Http::assertNothingSent();
    }
}
