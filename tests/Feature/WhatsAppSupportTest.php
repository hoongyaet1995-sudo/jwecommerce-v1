<?php
namespace Tests\Feature;

use App\Models\ManagedSocialAccount;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppSupportTest extends TestCase
{
 protected function setUp():void {
  parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','services.whatsapp.app_id'=>'123','services.whatsapp.app_secret'=>'secret','services.whatsapp.inbound_routing_ready'=>true,'services.facebook.merchant_key'=>'shop']);DB::purge('sqlite');DB::setDefaultConnection('sqlite');
  Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin')->default(false);$t->boolean('is_super_admin')->default(false);$t->timestamp('email_verified_at')->nullable();$t->rememberToken();$t->timestamps();});
  Schema::create('customers',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable();$t->string('email')->nullable()->unique();$t->string('phone')->nullable();$t->string('first_name')->nullable();$t->string('last_name')->nullable();$t->string('avatar')->nullable();$t->string('status')->nullable();$t->json('tags')->nullable();$t->text('notes')->nullable();$t->timestamps();});
  Schema::create('store_settings',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->json('value');$t->timestamps();});
  foreach(['2026_10_02_160000_create_support_chat_tables.php','2026_10_03_100000_add_support_message_actions.php','2026_10_03_180000_create_notifications_table.php','2026_10_04_120000_create_managed_social_accounts.php','2026_10_04_180000_add_messenger_inbound.php','2026_10_04_200000_add_messenger_replies.php','2026_10_06_110000_add_whatsapp_account_metadata.php'] as $file)(require database_path('migrations/'.$file))->up();
  if(file_exists(database_path('migrations/2026_10_06_120000_create_whatsapp_status_receipts.php')))(require database_path('migrations/2026_10_06_120000_create_whatsapp_status_receipts.php'))->up();
  Schema::table('support_messages',function(Blueprint $t){$t->unsignedInteger('ai_settings_version')->nullable();$t->unsignedBigInteger('ai_control_version')->nullable();});
  (require database_path('migrations/2026_10_06_150000_add_customer_channel_identity.php'))->up();
        (require database_path('migrations/2026_10_06_190000_create_support_attachments.php'))->up();
  Http::preventStrayRequests();Bus::fake();
  $staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($staff);
  ManagedSocialAccount::unguarded(fn()=>ManagedSocialAccount::create(['merchant_key'=>'shop','platform'=>'whatsapp','app_id'=>'123','provider_account_id'=>'456','waba_id'=>'789','name'=>'JW','access_token'=>'private','permissions'=>['whatsapp_business_messaging'],'tasks'=>[],'state'=>'connected','inbound_enabled'=>true,'inbound_verified_at'=>now(),'authorized_by'=>$staff->id]));
  $this->assertSame(['shop','123','789','connected',true],[ManagedSocialAccount::first()->merchant_key,ManagedSocialAccount::first()->app_id,ManagedSocialAccount::first()->waba_id,ManagedSocialAccount::first()->state,ManagedSocialAccount::first()->inbound_enabled]);
 }
 private function payload(string $mid='wamid.in'):array {return ['object'=>'whatsapp_business_account','entry'=>[['id'=>'789','changes'=>[['field'=>'messages','value'=>['metadata'=>['phone_number_id'=>'456'],'messages'=>[['from'=>'60123','id'=>$mid,'timestamp'=>(string)now()->timestamp,'type'=>'text','text'=>['body'=>'Hello']]]]]]]]];}
 private function ingest(): SupportConversation {app(\App\Services\Social\WhatsAppInboundService::class)->receive($this->payload());return SupportConversation::firstOrFail();}
 public function test_new_message_reopens_resolved_archive_without_stealing_human_control():void {
  $c=$this->ingest();$c->update(['status'=>'resolved']);$c->notes()->create(['content'=>'[Archive] true']);
  $service=app(\App\Services\Social\WhatsAppInboundService::class);
  $this->assertSame(1,$service->receive($this->payload('wamid.reopen')));
  $this->assertSame('open',$c->fresh()->status);$this->assertSame('[Archive] false',$c->notes()->latest('id')->value('content'));
  foreach(['human_mode','waiting_human'] as $status){$c->update(['status'=>$status]);$this->assertSame(1,$service->receive($this->payload('wamid.'.$status)));$this->assertSame($status,$c->fresh()->status);}
 }
 public function test_inbound_deduplicates_and_emits_whatsapp_alert_once():void {
  $service=app(\App\Services\Social\WhatsAppInboundService::class);$this->assertSame(1,$service->receive($this->payload()));$this->assertSame(0,$service->receive($this->payload()));
  $this->assertSame(1,DB::table('support_messages')->count());$this->assertSame(1,DB::table('notifications')->count());
  $this->assertStringContainsString('WhatsApp',DB::table('notifications')->value('data'));
  Bus::assertDispatchedTimes(\App\Jobs\ProcessWhatsAppSupportReply::class,1);
 }
 public function test_wrong_waba_and_disconnected_account_do_not_ingest():void {
  $p=$this->payload();$p['entry'][0]['id']='999';$this->assertSame(0,app(\App\Services\Social\WhatsAppInboundService::class)->receive($p));
  ManagedSocialAccount::query()->update(['state'=>'disconnected']);$this->assertSame(0,app(\App\Services\Social\WhatsAppInboundService::class)->receive($this->payload()));$this->assertSame(0,SupportConversation::count());
 }
 public function test_reply_is_accepted_once_and_delivered_status_does_not_regress():void {
  $c=$this->ingest();Http::fake(['*'=>Http::response(['messaging_product'=>'whatsapp','contacts'=>[['wa_id'=>'60123']],'messages'=>[['id'=>'wamid.out']]])]);
  $data=['client_message_id'=>(string)Str::uuid(),'content'=>'Reply'];$service=app(\App\Services\Social\WhatsAppReplyService::class);
  $message=$service->send($c,$data,auth()->id());$this->assertSame('accepted',$message->delivery_status);$service->send($c,$data,auth()->id());Http::assertSentCount(1);
  Http::assertSent(fn($r)=>$r['messaging_product']==='whatsapp'&&$r['to']==='60123'&&$r['text']['body']==='Reply');
  $p=$this->payload();unset($p['entry'][0]['changes'][0]['value']['messages']);
  foreach(['delivered','sent','read','sent'] as $state){$p['entry'][0]['changes'][0]['value']['statuses']=[['id'=>'wamid.out','recipient_id'=>'60123','status'=>$state]];app(\App\Services\Social\WhatsAppInboundService::class)->receive($p);}
  $this->assertSame('read',$message->fresh()->delivery_status);
 }
 public function test_expired_customer_window_blocks_freeform_send():void {
  $c=$this->ingest();$c->messages()->update(['created_at'=>now()->subHours(24)]);
  $ready=app(\App\Services\Social\WhatsAppReplyService::class)->readiness($c);$this->assertFalse($ready['canReply']);$this->assertStringContainsString('template',$ready['replyBlockedReason']);Http::assertNothingSent();
 }
 public function test_ambiguous_send_cannot_be_retried():void {
  $c=$this->ingest();Http::fake(['*'=>Http::response('invalid',502)]);$data=['client_message_id'=>(string)Str::uuid(),'content'=>'Reply'];$service=app(\App\Services\Social\WhatsAppReplyService::class);
  $message=$service->send($c,$data,auth()->id());$this->assertSame('unknown',$message->delivery_status);$service->send($c,$data+['retry'=>true],auth()->id());Http::assertSentCount(1);
 }
 public function test_management_has_safe_accounts_and_no_fabricated_oauth():void {
  config(['services.whatsapp.oauth_routing_ready'=>false]);
  $this->mock(\App\Services\Social\SocialHubOAuth::class,fn($m)=>$m->shouldReceive('permitted')->with('whatsapp')->andReturn(['oauthPermitted'=>true,'oauthReason'=>null]));
  $this->getJson('/admin/api/social/whatsapp')->assertOk()->assertJsonPath('configured',false)->assertJsonPath('accounts.0.pageId','456')->assertJsonMissingPath('accounts.0.access_token');
  $this->get('/admin/social/whatsapp/authorize')->assertRedirect('/admin/social/whatsapp?whatsapp=not_configured');
 }
 public function test_staff_cannot_disconnect_or_authorize():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]));
  $this->postJson('/admin/api/social/whatsapp/1/disconnect')->assertForbidden();$this->get('/admin/social/whatsapp/authorize')->assertForbidden();
 }
 public function test_support_endpoint_and_view_use_whatsapp_transport():void {
  $c=$this->ingest();$c->messages()->update(['created_at'=>now()->subHours(25)]);
  $view=app(\App\Services\Support\SupportChatService::class)->adminView($c);$this->assertFalse($view['canReply']);$this->assertSame('WhatsApp customer',$view['customer']['name']);
  $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',['client_message_id'=>(string)Str::uuid(),'content'=>'Reply'])->assertStatus(409);Http::assertNothingSent();
 }
 public function test_ai_uses_shared_faq_and_delivers_once():void {
  $c=$this->ingest();$source=$c->messages()->first();$source->update(['content'=>'Wash?']);
  \App\Models\StoreSetting::create(['key'=>'support_faq','value'=>['version'=>1,'autoReplyEnabled'=>true,'primaryLanguage'=>'en','productCatalog'=>['isEnabled'=>false],'faq'=>['isEnabled'=>true,'items'=>[['id'=>'wash','question'=>'Wash?','answer'=>'Cold wash.','isActive'=>true]]]]]);
  Http::fake(['*'=>Http::response(['messaging_product'=>'whatsapp','contacts'=>[['wa_id'=>'60123']],'messages'=>[['id'=>'wamid.ai']]])]);
  $job=new \App\Jobs\ProcessWhatsAppSupportReply($source->id);$job->handle(app(\App\Services\Support\FaqAutoReplyService::class),app(\App\Services\Social\WhatsAppReplyService::class));$job->handle(app(\App\Services\Support\FaqAutoReplyService::class),app(\App\Services\Social\WhatsAppReplyService::class));
  $reply=$c->messages()->where('sender_role','ai')->firstOrFail();$this->assertSame('Cold wash.',$reply->content);$this->assertSame('accepted',$reply->delivery_status);Http::assertSentCount(1);
 }
 public function test_status_received_before_api_response_is_not_lost():void {
  $c=$this->ingest();
  Http::fake(function(){
   $p=$this->payload();unset($p['entry'][0]['changes'][0]['value']['messages']);$p['entry'][0]['changes'][0]['value']['statuses']=[['id'=>'wamid.race','recipient_id'=>'60123','status'=>'delivered']];
   app(\App\Services\Social\WhatsAppInboundService::class)->receive($p);
   return Http::response(['messaging_product'=>'whatsapp','contacts'=>[['wa_id'=>'60123']],'messages'=>[['id'=>'wamid.race']]]);
  });
  $message=app(\App\Services\Social\WhatsAppReplyService::class)->send($c,['client_message_id'=>(string)Str::uuid(),'content'=>'Reply'],auth()->id());
  $this->assertSame('delivered',$message->fresh()->delivery_status);
 }
 public function test_failed_receipt_can_follow_sent_receipt():void {
  $c=$this->ingest();$message=$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'human_agent','content'=>'Reply','delivery_status'=>'sent','provider_message_id'=>'wamid.failed']);
  $p=$this->payload();unset($p['entry'][0]['changes'][0]['value']['messages']);$p['entry'][0]['changes'][0]['value']['statuses']=[['id'=>'wamid.failed','recipient_id'=>'60123','status'=>'failed']];
  app(\App\Services\Social\WhatsAppInboundService::class)->receive($p);$this->assertSame('failed',$message->fresh()->delivery_status);
 }
 public function test_subscription_requires_owning_app_confirmation():void {
  $a=ManagedSocialAccount::first();$a->update(['inbound_enabled'=>false,'inbound_verified_at'=>null,'permissions'=>['whatsapp_business_management','whatsapp_business_messaging']]);
  Http::fake(['*/789/subscribed_apps*'=>Http::response(['data'=>[['whatsapp_business_api_data'=>['id'=>'123']]]]),'*/456*'=>Http::response(['id'=>'456','status'=>'CONNECTED','platform_type'=>'CLOUD_API'])]);
  app(\App\Services\Social\WhatsAppSubscriptionService::class)->activate($a);$this->assertTrue($a->fresh()->inbound_enabled);$this->assertNotNull($a->fresh()->inbound_verified_at);
 }
 public function test_transport_timeout_is_unknown_and_never_retried():void {
  $c=$this->ingest();$attempts=0;Http::fake(function()use(&$attempts){$attempts++;throw new \Illuminate\Http\Client\ConnectionException('private timeout');});
  $data=['client_message_id'=>(string)Str::uuid(),'content'=>'Reply'];$s=app(\App\Services\Social\WhatsAppReplyService::class);$m=$s->send($c,$data,auth()->id());$this->assertSame('unknown',$m->delivery_status);$s->send($c,$data+['retry'=>true],auth()->id());$this->assertSame(1,$attempts);$this->assertStringNotContainsString('private',$m->send_error);
 }
 public function test_generated_ai_is_cancelled_after_takeover_and_newer_customer_turn():void {
  \App\Models\StoreSetting::create(['key'=>'support_faq','value'=>['version'=>1,'autoReplyEnabled'=>true]]);$c=$this->ingest();$source=$c->messages()->first();
  foreach(['human_mode','open'] as $state){$c->update(['status'=>$state]);if($state==='open')$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'New turn']);
   $draft=$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'ai','content'=>'Draft','ai_settings_version'=>1,'ai_control_version'=>0]);app(\App\Services\Social\WhatsAppReplyService::class)->sendGenerated($draft,$source);$this->assertSame('failed',$draft->fresh()->delivery_status);
  }Http::assertNothingSent();
 }
 public function test_native_selection_saves_verified_account_and_activates_subscription():void {
  config(['services.whatsapp.embedded_signup_config_id'=>'321','services.whatsapp.oauth_routing_ready'=>true]);$tx=(string)Str::uuid();
  $this->mock(\App\Services\Social\SocialHubOAuth::class,function($m)use($tx){$m->shouldReceive('fingerprint')->andReturn('fixture');$m->shouldReceive('permitted')->with('whatsapp')->andReturn(['oauthPermitted'=>true]);$m->shouldReceive('call')->once()->with('select',['transaction_id'=>$tx,'page_ids'=>['456']],'whatsapp')->andReturn(['app_id'=>'123','pages'=>[['id'=>'456','waba_id'=>'789','access_token'=>'private']]]);});
  Http::fake(['*/debug_token*'=>Http::response(['data'=>['is_valid'=>true,'app_id'=>'123','scopes'=>['whatsapp_business_management','whatsapp_business_messaging']]]),'*/789/phone_numbers*'=>Http::response(['data'=>[['id'=>'456','verified_name'=>'JW']]]),'*/789/subscribed_apps*'=>Http::response(['data'=>[['whatsapp_business_api_data'=>['id'=>'123']]]]),'*/456*'=>Http::response(['id'=>'456','status'=>'CONNECTED','platform_type'=>'CLOUD_API'])]);
  $pending=\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['actor'=>auth()->id(),'merchant'=>'shop','app'=>'123','transaction'=>$tx,'installation'=>'fixture','expires'=>now()->addMinutes(5)->timestamp,'pages'=>[['id'=>'456','name'=>'JW']]]));
  $this->withSession(['whatsapp_pending'=>$pending])->postJson('/admin/api/social/whatsapp/select',['page_ids'=>['456']])->assertOk()->assertJsonPath('accounts.0.messagingReady',true);$this->assertSame('embedded_signup',ManagedSocialAccount::first()->connection_method);
 }
 public function test_unregistered_phone_never_becomes_ready():void {
  $a=ManagedSocialAccount::first();$a->update(['permissions'=>['whatsapp_business_management','whatsapp_business_messaging']]);
  Http::fake(['*/789/subscribed_apps*'=>Http::response(['data'=>[['whatsapp_business_api_data'=>['id'=>'123']]]]),'*/456*'=>Http::response(['id'=>'456','status'=>'PENDING','platform_type'=>'CLOUD_API'])]);app(\App\Services\Social\WhatsAppSubscriptionService::class)->activate($a);$this->assertFalse($a->fresh()->inbound_enabled);$this->assertStringContainsString('registration',$a->fresh()->messaging_error);
 }

 public function test_image_send_uploads_media_and_sends_caption():void {
  \Illuminate\Support\Facades\Storage::fake('support-private');$c=$this->ingest();
  $a=$this->post('/admin/api/support/conversations/'.$c->id.'/attachments',['image'=>\Illuminate\Http\UploadedFile::fake()->image('photo.png')],['Accept'=>'application/json'])->assertCreated()->json('attachment');
  Http::fake(['*/media'=>Http::response(['id'=>'999']),'*/messages'=>Http::response(['messaging_product'=>'whatsapp','contacts'=>[['wa_id'=>'60123']],'messages'=>[['id'=>'wamid.image']]])]);
  $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',['content'=>'Your photo','attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertOk()->assertJsonPath('sendStatus','accepted');
  Http::assertSent(fn($r)=>str_ends_with($r->url(),'/messages') && $r['type']==='image' && $r['image']['id']==='999' && $r['image']['caption']==='Your photo');
  Http::assertSentCount(2);
 }
 public function test_image_caption_over_provider_limit_is_rejected_before_reservation():void {
  \Illuminate\Support\Facades\Storage::fake('support-private');$c=$this->ingest();
  $a=$this->post('/admin/api/support/conversations/'.$c->id.'/attachments',['image'=>\Illuminate\Http\UploadedFile::fake()->image('photo.png')],['Accept'=>'application/json'])->assertCreated()->json('attachment');
  $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',['content'=>str_repeat('x',1025),'attachment_id'=>$a['id'],'client_message_id'=>(string)Str::uuid()])->assertUnprocessable();
  $this->assertSame(1,$c->messages()->count());Http::assertNothingSent();
 }
 public function test_image_inbound_is_durable_and_never_generates_media_ai():void {
  $p=$this->payload();$event=&$p['entry'][0]['changes'][0]['value']['messages'][0];unset($event['text']);$event['type']='image';$event['image']=['id'=>'999'];
  $this->assertSame(1,app(\App\Services\Social\WhatsAppInboundService::class)->receive($p));
  $this->assertSame(1,\App\Models\SupportAttachment::count());
  Bus::assertDispatched(\App\Jobs\DownloadSupportImage::class);Bus::assertNotDispatched(\App\Jobs\ProcessWhatsAppSupportReply::class);
 }
}
