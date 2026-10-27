<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingTemplateContract;
class MarketingTemplateContractTest extends TestCase {
 public function test_unknown_mode_is_rejected_before_process():void {$this->expectException(\RuntimeException::class);$this->expectExceptionMessage('template_contract_mode_invalid');app(MarketingTemplateContract::class)->call('eval',[]);}
 public function test_private_or_escaped_asset_paths_are_rejected_for_every_mode():void {
  foreach(['packet','creative','checker','validate','check_result','props','music'] as $mode){try{app(MarketingTemplateContract::class)->call($mode,['asset_index'=>['x'=>['path'=>base_path('.env')]]]);$this->fail('Asset escaped public root');}catch(\RuntimeException $e){$this->assertSame('template_asset_path_invalid',$e->getMessage());}}
 }
 public function test_local_worker_can_validate_response_and_compose_music_parameters_without_transport():void {
  config(['marketing.editing.python_binary'=>'C:/Python312/python.exe']);
  $service=app(MarketingTemplateContract::class);
  $this->assertSame('invalid_response',$service->call('validate',['packet'=>[],'response'=>[]])['status']);
  $this->assertSame(['enabled'=>false],$service->call('music',['draft'=>['music'=>['enabled'=>false]]]));
 }
 public function test_catalog_packet_builds_provider_payload_and_renderer_props_from_one_public_asset():void {
  config(['marketing.editing.python_binary'=>'C:/Python312/python.exe']);
  $file=public_path('contract-test-'.bin2hex(random_bytes(6)).'.png');file_put_contents($file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF3sAAAAASUVORK5CYII='));
  try{
   $svc=app(MarketingTemplateContract::class);$asset=['id'=>'owned','product_id'=>'product-1','path'=>$file,'sha256'=>hash_file('sha256',$file),'rights_confirmed'=>true,'provenance'=>'merchant_photo','width'=>1,'height'=>1];$index=['owned'=>$asset];
   $packet=$svc->call('packet',['product'=>['id'=>'product-1','name'=>'Test product','facts'=>['label'=>'Provided product label'],'description'=>'Untrusted catalog text'],'assets'=>[$asset],'template_id'=>'product_showcase_15s','language'=>'en','goal'=>'Show product']);
   $draft=['template_id'=>'product_showcase_15s','product_id'=>'product-1','language'=>'en','copy'=>['hook'=>'Look closer','benefit'=>'A visible detail','support'=>'Explore the photo','cta'=>'View product'],'details'=>array_fill(0,3,['title'=>'Visible label','copy'=>'','asset_id'=>'owned','position_x'=>.5,'position_y'=>.5,'scale'=>1]),'narration'=>['text'=>'Take a closer look at the visible product label. View this product.','voice_id'=>$packet['voices']['en'],'delivery_brief'=>'Natural and concise','planned_start'=>.4,'planned_end'=>13,'silence_purpose'=>'Leave closing beat'],'music'=>['enabled'=>true,'capability_id'=>'local-original-instrumental','brief'=>'Soft keys','mood'=>'Calm','energy'=>.4],'sound_effects'=>[],'claims'=>[]];
   $payload=$svc->call('creative',['packet'=>$packet,'asset_index'=>$index,'feedback'=>['actual_seconds'=>16],'prior_draft'=>$draft]);
   $this->assertSame([],$payload['tools']);$this->assertStringContainsString('12 to 13.5',$payload['instructions']);$this->assertStringContainsString('Server repair context',$payload['input'][2]['content']);$this->assertStringStartsWith('data:image/png;base64,',$payload['input'][1]['content'][2]['image_url']);
   $valid=$svc->call('validate',['packet'=>$packet,'response'=>['decision'=>'ready','reasons'=>[],'draft'=>$draft]]);$this->assertSame('awaiting_semantic_check',$valid['status']);
   $props=$svc->call('props',['packet'=>$packet,'draft'=>$draft,'asset_index'=>$index]);$this->assertSame('Test product',$props['brief']['name']);$this->assertSame(hash_file('sha256',$file),$props['asset_files']['owned']['sha256']);$this->assertSame('front.png',$props['brief']['source']);
   $packet['catalog_data_kind']='test_sample';$sample=$svc->call('props',['packet'=>$packet,'draft'=>$draft,'asset_index'=>$index]);$this->assertSame('TEST SAMPLE · Fictional specs',$sample['brief']['disclosure']);$sampleCreative=$svc->call('creative',['packet'=>$packet,'asset_index'=>$index]);$this->assertStringContainsString('explicitly fictional test data',$sampleCreative['instructions']);$sampleChecker=$svc->call('checker',['packet'=>$packet,'draft'=>$draft,'asset_index'=>$index]);$this->assertStringContainsString('fictional test sample',$sampleChecker['instructions']);
   $schema=$sampleCreative['text']['format']['schema']['properties']['draft']['anyOf'][0];
   $this->assertSame(38,$schema['properties']['copy']['properties']['support']['maxLength']??null);
   $draft['copy']['support']=str_repeat('wide ',19);$invalid=$svc->call('validate',['packet'=>$packet,'response'=>['decision'=>'ready','reasons'=>[],'draft'=>$draft]]);$this->assertSame('invalid_draft',$invalid['status']);$this->assertContains('display_copy_limit:copy.support',$invalid['errors']);
   $draft['copy']['support']='Explore the photo';
   $draft['narration']['text']=str_repeat('x',301);$this->assertSame('invalid_draft',$svc->call('validate',['packet'=>$packet,'response'=>['decision'=>'ready','reasons'=>[],'draft'=>$draft]])['status']);
  }finally{unlink($file);}
 } public function test_server_module_path_survives_a_web_worker_without_interactive_user_site():void {
  config(['marketing.editing.python_binary'=>'C:/Python312/python.exe','marketing.template.python_module_path'=>null]);
  $oldGet=getenv('APPDATA');$hadEnv=array_key_exists('APPDATA',$_ENV);$oldEnv=$_ENV['APPDATA']??null;
  $empty=sys_get_temp_dir().'/template-no-usersite-'.bin2hex(random_bytes(4));putenv('APPDATA='.$empty);$_ENV['APPDATA']=$empty;
  \Illuminate\Support\Facades\Log::shouldReceive('warning')->once()->withArgs(function($message,$context){return $message==='template_contract_process_failed'&&$context['mode']==='music'&&$context['error_class']==='ModuleNotFoundError'&&$context['missing_module']==='jsonschema'&&!isset($context['stderr']);});
  try{
   try{app(MarketingTemplateContract::class)->call('music',['draft'=>['music'=>['enabled'=>false]]]);$this->fail('Missing dependency should fail safely');}catch(\RuntimeException $e){$this->assertSame('template_contract_failed',$e->getMessage());}
   config(['marketing.template.python_module_path'=>'C:/Users/User/AppData/Roaming/Python/Python312/site-packages']);
   $this->assertSame(['enabled'=>false],app(MarketingTemplateContract::class)->call('music',['draft'=>['music'=>['enabled'=>false]]]));
  }finally{if($oldGet===false)putenv('APPDATA');else putenv('APPDATA='.$oldGet);if($hadEnv)$_ENV['APPDATA']=$oldEnv;else unset($_ENV['APPDATA']);}
 }}
