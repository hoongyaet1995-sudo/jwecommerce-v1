<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Queue,Storage};
use Illuminate\Http\UploadedFile;
use App\Models\{User,Customer};
use App\Services\Marketing\Audience\{AudienceDirectory,AudienceTasks,AudienceSegments};
class MarketingAudienceTest extends TestCase {
 use RefreshDatabase;
 private function actor(){config(['marketing.execution_enabled'=>false]);Http::preventStrayRequests();Queue::fake();Storage::fake('local');$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);return $u;}
 private function drain(string $id){for($i=0;$i<50;$i++){app(AudienceTasks::class)->step($id,true);$t=DB::table('marketing_audience_tasks')->find($id);if(in_array($t->state,['completed','preview','failed']))return $t;}throw new \RuntimeException('Task did not settle');}
 private function sync(){ $id=$this->postJson('/admin/marketing/audience/sync')->assertAccepted()->json('id');return $this->drain($id); }
 public function test_unreachable_customers_are_not_audience_but_reachable_guests_are(){
  $this->actor();$d=app(AudienceDirectory::class);
  $this->assertNull($d->project('customer','empty',['name'=>'Guest','customer_id'=>999],'boundary'));
  $this->assertNull($d->project('customer','invalid',['name'=>'Guest','email'=>'bad','phone'=>'123','customer_id'=>998],'boundary'));
  $id=$d->project('enquiry','reachable',['name'=>'Guest','email'=>'guest@example.com'],'boundary');
  $this->assertSame(1,$d->query()->count());$this->assertFalse($d->eligibility($d->query()->find($id),'email')['eligible']);
  DB::table('marketing_audience_contacts')->where('id',$id)->update(['email'=>null,'email_hash'=>null]);$d->recalculate($id);
  $this->assertSame(0,$d->query()->count());$this->assertDatabaseHas('marketing_audience_contacts',['id'=>$id,'active'=>false]);
 }
 public function test_pages_require_explicit_audience_permission_and_never_send(){ $this->actor();$this->get('/admin/marketing/audience/contacts')->assertOk()->assertSee('Total Contacts')->assertSee('marketing-studio/audience-sync.js')->assertSee('audience-sync-panel');$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$this->actingAs($u)->get('/admin/marketing/audience/contacts')->assertForbidden();$this->postJson('/admin/marketing/audience/exports',[])->assertForbidden();Http::assertNothingSent(); }
 public function test_sync_is_idempotent_and_customer_is_not_subscribed(){ $this->actor();$c=Customer::create(['email'=>'buyer@example.com','first_name'=>'Buyer','phone'=>'+60123456789','status'=>'active']);$this->assertSame('completed',$this->sync()->state);$this->sync();$this->assertDatabaseCount('marketing_audience_contacts',1);$a=DB::table('marketing_audience_contacts')->first();$this->assertSame($c->id,$a->customer_id);$this->assertSame('unknown',app(AudienceDirectory::class)->eligibility($a,'email')['state']); }
 public function test_conflicting_identifiers_are_excluded_instead_of_merged(){ $this->actor();Customer::create(['email'=>'one@example.com','phone'=>'+60123456789','first_name'=>'One']);Customer::create(['email'=>'two@example.com','phone'=>'+60123456789','first_name'=>'Two']);$this->sync();$this->assertSame(2,Customer::count());$a=DB::table('marketing_audience_contacts')->first();$this->assertTrue((bool)$a->conflict);$this->assertFalse(app(AudienceDirectory::class)->eligibility($a,'email')['eligible']); }
 public function test_csv_preview_confirm_is_idempotent_and_does_not_grant_consent(){ $this->actor();$r=$this->post('/admin/marketing/audience/imports',['file'=>UploadedFile::fake()->createWithContent('contacts.csv',"Name,Email,Phone\nExample,example@example.com,+60123456789\nInvalid,not-email,wrong\n"),'mapping'=>['name'=>'Name','email'=>'Email','phone'=>'Phone']],['Accept'=>'application/json'])->assertAccepted();$id=$r->json('id');$this->assertSame('preview',$this->drain($id)->state);$this->assertDatabaseCount('marketing_audience_contacts',0);$this->postJson('/admin/marketing/audience/tasks/'.$id.'/confirm')->assertAccepted();$this->drain($id);$this->postJson('/admin/marketing/audience/tasks/'.$id.'/confirm')->assertAccepted();$this->assertDatabaseCount('marketing_audience_contacts',1);$a=DB::table('marketing_audience_contacts')->first();$this->assertFalse(app(AudienceDirectory::class)->eligibility($a,'email')['eligible']); }
 public function test_segments_validate_rules_and_version_and_show_exclusions(){ $this->actor();Customer::create(['email'=>'one@example.com','first_name'=>'One']);$this->sync();$d=['name'=>'All customers','version'=>0,'rules'=>['match'=>'all','conditions'=>[['field'=>'source','operator'=>'eq','value'=>'customer']]]];$s=$this->postJson('/admin/marketing/audience/segments',$d)->assertCreated()->json('id');$this->postJson('/admin/marketing/audience/segments/'.$s,$d)->assertStatus(409);$this->postJson('/admin/marketing/audience/segments/preview',['rules'=>$d['rules']])->assertOk()->assertJsonPath('matched',1)->assertJsonPath('email.eligible',0);$d['rules']['conditions'][0]['field']='raw_sql';$this->postJson('/admin/marketing/audience/segments',$d)->assertUnprocessable(); }
 public function test_exports_are_private_formula_safe_and_expire(){ $this->actor();Customer::create(['email'=>'one@example.com','first_name'=>'=DANGER']);$this->sync();$id=$this->postJson('/admin/marketing/audience/exports',['filters'=>[]])->assertAccepted()->json('id');$this->assertSame('completed',$this->drain($id)->state);$r=$this->get('/admin/marketing/audience/tasks/'.$id.'/download')->assertOk();$this->assertStringContainsString("'=DANGER",$r->streamedContent());$this->travel(25)->hours();$this->get('/admin/marketing/audience/tasks/'.$id.'/download')->assertStatus(410);Http::assertNothingSent(); }

 public function test_newsletter_opt_in_never_overrides_unsubscribe_or_global_suppression(){
  $this->actor();DB::table('newsletter_subscriptions')->insert(['page_id_snapshot'=>1,'coupon_id_snapshot'=>1,'coupon_code_snapshot'=>'TEST','document_stage'=>'published','section_id'=>'test','section_type'=>'newsletter_signup','email'=>'subscriber@example.com','created_at'=>now(),'updated_at'=>now()]);
  $this->sync();$a=DB::table('marketing_audience_contacts')->first();$dir=app(AudienceDirectory::class);$this->assertTrue($dir->eligibility($a,'email')['eligible']);
  $this->postJson('/admin/marketing/audience/contacts/'.$a->id.'/subscription',['channel'=>'email','status'=>'unsubscribed'])->assertOk();$this->sync();$this->assertSame('unsubscribed',$dir->eligibility($a,'email')['state']);
  $this->postJson('/admin/marketing/audience/contacts/'.$a->id.'/subscription',['channel'=>'global','status'=>'suppressed'])->assertOk();$this->assertSame('suppressed',$dir->eligibility($a,'email')['state']);
  $this->getJson('/admin/marketing/audience/data/contacts')->assertOk()->assertJsonPath('data.0.email_eligibility.state','suppressed')->assertJsonPath('summary.subscribers',0);
 }
 public function test_unknown_and_any_segment_rules_are_bounded_and_foreign_ids_are_denied(){
  $this->actor();Customer::create(['email'=>'one@example.com','first_name'=>'One']);$this->sync();$rules=['match'=>'any','conditions'=>[['field'=>'paid_orders','operator'=>'gte','value'=>1],['field'=>'subscription','operator'=>'eq','channel'=>'email','value'=>'unknown']]];
  $this->postJson('/admin/marketing/audience/segments/preview',['rules'=>$rules])->assertOk()->assertJsonPath('matched',1);
  $id=\Illuminate\Support\Str::uuid();DB::table('marketing_audience_segments')->insert(['id'=>$id,'merchant_key'=>'foreign-store','user_id'=>1,'name'=>'Other','rules'=>json_encode($rules),'version'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $this->getJson('/admin/marketing/audience/segments/'.$id.'/recipients')->assertNotFound();$this->postJson('/admin/marketing/audience/segments/'.$id,['version'=>1,'name'=>'Wrong','rules'=>$rules])->assertNotFound();
 }
 public function test_worker_stops_when_import_or_export_permission_is_revoked(){
  $owner=$this->actor();$staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);foreach(['marketing.view','marketing.view_audience','marketing.manage_audience'] as $p)$staff->permissions()->create(['permission'=>$p]);
  $this->actingAs($staff);$id=$this->postJson('/admin/marketing/audience/sync')->assertAccepted()->json('id');$staff->permissions()->where('permission','marketing.manage_audience')->delete();$t=$this->drain($id);$this->assertSame('failed',$t->state);$this->assertSame('permission_revoked',$t->error_code);$this->assertDatabaseCount('marketing_audience_contacts',0);
 }
 public function test_csv_reimport_does_not_revive_suppression_and_invalid_rows_are_downloadable(){
  $this->actor();Customer::create(['email'=>'one@example.com','first_name'=>'Original']);$this->sync();$a=DB::table('marketing_audience_contacts')->first();$this->postJson('/admin/marketing/audience/contacts/'.$a->id.'/subscription',['channel'=>'email','status'=>'unsubscribed'])->assertOk();
  $d=['file'=>UploadedFile::fake()->createWithContent('again.csv',"Name,Email\nReplacement,one@example.com\nBroken,bad\n"),'mapping'=>['name'=>'Name','email'=>'Email']];$id=$this->post('/admin/marketing/audience/imports',$d,['Accept'=>'application/json'])->assertAccepted()->json('id');$this->drain($id);$this->get('/admin/marketing/audience/tasks/'.$id.'/errors')->assertOk();$this->postJson('/admin/marketing/audience/tasks/'.$id.'/confirm')->assertAccepted();$this->drain($id);$this->assertSame('unsubscribed',app(AudienceDirectory::class)->eligibility($a,'email')['state']);$this->assertSame('Original',Customer::first()->first_name);$this->assertDatabaseCount('marketing_audience_contacts',1);
 }
 public function test_import_and_export_direct_task_ids_require_permissions_and_owner(){
  $this->actor();$id=$this->postJson('/admin/marketing/audience/exports',[])->assertAccepted()->json('id');$this->drain($id);$staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);foreach(['marketing.view','marketing.view_audience','marketing.export_audience'] as $p)$staff->permissions()->create(['permission'=>$p]);$this->actingAs($staff)->get('/admin/marketing/audience/tasks/'.$id.'/download')->assertForbidden();$this->getJson('/admin/marketing/audience/tasks/'.$id)->assertForbidden();
 }
 public function test_paid_values_require_evidence_and_keep_refunds_and_currencies_separate(){
  $this->actor();$c=Customer::create(['email'=>'payer@example.com','first_name'=>'Payer']);
  foreach([['AUD-1','MYR',10000,2000,true],['AUD-2','USD',5000,0,true],['AUD-3','MYR',99999,0,false]] as [$number,$currency,$total,$refund,$verified]){
   $o=\App\Models\Order::create(['number'=>$number,'customer_id'=>$c->id,'email'=>$c->email,'currency'=>$currency,'status'=>'pending','total'=>$total,'subtotal'=>$total]);
   $p=$o->payments()->create(['gateway'=>'bank_transfer','provider'=>'bank_transfer','environment'=>'offline','status'=>'paid','amount'=>$total,'currency'=>$currency,'proof_status'=>$verified?'verified':'pending','proof_reviewed_at'=>$verified?now():null]);if($refund)$o->refunds()->create(['order_payment_id'=>$p->id,'amount'=>$refund,'status'=>'completed','reason'=>'Audience test refund']);
  }$this->sync();$a=DB::table('marketing_audience_contacts')->first();$this->assertSame(2,$a->paid_orders);$this->assertSame(8000,DB::table('marketing_audience_values')->where('contact_id',$a->id)->where('currency','MYR')->value('net_minor'));$this->assertSame(5000,DB::table('marketing_audience_values')->where('contact_id',$a->id)->where('currency','USD')->value('net_minor'));
 }

 public function test_campaign_draft_saves_scoped_segment_version_without_dispatching(){
  $this->actor();$rules=['match'=>'all','conditions'=>[['field'=>'source','operator'=>'eq','value'=>'customer']]];$id=$this->postJson('/admin/marketing/audience/segments',['name'=>'Audience','version'=>0,'rules'=>$rules])->assertCreated()->json('id');
  $r=$this->postJson('/admin/marketing/campaigns/channel-drafts',['name'=>'Audience preparation','version'=>0,'channel'=>'email','document'=>['segment_id'=>$id]])->assertCreated();$campaign=DB::table('marketing_campaigns')->where('id',$r->json('campaign.id'))->first();$doc=json_decode($campaign->channel_document,true);$this->assertSame(1,$doc['segment_version']);$this->assertSame('Draft',$campaign->status);$this->assertDatabaseCount('marketing_email_deliveries',0);
  DB::table('marketing_audience_segments')->where('id',$id)->update(['archived_at'=>now()]);$this->postJson('/admin/marketing/campaigns/channel-drafts',['name'=>'Wrong','version'=>0,'channel'=>'email','document'=>['segment_id'=>$id]])->assertUnprocessable();
 }
 public function test_contact_list_hydrates_related_data_in_batches_and_returns_only_page(){
  $this->actor();for($i=0;$i<30;$i++)Customer::create(['email'=>'batch'.$i.'@example.com','first_name'=>'Batch '.$i]);$this->sync();DB::enableQueryLog();$r=$this->getJson('/admin/marketing/audience/data/contacts?per_page=25')->assertOk()->assertJsonPath('total',30);$this->assertCount(25,$r->json('data'));$this->assertLessThan(25,count(DB::getQueryLog()));DB::disableQueryLog();
 }
 public function test_tags_import_preview_never_updates_customer_master_and_survives_sync(){
  $this->actor();Customer::create(['email'=>'tagged@example.com','first_name'=>'Tagged','tags'=>['original']]);$this->sync();$id=$this->post('/admin/marketing/audience/imports',['file'=>UploadedFile::fake()->createWithContent('tags.csv',"Name,Email,Tags\nTagged,tagged@example.com,VIP;Event\n"),'mapping'=>['name'=>'Name','email'=>'Email','tags'=>'Tags']],['Accept'=>'application/json'])->assertAccepted()->json('id');$this->drain($id);$this->postJson('/admin/marketing/audience/tasks/'.$id.'/confirm')->assertAccepted();$this->drain($id);$this->sync();$a=DB::table('marketing_audience_contacts')->first();$this->assertContains('VIP',json_decode($a->tags,true));$this->assertSame(['original'],Customer::first()->tags);
 }

 public function test_export_freezes_members_and_respects_selected_fields(){
  $this->actor();Customer::create(['email'=>'first@example.com','first_name'=>'First']);$this->sync();$id=$this->postJson('/admin/marketing/audience/exports',['columns'=>['name']])->assertAccepted()->json('id');Customer::create(['email'=>'later@example.com','first_name'=>'Later']);$this->sync();$this->drain($id);$csv=$this->get('/admin/marketing/audience/tasks/'.$id.'/download')->assertOk()->streamedContent();$this->assertStringContainsString('First',$csv);$this->assertStringNotContainsString('Later',$csv);$this->assertStringNotContainsString('first@example.com',$csv);$this->postJson('/admin/marketing/audience/exports',['columns'=>['secret']])->assertUnprocessable();
 }

 public function test_missing_export_part_is_rejected_instead_of_silently_truncated(){
  $this->actor();Customer::create(['email'=>'export@example.com','first_name'=>'Export']);$this->sync();$id=$this->postJson('/admin/marketing/audience/exports',[])->assertAccepted()->json('id');$t=$this->drain($id);$part=json_decode($t->payload,true)['parts'][0]['path'];Storage::disk('local')->delete($part);$this->get('/admin/marketing/audience/tasks/'.$id.'/download')->assertStatus(409);
 }
 public function test_failed_storage_write_does_not_advance_export_checkpoint(){
  $this->actor();Customer::create(['email'=>'export@example.com','first_name'=>'Export']);$this->sync();$id=$this->postJson('/admin/marketing/audience/exports',[])->assertAccepted()->json('id');$disk=\Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);$disk->shouldReceive('put')->once()->andReturn(false);Storage::shouldReceive('disk')->with('local')->andReturn($disk);$t=$this->drain($id);$this->assertSame('failed',$t->state);$this->assertSame(0,$t->processed);$this->assertSame('export_file_incomplete',$t->error_code);
 }

 public function test_reappearing_source_does_not_reactivate_duplicate_eligible_identity(){
  $this->actor();$dir=app(AudienceDirectory::class);$run=(string)\Illuminate\Support\Str::uuid();$a=$dir->project('customer','1',['customer_id'=>1,'name'=>'Original','email'=>'reappear@example.com'],$run);$dir->subscribe('email','reappear@example.com','subscribed','fixture',now());DB::table('marketing_audience_contacts')->where('id',$a)->update(['active'=>false]);$b=$dir->project('import','import-1',['name'=>'Imported','email'=>'reappear@example.com'],$run);$this->assertNotSame($a,$b);$dir->project('customer','1',['customer_id'=>1,'name'=>'Original','email'=>'reappear@example.com'],$run);$this->assertSame(0,app(AudienceSegments::class)->eligible($dir->query(),'email')->count());$this->assertSame(2,$dir->query()->where('conflict',true)->count());
 }

 public function test_legacy_whatsapp_status_without_consent_evidence_does_not_grant_eligibility(){
  $this->actor();Customer::create(['email'=>'wa@example.com','phone'=>'+60123456789','first_name'=>'WA']);$dir=app(AudienceDirectory::class);DB::table('marketing_whatsapp_subscriptions')->insert(['id'=>(string)\Illuminate\Support\Str::uuid(),'merchant_key'=>$dir->merchant(),'phone_hash'=>hash('sha256','+60123456789'),'encrypted_phone'=>\Illuminate\Support\Facades\Crypt::encryptString('+60123456789'),'status'=>'subscribed','source'=>'fixture','consented_at'=>null,'created_at'=>now(),'updated_at'=>now()]);$this->sync();$a=DB::table('marketing_audience_contacts')->first();$this->assertFalse($dir->eligibility($a,'whatsapp')['eligible']);
 }
 public function test_segment_filters_search_status_and_page_size_without_sending(){
  $this->actor();$rules=['match'=>'all','conditions'=>[['field'=>'source','operator'=>'eq','value'=>'customer']]];
  $a=$this->postJson('/admin/marketing/audience/segments',['name'=>'Alpha buyers','version'=>0,'rules'=>$rules])->assertCreated()->json('id');
  $b=$this->postJson('/admin/marketing/audience/segments',['name'=>'Beta buyers','version'=>0,'rules'=>$rules])->assertCreated()->json('id');
  $this->postJson('/admin/marketing/audience/segments/'.$b.'/archive',['version'=>1])->assertOk();
  $this->getJson('/admin/marketing/audience/data/segments?status=active&q=Alpha&per_page=50')->assertOk()->assertJsonPath('total',1)->assertJsonPath('per_page',50)->assertJsonPath('data.0.id',$a);
  $this->getJson('/admin/marketing/audience/data/segments?status=archived')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',$b);
  $this->getJson('/admin/marketing/audience/data/segments?q=unmatched')->assertOk()->assertJsonPath('total',0);
  $this->getJson('/admin/marketing/audience/data/segments?per_page=999')->assertUnprocessable();Http::assertNothingSent();
 }
 public function test_presets_have_full_page_builder_and_valid_rules(){
  $this->actor();$this->get('/admin/marketing/audience/segments/create')->assertOk()->assertSee('Choose a Starting Point')->assertSee('Repeat Buyers')->assertSee('Birthday Month');
  foreach(app(\App\Services\Marketing\Audience\AudienceSegmentPresets::class)->all() as $key=>$p){if(!$p['available'])continue;$this->get('/admin/marketing/audience/segments/create?preset='.$key)->assertOk()->assertSee('segment-form',false)->assertSee('Audience Preview');if(count($p['rules']['conditions'])&&$key!=='category_buyers')$this->postJson('/admin/marketing/audience/segments/preview',['rules'=>$p['rules']])->assertOk();}
  $this->get('/admin/marketing/audience/segments/create?preset=invalid')->assertUnprocessable();$this->get('/admin/marketing/audience/segments/create?preset=birthday_month')->assertOk();Http::assertNothingSent();
 }
 public function test_relative_purchase_presets_use_first_and_last_paid_evidence(){
  $this->actor();$dir=app(AudienceDirectory::class);$id=$dir->project('customer','test-customer',['name'=>'Buyer','email'=>'relative@example.test','customer_id'=>null],'test-run');
  $dir->project('order','old-order',['email'=>'relative@example.test','paid_minor'=>1000,'currency'=>'MYR','occurred_at'=>now()->subDays(120)],'test-run');
  $dir->project('order','new-order',['email'=>'relative@example.test','paid_minor'=>1000,'currency'=>'MYR','occurred_at'=>now()->subDays(10)],'test-run');
  $presets=app(\App\Services\Marketing\Audience\AudienceSegmentPresets::class)->all();$svc=app(AudienceSegments::class);
  $this->assertSame(0,$svc->apply($dir->query(),$presets['new_customers']['rules'])->count());$this->assertSame(1,$svc->apply($dir->query(),$presets['active_customers']['rules'])->count());$this->assertSame(0,$svc->apply($dir->query(),$presets['at_risk']['rules'])->count());$this->assertSame(1,$svc->apply($dir->query(),$presets['repeat_buyers']['rules'])->count());
  $this->postJson('/admin/marketing/audience/segments/preview',['rules'=>['match'=>'all','conditions'=>[['field'=>'last_paid_at','operator'=>'between_days_ago','value'=>180,'value_to'=>90]]]])->assertUnprocessable();
 }
 public function test_segment_description_status_and_full_page_edit_preserve_version(){
  $this->actor();$rules=['match'=>'all','conditions'=>[['field'=>'paid_orders','operator'=>'gte','value'=>2]]];$id=$this->postJson('/admin/marketing/audience/segments',['name'=>'Repeat draft','description'=>'Reusable group','status'=>'draft','version'=>0,'rules'=>$rules])->assertCreated()->json('id');
  $this->get('/admin/marketing/audience/segments/'.$id.'/edit')->assertOk()->assertSee('Reusable group')->assertSee('Update Segment');$this->getJson('/admin/marketing/audience/segments/'.$id.'/recipients')->assertConflict();
  $this->assertDatabaseHas('marketing_audience_segments',['id'=>$id,'status'=>'draft','description'=>'Reusable group','version'=>1]);$this->postJson('/admin/marketing/audience/segments/'.$id,['name'=>'Repeat draft','description'=>'Archived group','status'=>'archived','version'=>1,'rules'=>$rules])->assertOk();$this->assertNotNull(DB::table('marketing_audience_segments')->where('id',$id)->value('archived_at'));$this->get('/admin/marketing/audience/segments/'.$id.'/edit')->assertConflict();Http::assertNothingSent();
 }
 public function test_category_preset_requires_successful_order_source_and_catalog_match(){
  $this->actor();$category=\App\Models\Category::create(['name'=>'Audio','slug'=>'audio','is_active'=>true]);$product=\App\Models\Product::create(['category_id'=>$category->id,'title'=>'Audio','handle'=>'audio-product','status'=>'active','base_price'=>1000]);$variant=\App\Models\ProductVariant::create(['product_id'=>$product->id,'sku'=>'SEG-AUDIO','title'=>'Default','price'=>1000,'stock'=>0]);
  $order=\App\Models\Order::create(['number'=>'SEG-CAT','email'=>'category@example.test','currency'=>'MYR','status'=>'pending','total'=>1000,'subtotal'=>1000]);$order->items()->create(['product_variant_id'=>$variant->id,'sku'=>'SEG','title'=>'Audio','quantity'=>1,'unit_price'=>1000,'line_total'=>1000]);
  $dir=app(AudienceDirectory::class);$dir->project('order',(string)$order->id,['email'=>'category@example.test','paid_minor'=>0,'currency'=>'MYR','occurred_at'=>now()],'category-run');$rule=['match'=>'all','conditions'=>[['field'=>'category_purchased','operator'=>'eq','value'=>$category->id]]];
  $svc=app(AudienceSegments::class);$this->assertSame(0,$svc->apply($dir->query(),$rule)->count());$dir->project('order',(string)$order->id,['email'=>'category@example.test','paid_minor'=>1000,'currency'=>'MYR','occurred_at'=>now()],'category-run');$this->assertSame(1,$svc->apply($dir->query(),$rule)->count());Http::assertNothingSent();
 }
 public function test_birthday_sync_month_matching_update_and_clear_without_consent(){
  $this->actor();$customer=Customer::create(['email'=>'birthday@example.test','first_name'=>'Birthday','date_of_birth'=>'1995-02-28']);$this->sync();$dir=app(AudienceDirectory::class);$service=app(AudienceSegments::class);$query=fn($month)=>$service->apply($dir->query(),['match'=>'all','conditions'=>[['field'=>'birthday_month','operator'=>'eq','value'=>$month]]]);
  $this->assertSame('1995-02-28',$customer->fresh()->date_of_birth->format('Y-m-d'));$this->assertSame(1,$query(2)->count());$this->assertSame(0,$query(3)->count());$contact=DB::table('marketing_audience_contacts')->where('customer_id',$customer->id)->first();$this->assertFalse($dir->eligibility($contact,'email')['eligible']);
  $customer->update(['date_of_birth'=>'2000-03-01']);$this->sync();$this->assertSame(0,$query(2)->count());$this->assertSame(1,$query(3)->count());
  $customer->update(['date_of_birth'=>null]);$this->sync();$this->assertSame(0,$query(3)->count());$this->assertNull(DB::table('marketing_audience_contacts')->where('id',$contact->id)->value('birthday_month'));
  $this->postJson('/admin/marketing/audience/segments/preview',['rules'=>['match'=>'all','conditions'=>[['field'=>'birthday_month','operator'=>'eq','value'=>13]]]])->assertUnprocessable();Http::assertNothingSent();
 }
 public function test_future_birth_date_is_rejected(){
  $this->actor();$this->expectException(\Illuminate\Validation\ValidationException::class);Customer::create(['email'=>'future-birthday@example.test','date_of_birth'=>now()->addDay()->format('Y-m-d')]);
 }
 public function test_sync_is_page_driven_resumable_and_does_not_dispatch_worker(){
  $this->actor();Customer::create(['email'=>'page-sync@example.test','first_name'=>'Page']);
  $id=$this->postJson('/admin/marketing/audience/sync')->assertAccepted()->json('id');Queue::assertNothingPushed();
  $this->assertSame($id,$this->postJson('/admin/marketing/audience/sync')->assertAccepted()->json('id'));
  $url='/admin/marketing/audience/tasks/'.$id;$checkpoint=$this->getJson($url)->assertOk()->json('checkpoint');$this->assertNotEmpty($checkpoint);
  $this->postJson($url.'/continue',['checkpoint'=>$checkpoint])->assertOk();$this->assertDatabaseCount('marketing_audience_contacts',1);
  $after=DB::table('marketing_audience_tasks')->where('id',$id)->value('payload');$this->postJson($url.'/continue',['checkpoint'=>$checkpoint])->assertOk();$this->assertSame($after,DB::table('marketing_audience_tasks')->where('id',$id)->value('payload'));
  for($i=0;$i<20;$i++){$status=$this->getJson($url)->assertOk()->json();if($status['state']==='completed')break;$this->postJson($url.'/continue',['checkpoint'=>$status['checkpoint']])->assertOk();}
  $this->assertSame('completed',$this->getJson($url)->json('state'));Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_worker_cannot_advance_sync_without_page_and_continue_requires_permission(){
  $owner=$this->actor();Customer::create(['email'=>'locked-sync@example.test']);$id=$this->postJson('/admin/marketing/audience/sync')->json('id');(new \App\Jobs\ProcessMarketingAudience($id))->handle();$this->assertDatabaseCount('marketing_audience_contacts',0);
  $staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);foreach(['marketing.view','marketing.view_audience'] as $p)$staff->permissions()->create(['permission'=>$p]);$this->actingAs($staff)->postJson('/admin/marketing/audience/tasks/'.$id.'/continue',['checkpoint'=>str_repeat('a',64)])->assertForbidden();
  $this->actingAs($owner)->postJson('/admin/marketing/audience/tasks/'.$id.'/continue',[])->assertUnprocessable();Http::assertNothingSent();
 }
 public function test_import_and_export_are_page_driven_and_checkpoint_safe(){
  $this->actor();$csv="Name,Email\nPage Import,page-import@example.test\n";
  $id=$this->post('/admin/marketing/audience/imports',['file'=>UploadedFile::fake()->createWithContent('contacts.csv',$csv),'mapping'=>['name'=>'Name','email'=>'Email']],['Accept'=>'application/json'])->assertAccepted()->json('id');
  Queue::assertNothingPushed();(new \App\Jobs\ProcessMarketingAudience($id))->handle();$this->assertSame(0,DB::table('marketing_audience_tasks')->find($id)->processed);
  $url='/admin/marketing/audience/tasks/'.$id;$t=$this->getJson($url)->assertOk()->json();$this->assertNotEmpty($t['checkpoint']);
  $preview=$this->postJson($url.'/continue',['checkpoint'=>$t['checkpoint']])->assertOk()->json();$this->assertSame('preview',$preview['state']);$this->assertDatabaseCount('marketing_audience_contacts',0);
  $this->postJson($url.'/continue',['checkpoint'=>$preview['checkpoint']])->assertOk();$this->assertDatabaseCount('marketing_audience_contacts',0);
  $this->postJson($url.'/confirm',[])->assertAccepted();Queue::assertNothingPushed();$apply=$this->getJson($url)->json();
  $this->postJson($url.'/continue',['checkpoint'=>$preview['checkpoint']])->assertOk();$this->assertDatabaseCount('marketing_audience_contacts',0);
  $this->postJson($url.'/continue',['checkpoint'=>$apply['checkpoint']])->assertOk();$this->assertDatabaseCount('marketing_audience_contacts',1);
  $this->postJson($url.'/continue',['checkpoint'=>$apply['checkpoint']])->assertOk();$this->assertSame(1,DB::table('marketing_audience_tasks')->find($id)->processed);
  $this->drain($id);$export=$this->postJson('/admin/marketing/audience/exports',['filters'=>[]])->assertAccepted()->json('id');Queue::assertNothingPushed();
  (new \App\Jobs\ProcessMarketingAudience($export))->handle();$this->assertSame(0,DB::table('marketing_audience_tasks')->find($export)->processed);
  $url='/admin/marketing/audience/tasks/'.$export;$t=$this->getJson($url)->json();$this->postJson($url.'/continue',['checkpoint'=>$t['checkpoint']])->assertOk();$payload=DB::table('marketing_audience_tasks')->find($export)->payload;
  $this->postJson($url.'/continue',['checkpoint'=>$t['checkpoint']])->assertOk();$this->assertSame($payload,DB::table('marketing_audience_tasks')->find($export)->payload);
  $this->drain($export);$this->get($url.'/download')->assertOk();Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_transfer_continuation_requires_kind_permission_owner_and_unexpired_task(){
  $owner=$this->actor();$id=$this->postJson('/admin/marketing/audience/exports',['filters'=>[]])->json('id');$url='/admin/marketing/audience/tasks/'.$id;
  $staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);foreach(['marketing.view','marketing.view_audience','marketing.manage_audience'] as $p)$staff->permissions()->create(['permission'=>$p]);
  $this->actingAs($staff)->postJson($url.'/continue',['checkpoint'=>str_repeat('a',64)])->assertForbidden();
  $this->actingAs($owner);DB::table('marketing_audience_tasks')->where('id',$id)->update(['expires_at'=>now()->subMinute()]);
  $this->getJson($url)->assertOk()->assertJsonPath('state','expired');$this->postJson($url.'/continue',['checkpoint'=>str_repeat('a',64)])->assertStatus(410);Http::assertNothingSent();
 }

 public function test_transfer_batches_resume_over_one_hundred_rows_without_duplicate_parts(){
  $this->actor();$csv="Name,Email\n";for($i=0;$i<105;$i++)$csv.="Batch $i,batch$i@example.test\n";
  $id=$this->post('/admin/marketing/audience/imports',['file'=>UploadedFile::fake()->createWithContent('contacts.csv',$csv),'mapping'=>['name'=>'Name','email'=>'Email']],['Accept'=>'application/json'])->json('id');$url='/admin/marketing/audience/tasks/'.$id;
  $cp=$this->getJson($url)->json('checkpoint');$this->postJson($url.'/continue',['checkpoint'=>$cp])->assertOk()->assertJsonPath('processed',100)->assertJsonPath('state','queued');
  $this->postJson($url.'/continue',['checkpoint'=>$cp])->assertOk()->assertJsonPath('processed',100);$this->assertDatabaseCount('marketing_audience_import_rows',100);
  $this->assertSame('preview',$this->drain($id)->state);$this->assertDatabaseCount('marketing_audience_contacts',0);$this->postJson($url.'/confirm')->assertAccepted();$this->drain($id);$this->assertDatabaseCount('marketing_audience_contacts',105);
  $e=$this->postJson('/admin/marketing/audience/exports',['filters'=>[]])->json('id');$this->drain($e);$out=$this->get('/admin/marketing/audience/tasks/'.$e.'/download')->assertOk()->streamedContent();$this->assertSame(106,count(array_filter(explode("\n",trim($out)))));Queue::assertNothingPushed();Http::assertNothingSent();
 }

}
