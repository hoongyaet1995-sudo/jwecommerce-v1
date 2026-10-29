<?php
namespace Tests\Feature;

use App\Models\{MarketingCampaign,Order,Product,StoreSetting,User};
use App\Services\Marketing\MarketingOrderEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail,Storage};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingConversionFlowTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\CreatesCountedWebsiteStock;

    private function landing(bool $consent): User
    {
        Http::preventStrayRequests(); Mail::fake();
        config(['marketing.attribution_enabled'=>true,'product_enquiries.enabled'=>true,'app.url'=>'http://localhost']);
        $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $campaign=MarketingCampaign::create(['merchant_key'=>app(\App\Services\Marketing\MarketingPosts::class)->merchant(),'user_id'=>$owner->id,'name'=>'Conversion acceptance','objective'=>'Sales','status'=>'Active','starts_on'=>now()->toDateString(),'ends_on'=>now()->toDateString(),'timezone'=>'UTC','destination_url'=>'http://localhost/','cta'=>'Learn more','version'=>1]);
        $link=(string)Str::uuid();
        DB::table('marketing_tracking_links')->insert(['id'=>$link,'campaign_id'=>$campaign->id,'post_id'=>(string)Str::uuid(),'destination_id'=>(string)Str::uuid(),'merchant_key'=>$campaign->merchant_key,'user_id'=>$owner->id,'post_version'=>1,'destination_url'=>'http://localhost/']);
        $response=$this->get('/m/r/'.$link)->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'),PHP_URL_QUERY),$query);
        $this->postJson('/marketing/tracking/consent',['accepted'=>$consent,'token'=>$query['jw_ref']])->assertOk();
        return $owner;
    }

    public function test_purchase_route_waits_for_verified_payment_and_deduplicates_reconciliation(): void
    {
        $owner=$this->landing(true);
        $product=Product::create(['title'=>'Acceptance item','handle'=>'acceptance-item','base_price'=>10000,'status'=>'active']);
        $variant=$this->countedWebsiteVariant($product->variants()->create(['sku'=>'CONVERSION-TEST','title'=>'Default','price'=>10000,'enabled'=>true,'stock'=>5]),5);
        StoreSetting::updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[['id'=>'standard','name'=>'Standard','zoneRegion'=>'all','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','flatRateCost'=>0,'isActive'=>true]]]]);
        StoreSetting::updateOrCreate(['key'=>'bank_transfer'],['value'=>['data'=>['isEnabled'=>true,'bankAccounts'=>[['id'=>'test-bank','bankName'=>'Test only','accountNumber'=>'TEST-NOT-PAYABLE','isPrimary'=>true,'isActive'=>true]]]]]);
        $this->withSession(['cart'=>[$variant->id=>1]])->get('/checkout')->assertOk();
        $this->get('/checkout')->assertOk();
        $this->assertSame(1,DB::table('marketing_analytics_events')->where('kind','checkout')->count());
        $this->post('/checkout',['email'=>'conversion@example.test','first_name'=>'Test','last_name'=>'Visitor','line_1'=>'Test address','city'=>'Kuala Lumpur','postal_code'=>'50000','country'=>'MY','delivery'=>'standard','payment_method'=>'bank_transfer'])->assertRedirect('/order/success');
        $order=Order::firstOrFail(); $payment=$order->payments()->firstOrFail();
        $this->assertDatabaseHas('marketing_order_attributions',['order_id'=>$order->id,'state'=>'pending']);
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        $this->actingAs($owner)->getJson('/admin/marketing/analytics/data')->assertOk()->assertJsonPath('paid_orders',0);
        // Exercise the normal upload/review routes with an isolated fake file; no transfer.
        Storage::fake('local');
        $this->app['auth']->forgetGuards();
        $this->withSession(['last_order_id'=>$order->id])->postJson(route('customer.payment-proofs.store',[$order,$payment]),[
            'payment_proof'=>UploadedFile::fake()->create('TEST-NOT-A-RECEIPT.pdf',20,'application/pdf'),
        ])->assertOk()->assertJsonPath('data.proofStatus','uploaded');
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        $this->actingAs($owner)->getJson('/admin/marketing/analytics/data')->assertJsonPath('paid_orders',0);
        $staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);
        $review='/admin/api/orders/'.$order->id.'/payments/'.$payment->id.'/proof/review';
        $this->actingAs($staff)->postJson($review,['decision'=>'verified','reason'=>'Isolated test'])->assertForbidden();
        $this->actingAs($owner)->postJson($review,['decision'=>'verified','reason'=>'Isolated acceptance only. No funds transferred.'])->assertOk();
        $this->assertDatabaseHas('order_payment_proof_events',['order_payment_id'=>$payment->id,'user_id'=>$owner->id,'event_type'=>'verified']);
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        $this->getJson('/admin/marketing/analytics/data')->assertJsonPath('paid_orders',1);
        $this->assertDatabaseCount('marketing_order_attributions',1);
        Http::assertNothingSent();
    }

    public function test_quote_route_is_a_lead_and_rejected_consent_still_allows_submission(): void
    {
        $owner=$this->landing(true);
        $product=Product::create(['title'=>'Quote acceptance','handle'=>'quote-acceptance','purchase_mode'=>'quote','status'=>'active','base_price'=>0]);
        $data=['product_id'=>$product->id,'request_key'=>(string)Str::uuid(),'name'=>'Local test','email'=>'quote@example.test','requirements'=>'Acceptance only. No quotation or contact required.'];
        $result=$this->post('/enquiries',$data)->assertRedirect();
        $this->get($result->headers->get('Location'))->assertOk();
        $this->post('/enquiries',$data)->assertRedirect();
        $this->assertDatabaseCount('marketing_enquiry_attributions',1);
        $this->postJson('/marketing/tracking/consent',['accepted'=>false])->assertOk();
        $data['request_key']=(string)Str::uuid();
        $this->post('/enquiries',$data)->assertRedirect();
        $this->assertDatabaseCount('product_enquiries',2);
        $this->assertDatabaseCount('marketing_enquiry_attributions',1);
        $this->assertDatabaseCount('orders',0);
        $this->actingAs($owner)->getJson('/admin/marketing/analytics/data')->assertJsonPath('quote_requests',1)->assertJsonPath('paid_orders',0);
        Http::assertNothingSent();
    }
}
