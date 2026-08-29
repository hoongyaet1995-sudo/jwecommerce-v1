<?php

namespace Tests\Feature\Admin;

use App\Domain\Shipping\ShippingPermission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingWebhookReceipt;
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

    public function test_manual_event_endpoint_cannot_mutate_easyparcel_provider_truth(): void
    {
        $order = $this->processingOrder();
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Sandbox Courier',
            'tracking_number' => 'AWB-PROVIDER-ONLY',
            'provider_reference' => 'ES-PROVIDER-ONLY',
            'status' => 'shipped',
            'currency' => 'MYR',
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
                'status' => 'in_transit',
                'description' => 'Warehouse user cannot confirm a provider milestone.',
                'location' => 'Manual console',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        self::assertSame('shipped', $shipment->fresh()->status);
        self::assertSame('processing', $order->fresh()->status);
        $this->assertDatabaseCount('shipment_events', 0);
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

    public function test_authorized_shipping_viewer_sees_only_safe_webhook_receipt_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $admin->permissions()->create(['permission' => ShippingPermission::VIEW]);
        $order = $this->processingOrder();
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel',
            'tracking_number' => 'AWB-SAFE-01',
            'status' => 'in_transit',
        ]);
        $receivedAt = now()->startOfSecond();
        $processedAt = $receivedAt->copy()->addSecond();
        $receipt = ShippingWebhookReceipt::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'topic' => 'shipment.tracking.update',
            'processing_state' => 'processed',
            'payload_hash' => str_repeat('a', 64),
            'shipment_id' => $shipment->id,
            'correlation_snapshot' => [
                'awb_number' => 'AWB-SAFE-01',
                'receiver_email' => 'must-not-serialize@example.test',
                'receiver_phone' => '+60111111111',
                'receiver_address' => 'Must not serialize',
                'raw_body' => 'must-not-serialize',
            ],
            'attempt_count' => 2,
            'last_error' => null,
            'received_at' => $receivedAt,
            'processed_at' => $processedAt,
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/webhook-receipts')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => (string) $receipt->id,
                    'topic' => 'shipment.tracking.update',
                    'state' => 'processed',
                    'shipmentId' => (string) $shipment->id,
                    'orderNumber' => $order->number,
                    'trackingNumber' => 'AWB-SAFE-01',
                    'receivedAt' => $receivedAt->toIso8601String(),
                    'processedAt' => $processedAt->toIso8601String(),
                    'attemptCount' => 2,
                    'errorCode' => null,
                ]],
            ]);
    }

    public function test_webhook_receipt_read_model_returns_only_the_newest_one_hundred_rows(): void
    {
        $admin = $this->superAdmin();

        foreach (range(1, 101) as $index) {
            ShippingWebhookReceipt::query()->create([
                'provider' => 'easyparcel',
                'environment' => 'sandbox',
                'topic' => 'shipment.tracking.update',
                'processing_state' => 'received',
                'payload_hash' => hash('sha256', 'receipt-'.$index),
                'correlation_snapshot' => ['awb_number' => 'AWB-'.$index],
                'received_at' => now()->addSeconds($index),
            ]);
        }
        ShippingWebhookReceipt::query()->create([
            'provider' => 'another-provider',
            'environment' => 'sandbox',
            'topic' => 'shipment.tracking.update',
            'processing_state' => 'received',
            'payload_hash' => hash('sha256', 'other-provider-receipt'),
            'correlation_snapshot' => ['awb_number' => 'AWB-OTHER-PROVIDER'],
            'received_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/api/shipping/webhook-receipts')
            ->assertOk()
            ->assertJsonCount(100, 'data');

        $this->assertSame('AWB-101', $response->json('data.0.trackingNumber'));
        $this->assertSame('AWB-2', $response->json('data.99.trackingNumber'));
    }

    public function test_webhook_receipt_read_model_requires_admin_authentication_and_shipping_view_permission(): void
    {
        $this->getJson('/admin/api/shipping/webhook-receipts')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->getJson('/admin/api/shipping/webhook-receipts')
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->getJson('/admin/api/shipping/webhook-receipts')
            ->assertForbidden();
    }

    public function test_admin_bootstrap_exposes_safe_webhook_receipt_configuration_without_the_endpoint_token(): void
    {
        config(['services.easyparcel.webhook_path_token' => 'must-not-serialize-webhook-token']);

        $response = $this->actingAs($this->superAdmin())
            ->get('/admin/shipping')
            ->assertOk();

        preg_match('/window\.__JW_ADMIN__\s*=\s*(\{.*\});/sU', $response->getContent(), $matches);
        $bootstrap = json_decode($matches[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(route('admin.api.shipping.webhook-receipts.index'), $bootstrap['easyParcelWebhookReceiptsUrl'] ?? null);
        $this->assertTrue($bootstrap['easyParcelWebhookConfigured'] ?? false);
        $this->assertStringNotContainsString('must-not-serialize-webhook-token', $response->getContent());
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
