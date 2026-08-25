<?php

namespace Tests\Feature\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentGatewayCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_paypal_verified_capture_confirms_its_matching_order_once(): void
    {
        [$order, $payment] = $this->pendingPayPalPayment();
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token']),
            'https://api-m.sandbox.paypal.com/v1/notifications/webhooks' => Http::response([
                'webhooks' => [['id' => 'WH-APP-1', 'url' => url('/payments/webhooks/paypal')]],
            ]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ]),
        ]);
        $payload = json_encode([
            'id' => 'WH-EVENT-123',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => $payment->reference]]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/payments/webhooks/paypal', [], [], [], $this->headers(), $payload)
            ->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseHas('order_payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);

        $this->call('POST', '/payments/webhooks/paypal', [], [], [], $this->headers(), $payload)->assertOk();
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_a_paypal_webhook_is_rejected_when_provider_verification_fails(): void
    {
        [, $payment] = $this->pendingPayPalPayment();
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token']),
            'https://api-m.sandbox.paypal.com/v1/notifications/webhooks' => Http::response([
                'webhooks' => [['id' => 'WH-APP-1', 'url' => url('/payments/webhooks/paypal')]],
            ]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'FAILURE',
            ]),
        ]);
        $payload = json_encode([
            'id' => 'WH-EVENT-INVALID',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => $payment->reference]]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/payments/webhooks/paypal', [], [], [], $this->headers(), $payload)->assertForbidden();
        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->assertDatabaseHas('order_payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    private function headers(): array
    {
        return [
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => 'https://api-m.sandbox.paypal.com/certs/test',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'transmission-123',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'signature',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
        ];
    }

    private function pendingPayPalPayment(): array
    {
        $customer = Customer::query()->create(['email' => 'buyer@example.com']);
        $order = Order::query()->create([
            'number' => 'JW-PAYPAL-123', 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'pending_payment', 'subtotal' => 10000, 'total' => 10000,
        ]);
        $payment = OrderPayment::query()->create([
            'order_id' => $order->id, 'gateway' => 'paypal', 'provider' => 'paypal', 'environment' => 'sandbox',
            'reference' => 'PAYPAL-ORDER-123', 'status' => 'pending', 'amount' => 10000, 'currency' => 'MYR',
        ]);
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'paypal', 'environment' => 'sandbox',
            'credentials' => ['clientId' => 'paypal_client', 'clientSecret' => 'paypal_secret'],
        ]);

        return [$order, $payment];
    }
}
