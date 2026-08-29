<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingWebhookReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class EasyParcelWebhookIngressTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_webhook_is_sanitized_deduplicated_and_does_not_mutate_order(): void
    {
        Queue::fake();
        config(['services.easyparcel.webhook_path_token' => 'test-endpoint-token']);
        [$order, $shipment] = $this->easyParcelShipment('ES-2608-WEB01', 'AWB-WEB01');
        $payload = [
            'topic' => 'shipment.tracking.update',
            'uuid' => 'webhook-test-uuid-123',
            'shipment_number' => $shipment->provider_reference,
            'awb_number' => $shipment->tracking_number,
            'latest_shipment_status_code' => 5,
            'latest_tracking_status' => 'Delivered to recipient',
            'timestamp' => '2026-08-29 12:00:00',
            'receiver_email' => 'must-not-persist@example.test',
        ];

        $this->postJson('/webhooks/easyparcel/test-endpoint-token', $payload)->assertOk();
        $this->postJson('/webhooks/easyparcel/test-endpoint-token', $payload)->assertOk();

        $this->assertDatabaseCount('shipping_webhook_receipts', 1);
        $receipt = ShippingWebhookReceipt::query()->sole();
        self::assertSame('shipment.tracking.update', $receipt->topic);
        self::assertSame($shipment->id, $receipt->shipment_id);
        self::assertSame([
            'shipment_number' => 'ES-2608-WEB01',
            'awb_number' => 'AWB-WEB01',
            'latest_shipment_status_code' => 5,
            'timestamp' => '2026-08-29 12:00:00',
        ], $receipt->correlation_snapshot);
        self::assertStringNotContainsString('must-not-persist@example.test', json_encode($receipt->getAttributes()));
        self::assertStringNotContainsString('Delivered to recipient', json_encode($receipt->getAttributes()));
        self::assertSame('processing', $order->fresh()->status);
        self::assertSame('pending', $shipment->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_ondemand_and_unknown_standard_webhooks_are_classified_without_a_lifecycle_mutation(): void
    {
        config(['services.easyparcel.webhook_path_token' => 'test-endpoint-token']);
        [$order, $shipment] = $this->easyParcelShipment('ES-2608-WEB02', 'AWB-WEB02');

        $this->postJson('/webhooks/easyparcel/test-endpoint-token', [
            'topic' => 'ondemand.status.update',
            'uuid' => 'ondemand-uuid',
            'shipment_number' => $shipment->provider_reference,
            'awb_number' => $shipment->tracking_number,
        ])->assertOk();
        $this->postJson('/webhooks/easyparcel/test-endpoint-token', [
            'topic' => 'shipment.status.update',
            'uuid' => 'unknown-uuid',
            'shipment_number' => 'ES-UNKNOWN',
            'awb_number' => 'AWB-UNKNOWN',
        ])->assertOk();

        $this->assertDatabaseHas('shipping_webhook_receipts', [
            'topic' => 'ondemand.status.update',
            'shipment_id' => null,
            'processing_state' => 'ignored',
        ]);
        $this->assertDatabaseHas('shipping_webhook_receipts', [
            'topic' => 'shipment.status.update',
            'shipment_id' => null,
            'processing_state' => 'unmatched',
        ]);
        self::assertSame('processing', $order->fresh()->status);
        self::assertSame('pending', $shipment->fresh()->status);
    }

    public function test_wrong_token_and_oversized_body_are_rejected_without_receipt(): void
    {
        config(['services.easyparcel.webhook_path_token' => 'correct-token']);

        $this->postJson('/webhooks/easyparcel/wrong-token', ['topic' => 'shipment.created'])
            ->assertNotFound();
        $this->call('POST', '/webhooks/easyparcel/correct-token', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['topic' => 'shipment.created', 'padding' => str_repeat('x', 65537)]))
            ->assertStatus(413);

        $this->assertDatabaseCount('shipping_webhook_receipts', 0);
    }

    /** @return array{Order, Shipment} */
    private function easyParcelShipment(string $providerReference, string $awb): array
    {
        $order = Order::query()->create([
            'number' => 'JW-WEBHOOK-'.Str::upper(Str::random(8)),
            'email' => 'webhook-fixture@example.test',
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Sandbox Courier',
            'tracking_number' => $awb,
            'status' => 'pending',
            'currency' => 'MYR',
            'provider_reference' => $providerReference,
        ]);

        return [$order, $shipment];
    }
}
