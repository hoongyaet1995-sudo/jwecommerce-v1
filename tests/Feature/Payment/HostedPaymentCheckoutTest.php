<?php

namespace Tests\Feature\Payment;

use App\Models\Category;
use App\Models\PaymentGatewayCredential;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostedPaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCountedWebsiteStock;

    public function test_checkout_redirects_to_a_stripe_hosted_session_and_records_its_reference(): void
    {
        $variant = $this->variant();
        $this->configureStripe();
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.test/c/pay/cs_test_123',
            ]),
        ]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData())
            ->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_123');

        $this->assertDatabaseHas('orders', ['status' => 'pending_payment']);
        $this->assertDatabaseHas('order_payments', [
            'gateway' => 'stripe', 'reference' => 'cs_test_123', 'status' => 'pending', 'currency' => 'MYR',
        ]);
    }

    public function test_checkout_redirects_to_a_paypal_sandbox_approval_url_and_records_its_reference(): void
    {
        $variant = $this->variant();
        $this->configureGateway('paypal', ['clientId' => 'paypal_test_client', 'clientSecret' => 'paypal_test_secret']);
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'access-token']),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-123',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/checkoutnow?token=PAYPAL-ORDER-123']],
            ]),
        ]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData('paypal'))
            ->assertRedirect('https://paypal.test/checkoutnow?token=PAYPAL-ORDER-123');

        $this->assertDatabaseHas('order_payments', ['gateway' => 'paypal', 'reference' => 'PAYPAL-ORDER-123', 'status' => 'pending']);
    }

    public function test_checkout_redirects_to_a_billplz_sandbox_bill_and_records_its_reference(): void
    {
        $variant = $this->variant();
        $this->configureGateway('billplz', ['apiKey' => 'billplz_test_key', 'collectionId' => 'collection-1', 'signatureKey' => 'signature-key']);
        Http::fake([
            'https://www.billplz-sandbox.com/api/v3/bills' => Http::response([
                'id' => 'BILLPLZ-123', 'url' => 'https://www.billplz-sandbox.com/bills/BILLPLZ-123',
            ]),
        ]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData('billplz'))
            ->assertRedirect('https://www.billplz-sandbox.com/bills/BILLPLZ-123');

        $this->assertDatabaseHas('order_payments', ['gateway' => 'billplz', 'reference' => 'BILLPLZ-123', 'status' => 'pending']);
    }

    private function configureStripe(): void
    {
        $this->configureGateway('stripe', ['publishableKey' => 'pk_test_public', 'secretKey' => 'sk_test_private', 'webhookSecret' => 'whsec_test_private']);
    }

    /** @param array<string, string> $credentials */
    private function configureGateway(string $gateway, array $credentials): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'payment_gateways'], ['value' => ['data' => [[
            'id' => $gateway, 'name' => ucfirst($gateway), 'isEnabled' => true, 'isSandbox' => true,
            'supportedCurrencies' => ['MYR'], 'limits' => [], 'sortOrder' => 1,
        ]]]]);
        PaymentGatewayCredential::query()->create([
            'gateway_id' => $gateway, 'environment' => 'sandbox', 'credentials' => $credentials,
        ]);
    }

    private function checkoutData(string $paymentMethod = 'stripe'): array
    {
        return [
            'email' => 'buyer@example.com', 'first_name' => 'Alex', 'last_name' => 'Buyer',
            'line_1' => '10 Studio Road', 'city' => 'Kuala Lumpur', 'state' => 'Selangor',
            'postal_code' => '50000', 'country' => 'MY', 'delivery' => 'standard', 'payment_method' => $paymentMethod,
        ];
    }

    private function variant()
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Studio Headphones', 'handle' => 'studio-headphones', 'brand' => 'JW Studio', 'description' => 'Test', 'base_price' => 10000, 'status' => 'active']);

        return $this->countedWebsiteVariant($product->variants()->create(['sku' => 'STUDIO-1', 'title' => 'Default', 'price' => 10000, 'stock' => 10, 'enabled' => true]), 10);
    }
}
