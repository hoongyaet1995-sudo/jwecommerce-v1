<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingResearchFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Http};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use App\Models\{MarketingRevision,MarketingProductionPlan};
use App\Services\Marketing\{MarketingMaterialPackage,MarketingMaterialReferences,MarketingMaterialImages,MarketingMaterialImageClient,MarketingProductionHandoff,MarketingWorkflow,MarketingDirectorWorkflow,MarketingWriter,MarketingTransitionPlanning,MarketingEvolinkAdapter};
class MarketingTransitionWorkflowTest extends TestCase
{
 use RefreshDatabase,MarketingResearchFixtures;
 private string $pairVerdict='pass';
 private function mockPairs():void{
  $this->partialMock(\App\Services\Marketing\MarketingMaterialVisionClient::class)->shouldReceive('inspect')->andReturnUsing(function($design,$images){
   $this->assertArrayHasKey('candidate',$images);$this->assertArrayHasKey('scene_start',$images);$this->assertSame('end_frame',$design['frame_role']);
   return ['verdict'=>$this->pairVerdict,'checks'=>array_fill_keys(\App\Services\Marketing\MarketingMaterialVisionClient::CHECKS,$this->pairVerdict==='pass'?'pass':'fail'),'summary'=>$this->pairVerdict==='pass'?'首尾服装一致':'首尾衬衫由浅蓝变深蓝','observations'=>['检查实际首尾图'],'correction'=>'Keep opening wardrobe','actual_usd'=>.001,'usage'=>['input_tokens'=>10,'output_tokens'=>10],'image_hashes'=>array_map(fn($path)=>hash_file('sha256',$path),$images)];
  });
 }
 private function source(bool $adopt=false):array{
  Storage::fake('local');$p=$this->researchProject();\App\Models\AiConnection::where('provider','openai')->update(['image_model'=>'gpt-image-2','models'=>[['id'=>'gpt-image-2','image'=>true]]]);$p->update(['concepts'=>[['title'=>'用途介绍','video_type'=>'product_showcase','ratio'=>'9:16']],'selected_concept'=>0,'planning_state'=>['status'=>'confirmed']]);
  $doc=['summary'=>'用途','duration'=>15,'cta'=>'询价','scenes'=>MarketingMaterialPackageTest::facts()['scenes'],'director_plan'=>MarketingDirectorContractTest::directorOutput(),'gaps'=>[]];
  $doc['duration']=30;$doc['scenes'][]=$doc['scenes'][0];$shot=$doc['director_plan']['shots'][0];$shot['scene_number']=2;$shot['start']=15;$shot['end']=30;foreach($shot['beats'] as &$beat){$beat['start']+=15;$beat['end']+=15;}unset($beat);$doc['director_plan']['shots'][]=$shot;
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$p->brief,'snapshot'=>$p->snapshot,'concept'=>$p->concepts[0]]]);
  $doc['director_plan']['source_revision_id']=$r->id;$doc['director_plan']['source_digest']=app(\App\Services\Marketing\MarketingDirectorWorkflow::class)->digest($p,$r);$r->update(['document'=>$doc]);
  $facts=MarketingMaterialPackageTest::facts();$facts['scenes']=$doc['scenes'];$facts['duration']=30;$facts['director_plan']=$doc['director_plan'];$facts['source_revision_id']=$r->id;$facts['source_digest']=app(MarketingMaterialPackage::class)->digest($p,$r);$doc['material_package']=app(MarketingMaterialPackage::class)->build(MarketingMaterialPackageTest::materialOutput(),$facts);$r->update(['document'=>$doc]);if($adopt){$context=$r->context;$context['director_review']=['plan_digest'=>hash('sha256',json_encode($doc['director_plan'],JSON_THROW_ON_ERROR))];$r->update(['context'=>$context]);}$p->update(['current_revision_id'=>$r->id]);return [$p->fresh(),$r];
 }


 private function prepared():array{
  [$p,$r]=$this->source(true);Http::preventStrayRequests();Storage::fake('public');$need=$r->document['material_package']['requirements'][0]['id'];app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();$s=app(MarketingMaterialImages::class);$run=$s->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$s->authorize($p->id,$p->owner_id,$run->id);$file=UploadedFile::fake()->image('frame.png',864,1536);$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->times(6)->andReturn(['bytes'=>file_get_contents($file->getRealPath()),'usage'=>[],'actual_usd'=>.01,'request_id'=>'fixture']);
  foreach($run->usage['outputs'] as $out){$s->advance($p->id,$p->owner_id,$run->id);$s->review($p->id,$p->owner_id,$run->id,$out['id'],true);}$p->refresh();app(MarketingWorkflow::class)->confirm($p->id,$p->owner_id,$p->version,$p->current_revision_id);return [$p->fresh(),$run->fresh()];
 }

 private function draft($p){$this->mockPairs();config(['marketing.conversation.enabled'=>true,'marketing.conversation.open_testing'=>true]);$this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->withArgs(fn($facts,$action)=>$action==='transitions'&&count($facts['director_facts']['frame_inventory'])===4&&!isset($facts['director_facts']['pair_review_summaries']))->andReturn(MarketingTransitionContractTest::transitionOutput());return app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version,$p->current_revision_id,(string)Str::uuid(),'transitions');}
 public function test_verified_reference_context_and_pair_failure_block_reuse():void{
  [$p]=$this->prepared();$this->pairVerdict='revise';$new=$this->draft($p);$p->refresh();
  $this->assertSame('revise',$new->document['transition_plan']['pair_reviews'][1]['verdict']);
  $state=app(MarketingTransitionPlanning::class)->state($p,$new);$this->assertSame('needs_materials',$state['status']);$this->assertFalse($state['ready']);
  $refs=app(MarketingTransitionPlanning::class)->referenceEvidence($p,$new);$this->assertSame('reference_provided',$refs[0]['status']);$this->assertTrue($refs[0]['verified']);
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingTransitionPlanning::class)->confirm($p->id,$p->owner_id,$p->version,$new->id,['1-2']);
 }
 public function test_failed_pair_can_quote_targeted_repair_without_repainting_approved_set():void{
  [$p]=$this->prepared();$this->pairVerdict='revise';$this->draft($p);$p->refresh();$old=$p->currentRevision;
  $this->mock(\App\Services\Marketing\MarketingMaterialRepairPlanner::class)->shouldReceive('plan')->once()->andReturn(['summary'=>'只调整第1段首帧，统一服装','needs_input'=>'','targets'=>[['output_id'=>'scene-1-start_frame','reason'=>'服装不一致','correction'=>'Keep the approved ending wardrobe; preserve opening pose','anchors'=>['scene-1-end_frame']]],'usage'=>[],'actual_usd'=>.001]);
  $run=app(\App\Services\Marketing\MarketingMaterialRepair::class)->plan($p->id,$p->owner_id,$p->version,(string)Str::uuid());
  $this->assertSame('quoted',$run->status);$this->assertCount(1,$run->usage['outputs']);$this->assertSame('scene-1-start_frame',$run->usage['outputs'][0]['id']);$this->assertSame('repair_pending',$run->usage['outputs'][0]['status']);$this->assertSame($old->id,$p->fresh()->current_revision_id);$this->assertSame($old->document['material_package'],$p->fresh()->currentRevision->document['material_package']);
  $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertSee('确认修正范围与费用');Http::assertNothingSent();
 }
 private function repairFixture():array{
  [$p]=$this->prepared();$this->pairVerdict='revise';$new=$this->draft($p);$doc=$new->document;$doc['transition_plan']['pair_reviews'][2]['verdict']='pass';$doc['transition_plan']['pair_reviews'][2]['checks']=array_fill_keys(\App\Services\Marketing\MarketingMaterialVisionClient::CHECKS,'pass');$new->update(['document'=>$doc]);$p->refresh();
  $this->mock(\App\Services\Marketing\MarketingMaterialRepairPlanner::class)->shouldReceive('plan')->once()->withArgs(fn($facts)=>isset($facts['failed_pair_checks'])&&!isset($facts['pair_reviews']))->andReturn(['summary'=>'只修正首帧','needs_input'=>'','targets'=>[['output_id'=>'scene-1-start_frame','reason'=>'统一全片服装','correction'=>'Use retained dark shirt anchor, keep opening pose','anchors'=>['scene-1-end_frame']]],'usage'=>[],'actual_usd'=>.001]);
  $service=app(\App\Services\Marketing\MarketingMaterialRepair::class);$request=(string)Str::uuid();$run=$service->plan($p->id,$p->owner_id,$p->version,$request);$again=$service->plan($p->id,$p->owner_id,$p->version,$request);$this->assertSame($run->id,$again->id);return [$p->fresh(),$run,$service];
 }
 private function repairReport(string $verdict='pass'):array{return ['verdict'=>$verdict,'checks'=>array_fill_keys(\App\Services\Marketing\MarketingMaterialVisionClient::CHECKS,$verdict==='pass'?'pass':'fail'),'summary'=>$verdict==='pass'?'一致性通过':'服装仍不一致','correction'=>'Preserve retained wardrobe','actual_usd'=>.001,'usage'=>[],'image_hashes'=>[]];}
 public function test_pair_failure_does_not_repair_a_passed_opening_for_a_retained_ending():void{
  [$p,$run,$service]=$this->repairFixture();$old=$p->current_revision_id;$service->authorize($p->id,$p->owner_id,$run->id,2);
  $file=UploadedFile::fake()->image('repair.png');$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->once()->andReturn(['bytes'=>file_get_contents($file->getRealPath()),'usage'=>[],'actual_usd'=>.02,'request_id'=>'repair']);
  $calls=0;$this->partialMock(\App\Services\Marketing\MarketingMaterialVisionClient::class)->shouldReceive('inspect')->twice()->andReturnUsing(function($design,$images)use(&$calls){$report=$this->repairReport(++$calls===1?'pass':'revise');$report['image_hashes']=array_map(fn($path)=>hash_file('sha256',$path),$images);if($calls===2){$report['summary']='候选结束帧取景偏宽';$report['checks']=array_fill_keys(\App\Services\Marketing\MarketingMaterialVisionClient::CHECKS,'pass');$report['checks']['composition']='fail';}return $report;});
  for($i=0;$i<3;$i++)app(MarketingMaterialImages::class)->advance($p->id,$p->owner_id,$run->id);
  $run->refresh();$this->assertSame('needs_attention',$run->status);$this->assertSame('pair_scope',$run->usage['pause_reason']);$this->assertSame('ai_passed',$run->usage['outputs'][0]['status']);$this->assertSame(1,$run->usage['outputs'][0]['repairs']);$this->assertSame('scene-1-end_frame',$run->usage['repair_checks']['scene-1']['candidate_output_id']);$this->assertSame($old,$p->fresh()->current_revision_id);
  $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertSee('成对复检未通过，需要重新评估修正对象')->assertSee('停止本批，保留记录');
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/apply',['run'=>$run->id,'consent'=>1])->assertStatus(409);
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/abandon',['run'=>$run->id,'consent'=>1])->assertOk();$this->assertSame('completed',$run->fresh()->status);$this->assertSame('pass',$run->usage['outputs'][0]['attempts'][0]['qa']['verdict']);$this->assertSame($old,$p->fresh()->current_revision_id);Http::assertNothingSent();
 }
 public function test_repair_authorization_returns_in_place_progress_without_reloading():void{
  [$p,$run]=$this->repairFixture();$this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/authorize',['run'=>$run->id,'consent'=>1,'spend_stop_usd'=>2])->assertOk()->assertJsonPath('reload',false)->assertJsonPath('next_operation','advance')->assertJsonPath('progress.stage','image')->assertJsonPath('run',$run->id);Http::assertNothingSent();
 }
 public function test_targeted_repair_requires_consent_rechecks_pairs_then_preserves_other_outputs():void{
  [$p,$run,$service]=$this->repairFixture();$old=$p->currentRevision;
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/authorize',['run'=>$run->id])->assertUnprocessable();
  $file=UploadedFile::fake()->image('repair.png',864,1536);$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->once()->andReturnUsing(function($prompt,$size,$paths)use($file){$this->assertStringContainsString('TARGETED EDIT',$prompt);$this->assertGreaterThanOrEqual(2,count($paths));return ['bytes'=>file_get_contents($file->getRealPath()),'usage'=>[],'actual_usd'=>.02,'request_id'=>'repair'];});
  $boundary=false;$this->partialMock(\App\Services\Marketing\MarketingMaterialVisionClient::class)->shouldReceive('inspect')->times(3)->andReturnUsing(function($design,$images,$isBoundary=false)use(&$boundary){$this->assertFileExists($images['candidate']);if($isBoundary){$boundary=true;$this->assertArrayHasKey('previous_passed_frame',$images);}return $this->repairReport();});
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/authorize',['run'=>$run->id,'consent'=>1,'spend_stop_usd'=>2])->assertOk();
  $images=app(MarketingMaterialImages::class);for($i=0;$i<5;$i++)$images->advance($p->id,$p->owner_id,$run->id);$run->refresh();$this->assertSame('awaiting_batch_review',$run->status);$this->assertTrue($boundary);$this->assertSame($old->id,$p->fresh()->current_revision_id);$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertSee('修正前')->assertSee('采用修正结果，继续准备');
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/apply',['run'=>$run->id])->assertUnprocessable();$this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/apply',['run'=>$run->id,'consent'=>1])->assertOk();$p->refresh();$doc=$p->currentRevision->document;$this->assertSame($old->document['scenes'],$doc['scenes']);$this->assertNotSame($old->document['material_package']['outputs'][0]['asset_id'],$doc['material_package']['outputs'][0]['asset_id']);foreach(range(1,5) as $i)$this->assertSame($old->document['material_package']['outputs'][$i],$doc['material_package']['outputs'][$i]);$this->assertTrue(app(MarketingTransitionPlanning::class)->state($p,$p->currentRevision)['ready']);Http::assertNothingSent();
 }
 public function test_targeted_repair_stops_at_two_failed_edits_and_cannot_apply():void{
  [$p,$run,$service]=$this->repairFixture();$old=$p->current_revision_id;$service->authorize($p->id,$p->owner_id,$run->id,2);$file=UploadedFile::fake()->image('repair.png');$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->twice()->andReturn(['bytes'=>file_get_contents($file->getRealPath()),'usage'=>[],'actual_usd'=>.01,'request_id'=>'repair']);$this->partialMock(\App\Services\Marketing\MarketingMaterialVisionClient::class)->shouldReceive('inspect')->twice()->andReturn($this->repairReport('revise'));
  for($i=0;$i<5;$i++)app(MarketingMaterialImages::class)->advance($p->id,$p->owner_id,$run->id);$run->refresh();$this->assertSame('needs_attention',$run->status);$this->assertSame(2,$run->usage['outputs'][0]['repairs']);$this->assertSame($old,$p->fresh()->current_revision_id);$this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/apply',['run'=>$run->id,'consent'=>1])->assertStatus(409);Http::assertNothingSent();
 }
 public function test_changed_original_permissions_invalidates_repair_authorization():void{
  [$p,$run,$service]=$this->repairFixture();$asset=$p->currentRevision->document['material_package']['outputs'][0]['asset_id'];$p->assets()->whereKey($asset)->update(['authorized'=>false]);$this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/authorize',['run'=>$run->id,'consent'=>1])->assertStatus(409);Http::assertNothingSent();
 }
 public function test_unexecuted_repair_quote_can_be_cancelled_without_touching_originals():void{
  [$p,$run]=$this->repairFixture();$original=$p->currentRevision->document;$this->postJson('/admin/marketing/workspace/'.$p->id.'/material-repairs/abandon',['run'=>$run->id,'consent'=>1])->assertOk();$this->assertSame('completed',$run->fresh()->status);$this->assertSame($original,$p->fresh()->currentRevision->document);Http::assertNothingSent();
 }
 public function test_new_plan_preserves_story_images_and_requires_human_reuse_then_handoff():void{
  [$p]=$this->prepared();$old=$p->currentRevision;$new=$this->draft($p);$this->assertNotSame($old->id,$new->id);$this->assertSame($old->document['scenes'],$new->document['scenes']);$this->assertSame($old->document['director_plan'],$new->document['director_plan']);$this->assertSame($old->document['material_package'],$new->document['material_package']);$p->refresh();$flow=app(MarketingTransitionPlanning::class);$this->assertSame('needs_review',$flow->state($p,$new)['status']);$this->postJson('/admin/marketing/workspace/'.$p->id.'/transition-review',['version'=>$p->version,'revision_id'=>$new->id,'consent'=>1,'edges'=>[]])->assertUnprocessable();
  $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertSee('前段尾帧')->assertSee('可复用候选');$flow->confirm($p->id,$p->owner_id,$p->version,$new->id,['1-2']);$p->refresh();$this->assertTrue($flow->state($p,$p->currentRevision)['ready']);$this->assertNull($p->confirmed_revision_id);app(MarketingWorkflow::class)->confirm($p->id,$p->owner_id,$p->version,$new->id);$p->refresh();$h=app(MarketingProductionHandoff::class)->build($p,$p->currentRevision);$q=app(MarketingEvolinkAdapter::class)->quote($p,$h,'seedance-2.0-image-to-video');$this->assertCount(1,$h['transitions']['boundaries']);$this->assertStringContainsString('boundary_constraints',$q['scenes'][0]['payload_template']['prompt']);$image=app(\App\Services\Marketing\MarketingMaterialImageDesign::class)->build($p,$p->currentRevision->document['material_package'],$p->currentRevision->document['material_package']['outputs'][1]);$this->assertSame('outgoing_end',$image['design']['boundary_constraints'][0]['side']);Http::assertNothingSent();
 }
 public function test_old_multiscene_quote_is_blocked_without_boundary_review_and_images_remain():void{
  [$p]=$this->prepared();$h=app(MarketingProductionHandoff::class)->build($p,$p->currentRevision);$this->assertSame('missing',$h['transitions']['status']);$this->assertCount(6,$p->currentRevision->document['material_package']['outputs']);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingEvolinkAdapter::class)->quote($p,$h,'seedance-2.0-image-to-video');
 }
 public function test_changed_file_permission_or_source_revokes_review():void{
  [$p]=$this->prepared();$new=$this->draft($p);$p->refresh();$flow=app(MarketingTransitionPlanning::class);$flow->confirm($p->id,$p->owner_id,$p->version,$new->id,['1-2']);$p->refresh();$id=$new->document['material_package']['outputs'][1]['asset_id'];$p->assets()->whereKey($id)->update(['authorized'=>false]);$this->assertSame('needs_materials',$flow->state($p,$p->currentRevision)['status']);$prefs=$p->preferences;$prefs['music']='changed';$p->update(['preferences'=>$prefs]);$this->assertSame('stale',$flow->state($p,$p->currentRevision)['status']);Http::assertNothingSent();
 }
 public function test_redesign_assessment_can_adopt_plan_but_cannot_approve_old_images():void{
  $this->mockPairs();
  [$p]=$this->prepared();config(['marketing.conversation.enabled'=>true,'marketing.conversation.open_testing'=>true]);$raw=MarketingTransitionContractTest::transitionOutput();$raw['boundaries'][0]['frame_reviews'][0]['status']='needs_redesign';$this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->andReturn($raw);$new=app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version,$p->current_revision_id,(string)Str::uuid(),'transitions');$p->refresh();$flow=app(MarketingTransitionPlanning::class);$flow->confirm($p->id,$p->owner_id,$p->version,$new->id,[],false);$p->refresh();$state=$flow->state($p,$p->currentRevision);$this->assertTrue($state['adopted']);$this->assertFalse($state['ready']);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$flow->confirm($p->id,$p->owner_id,$p->version,$new->id,['1-2']);
 }
 public function test_real_writer_path_sends_transition_schema_not_story_rewrite():void{
  $this->mockPairs();
  [$p]=$this->prepared();config(['marketing.conversation.enabled'=>true,'marketing.conversation.open_testing'=>true]);Http::fake(['https://api.openai.com/v1/responses'=>Http::response(['status'=>'completed','usage'=>['input_tokens'=>100,'output_tokens'=>100],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(MarketingTransitionContractTest::transitionOutput())]]]]])]);
  $request=(string)Str::uuid();$flow=app(MarketingDirectorWorkflow::class);$new=$flow->draft($p->id,$p->owner_id,$p->version,$p->current_revision_id,$request,'transitions','保持简单切镜');$again=$flow->draft($p->id,$p->owner_id,$p->version,$p->current_revision_id,$request,'transitions','保持简单切镜');$this->assertSame($new->id,$again->id);Http::assertSentCount(1);Http::assertSent(fn($r)=>$r['max_output_tokens']===8192&&isset($r['text']['format']['schema']['properties']['boundaries'])&&!isset($r['text']['format']['schema']['properties']['shots'])&&str_contains($r['input'],'reference_evidence')&&str_contains($r['input'],'reference_provided')&&str_contains($r['input'],'An omitted assets list is NOT evidence'));
 }
 public function test_pair_report_is_reused_and_changed_pair_is_blocked():void{
  [$p]=$this->prepared();$new=$this->draft($p);$p->refresh();$this->assertSame('needs_review',app(MarketingTransitionPlanning::class)->state($p,$new)['status']);
  $this->partialMock(\App\Services\Marketing\MarketingMaterialVisionClient::class)->shouldNotReceive('inspect');
  $run=\App\Models\MarketingAiRun::where('project_id',$p->id)->where('action','transitions')->firstOrFail();$cached=app(\App\Services\Marketing\MarketingFramePairReview::class)->inspect($p,$new,$run);$this->assertCount(2,$cached);
  $doc=$new->document;$doc['transition_plan']['pair_reviews'][1]['source_digest']='old';$new->update(['document'=>$doc]);$this->assertSame('needs_materials',app(MarketingTransitionPlanning::class)->state($p,$new)['status']);Http::assertNothingSent();
 }
 public function test_catalog_reference_is_verified_without_private_asset_uuid():void{
  [$p]=$this->prepared();$r=$p->currentRevision;$file=UploadedFile::fake()->image('catalog.png');$bytes=file_get_contents($file->getRealPath());Storage::disk('public')->put('pair-catalog.png',$bytes);
  $snapshot=$p->snapshot;$snapshot['images']=[['path'=>'/pair-catalog.png']];$p->update(['snapshot'=>$snapshot]);$doc=$r->document;$doc['material_package']['requirements'][0]['reference']=['type'=>'catalog_image','index'=>0,'path'=>'/pair-catalog.png','sha256'=>hash('sha256',$bytes),'confirmed_by'=>$p->owner_id];$r->update(['document'=>$doc]);
  $refs=app(MarketingTransitionPlanning::class)->referenceEvidence($p,$r);$this->assertTrue($refs[0]['verified']);$this->assertSame('catalog_image',$refs[0]['reference_type']);$this->assertArrayNotHasKey('asset_id',$refs[0]);
  Storage::disk('public')->delete('pair-catalog.png');$refs=app(MarketingTransitionPlanning::class)->referenceEvidence($p,$r);$this->assertFalse($refs[0]['verified']);$this->assertSame('reference_invalid',$refs[0]['status']);
 }
 public function test_failed_transition_preserves_original_revision_and_files():void{
  $this->mockPairs();
  [$p]=$this->prepared();$old=$p->currentRevision;config(['marketing.conversation.enabled'=>true,'marketing.conversation.open_testing'=>true]);$bad=MarketingTransitionContractTest::transitionOutput();$bad['boundaries'][0]['to']=3;$this->mock(MarketingWriter::class)->shouldReceive('propose')->once()->andReturn($bad);try{app(MarketingDirectorWorkflow::class)->draft($p->id,$p->owner_id,$p->version,$old->id,(string)Str::uuid(),'transitions');$this->fail('invalid boundary accepted');}catch(\Illuminate\Validation\ValidationException){$p->refresh();$this->assertSame($old->id,$p->current_revision_id);$this->assertSame($old->document['material_package'],$p->currentRevision->document['material_package']);$this->assertNull($p->busy_run_id);}Http::assertNothingSent();
 }
}
