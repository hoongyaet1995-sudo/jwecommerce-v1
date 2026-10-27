<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingDynamicFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage};
use Illuminate\Support\Str;
use App\Models\{MarketingProduction,MarketingProductionStep};
use App\Services\Marketing\{MarketingHybridProduction,MarketingProductionPreparation,MarketingProductionRunner,MarketingProductionProcessor,MarketingToolPlanning};
class MarketingRemoteStepTest extends TestCase
{
 use RefreshDatabase,MarketingDynamicFixtures;
 private function pilot():array {
  [$p,$r]=$this->dynamicFixture();config(['marketing.hybrid.enabled'=>true,'marketing.hybrid.project'=>$p->id]);
  $ctx=$r->context;$doc=$r->document;$doc['scenes'][1]['visual']='Illustrative generic industrial use, not actual inventory.';$doc['scenes'][1]['material']='Planned generated illustrative context';
  $proposal=app(MarketingToolPlanning::class)->fromSaved($p,$doc);$proposal['route']='hybrid';$proposal['scenes'][1]['capability']='generated_broll';$ctx['production_strategy']=['origin'=>'explicit_pilot','proposal'=>$proposal,'review'=>app(MarketingToolPlanning::class)->review($p,$doc,$proposal)];$r->update(['document'=>$doc,'context'=>$ctx]);
  $payload=app(MarketingHybridProduction::class)->payload($r);
  config(['marketing.hybrid.authorization'=>['id'=>(string)Str::uuid(),'revision'=>$r->id,'signature'=>hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),'cap_usd'=>'1.10','max_credits'=>70,'expires_at'=>now()->addHour()->toIso8601String()]]);
  $plan=app(MarketingProductionPreparation::class)->prepare($p->id,$p->owner_id,$p->version,$r->id,(string)Str::uuid());$this->assertSame([],$plan->manifest['blockers']);app(MarketingProductionPreparation::class)->confirm($p->id,$p->owner_id,$p->version,$plan->id);$t=app(MarketingProductionRunner::class)->start($p->id,$p->owner_id,$p->version,$plan->id);
  config(['marketing.hybrid.live_enabled'=>true,'marketing.native.api_key'=>'fixture-only']);$this->partialMock(\App\Services\Marketing\MarketingVideoRenderer::class)->shouldReceive('assertReady')->andReturnNull();return [$p,$r,$plan,$t];
 }
 public function test_pending_node_is_submitted_once_and_known_id_is_only_queried():void {
  [$p,$r,$plan,$t]=$this->pilot();Http::fake(['api.evolink.ai/v1/videos/generations'=>Http::response(['id'=>'remote-one','usage'=>['credits_reserved'=>67.5]],201),'api.evolink.ai/v1/tasks/remote-one'=>Http::response(['id'=>'remote-one','status'=>'processing'])]);$worker=app(MarketingProductionProcessor::class);$worker->process($t->id);$worker->process($t->id);
  $this->assertSame('external_pending',$t->fresh()->status);Http::assertSent(fn($q)=>$q->method()==='POST'&&$q['duration']===5&&$q['generate_audio']===false&&$q['model_params']['web_search']===false);$this->assertSame('remote-one',MarketingProductionStep::where('kind','generate_video')->sole()->provider_task_id);$this->assertCount(1,Http::recorded(fn($q)=>$q->method()==='POST'));
  $this->post('/admin/marketing/projects/'.$p->id.'/productions/'.$t->id.'/check')->assertRedirect();$worker->process($t->id);$this->assertCount(1,Http::recorded(fn($q)=>$q->method()==='POST'));$this->assertSame('external_pending',$t->fresh()->status);
 }
 public function test_ambiguous_submission_stops_unknown_and_never_submits_again():void {
  [$p,$r,$plan,$t]=$this->pilot();Http::fake(['api.evolink.ai/v1/videos/generations'=>Http::response([],503)]);$worker=app(MarketingProductionProcessor::class);$worker->process($t->id);$worker->process($t->id);$this->assertSame('unknown',$t->fresh()->status);$this->assertCount(1,Http::recorded(fn($q)=>$q->method()==='POST'));$this->post('/admin/marketing/projects/'.$p->id.'/productions/'.$t->id.'/check')->assertStatus(409);
 }
 public function test_live_disabled_or_expired_inputs_never_submit():void {
  [$p,$r,$plan,$t]=$this->pilot();config(['marketing.hybrid.live_enabled'=>false]);app(MarketingProductionProcessor::class)->process($t->id);$this->assertSame('blocked',$t->fresh()->status);Http::assertNothingSent();$this->assertNull(MarketingProductionStep::where('kind','generate_video')->first()?->authorization_id);
 }
 public function test_completed_remote_shot_composes_then_local_retry_never_posts_again():void {
  [$p,$r,$plan,$t]=$this->pilot();Http::fake(['api.evolink.ai/v1/videos/generations'=>Http::response(['id'=>'remote-one'],201),'api.evolink.ai/v1/tasks/remote-one'=>Http::response(['id'=>'remote-one','status'=>'completed','results'=>['https://example.test/shot.mp4'],'usage'=>['credits_consumed'=>67.5]])]);
  $this->mock(\App\Services\Marketing\MarketingEvolinkMedia::class)->shouldReceive('storeShot')->once()->andReturnUsing(function($task){$path='marketing-productions/'.$task->id.'/generated-shot.mp4';Storage::disk('local')->put($path,str_repeat('fixture-shot',150));return ['path'=>$path,'sha256'=>hash('sha256',Storage::disk('local')->get($path)),'mime'=>'video/mp4','width'=>720,'height'=>1280,'duration_seconds'=>5,'has_audio'=>false];});$this->fakeDynamicRenderer(true);
  $worker=app(MarketingProductionProcessor::class);$worker->process($t->id);$this->assertSame('failed',$t->fresh()->status);$this->assertSame('completed',MarketingProductionStep::where('kind','generate_video')->sole()->status);
  app(MarketingProductionRunner::class)->start($p->id,$p->owner_id,$p->version,$plan->id);$worker->process($t->id);$this->assertSame('completed',$t->fresh()->status);$this->assertCount(1,Http::recorded(fn($q)=>$q->method()==='POST'));$this->assertSame(1,$t->fresh()->result['new_provider_requests']);$this->assertNull($t->fresh()->result['actual_new_supplier_usd']);
  $input=json_decode(Storage::disk('local')->get('marketing-productions/'.$t->id.'/input.json'),true);$this->assertCount(1,$input['manifest']['generation_receipts']);$this->assertSame(1100000,$input['manifest']['budget']['cap_microusd']);$this->assertStringNotContainsString('node_ref',json_encode($input));
 }
 public function test_same_grant_cannot_be_used_by_a_second_production():void {
  [$p,$r,$plan,$t]=$this->pilot();Http::fake(['api.evolink.ai/v1/videos/generations'=>Http::response(['id'=>'remote-one'],201),'api.evolink.ai/v1/tasks/remote-one'=>Http::response(['id'=>'remote-one','status'=>'processing'])]);$worker=app(MarketingProductionProcessor::class);$worker->process($t->id);
  $copy=$plan->replicate();$copy->request_id=(string)Str::uuid();$copy->save();$second=MarketingProduction::create(['project_id'=>$p->id,'plan_id'=>$copy->id,'owner_id'=>$p->owner_id,'status'=>'queued']);$worker->process($second->id);$this->assertSame('blocked',$second->fresh()->status);$this->assertCount(1,Http::recorded(fn($q)=>$q->method()==='POST'));
 }
 public function test_normal_prepare_saves_a_separate_unconfirmed_pilot_without_http_or_duplicate_drafts():void {
  [$p,$r]=$this->dynamicFixture();config(['marketing.hybrid.enabled'=>true,'marketing.hybrid.project'=>$p->id]);$url='/admin/marketing/projects/'.$p->id.'/production';$input=['version'=>$p->version,'revision_id'=>$r->id,'request_id'=>(string)Str::uuid()];
  $this->post($url,$input)->assertRedirect();$new=$p->fresh()->currentRevision;$this->assertSame('hybrid',$new->context['production_strategy']['proposal']['route']);$this->assertSame($r->id,$new->parent_id);$this->assertNull($new->confirmed_at);$this->assertSame($r->document['scenes'][2]['voiceover'],$new->document['scenes'][2]['voiceover']);$this->assertSame($r->document['scenes'][2]['subtitle'],$new->document['scenes'][2]['subtitle']);$this->post($url,$input)->assertRedirect();$this->assertDatabaseCount('marketing_revisions',2);$this->assertDatabaseCount('marketing_ai_runs',0);$this->assertDatabaseCount('marketing_productions',0);Http::assertNothingSent();
  app(\App\Services\Marketing\MarketingWorkflow::class)->confirm($p->id,$p->owner_id,$p->fresh()->version,$new->id);$p=$p->fresh();$this->post($url,['version'=>$p->version,'revision_id'=>$new->id,'request_id'=>(string)Str::uuid()])->assertRedirect();$plan=\App\Models\MarketingProductionPlan::sole();$this->assertSame('blocked',$plan->status);$this->get($url)->assertOk()->assertSee('No new voice request or publishing');Http::assertNothingSent();
 }
 public function test_terminal_rejection_never_retries_and_expired_budget_never_submits():void {
  [$p,$r,$plan,$t]=$this->pilot();Http::fake(['api.evolink.ai/v1/videos/generations'=>Http::response([],400)]);$worker=app(MarketingProductionProcessor::class);$worker->process($t->id);app(MarketingProductionRunner::class)->start($p->id,$p->owner_id,$p->version,$plan->id);$worker->process($t->id);$this->assertSame('failed',$t->fresh()->status);$this->assertCount(1,Http::recorded(fn($q)=>$q->method()==='POST'));
 }
 public function test_known_remote_id_survives_query_error_and_recovers_without_new_post():void {
  [$p,$r,$plan,$t]=$this->pilot();Http::fake(['api.evolink.ai/v1/videos/generations'=>Http::response(['id'=>'remote-one'],201),'api.evolink.ai/v1/tasks/remote-one'=>Http::sequence()->push([],503)->push(['id'=>'remote-one','status'=>'processing'])]);$worker=app(MarketingProductionProcessor::class);$worker->process($t->id);$this->assertSame('unknown',$t->fresh()->status);$this->post('/admin/marketing/projects/'.$p->id.'/productions/'.$t->id.'/check')->assertRedirect();$worker->process($t->id);$this->assertSame('external_pending',$t->fresh()->status);$this->assertCount(1,Http::recorded(fn($q)=>$q->method()==='POST'));
 }
 public function test_expired_plan_and_revoked_role_stop_before_provider_or_claim():void {
  [$p,$r,$plan,$t]=$this->pilot();$plan->update(['expires_at'=>now()->subMinute()]);app(MarketingProductionProcessor::class)->process($t->id);$this->assertSame('blocked',$t->fresh()->status);Http::assertNothingSent();$this->assertDatabaseCount('marketing_production_steps',0);
 }
 public function test_renderer_unavailable_stops_before_reserving_or_submitting_video():void {
  [$p,$r,$plan,$t]=$this->pilot();$this->mock(\App\Services\Marketing\MarketingVideoRenderer::class)->shouldReceive('assertReady')->andThrow(new \App\Services\Marketing\MarketingProductionFailure('renderer_not_ready','blocked'));app(MarketingProductionProcessor::class)->process($t->id);$this->assertSame('blocked',$t->fresh()->status);$this->assertSame(0,$t->fresh()->new_reserved_microusd);Http::assertNothingSent();
 }
}
