<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderAddressAmendment;
use App\Models\User;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class OrderDeliveryDetailsApiTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_address_amendment_preserves_diff_sync_result_and_actor(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $order = Order::query()->create([
            'number' => 'JW-ADDRESS-AMEND-1', 'email' => 'address@example.test', 'currency' => 'MYR',
            'status' => 'processing', 'subtotal' => 10000, 'discount_amount' => 0,
            'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $address = $order->addresses()->create([
            'type' => 'shipping', 'first_name' => 'Tee', 'last_name' => 'Jing',
            'phone' => '0143323862', 'line_1' => 'Old address', 'city' => 'Shah Alam',
            'state' => 'Selangor', 'postal_code' => '45600', 'country' => 'Malaysia',
        ]);

        $amendment = OrderAddressAmendment::query()->create([
            'order_id' => $order->id,
            'order_address_id' => $address->id,
            'before_snapshot' => ['line_1' => 'Old address'],
            'after_snapshot' => ['line_1' => 'New address'],
            'reason_code' => 'customer_request',
            'internal_note' => 'Customer verified new address.',
            'address_book_sync_requested' => false,
            'address_book_sync_result' => 'not_requested',
            'created_by' => $actor->id,
        ]);

        $this->assertFalse($amendment->address_book_sync_requested);
        $this->assertSame('New address', $amendment->after_snapshot['line_1']);
        $this->assertTrue($order->addressAmendments->contains($amendment));
        $this->assertTrue($address->amendments->contains($amendment));
        $this->assertTrue($actor->is($amendment->creator));
    }
}
