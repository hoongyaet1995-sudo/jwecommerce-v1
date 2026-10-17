<?php
namespace Tests\Feature;

use App\Models\{Product,StoreSetting,SupportMessage};
use App\Services\Support\{SupportChatService,SupportPrivateContextLease};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,DB,Route};
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportPrivateContextLeaseTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void {
  parent::setUp();config(['session.driver'=>'database']);app('session')->forgetDrivers();
  StoreSetting::create(['key'=>'support_faq','value'=>['version'=>1,'pageContext'=>['enabled'=>true,'cart'=>true,'account'=>true],'autoReplyEnabled'=>true]]);
  Route::post('/_test/leased-cart',function(Request $r){$c=app(SupportChatService::class)->sendCustomer($r,['content'=>'Explain current cart','client_message_id'=>(string)Str::uuid(),'page_context'=>['path'=>'/cart']]);$m=$c->messages()->latest('id')->first();app(SupportPrivateContextLease::class)->issue($r,$m);return ['source'=>$m->id];})->middleware('web');
 }
 private function settings():array{return app(\App\Services\SupportFaqService::class)->read();}
 private function source():SupportMessage {
  $p=Product::create(['handle'=>'private-cart','title'=>'LEASE-PRIVATE-CONTAINER','status'=>'active','base_price'=>1000]);$v=$p->variants()->create(['sku'=>'LEASE-PRIVATE','title'=>'Blue','price'=>1000,'enabled'=>true,'stock'=>3]);
  $r=$this->withSession(['cart'=>[$v->id=>2]])->postJson('/_test/leased-cart')->assertOk();return SupportMessage::findOrFail($r->json('source'));
 }
 public function test_worker_resolves_encrypted_lease_without_http_session_or_private_facts_in_trace():void {
  $m=$this->source();$cipher=Cache::get('support-private-context:'.$m->id);$this->assertIsString($cipher);$this->assertStringNotContainsString(session()->getId(),$cipher);$this->assertStringNotContainsString('LEASE-PRIVATE-CONTAINER',json_encode($m->ai_trace));
  app()->instance('request',Request::create('/console'));
  $facts=app(SupportPrivateContextLease::class)->facts($m,$this->settings());$this->assertStringContainsString('LEASE-PRIVATE-CONTAINER',json_encode($facts));$this->assertStringContainsString('"quantity":2',$facts[0]['answer']);
 }
 public function test_logout_or_missing_session_revokes_a_previously_issued_lease():void {
  $m=$this->source();DB::table('sessions')->delete();$this->assertSame([],app(SupportPrivateContextLease::class)->facts($m,$this->settings()));
 }
 public function test_scope_off_settings_change_and_expiry_fail_closed():void {
  $m=$this->source();$s=$this->settings();$s['pageContext']['cart']=false;$this->assertSame([],app(SupportPrivateContextLease::class)->facts($m,$s));
  $s=$this->settings();$s['version']=2;$this->assertSame([],app(SupportPrivateContextLease::class)->facts($m,$s));
  $this->travel(6)->minutes();$this->assertSame([],app(SupportPrivateContextLease::class)->facts($m,$this->settings()));
 }
 public function test_new_source_takeover_and_tampered_cache_are_denied():void {
  $m=$this->source();$m->conversation->update(['status'=>'human_mode']);$this->assertSame([],app(SupportPrivateContextLease::class)->facts($m,$this->settings()));
  $m->conversation->update(['status'=>'open']);Cache::put('support-private-context:'.$m->id,'tampered',300);$this->assertSame([],app(SupportPrivateContextLease::class)->facts($m,$this->settings()));
 }
 public function test_newer_input_and_changed_owner_revoke_lease():void {
  $m=$this->source();$c=$m->conversation;$owner=$c->owner_hash;
  $c->update(['owner_hash'=>hash('sha256','other-owner')]);
  $this->assertSame([],app(SupportPrivateContextLease::class)->facts($m,$this->settings()));
  $c->update(['owner_hash'=>$owner]);
  $c->messages()->create(['sender_role'=>'customer','client_message_id'=>(string)Str::uuid(),'content'=>'New turn']);
  $this->assertSame([],app(SupportPrivateContextLease::class)->facts($m,$this->settings()));
 }
 public function test_worker_publication_rejects_changed_cart_and_unsupported_session_driver():void {
  $m=$this->source();app()->instance('request',Request::create('/console'));
  $lease=app(SupportPrivateContextLease::class);$facts=$lease->facts($m,$this->settings());
  $fingerprint=$lease->fingerprint($facts);$this->assertTrue($lease->publicationAllowed($m,$this->settings(),$fingerprint));
  Product::where('handle','private-cart')->update(['status'=>'draft']);
  $this->assertFalse($lease->publicationAllowed($m,$this->settings(),$fingerprint));
  config(['session.driver'=>'cookie']);$this->assertSame([],$lease->facts($m,$this->settings()));
 }

 public function test_revocation_during_understanding_removes_private_facts_before_answer_generation():void {
  $m=$this->source();app()->instance('request',Request::create('/console'));
  $this->mock(\App\Services\Support\SupportUnderstandingService::class)->shouldReceive('understand')->once()->andReturnUsing(function(){
   DB::table('sessions')->delete();
   return ['tools'=>[['name'=>'commerce.read','query'=>'cart']],'language'=>'en','clarification'=>'none','escalation'=>'none','negotiation'=>false,'questions'=>[]];
  });
  $facts=app(\App\Services\Support\UnifiedKnowledgeService::class)->retrieve('Explain cart',$this->settings(),[],$m->conversation,'en',true);
  $this->assertStringNotContainsString('LEASE-PRIVATE-CONTAINER',json_encode($facts));
  $this->assertFalse(collect($facts)->contains(fn($f)=>$f['id']==='page:cart'));
 }

}
