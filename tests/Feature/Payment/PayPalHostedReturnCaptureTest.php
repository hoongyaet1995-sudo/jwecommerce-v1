<?php

namespace Tests\Feature\Payment;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentGatewayCredential;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalHostedReturnCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_paypal_return_captures_the_provider_order_but_keeps_local_confirmation_pending_for_webhook(): void
    {
        [$variant, $order, $payment] = $this->payment();
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token']),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-123', 'status' => 'COMPLETED',
            ]),
        ]);

        $this->withSession(['cart' => [$variant->id => 1], 'pending_payment_id' => $payment->id])
            ->get(route('checkout.payment-return', ['payment' => $payment, 'state' => 'pending', 'token' => $payment->reference]))
            ->assertOk()
            ->assertSee('Payment submitted — awaiting secure confirmation.');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123/capture');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment']);
    }

    private function payment(): array
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Studio Headphones', 'handle' => 'studio-headphones', 'brand' => 'JW Studio', 'description' => 'Test', 'base_price' => 10000, 'status' => 'active']);
        $variant = $product->variants()->create(['sku' => 'STUDIO-1', 'title' => 'Default', 'price' => 10000, 'stock' => 10, 'enabled' => true]);
        $customer = Customer::query()->create(['email' => 'buyer@example.com']);
        $order = Order::query()->create(['number' => 'JW-PAYPAL-123', 'customer_id' => $customer->id, 'email' => $customer->email, 'currency' => 'MYR', 'status' => 'pending_payment', 'subtotal' => 10000, 'total' => 10000]);
        $payment = OrderPayment::query()->create(['order_id' => $order->id, 'gateway' => 'paypal', 'provider' => 'paypal', 'environment' => 'sandbox', 'reference' => 'PAYPAL-ORDER-123', 'status' => 'pending', 'amount' => 10000, 'currency' => 'MYR']);
        PaymentGatewayCredential::query()->create(['gateway_id' => 'paypal', 'environment' => 'sandbox', 'credentials' => ['clientId' => 'paypal_client', 'clientSecret' => 'paypal_secret']]);

        return [$variant, $order, $payment];
    }
}
