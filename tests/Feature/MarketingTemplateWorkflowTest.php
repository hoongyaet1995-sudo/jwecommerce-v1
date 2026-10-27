<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use App\Models\{User,Product,ProductImage,MarketingProject,MarketingTemplateGrant,MarketingTemplateRun,MarketingTemplateCall};
use App\Services\Marketing\{MarketingTemplateProjects,MarketingTemplateWorkflow,MarketingTemplateContract,MarketingTemplateProvider};
class MarketingTemplateWorkflowTest extends TestCase {
 use RefreshDatabase;
 private function setupProject():MarketingProject{
  $this->mock(\App\Services\Marketing\MarketingTemplateRuntime::class,fn($m)=>$m->shouldReceive('ready')->andReturn(true));
  Queue::fake();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);$p=Product::create(['title'=>'Tea','handle'=>'test-tea','base_price'=>1,'status'=>'active']);ProductImage::create(['product_id'=>$p->id,'path'=>'/images/themes/food/tea.png','position'=>0]);
  [$project]=app(MarketingTemplateProjects::class)->create($u,['request_id'=>(string)Str::uuid(),'product_id'=>$p->id,'template_id'=>'product_showcase_15s','language'=>'English','formats'=>['landscape','portrait'],'narration'=>true,'subtitles'=>true,'goal'=>'introduction','duration'=>'15']);
  MarketingTemplateGrant::create(['owner_id'=>$u->id,'product_id'=>$p->id,'cap_microusd'=>1000000,'max_llm'=>8,'max_tts'=>4,'active'=>true,'policy'=>[],'pricing'=>[],'expires_at'=>now()->addDay()]);return $project;
 }
 public function test_queued_plan_locks_saves_and_refresh_does_not_dispatch_then_consent_is_idempotent():void{
  $p=$this->setupProject();$this->mock(MarketingTemplateContract::class,fn($m)=>$m->shouldReceive('call')->with('packet',\Mockery::any())->andReturn(['language'=>'en']));
  $d=['version'=>$p->version,'request_id'=>(string)Str::uuid()];$url='/admin/marketing/templates/projects/'.$p->id;
  $run=$this->postJson($url.'/plan',$d)->assertOk()->json('run.id');$this->postJson($url.'/plan',$d)->assertOk()->assertJsonPath('run.id',$run);$this->getJson($url)->assertOk();Queue::assertPushed(\App\Jobs\MarketingTemplateJob::class,1);
  $this->patchJson($url.'/brief',['version'=>$p->version,'goal'=>'details'])->assertStatus(409);
  $r=MarketingTemplateRun::find($run);$r->update(['status'=>'planned','draft'=>['test'=>true],'quote'=>['digest'=>str_repeat('a',64),'expires_at'=>now()->addHour()->toIso8601String()]]);
  $this->postJson($url.'/plan',['version'=>$p->version,'request_id'=>(string)Str::uuid()])->assertStatus(409);
  $data=['run_id'=>$run,'version'=>$p->version,'quote_digest'=>str_repeat('b',64),'consent'=>true];$this->postJson($url.'/produce',$data)->assertStatus(409);$data['quote_digest']=str_repeat('a',64);$this->postJson($url.'/produce',$data)->assertOk();$this->postJson($url.'/produce',$data)->assertOk();Queue::assertPushed(\App\Jobs\MarketingTemplateJob::class,2);$this->assertDatabaseCount('marketing_template_calls',0);
 }
 public function test_project_view_uses_current_model_and_budget_without_repricing_old_calls():void{
  $p=$this->setupProject();$g=MarketingTemplateGrant::firstOrFail();$g->update(['cap_microusd'=>10000,'max_llm'=>4]);
  $r=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('a',64),'input'=>[],'status'=>'paused']);
  MarketingTemplateCall::create(['id'=>hash('sha256','old-charge'),'run_id'=>$r->id,'grant_id'=>$g->id,'stage'=>'creative-0','kind'=>'llm','payload_hash'=>str_repeat('b',64),'client_request_id'=>(string)Str::uuid(),'status'=>'completed','reserved_microusd'=>31500,'actual_microusd'=>2000,'receipt'=>['data'=>['model'=>'gpt-5.4-mini-2026-03-17']]]);
  $v=app(MarketingTemplateProjects::class)->view($p);$this->assertSame('gpt-6-luna',$v['grant']['model']);$this->assertSame(14800,$v['grant']['planning_cap_microusd']);$this->assertTrue($v['grant']['available']);$this->assertSame(2000,$v['grant']['actual_microusd']);$this->assertSame(8000,$v['grant']['remaining_microusd']);$this->assertDatabaseCount('marketing_template_calls',1);$g->update(['expires_at'=>now()->subMinute()]);$expired=app(MarketingTemplateProjects::class)->view($p);$this->assertFalse($expired['grant']['available']);$this->assertSame(2000,$expired['grant']['actual_microusd']??null);
 }
 public function test_missing_runtime_pauses_before_any_paid_provider_request():void{
  $p=$this->setupProject();$this->instance(\App\Services\Marketing\MarketingTemplateRuntime::class,\Mockery::mock(\App\Services\Marketing\MarketingTemplateRuntime::class,function($m){$m->shouldReceive('ready')->once()->andReturn(false);}));$this->mock(MarketingTemplateProvider::class,function($m){$m->shouldNotReceive('llm');$m->shouldNotReceive('tts');});$i=['project_version'=>$p->version];$r=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>MarketingTemplateGrant::firstOrFail()->id,'request_id'=>(string)Str::uuid(),'input'=>$i,'input_digest'=>MarketingTemplateProjects::hash($i),'status'=>'queued']);app(MarketingTemplateWorkflow::class)->execute($r->id,'plan');$this->assertSame('template_runtime_not_ready',$r->fresh()->error['code']);$this->assertSame('paused',$r->fresh()->status);$this->assertDatabaseCount('marketing_template_calls',0);
 }
 public function test_expired_quote_can_be_refreshed_without_replanning_or_new_provider_calls():void{
  $p=$this->setupProject();$g=MarketingTemplateGrant::firstOrFail();$draft=['copy'=>['hook'=>'Saved']];$input=['project_version'=>$p->version];
  $r=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input'=>$input,'input_digest'=>MarketingTemplateProjects::hash($input),'draft'=>$draft,'status'=>'planned','checks'=>['status'=>'pass'],'quote'=>['draft_hash'=>MarketingTemplateProjects::hash($draft),'expires_at'=>now()->subMinute()->toIso8601String(),'digest'=>str_repeat('b',64)]]);
  $this->mock(\App\Services\Marketing\MarketingProductTemplateRenderer::class,fn($m)=>$m->shouldReceive('validateDraft')->once());
  $this->postJson('/admin/marketing/templates/projects/'.$p->id.'/refresh-quote',['version'=>$p->version,'run_id'=>$r->id])->assertOk()->assertJsonPath('run.status','planned')->assertJsonPath('run.quote.model','gpt-6-luna');$this->assertSame($draft,$r->fresh()->draft);$this->assertDatabaseCount('marketing_template_calls',0);Queue::assertNothingPushed();
 }
 public function test_latest_failed_revision_exposes_previous_successful_film_without_private_paths():void{
  $p=$this->setupProject();$g=MarketingTemplateGrant::firstOrFail();
  $old=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('a',64),'input'=>['project_version'=>1,'packet'=>['language'=>'zh'],'preferences'=>['formats'=>['portrait'],'narration'=>true,'subtitles'=>true]],'status'=>'completed','result'=>['formats'=>['portrait'=>['width'=>1080,'height'=>1920,'seconds'=>15,'frames'=>360,'audio'=>true,'path'=>'private.mp4']]],'created_at'=>now()->subMinute()]);
  $new=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('b',64),'input'=>[],'status'=>'paused']);
  $v=app(MarketingTemplateProjects::class)->view($p);$this->assertSame($new->id,$v['run']['id']);$this->assertSame($old->id,$v['last_successful']['id']??null);$this->assertSame('zh-MY',$v['last_successful']['preferences']['language']);$this->assertArrayNotHasKey('path',$v['last_successful']['result']['formats']['portrait']);
 }
 public function test_saved_voice_is_reused_and_task_uses_persisted_timestamp_representation():void{
  $p=$this->setupProject();$this->mock(MarketingTemplateContract::class,fn($m)=>$m->shouldReceive('call')->with('packet',\Mockery::any())->andReturn(['language'=>'en']));$r=app(MarketingTemplateWorkflow::class)->start($p,['version'=>$p->version,'request_id'=>(string)Str::uuid()]);
  $r->update(['status'=>'queued_production','draft'=>['copy'=>['hook'=>'Tea']],'result'=>['voice'=>['path'=>'saved.mp3','sha256'=>str_repeat('a',64),'alignment'=>['character_start_times_seconds'=>[0.0,.1]]]]]);
  $this->mock(MarketingTemplateProvider::class,fn($m)=>$m->shouldNotReceive('tts'));
  $this->mock(\App\Services\Marketing\MarketingProductTemplateRenderer::class,function($m)use($r){$m->shouldReceive('validateDraft')->once();$m->shouldReceive('execute')->once()->andReturnUsing(function($task)use($r){$this->assertSame($r->fresh()->result['voice'],$task['voice']);throw new \RuntimeException('local_test_stop');});});
  app(MarketingTemplateWorkflow::class)->execute($r->id,'produce');$this->assertDatabaseCount('marketing_template_calls',0);$this->assertSame('paused',$r->fresh()->status);
 }
 public function test_no_automatic_correction_policy_stops_after_the_first_failed_check():void{
  $p=$this->setupProject();MarketingTemplateGrant::firstOrFail()->update(['policy'=>['automatic_corrections'=>false]]);
  $this->mock(MarketingTemplateContract::class,function($m){$m->shouldReceive('call')->andReturnUsing(fn($mode,$a)=>match($mode){'packet'=>['language'=>'en'],'creative','checker'=>['model'=>'fixed','input'=>[]],'validate'=>['status'=>'awaiting_semantic_check'],'check_result'=>['status'=>'fail','issues'=>[['field'=>'copy','reason'=>'unsupported claim']]],default=>throw new \RuntimeException('unexpected')});});
  $this->mock(\App\Services\Marketing\MarketingProductTemplateRenderer::class,fn($m)=>$m->shouldReceive('validateDraft')->once());
  $provider=$this->mock(MarketingTemplateProvider::class);$provider->shouldReceive('llm')->twice()->andReturn(['actual_microusd'=>100,'data'=>['id'=>'fake','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['decision'=>'ready','draft'=>['copy'=>['hook'=>'Tea']]])]]]]]]);$provider->shouldNotReceive('tts');
  $r=app(MarketingTemplateWorkflow::class)->start($p,['version'=>$p->version,'request_id'=>(string)Str::uuid()]);app(MarketingTemplateWorkflow::class)->execute($r->id,'plan');$r->refresh();$this->assertSame('paused',$r->status);$this->assertSame(0,$r->corrections);$this->assertDatabaseCount('marketing_template_calls',2);$this->assertSame('semantic_check_rejected',$r->error['code']);
 }
 public function test_needs_input_preserves_original_reasons_and_stops_without_checker_or_retry():void{
  $p=$this->setupProject();$response=['decision'=>'needs_input','reasons'=>['No verified performance facts.'],'draft'=>null];
  $this->mock(MarketingTemplateContract::class,function($m)use($response){$m->shouldReceive('call')->andReturnUsing(fn($mode,$a)=>match($mode){'packet'=>['language'=>'en'],'creative'=>['input'=>[]],'validate'=>['status'=>'needs_input','reasons'=>$response['reasons']],default=>throw new \RuntimeException('no extra work')});});
  $provider=$this->mock(MarketingTemplateProvider::class);$provider->shouldReceive('llm')->once()->andReturn(['actual_microusd'=>100,'data'=>['id'=>'fake-needs-input','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($response)]]]]]]);$provider->shouldNotReceive('tts');
  $r=app(MarketingTemplateWorkflow::class)->start($p,['version'=>$p->version,'request_id'=>(string)Str::uuid()]);app(MarketingTemplateWorkflow::class)->execute($r->id,'plan');$r->refresh();$this->assertSame('paused',$r->status);$this->assertSame('product_information_required',$r->error['code']);$this->assertSame($response['reasons'],$r->checks['reasons']);$this->assertSame($response,$r->result['planning_response']);$this->assertDatabaseCount('marketing_template_calls',1);
 }
 public function test_explicit_clarification_reuses_grant_and_reads_generic_sample_metadata():void{
  $p=$this->setupProject();$g=MarketingTemplateGrant::firstOrFail();$g->update(['policy'=>['automatic_corrections'=>false]]);
  $product=Product::findOrFail($p->product_id);$product->update(['description'=>'TEST DATA ONLY: illustrative specifications, not measured.','features'=>['Illustrative feature A'],'tags'=>['marketing_test_data']]);
  $this->mock(MarketingTemplateContract::class,function($m){$m->shouldReceive('call')->with('packet',\Mockery::on(function($a){$this->assertStringContainsString('Illustrative feature A',json_encode($a['product']['facts']));return true;}))->once()->andReturn(['language'=>'en']);});
  $old=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('a',64),'input'=>['project_version'=>$p->version],'status'=>'paused','checks'=>['status'=>'needs_input'],'result'=>['planning_response'=>['decision'=>'needs_input']]]);
  $this->postJson('/admin/marketing/templates/projects/'.$p->id.'/revise',['version'=>$p->version,'run_id'=>$old->id,'request_id'=>(string)Str::uuid(),'request'=>'Use the newly supplied illustrative test data.'])->assertOk()->assertJsonPath('run.status','queued');
  $new=MarketingTemplateRun::where('id','!=',$old->id)->firstOrFail();$this->assertSame($g->id,$new->grant_id);$this->assertSame('test_sample',$new->input['packet']['catalog_data_kind']);$this->assertSame('paused',$old->fresh()->status);$this->assertDatabaseCount('marketing_template_grants',1);$this->assertDatabaseCount('marketing_template_calls',0);Queue::assertPushed(\App\Jobs\MarketingTemplateJob::class,1);
 }
 public function test_explicit_server_authorized_revision_preserves_shared_cap_and_old_run():void{
  $p=$this->setupProject();$g=MarketingTemplateGrant::firstOrFail();$g->update(['policy'=>['automatic_corrections'=>false,'max_corrections_per_language'=>2,'corrections'=>['en'=>1]]]);
  $this->mock(MarketingTemplateContract::class,fn($m)=>$m->shouldReceive('call')->with('packet',\Mockery::any())->once()->andReturn(['language'=>'en']));
  $old=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('a',64),'input'=>[],'draft'=>['retained'=>true],'status'=>'paused']);
  $data=['version'=>$p->version,'run_id'=>$old->id,'request_id'=>(string)Str::uuid(),'request'=>'Explicit bounded repair'];$url='/admin/marketing/templates/projects/'.$p->id.'/revise';
  $this->postJson($url,$data)->assertOk()->assertJsonPath('run.status','queued');$this->postJson($url,$data)->assertOk();
  $this->assertSame(1000000,$g->fresh()->cap_microusd);$this->assertSame(2,$g->fresh()->policy['corrections']['en']);$this->assertSame(['retained'=>true],$old->fresh()->draft);$this->assertDatabaseCount('marketing_template_calls',0);Queue::assertPushed(\App\Jobs\MarketingTemplateJob::class,1);
 }
 public function test_fake_provider_plan_is_independently_checked_and_quoted_without_tts():void{
  $p=$this->setupProject();$this->mock(\App\Services\Marketing\MarketingProductTemplateRenderer::class,fn($m)=>$m->shouldReceive('validateDraft')->once());$this->mock(MarketingTemplateContract::class,function($m){$m->shouldReceive('call')->andReturnUsing(fn($mode,$a)=>match($mode){'packet'=>['language'=>'en'],'creative','checker'=>['model'=>'fixed','input'=>[]],'validate'=>['status'=>'awaiting_semantic_check'],'check_result'=>['status'=>'pass'],default=>throw new \RuntimeException('unexpected')});});
  $provider=$this->mock(MarketingTemplateProvider::class);$provider->shouldReceive('llm')->twice()->andReturn(['actual_microusd'=>100,'data'=>['id'=>'fake-1','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['decision'=>'ready','draft'=>['copy'=>['hook'=>'Tea']]])]]]]]],['actual_microusd'=>100,'data'=>['id'=>'fake-2','output'=>[['content'=>[['type'=>'output_text','text'=>'{}']]]]]]);$provider->shouldNotReceive('tts');
  $r=app(MarketingTemplateWorkflow::class)->start($p,['version'=>$p->version,'request_id'=>(string)Str::uuid()]);app(MarketingTemplateWorkflow::class)->execute($r->id,'plan');$r->refresh();$this->assertSame('planned',$r->status);$this->assertSame(200,$r->quote['planning_actual_microusd']);$this->assertDatabaseCount('marketing_template_calls',2);
  app(MarketingTemplateWorkflow::class)->execute($r->id,'plan');$this->assertDatabaseCount('marketing_template_calls',2);
 }
}
