<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingResearchFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Http};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use App\Models\{MarketingRevision,MarketingProductionPlan};
use App\Services\Marketing\{MarketingMaterialPackage,MarketingMaterialReferences,MarketingMaterialImages,MarketingMaterialImageClient,MarketingProductionHandoff,MarketingProductionPreparation,MarketingProductionCompiler,MarketingWorkflow};
class MarketingProductionHandoffTest extends TestCase
{
 use RefreshDatabase,MarketingResearchFixtures;
 private function source(bool $adopt=false):array{
  Storage::fake('local');$p=$this->researchProject();\App\Models\AiConnection::where('provider','openai')->update(['image_model'=>'gpt-image-2','models'=>[['id'=>'gpt-image-2','image'=>true]]]);$p->update(['concepts'=>[['title'=>'用途介绍','video_type'=>'product_showcase']],'selected_concept'=>0,'planning_state'=>['status'=>'confirmed']]);
  $doc=['summary'=>'用途','duration'=>15,'cta'=>'询价','scenes'=>MarketingMaterialPackageTest::facts()['scenes'],'director_plan'=>MarketingDirectorContractTest::directorOutput(),'gaps'=>[]];
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$p->brief,'snapshot'=>$p->snapshot,'concept'=>$p->concepts[0]]]);
  $doc['director_plan']['source_revision_id']=$r->id;$doc['director_plan']['source_digest']=app(\App\Services\Marketing\MarketingDirectorWorkflow::class)->digest($p,$r);$r->update(['document'=>$doc]);
  $facts=MarketingMaterialPackageTest::facts();$facts['source_revision_id']=$r->id;$facts['source_digest']=app(MarketingMaterialPackage::class)->digest($p,$r);$doc['material_package']=app(MarketingMaterialPackage::class)->build(MarketingMaterialPackageTest::materialOutput(),$facts);$r->update(['document'=>$doc]);if($adopt){$context=$r->context;$context['director_review']=['plan_digest'=>hash('sha256',json_encode($doc['director_plan'],JSON_THROW_ON_ERROR))];$r->update(['context'=>$context]);}$p->update(['current_revision_id'=>$r->id]);return [$p->fresh(),$r];
 }


 private function prepared():array{
  [$p,$r]=$this->source(true);Http::preventStrayRequests();Storage::fake('public');$need=$r->document['material_package']['requirements'][0]['id'];app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();$s=app(MarketingMaterialImages::class);$run=$s->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$s->authorize($p->id,$p->owner_id,$run->id);$file=UploadedFile::fake()->image('frame.png');$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->times(3)->andReturn(['bytes'=>file_get_contents($file->getRealPath()),'usage'=>[],'actual_usd'=>.01,'request_id'=>'fixture']);
  foreach($run->usage['outputs'] as $out){$s->advance($p->id,$p->owner_id,$run->id);$s->review($p->id,$p->owner_id,$run->id,$out['id'],true);}$p->refresh();app(MarketingWorkflow::class)->confirm($p->id,$p->owner_id,$p->version,$p->current_revision_id);return [$p->fresh(),$run->fresh()];
 }
 public function test_handoff_keeps_frame_roles_director_copy_and_blocks_provider_execution():void{
  [$p]=$this->prepared();$h=app(MarketingProductionHandoff::class)->build($p,$p->currentRevision);$this->assertCount(1,$h['scenes']);$this->assertSame(['start_frame','end_frame','extra_image'],array_keys($h['scenes'][0]['frames']));$this->assertSame($p->currentRevision->document['scenes'][0]['subtitle'],$h['scenes'][0]['postproduction']['subtitle']);$this->assertSame($p->currentRevision->document['director_plan']['shots'][0],$h['scenes'][0]['director']);$m=app(MarketingProductionCompiler::class)->compile($p,$p->currentRevision);$this->assertArrayHasKey('graphics',$h['scenes'][0]['postproduction']);$this->assertSame('preflight-only',$m['execution_profile']);$this->assertNotEmpty($m['blockers']);$this->assertFalse($m['publication_authorized']);Http::assertNothingSent();
 }
 public function test_workspace_preflight_is_saved_idempotently_and_cannot_confirm_budget():void{
  [$p]=$this->prepared();$request=(string)Str::uuid();$url='/admin/marketing/workspace/'.$p->id.'/preflight';$data=['version'=>$p->version,'revision_id'=>$p->current_revision_id,'request_id'=>$request];$this->post($url,$data)->assertRedirect();$this->post($url,$data)->assertRedirect();$this->assertDatabaseCount('marketing_production_plans',1);$plan=MarketingProductionPlan::first();$this->assertSame('blocked',$plan->status);$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=confirmation')->assertOk()->assertSee('本地预检通过')->assertSee('已批准生产帧')->assertSee('start_frame',false);$this->post('/admin/marketing/projects/'.$p->id.'/production/'.$plan->id.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertUnprocessable();Http::assertNothingSent();
 }
 public function test_file_revocation_invalidates_even_replayed_preflight():void{
  [$p]=$this->prepared();$service=app(MarketingProductionPreparation::class);$request=(string)Str::uuid();$plan=$service->prepare($p->id,$p->owner_id,$p->version,$p->current_revision_id,$request);$id=$p->currentRevision->document['material_package']['outputs'][0]['asset_id'];$p->assets()->whereKey($id)->update(['authorized'=>false]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$service->prepare($p->id,$p->owner_id,$p->version,$p->current_revision_id,$request);
 }
 public function test_missing_or_duplicate_role_cannot_be_handed_off():void{
  [$p]=$this->prepared();$r=$p->currentRevision;$d=$r->document;$d['material_package']['outputs'][1]['role']='start_frame';$r->update(['document'=>$d]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingProductionHandoff::class)->build($p,$r);
 }
 public function test_modified_frame_file_is_rejected():void{
  [$p]=$this->prepared();$r=$p->currentRevision;$a=$p->assets()->findOrFail($r->document['material_package']['outputs'][0]['asset_id']);Storage::disk('local')->put($a->path,'changed bytes');$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=confirmation')->assertOk()->assertSee('本地预检未通过');$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingProductionHandoff::class)->build($p,$r);
 }
 public function test_preferences_change_invalidates_saved_preflight_and_owner_is_enforced():void{
  [$p]=$this->prepared();$url='/admin/marketing/workspace/'.$p->id.'/preflight';$data=['version'=>$p->version,'revision_id'=>$p->current_revision_id,'request_id'=>(string)Str::uuid()];$this->post($url,$data)->assertRedirect();$prefs=$p->preferences;$prefs['narration']=false;$p->update(['preferences'=>$prefs]);$this->postJson($url,$data)->assertConflict();$other=\App\Models\User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($other)->postJson($url,$data)->assertNotFound();Http::assertNothingSent();
 }
 public function test_legacy_production_entry_preserves_modern_story_instead_of_creating_draft():void{
  [$p]=$this->prepared();config(['marketing.tool_planning.dynamic_project_id'=>$p->id]);$revision=$p->current_revision_id;$this->post('/admin/marketing/projects/'.$p->id.'/production',['version'=>$p->version,'revision_id'=>$revision,'request_id'=>(string)Str::uuid()])->assertRedirect();$this->assertSame($revision,$p->fresh()->current_revision_id);$this->assertSame('preflight-only',MarketingProductionPlan::first()->manifest['execution_profile']);Http::assertNothingSent();
 }
}
