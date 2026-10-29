<?php
namespace Tests\Feature;

use App\Models\{MarketingCampaign,Order,User};
use App\Services\Marketing\{MarketingAttribution,MarketingPosts};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingAttributionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function source(): string
    {
        config(['marketing.attribution_enabled'=>true,'app.url'=>'http://localhost']); Http::preventStrayRequests();
        $user=User::factory()->create();
        $campaign=MarketingCampaign::create(['merchant_key'=>app(MarketingPosts::class)->merchant(),'user_id'=>$user->id,'name'=>'Boundary campaign','objective'=>'Purchases','status'=>'Draft','starts_on'=>now()->toDateString(),'ends_on'=>now()->toDateString(),'timezone'=>'UTC','destination_url'=>'http://localhost/shop','cta'=>'Shop now','version'=>1]);
        $id=(string)Str::uuid();
        DB::table('marketing_tracking_links')->insert(['id'=>$id,'campaign_id'=>$campaign->id,'post_id'=>(string)Str::uuid(),'destination_id'=>(string)Str::uuid(),'merchant_key'=>$campaign->merchant_key,'user_id'=>$user->id,'post_version'=>1,'destination_url'=>'http://localhost/shop']);
        return $id;
    }

    private function token(string $id): string
    {
        $r=$this->get('/m/r/'.$id)->assertRedirect();
        parse_str(parse_url($r->headers->get('Location'),PHP_URL_QUERY),$query);
        return $query['jw_ref'];
    }

    private function arrive(string $token): void
    {
        $this->postJson('/marketing/tracking/consent',['accepted'=>true,'token'=>$token])->assertOk();
    }

    private function request(): Request
    {
        $request=Request::create('/checkout','POST'); $request->setLaravelSession(app('session')->driver());
        return $request;
    }

    private function order(): Order
    {
        return Order::create(['number'=>'JW-BOUNDARY-'.Str::random(8),'email'=>'boundary@example.test','currency'=>'MYR','currency_exchange_rate'=>1,'status'=>'pending_payment','subtotal'=>10000,'total'=>10000]);
    }

    public function test_two_clicks_in_the_same_second_use_latest_click_and_ignore_replayed_older_tab(): void
    {
        $this->freezeTime(); $a=$this->source(); $b=$this->source();
        $old=$this->token($a); $this->arrive($old);
        $new=$this->token($b); $this->arrive($new);
        $this->assertSame($a,session('marketing.first.id'));
        $this->assertSame($b,session('marketing.last.id'));
        $this->arrive($old);
        $this->assertSame($b,session('marketing.last.id'));
        Http::assertNothingSent();
    }

    public function test_direct_return_preserves_source_and_an_existing_order_cannot_be_reattributed(): void
    {
        $a=$this->source(); $b=$this->source(); $this->arrive($this->token($a));
        $this->postJson('/marketing/tracking/page',['navigation_id'=>(string)Str::uuid()])->assertJsonPath('tracked',true);
        $order=$this->order(); app(MarketingAttribution::class)->attach($order,$this->request());
        $this->travel(1)->minutes(); $this->arrive($this->token($b));
        app(MarketingAttribution::class)->attach($order,$this->request());
        $this->assertDatabaseHas('marketing_order_attributions',['order_id'=>$order->id,'link_id'=>$a,'first_link_id'=>$a]);
        $this->assertDatabaseCount('marketing_order_attributions',1);
        Http::assertNothingSent();
    }

    public function test_checkout_snapshot_survives_newer_click_in_another_tab(): void
    {
        $a=$this->source(); $b=$this->source(); $this->arrive($this->token($a));
        app(MarketingAttribution::class)->checkout($this->request());
        $this->travel(1)->minutes(); $this->arrive($this->token($b));
        $order=$this->order(); app(MarketingAttribution::class)->attach($order,$this->request());
        $this->assertDatabaseHas('marketing_order_attributions',['order_id'=>$order->id,'link_id'=>$a]);
        Http::assertNothingSent();
    }

    public function test_window_is_evaluated_at_placement_and_does_not_block_unattributed_orders(): void
    {
        $this->freezeTime();$a=$this->source();$this->arrive($this->token($a));
        $this->travel(7)->days();$within=$this->order();app(MarketingAttribution::class)->attach($within,$this->request());
        $this->assertDatabaseHas('marketing_order_attributions',['order_id'=>$within->id]);
        $this->travel(1)->seconds();$outside=$this->order();app(MarketingAttribution::class)->attach($outside,$this->request());
        $this->assertDatabaseMissing('marketing_order_attributions',['order_id'=>$outside->id]);
        $this->assertDatabaseCount('orders',2);Http::assertNothingSent();
    }

    public function test_staff_cannot_convert_a_previously_attributed_guest_session_into_analytics(): void
    {
        $a=$this->source();$this->arrive($this->token($a));$request=$this->request();
        $admin=User::factory()->create(['is_admin'=>true]);$request->setUserResolver(fn()=>$admin);
        app(MarketingAttribution::class)->checkout($request);
        app(MarketingAttribution::class)->attach($this->order(),$request);
        $this->assertSame(0,DB::table('marketing_analytics_events')->where('kind','checkout')->count());
        $this->assertDatabaseCount('marketing_order_attributions',0);Http::assertNothingSent();
    }

    public function test_reverse_tab_arrival_keeps_first_and_last_click_in_click_order(): void
    {
        $this->freezeTime();$a=$this->source();$b=$this->source();
        $old=$this->token($a);$new=$this->token($b);
        $this->arrive($new);$this->arrive($old);
        $this->assertSame($a,session('marketing.first.id'));
        $this->assertSame($b,session('marketing.last.id'));Http::assertNothingSent();
    }

    public function test_tracking_requests_use_session_locks_for_competing_tabs(): void
    {
        foreach(['consent','arrival','page'] as $path){
            $route=app('router')->getRoutes()->match(Request::create('/marketing/tracking/'.$path,'POST'));
            $this->assertSame(10,$route->locksFor());
        }
    }
}
