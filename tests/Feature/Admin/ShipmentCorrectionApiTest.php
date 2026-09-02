<?php

namespace Tests\Feature\Admin;

use App\Domain\Shipping\ShippingPermission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentCorrection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class ShipmentCorrectionApiTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_only_one_active_correction_can_exist_for_an_order_and_snapshots_are_cast(): void
    {
        [$order, $shipment, $actor] = $this->fixtures('JW-CORRECTION-LEDGER-1');

        $correction = ShipmentCorrection::query()->create([
            'order_id' => $order->id,
            'source_shipment_id' => $shipment->id,
            'active_order_id' => $order->id,
            'reason_code' => 'wrong_fulfilment_method',
            'internal_note' => 'Wrong method selected by staff.',
            'original_snapshot' => ['tracking_number' => 'MANUAL-1001'],
            'external_action_state' => 'not_required',
            'status' => 'awaiting_replacement',
            'created_by' => $actor->id,
        ]);

        $this->assertSame('MANUAL-1001', $correction->original_snapshot['tracking_number']);
        $this->assertTrue($shipment->corrections->contains($correction));
        $this->assertTrue($order->shipmentCorrections->contains($correction));
        $this->assertTrue($actor->is($correction->creator));

        $this->expectException(QueryException::class);
        ShipmentCorrection::query()->create([
            'order_id' => $order->id,
            'source_shipment_id' => $shipment->id,
            'active_order_id' => $order->id,
            'reason_code' => 'wrong_details',
            'internal_note' => 'Duplicate active correction.',
            'original_snapshot' => [],
            'external_action_state' => 'not_required',
            'status' => 'awaiting_replacement',
            'created_by' => $actor->id,
        ]);
    }

    public function test_dispatch_staff_can_void_an_attempt_without_cancelling_or_repricing_the_order(): void
    {
        [$order, $shipment, $actor] = $this->fixtures('JW-CORRECTION-API-1');
        $actor->permissions()->create(['permission' => ShippingPermission::DISPATCH]);
        $order->forceFill([
            'status' => 'shipped',
            'tracking_carrier' => $shipment->carrier_name,
            'tracking_number' => $shipment->tracking_number,
            'shipped_at' => now()->subDay(),
            'payment_surcharge_amount' => 300,
        ])->save();
        $financials = $order->only([
            'subtotal', 'discount_amount', 'shipping_amount', 'payment_surcharge_amount', 'tax_amount', 'total',
        ]);

        $response = $this->actingAs($actor)->postJson(
            '/admin/api/shipping/shipments/'.$shipment->id.'/corrections',
            [
                'reasonCode' => 'wrong_fulfilment_method',
                'internalNote' => 'Staff selected the wrong dispatch method.',
                'externalActionState' => 'not_required',
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_replacement')
            ->assertJsonPath('data.orderId', (string) $order->id)
            ->assertJsonPath('data.sourceShipmentId', (string) $shipment->id);

        $order->refresh();
        $shipment->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertNull($order->tracking_carrier);
        $this->assertNull($order->tracking_number);
        $this->assertSame($financials, $order->only(array_keys($financials)));
        $this->assertSame('cancelled', $shipment->status);
        $this->assertNotNull($shipment->cancelled_at);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'status' => 'cancelled',
            'source' => 'manual',
            'created_by' => $actor->id,
        ]);
        $this->assertDatabaseMissing('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'cancelled',
        ]);
    }

    public function test_correction_requires_permission_and_blocks_delivered_or_cancelled_business_state(): void
    {
        [$order, $shipment] = $this->fixtures('JW-CORRECTION-BLOCK-1');
        $viewer = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $viewer->permissions()->create(['permission' => ShippingPermission::VIEW]);
        $payload = [
            'reasonCode' => 'wrong_fulfilment_method',
            'internalNote' => 'A sufficiently detailed audit note.',
            'externalActionState' => 'not_required',
        ];

        $this->actingAs($viewer)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/corrections', $payload)
            ->assertForbidden();

        $shipment->forceFill(['status' => 'delivered', 'delivered_at' => now()])->save();
        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/corrections', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipment');

        $shipment->forceFill(['status' => 'in_transit', 'delivered_at' => null])->save();
        $order->forceFill(['status' => 'cancelled'])->save();
        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/corrections', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_provider_attempt_requires_truthful_external_action_evidence_and_duplicate_is_conflict(): void
    {
        [$order, $shipment] = $this->fixtures('JW-CORRECTION-PROVIDER-1');
        $shipment->forceFill(['source' => 'easyparcel', 'fulfillment_method' => 'easyparcel'])->save();
        $url = '/admin/api/shipping/shipments/'.$shipment->id.'/corrections';

        $this->actingAs($this->superAdmin())->postJson($url, [
            'reasonCode' => 'wrong_fulfilment_method',
            'internalNote' => 'Provider booking must be replaced.',
            'externalActionState' => 'not_required',
        ])->assertUnprocessable()->assertJsonValidationErrors('externalActionState');

        $payload = [
            'reasonCode' => 'wrong_fulfilment_method',
            'internalNote' => 'Provider cancellation checked in dashboard.',
            'externalActionState' => 'staff_confirmed_external',
            'externalActionNote' => 'Staff double-checked EasyParcel dashboard and accepts external risk.',
        ];
        $this->actingAs($this->superAdmin())->postJson($url, $payload)->assertCreated();
        $this->actingAs($this->superAdmin())->postJson($url, $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'active_correction_exists');
    }

    public function test_team_managed_replacement_creates_a_new_attempt_and_completes_the_correction(): void
    {
        [$order, $source, $actor] = $this->fixtures('JW-CORRECTION-REPLACE-1');
        $actor->permissions()->create(['permission' => ShippingPermission::DISPATCH]);
        $this->actingAs($actor)->postJson('/admin/api/shipping/shipments/'.$source->id.'/corrections', [
            'reasonCode' => 'wrong_fulfilment_method',
            'internalNote' => 'Replace the original manual method.',
            'externalActionState' => 'not_required',
        ])->assertCreated();

        $response = $this->actingAs($actor)->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
            'fulfillmentMethod' => 'external_courier',
            'courierCode' => 'JNT',
            'courierName' => 'J&T Express',
            'trackingNumber' => 'JNT-REPLACEMENT-1001',
            'trackingUrl' => 'https://tracking.example.test/JNT-REPLACEMENT-1001',
            'auditNote' => 'Replacement external courier confirmed.',
        ]);

        $response->assertCreated();
        $replacement = Shipment::query()->findOrFail((int) $response->json('data.id'));
        $this->assertNotSame($source->id, $replacement->id);
        $this->assertSame(2, $replacement->sequence);
        $this->assertSame('cancelled', $source->fresh()->status);
        $this->assertDatabaseHas('shipment_corrections', [
            'order_id' => $order->id,
            'source_shipment_id' => $source->id,
            'replacement_shipment_id' => $replacement->id,
            'active_order_id' => null,
            'status' => 'completed',
        ]);
    }

    public function test_shipment_read_model_exposes_one_resumable_pending_row_for_an_active_correction(): void
    {
        [$order, $source, $actor] = $this->fixtures('JW-CORRECTION-READ-1');
        $actor->permissions()->createMany([
            ['permission' => ShippingPermission::DISPATCH],
            ['permission' => ShippingPermission::VIEW],
        ]);
        $created = $this->actingAs($actor)->postJson('/admin/api/shipping/shipments/'.$source->id.'/corrections', [
            'reasonCode' => 'wrong_fulfilment_method',
            'internalNote' => 'Resume this order using another method.',
            'externalActionState' => 'not_required',
        ])->assertCreated();

        $rows = $this->actingAs($actor)->getJson('/admin/api/shipping/shipments')->assertOk()->json('data');
        $pending = collect($rows)->firstWhere('id', 'pending-order-'.$order->id);

        $this->assertNotNull($pending);
        $this->assertSame($created->json('data.id'), data_get($pending, 'activeCorrection.id'));
        $this->assertSame((string) $source->id, data_get($pending, 'activeCorrection.sourceShipmentId'));
        $this->assertTrue(data_get($pending, 'activeCorrection.readyToResume'));
    }

    /** @return array{Order, Shipment, User} */
    private function fixtures(string $number): array
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $customer = Customer::query()->create([
            'email' => strtolower($number).'@example.test',
            'first_name' => 'Correction',
            'last_name' => 'Buyer',
            'phone' => '0143323862',
        ]);
        $order = Order::query()->create([
            'number' => $number, 'customer_id' => $customer->id, 'email' => $customer->email, 'currency' => 'MYR',
            'status' => 'processing', 'subtotal' => 10000, 'discount_amount' => 0,
            'shipping_amount' => 0, 'tax_amount' => 600, 'total' => 10600,
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id, 'sequence' => 1, 'source' => 'manual',
            'fulfillment_method' => 'manual_delivery', 'carrier_name' => 'Manual Delivery',
            'tracking_number' => 'MANUAL-1001', 'status' => 'in_transit', 'currency' => 'MYR',
        ]);

        return [$order, $shipment, $actor];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }
}
