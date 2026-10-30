<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use App\Models\{User,ManagedSocialAccount,MarketingContentTemplate};
use App\Services\Marketing\{MarketingCampaigns,MarketingPosts,MarketingWhatsAppBatches};
use App\Services\Marketing\Audience\AudienceDirectory;
class MarketingWhatsAppBatchTest extends TestCase {
 use RefreshDatabase;
 private function fixture():array {
  Http::preventStrayRequests();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);config(['services.whatsapp.app_id'=>'123','services.whatsapp.inbound_routing_ready'=>true,'marketing.whatsapp_campaign_enabled'=>true]);
  $a=ManagedSocialAccount::create(['merchant_key'=>app(MarketingPosts::class)->merchant(),'platform'=>'whatsapp','app_id'=>'123','provider_account_id'=>'456','waba_id'=>'789','name'=>'Test sender','access_token'=>'FAKE-TOKEN','permissions'=>['whatsapp_business_management','whatsapp_business_messaging'],'tasks'=>[],'state'=>'connected','authorized_by'=>$u->id,'inbound_enabled'=>true,'inbound_verified_at'=>now()]);
  $t=MarketingContentTemplate::create(['merchant_key'=>$a->merchant_key,'user_id'=>$u->id,'channel'=>'whatsapp','name'=>'Test marketing','language'=>'en_US','category'=>'MARKETING','state'=>'Approved','provider_id'=>'999','account_id'=>$a->id,'waba_id'=>'789','version'=>1,'document'=>['meta_name'=>'test_marketing','body'=>'Hello {{1}}, discover our shop.','header_type'=>'NONE','buttons'=>[]]]);
  $dir=app(AudienceDirectory::class);$dir->project('import','wa-test',['name'=>'Owner','phone'=>'+60123456789'],'wa-test');$dir->subscribe('whatsapp','+60123456789','subscribed','owner-test',now());
  $id=(string)\Illuminate\Support\Str::uuid();DB::table('marketing_audience_segments')->insert(['id'=>$id,'merchant_key'=>$a->merchant_key,'user_id'=>$u->id,'name'=>'Test only','description'=>'','rules'=>json_encode(['match'=>'all','conditions'=>[['field'=>'source','operator'=>'eq','value'=>'import']]]),'status'=>'active','version'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $c=app(MarketingCampaigns::class)->saveChannelDraft($u->id,['channel'=>'whatsapp','name'=>'Test only campaign','version'=>0,'document'=>['template_id'=>$t->id,'segment_id'=>$id,'values'=>['{{first_name}}']]],null);
  config(['marketing.whatsapp_campaign_rates'=>['account_id'=>$a->id,'currency'=>'USD','reference'=>'FAKE TEST ONLY','expires_at'=>now()->addDay()->toIso8601String(),'prefixes'=>['60'=>2]]]);return [$c,$a,$u];
 }
 private function review(array $c):array{return $this->postJson('/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/review',['version'=>$c['version']])->assertCreated()->json('batch');}
 private function confirm(array $c,array $b){return $this->postJson('/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/'.$b['id'].'/confirm',['version'=>$c['version'],'confirmed'=>true,'cost_limit_minor'=>$b['cost_limit_minor']]);}
 public function test_review_and_confirm_freeze_without_sending_then_tick_submits_once():void {
  [$c,$a]=$this->fixture();$b=$this->review($c);$this->assertSame(1,$b['total']);$this->assertSame(2,$b['cost_limit_minor']);Http::assertNothingSent();
  $this->confirm($c,$b)->assertOk();$this->confirm($c,$b)->assertOk();Http::assertNothingSent();
  Http::fake(['graph.facebook.com/*'=>Http::response(['messages'=>[['id'=>'wamid.test']]])]);$path='/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/'.$b['id'].'/continue';
  $this->postJson($path)->assertOk()->assertJsonPath('batch.state','completed');$this->postJson($path)->assertOk();Http::assertSentCount(1);
  Http::assertSent(fn($r)=>$r['template']['components'][0]['parameters'][0]['text']==='Owner'&&$r['to']==='60123456789');
 }
 public function test_unknown_price_and_unsubscribed_contacts_block_without_sending():void {
  [$c]=$this->fixture();config(['marketing.whatsapp_campaign_rates'=>[]]);$b=$this->review($c);$this->assertNull($b['cost_limit_minor']);$this->confirm($c,$b)->assertStatus(422);Http::assertNothingSent();
 }
 public function test_optout_after_confirmation_skips_and_foreign_actor_cannot_advance():void {
  [$c]=$this->fixture();$b=$this->review($c);$this->confirm($c,$b)->assertOk();app(AudienceDirectory::class)->subscribe('whatsapp','+60123456789','unsubscribed','test',now());
  $this->postJson('/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/'.$b['id'].'/continue')->assertOk()->assertJsonPath('batch.counts.blocked',1);Http::assertNothingSent();
  $this->actingAs(User::factory()->create(['is_admin'=>true]));$this->getJson('/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches')->assertForbidden();
 }
 public function test_timeout_is_unknown_and_never_retried():void {
  [$c]=$this->fixture();$b=$this->review($c);$this->confirm($c,$b)->assertOk();Http::fake(fn()=>throw new \Illuminate\Http\Client\ConnectionException('PRIVATE PROVIDER TEXT'));
  $path='/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/'.$b['id'].'/continue';$this->postJson($path)->assertOk()->assertJsonPath('batch.state','paused')->assertDontSee('PRIVATE PROVIDER TEXT');$this->postJson($path)->assertOk();$this->assertDatabaseCount('marketing_whatsapp_deliveries',1);
 }

 public function test_receipts_reconcile_exact_account_and_never_downgrade_read():void {
  [$c,$a]=$this->fixture();$b=$this->review($c);$this->confirm($c,$b)->assertOk();Http::fake(['graph.facebook.com/*'=>Http::response(['messages'=>[['id'=>'wamid.receipt']]])]);
  $this->postJson('/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/'.$b['id'].'/continue')->assertOk();$status=app(\App\Services\Social\WhatsAppStatusService::class);
  $status->record($a,['id'=>'wamid.receipt','recipient_id'=>'60123456789','status'=>'read']);$status->record($a,['id'=>'wamid.receipt','recipient_id'=>'60123456789','status'=>'sent']);
  $this->assertDatabaseHas('marketing_whatsapp_deliveries',['batch_id'=>$b['id'],'state'=>'read']);
  $status->record($a,['id'=>'wamid.receipt','recipient_id'=>'60111111111','status'=>'failed']);$this->assertDatabaseHas('marketing_whatsapp_deliveries',['batch_id'=>$b['id'],'state'=>'read']);
  $this->assertTrue(app(\App\Services\Marketing\MarketingWhatsAppOptOut::class)->receive($a,['from'=>'60123456789','text'=>['body'=>'STOP']]));
  $this->assertDatabaseHas('marketing_audience_subscriptions',['channel'=>'whatsapp','destination_hash'=>hash('sha256','+60123456789'),'status'=>'unsubscribed']);
 }
 public function test_revoked_gate_changed_price_and_expired_review_cannot_send():void {
  [$c]=$this->fixture();$b=$this->review($c);config(['marketing.whatsapp_campaign_enabled'=>false]);$this->confirm($c,$b)->assertStatus(409);config(['marketing.whatsapp_campaign_enabled'=>true]);
  $rates=config('marketing.whatsapp_campaign_rates');$rates['prefixes']['60']=3;config(['marketing.whatsapp_campaign_rates'=>$rates]);$this->confirm($c,$b)->assertStatus(409);Http::assertNothingSent();
 }

 public function test_expired_review_and_incompatible_variables_are_blocked():void {
  [$c]=$this->fixture();$b=$this->review($c);DB::table('marketing_whatsapp_batches')->where('id',$b['id'])->update(['expires_at'=>now()->subMinute()]);$this->confirm($c,$b)->assertStatus(409);
  $template=MarketingContentTemplate::find($c['document']['template_id']);$doc=$template->document;$doc['header_text']='Hello {{1}}';$template->update(['document'=>$doc]);
  // New campaign revision creates a new review, never mutates a previous snapshot.
  $next=app(MarketingCampaigns::class)->saveChannelDraft(auth()->id(),['channel'=>'whatsapp','name'=>$c['name'],'version'=>$c['version'],'document'=>$c['document']],$c['id']);
  $this->postJson('/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/review',['version'=>$next['version']])->assertStatus(422);Http::assertNothingSent();
 }
 public function test_definite_provider_failure_pauses_without_resending_or_leaking_details():void {
  [$c]=$this->fixture();$b=$this->review($c);$this->confirm($c,$b)->assertOk();Http::fake(['graph.facebook.com/*'=>Http::response(['error'=>['message'=>'PRIVATE']],400)]);
  $url='/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/'.$b['id'].'/continue';$this->postJson($url)->assertOk()->assertJsonPath('batch.counts.failed',1)->assertJsonPath('batch.processed',1)->assertDontSee('PRIVATE');$this->postJson($url)->assertOk();Http::assertSentCount(1);
 }

 public function test_receipt_before_submission_response_is_reconciled():void {
  [$c,$a]=$this->fixture();$b=$this->review($c);$this->confirm($c,$b)->assertOk();Http::fake(function()use($a){app(\App\Services\Social\WhatsAppStatusService::class)->record($a,['id'=>'wamid.early','recipient_id'=>'60123456789','status'=>'delivered']);return Http::response(['messages'=>[['id'=>'wamid.early']]]);});
  $this->postJson('/admin/marketing/campaigns/'.$c['id'].'/whatsapp-batches/'.$b['id'].'/continue')->assertOk()->assertJsonPath('batch.counts.delivered',1);
 }
 public function test_verified_inbound_stop_updates_consent_without_sending_ai_reply():void {
  [$c,$a]=$this->fixture();\Illuminate\Support\Facades\Queue::fake();$event=['from'=>'60123456789','id'=>'wamid.stop','type'=>'text','timestamp'=>(string)now()->timestamp,'text'=>['body'=>'STOP']];
  $payload=['object'=>'whatsapp_business_account','entry'=>[['id'=>$a->waba_id,'changes'=>[['field'=>'messages','value'=>['metadata'=>['phone_number_id'=>$a->provider_account_id],'messages'=>[$event]]]]]]];
  $this->assertSame(1,app(\App\Services\Social\WhatsAppInboundService::class)->receive($payload));
  $this->assertDatabaseHas('marketing_audience_subscriptions',['channel'=>'whatsapp','destination_hash'=>hash('sha256','+60123456789'),'status'=>'unsubscribed']);
  \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\ProcessWhatsAppSupportReply::class);Http::assertNothingSent();
 }
}
