<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use App\Models\{Order,User};
class MarketingPrivacyCloseoutTest extends TestCase {
 use RefreshDatabase;
 public function test_withdrawal_and_expiry_clear_visitor_context_without_affecting_shopping():void {
  Http::preventStrayRequests();config(['marketing.attribution_enabled'=>true]);
  $this->withSession(['marketing'=>['consent'=>true,'consent_expires_at'=>now()->subSecond()->timestamp,'sid'=>'old','last'=>['id'=>Str::uuid(),'at'=>now()->timestamp,'session'=>'old']]])->postJson('/marketing/tracking/page',['navigation_id'=>Str::uuid()])->assertOk()->assertJsonPath('tracked',false);
  $this->assertNull(session('marketing.last'));
  $this->postJson('/marketing/tracking/consent',['accepted'=>true])->assertOk();$this->assertGreaterThan(now()->timestamp,session('marketing.consent_expires_at'));
  $this->postJson('/marketing/tracking/consent',['accepted'=>false])->assertOk();$this->assertFalse(session('marketing.consent'));$this->assertNull(session('marketing.sid'));
  $this->get('/')->assertOk()->assertSee('jw-marketing-consent');Http::assertNothingSent();
 }
 public function test_expired_events_are_pruned_only_for_this_merchant_and_financial_snapshots_are_retained():void {
  Http::preventStrayRequests();$u=User::factory()->create();$m=app(\App\Services\Marketing\MarketingPosts::class)->merchant();$campaign=Str::uuid();$link=Str::uuid();
  foreach([['request',8,$m],['request',6,$m],['page_view',91,$m],['page_view',89,$m],['page_view',100,'other-merchant']] as [$kind,$age,$merchant])DB::table('marketing_analytics_events')->insert(['merchant_key'=>$merchant,'campaign_id'=>$campaign,'link_id'=>$link,'kind'=>$kind,'dedupe_key'=>Str::uuid(),'session_key'=>'old-visitor','occurred_at'=>now()->subDays($age)]);
  $o=Order::create(['number'=>'PRIVACY-TEST','email'=>'test@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>10000,'total'=>10000]);
  DB::table('marketing_order_attributions')->insert(['order_id'=>$o->id,'merchant_key'=>$m,'campaign_id'=>$campaign,'link_id'=>$link,'owner_id'=>$u->id,'session_key'=>'old-visitor','clicked_at'=>now()->subDays(100),'placed_at'=>now()->subDays(100),'base_currency'=>'MYR','base_minor'=>10000,'state'=>'paid','paid_at'=>now()->subDays(99)]);
  $s=app(\App\Services\Marketing\MarketingTrackingPrivacy::class);$dry=$s->prune(true);$this->assertSame(2,$dry['events']);$this->assertDatabaseCount('marketing_analytics_events',5);
  $s->prune(false);$this->assertDatabaseCount('marketing_analytics_events',3);$this->assertDatabaseHas('orders',['id'=>$o->id,'total'=>10000]);$a=DB::table('marketing_order_attributions')->first();$this->assertSame(10000,$a->base_minor);$this->assertStringStartsWith('retired:',$a->session_key);$this->assertSame(0,$s->prune(false)['events']);$this->assertSame(0,$s->prune(false)['retired']);Http::assertNothingSent();
 }
 public function test_privacy_controls_are_available_while_optional_collection_is_disabled():void {
  config(['marketing.attribution_enabled'=>false]);$this->get('/')->assertOk()->assertSee('jw-marketing-consent');
 }
}
