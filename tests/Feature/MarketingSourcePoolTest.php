<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\{User,Product,Coupon,Collection,MarketingPostAutomation};
use App\Services\Marketing\MarketingAutomationSources;

class MarketingSourcePoolTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();Http::preventStrayRequests();$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));}
 private function settings(array $modes=[]):array {return ['source_scope'=>'selected','source_modes'=>array_replace(array_fill_keys(MarketingAutomationSources::KINDS,'none'),$modes),'sources'=>array_fill_keys(MarketingAutomationSources::KINDS,[]),'prompt'=>'Useful ideas'];}
 private function product(string $title='Desk Lamp',string $status='active'):Product {return Product::create(['title'=>$title,'handle'=>uniqid('product-'),'base_price'=>100,'status'=>$status,'description'=>'Confirmed source facts']);}
 private function voucher(string $code,array $extra=[]):Coupon {return Coupon::create(array_replace(['name'=>$code,'code'=>$code,'discount_type'=>'percentage','value'=>30,'is_active'=>true,'starts_at'=>now()->subDay(),'ends_at'=>now()->addDay()],$extra));}
 public function test_all_promotions_never_broadens_to_products_and_new_valid_vouchers_join():void {
  $this->product();$valid=$this->voucher('VALID');$this->voucher('EXPIRED',['ends_at'=>now()->subMinute()]);$this->voucher('FUTURE',['starts_at'=>now()->addDay()]);$this->voucher('INACTIVE',['is_active'=>false]);$this->voucher('USED',['usage_limit'=>1,'usage_count'=>1]);$s=$this->settings(['promotions'=>'all']);$service=app(MarketingAutomationSources::class);$pool=$service->pool($s);$this->assertSame([],$pool['products']);$this->assertSame([$valid->id],array_column($pool['promotions'],'id'));$new=$this->voucher('NEW');$this->assertContains($new->id,array_column($service->pool($s)['promotions'],'id'));Http::assertNothingSent();
 }
 public function test_all_products_uses_active_catalog_without_promotions():void {
  $p=$this->product();$this->product('Draft','draft');$this->voucher('VALID');$s=$this->settings(['products'=>'all']);$service=app(MarketingAutomationSources::class);$pool=$service->pool($s);$this->assertSame([$p->id],array_column($pool['products'],'id'));$this->assertSame([],$pool['promotions']);$new=$this->product('New product');$this->assertContains($new->id,array_column($service->pool($s)['products'],'id'));
 }
 public function test_specific_collection_and_all_promotions_keep_the_product_boundary():void {
  $p=$this->product();$other=$this->product('Outside pool');$c=Collection::create(['title'=>'Selected collection','slug'=>'selected','is_active'=>true,'type'=>'manual']);$c->products()->attach($p->id);$voucher=$this->voucher('VALID');$s=$this->settings(['collections'=>'items','promotions'=>'all']);$s['sources']['collections']=[$c->id];$pool=app(MarketingAutomationSources::class)->pool($s);$this->assertSame([$p->id],array_column($pool['products'],'id'));$this->assertSame([$voucher->id],array_column($pool['promotions'],'id'));$this->assertNotContains($other->id,array_column($pool['products'],'id'));
 }
 public function test_live_preview_is_bounded_and_shows_real_counts_without_provider_calls():void {
  for($i=0;$i<8;$i++)$this->product('Product '.$i);$this->voucher('VALID');$this->voucher('OLD',['ends_at'=>now()->subDay()]);$s=$this->settings(['products'=>'all','promotions'=>'all']);$response=$this->postJson('/admin/marketing/automation/source-preview',$s)->assertOk()->assertJsonPath('groups.0.count',8)->assertJsonPath('groups.1.count',1);$this->assertCount(3,$response->json('groups.0.items'));$this->assertCount(1,$response->json('groups.1.items'));$this->assertNotEmpty($response->json('as_of'));Http::assertNothingSent();$this->assertDatabaseCount('marketing_daily_draft_runs',0);
 }
 public function test_saved_modes_round_trip_and_old_selected_settings_stay_specific():void {
  $p=$this->product();$s=$this->settings(['products'=>'all','promotions'=>'all'])+['id'=>(string)\Illuminate\Support\Str::uuid(),'version'=>0,'name'=>'All products and offers','preset'=>'offers','type'=>'promotion','image_mode'=>'single','cta_url'=>'','platforms'=>['Facebook'],'language'=>'English','tone'=>'Professional','theme'=>'#123e5a','days'=>[],'time'=>'10:00','timezone'=>'Asia/Kuala_Lumpur'];$this->postJson('/admin/marketing/automation/ai-posts',$s)->assertOk();$this->assertSame('all',MarketingPostAutomation::findOrFail($s['id'])->settings['source_modes']['promotions']);
  $s['id']=(string)\Illuminate\Support\Str::uuid();unset($s['source_modes']);$s['sources']['products']=[$p->id];$this->postJson('/admin/marketing/automation/ai-posts',$s)->assertOk();$this->assertSame('items',MarketingPostAutomation::findOrFail($s['id'])->settings['source_modes']['products']);$this->assertSame('none',MarketingPostAutomation::findOrFail($s['id'])->settings['source_modes']['promotions']);
 }
 public function test_preview_requires_permission_and_invalid_modes_are_rejected():void {
  $s=$this->settings(['products'=>'all']);$s['source_modes']['promotions']='everything';$this->postJson('/admin/marketing/automation/source-preview',$s)->assertUnprocessable();$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]));$this->postJson('/admin/marketing/automation/source-preview',$this->settings(['products'=>'all']))->assertForbidden();
 }
 public function test_unnamed_voucher_is_visible_and_searchable_by_code():void {
  $voucher=$this->voucher('VISIBLE-CODE',['name'=>null]);$this->postJson('/admin/marketing/automation/source-preview',$this->settings(['promotions'=>'all']))->assertOk()->assertJsonPath('groups.0.items.0.label','VISIBLE-CODE');$this->getJson('/admin/marketing/automation/sources?kind=promotions&q=VISIBLE')->assertOk()->assertJsonPath('items.0.label','VISIBLE-CODE');
 }
 public function test_promotion_only_range_creates_mocked_draft_without_importing_products():void {
  \Illuminate\Support\Facades\Queue::fake();\Illuminate\Support\Facades\Storage::fake('local');\Illuminate\Support\Facades\Storage::fake('public');$this->product('Outside product pool');$voucher=$this->voucher('PROMO-ONLY',['name'=>null]);
  \App\Models\StoreSetting::create(['key'=>'shop_profile','value'=>['data'=>['shopName'=>'Test Company']]]);\App\Models\AiConnection::create(['provider'=>'openai','enabled'=>true,'verified_at'=>now(),'text_model'=>'gpt-5.6-luna','api_key'=>'TEST-ONLY','version'=>1,'models'=>[['id'=>'gpt-5.6-luna','text'=>true],['id'=>'gpt-image-2.5-flare','image'=>true]]]);
  $settings=$this->settings(['promotions'=>'all'])+['id'=>(string)\Illuminate\Support\Str::uuid(),'version'=>0,'name'=>'Promotion only','preset'=>'offers','type'=>'promotion','image_mode'=>'single','cta_url'=>'','platforms'=>['Facebook'],'language'=>'English','tone'=>'Professional','theme'=>'#123e5a','days'=>[],'time'=>'10:00','timezone'=>'Asia/Kuala_Lumpur'];$this->postJson('/admin/marketing/automation/ai-posts',$settings)->assertOk();
  $plan=['angle'=>'Explore the current welcome offer','headline'=>'Explore this welcome offer','cta'=>'See voucher terms','variants'=>['facebook'=>'Learn about PROMO-ONLY and confirm applicable voucher conditions.'],'slides'=>[['headline'=>'Explore this welcome offer','prompt'=>'A complete brand promotional artwork for PROMO-ONLY with no invented products or prices.']]];$image=imagecreatetruecolor(1024,1280);ob_start();imagepng($image);$png=base64_encode(ob_get_clean());imagedestroy($image);
  Http::fake(['api.openai.com/v1/responses'=>function($request)use($voucher,$plan){$selection=str_contains(json_encode($request->data()),'AUTOMATION_SOURCE_SELECTION');return Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($selection?['product_ids'=>[],'page_ids'=>[],'promotion_ids'=>[$voucher->id],'reason'=>'Current voucher only']:$plan)]]]]]);},'api.openai.com/v1/images/*'=>Http::response(['data'=>[['b64_json'=>$png]]])]);
  $id=(string)\Illuminate\Support\Str::uuid();$this->postJson('/admin/marketing/automation/ai-posts/'.$settings['id'].'/generate',['id'=>$id,'version'=>1])->assertOk();for($i=0;$i<5;$i++)$result=$this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk();$result->assertJsonPath('status','completed');$child=\App\Models\MarketingDailyDraftRun::findOrFail($id);$this->assertSame([],$child->snapshot['product_ids']);$this->assertSame('PROMO-ONLY',$child->snapshot['title']);$this->assertSame('all',$child->snapshot['automation']['sources']['source_modes']['promotions']);$this->assertSame('Draft',\App\Models\MarketingPost::firstOrFail()->status);Http::assertSentCount(3);\Illuminate\Support\Facades\Queue::assertNothingPushed();
 }
 public function test_read_only_preview_does_not_require_publishing_execution_enabled():void {
  config(['marketing.execution_enabled'=>false]);$this->product();$this->postJson('/admin/marketing/automation/source-preview',$this->settings(['products'=>'all']))->assertOk();Http::assertNothingSent();
 }
}
