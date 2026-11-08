<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingResearchFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use App\Models\MarketingRevision;
use App\Services\Marketing\MarketingMaterialPackage;
use App\Services\Marketing\MarketingMaterialReferences;
class MarketingMaterialReferencesTest extends TestCase
{
 use RefreshDatabase,MarketingResearchFixtures;
 protected function setUp():void {parent::setUp();$this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-26 12:00:00'));} // Historical verified pricing window; production expiry guard remains active.

 private function source(bool $adopt=false):array{
  Storage::fake('local');$p=$this->researchProject();\App\Models\AiConnection::where('provider','openai')->update(['image_model'=>'gpt-image-2','models'=>[['id'=>'gpt-image-2','image'=>true]]]);$p->update(['concepts'=>[['title'=>'用途介绍','video_type'=>'product_showcase']],'selected_concept'=>0,'planning_state'=>['status'=>'confirmed']]);
  $doc=['summary'=>'用途','duration'=>15,'cta'=>'询价','scenes'=>MarketingMaterialPackageTest::facts()['scenes'],'director_plan'=>MarketingDirectorContractTest::directorOutput(),'gaps'=>[]];
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$p->brief,'snapshot'=>$p->snapshot,'concept'=>$p->concepts[0]]]);
  $facts=MarketingMaterialPackageTest::facts();$facts['source_revision_id']=$r->id;$facts['source_digest']=app(MarketingMaterialPackage::class)->digest($p,$r);$doc['material_package']=app(MarketingMaterialPackage::class)->build(MarketingMaterialPackageTest::materialOutput(),$facts);$r->update(['document'=>$doc]);if($adopt){$context=$r->context;$context['director_review']=['plan_digest'=>hash('sha256',json_encode($doc['director_plan'],JSON_THROW_ON_ERROR))];$r->update(['context'=>$context]);}$p->update(['current_revision_id'=>$r->id]);return [$p->fresh(),$r];
 }
 public function test_image_quote_requires_reference_and_generation_needs_explicit_consent():void{
  [$p,$r]=$this->source(true);$service=app(\App\Services\Marketing\MarketingMaterialImages::class);
  $need=$r->document['material_package']['requirements'][0]['id'];
  app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));
  $p->refresh();$run=$service->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());
  $this->assertSame('quoted',$run->status);$this->assertNull($run->usage['total_usd']);$this->assertSame('openai',$run->usage['provider']);$this->assertSame('gpt-image-2',$run->usage['model']);
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/authorize',['run'=>$run->id])->assertUnprocessable();
  $this->assertSame('quoted',$run->fresh()->status);
 }
 public function test_image_submission_replay_does_not_submit_twice_and_review_versions_media():void{
  [$p,$r]=$this->source(true);$service=app(\App\Services\Marketing\MarketingMaterialImages::class);$need=$r->document['material_package']['requirements'][0]['id'];
  app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();
  $run=$service->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$reordered=$run->usage;ksort($reordered);$run->update(['usage'=>$reordered]);$service->authorize($p->id,$p->owner_id,$run->id);
  $resultFile=UploadedFile::fake()->image('result.png');$bytes=file_get_contents($resultFile->getRealPath());
  $mock=$this->partialMock(\App\Services\Marketing\MarketingMaterialImageClient::class);
  $mock->shouldReceive('generate')->once()->andReturn(['bytes'=>$bytes,'usage'=>['input_tokens'=>100,'output_tokens'=>200],'actual_usd'=>0.003,'request_id'=>'req-test']);
  $service->advance($p->id,$p->owner_id,$run->id);$service->authorize($p->id,$p->owner_id,$run->id);$service->advance($p->id,$p->owner_id,$run->id);
  $first=$run->fresh()->usage['outputs'][0];$this->assertSame('review',$first['status']);
  Storage::fake('public');$before=$p->fresh()->current_revision_id;$service->review($p->id,$p->owner_id,$run->id,$first['id'],true);
  $p->refresh();$this->assertNotSame($before,$p->current_revision_id);$output=$p->currentRevision->document['material_package']['outputs'][0];$this->assertSame('approved',$output['status']);$this->assertDatabaseHas('cms_media_assets',['id'=>$output['media_library_id']]);
 }
 public function test_unknown_image_submission_is_not_retried_or_quoted_again():void{
  [$p,$r]=$this->source(true);$need=$r->document['material_package']['requirements'][0]['id'];app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();
  config(['marketing.native.api_key'=>'fake']);$service=app(\App\Services\Marketing\MarketingMaterialImages::class);$run=$service->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$run->id);
  $mock=$this->partialMock(\App\Services\Marketing\MarketingMaterialImageClient::class);$mock->shouldReceive('generate')->once()->andThrow(new \App\Services\AI\ProviderFailure('timeout'));
  $service->advance($p->id,$p->owner_id,$run->id);$service->advance($p->id,$p->owner_id,$run->id);$this->assertSame('unknown',$run->fresh()->usage['outputs'][0]['status']);
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/quote',['version'=>$p->version,'request_id'=>(string)Str::uuid()])->assertConflict();
 }
 public function test_reference_change_invalidates_image_quote_without_paid_request():void{
  [$p,$r]=$this->source(true);$need=$r->document['material_package']['requirements'][0]['id'];app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();
  $service=app(\App\Services\Marketing\MarketingMaterialImages::class);$run=$service->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$p->assets()->update(['authorized'=>false]);
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/authorize',['run'=>$run->id,'consent'=>1])->assertUnprocessable();$this->assertSame('quoted',$run->fresh()->status);
 }
 public function test_gpt_image_transport_uses_ai_connect_and_multiple_base64_references():void{
  [$p]=$this->source(true);$file=UploadedFile::fake()->image('reference.png');$generated=UploadedFile::fake()->image('frame.png',864,1536);$bytes=file_get_contents($generated->getRealPath());
  \Illuminate\Support\Facades\Http::fake(['https://api.openai.com/v1/images/edits'=>\Illuminate\Support\Facades\Http::response(['data'=>[['b64_json'=>base64_encode($bytes)]],'usage'=>['input_tokens_details'=>['text_tokens'=>100,'image_tokens'=>200],'output_tokens'=>300]])]);
  $result=app(\App\Services\Marketing\MarketingMaterialImageClient::class)->generate('A fictional presenter in a workshop, product reference only','864x1536',[$file->getRealPath(),$file->getRealPath()]);
  $this->assertEqualsWithDelta(0.0111,$result['actual_usd'],0.000001);
  \Illuminate\Support\Facades\Http::assertSent(fn($request)=>$request->url()==='https://api.openai.com/v1/images/edits'&&$request['model']==='gpt-image-2'&&count($request['images'])===2&&str_starts_with($request['images'][0]['image_url'],'data:image/png;base64,')&&$request['size']==='864x1536');
 }
 public function test_frame_design_uses_distinct_story_beats_and_virtual_subject_without_user_photo():void{
  [$p,$r]=$this->source(true);$doc=$r->document;$doc['director_plan']['shots'][0]['subject']='虚拟主持人在店内，产品放右侧';$doc['director_plan']['shots'][0]['beats'][0]['action']='开场看向镜头，双手自然放下';$doc['director_plan']['shots'][0]['beats'][1]['action']='结尾微笑，右手指向商品';$r->update(['document'=>$doc]);$p->refresh();
  $pkg=$doc['material_package'];$pkg['requirements']=[];$builder=app(\App\Services\Marketing\MarketingMaterialImageDesign::class);
  $first=$builder->build($p,$pkg,$pkg['outputs'][0]);$last=$builder->build($p,$pkg,$pkg['outputs'][1]);
  $this->assertSame([],$first['design']['reference_roles']);$this->assertSame('虚拟主持人在店内，产品放右侧',$first['design']['subject']);$this->assertNotSame($first['design']['frame_state'],$last['design']['frame_state']);$this->assertStringContainsString('Create fictional environments and virtual subjects from text even with no input image',$first['prompt']);
 }
 public function test_presenter_story_without_character_plan_cannot_quote_product_only_frames():void{
  [$p,$r]=$this->source(true);$p->update(['concepts'=>[['title'=>'用途介绍','video_type'=>'presenter']]]);$doc=$r->document;$doc['material_package']['requirements']=[];$doc['material_package']['source_digest']=app(MarketingMaterialPackage::class)->digest($p,$r);$r->update(['document'=>$doc]);
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/quote',['version'=>$p->version,'request_id'=>(string)Str::uuid()])->assertUnprocessable()->assertJsonFragment(['message'=>'选定故事是人物口播，但当前分镜与素材计划没有安排人物。请先修正分镜并重新审阅导演稿，不能用商品照片替代主持人。']);
 }
 public function test_ai_connect_change_invalidates_quote_and_legacy_evolink_cannot_run():void{
  [$p,$r]=$this->source(true);$need=$r->document['material_package']['requirements'][0]['id'];app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();$service=app(\App\Services\Marketing\MarketingMaterialImages::class);$run=$service->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());
  \App\Models\AiConnection::where('provider','openai')->increment('version');$this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/authorize',['run'=>$run->id,'consent'=>1])->assertConflict();
  $legacy=\App\Models\MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'material_images','status'=>'running','usage'=>['model'=>'nano-banana-beta','outputs'=>[]]]);
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/advance',['run'=>$legacy->id])->assertUnprocessable();\Illuminate\Support\Facades\Http::assertNothingSent();
 }
 public function test_text_only_story_uses_generations_endpoint_without_fabricated_reference():void{
  [$p]=$this->source(true);$frame=UploadedFile::fake()->image('frame.png',864,1536);$bytes=file_get_contents($frame->getRealPath());
  \Illuminate\Support\Facades\Http::fake(['https://api.openai.com/v1/images/generations'=>\Illuminate\Support\Facades\Http::response(['data'=>[['b64_json'=>base64_encode($bytes)]],'usage'=>['input_tokens_details'=>['text_tokens'=>10,'image_tokens'=>0],'output_tokens'=>20]])]);
  app(\App\Services\Marketing\MarketingMaterialImageClient::class)->generate('Virtual presenter in fictional shop','864x1536',[]);
  \Illuminate\Support\Facades\Http::assertSent(fn($r)=>$r->url()==='https://api.openai.com/v1/images/generations'&&!isset($r['images'])&&$r['model']==='gpt-image-2');
 }
 public function test_clean_frames_exclude_caption_copy_and_graphic_jobs_from_image_request():void{
  [$p,$r]=$this->source(true);$doc=$r->document;$doc['scenes'][0]['voiceover']='绝不进入图像请求的口播测试文案';$r->update(['document'=>$doc]);$p->refresh();$pkg=$doc['material_package'];
  $pkg['requirements'][]=['id'=>'caption','kind'=>'graphic','name'=>'中文字幕和CTA','scene_numbers'=>[1],'owner'=>'ai','reference'=>null,'preparation'=>'绘制蓝色字幕条并写上专属测试文字'];
  $built=app(\App\Services\Marketing\MarketingMaterialImageDesign::class)->build($p,$pkg,$pkg['outputs'][0]);
  $this->assertArrayNotHasKey('spoken_context',$built['design']);$this->assertStringNotContainsString('专属测试文字',$built['prompt']);$this->assertStringNotContainsString('绝不进入图像请求的口播测试文案',$built['prompt']);
  $this->assertStringContainsString('No subtitle bars',$built['prompt']);$this->assertStringContainsString('natural scene background',$built['prompt']);$this->assertSame('postproduction_compositor',$built['postproduction']['owner']);
 }
 public function test_old_caption_design_cannot_authorize_and_new_free_quote_is_allowed():void{
  [$p,$r]=$this->source(true);$need=$r->document['material_package']['requirements'][0]['id'];app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();$service=app(\App\Services\Marketing\MarketingMaterialImages::class);$old=$service->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$usage=$old->usage;unset($usage['design_version']);$old->update(['usage'=>$usage]);
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/material-images/authorize',['run'=>$old->id,'consent'=>1])->assertConflict();
  $new=$service->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$this->assertNotSame($old->id,$new->id);$this->assertSame(\App\Services\Marketing\MarketingMaterialImageDesign::VERSION,$new->usage['design_version']);\Illuminate\Support\Facades\Http::assertNothingSent();
 }
 public function test_factual_requirement_needs_explicit_evidence_bound_acknowledgement():void{
  [$p,$r]=$this->source(true);$doc=$r->document;$item=['kind'=>'required_fact','scene_number'=>1,'message'=>'请确认已使用的商品说明真实','evidence'=>$doc['scenes'][0]['voiceover']];$doc['requirements']=[$item];$doc['gaps']=['Scene 1: '.$item['message'],'Other unresolved permission'];$r->update(['document'=>$doc]);$p->refresh();$service=app(\App\Services\Marketing\MarketingRequirementReview::class);$pending=$service->pending($p,$p->currentRevision);$this->assertCount(2,$pending['gaps']);
  $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials&legacy_materials=1')->assertOk()->assertSee('确认勾选的真实资料');
  $url='/admin/marketing/workspace/'.$p->id.'/material-images/facts';$this->postJson($url,['version'=>$p->version,'facts'=>[str_repeat('a',64)],'consent'=>1])->assertUnprocessable();
  $this->postJson($url,['version'=>$p->version,'facts'=>[$pending['items'][0]['key']],'consent'=>1])->assertOk();$p->refresh();$this->assertSame(['Other unresolved permission'],$service->pending($p,$p->currentRevision)['gaps']);$this->assertCount(2,$r->fresh()->document['gaps']);
  $brief=$p->brief;$brief['audience']='new audience';$p->update(['brief'=>$brief]);$this->assertCount(2,$service->pending($p,$p->currentRevision)['gaps']);
 }
 public function test_upload_binds_one_reference_without_making_production_image_and_stays_in_new_workspace():void{
  [$p,$r]=$this->source();$need=$r->document['material_package']['requirements'][0]['id'];$request=(string)Str::uuid();
  $url='/admin/marketing/workspace/'.$p->id.'/materials/'.$need.'/reference';
  $data=['version'=>$p->version,'request_id'=>$request,'permission'=>1,'image'=>UploadedFile::fake()->image('back.jpg')];
  $this->post($url,$data)->assertRedirect(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'materials']));
  $p->refresh();$package=$p->currentRevision->document['material_package'];$this->assertSame('reference_provided',$package['requirements'][0]['status']);$this->assertSame('awaiting_image_budget',$package['status']);
  foreach($package['outputs'] as $o){$this->assertNull($o['asset_id']);$this->assertSame('planned',$o['status']);}
  $this->assertCount(1,$p->assets);$this->assertArrayNotHasKey('reference',$r->fresh()->document['material_package']['outputs'][0]);
  $this->post($url,$data)->assertRedirect();$this->assertSame(1,$p->assets()->count());$this->assertSame(2,$p->revisions()->count());
 }
 public function test_ai_requirement_cannot_be_sent_back_to_employee_and_foreign_asset_is_rejected():void{
  [$p,$r]=$this->source();$needs=$r->document['material_package']['requirements'];$flow=app(MarketingMaterialReferences::class);
  foreach([[$needs[1]['id'],''],[$needs[0]['id'],'upload:'.(string)Str::uuid()]] as [$id,$source]){
   $this->post('/admin/marketing/workspace/'.$p->id.'/materials/'.$id.'/reference',['version'=>$p->version,'request_id'=>(string)Str::uuid(),'source'=>$source,'permission'=>1])->assertStatus($source===''?422:404);
  }$this->assertSame(1,$p->revisions()->count());
 }
 public function test_page_has_structured_inputs_and_no_legacy_material_link():void{
  [$p,$r]=$this->source();$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials&legacy_materials=1')->assertOk()->assertSee('核对素材制备计划')->assertSee('仅作参考')->assertSee('name="image"',false)->assertDontSee('href="'.route('marketing.show',$p->id).'"',false);
 }
 public function test_deleted_reference_becomes_missing_without_get_mutating_history():void{
  [$p,$r]=$this->source();$need=$r->document['material_package']['requirements'][0]['id'];$new=app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('rear.jpg'));
  $p->refresh();$asset=$p->assets()->first();Storage::disk('local')->delete($asset->path);
  $checked=app(MarketingMaterialPackage::class)->assertFresh($p,$new);$this->assertNull($checked['requirements'][0]['reference']);$this->assertSame('awaiting_inputs',$checked['status']);
  $this->assertNotNull($new->fresh()->document['material_package']['requirements'][0]['reference']);
 }
 public function test_workspace_confirmation_requires_package_even_without_director():void{
  [$p,$r]=$this->source();$doc=$r->document;unset($doc['director_plan'],$doc['material_package']);$context=$r->context;$context['workspace_planning']=true;$r->update(['document'=>$doc,'context'=>$context]);
  try{app(\App\Services\Marketing\MarketingWorkflow::class)->confirm($p->id,$p->owner_id,$p->version,$r->id);$this->fail('bypassed');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
 } public function test_live_materials_uses_approved_single_entry_and_reference_forms_are_inside_wizard():void{
  [$p,$r]=$this->source();$before=$p->getRawOriginal();
  $response=$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials&legacy_materials=1')->assertOk()->assertSee('data-material-workspace',false)->assertSee('materialWorkspaceWizard')->assertSee('material-workspace.js')->assertDontSee('（演示）');
  $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());$xpath=new \DOMXPath($dom);
  $this->assertSame(1,$xpath->query('//button[@data-mw-start]')->length);
  $this->assertSame(0,$xpath->query('//button[@data-creative-action and not(ancestor::*[@id="materialWorkspaceWizard"]) and not(ancestor::*[contains(@class,"dropdown-menu")])]')->length);
  $this->assertSame(1,$xpath->query('//div[@id="materialWorkspaceWizard"]//input[@name="image"]')->length);
  $this->assertSame($before,$p->fresh()->getRawOriginal());\Illuminate\Support\Facades\Http::assertNothingSent();
 }}
