<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingResearchFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Http};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use App\Models\MarketingRevision;
use App\Services\Marketing\{MarketingMaterialPackage,MarketingMaterialReferences,MarketingMaterialImages,MarketingMaterialImageClient,MarketingMaterialVisionClient};
class MarketingMaterialAutoReviewTest extends TestCase
{
 use RefreshDatabase,MarketingResearchFixtures;
 private function source(bool $adopt=false):array{
  Storage::fake('local');$p=$this->researchProject();\App\Models\AiConnection::where('provider','openai')->update(['image_model'=>'gpt-image-2','models'=>[['id'=>'gpt-image-2','image'=>true]]]);$p->update(['concepts'=>[['title'=>'用途介绍','video_type'=>'product_showcase']],'selected_concept'=>0,'planning_state'=>['status'=>'confirmed']]);
  $doc=['summary'=>'用途','duration'=>15,'cta'=>'询价','scenes'=>MarketingMaterialPackageTest::facts()['scenes'],'director_plan'=>MarketingDirectorContractTest::directorOutput(),'gaps'=>[]];
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$p->brief,'snapshot'=>$p->snapshot,'concept'=>$p->concepts[0]]]);
  $facts=MarketingMaterialPackageTest::facts();$facts['source_revision_id']=$r->id;$facts['source_digest']=app(MarketingMaterialPackage::class)->digest($p,$r);$doc['material_package']=app(MarketingMaterialPackage::class)->build(MarketingMaterialPackageTest::materialOutput(),$facts);$r->update(['document'=>$doc]);if($adopt){$context=$r->context;$context['director_review']=['plan_digest'=>hash('sha256',json_encode($doc['director_plan'],JSON_THROW_ON_ERROR))];$r->update(['context'=>$context]);}$p->update(['current_revision_id'=>$r->id]);return [$p->fresh(),$r];
 }

 private function batch(float $budget=2):array{
  [$p,$r]=$this->source(true);$need=$r->document['material_package']['requirements'][0]['id'];app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();$s=app(MarketingMaterialImages::class);$run=$s->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid(),'',true,$budget);$s->authorize($p->id,$p->owner_id,$run->id);return [$p,$run,$s];
 }
 private function report(string $verdict='pass'):array{return ['verdict'=>$verdict,'checks'=>array_fill_keys(MarketingMaterialVisionClient::CHECKS,$verdict==='pass'?'pass':'fail'),'summary'=>$verdict==='pass'?'画面通过':'字幕覆盖画面','observations'=>['已检查实际画面'],'correction'=>'Remove the caption bar, preserve identity and product.','actual_usd'=>.001,'image_hashes'=>[],'usage'=>['input_tokens'=>10,'output_tokens'=>10]];}
 private function imageResult(float $cost=.02):array{$file=UploadedFile::fake()->image('frame.png');return ['bytes'=>file_get_contents($file->getRealPath()),'usage'=>['input_tokens'=>10,'output_tokens'=>10],'actual_usd'=>$cost,'request_id'=>'req-fixture'];}
 public function test_failed_actual_frame_is_targeted_edited_then_rechecked_and_batch_approved():void{
  [$p,$r,$s]=$this->batch();$image=$this->partialMock(MarketingMaterialImageClient::class);$result=$this->imageResult();$image->shouldReceive('generate')->times(4)->andReturnUsing(function($prompt,$size,$paths)use($result){if(str_contains($prompt,'TARGETED EDIT')){$this->assertNotEmpty($paths);$this->assertStringContainsString('Remove the caption bar',$prompt);}return $result;});
  $vision=$this->partialMock(MarketingMaterialVisionClient::class);$calls=0;$vision->shouldReceive('inspect')->times(4)->andReturnUsing(function($design,$images)use(&$calls){$this->assertFileExists($images['candidate']);if(($design['frame_role']??'')==='end_frame')$this->assertArrayHasKey('scene_start',$images);return $this->report($calls++===0?'revise':'pass');});
  for($i=0;$i<9;$i++)$s->advance($p->id,$p->owner_id,$r->id);
  $r->refresh();$this->assertSame('awaiting_batch_review',$r->status);$this->assertCount(2,$r->usage['outputs'][0]['attempts']);$this->assertSame('ai_passed',$r->usage['outputs'][0]['status']);$this->assertSame('revise',$r->usage['outputs'][0]['attempts'][0]['qa']['verdict']);
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/review',['run'=>$r->id,'output'=>$r->usage['outputs'][0]['id'],'approve'=>1])->assertUnprocessable();
  Storage::fake('public');$this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/approve-batch',['run'=>$r->id])->assertUnprocessable();$s->approveBatch($p->id,$p->owner_id,$r->id);$s->approveBatch($p->id,$p->owner_id,$r->id);$this->assertSame('completed',$r->fresh()->status);$this->assertDatabaseCount('cms_media_assets',3);$this->assertTrue(collect($p->fresh()->currentRevision->document['material_package']['outputs'])->every(fn($o)=>$o['status']==='approved'));
 }
 public function test_two_repairs_is_a_hard_request_limit():void{
  [$p,$r,$s]=$this->batch();$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->times(3)->andReturn($this->imageResult());$this->partialMock(MarketingMaterialVisionClient::class)->shouldReceive('inspect')->times(3)->andReturn($this->report('revise'));
  for($i=0;$i<8;$i++)$s->advance($p->id,$p->owner_id,$r->id);$r->refresh();$this->assertSame('needs_attention',$r->status);$this->assertSame('repair_limit',$r->usage['pause_reason']);$this->assertSame(2,$r->usage['outputs'][0]['repairs']);$this->assertCount(3,$r->usage['outputs'][0]['attempts']);
 }
 public function test_budget_pauses_before_next_call_and_only_explicit_increase_resumes():void{
  [$p,$r,$s]=$this->batch(.6);$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->once()->andReturn($this->imageResult(.55));$this->partialMock(MarketingMaterialVisionClient::class)->shouldNotReceive('inspect');$s->advance($p->id,$p->owner_id,$r->id);$s->advance($p->id,$p->owner_id,$r->id);$this->assertSame('budget',$r->fresh()->usage['pause_reason']);$this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/resume-budget',['run'=>$r->id,'spend_stop_usd'=>2])->assertUnprocessable();$s->resumeBudget($p->id,$p->owner_id,$r->id,2);$this->assertSame('running',$r->fresh()->status);
 }
 public function test_unknown_vision_response_never_replays_or_accepts_frame():void{
  [$p,$r,$s]=$this->batch();$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->once()->andReturn($this->imageResult());$this->partialMock(MarketingMaterialVisionClient::class)->shouldReceive('inspect')->once()->andThrow(new \RuntimeException('timeout'));for($i=0;$i<4;$i++)$s->advance($p->id,$p->owner_id,$r->id);$r->refresh();$this->assertTrue($r->usage['unknown_cost']);$this->assertSame('qa_unknown',$r->usage['outputs'][0]['status']);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$s->approveBatch($p->id,$p->owner_id,$r->id);
 }
 public function test_vision_sends_actual_pixels_and_server_derives_failure():void{
  $this->source();$checks=array_fill_keys(MarketingMaterialVisionClient::CHECKS,'pass');$checks['no_overlays']='fail';Http::fake(['https://api.openai.com/v1/responses'=>Http::response(['id'=>'resp-test','status'=>'completed','usage'=>['input_tokens'=>1000,'output_tokens'=>100],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['checks'=>$checks,'observations'=>['有字幕条'],'summary'=>'需移除字幕','correction'=>'Remove text'])]]]]])]);$file=UploadedFile::fake()->image('frame.png');$path=$file->getRealPath();$result=app(MarketingMaterialVisionClient::class)->inspect(['subject'=>'虚拟人物'],['candidate'=>$path]);$this->assertSame('revise',$result['verdict']);$this->assertEqualsWithDelta(.00032,$result['actual_usd'],.000001);Http::assertSent(fn($req)=>$req['model']==='gpt-5.6-luna'&&str_starts_with($req['input'][1]['content'][2]['image_url'],'data:image/png;base64,')&&$req['text']['format']['strict']===true);
 }
 public function test_automatic_quote_renders_limits_and_preserves_manual_mode():void{
  [$p,$r,$s]=$this->batch();$r->update(['status'=>'quoted']);$html=view('marketing.workspace.material-image-workflow',['project'=>$p,'imageUsage'=>$r->usage,'imageRun'=>$r,'imageFresh'=>true,'imageClean'=>true,'imageActive'=>false,'pendingFacts'=>['gaps'=>[],'items'=>[]],'productionBlocker'=>null,'productionReady'=>false,'imageStoryMismatch'=>false,'imagesReviewed'=>false])->render();$this->assertStringContainsString('AI视觉质检',$html);$this->assertStringContainsString('并非供应商硬性封顶',$html);$this->assertStringContainsString('最后由我确认整套',$html);$this->assertStringNotContainsString('每张先审阅，再继续',$html);
 }
 public function test_wardrobe_pair_failure_is_derived_from_two_actual_image_inputs():void{
  $this->source();$checks=array_fill_keys(MarketingMaterialVisionClient::CHECKS,'pass');$checks['pair_transition']='fail';
  Http::fake(['https://api.openai.com/v1/responses'=>Http::response(['status'=>'completed','usage'=>['input_tokens'=>100,'output_tokens'=>100],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['checks'=>$checks,'observations'=>['首帧浅蓝衬衫，尾帧深蓝'],'summary'=>'服装不一致','correction'=>'Match the opening shirt color'])]]]]])]);
  $start=UploadedFile::fake()->image('start.png');$end=UploadedFile::fake()->image('end.png');$result=app(MarketingMaterialVisionClient::class)->inspect(['frame_role'=>'end_frame'],['candidate'=>$end->getRealPath(),'scene_start'=>$start->getRealPath()]);$this->assertSame('revise',$result['verdict']);$this->assertCount(2,$result['image_hashes']);
  Http::assertSent(fn($request)=>count(array_filter($request['input'][1]['content'],fn($item)=>$item['type']==='input_image'))===2&&str_contains($request['input'][0]['content'],'SAME identity/wardrobe'));
 }
 public function test_uncertain_facts_stop_without_creative_repair():void{
  [$p,$r,$s]=$this->batch();$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->once()->andReturn($this->imageResult());$this->partialMock(MarketingMaterialVisionClient::class)->shouldReceive('inspect')->once()->andReturn($this->report('needs_input'));for($i=0;$i<3;$i++)$s->advance($p->id,$p->owner_id,$r->id);$this->assertSame('input',$r->fresh()->usage['pause_reason']);$s->abandon($p->id,$p->owner_id,$r->id);$this->assertSame('completed',$r->fresh()->status);$new=$s->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid(),'',true,2);$this->assertNotSame($r->id,$new->id);
 }
}
