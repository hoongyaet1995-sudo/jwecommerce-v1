<?php
namespace Tests\Feature;
use App\Models\MarketingRevision;
use App\Services\Marketing\MarketingDirectorWorkflow;
use App\Services\Marketing\MarketingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;
use Illuminate\Support\Str;
class MarketingDirectorWorkflowTest extends TestCase
{
 use RefreshDatabase,MarketingResearchFixtures;
 private function source():array{
  $p=$this->researchProject();config(['marketing.conversation.enabled'=>true,'marketing.conversation.open_testing'=>true]);
  $concept=['title'=>'用途介绍','video_type'=>'presenter'];$p->update(['concepts'=>[$concept],'selected_concept'=>0,'planning_state'=>['status'=>'confirmed']]);
  $doc=['summary'=>'用途介绍','duration'=>15,'cta'=>'欢迎询价','scenes'=>[MarketingDirectorContractTest::facts()['scenes'][0]+['visual'=>'Presenter explains product','material'=>'Virtual presenter pending']],'gaps'=>[]];
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$p->brief,'concept'=>$concept,'snapshot'=>$p->snapshot,'production_recipe'=>'pending-tool-routing']]);$p->update(['current_revision_id'=>$r->id,'confirmed_revision_id'=>$r->id]);return [$p->fresh(),$r];
 }
 public function test_versioned_director_preserves_story_and_duplicate_does_not_call_twice():void{
  [$p,$r]=$this->source();$this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->withArgs(fn($facts,$action,$feedback)=>$action==='director'&&$facts['director_facts']['source_revision_id']===$r->id&&$facts['creative_tone']['tone']==='亲切专业')->andReturn(MarketingDirectorContractTest::directorOutput());
  $id=(string)Str::uuid();$flow=app(MarketingDirectorWorkflow::class);$new=$flow->draft($p->id,$p->owner_id,$p->version,$r->id,$id);
  $this->assertSame($r->document['scenes'],$new->document['scenes']);$this->assertSame($r->id,$new->document['director_plan']['source_revision_id']);$this->assertNull($p->fresh()->confirmed_revision_id);$this->assertNull($new->confirmed_at);
  $this->assertSame($new->id,$flow->draft($p->id,$p->owner_id,$p->version,$r->id,$id)->id);
 }
 public function test_invalid_response_preserves_saved_revision():void{
  [$p,$r]=$this->source();$bad=MarketingDirectorContractTest::directorOutput();$bad['shots'][0]['end']=14;
  $this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->andReturn($bad);
  try{app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version,$r->id,(string)Str::uuid());$this->fail('accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertSame($r->id,$p->fresh()->current_revision_id);$this->assertNull($p->fresh()->busy_run_id);$this->assertSame(1,$p->revisions()->count());}
 }
 public function test_workspace_director_action_and_review_do_not_approve_production():void{
  [$p,$r]=$this->source();$this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->andReturn(MarketingDirectorContractTest::directorOutput());
  $url='/admin/marketing/workspace/'.$p->id.'/creative';
  $this->post($url,['version'=>$p->version,'request_id'=>(string)Str::uuid(),'action'=>'director'])->assertRedirect();
  $p->refresh();$new=$p->currentRevision;$this->assertNotNull($new->document['director_plan']??null);
  $this->post($url,['version'=>$p->version,'request_id'=>(string)Str::uuid(),'action'=>'confirm_director'])->assertRedirect();
  $this->assertNotNull($new->fresh()->context['director_review']??null);$this->assertNull($p->fresh()->confirmed_revision_id);
  $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials&legacy_materials=1')->assertOk()->assertSee('审阅镜头与口播')->assertSee('补充必要真实参考')->assertSee('尚未授权制作');
 }
 public function test_stale_and_wrong_owner_inputs_do_not_invoke_writer():void{
  [$p,$r]=$this->source();$this->mock(MarketingWriter::class)->shouldNotReceive('propose');
  try{app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version-1,$r->id,(string)Str::uuid());$this->fail('stale');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
  $this->assertDatabaseCount('marketing_ai_runs',0);
 } public function test_actual_writer_transport_gets_director_schema_and_no_other_tool():void{
  [$p,$r]=$this->source();$data=MarketingDirectorContractTest::directorOutput();
  \Illuminate\Support\Facades\Http::fake(['https://api.openai.com/v1/responses'=>function($request,$options)use($data){$this->assertSame(90,$options['timeout']);return \Illuminate\Support\Facades\Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]]]);}]);
  $new=app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version,$r->id,(string)Str::uuid());
  \Illuminate\Support\Facades\Http::assertSentCount(1);
  \Illuminate\Support\Facades\Http::assertSent(fn($request)=>isset($request['text']['format']['schema']['properties']['shots'])&&str_contains($request['input'],'supplier-independent'));
  $this->assertSame('director-v1',$new->document['director_plan']['schema_version']);
 }
 public function test_new_design_does_not_inherit_old_design_review_and_missing_material_blocks_final_confirm():void{
  [$p,$r]=$this->source();$context=$r->context;$context['director_review']=['actor_id'=>$p->owner_id];$r->update(['context'=>$context]);
  $this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->andReturn(MarketingDirectorContractTest::directorOutput());
  $new=app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version,$r->id,(string)Str::uuid());
  $this->assertArrayNotHasKey('director_review',$new->context);
  try{app(\App\Services\Marketing\MarketingWorkflow::class)->confirm($p->id,$p->owner_id,$p->fresh()->version,$new->id);$this->fail('accepted missing');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
 } public function test_timeout_is_identifiable_and_preserves_source_without_retry():void{
  [$p,$r]=$this->source();
  $this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->andThrow(new \App\Services\AI\ProviderFailure('timeout'));
  try{app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version,$r->id,(string)Str::uuid());$this->fail('accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertStringContainsString('等待超时',$e->getMessage());}
  $run=\App\Models\MarketingAiRun::where('project_id',$p->id)->latest()->first();$this->assertSame('timeout',$run->usage['failure']['reason']);$this->assertSame($r->id,$p->fresh()->current_revision_id);
 } public function test_material_plan_is_versioned_and_keeps_director_separate():void{
  [$p,$r]=$this->source();$doc=$r->document;$doc['scenes'][0]['visual']='Show the real rear';$doc['director_plan']=MarketingDirectorContractTest::directorOutput()+['source_revision_id'=>$r->id];$r->update(['document'=>$doc]);
  $this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->withArgs(fn($f,$a)=>$a==='material_plan')->andReturn(MarketingMaterialPackageTest::materialOutput());
  $new=app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version,$r->id,(string)Str::uuid(),'material_plan');
  $this->assertSame($doc['director_plan'],$new->document['director_plan']);$this->assertCount(3,$new->document['material_package']['outputs']);$this->assertArrayNotHasKey('material_package',$r->fresh()->document);
 } public function test_workspace_redesign_passes_explicit_feedback_and_rejects_changed_duplicate():void{
  [$p,$r]=$this->source();$this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->withArgs(fn($facts,$action,$feedback)=>$action==='director'&&$feedback==='镜头保持固定')->andReturn(MarketingDirectorContractTest::directorOutput());
  $id=(string)Str::uuid();$url='/admin/marketing/workspace/'.$p->id.'/creative';$data=['version'=>$p->version,'request_id'=>$id,'action'=>'director','feedback'=>'镜头保持固定'];
  $this->post($url,$data)->assertRedirect()->assertSessionHasNoErrors();$this->assertNotNull($p->fresh()->currentRevision->document['director_plan']??null);
  $data['feedback']='改成快速环绕';$this->post($url,$data)->assertStatus(409);
 }}
