<?php

namespace Tests\Feature\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPaymentProofUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_session_can_upload_private_bank_proof(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->payment();

        $response = $this->withSession(['last_order_id' => $order->id])
            ->postJson(route('customer.payment-proofs.store', [$order, $payment]), [
                'payment_proof' => UploadedFile::fake()->create('duitnow.png', 100, 'image/png'),
            ])->assertOk()->assertJsonPath('data.proofStatus', 'uploaded');

        $payment->refresh();
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertSame('uploaded', $payment->proof_status);
        $this->assertDatabaseHas('order_payment_proof_events', ['order_payment_id' => $payment->id, 'event_type' => 'uploaded']);
        $response->assertJsonMissingPath('data.proof_path');
    }

    public function test_owner_can_resubmit_rejected_proof_but_other_customer_cannot(): void
    {
        Storage::fake('local');
        [$order, $payment, $owner] = $this->payment('rejected');
        Storage::disk('local')->put('payment-proofs/'.$order->id.'/rejected.pdf', 'old-proof');
        $payment->update([
            'proof_disk' => 'local',
            'proof_path' => 'payment-proofs/'.$order->id.'/rejected.pdf',
        ]);
        $other = User::factory()->create(['is_admin' => false]);
        Customer::query()->create(['user_id' => $other->id, 'email' => $other->email]);

        $this->actingAs($other, 'customer')->postJson(route('customer.payment-proofs.store', [$order, $payment]), [
            'payment_proof' => UploadedFile::fake()->create('other.pdf', 20, 'application/pdf'),
        ])->assertNotFound();

        $this->actingAs($owner, 'customer')->postJson(route('customer.payment-proofs.store', [$order, $payment]), [
            'payment_proof' => UploadedFile::fake()->create('replacement.pdf', 20, 'application/pdf'),
        ])->assertOk()->assertJsonPath('data.proofStatus', 'uploaded');

        Storage::disk('local')->assertMissing('payment-proofs/'.$order->id.'/rejected.pdf');
        Storage::disk('local')->assertExists($payment->fresh()->proof_path);
    }

    public function test_verified_proof_cannot_be_replaced_and_invalid_file_is_rejected(): void
    {
        Storage::fake('local');
        [$order, $payment, $owner] = $this->payment('verified');
        $this->actingAs($owner, 'customer')->postJson(route('customer.payment-proofs.store', [$order, $payment]), [
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
        ])->assertNotFound();

        [$pendingOrder, $pendingPayment, $pendingOwner] = $this->payment();
        $this->actingAs($pendingOwner, 'customer')->postJson(route('customer.payment-proofs.store', [$pendingOrder, $pendingPayment]), [
            'payment_proof' => UploadedFile::fake()->create('proof.txt', 20, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_proof');
    }

    private function payment(string $proofStatus = 'none'): array
    {
        $user = User::factory()->create(['is_admin' => false]);
        $customer = Customer::query()->create(['user_id' => $user->id, 'email' => $user->email]);
        $order = Order::query()->create([
            'number' => 'JW-UPLOAD-'.uniqid(), 'customer_id' => $customer->id, 'email' => $user->email,
            'currency' => 'MYR', 'status' => 'pending_payment', 'shipping_method' => 'standard',
            'subtotal' => 10000, 'discount_amount' => 0, 'shipping_amount' => 0,
            'payment_surcharge_amount' => 0, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $payment = OrderPayment::query()->create([
            'order_id' => $order->id, 'gateway' => 'bank_transfer', 'provider' => 'bank_transfer',
            'environment' => 'offline', 'status' => 'pending', 'reference' => 'BANK-'.uniqid(),
            'idempotency_key' => 'proof-'.uniqid(), 'amount' => 10000, 'currency' => 'MYR',
            'proof_status' => $proofStatus,
        ]);

        return [$order, $payment, $user];
    }
}
