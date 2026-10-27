<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\{User,MarketingProject,MarketingTemplateGrant,MarketingTemplateRun,MarketingTemplateCall};
use App\Services\Marketing\{MarketingTemplateBilling,MarketingTemplateProjects};
class MarketingTemplateBillingTest extends TestCase {
 use RefreshDatabase;
 private function runRecord(int $cap=1000000):MarketingTemplateRun{
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$p=MarketingProject::create(['owner_id'=>$u->id,'source_type'=>'product','product_id'=>102,'name'=>'T','snapshot'=>['title'=>'T'],'brief'=>[],'template_id'=>MarketingTemplateProjects::ID,'template_version'=>'1.0.0']);
  $g=MarketingTemplateGrant::create(['owner_id'=>$u->id,'product_id'=>102,'cap_microusd'=>$cap,'max_llm'=>8,'max_tts'=>4,'active'=>true,'policy'=>['old_unknown_reserved_microusd'=>100000],'pricing'=>[],'expires_at'=>now()->addDay()]);
  return MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$u->id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('a',64),'input'=>['project_version'=>1],'status'=>'planning','phase'=>'creative']);
 }
 public function test_same_stage_reuses_known_receipt_and_payload_change_is_rejected():void{
  $r=$this->runRecord();$calls=0;$s=app(MarketingTemplateBilling::class);$send=function($id)use(&$calls){$calls++;return ['actual_microusd'=>100,'data'=>['value'=>'original'],'server_request_id'=>'req-test'];};
  $a=$s->call($r,'creative-0','llm',['model'=>'fixed'],1000,$send);$b=$s->call($r,'creative-0','llm',['model'=>'fixed'],1000,$send);$this->assertSame($a,$b);$this->assertSame(1,$calls);
  try{$s->call($r,'creative-0','llm',['model'=>'changed'],1000,$send);$this->fail();}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}
 }
 public function test_disconnect_is_unknown_and_blocks_new_charge_without_releasing_reservation():void{
  $r=$this->runRecord();$s=app(MarketingTemplateBilling::class);
  try{$s->call($r,'creative-0','llm',['model'=>'fixed'],1000,fn()=>throw new \RuntimeException('secret must not persist'));$this->fail();}catch(\RuntimeException $e){$this->assertSame('provider_outcome_unknown',$e->getMessage());}
  $c=MarketingTemplateCall::firstOrFail();$this->assertSame('unknown',$c->status);$this->assertNull($c->actual_microusd);$this->assertStringNotContainsString('secret',json_encode($c->receipt));
  try{$s->call($r,'checker-0','llm',[],1000,fn()=>$this->fail('no new request'));$this->fail();}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}
  $this->assertSame(1000,$s->summary($r->grant_id)['reserved_microusd']);
 }
 public function test_budget_and_counts_are_claimed_before_transport():void{
  $r=$this->runRecord(900);$s=app(MarketingTemplateBilling::class);try{$s->call($r,'creative-0','llm',[],1000,fn()=>$this->fail('over cap'));$this->fail();}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}$this->assertDatabaseCount('marketing_template_calls',0);
 }
 public function test_usage_missing_or_outside_reserved_bound_is_unknown():void{
  $r=$this->runRecord();$s=app(MarketingTemplateBilling::class);try{$s->call($r,'creative-0','llm',[],1000,fn()=>['data'=>['output'=>'preserved']]);$this->fail();}catch(\RuntimeException $e){$this->assertSame('provider_cost_unknown',$e->getMessage());}
  $this->assertSame('preserved',MarketingTemplateCall::first()->receipt['data']['output']);$this->assertNull(MarketingTemplateCall::first()->actual_microusd);
 }
}
