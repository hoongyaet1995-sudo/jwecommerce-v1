<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingProductTemplateRenderer;
class MarketingProductTemplateRendererTest extends TestCase {
 use \Illuminate\Foundation\Testing\RefreshDatabase;
 public function test_rejects_unbound_task_before_any_process():void { $this->expectException(\RuntimeException::class); $this->expectExceptionMessage('template_task_invalid'); app(MarketingProductTemplateRenderer::class)->execute(['version'=>'arbitrary']); }
 public function test_verify_rejects_forged_empty_receipt():void { $this->expectException(\RuntimeException::class); $this->expectExceptionMessage('template_output_invalid'); app(MarketingProductTemplateRenderer::class)->verify(['source_unchanged'=>true,'formats'=>[]]); }
 public function test_stored_run_cannot_be_rebound_to_another_owner_or_changed_copy_preferences_or_voice():void {
  $u=\App\Models\User::factory()->create();$project=\App\Models\MarketingProject::create(['owner_id'=>$u->id,'source_type'=>'product','name'=>'Bound test','snapshot'=>[],'brief'=>[]]);
  $input=['packet'=>['product_id'=>'opaque'],'asset_index'=>[],'preferences'=>['formats'=>['landscape'],'music'=>true,'subtitles'=>false,'narration'=>false]];
  $run=\App\Models\MarketingTemplateRun::create(['project_id'=>$project->id,'owner_id'=>$u->id,'request_id'=>(string)\Illuminate\Support\Str::uuid(),'input_digest'=>str_repeat('a',64),'input'=>$input,'draft'=>['copy'=>['hook'=>'Saved copy']],'result'=>['voice'=>null]]);
  $task=['version'=>'product-template-task-v1','subject'=>['type'=>'template_run','id'=>$run->id,'project_id'=>$project->id,'owner_id'=>$u->id],'source_digest'=>$run->input_digest,'packet'=>$input['packet'],'asset_index'=>[],'draft'=>$run->draft,'formats'=>['landscape'],'music'=>true,'subtitles'=>false,'voice'=>null];
  $mutations=[['subject'=>array_replace($task['subject'],['owner_id'=>$u->id+1])],['source_digest'=>str_repeat('b',64)],['draft'=>['copy'=>['hook'=>'Injected copy']]],['formats'=>['portrait']],['subtitles'=>true],['music'=>false],['voice'=>['path'=>'arbitrary','sha256'=>str_repeat('0',64)]],['asset_index'=>['injected'=>'/etc/passwd']]];
  foreach($mutations as $change){try{app(MarketingProductTemplateRenderer::class)->execute(array_replace($task,$change));$this->fail('Changed task was accepted');}catch(\RuntimeException $e){$this->assertSame('template_task_invalid',$e->getMessage());}}
 }
 public function test_verify_rejects_path_escape_even_with_plausible_video_metadata():void {
  $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('template_output_invalid');
  app(MarketingProductTemplateRenderer::class)->verify(['adapter'=>'product_template_v1','source_unchanged'=>true,'formats'=>['landscape'=>['width'=>1920,'height'=>1080,'frames'=>360,'seconds'=>15,'audio'=>false,'path'=>'../outside.mp4']]]);
 }
 public function test_approved_component_is_byte_identical_to_frozen_source():void {
  $this->assertSame('b25dfc665301fbe9331b3bf7e8015caa86ced8ecfa9fc9b529d95f2be1b13cde',hash_file('sha256',resource_path('marketing-renderer/product-template/component.tsx')));
 } public function test_draft_preflight_rejects_unsaved_unbound_run_without_starting_renderer():void {
  $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('template_task_invalid');
  app(MarketingProductTemplateRenderer::class)->validateDraft(new \App\Models\MarketingTemplateRun(['id'=>'../../outside','input'=>[],'draft'=>[]]));
 } public function test_draft_preflight_checks_real_fonts_and_reuses_only_its_bound_receipt():void {
  \Illuminate\Support\Facades\Storage::fake('local');config(['marketing.production.node_binary'=>'node','marketing.production.browser_binary'=>'C:/Users/User/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe']);
  $r=new \App\Models\MarketingTemplateRun(['id'=>(string)\Illuminate\Support\Str::uuid(),'input'=>['packet'=>[],'asset_index'=>[],'preferences'=>['formats'=>['landscape'],'subtitles'=>false]],'draft'=>['copy'=>'Test']]);$r->exists=true;
  $brief=['source'=>'front.png','brand'=>'TEST','disclosure'=>'','language'=>'en','product'=>'test','name'=>'Test product','category'=>'Product showcase','hook'=>'Look closer','benefit'=>'Visible detail','support'=>'Explore the photo','cta'=>'View product','accent'=>'#7a6650','ink'=>'#253d32','bg'=>'#f8f5ef','details'=>array_fill(0,3,['title'=>'Detail','copy'=>'','source'=>'front.png','position'=>'50% 50%','scale'=>1])];
  $this->mock(\App\Services\Marketing\MarketingTemplateContract::class)->shouldReceive('call')->with('props',\Mockery::type('array'))->twice()->andReturn(['brief'=>$brief,'asset_files'=>[],'draft_hash'=>str_repeat('a',64)]);
  $renderer=app(MarketingProductTemplateRenderer::class);$renderer->validateDraft($r);
  $path='marketing-template-runs/'.$r->id.'/preflight-'.str_repeat('a',64).'/preflight-receipt.json';$disk=\Illuminate\Support\Facades\Storage::disk('local');$this->assertTrue($disk->exists($path));$receipt=$disk->get($path);
  config(['marketing.production.node_binary'=>'missing-node-should-not-start']);$renderer->validateDraft($r);$this->assertSame($receipt,$disk->get($path));
  $this->assertCount(2,$disk->allFiles('marketing-template-runs/'.$r->id));
 } public function test_saved_receipt_adaptation_retains_private_paths_for_every_format():void {
  $path=tempnam(sys_get_temp_dir(),'template-receipt-');$relative='marketing-template-runs/00000000-0000-4000-8000-000000000001/render-v1';
  file_put_contents($path,json_encode(['formats'=>['landscape'=>['filename'=>'landscape.mp4','sha256'=>str_repeat('a',64)],'portrait'=>['filename'=>'portrait.mp4','sha256'=>str_repeat('b',64)]]]));
  try{$method=new \ReflectionMethod(MarketingProductTemplateRenderer::class,'receipt');$out=$method->invoke(app(MarketingProductTemplateRenderer::class),$path,$relative);$this->assertSame($relative.'/landscape.mp4',$out['formats']['landscape']['path']??null);$this->assertSame($relative.'/portrait.mp4',$out['formats']['portrait']['path']??null);$this->assertSame(str_repeat('b',64),$out['formats']['portrait']['sha256']);}finally{unlink($path);}
 }}
