<?php

namespace Tests\Feature\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPaymentProofRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_history_renders_compact_panel_only_for_owned_bank_order(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $customer = Customer::query()->create(['user_id' => $user->id, 'email' => $user->email]);
        $order = Order::query()->create(['number' => 'JW-ACCOUNT-PROOF', 'customer_id' => $customer->id, 'email' => $user->email, 'currency' => 'MYR', 'status' => 'pending_payment', 'shipping_method' => 'standard', 'subtotal' => 10000, 'discount_amount' => 0, 'shipping_amount' => 0, 'payment_surcharge_amount' => 0, 'tax_amount' => 0, 'total' => 10000]);
        OrderPayment::query()->create(['order_id' => $order->id, 'gateway' => 'bank_transfer', 'provider' => 'bank_transfer', 'environment' => 'offline', 'status' => 'pending', 'reference' => 'BANK-ACCOUNT', 'idempotency_key' => 'account-proof', 'amount' => 10000, 'currency' => 'MYR', 'proof_status' => 'none']);

        $this->actingAs($user, 'customer')->get('/account#purchase_history')
            ->assertOk()->assertSee('data-bank-payment-proof', false)->assertSee('JW-ACCOUNT-PROOF');
    }
}
