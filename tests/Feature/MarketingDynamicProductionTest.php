<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingDynamicFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage};
use App\Services\Marketing\MarketingProductionProcessor;
class MarketingDynamicProductionTest extends TestCase
{
    use RefreshDatabase,MarketingDynamicFixtures;
    public function test_explicit_saved_presentation_reaches_frozen_plan_without_changing_story():void
    {
        [$p,$r]=$this->dynamicFixture();$context=$r->context;$context['presentation']['style']='immersive-v1';$r->update(['context'=>$context]);
        $manifest=app(\App\Services\Marketing\MarketingToolPlanning::class)->compile($p,$r);
        $this->assertSame('immersive-v1',$manifest['presentation']['style']??null);
        $this->assertSame($r->document['scenes'][0]['subtitle'],$manifest['scenes'][0]['subtitle']);
        $this->assertSame([],$manifest['blockers']);Http::assertNothingSent();
    }
    public function test_unknown_presentation_blocks_preflight_instead_of_silently_using_a_default():void
    {
        [$p,$r]=$this->dynamicFixture();$context=$r->context;$context['presentation']['style']='unsupported';$r->update(['context'=>$context]);
        $manifest=app(\App\Services\Marketing\MarketingToolPlanning::class)->compile($p,$r);
        $this->assertContains('The saved visual treatment is unsupported; no substitute was applied.',$manifest['blockers']);Http::assertNothingSent();
    }
    public function test_dynamic_local_plan_executes_serial_steps_and_preserves_delivery_gate():void
    {
        [$p,$r]=$this->dynamicFixture();[$plan,$t]=$this->dynamicStart($p,$r);$this->fakeDynamicRenderer();
        $processor=app(MarketingProductionProcessor::class);$processor->process($t->id);$processor->process($t->id);
        $this->assertSame('completed',$t->fresh()->status);$this->assertDatabaseCount('marketing_production_steps',4);$this->assertSame(4,\App\Models\MarketingProductionStep::where('status','completed')->count());
        $input=json_decode(Storage::disk('local')->get('marketing-productions/'.$t->id.'/input.json'),true);$this->assertArrayNotHasKey('visual',$input['manifest']['scenes'][0]);$this->assertSame('Confirmed use 1',$input['manifest']['scenes'][0]['subtitle']);
        $url='/admin/marketing/projects/'.$p->id.'/productions/'.$t->id;$this->get($url.'/download')->assertForbidden();$this->post($url.'/approve',['output_sha256'=>$t->fresh()->output_sha256,'final_confirmation'=>1])->assertRedirect();$this->get($url.'/download')->assertOk();
        Http::assertNothingSent();$this->assertDatabaseCount('marketing_voice_assets',0);
    }
    public function test_failed_local_composition_can_retry_without_repeating_completed_media_steps():void
    {
        [$p,$r]=$this->dynamicFixture();[$plan,$t]=$this->dynamicStart($p,$r);$this->fakeDynamicRenderer(true);$processor=app(MarketingProductionProcessor::class);$processor->process($t->id);$this->assertSame('failed',$t->fresh()->status);
        $reuse=\App\Models\MarketingProductionStep::where('kind','reuse_media')->pluck('id')->all();
        $this->post('/admin/marketing/projects/'.$p->id.'/production/'.$plan->id.'/start',['version'=>$p->version])->assertRedirect();$processor->process($t->id);
        $this->assertSame('completed',$t->fresh()->status);$this->assertSame($reuse,\App\Models\MarketingProductionStep::where('kind','reuse_media')->pluck('id')->all());$this->assertSame(2,\App\Models\MarketingProductionStep::where('kind','compose')->sole()->attempts);Http::assertNothingSent();
    }
    public function test_existing_prepare_action_creates_a_free_unconfirmed_dynamic_draft_once():void
    {
        [$p,$r]=$this->dynamicFixture();$context=$r->context;unset($context['production_strategy']);$context['production_recipe']='existing-images-voice-v1';$r->update(['context'=>$context]);
        $url='/admin/marketing/projects/'.$p->id.'/production';$data=['version'=>$p->version,'revision_id'=>$r->id,'request_id'=>(string)\Illuminate\Support\Str::uuid()];
        $this->post($url,$data)->assertRedirect('/admin/marketing/projects/'.$p->id);$new=$p->fresh()->currentRevision;
        $this->assertNotSame($r->id,$new->id);$this->assertSame($r->document,$new->document);$this->assertSame($context['presentation']['brand'],$new->context['presentation']['brand']);$this->assertNull($new->confirmed_at);
        $this->post($url,$data)->assertRedirect();$this->assertDatabaseCount('marketing_revisions',2);$this->assertDatabaseCount('marketing_ai_runs',0);$this->assertDatabaseCount('marketing_production_plans',0);
        $p->refresh();$this->post('/admin/marketing/projects/'.$p->id.'/confirm',['version'=>$p->version,'revision_id'=>$new->id,'content_confirmation'=>1])->assertRedirect();
        Http::assertNothingSent();
    }
}
