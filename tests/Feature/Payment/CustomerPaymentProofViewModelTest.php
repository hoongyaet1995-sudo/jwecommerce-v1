<?php

namespace Tests\Feature\Payment;

use App\Domain\Payments\CustomerPaymentProofPolicy;
use App\Domain\Payments\CustomerPaymentProofViewModelFactory;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderPaymentProofEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomerPaymentProofViewModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_exposes_only_customer_safe_bank_proof_state(): void
    {
        [$order, $payment] = $this->payment('rejected');
        $payment->update([
            'proof_path' => 'payment-proofs/1/private.pdf', 'proof_disk' => 'local',
            'proof_original_name' => 'receipt.pdf', 'proof_size' => 2048,
            'proof_sha256' => str_repeat('a', 64), 'proof_uploaded_at' => now(),
        ]);
        OrderPaymentProofEvent::query()->create([
            'order_id' => $order->id, 'order_payment_id' => $payment->id,
            'event_type' => 'rejected', 'reason' => 'Older rejection reason.',
        ]);
        OrderPaymentProofEvent::query()->create([
            'order_id' => $order->id, 'order_payment_id' => $payment->id,
            'event_type' => 'rejected', 'reason' => 'Amount is not visible.',
        ]);

        $view = app(CustomerPaymentProofViewModelFactory::class)->for($order, $payment->fresh());

        $this->assertSame('rejected', $view['proofStatus']);
        $this->assertSame('Amount is not visible.', $view['rejectionReason']);
        $this->assertTrue($view['canUpload']);
        $this->assertTrue($view['canReplace']);
        $this->assertSame('receipt.pdf', $view['originalFileName']);
        $this->assertArrayNotHasKey('proof_path', $view);
        $this->assertArrayNotHasKey('proof_disk', $view);
        $this->assertArrayNotHasKey('proof_sha256', $view);
    }

    public function test_verified_and_non_bank_payments_are_not_customer_mutable(): void
    {
        [$order, $verified] = $this->payment('verified');
        $view = app(CustomerPaymentProofViewModelFactory::class)->for($order, $verified);
        $this->assertFalse($view['canUpload']);
        $this->assertFalse($view['canReplace']);

        $card = OrderPayment::query()->create([
            'order_id' => $order->id, 'gateway' => 'stripe', 'provider' => 'stripe',
            'environment' => 'sandbox', 'status' => 'pending', 'reference' => 'CARD-1',
            'idempotency_key' => 'card-1', 'amount' => 10000, 'currency' => 'MYR',
        ]);
        $this->assertNull(app(CustomerPaymentProofViewModelFactory::class)->for($order, $card));
    }

    public function test_policy_accepts_owner_or_exact_checkout_session_and_rejects_others(): void
    {
        [$order, $payment, $owner] = $this->payment();
        $policy = app(CustomerPaymentProofPolicy::class);

        $ownerRequest = Request::create('/');
        $ownerRequest->setUserResolver(fn () => $owner);
        $this->assertTrue($policy->allows($ownerRequest, $order, $payment));

        $sessionRequest = Request::create('/');
        $sessionRequest->setLaravelSession(app('session')->driver());
        $sessionRequest->session()->put('last_order_id', $order->id);
        $this->assertTrue($policy->allows($sessionRequest, $order, $payment));

        $other = User::factory()->create(['is_admin' => false]);
        $otherRequest = Request::create('/');
        $otherRequest->setUserResolver(fn () => $other);
        $this->assertFalse($policy->allows($otherRequest, $order, $payment));
    }

    private function payment(string $proofStatus = 'none'): array
    {
        $user = User::factory()->create(['is_admin' => false]);
        $customer = Customer::query()->create(['user_id' => $user->id, 'email' => $user->email]);
        $order = Order::query()->create([
            'number' => 'JW-PROOF-1', 'customer_id' => $customer->id, 'email' => $user->email,
            'currency' => 'MYR', 'status' => 'pending_payment', 'shipping_method' => 'standard',
            'subtotal' => 10000, 'discount_amount' => 0, 'shipping_amount' => 0,
            'payment_surcharge_amount' => 0, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $payment = OrderPayment::query()->create([
            'order_id' => $order->id, 'gateway' => 'bank_transfer', 'provider' => 'bank_transfer',
            'environment' => 'offline', 'status' => 'pending', 'reference' => 'BANK-1',
            'idempotency_key' => 'proof-1-'.uniqid(), 'amount' => 10000, 'currency' => 'MYR',
            'proof_status' => $proofStatus,
        ]);

        return [$order, $payment, $user];
    }
}
