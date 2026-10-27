<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingResearchFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Http};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use App\Models\{MarketingRevision,MarketingProductionPlan};
use App\Services\Marketing\{MarketingMaterialPackage,MarketingMaterialReferences,MarketingMaterialImages,MarketingMaterialImageClient,MarketingProductionHandoff,MarketingProductionPreparation,MarketingWorkflow,MarketingEvolinkAdapter};
class MarketingEvolinkAdapterTest extends TestCase
{
 use RefreshDatabase,MarketingResearchFixtures;
 private function source(bool $adopt=false):array{
  Storage::fake('local');$p=$this->researchProject();\App\Models\AiConnection::where('provider','openai')->update(['image_model'=>'gpt-image-2','models'=>[['id'=>'gpt-image-2','image'=>true]]]);$p->update(['concepts'=>[['title'=>'用途介绍','video_type'=>'product_showcase','ratio'=>'9:16']],'selected_concept'=>0,'planning_state'=>['status'=>'confirmed']]);
  $doc=['summary'=>'用途','duration'=>15,'cta'=>'询价','scenes'=>MarketingMaterialPackageTest::facts()['scenes'],'director_plan'=>MarketingDirectorContractTest::directorOutput(),'gaps'=>[]];
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$p->brief,'snapshot'=>$p->snapshot,'concept'=>$p->concepts[0]]]);
  $doc['director_plan']['source_revision_id']=$r->id;$doc['director_plan']['source_digest']=app(\App\Services\Marketing\MarketingDirectorWorkflow::class)->digest($p,$r);$r->update(['document'=>$doc]);
  $facts=MarketingMaterialPackageTest::facts();$facts['source_revision_id']=$r->id;$facts['source_digest']=app(MarketingMaterialPackage::class)->digest($p,$r);$doc['material_package']=app(MarketingMaterialPackage::class)->build(MarketingMaterialPackageTest::materialOutput(),$facts);$r->update(['document'=>$doc]);if($adopt){$context=$r->context;$context['director_review']=['plan_digest'=>hash('sha256',json_encode($doc['director_plan'],JSON_THROW_ON_ERROR))];$r->update(['context'=>$context]);}$p->update(['current_revision_id'=>$r->id]);return [$p->fresh(),$r];
 }


 private function prepared():array{
  [$p,$r]=$this->source(true);Http::preventStrayRequests();Storage::fake('public');$need=$r->document['material_package']['requirements'][0]['id'];app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$need,(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('front.jpg'));$p->refresh();$s=app(MarketingMaterialImages::class);$run=$s->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$s->authorize($p->id,$p->owner_id,$run->id);$file=UploadedFile::fake()->image('frame.png',864,1536);$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->times(3)->andReturn(['bytes'=>file_get_contents($file->getRealPath()),'usage'=>[],'actual_usd'=>.01,'request_id'=>'fixture']);
  foreach($run->usage['outputs'] as $out){$s->advance($p->id,$p->owner_id,$run->id);$s->review($p->id,$p->owner_id,$run->id,$out['id'],true);}$p->refresh();app(MarketingWorkflow::class)->confirm($p->id,$p->owner_id,$p->version,$p->current_revision_id);return [$p->fresh(),$run->fresh()];
 }

 public function test_first_last_order_and_reference_roles_are_explicit_and_quotes_do_not_dispatch():void{
  [$p]=$this->prepared();$h=app(MarketingProductionHandoff::class)->build($p,$p->currentRevision);$a=app(MarketingEvolinkAdapter::class);$q=$a->quote($p,$h,'seedance-2.0-image-to-video');$this->assertSame(2985000,$q['video_estimate_microusd']);$this->assertSame(['start_frame','end_frame'],array_column($q['scenes'][0]['image_bindings'],'role'));$this->assertSame(['extra_image'],$q['scenes'][0]['retained_roles']);$this->assertSame('quotation-only',$q['execution_profile']);$this->assertNull($q['total_estimate_microusd']);$this->assertStringNotContainsString($h['scenes'][0]['postproduction']['subtitle'],$q['scenes'][0]['payload_template']['prompt']);
  $ref=$a->quote($p,$h,'seedance-2.0-reference-to-video');$this->assertCount(3,$ref['scenes'][0]['image_bindings']);$this->assertStringContainsString('@image3',$ref['scenes'][0]['payload_template']['prompt']);$this->assertSame('prompt_guidance',$ref['frame_control']);$urls=[];foreach($q['scenes'][0]['image_bindings'] as $i=>$binding)$urls[$binding['asset_id']]='https://cdn.example.com/frame'.$i.'.png';$payload=$a->payload($q['scenes'][0],$urls);$this->assertSame(array_values($urls),$payload['image_urls']);$this->assertFalse($payload['generate_audio']);$this->assertArrayNotHasKey('image_start',$payload);Http::assertNothingSent();
 }
 public function test_quote_persists_exact_model_and_cannot_confirm_budget_or_reuse_request_for_another_model():void{
  [$p]=$this->prepared();$url='/admin/marketing/workspace/'.$p->id.'/supplier-quote';$data=['version'=>$p->version,'revision_id'=>$p->current_revision_id,'request_id'=>(string)Str::uuid(),'model'=>'seedance-2.0-image-to-video'];$this->post($url,$data)->assertRedirect();$this->post($url,$data)->assertRedirect();$this->assertDatabaseCount('marketing_production_plans',1);$plan=MarketingProductionPlan::first();$this->assertSame('blocked',$plan->status);$this->assertSame($data['model'],$plan->manifest['supplier_quote']['model']);$data['model']='seedance-2.0-reference-to-video';$this->postJson($url,$data)->assertConflict();$this->post('/admin/marketing/projects/'.$p->id.'/production/'.$plan->id.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertUnprocessable();$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=confirmation')->assertOk()->assertSee('视频生成估价')->assertSee('2.985');Http::assertNothingSent();
 }
 public function test_unsupported_duration_never_silently_changes_story():void{
  [$p]=$this->prepared();$h=app(MarketingProductionHandoff::class)->build($p,$p->currentRevision);$h['scenes'][0]['planned_seconds']=3;$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingEvolinkAdapter::class)->quote($p,$h,'seedance-2.0-image-to-video');
 }
 public function test_quote_replay_and_display_invalidate_after_connection_change():void{
  [$p]=$this->prepared();config(['marketing.native.api_key'=>'fixture-first']);$url='/admin/marketing/workspace/'.$p->id.'/supplier-quote';$d=['version'=>$p->version,'revision_id'=>$p->current_revision_id,'request_id'=>(string)Str::uuid(),'model'=>'seedance-2.0-image-to-video'];$this->post($url,$d)->assertRedirect();config(['marketing.native.api_key'=>'fixture-changed']);$this->postJson($url,$d)->assertConflict();$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=confirmation')->assertOk()->assertSee('请重新核算');Http::assertNothingSent();
 }
 public function test_unverified_model_and_expired_rate_are_blocked_without_calls():void{
  [$p]=$this->prepared();$h=app(MarketingProductionHandoff::class)->build($p,$p->currentRevision);$this->postJson('/admin/marketing/workspace/'.$p->id.'/supplier-quote',['version'=>$p->version,'revision_id'=>$p->current_revision_id,'request_id'=>(string)Str::uuid(),'model'=>'veo3.1-fast-beta'])->assertUnprocessable();$this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-29'));$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingEvolinkAdapter::class)->quote($p,$h,'seedance-2.0-image-to-video');
 }
 public function test_payload_builder_rejects_extra_provider_fields():void{
  [$p]=$this->prepared();$h=app(MarketingProductionHandoff::class)->build($p,$p->currentRevision);$a=app(MarketingEvolinkAdapter::class);$q=$a->quote($p,$h,'seedance-2.0-reference-to-video');$scene=$q['scenes'][0];$scene['payload_template']['model_params']=['web_search'=>true];$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$a->payload($scene,[]);
 }
}
