<?php

namespace Tests\Feature;

use App\Mail\OrderTrackingCodeMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderTrackingVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTrackingVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_order_and_email_create_a_hashed_expiring_attempt_without_exposing_the_code(): void
    {
        Mail::fake();
        $order = $this->orderFor('guest@example.test');

        $this->post('/order-tracking/request', [
            'order_number' => $order->number,
            'email' => 'GUEST@example.test',
        ])->assertRedirect('/order-tracking?step=verify');

        $attempt = \App\Models\OrderTrackingVerification::query()->firstOrFail();

        self::assertSame($order->id, $attempt->order_id);
        self::assertNotSame('123456', $attempt->code_hash);
        self::assertTrue($attempt->expires_at->isFuture());
        Mail::assertQueued(OrderTrackingCodeMail::class);
    }

    public function test_mismatched_lookup_uses_the_same_verify_redirect_without_email_or_order_access(): void
    {
        Mail::fake();

        $this->post('/order-tracking/request', [
            'order_number' => 'JW-UNKNOWN-1000',
            'email' => 'nobody@example.test',
        ])->assertRedirect('/order-tracking?step=verify');

        Mail::assertNothingQueued();

        $this->post('/order-tracking/verify', ['code' => '123456'])
            ->assertRedirect('/order-tracking?step=verify')
            ->assertSessionHas('order_tracking.error', 'We couldn’t verify those details. Please check your order number and email, or request a new code.');
    }

    public function test_expired_or_wrong_code_cannot_grant_a_verified_order_session(): void
    {
        $attempt = OrderTrackingVerification::query()->create([
            'public_id' => (string) Str::uuid(),
            'order_id' => $this->orderFor('guest@example.test')->id,
            'email_hash' => hash('sha256', 'guest@example.test'),
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withSession(['order_tracking.attempt_id' => $attempt->public_id])
            ->post('/order-tracking/verify', ['code' => '123456'])
            ->assertRedirect('/order-tracking?step=verify')
            ->assertSessionMissing('order_tracking.verified_attempt_id');
    }

    public function test_resend_replaces_the_attempt_for_the_same_session_bound_lookup(): void
    {
        Mail::fake();
        $order = $this->orderFor('guest@example.test');
        $first = OrderTrackingVerification::query()->create([
            'public_id' => (string) Str::uuid(), 'order_id' => $order->id,
            'email_hash' => hash('sha256', 'guest@example.test'), 'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession([
            'order_tracking.attempt_id' => $first->public_id,
            'order_tracking.email' => 'guest@example.test',
            'order_tracking.order_number' => $order->number,
        ])->post('/order-tracking/resend')
            ->assertRedirect('/order-tracking?step=verify');

        self::assertSame(2, OrderTrackingVerification::query()->count());
        self::assertNotNull($first->fresh()->consumed_at);
        Mail::assertQueued(OrderTrackingCodeMail::class);
    }

    private function orderFor(string $email): Order
    {
        $customer = Customer::query()->create([
            'email' => $email,
            'first_name' => 'Guest',
            'last_name' => 'Buyer',
        ]);

        return Order::query()->create([
            'number' => 'JW-TRACK-1001',
            'customer_id' => $customer->id,
            'email' => $email,
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
    }
}
