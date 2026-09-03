<?php

namespace Tests\Feature\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSuccessPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_success_renders_real_hero_proof_state_and_card_does_not(): void
    {
        [$bankOrder] = $this->orderWithPayment('bank_transfer', 'none');
        $this->withSession(['last_order_id' => $bankOrder->id])->get('/order/success')
            ->assertOk()->assertSee('data-bank-payment-proof', false)->assertSee('Upload Payment Proof');

        [$cardOrder] = $this->orderWithPayment('stripe', 'none');
        $this->withSession(['last_order_id' => $cardOrder->id])->get('/order/success')
            ->assertOk()->assertDontSee('data-bank-payment-proof', false);
    }

    public function test_verified_success_is_locked_without_upload_form(): void
    {
        [$order] = $this->orderWithPayment('bank_transfer', 'verified');
        $this->withSession(['last_order_id' => $order->id])->get('/order/success')
            ->assertOk()->assertSee('Payment Verified &amp; Settled', false)->assertDontSee('data-proof-form', false);
    }

    private function orderWithPayment(string $gateway, string $proofStatus): array
    {
        $user = User::factory()->create(['is_admin' => false]);
        $customer = Customer::query()->create(['user_id' => $user->id, 'email' => $user->email]);
        $order = Order::query()->create(['number' => 'JW-SUCCESS-'.uniqid(), 'customer_id' => $customer->id, 'email' => $user->email, 'currency' => 'MYR', 'status' => 'pending_payment', 'shipping_method' => 'standard', 'subtotal' => 10000, 'discount_amount' => 0, 'shipping_amount' => 0, 'payment_surcharge_amount' => 0, 'tax_amount' => 0, 'total' => 10000]);
        $payment = OrderPayment::query()->create(['order_id' => $order->id, 'gateway' => $gateway, 'provider' => $gateway, 'environment' => 'offline', 'status' => 'pending', 'reference' => 'REF-'.uniqid(), 'idempotency_key' => 'IDEM-'.uniqid(), 'amount' => 10000, 'currency' => 'MYR', 'proof_status' => $proofStatus]);

        return [$order, $payment, $user];
    }
}
