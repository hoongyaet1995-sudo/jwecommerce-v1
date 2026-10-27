<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingDynamicFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\{AiConnection,MarketingAiRun};
use App\Services\Marketing\{MarketingWorkflow,MarketingToolPlanning};
class MarketingToolRoutingLiveTest extends TestCase
{
 use RefreshDatabase,MarketingDynamicFixtures;
 private function setupGrant():array {
  [$p,$r]=$this->dynamicFixture();AiConnection::create(['provider'=>'openai','enabled'=>true,'verified_at'=>now(),'text_model'=>'gpt-5.6-luna','api_key'=>'fixture-only']);
  config(['marketing.planning.live_text_enabled'=>true,'marketing.planning.text_revision'=>$r->id,'marketing.creative.enabled'=>true,'marketing.creative.project'=>$p->id,'marketing.creative.budget_usd'=>'0.04','marketing.creative.max_calls'=>1,'marketing.creative.authorization'=>(string)Str::uuid()]);
  $research=['digest'=>'saved-fixture','sources'=>[['id'=>'one','bounded_excerpt'=>'Confirmed reference'],['id'=>'two','bounded_excerpt'=>'Another reference']]];$concept=$p->concepts;$concept[0]+=['recommended'=>true,'hook'=>'Confirmed context','story'=>'Explain real uses','materials'=>'Existing graphics','duration'=>15,'reason'=>'Fits facts','_research'=>['digest'=>'saved-fixture']];$p->update(['concepts'=>$concept]);$context=$r->context;$context['concept']=$concept[0];$context['research']=$research;$r->update(['context'=>$context]);$this->partialMock(\App\Services\Marketing\MarketingCreativePlanning::class)->shouldReceive('research')->andReturn($research);$p=$p->fresh();
  $d=$r->document;unset($d['gaps']);$d['requirements']=[];$d['changes']=['Reviewed production tools without changing the story.'];$d['production_strategy']=app(MarketingToolPlanning::class)->fromSaved($p,$r->document);
  return [$p,$r,$d];
 }
 public function test_one_normal_request_saves_ai_strategy_and_preserves_story_brand_and_history():void {
  [$p,$r,$d]=$this->setupGrant();$ctx=$r->context;$ctx['presentation']['style']='immersive-v1';$r->update(['context'=>$ctx]);Http::fake(['api.openai.com/v1/responses'=>Http::response(['status'=>'completed','usage'=>['input_tokens'=>100,'output_tokens'=>200],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($d)]]]]])]);
  $request=(string)Str::uuid();$workflow=app(MarketingWorkflow::class);$workflow->generate($p->id,$p->owner_id,$p->version,'revise','Review tools only.',$request);
  $new=$p->fresh()->currentRevision;$this->assertNotSame($r->id,$new->id);$this->assertSame($r->document['scenes'],$new->document['scenes']);$this->assertSame($r->context['presentation']['brand'],$new->context['presentation']['brand']);$this->assertSame('immersive-v1',$new->context['presentation']['style']??null);$this->assertSame('ai',$new->context['production_strategy']['origin']);$this->assertNull($new->confirmed_at);
  $workflow->generate($p->id,$p->owner_id,$p->version,'revise','Review tools only.',$request);Http::assertSentCount(1);
  try{$workflow->generate($p->id,$p->owner_id,$p->fresh()->version,'revise','Again',(string)Str::uuid(),$r->id);$this->fail('No second allowance');}catch(\Illuminate\Validation\ValidationException){Http::assertSentCount(1);}
  $this->assertDatabaseCount('marketing_revisions',2);$this->assertDatabaseCount('marketing_productions',0);$this->get('/admin/marketing/projects/'.$p->id)->assertOk()->assertSee('AI suggested approach:');
 }
 public function test_story_changes_are_rejected_without_replacing_saved_revision_or_retrying():void {
  [$p,$r,$d]=$this->setupGrant();$d['scenes'][0]['subtitle']='Invented new story';Http::fake(['api.openai.com/v1/responses'=>Http::response(['status'=>'completed','usage'=>['input_tokens'=>100,'output_tokens'=>200],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($d)]]]]])]);
  try{app(MarketingWorkflow::class)->generate($p->id,$p->owner_id,$p->version,'revise','Review tools only.',(string)Str::uuid());$this->fail('Changed story must fail this grant');}catch(\Illuminate\Validation\ValidationException){$this->assertSame($r->id,$p->fresh()->current_revision_id);Http::assertSentCount(1);$this->assertSame(200,MarketingAiRun::sole()->usage['output_tokens']);}
 }
 public function test_wrong_source_revision_blocks_before_paid_http():void {
  [$p,$r]=$this->setupGrant();config(['marketing.planning.text_revision'=>(string)Str::uuid()]);
  try{app(MarketingWorkflow::class)->generate($p->id,$p->owner_id,$p->version,'revise','Review tools only.',(string)Str::uuid());$this->fail('Wrong source');}catch(\Illuminate\Validation\ValidationException){Http::assertNothingSent();$this->assertSame(0.0,(float)MarketingAiRun::sole()->reserved_usd);}
 }
}
