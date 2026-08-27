<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderTrackingPayloadFactory;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingReadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_payload_uses_persisted_shipment_events_and_never_invents_an_eta(): void
    {
        $customer = Customer::query()->create(['email' => 'guest@example.test']);
        $order = Order::query()->create([
            'number' => 'JW-TRACK-1001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'shipped',
            'tracking_carrier' => 'Manual Warehouse',
            'tracking_number' => 'TRACK-1001',
            'shipped_at' => '2026-08-27 09:30:00',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => 'processing',
            'to_status' => 'shipped',
            'note' => 'Dispatched via Manual Warehouse · TRACK-1001',
            'created_at' => '2026-08-27 09:30:00',
            'updated_at' => '2026-08-27 09:30:00',
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'manual',
            'carrier_name' => 'Manual Warehouse',
            'tracking_number' => 'TRACK-1001',
            'status' => 'shipped',
            'currency' => 'MYR',
            'shipping_cost' => 0,
            'dispatched_at' => '2026-08-27 09:30:00',
        ]);
        ShipmentEvent::query()->create([
            'shipment_id' => $shipment->id,
            'status' => 'shipped',
            'occurred_at' => '2026-08-27 09:30:00',
            'description' => 'Manually dispatched via Manual Warehouse · TRACK-1001',
            'source' => 'manual',
        ]);

        $payload = app(OrderTrackingPayloadFactory::class)->for($order->fresh());

        self::assertSame('Manual Warehouse', data_get($payload, 'tracking.carrier'));
        self::assertSame('TRACK-1001', data_get($payload, 'tracking.number'));
        self::assertNull(data_get($payload, 'tracking.estimatedDelivery'));
        self::assertContains(
            'Manually dispatched via Manual Warehouse · TRACK-1001',
            collect(data_get($payload, 'timeline'))->pluck('description')->all(),
        );
    }

    public function test_verified_payload_reads_purchased_items_and_delivery_destination_from_the_order(): void
    {
        $customer = Customer::query()->create(['email' => 'buyer@example.test']);
        $order = Order::query()->create([
            'number' => 'JW-TRACK-ITEMS', 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'processing', 'shipping_label' => 'Express Tracked',
            'subtotal' => 19900, 'discount_amount' => 0, 'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 19900,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'sku' => 'SKU-TRACK-1', 'title' => 'Real Order Item',
            'variant_title' => 'Black', 'image_url' => '/images/real-item.jpg', 'unit_price' => 19900,
            'quantity' => 1, 'line_total' => 19900,
        ]);
        OrderAddress::query()->create([
            'order_id' => $order->id, 'type' => 'shipping', 'first_name' => 'Real', 'last_name' => 'Buyer',
            'line_1' => '1 Commerce Road', 'city' => 'Kuala Lumpur', 'state' => 'KL',
            'postal_code' => '50000', 'country' => 'Malaysia',
        ]);

        $payload = app(OrderTrackingPayloadFactory::class)->for($order->fresh());

        self::assertSame('Real Order Item', data_get($payload, 'items.0.title'));
        self::assertSame('1 Commerce Road', data_get($payload, 'address.line1'));
        self::assertSame('Express Tracked', data_get($payload, 'shippingLabel'));
    }
}
