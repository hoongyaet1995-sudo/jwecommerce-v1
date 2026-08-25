<?php

namespace Tests\Feature\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentGatewayCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostedPaymentRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_creates_a_new_hosted_attempt_for_the_same_pending_order(): void
    {
        [$order, $previous] = $this->pendingStripePayment();
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_retry_123', 'url' => 'https://checkout.stripe.test/c/pay/cs_test_retry_123',
            ]),
        ]);

        $this->withSession(['pending_payment_id' => $previous->id])
            ->post(route('checkout.payment-retry', $previous))
            ->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_retry_123');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_payments', 2);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id, 'reference' => 'cs_test_retry_123', 'status' => 'pending',
        ]);
    }

    private function pendingStripePayment(): array
    {
        $customer = Customer::query()->create(['email' => 'buyer@example.com']);
        $order = Order::query()->create([
            'number' => 'JW-RETRY-123', 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'pending_payment', 'subtotal' => 10000, 'total' => 10000,
        ]);
        $payment = OrderPayment::query()->create([
            'order_id' => $order->id, 'gateway' => 'stripe', 'provider' => 'stripe', 'environment' => 'sandbox',
            'reference' => 'cs_test_previous', 'status' => 'pending', 'amount' => 10000, 'currency' => 'MYR',
            'configuration_snapshot' => ['gateway' => ['id' => 'stripe', 'isSandbox' => true]],
        ]);
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'stripe', 'environment' => 'sandbox',
            'credentials' => ['publishableKey' => 'pk_test_public', 'secretKey' => 'sk_test_private', 'webhookSecret' => 'whsec_test_private'],
        ]);

        return [$order, $payment];
    }
}
