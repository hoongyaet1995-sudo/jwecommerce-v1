<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Storage};
use Illuminate\Support\Str;
use App\Models\{User,Product,MarketingChannelConnection,MarketingPostAutomation,MarketingDailyDraftRun};
use App\Services\Marketing\{MarketingAutomationPublisher,MarketingPosts,MarketingPostQueue,MarketingPostAutomations};

class MarketingAutomationPublishingTest extends TestCase
{
 use RefreshDatabase;
 private function fixture():array {
  Http::preventStrayRequests();Storage::fake('local');Storage::fake('public');
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);
  config(['marketing.post_queue_enabled'=>true,'marketing.social_publishing_enabled'=>true,'services.facebook.client_id'=>'auto-test','services.facebook.client_secret'=>'test']);
  $p=Product::create(['title'=>'Lamp','handle'=>'auto-lamp','status'=>'active','description'=>'White lamp','base_price'=>1]);
  $im=imagecreatetruecolor(10,10);ob_start();imagejpeg($im);Storage::disk('public')->put('auto.jpg',ob_get_clean());imagedestroy($im);
  $image=$p->images()->create(['path'=>'auto.jpg','position'=>0]);
  $c=MarketingChannelConnection::create(['merchant_key'=>app(MarketingPosts::class)->merchant(),'platform'=>'facebook','provider_account_id'=>'123','app_id'=>'auto-test','name'=>'Auto page','access_token'=>'TEST','permissions'=>['pages_show_list','pages_read_engagement','pages_manage_posts'],'tasks'=>['CREATE_CONTENT'],'authorized_by'=>$u->id,'state'=>'connected']);
  $c->refresh();
  $v=['id'=>(string)Str::uuid(),'version'=>0,'name'=>'Automatic ideas','preset'=>'spotlight','sources'=>['products'=>[$p->id],'categories'=>[],'collections'=>[],'promotions'=>[],'pages'=>[]],'prompt'=>'Show a useful idea','type'=>'poster','image_mode'=>'single','cta_url'=>'','platforms'=>['Facebook'],'language'=>'English','tone'=>'Professional','theme'=>'#123e5a','days'=>[1],'time'=>'10:00','timezone'=>'Asia/Kuala_Lumpur','schedule_enabled'=>true];
  return [$u,$c,$v,'image:'.$image->id];
 }
 public function test_defaults_off_and_saving_on_requires_explicit_valid_account():void {
  [$u,$c,$v]=$this->fixture();$a=app(MarketingPostAutomations::class)->save($u,$v);
  $this->assertFalse($a->settings['auto_publish']);
  $v['version']=$a->version;$v['auto_publish']=true;
  $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertUnprocessable()->assertJsonValidationErrors('publish_targets');
  $v['publish_targets']=[$c->id];$a=app(MarketingPostAutomations::class)->save($u,$v);
  $this->assertSame([$c->id],$a->settings['publish_targets']);$this->assertSame($c->version,$a->settings['publish_connections'][$c->id]);
  $this->get('/admin/marketing/automation/ai-posts/'.$a->id.'/edit')->assertOk()->assertSee('Automatic Publish Status')->assertSee('Auto page');
  $this->assertDatabaseCount('marketing_post_dispatches',0);Http::assertNothingSent();
 }
 public function test_foreign_account_and_platform_mismatch_are_rejected():void {
  [$u,$c,$v]=$this->fixture();$v+=['auto_publish'=>true,'publish_targets'=>[$c->id]];$v['platforms']=['Instagram'];
  $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertUnprocessable();
  $v['platforms']=['Facebook'];$c->update(['merchant_key'=>'foreign']);
  $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertUnprocessable();
 }
 public function test_publishing_gate_and_revoked_owner_are_rejected():void {
  [$u,$c,$v]=$this->fixture();$v+=['auto_publish'=>true,'publish_targets'=>[$c->id]];
  config(['marketing.social_publishing_enabled'=>false]);$this->postJson('/admin/marketing/automation/ai-posts',$v)->assertStatus(423);
  config(['marketing.social_publishing_enabled'=>true]);$u->update(['is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$u->permissions()->create(['permission'=>'marketing.edit']);$u->permissions()->create(['permission'=>'marketing.approve_cost']);
  $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertForbidden();
 }
 private function completed(bool $enabled=true,int $slides=1):array {
  [$u,$c,$v,$asset]=$this->fixture();$v+=['auto_publish'=>$enabled,'publish_targets'=>$enabled?[$c->id]:[]];
  $a=app(MarketingPostAutomations::class)->save($u,$v);$gen=(string)Str::uuid();
  $assets=[$asset];for($i=1;$i<$slides;$i++){$image=\App\Models\ProductImage::find(substr($asset,6))->replicate();$image->position=$i;$image->save();$assets[]='image:'.$image->id;}
  $post=app(MarketingPosts::class)->write($u->id,'draft',['id'=>(string)Str::uuid(),'version'=>0,'title'=>'Useful lamp','copy'=>'White lamp','targets'=>[],'copies'=>[],'assets'=>$assets,'asset'=>$asset,'format'=>$slides>1?'carousel':'image','type'=>'Post','tz'=>'Asia/Kuala_Lumpur','ai_context'=>['automation_id'=>$a->id,'daily_draft_run'=>$gen,'platform'=>'Facebook']]);
  MarketingDailyDraftRun::create(['id'=>$gen,'user_id'=>$u->id,'merchant_key'=>$a->merchant_key,'status'=>'completed','snapshot'=>[],'post_ids'=>['facebook'=>$post['id']]]);
  $id=(string)Str::uuid();DB::table('marketing_automation_occurrences')->insert(['id'=>$id,'automation_id'=>$a->id,'scheduled_at'=>now(),'local_label'=>'Mon','version'=>$a->version,'settings'=>json_encode($a->settings),'status'=>'running','generation_id'=>$gen,'created_at'=>now(),'updated_at'=>now()]);
  return [$id,$a,$c,$post];
 }
 public function test_completed_scheduled_generation_queues_once_and_worker_publishes_once():void {
  [$id,$a,$c,$post]=$this->completed();$s=app(MarketingAutomationPublisher::class);
  $this->assertSame('queued',$s->publishCompletedOccurrence($id)['state']);$s->publishCompletedOccurrence($id);
  $this->assertDatabaseCount('marketing_post_dispatches',1);$this->assertDatabaseCount('jobs',1);Http::assertNothingSent();
  Http::fake(['*/123/photos'=>Http::response(['id'=>'photo','post_id'=>'123_post'])]);$job=DB::table('marketing_post_dispatches')->value('id');app(MarketingPostQueue::class)->run($job);app(MarketingPostQueue::class)->run($job);
  Http::assertSentCount(1);$this->assertSame('published',$s->publicationForGeneration(DB::table('marketing_automation_occurrences')->find($id)->generation_id)['state']);
 }
 public function test_off_and_manual_generation_never_submit():void {
  [$id,$a,$c,$post]=$this->completed(false);$s=app(MarketingAutomationPublisher::class);
  $this->assertSame('draft_only',$s->publishCompletedOccurrence($id)['state']);$this->assertNull($s->publicationForGeneration((string)Str::uuid()));
  $this->assertDatabaseCount('marketing_post_dispatches',0);Http::assertNothingSent();
 }
 public function test_paused_and_changed_approval_preserve_drafts():void {
  [$id,$a,$c,$post]=$this->completed();$a->update(['schedule_enabled'=>false]);
  $this->assertSame('attention',app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id)['state']);
  $this->assertDatabaseHas('marketing_posts',['id'=>$post['id'],'status'=>'Draft']);$this->assertDatabaseCount('jobs',0);
 }
 public function test_changed_connection_is_not_silently_adopted():void {
  [$id,$a,$c,$post]=$this->completed();$c->increment('version');
  $this->assertSame('attention',app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id)['state']);
  $this->assertDatabaseCount('jobs',0);$this->assertDatabaseHas('marketing_posts',['id'=>$post['id'],'status'=>'Draft']);
 }
 public function test_queue_failure_rolls_back_and_is_not_automatically_retried():void {
  [$id,$a,$c,$post]=$this->completed();config(['queue.connections.marketing-posts.driver'=>'sync']);$s=app(MarketingAutomationPublisher::class);
  $this->assertSame('attention',$s->publishCompletedOccurrence($id)['state']);config(['queue.connections.marketing-posts.driver'=>'database']);$s->publishCompletedOccurrence($id);
  $this->assertDatabaseCount('marketing_post_dispatches',0);$this->assertDatabaseCount('jobs',0);$this->assertDatabaseHas('marketing_posts',['id'=>$post['id'],'status'=>'Draft']);Http::assertNothingSent();
 }
 public function test_tiktok_requires_explicit_visibility_disclosure_and_declaration():void {
  [$u,$c,$v]=$this->fixture();\Illuminate\Support\Facades\URL::forceRootUrl('https://shop.example');\Illuminate\Support\Facades\URL::forceScheme('https');
  config(['services.tiktok.client_id'=>'tk','marketing.external_video_publishing_enabled'=>true,'marketing.tiktok_photo_verified_prefix'=>'https://shop.example/marketing/post-delivery/']);
  $c->update(['platform'=>'tiktok','app_id'=>'tk','permissions'=>['user.info.basic','video.publish'],'expires_at'=>now()->addHour()]);
  $v['platforms']=['TikTok'];$v+=['auto_publish'=>true,'publish_targets'=>[$c->id]];
  $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertUnprocessable();
  $v['publish_options']=[$c->id=>['privacy'=>'SELF_ONLY','allow_comment'=>false,'commercial'=>false,'your_brand'=>false,'branded'=>false,'terms'=>true]];
  $a=app(MarketingPostAutomations::class)->save($u,$v);$this->assertTrue($a->settings['publish_options'][$c->id]['terms']);Http::assertNothingSent();
 }
 public function test_all_drafts_roll_back_when_second_platform_media_is_unavailable():void {
  [$id,$a,$c,$post]=$this->completed();$other=$c->replicate();$other->provider_account_id='456';$other->save();$other->refresh();
  $s=$a->settings;$s['platforms'][]='Instagram';$s['publish_targets'][]=$other->id;$s['publish_connections'][$other->id]=$other->version;$a->update(['settings'=>$s]);
  // Two separate platform drafts; the second has corrupt media. No provider call is allowed.
  $o=DB::table('marketing_automation_occurrences')->find($id);$run=MarketingDailyDraftRun::find($o->generation_id);
  $second=\App\Models\MarketingPost::find($post['id'])->replicate();$second->id=(string)Str::uuid();$doc=$second->document;$doc['asset']='image:999999';$doc['assets']=['image:999999'];$second->document=$doc;$second->save();
  $run->update(['post_ids'=>['Facebook'=>$post['id'],'Instagram'=>$second->id]]);
  // Use the existing Facebook-login Instagram permission contract.
  $other->update(['platform'=>'instagram','app_id'=>'auto-test','permissions'=>['instagram_basic','pages_read_engagement','instagram_content_publish'],'expires_at'=>now()->addHour()]);
  DB::table('marketing_automation_occurrences')->where('id',$id)->update(['settings'=>json_encode($s)]);
  $this->assertTrue(collect(app(MarketingPosts::class)->accounts())->firstWhere('id',$other->id)['canSchedule']);
  $created=0;\App\Models\MarketingPostDispatch::created(function()use(&$created){$created++;});
  $this->assertSame('attention',app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id)['state']);
  $this->assertSame(1,$created,'The first enqueue must succeed before the second fails.');$this->assertDatabaseCount('marketing_post_dispatches',0);$this->assertDatabaseCount('jobs',0);$this->assertDatabaseHas('marketing_posts',['id'=>$post['id'],'status'=>'Draft']);Http::assertNothingSent();
 }
 public function test_incomplete_generation_never_publishes():void {
  [$id,$a,$c,$post]=$this->completed();$o=DB::table('marketing_automation_occurrences')->find($id);MarketingDailyDraftRun::whereKey($o->generation_id)->update(['status'=>'text_unknown']);
  $this->assertSame('attention',app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id)['state']);$this->assertDatabaseCount('jobs',0);Http::assertNothingSent();
 }
 public function test_edited_automation_and_incomplete_generation_do_not_publish():void {
  [$id,$a,$c,$post]=$this->completed();$a->increment('version');app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id);
  $this->assertDatabaseCount('jobs',0);$this->assertDatabaseHas('marketing_automation_occurrences',['id'=>$id,'publication_state'=>'attention']);
 }
 public function test_runner_queues_only_after_completed_generation_and_recovers_before_finish():void {
  [$id,$a,$c,$post]=$this->completed();$o=DB::table('marketing_automation_occurrences')->find($id);
  $a->update(['schedule_epoch'=>now(),'schedule_checked_at'=>now()]);
  \App\Models\MarketingAutomationRun::create(['id'=>$o->generation_id,'automation_id'=>$a->id,'user_id'=>$a->user_id,'merchant_key'=>$a->merchant_key,'status'=>'generating','snapshot'=>[]]);
  $service=$this->mock(MarketingPostAutomations::class);$service->shouldReceive('view')->once()->andReturn(['status'=>'completed','can_step'=>false]);
  config(['marketing.automation_cron_enabled'=>true]);
  // Simulate process exit after the committed handoff but before Runner::finish.
  app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id);
  app(\App\Services\Marketing\MarketingAutomationRunner::class)->tick(\Carbon\CarbonImmutable::now('UTC'));
  $this->assertDatabaseCount('marketing_post_dispatches',1);$this->assertDatabaseHas('marketing_automation_occurrences',['id'=>$id,'status'=>'completed']);Http::assertNothingSent();
 }

 public function test_multi_image_post_keeps_the_whole_ordered_group():void {
  [$id,$a,$c,$post]=$this->completed(true,2);app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id);
  $dispatch=\App\Models\MarketingPostDispatch::firstOrFail();$this->assertSame($post['assets'],$dispatch->payload['assets']);$this->assertSame('carousel',$dispatch->payload['format']);$this->assertDatabaseCount('jobs',1);Http::assertNothingSent();
 }

 public function test_editing_a_generated_draft_requires_fresh_manual_publish_approval():void {
  [$id,$a,$c,$post]=$this->completed();$post['copy']='Changed by the user after generation';
  app(MarketingPosts::class)->write($a->user_id,'draft',$post);
  $this->assertSame('attention',app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id)['state']);
  $this->assertDatabaseHas('marketing_posts',['id'=>$post['id'],'status'=>'Draft','version'=>2]);$this->assertDatabaseCount('marketing_post_dispatches',0);$this->assertDatabaseCount('jobs',0);Http::assertNothingSent();
 }

 public function test_disconnected_account_after_approval_preserves_draft():void {
  [$id,$a,$c,$post]=$this->completed();$c->update(['state'=>'disconnected']);
  $this->assertSame('attention',app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id)['state']);$this->assertDatabaseCount('jobs',0);$this->assertDatabaseHas('marketing_posts',['id'=>$post['id'],'status'=>'Draft']);Http::assertNothingSent();
 }
 public function test_expired_account_without_refresh_access_preserves_draft():void {
  [$id,$a,$c,$post]=$this->completed();$c->update(['expires_at'=>now()->subMinute()]);
  $this->assertSame('attention',app(MarketingAutomationPublisher::class)->publishCompletedOccurrence($id)['state']);$this->assertDatabaseCount('jobs',0);$this->assertDatabaseHas('marketing_posts',['id'=>$post['id'],'status'=>'Draft']);Http::assertNothingSent();
 }

}
