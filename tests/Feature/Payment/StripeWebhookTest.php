<?php

namespace Tests\Feature\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentGatewayCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_checkout_completion_marks_the_attempt_and_order_paid_once(): void
    {
        [$order, $payment] = $this->pendingStripePayment();
        $payload = json_encode([
            'id' => 'evt_checkout_paid_123',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => $payment->reference, 'payment_status' => 'paid']],
        ], JSON_THROW_ON_ERROR);

        $this->postSignedPayload($payload)
            ->assertOk()
            ->assertJson(['received' => true]);

        $this->assertDatabaseHas('order_payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payment_webhook_events', [
            'order_payment_id' => $payment->id,
            'provider' => 'stripe',
            'provider_event_id' => 'evt_checkout_paid_123',
            'environment' => 'sandbox',
        ]);

        $this->postSignedPayload($payload)->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('order_status_histories', 1);
    }

    public function test_a_stripe_webhook_with_an_invalid_signature_is_rejected_without_side_effects(): void
    {
        [, $payment] = $this->pendingStripePayment();
        $payload = json_encode([
            'id' => 'evt_invalid_signature',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => $payment->reference, 'payment_status' => 'paid']],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/payments/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=not-a-valid-signature',
        ], $payload)->assertForbidden();

        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->assertDatabaseHas('order_payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    private function postSignedPayload(string $payload)
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_private');

        return $this->call('POST', '/payments/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);
    }

    private function pendingStripePayment(): array
    {
        $customer = Customer::query()->create(['email' => 'buyer@example.com']);
        $order = Order::query()->create([
            'number' => 'JW-WEBHOOK-123', 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'pending_payment', 'subtotal' => 10000, 'total' => 10000,
        ]);
        $payment = OrderPayment::query()->create([
            'order_id' => $order->id, 'gateway' => 'stripe', 'provider' => 'stripe', 'environment' => 'sandbox',
            'reference' => 'cs_test_123', 'status' => 'pending', 'amount' => 10000, 'currency' => 'MYR',
        ]);
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'stripe', 'environment' => 'sandbox',
            'credentials' => ['publishableKey' => 'pk_test_public', 'secretKey' => 'sk_test_private', 'webhookSecret' => 'whsec_test_private'],
        ]);

        return [$order, $payment];
    }
}
