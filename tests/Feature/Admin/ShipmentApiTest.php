<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_dispatch_creates_one_shipment_and_an_append_only_event(): void
    {
        $order = $this->processingOrder();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
                'carrierName' => 'Manual courier',
                'trackingNumber' => 'MANUAL-TRACK-1001',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'tracking_number' => 'MANUAL-TRACK-1001',
            'status' => 'shipped',
            'source' => 'manual',
        ]);
        $this->assertDatabaseHas('shipment_events', [
            'status' => 'shipped',
            'source' => 'manual',
        ]);
    }

    public function test_non_permitted_admin_cannot_dispatch_a_shipment(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->postJson('/admin/api/shipping/orders/'.$this->processingOrder()->id.'/dispatch', [
                'carrierName' => 'Manual courier',
                'trackingNumber' => 'DENIED-1001',
            ])
            ->assertForbidden();
    }

    public function test_event_append_rejects_backward_transition_and_keeps_history_intact(): void
    {
        $admin = $this->superAdmin();
        $order = $this->processingOrder();

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'LIFECYCLE-1001',
        ])->assertCreated();

        $shipment = Shipment::query()->sole();

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
                'status' => 'pending',
                'description' => 'Attempted rewind',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('shipment_events', 1);
    }

    public function test_shipment_index_returns_a_real_order_backed_read_model(): void
    {
        $admin = $this->superAdmin();
        $order = $this->processingOrder();

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'READMODEL-1001',
        ])->assertCreated();

        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.orderNumber', $order->number)
            ->assertJsonPath('data.0.trackingNumber', 'READMODEL-1001');
    }

    public function test_authorized_staff_can_append_a_forward_event_and_order_delivery_is_recorded(): void
    {
        $admin = $this->superAdmin();
        $order = $this->processingOrder();

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'FORWARD-1001',
        ])->assertCreated();

        $shipment = Shipment::query()->sole();
        $this->actingAs($admin)->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
            'status' => 'in_transit',
            'description' => 'Accepted at the dispatch depot.',
            'location' => 'Petaling Jaya',
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
            'status' => 'out_for_delivery',
            'description' => 'Assigned to the local delivery route.',
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
            'status' => 'delivered',
            'description' => 'Delivered to the recipient.',
        ])->assertCreated();

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
        $this->assertDatabaseCount('shipment_events', 4);
    }

    public function test_duplicate_tracking_number_is_rejected_for_a_different_order(): void
    {
        $admin = $this->superAdmin();
        $first = $this->processingOrder();
        $second = $this->processingOrder('shipment-second@example.com', 'JW-SHIPMENT-1002');

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$first->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'UNIQUE-1001',
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$second->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'UNIQUE-1001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('trackingNumber');
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    private function processingOrder(string $email = 'shipment-buyer@example.com', string $number = 'JW-SHIPMENT-1001'): Order
    {
        $customer = Customer::query()->create([
            'email' => $email,
            'first_name' => 'Shipment',
            'last_name' => 'Buyer',
        ]);

        $order = Order::query()->create([
            'number' => $number,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        $order->statusHistory()->create(['to_status' => 'processing']);

        return $order;
    }
}
