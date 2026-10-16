<?php

namespace Tests\Feature;

use App\Models\EnquiryQuotationVersion;
use App\Models\Order;
use App\Models\PaymentGatewayCredential;
use App\Models\Product;
use App\Models\ProductEnquiry;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\EnquiryQuotationService;
use App\Services\Payments\HostedPaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function quote(): EnquiryQuotationVersion
    {
        config(['product_enquiries.enabled' => true]);
        StoreSetting::updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => ['isEnabled' => true, 'allowProofUpload' => true, 'bankAccounts' => [['id' => 'test-bank', 'bankName' => 'Local test bank', 'accountNumber' => 'LOCAL-ONLY', 'accountHolderName' => 'Local acceptance', 'isActive' => true, 'isPrimary' => true]]]]]);
        $p = Product::create(['title' => 'Custom photography scope', 'handle' => 'quote-scope', 'purchase_mode' => 'quote', 'status' => 'active', 'base_price' => 0]);
        $this->post('/enquiries', ['product_id' => $p->id, 'request_key' => (string) Str::uuid(), 'name' => 'Quote checkout buyer', 'email' => 'quote-checkout@example.test', 'requirements' => 'Half day session.'])->assertRedirect();
        $e = ProductEnquiry::firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin, 'web');
        $s = app(EnquiryQuotationService::class);
        $state = $s->view($e);
        $s->save($e, ['contextHash' => $state['contextHash'], 'lockVersion' => 0, 'validUntil' => now('Asia/Kuala_Lumpur')->addDays(2)->toDateString(), 'notes' => 'Includes travel and all charges.', 'items' => [['name' => 'Session', 'description' => 'Agreed scope', 'qty' => '0.5', 'price' => '1000.00']]], $admin->id);
        $s->send($e, 1, $admin->id);

        return EnquiryQuotationVersion::firstOrFail();
    }

    public function test_quote_checkout_uses_each_registered_theme_renderer_without_cart_repricing(): void
    {
        $v = $this->quote();
        $this->post('/quotations/'.$v->id.'/accept')->assertRedirect();
        foreach (\App\Services\WebsiteDesignService::THEMES as $theme) {
            StoreSetting::updateOrCreate(['key'=>'website_design'], ['value'=>['data'=>['theme'=>$theme]]]);
            $view = $theme === 'default' ? 'storefront.checkout' : 'storefront.themes.'.str_replace('_', '-', $theme).'-checkout';
            $this->get('/quotations/'.$v->id.'/checkout')->assertOk()->assertViewIs($view)
                ->assertSee('data-quotation-checkout', false)->assertSee('500.00')
                ->assertDontSee('data-checkout-shipping-quote-url', false)
                ->assertDontSee('checkout-coupon-apply-form', false);
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_quote_checkout_inherits_checkout_page_media_and_color_settings(): void
    {
        $v = $this->quote();
        $this->post('/quotations/'.$v->id.'/accept')->assertRedirect();
        $profile = ['food'=>[
            'pages'=>['checkout'=>['colorMode'=>'custom','main'=>'#123456','sub'=>'#eeeeee']],
            'media'=>['checkout'=>['desktop'=>'/quote-media.png','mobile'=>'/quote-mobile.png','hidden'=>false]],
        ]];
        StoreSetting::updateOrCreate(['key'=>'website_design'], ['value'=>['data'=>['theme'=>'food','customization'=>$profile]]]);
        $this->get('/quotations/'.$v->id.'/checkout')->assertOk()->assertSee('data-checkout-page-media',false)
            ->assertSee('/quote-media.png')->assertSee('/quote-mobile.png')->assertSee('#123456');
        $profile['food']['media']['checkout']['hidden'] = true;
        StoreSetting::updateOrCreate(['key'=>'website_design'], ['value'=>['data'=>['theme'=>'food','customization'=>$profile]]]);
        $this->get('/quotations/'.$v->id.'/checkout')->assertOk()->assertDontSee('data-checkout-page-media',false);
        $this->assertDatabaseCount('orders',0);
    }

    private function details(): array
    {
        return ['name' => 'Quote checkout buyer', 'phone' => '0120000000', 'payment_method' => 'bank_transfer', 'bank_account_id' => 'test-bank'];
    }

    public function test_accept_checkout_creates_one_scope_order_without_changing_cart_or_stock(): void
    {
        $v = $this->quote();
        $base = '/quotations/'.$v->id;
        $this->withSession(['cart' => [123 => 2], 'coupon_code' => 'KEEP-ME']);
        $this->post($base.'/accept')->assertRedirect($base.'/checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->get($base.'/checkout')->assertOk()->assertSee('500.00')->assertSee('0.5');
        $this->post($base.'/checkout', $this->details() + ['total' => 1, 'coupon' => 'FREE'])->assertRedirect($base.'/payment');
        $o = Order::firstOrFail();
        $this->assertSame('pending_payment', $o->status);
        $this->assertEquals(50000, $o->total);
        $this->assertEquals(1, $o->currency_exchange_rate);
        $this->assertNull($o->items->first()->product_variant_id);
        $this->assertEquals(1, $o->items->first()->quantity);
        $this->post($base.'/checkout', $this->details())->assertRedirect($base.'/payment')->assertSessionHas('cart', [123 => 2])->assertSessionHas('coupon_code', 'KEEP-ME');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_payments', 1);
        $this->get($base.'/payment')->assertOk()->assertSee('Awaiting payment')->assertSee($o->number);
        $this->assertDatabaseCount('stock_allocations', 0);
    }

    public function test_superseded_expired_or_foreign_quote_cannot_be_accepted(): void
    {
        $v = $this->quote();
        $s = app(EnquiryQuotationService::class);
        $e = $v->quotation->enquiry;
        $admin = User::where('is_admin', true)->firstOrFail();
        $state = $s->view($e);
        $r = $s->revise($e, $state['lockVersion'], $admin->id);
        $s->send($e, $r['lockVersion'], $admin->id);
        $this->post('/quotations/'.$v->id.'/accept')->assertStatus(409);
        $latest = EnquiryQuotationVersion::where('revision', 2)->firstOrFail();
        $this->travel(3)->days();
        $this->postJson('/quotations/'.$latest->id.'/accept')->assertUnprocessable();
        $this->flushSession();
        $this->post('/quotations/'.$latest->id.'/accept')->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_accepted_quote_cannot_be_revised_and_remains_resumable_after_expiry(): void
    {
        $v = $this->quote();
        $base = '/quotations/'.$v->id;
        $this->post($base.'/accept')->assertRedirect();
        $s = app(EnquiryQuotationService::class);
        $e = $v->quotation->enquiry;
        $state = $s->view($e);
        $this->postJson('/admin/api/enquiries/'.$e->id.'/quotation/revise', ['lockVersion' => $state['lockVersion']])->assertStatus(409);
        $this->travel(3)->days();
        $this->post($base.'/accept')->assertRedirect($base.'/checkout');
        $this->post($base.'/checkout', $this->details())->assertRedirect($base.'/payment');
        $this->flushSession();
        $this->get($base.'/payment')->assertNotFound();
    }

    public function test_bank_proof_is_pending_until_authorized_review_and_order_links_back_to_quote(): void
    {
        Mail::fake();
        Storage::fake('local');
        $v = $this->quote();
        $base = '/quotations/'.$v->id;
        $this->post($base.'/accept')->assertRedirect();
        $this->post($base.'/checkout', $this->details())->assertRedirect();
        $o = Order::firstOrFail();
        $p = $o->payments()->firstOrFail();
        $this->post($base.'/proof', ['payment_proof' => UploadedFile::fake()->image('local-proof.png')])->assertRedirect();
        $this->assertSame('pending_payment', $o->fresh()->status);
        $this->assertSame('uploaded', $p->fresh()->proof_status);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($admin, 'web')->postJson('/admin/api/orders/'.$o->id.'/payments/'.$p->id.'/proof/review', ['decision' => 'verified', 'reason' => 'Local isolated test only.'])->assertOk();
        $this->assertSame('paid', $o->fresh()->status);
        $this->get($base.'/payment')->assertOk()->assertSee('Payment confirmed.');
        $this->getJson('/admin/api/orders')->assertOk()->assertJsonPath('orders.0.quotation.manualFulfilment', true);
        $this->assertDatabaseCount('stock_allocations', 0);
    }

    public function test_acceptance_and_checkout_retain_real_csrf_protection(): void
    {
        $v = $this->quote();
        $base = '/quotations/'.$v->id;
        $this->app->instance('env', 'local');
        $this->withSession(['_token' => 'quote-checkout-csrf']);
        $this->post($base.'/accept')->assertStatus(419);
        $this->post($base.'/accept', ['_token' => 'quote-checkout-csrf'])->assertRedirect();
        $this->post($base.'/checkout', $this->details())->assertStatus(419);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_hosted_payment_link_is_reused_and_return_does_not_mark_paid(): void
    {
        $v = $this->quote();
        $base = '/quotations/'.$v->id;
        $this->post($base.'/accept');
        $this->post($base.'/checkout', $this->details());
        $o = Order::firstOrFail();
        $p = $o->payments()->firstOrFail();
        $p->update(['gateway' => 'stripe', 'provider' => 'stripe', 'environment' => 'sandbox']);
        $this->mock(HostedPaymentGatewayManager::class, function ($mock) {
            $mock->shouldReceive('begin')->once()->andReturn('https://pay.example.test/local-session');
        });
        $this->post($base.'/payment')->assertRedirect('https://pay.example.test/local-session');
        $this->post($base.'/payment')->assertRedirect('https://pay.example.test/local-session');
        $this->get('/checkout/payment-return/'.$p->id.'?state=cancelled')->assertRedirect($base.'/payment?state=cancelled');
        $this->post('/checkout/payment-retry/'.$p->id)->assertStatus(409);
        $this->assertSame('pending_payment', $o->fresh()->status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_payments', 1);
    }

    public function test_verified_callback_requires_exact_quote_amount_and_currency(): void
    {
        Mail::fake();
        $v = $this->quote();
        $base = '/quotations/'.$v->id;
        $this->post($base.'/accept');
        $this->post($base.'/checkout', $this->details());
        $o = Order::firstOrFail();
        $p = $o->payments()->firstOrFail();
        $p->update(['gateway' => 'stripe', 'provider' => 'stripe', 'reference' => 'cs_quote_test', 'environment' => 'sandbox']);
        PaymentGatewayCredential::create(['gateway_id' => 'stripe', 'environment' => 'sandbox', 'credentials' => ['webhookSecret' => 'whsec_quote_local']]);
        $send = function ($amount, $currency) {
            $payload = json_encode(['id' => 'evt_quote_test', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_quote_test', 'payment_status' => 'paid', 'amount_total' => $amount, 'currency' => $currency]]]);
            $time = time();

            return $this->call('POST', '/payments/webhooks/stripe', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, 'whsec_quote_local')], $payload);
        };
        $send(1, 'myr')->assertUnprocessable();
        $send(50000, 'usd')->assertUnprocessable();
        $this->assertSame('pending_payment', $o->fresh()->status);
        $this->assertDatabaseCount('payment_webhook_events', 0);
        $send(50000, 'myr')->assertOk();
        $send(50000, 'myr')->assertOk();
        $this->assertSame('paid', $o->fresh()->status);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('stock_allocations', 0);
    }

    public function test_matching_email_cannot_claim_guest_quote_checkout_or_order(): void
    {
        $v = $this->quote();
        $base = '/quotations/'.$v->id;
        $this->post($base.'/accept');
        $this->post($base.'/checkout', $this->details());
        $this->flushSession();
        $this->actingAs(User::factory()->create(['email' => 'quote-checkout@example.test']),'customer');
        $this->get($base.'/checkout')->assertNotFound();
        $this->get($base.'/payment')->assertNotFound();
        $this->post($base.'/payment')->assertNotFound();
    }
}
