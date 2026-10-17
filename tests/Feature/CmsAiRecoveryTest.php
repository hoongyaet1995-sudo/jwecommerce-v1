<?php
namespace Tests\Feature;
use App\Models\{AiConnection,User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Http\Request;
use Tests\TestCase;
class CmsAiRecoveryTest extends TestCase
{
 protected function setUp():void{
  parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');DB::setDefaultConnection('sqlite');
  Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin')->default(true);$t->boolean('is_super_admin')->default(true);$t->timestamp('email_verified_at')->nullable();$t->rememberToken();$t->timestamps();});
  (require database_path('migrations/2026_10_02_180000_create_ai_connections.php'))->up();(require database_path('migrations/2026_10_17_160000_create_cms_ai_workspaces.php'))->up();
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
 }
 private function state():array{return ['version'=>0,'signature'=>'source-v1','state'=>['step'=>1,'mode'=>'create','working'=>['title'=>'QA','slug'=>'qa','template'=>'standard','sections'=>[]],'prompt'=>'Keep this requirement']];}
 public function test_workspace_recovery_is_private_versioned_and_discard_is_soft():void{
  $owner=auth()->user();$payload=$this->state();$this->putJson('/admin/api/cms/ai-workspaces/test-page',$payload)->assertOk()->assertJsonPath('version',1);
  $this->getJson('/admin/api/cms/ai-workspaces/test-page')->assertOk()->assertJsonPath('data.state.prompt','Keep this requirement');
  $this->putJson('/admin/api/cms/ai-workspaces/test-page',$payload)->assertStatus(409);
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->getJson('/admin/api/cms/ai-workspaces/test-page')->assertJsonPath('data',null);
  $this->actingAs($owner);$this->deleteJson('/admin/api/cms/ai-workspaces/test-page',['version'=>1])->assertOk();$this->getJson('/admin/api/cms/ai-workspaces/test-page')->assertJsonPath('data',null);$this->assertNotNull(DB::table('cms_ai_workspaces')->first()->discarded_at);
 }
 public function test_completed_operation_replays_once_and_result_is_private():void{
  $id=(string)\Illuminate\Support\Str::uuid();$body=['operationId'=>$id,'action'=>'generate','sourcePage'=>'qa'];$request=Request::create('/admin/api/cms/ai-generator','POST',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json'],json_encode($body));$request->setUserResolver(fn()=>auth()->user());
  $calls=0;$next=function()use(&$calls){$calls++;return response()->json(['data'=>['document'=>['title'=>'Recovered']]]);};$middleware=new \App\Http\Middleware\CmsAiTask;
  $first=$middleware->handle($request,$next);$second=$middleware->handle($request,$next);$this->assertSame($first->getContent(),$second->getContent());$this->assertSame(1,$calls);
  $this->getJson('/admin/api/cms/ai-tasks/'.$id)->assertOk()->assertJsonPath('data.status','completed');$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->getJson('/admin/api/cms/ai-tasks/'.$id)->assertNotFound();
 }
 public function test_actual_usage_survives_replay_without_losing_empty_json_objects():void{
  $id=(string)\Illuminate\Support\Str::uuid();$body=json_encode(['operationId'=>$id,'action'=>'generate']);$request=Request::create('/admin/api/cms/ai-generator','POST',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json'],$body);$request->setUserResolver(fn()=>auth()->user());
  $calls=0;$next=function($request)use(&$calls){$calls++;$request->attributes->set('ai_provider_usage',['input_tokens'=>31,'output_tokens'=>12,'total_tokens'=>43]);return response()->json(['data'=>['document'=>['customColors'=>(object)[]]]]);};
  $middleware=new \App\Http\Middleware\CmsAiTask;$first=$middleware->handle($request,$next);$again=$middleware->handle($request,$next);
  $this->assertSame(1,$calls);$this->assertSame($first->getContent(),$again->getContent());$this->assertSame(43,json_decode($first->getContent(),true)['data']['usage']['total_tokens']);$this->assertStringContainsString('"customColors":{}',$first->getContent());
 }
 public function test_unknown_operation_is_not_fabricated():void{$this->getJson('/admin/api/cms/ai-tasks/'.\Illuminate\Support\Str::uuid())->assertNotFound();}
 public function test_workspace_preserves_empty_object_tokens():void{
  $payload=$this->state();$payload['state']['working']['sections']=[['id'=>'one','type'=>'rich_text','title'=>'Example','isVisible'=>true,'content'=>'','customColors'=>(object)[]]];
  $this->putJson('/admin/api/cms/ai-workspaces/tokens',$payload)->assertOk();$response=$this->getJson('/admin/api/cms/ai-workspaces/tokens')->assertOk();$this->assertStringContainsString('"customColors":{}',$response->getContent());$response->assertJsonPath('data.state.working.sections.0.content','');
  $payload['version']=1;$payload['state']=$response->json('data.state');$payload['state']['working']['sections'][0]['customColors']=(object)[];$this->putJson('/admin/api/cms/ai-workspaces/tokens',$payload)->assertOk();
 }
 public function test_running_operation_is_not_reissued():void{
  $id=(string)\Illuminate\Support\Str::uuid();$body=json_encode(['operationId'=>$id,'action'=>'generate']);$request=Request::create('/admin/api/cms/ai-generator','POST',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json'],$body);$request->setUserResolver(fn()=>auth()->user());
  DB::table('cms_ai_tasks')->insert(['id'=>$id,'user_id'=>auth()->id(),'action'=>'generate','request_hash'=>hash('sha256',$body),'status'=>'running','created_at'=>now(),'updated_at'=>now()]);
  try{(new \App\Http\Middleware\CmsAiTask)->handle($request,function(){$this->fail('Must not call the provider again.');});$this->fail('Expected processing conflict.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
 }
}
