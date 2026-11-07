<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Cache};
use App\Models\{MarketingDailyDraftRun,Product};
use App\Services\Marketing\{MarketingCreativeIntelligence,MarketingTrendContext,MarketingAutomationSources};

class MarketingCreativeIntelligenceTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();Http::preventStrayRequests();Cache::flush();\App\Models\User::factory()->create(['id'=>1]);$this->travelTo(\Carbon\Carbon::parse('2026-12-20 09:00:00','Asia/Kuala_Lumpur'));}
 public function test_verified_calendar_window_stops_after_occasion_and_crosses_year():void {
  $service=app(MarketingCreativeIntelligence::class);$s=['market'=>'Singapore','occasions'=>true,'live_trends'=>false,'type'=>'poster'];
  $c=$service->context('company-a',$s);$this->assertContains('Christmas Day',array_column($c['occasions'],'name'));$this->assertContains('New Year’s Day',array_column($c['occasions'],'name'));$this->assertNotEmpty($c['occasions'][0]['source']);
  $this->travelTo(\Carbon\Carbon::parse('2026-12-26','Asia/Kuala_Lumpur'));$c=$service->context('company-a',$s);$this->assertNotContains('Christmas Day',array_column($c['occasions'],'name'));
  $c=$service->context('company-a',array_replace($s,['occasions'=>false]));$this->assertSame([],$c['occasions']);Http::assertNothingSent();
 }
 public function test_memory_is_company_scoped_deduplicated_and_rotates_angle():void {
  foreach([['recent','company-a',now()->subDay()],['foreign','company-b',now()],['old','company-a',now()->subDays(91)]] as [$id,$merchant,$created])MarketingDailyDraftRun::create(['id'=>$id,'user_id'=>1,'merchant_key'=>$merchant,'status'=>'completed','snapshot'=>['mode'=>'free_design','facts'=>['type'=>'poster'],'product_ids'=>[9],'automation'=>['intelligence'=>['angle'=>'everyday-use']]],'creative'=>['angle'=>'A better desk','headline'=>'Small everyday benefits','slides'=>[['prompt'=>'Bright desk scene']]],'receipts'=>[],'post_ids'=>[],'created_at'=>$created]);
  $draft=\App\Models\MarketingPost::create(['user_id'=>1,'merchant_key'=>'company-a','status'=>'Draft','document'=>['deleted_at'=>now()->toIso8601String()]]);$published=\App\Models\MarketingPost::create(['user_id'=>1,'merchant_key'=>'company-a','status'=>'Published','document'=>[]]);MarketingDailyDraftRun::findOrFail('recent')->update(['post_ids'=>['facebook'=>$draft->id,'instagram'=>$published->id]]);
  $c=app(MarketingCreativeIntelligence::class)->context('company-a',['type'=>'poster','occasions'=>false]);$this->assertSame(1,$c['memory']['total']);$this->assertSame('recent',$c['memory']['recent'][0]['run_id']);$this->assertNotSame('everyday-use',$c['angle']);$this->assertSame([9],$c['recent_product_ids']);Http::assertNothingSent();
  $this->assertEqualsCanonicalizing(['Discarded','Published'],$c['memory']['recent'][0]['state']);
 }
 public function test_all_scope_includes_new_resources_and_excludes_inactive_without_saved_ids():void {
  $p=Product::create(['title'=>'Lamp','handle'=>'lamp','status'=>'active','base_price'=>100]);Product::create(['title'=>'Hidden','handle'=>'hidden','status'=>'draft','base_price'=>100]);
  $s=['source_scope'=>'all','sources'=>array_fill_keys(MarketingAutomationSources::KINDS,[]),'prompt'=>'Useful lamp'];$a=app(MarketingAutomationSources::class);$pool=$a->pool($s);$this->assertSame([$p->id],array_column($pool['products'],'id'));
  $next=Product::create(['title'=>'New Lamp','handle'=>'new-lamp','status'=>'active','base_price'=>100]);$this->assertContains($next->id,array_column($a->pool($s)['products'],'id'));Http::assertNothingSent();
 }
 public function test_trends_off_never_queries_and_failed_feed_falls_back():void {
  $s=app(MarketingTrendContext::class);$this->assertSame('off',$s->read('Malaysia',false)['status']);Http::assertNothingSent();
  Http::fake(['trends.google.com/*'=>Http::response('',503)]);$this->assertSame('unavailable',$s->read('Malaysia',true)['status']);Http::assertSentCount(1);
 }
 public function test_trends_only_accept_dated_recent_items_and_cache_public_feed():void {
  Http::fake(['trends.google.com/*'=>Http::response('<rss><channel><item><title>Desk setup</title><pubDate>Sun, 20 Dec 2026 00:00:00 GMT</pubDate></item><item><title>Old topic</title><pubDate>Sun, 01 Nov 2026 00:00:00 GMT</pubDate></item><item><title>Undated</title></item></channel></rss>')]);
  $s=app(MarketingTrendContext::class);$c=$s->read('Malaysia',true);$this->assertSame('available',$c['status']);$this->assertSame(['Desk setup'],array_column($c['items'],'title'));$this->assertStringContainsString('trends.google.com',$c['items'][0]['source']);$s->read('Malaysia',true);Http::assertSentCount(1);
 }
 public function test_all_scope_settings_and_intelligence_reach_selector_and_artwork_without_publishing():void {
  $user=\App\Models\User::findOrFail(1);$user->update(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($user);
  \App\Models\StoreSetting::create(['key'=>'shop_profile','value'=>['data'=>['shopName'=>'Test Company']]]);
  \App\Models\AiConnection::create(['provider'=>'openai','enabled'=>true,'verified_at'=>now(),'text_model'=>'gpt-5.6-luna','api_key'=>'TEST-ONLY','version'=>1,'models'=>[['id'=>'gpt-5.6-luna','text'=>true],['id'=>'gpt-image-2.5-flare','image'=>true]]]);
  \Illuminate\Support\Facades\Storage::fake('public');\Illuminate\Support\Facades\Storage::fake('local');
  $p=Product::create(['title'=>'Desk Lamp','handle'=>'lamp','status'=>'active','base_price'=>100,'description'=>'White lamp.']);$im=imagecreatetruecolor(100,100);ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);\Illuminate\Support\Facades\Storage::disk('public')->put('lamp.png',$bytes);$p->images()->create(['path'=>'lamp.png','position'=>0]);
  $settings=['id'=>(string)\Illuminate\Support\Str::uuid(),'version'=>0,'name'=>'Fresh ideas','preset'=>'spotlight','source_scope'=>'all','sources'=>array_fill_keys(MarketingAutomationSources::KINDS,[]),'prompt'=>'A useful everyday product.','type'=>'poster','image_mode'=>'single','cta_url'=>'','platforms'=>['Facebook'],'language'=>'English','tone'=>'Professional','theme'=>'#123e5a','days'=>[],'time'=>'10:00','timezone'=>'Asia/Kuala_Lumpur','market'=>'Singapore','occasions'=>true,'live_trends'=>false];
  $this->postJson('/admin/marketing/automation/ai-posts',$settings)->assertOk();$saved=\App\Models\MarketingPostAutomation::findOrFail($settings['id']);$this->assertSame('all',$saved->settings['source_scope']);
  $plan=['angle'=>'A useful desk companion','headline'=>'Light for everyday ideas','cta'=>'Explore','variants'=>['facebook'=>"Desk Lamp.\n\nA white lamp for your workspace."],'slides'=>[['headline'=>'Light for everyday ideas','prompt'=>'Complete clean white lamp artwork.']]];
  $image=imagecreatetruecolor(1024,1280);ob_start();imagepng($image);$png=base64_encode(ob_get_clean());imagedestroy($image);
  Http::fake(['api.openai.com/v1/responses'=>function($request)use($p,$plan){$data=str_contains(json_encode($request->data()),'AUTOMATION_SOURCE_SELECTION')?['product_ids'=>[$p->id],'page_ids'=>[],'promotion_ids'=>[],'reason'=>'Useful everyday workspace product','occasion_id'=>null,'trend_id'=>null]:$plan;return Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]]]);},'api.openai.com/v1/images/edits'=>Http::response(['data'=>[['b64_json'=>$png]]])]);
  $id=(string)\Illuminate\Support\Str::uuid();$this->postJson('/admin/marketing/automation/ai-posts/'.$settings['id'].'/generate',['id'=>$id,'version'=>1])->assertOk();
  $this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk();$this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk()->assertJsonPath('status','free_prepared');
  $child=MarketingDailyDraftRun::findOrFail($id);$this->assertSame('Singapore',$child->snapshot['facts']['creative_intelligence']['market']);$this->assertSame('everyday-use',$child->snapshot['facts']['creative_intelligence']['angle']);$this->assertSame('Useful everyday workspace product',$child->snapshot['automation']['selection_reason']);
  Http::assertSent(fn($r)=>str_contains(json_encode($r->data()),'creative_intelligence'));Http::assertSentCount(1);$this->assertDatabaseCount('marketing_posts',0);
  for($i=0;$i<3;$i++)$result=$this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk();$result->assertJsonPath('status','completed');
  $this->get($result->json('review_url'))->assertOk()->assertSee('Why this post')->assertSee('Useful everyday workspace product')->assertSee('Singapore');$post=\App\Models\MarketingPost::firstOrFail();$this->assertSame('Draft',$post->status);$this->assertEmpty($post->document['targets']);
  $settings['id']=(string)\Illuminate\Support\Str::uuid();$this->postJson('/admin/marketing/automation/ai-posts',$settings)->assertOk();$second=(string)\Illuminate\Support\Str::uuid();$this->postJson('/admin/marketing/automation/ai-posts/'.$settings['id'].'/generate',['id'=>$second,'version'=>1])->assertOk();$snapshot=\App\Models\MarketingAutomationRun::findOrFail($second)->snapshot;$this->assertSame(1,$snapshot['intelligence']['memory']['total']);$this->assertNotSame('everyday-use',$snapshot['intelligence']['angle']);
 }
 public function test_repeated_headline_is_rejected_before_image_spend():void {
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingCreativeIntelligence::class)->assertFresh(['headline'=>'A BETTER desk!'],['memory'=>['recent'=>[['headline'=>'A better desk']]]]);
 }
 public function test_occasion_day_and_next_day_are_exact_and_unknown_year_is_evergreen():void {
  $s=app(MarketingCreativeIntelligence::class);$this->travelTo(\Carbon\Carbon::parse('2026-11-08 12:00','Asia/Kuala_Lumpur'));$c=$s->context('a',['market'=>'Malaysia']);$this->assertContains('Deepavali',array_column($c['occasions'],'name'));
  $this->travelTo(\Carbon\Carbon::parse('2026-11-09 12:00','Asia/Kuala_Lumpur'));$this->assertSame([],$s->context('a',['market'=>'Malaysia'])['occasions']);
  $this->travelTo(\Carbon\Carbon::parse('2028-11-08 12:00','Asia/Kuala_Lumpur'));$this->assertSame([],$s->context('a',['market'=>'Malaysia'])['occasions']);
 }
 public function test_all_scope_rotates_bounded_windows_and_pages_without_catalog_preload():void {
  for($i=1;$i<=70;$i++)Product::create(['title'=>'Product '.$i,'handle'=>'p'.$i,'base_price'=>100,'status'=>'active']);
  $s=['source_scope'=>'all','sources'=>array_fill_keys(MarketingAutomationSources::KINDS,[]),'prompt'=>'Useful idea'];$service=app(MarketingAutomationSources::class);$a=$service->pool($s);$b=$service->pool($s+['_rotation'=>1]);$this->assertCount(60,$a['products']);$this->assertSame(70,$a['eligible_products']);$this->assertNotSame(array_column($a['products'],'id'),array_column($b['products'],'id'));
 }
 public function test_xml_entities_are_rejected_without_following_links():void {
  Http::fake(['trends.google.com/*'=>Http::response('<!DOCTYPE rss [<!ENTITY x SYSTEM "http://127.0.0.1/private">]><rss><channel><item><title>&x;</title></item></channel></rss>')]);$this->assertSame('unavailable',app(MarketingTrendContext::class)->read('Malaysia',true)['status']);Http::assertSentCount(1);
 }
 public function test_cached_trend_cannot_outlive_48_hour_freshness_window():void {
  Http::fake(['trends.google.com/*'=>Http::response('<rss><channel><item><title>Workspace</title><pubDate>Fri, 18 Dec 2026 01:00:01 GMT</pubDate></item></channel></rss>')]);$s=app(MarketingTrendContext::class);$this->assertCount(1,$s->read('Malaysia',true)['items']);$this->travel(3)->seconds();$this->assertSame([],$s->read('Malaysia',true)['items']);Http::assertSentCount(1);
 }
 public function test_all_scope_cms_window_rotates_instead_of_always_using_first_ten():void {
  for($i=1;$i<=12;$i++){$p=\App\Models\Page::create(['title'=>'Guide '.$i,'slug'=>'guide-'.$i,'is_published'=>true,'status'=>'published']);$p->documents()->create(['stage'=>'published','slug'=>$p->slug,'schema_version'=>1,'lock_version'=>1,'content_hash'=>hash('sha256',(string)$i),'document'=>['title'=>'Guide '.$i,'sections'=>[]]]);}
  $s=['source_scope'=>'all','sources'=>array_fill_keys(MarketingAutomationSources::KINDS,[]),'prompt'=>'Guide'];$a=app(MarketingAutomationSources::class);$first=$a->pool($s);$second=$a->pool($s+['_rotation'=>1]);$this->assertCount(10,$first['pages']);$this->assertNotSame(array_column($first['pages'],'id'),array_column($second['pages'],'id'));
 }
 public function test_explicit_requested_product_is_not_lost_to_memory_penalty():void {
  $ram=Product::create(['title'=>'Requested RAM','handle'=>'ram','base_price'=>100,'status'=>'active']);for($i=0;$i<65;$i++)Product::create(['title'=>'Unrelated chair '.$i,'handle'=>'chair'.$i,'base_price'=>100,'status'=>'active']);$pool=app(MarketingAutomationSources::class)->pool(['source_scope'=>'all','sources'=>array_fill_keys(MarketingAutomationSources::KINDS,[]),'prompt'=>'RAM','_recent_product_ids'=>[$ram->id],'_rotation'=>5]);$this->assertContains($ram->id,array_column($pool['products'],'id'));
 }
 public function test_rotating_windows_eventually_cover_every_eligible_id():void {
  for($i=1;$i<=121;$i++)Product::create(['title'=>'Item '.$i,'handle'=>'item'.$i,'base_price'=>100,'status'=>'active']);$seen=[];$service=app(MarketingAutomationSources::class);for($i=0;$i<4;$i++)$seen=array_merge($seen,array_column($service->pool(['source_scope'=>'all','sources'=>array_fill_keys(MarketingAutomationSources::KINDS,[]),'prompt'=>'','_rotation'=>$i])['products'],'id'));$this->assertCount(121,array_unique($seen));
 }
 public function test_model_context_is_byte_bounded_while_audit_context_stays_complete():void {
  $context=['angle'=>'everyday-use','market'=>'Malaysia','as_of'=>'2026-12-20','occasions'=>[],'trends'=>['status'=>'off','items'=>[]],'memory'=>['total'=>200,'days'=>90,'recent'=>array_fill(0,12,['headline'=>str_repeat('内容',40),'angle'=>str_repeat('a',180),'visual'=>str_repeat('b',160),'run_id'=>'private-audit-id'])]];$model=app(MarketingCreativeIntelligence::class)->forModel($context,1200);$this->assertLessThanOrEqual(1200,strlen(json_encode($model,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));$this->assertSame('everyday-use',$model['angle']);$this->assertCount(12,$context['memory']['recent']);$this->assertStringNotContainsString('private-audit-id',json_encode($model));
 }
}
