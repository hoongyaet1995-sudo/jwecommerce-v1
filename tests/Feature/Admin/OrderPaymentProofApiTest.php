<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderPaymentProofApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_preview_a_private_bank_transfer_proof_without_a_public_url(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->bankTransferOrderWithProof();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/api/orders/'.$order->id.'/payments/'.$payment->id.'/proof')
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertDontSee('payment-proofs');
    }

    public function test_admin_without_payment_proof_permission_cannot_preview_a_receipt(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->bankTransferOrderWithProof();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);

        $this->actingAs($admin)
            ->get('/admin/api/orders/'.$order->id.'/payments/'.$payment->id.'/proof')
            ->assertForbidden();
    }

    public function test_payment_proof_endpoints_are_not_available_for_non_bank_transfer_gateways(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->bankTransferOrderWithProof();
        $payment->update(['gateway' => 'paypal', 'provider' => 'paypal']);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/api/orders/'.$order->id.'/payments/'.$payment->id.'/proof')
            ->assertNotFound();

        $this->actingAs($admin)
            ->getJson('/admin/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.paymentProof.status', 'not_required')
            ->assertJsonPath('orders.0.paymentProof.previewUrl', null)
            ->assertJsonPath('orders.0.paymentProof.downloadUrl', null);
    }

    public function test_manual_bank_transfer_cannot_be_marked_paid_until_its_proof_is_verified(): void
    {
        [$order] = $this->bankTransferOrderWithProof();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->patchJson('/admin/api/orders/'.$order->id.'/status', ['paymentStatus' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paymentStatus');
    }

    public function test_authorized_reviewer_verifies_a_proof_and_appends_an_audit_event_before_payment_is_marked_paid(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->bankTransferOrderWithProof();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/api/orders/'.$order->id.'/payments/'.$payment->id.'/proof/review', [
                'decision' => 'verified',
                'reason' => 'Amount and reference match the bank statement.',
            ])
            ->assertOk()
            ->assertJsonPath('data.paymentGatewayStatus', 'paid')
            ->assertJsonPath('data.paymentProof.status', 'verified');

        $this->assertDatabaseHas('order_payment_proof_events', [
            'order_payment_id' => $payment->id,
            'user_id' => $admin->id,
            'event_type' => 'verified',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_authorized_reviewer_can_reject_a_proof_with_a_reason_without_marking_the_order_paid(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->bankTransferOrderWithProof();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/api/orders/'.$order->id.'/payments/'.$payment->id.'/proof/review', [
                'decision' => 'rejected',
                'reason' => 'The receipt amount does not match the order total.',
            ])
            ->assertOk()
            ->assertJsonPath('data.paymentGatewayStatus', 'pending')
            ->assertJsonPath('data.paymentProof.status', 'rejected');

        $this->assertDatabaseHas('order_payment_proof_events', [
            'order_payment_id' => $payment->id,
            'user_id' => $admin->id,
            'event_type' => 'rejected',
            'reason' => 'The receipt amount does not match the order total.',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment']);
    }

    public function test_viewing_a_legacy_public_proof_moves_it_to_private_storage_before_streaming(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$order, $payment] = $this->bankTransferOrderWithProof();
        Storage::disk('local')->delete($payment->proof_path);
        Storage::disk('public')->put($payment->proof_path, 'legacy-proof');
        $payment->update(['proof_disk' => 'public']);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/api/orders/'.$order->id.'/payments/'.$payment->id.'/proof')
            ->assertOk();

        Storage::disk('local')->assertExists($payment->proof_path);
        Storage::disk('public')->assertMissing($payment->proof_path);
        $this->assertSame('local', $payment->fresh()->proof_disk);
    }

    public function test_order_payload_infers_safe_preview_metadata_for_a_legacy_bank_transfer_proof(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$order, $payment] = $this->bankTransferOrderWithProof();
        Storage::disk('local')->delete($payment->proof_path);
        Storage::disk('public')->put($payment->proof_path, 'legacy-proof');
        $payment->update([
            'proof_disk' => 'public',
            'proof_original_name' => null,
            'proof_mime_type' => null,
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->getJson('/admin/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.paymentProof.originalFileName', 'receipt.png')
            ->assertJsonPath('orders.0.paymentProof.mimeType', 'image/png');
    }

    public function test_order_payload_identifies_bank_transfer_and_checkout_shipping_source_without_disclosing_proof_path(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->bankTransferOrderWithProof();
        $order->update([
            'shipping_method' => 'peninsular-standard',
            'shipping_label' => 'Peninsular Standard',
            'shipping_snapshot' => [
                'id' => 'peninsular-standard',
                'label' => 'Peninsular Standard',
                'rate_type' => 'flat',
                'calculation_mode' => 'per_order',
            ],
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->getJson('/admin/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.paymentGateway', 'Bank In / DuitNow')
            ->assertJsonPath('orders.0.paymentProvider', 'Manual Bank Transfer')
            ->assertJsonPath('orders.0.paymentReference', 'BANK-TRANSFER-JW-PROOF-1001')
            ->assertJsonPath('orders.0.paymentProof.status', 'uploaded')
            ->assertJsonPath('orders.0.paymentProof.previewUrl', route('admin.api.orders.payments.proof.show', [$order, $payment]))
            ->assertJsonPath('orders.0.shippingSource.checkoutService', 'Peninsular Standard')
            ->assertJsonPath('orders.0.shippingSource.fulfillmentModule', 'Not initiated')
            ->assertDontSee('payment-proofs/'.$order->id);
    }

    /** @return array{0: Order, 1: OrderPayment} */
    private function bankTransferOrderWithProof(): array
    {
        $customer = Customer::query()->create(['email' => 'proof-buyer@example.test', 'first_name' => 'Proof', 'last_name' => 'Buyer']);
        $order = Order::query()->create([
            'number' => 'JW-PROOF-1001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        $payment = OrderPayment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'bank_transfer',
            'provider' => 'bank_transfer',
            'environment' => 'offline',
            'status' => 'pending',
            'reference' => 'BANK-TRANSFER-'.$order->number,
            'proof_path' => 'payment-proofs/'.$order->id.'/receipt.png',
            'proof_disk' => 'local',
            'proof_original_name' => 'receipt.png',
            'proof_mime_type' => 'image/png',
            'proof_status' => 'uploaded',
            'proof_uploaded_at' => now(),
        ]);
        Storage::disk('local')->put($payment->proof_path, 'proof-image-bytes');

        return [$order, $payment];
    }
}
