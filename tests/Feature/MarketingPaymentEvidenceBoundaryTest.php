<?php
namespace Tests\Feature;

use App\Models\{MarketingCampaign,Order,OrderPayment,OrderRefund,PaymentGatewayCredential,PaymentWebhookEvent,User};
use App\Services\Marketing\{MarketingOrderEvidence,MarketingPosts};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail};
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingPaymentEvidenceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $gateway='stripe'): array
    {
        Http::preventStrayRequests();Mail::fake();
        $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $merchant=app(MarketingPosts::class)->merchant();
        $campaign=MarketingCampaign::create(['merchant_key'=>$merchant,'user_id'=>$owner->id,'name'=>'Payment boundary','objective'=>'Purchases','status'=>'Draft','starts_on'=>now()->toDateString(),'ends_on'=>now()->toDateString(),'timezone'=>'UTC','destination_url'=>'http://localhost','cta'=>'Shop now','version'=>1]);
        $order=Order::create(['number'=>'JW-EVIDENCE-'.Str::random(8),'email'=>'evidence@example.test','currency'=>'MYR','status'=>'pending_payment','subtotal'=>10000,'total'=>10000]);
        DB::table('marketing_order_attributions')->insert(['order_id'=>$order->id,'merchant_key'=>$merchant,'campaign_id'=>$campaign->id,'link_id'=>(string)Str::uuid(),'owner_id'=>$owner->id,'session_key'=>'closed-browser','clicked_at'=>now()->subDay(),'placed_at'=>now(),'base_currency'=>'MYR','base_minor'=>10000,'state'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        $payment=OrderPayment::create(['order_id'=>$order->id,'gateway'=>$gateway,'provider'=>$gateway,'environment'=>'live','reference'=>'test-reference-'.Str::uuid(),'status'=>'paid','amount'=>10000,'currency'=>'MYR']);
        return [$owner,$order,$payment];
    }

    private function receipt(OrderPayment $payment,array $changes=[]): PaymentWebhookEvent
    {
        $payload=match($payment->gateway){
            'stripe'=>['type'=>'checkout.session.completed','data'=>['object'=>['id'=>$payment->reference,'payment_status'=>'paid','amount_total'=>10000,'currency'=>'myr']]],
            'paypal'=>['event_type'=>'PAYMENT.CAPTURE.COMPLETED','resource'=>['amount'=>['value'=>'100.00','currency_code'=>'MYR']]],
            'billplz'=>['status'=>'paid','amount'=>'10000'],
        };
        return PaymentWebhookEvent::create(array_replace(['order_payment_id'=>$payment->id,'provider'=>$payment->gateway,'provider_event_id'=>(string)Str::uuid(),'environment'=>'live','raw_payload'=>'isolated fake only','normalized_payload'=>$payload,'verified_at'=>now()],$changes));
    }

    public function test_all_supported_provider_receipts_are_counted_once(): void
    {
        foreach(['stripe','paypal','billplz'] as $gateway){
            [$owner,$order,$payment]=$this->fixture($gateway);$this->receipt($payment);
            app(MarketingOrderEvidence::class)->reconcile($order->id);app(MarketingOrderEvidence::class)->reconcile($order->id);
            $this->assertDatabaseHas('marketing_order_attributions',['order_id'=>$order->id,'state'=>'paid','base_minor'=>10000]);
            $this->actingAs($owner)->getJson('/admin/marketing/analytics/data')->assertOk()->assertJsonPath('paid_orders',1);
        }
        Http::assertNothingSent();
    }

    public function test_mismatched_provider_environment_amount_currency_and_unverified_evidence_are_excluded(): void
    {
        foreach(['provider','environment','amount','currency','unverified','sandbox-payment'] as $case){
            [$owner,$order,$payment]=$this->fixture();
            $receipt=$this->receipt($payment);
            if($case==='provider')$receipt->update(['provider'=>'paypal']);
            if($case==='environment')$receipt->update(['environment'=>'sandbox']);
            if($case==='unverified')$receipt->update(['verified_at'=>null]);
            if($case==='amount'){$payload=$receipt->normalized_payload;$payload['data']['object']['amount_total']=9999;$receipt->update(['normalized_payload'=>$payload]);}
            if($case==='currency'){$payload=$receipt->normalized_payload;$payload['data']['object']['currency']='USD';$receipt->update(['normalized_payload'=>$payload]);}
            if($case==='sandbox-payment')$payment->update(['environment'=>'sandbox']);
            app(MarketingOrderEvidence::class)->reconcile($order->id);
            $this->assertDatabaseHas('marketing_order_attributions',['order_id'=>$order->id,'state'=>'evidence_unconfirmed']);
            $this->actingAs($owner)->getJson('/admin/marketing/analytics/data')->assertJsonPath('paid_orders',0);
        }
        Http::assertNothingSent();
    }

    public function test_bank_proof_upload_or_missing_review_date_is_not_a_payment(): void
    {
        [$owner,$order,$payment]=$this->fixture('bank_transfer');
        $payment->update(['environment'=>'offline','status'=>'pending','proof_status'=>'uploaded']);
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        $this->actingAs($owner)->getJson('/admin/marketing/analytics/data')->assertJsonPath('paid_orders',0);
        $payment->update(['status'=>'paid','proof_status'=>'verified']);
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        $this->assertDatabaseHas('marketing_order_attributions',['order_id'=>$order->id,'state'=>'evidence_unconfirmed']);
        $payment->update(['proof_reviewed_at'=>now()]);
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        $this->getJson('/admin/marketing/analytics/data')->assertJsonPath('paid_orders',1);
        Http::assertNothingSent();
    }

    public function test_delayed_signed_callback_works_without_browser_session_and_replay_does_not_duplicate(): void
    {
        [$owner,$order,$payment]=$this->fixture();$payment->update(['status'=>'pending']);
        PaymentGatewayCredential::create(['gateway_id'=>'stripe','environment'=>'live','credentials'=>['webhookSecret'=>'isolated-test-signing-secret']]);
        $this->flushSession();$this->travel(10)->days();
        $payload=json_encode(['id'=>'evt_isolated_delayed','type'=>'checkout.session.completed','data'=>['object'=>['id'=>$payment->reference,'payment_status'=>'paid','amount_total'=>10000,'currency'=>'myr']]]);
        $timestamp=time();$signature=hash_hmac('sha256',$timestamp.'.'.$payload,'isolated-test-signing-secret');
        for($i=0;$i<2;$i++)$this->call('POST','/payments/webhooks/stripe',[],[],[],['HTTP_STRIPE_SIGNATURE'=>'t='.$timestamp.',v1='.$signature],$payload)->assertOk();
        app(MarketingOrderEvidence::class)->sweep();app(MarketingOrderEvidence::class)->sweep();
        $this->assertDatabaseCount('payment_webhook_events',1);
        $this->assertDatabaseHas('marketing_order_attributions',['order_id'=>$order->id,'state'=>'paid']);
        $this->actingAs($owner)->getJson('/admin/marketing/analytics/data')->assertJsonPath('paid_orders',1);
        Http::assertNothingSent();
    }

    public function test_partial_and_full_refunds_keep_original_purchase_and_do_not_repeat_adjustments(): void
    {
        [$owner,$order,$payment]=$this->fixture();$this->receipt($payment);
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        $paidAt=DB::table('marketing_order_attributions')->where('order_id',$order->id)->value('paid_at');
        OrderRefund::create(['order_id'=>$order->id,'order_payment_id'=>$payment->id,'user_id'=>$owner->id,'amount'=>2500,'reason'=>'Isolated partial refund','status'=>'completed']);
        OrderRefund::create(['order_id'=>$order->id,'order_payment_id'=>$payment->id,'user_id'=>$owner->id,'amount'=>500,'reason'=>'Not completed','status'=>'pending']);
        app(MarketingOrderEvidence::class)->sweep();app(MarketingOrderEvidence::class)->sweep();
        $this->actingAs($owner)->getJson('/admin/marketing/analytics/data')->assertJsonPath('paid_orders',1)->assertJsonPath('money.0.paid_minor',10000)->assertJsonPath('money.0.refunded_minor',2500);
        OrderRefund::create(['order_id'=>$order->id,'order_payment_id'=>$payment->id,'user_id'=>$owner->id,'amount'=>7500,'reason'=>'Isolated remaining refund','status'=>'completed']);
        $order->update(['status'=>'cancelled']);$payment->update(['status'=>'refunded']);
        app(MarketingOrderEvidence::class)->sweep();app(MarketingOrderEvidence::class)->sweep();
        $this->getJson('/admin/marketing/analytics/data')->assertJsonPath('paid_orders',1)->assertJsonPath('money.0.paid_minor',10000)->assertJsonPath('money.0.refunded_minor',10000);
        $this->assertSame($paidAt,DB::table('marketing_order_attributions')->where('order_id',$order->id)->value('paid_at'));
        $this->assertDatabaseCount('marketing_order_attributions',1);Http::assertNothingSent();
    }

    public function test_payment_date_comes_from_the_matching_receipt_not_an_unrelated_earlier_receipt(): void
    {
        $this->freezeTime();[, $order,$payment]=$this->fixture();
        $this->receipt($payment,['provider'=>'paypal','verified_at'=>now()->subDays(20)]);
        $matching=$this->receipt($payment);
        app(MarketingOrderEvidence::class)->reconcile($order->id);
        $this->assertSame($matching->verified_at->format('Y-m-d H:i:s'),DB::table('marketing_order_attributions')->where('order_id',$order->id)->value('paid_at'));
        Http::assertNothingSent();
    }
}
