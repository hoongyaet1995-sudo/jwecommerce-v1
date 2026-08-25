<?php

namespace Tests\Feature\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentGatewayCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillplzWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_billplz_paid_callback_confirms_the_order_idempotently(): void
    {
        [$order, $payment] = $this->pendingBillplzPayment();
        $callback = [
            'id' => $payment->reference,
            'bank_account_number' => '1234567890',
            'status' => 'paid',
            'total' => 10000,
            'reference_id' => $order->number,
            'epoch' => 1724550000,
        ];
        $callback['x_signature'] = hash_hmac('sha512', implode('', [
            $callback['id'], $callback['bank_account_number'], $callback['status'],
            $callback['total'], $callback['reference_id'], $callback['epoch'],
        ]), 'billplz_signature_key');

        $this->post('/payments/webhooks/billplz', $callback)->assertOk()->assertJson(['received' => true]);
        $this->assertDatabaseHas('order_payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);

        $this->post('/payments/webhooks/billplz', $callback)->assertOk()->assertJson(['received' => true]);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_an_invalid_billplz_signature_is_rejected_without_changing_the_order(): void
    {
        [, $payment] = $this->pendingBillplzPayment();

        $this->post('/payments/webhooks/billplz', [
            'id' => $payment->reference, 'bank_account_number' => '1234567890', 'status' => 'paid',
            'total' => 10000, 'reference_id' => 'JW-BILLPLZ-123', 'epoch' => 1724550000, 'x_signature' => 'invalid',
        ])->assertForbidden();

        $this->assertDatabaseHas('order_payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    private function pendingBillplzPayment(): array
    {
        $customer = Customer::query()->create(['email' => 'buyer@example.com']);
        $order = Order::query()->create([
            'number' => 'JW-BILLPLZ-123', 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'pending_payment', 'subtotal' => 10000, 'total' => 10000,
        ]);
        $payment = OrderPayment::query()->create([
            'order_id' => $order->id, 'gateway' => 'billplz', 'provider' => 'billplz', 'environment' => 'sandbox',
            'reference' => 'BILLPLZ-123', 'status' => 'pending', 'amount' => 10000, 'currency' => 'MYR',
        ]);
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'billplz', 'environment' => 'sandbox',
            'credentials' => ['apiKey' => 'billplz_test_key', 'collectionId' => 'collection-1', 'signatureKey' => 'billplz_signature_key'],
        ]);

        return [$order, $payment];
    }
}
