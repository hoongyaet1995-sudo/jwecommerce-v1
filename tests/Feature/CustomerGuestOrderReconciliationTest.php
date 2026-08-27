<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerGuestOrderReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_claims_the_existing_guest_customer_and_its_orders_by_email(): void
    {
        $guest = Customer::query()->create([
            'email' => 'guest@example.test',
            'first_name' => 'Guest',
            'last_name' => 'Buyer',
        ]);
        $order = Order::query()->create([
            'number' => 'JW-GUEST-1001',
            'customer_id' => $guest->id,
            'email' => $guest->email,
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);

        $this->post('/account/register', [
            'name' => 'Guest Buyer',
            'email' => 'GUEST@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ])->assertRedirect('/account');

        self::assertSame(1, Customer::query()->whereRaw('lower(email) = ?', ['guest@example.test'])->count());
        self::assertSame($guest->id, $order->fresh()->customer_id);
        self::assertNotNull($guest->fresh()->user_id);
    }
}
