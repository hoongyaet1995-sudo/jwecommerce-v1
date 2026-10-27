<?php
namespace Tests\Feature;
use App\Models\{MarketingAiRun,MarketingResearchRun};
use App\Services\Marketing\{MarketingCreativePlanning,MarketingResearchSources,MarketingResearchWorkflow,MarketingWriter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingCreativePlanningTest extends TestCase
{
 use RefreshDatabase,MarketingResearchFixtures;
 private function sample():array{
  $p=$this->researchProject('Business storage equipment');
  config(['marketing.creative.enabled'=>true,'marketing.creative.project'=>$p->id,'marketing.creative.budget_usd'=>'0.20','marketing.creative.max_calls'=>5,'marketing.creative.authorization'=>null]);
  $r=app(MarketingResearchWorkflow::class)->start($p->id,$p->owner_id,$p->version,(string)Str::uuid());
  $sources=app(MarketingResearchSources::class)->normalize(array_map(fn($i)=>['url'=>'https://youtube.com/watch?v=examplevid'.$i,'raw_content'=>"### Transcript\n[0:01] Explain the real use first.\n[0:06] Then introduce the product.",'_role'=>$i===3?'cross_industry':'same_industry','_extraction_status'=>'content_available'],[1,2,3]));
  $r->update(['status'=>'partial','sources'=>$sources]);$p->refresh()->update(['active_research_run_id'=>null]);
  return [$p->fresh(),$r];
 }
 public function test_saved_research_is_reassessed_without_fetching_or_claiming_playback():void{
  [$p,$r]=$this->sample();$s=app(MarketingCreativePlanning::class)->research($p);
  $this->assertSame($r->id,$s['run_id']);$this->assertCount(3,$s['sources']);$this->assertSame('partial',$s['coverage']['status']);
  $this->assertSame(0,$s['coverage']['video_observations']);Http::assertNothingSent();
  $this->assertSame('pending_visual_evidence',$s['creative_readiness']['status']);
 }
 public function test_one_call_revision_usage_describes_the_actual_bounded_allowance():void{
  [$p]=$this->sample();$grant=(string)Str::uuid();config(['marketing.creative.authorization'=>$grant,'marketing.creative.budget_usd'=>'0.02','marketing.creative.max_calls'=>1]);
  $run=MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'revise','feedback'=>'Hold ending longer','status'=>'running']);
  Http::fake(['api.openai.com/v1/responses'=>Http::response(['status'=>'completed','usage'=>['input_tokens'=>12,'output_tokens'=>3],'output'=>[['content'=>[['type'=>'refusal','refusal'=>'Not prepared']]]]])]);
  try{app(MarketingWriter::class)->propose(['project_id'=>$p->id,'run_id'=>$run->id,'creative_planning'=>true,'snapshot'=>['images'=>[]]],'revise','Hold ending longer');$this->fail('Expected refusal');}
  catch(\Illuminate\Validation\ValidationException){$this->assertSame('JTG revision allowance maxUSD0.02 max1calls',$run->fresh()->usage['authorization']);Http::assertSentCount(1);}
 }
 public function test_another_owner_research_and_materials_are_never_reused():void{
  [$p]=$this->sample();$owner=\App\Models\User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$other=app(\App\Services\Marketing\MarketingWorkflow::class)->create($owner,['source_type'=>'theme','theme'=>'Another business']);config(['marketing.creative.project'=>$other->id]);
  $this->assertNull(app(MarketingCreativePlanning::class)->research($other));Http::assertNothingSent();
 }
 public function test_scoped_text_budget_cannot_borrow_old_allowance_or_exceed_call_limit():void{
  [$p]=$this->sample();$service=app(MarketingCreativePlanning::class);
  foreach(range(1,5) as $i){$r=MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'concepts','feedback'=>'','status'=>'running']);$service->reserve($r->id,$p->id);}
  $this->assertSame('0.1000',number_format((float)MarketingAiRun::sum('reserved_usd'),4,'.',''));
  $r=MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'storyboard','feedback'=>'','status'=>'running']);
  $this->expectException(\Illuminate\Validation\ValidationException::class);$service->reserve($r->id,$p->id);
 }
 public function test_reference_requires_existing_source_and_exact_evidence_excerpt():void{
  [$p]=$this->sample();$s=app(MarketingCreativePlanning::class)->research($p);
  $concepts=array_map(fn($i)=>['title'=>'Direction'.$i,'approach'=>['use_scenario','feature_detail','question_answer'][$i-1],'hook'=>'Opening'.$i,'story'=>'A distinct product-value story'.$i,'materials'=>'Available materials','duration'=>30,'ratio'=>'9:16','recommended'=>$i===1,'reason'=>'Fits current facts','references'=>[['source_id'=>$s['sources'][$i-1]['id'],'excerpt'=>'Explain the real use first.','adaptation'=>'An original adaptation; no video performance claim.']]], [1,2,3]);
  $data=app(MarketingWriter::class)->validateOutput(['concepts'=>$concepts],'concepts',['research'=>$s]);$this->assertCount(3,$data['concepts']);
  $concepts[0]['references'][0]['source_id']='invented';$this->expectException(\Illuminate\Validation\ValidationException::class);
  app(MarketingWriter::class)->validateOutput(['concepts'=>$concepts],'concepts',['research'=>$s]);
 }
 public function test_new_explicit_authorization_counts_separately_without_clearing_old_runs():void{
  [$p]=$this->sample();$service=app(MarketingCreativePlanning::class);
  foreach(range(1,5) as $i){$r=MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'concepts','feedback'=>'','status'=>'failed']);$service->reserve($r->id,$p->id);}
  $old=MarketingAiRun::where('project_id',$p->id)->get()->mapWithKeys(fn($r)=>[$r->id=>$r->getRawOriginal()])->all();
  $grant=(string)Str::uuid();config(['marketing.creative.authorization'=>$grant,'marketing.creative.budget_usd'=>'0.04','marketing.creative.max_calls'=>2]);
  foreach(range(1,2) as $i){$r=MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'revise','feedback'=>'','status'=>'running']);$service->reserve($r->id,$p->id);$this->assertSame($grant,$r->fresh()->usage['creative_authorization']['id']);}
  foreach($old as $id=>$row)$this->assertSame($row,MarketingAiRun::findOrFail($id)->getRawOriginal());
  $r=MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'revise','feedback'=>'','status'=>'running']);
  $this->expectException(\Illuminate\Validation\ValidationException::class);$service->reserve($r->id,$p->id);
 }
 public function test_refused_paid_output_keeps_usage_and_does_not_retry_or_save_revision():void{
  [$p]=$this->sample();
  Http::fake(['api.openai.com/v1/responses'=>Http::response(['status'=>'completed','usage'=>['input_tokens'=>112,'output_tokens'=>3,'total_tokens'=>115],'output'=>[['content'=>[['type'=>'refusal','refusal'=>'Not prepared']]]]])]);
  try{app(\App\Services\Marketing\MarketingWorkflow::class)->generate($p->id,$p->owner_id,$p->version,'recommend','',(string)Str::uuid());$this->fail('Expected refusal');}
  catch(\Illuminate\Validation\ValidationException $e){
   $run=MarketingAiRun::where('project_id',$p->id)->firstOrFail();$this->assertSame('failed',$run->status);
   $this->assertSame(112,$run->usage['input_tokens']??null);$this->assertSame('0.0200',$run->reserved_usd);
   $this->assertSame(0,$p->revisions()->count());Http::assertSentCount(1);
  }
 }
 public function test_existing_ui_generates_cited_concepts_and_saves_recipe_with_selected_storyboard():void{
  [$p]=$this->sample();\Illuminate\Support\Facades\Storage::fake('local');
  $disk=\Illuminate\Support\Facades\Storage::disk('local');$disk->put('marketing/clip.mp4','validated fixture content');
  $clip=\App\Models\MarketingAsset::create(['project_id'=>$p->id,'name'=>'Illustrative clip','path'=>'marketing/clip.mp4','mime'=>'video/mp4','size'=>25,'authorized'=>true]);
  $path=\Illuminate\Http\UploadedFile::fake()->image('p.png',100,100)->store('marketing','local');
  $image=\App\Models\MarketingAsset::create(['project_id'=>$p->id,'name'=>'Product','path'=>$path,'mime'=>'image/png','size'=>$disk->size($path),'authorized'=>true]);
  $this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('probe')->andReturn(['streams'=>[['codec_type'=>'video','codec_name'=>'h264','width'=>720,'height'=>1280,'nb_read_frames'=>'120','nb_frames'=>'120']],'format'=>['duration'=>'5']]);
  app(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->andReturn('');
  $s=app(MarketingCreativePlanning::class)->research($p);
  $concepts=array_map(fn($i)=>['title'=>'Direction'.$i,'approach'=>['use_scenario','feature_detail','question_answer'][$i-1],'hook'=>'Opening'.$i,'story'=>'A distinct product-value story'.$i,'materials'=>'Saved materials','duration'=>30,'ratio'=>'9:16','recommended'=>$i===1,'reason'=>'Fits current facts','references'=>[['source_id'=>$s['sources'][$i-1]['id'],'excerpt'=>'Explain the real use first.','adaptation'=>'Original method.']]], [1,2,3]);
  $scenes=[];foreach(range(0,5) as $i)$scenes[]=['visual'=>'An original scene','voiceover'=>'Explain a use.','subtitle'=>'A product use','duration'=>5,'material'=>$i===0?'Saved clip':(in_array($i,[1,4])?'Product photo':'text-only original graphic'),'asset_ids'=>$i===0?[$clip->id]:(in_array($i,[1,4])?[$image->id]:[])];
  $production=['brand'=>['name'=>'ACME','descriptor'=>'Business'],'scenes'=>array_map(fn($layout)=>['layout'=>$layout,'heading'=>'A clear headline','lines'=>['One','Two','Three'],'body'=>'Confirmed note.'],['need_open','product_intro','use_map','business_uses','requirements','cta_close'])];
  $story=['summary'=>'A use scenario','duration'=>30,'cta'=>$p->brief['cta'],'changes'=>[],'requirements'=>[],'scenes'=>$scenes,'production'=>$production];
  $response=fn($d)=>['status'=>'completed','usage'=>['input_tokens'=>100,'output_tokens'=>200],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($d)]]]]];
  Http::fake(['api.openai.com/v1/responses'=>Http::sequence()->push($response(['concepts'=>$concepts]))->push($response($story))]);
  $flow=app(\App\Services\Marketing\MarketingWorkflow::class);
  $flow->generate($p->id,$p->owner_id,$p->version,'concepts','',(string)Str::uuid());$p->refresh();
  $this->assertSame($s['digest'],$p->concepts[0]['_research']['digest']);
  $flow->selectConcept($p->id,$p->owner_id,$p->version,0);$p->refresh();
  $flow->generate($p->id,$p->owner_id,$p->version,'storyboard','',(string)Str::uuid());$p->refresh();
  $r=$p->currentRevision;$this->assertSame('use-scenario-hybrid-v1',$r->context['production_recipe']);
  $this->assertSame($s['digest'],$r->context['research']['digest']);$this->assertSame($production,$r->context['presentation']);
  $this->assertArrayNotHasKey('production',$r->document);$this->assertNull($r->confirmed_at);
  $this->assertSame([],$r->document['gaps']);$this->assertSame([],$r->document['notes']);
  $this->assertSame(\App\Services\Marketing\MarketingDraftContract::VERSION,$r->context['draft_contract']);
  Http::assertSent(fn($req)=>($req['text']['format']['type']??'')==='json_schema' && $req['text']['format']['strict']===true);
  Http::assertSentCount(2);
 }
}
