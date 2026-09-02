<?php

namespace Tests\Feature\Admin;

use App\Domain\Shipping\ShippingPermission;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentOperationalAmendment;
use App\Models\User;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class ShipmentOperationalDetailsApiTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_operational_amendment_preserves_before_and_after_snapshots_with_actor(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $order = Order::query()->create([
            'number' => 'JW-OP-AMEND-1', 'email' => 'ops@example.test', 'currency' => 'MYR',
            'status' => 'processing', 'subtotal' => 10000, 'discount_amount' => 0,
            'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id, 'sequence' => 1, 'source' => 'manual',
            'fulfillment_method' => 'external_courier', 'carrier_name' => 'J&T',
            'tracking_number' => 'OLD-AWB', 'status' => 'shipped', 'currency' => 'MYR',
        ]);

        $amendment = ShipmentOperationalAmendment::query()->create([
            'shipment_id' => $shipment->id,
            'before_snapshot' => ['tracking_number' => 'OLD-AWB'],
            'after_snapshot' => ['tracking_number' => 'NEW-AWB'],
            'reason_code' => 'tracking_typo',
            'internal_note' => 'Corrected transposed digits.',
            'created_by' => $actor->id,
        ]);

        $this->assertSame('OLD-AWB', $amendment->before_snapshot['tracking_number']);
        $this->assertSame('NEW-AWB', $amendment->after_snapshot['tracking_number']);
        $this->assertTrue($shipment->operationalAmendments->contains($amendment));
        $this->assertTrue($actor->is($amendment->creator));
    }

    public function test_update_status_staff_can_correct_external_courier_details_with_an_audit_diff(): void
    {
        [$shipment, $actor] = $this->externalShipment('JW-OP-AMEND-API-1');
        $actor->permissions()->create(['permission' => ShippingPermission::UPDATE_STATUS]);

        $this->actingAs($actor)->patchJson('/admin/api/shipping/shipments/'.$shipment->id.'/operational-details', [
            'courierCode' => 'NINJA',
            'courierName' => 'Ninja Van',
            'serviceName' => 'Ninja Standard',
            'trackingNumber' => 'NINJA-NEW-1001',
            'trackingUrl' => 'https://tracking.example.test/NINJA-NEW-1001',
            'reasonCode' => 'tracking_typo',
            'internalNote' => 'Corrected courier and AWB after portal verification.',
        ])->assertOk()
            ->assertJsonPath('data.trackingNumber', 'NINJA-NEW-1001');

        $shipment->refresh();
        $this->assertSame('Ninja Van', $shipment->carrier_name);
        $this->assertSame('NINJA-NEW-1001', $shipment->tracking_number);
        $this->assertDatabaseHas('shipment_operational_amendments', [
            'shipment_id' => $shipment->id,
            'reason_code' => 'tracking_typo',
            'created_by' => $actor->id,
        ]);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'status' => 'shipped',
            'created_by' => $actor->id,
        ]);
    }

    public function test_operational_details_reject_provider_owned_and_delivered_shipments(): void
    {
        [$shipment] = $this->externalShipment('JW-OP-AMEND-BLOCK-1');
        $shipment->forceFill(['source' => 'easyparcel', 'fulfillment_method' => 'easyparcel'])->save();
        $payload = [
            'trackingNumber' => 'EDITED-AWB',
            'reasonCode' => 'tracking_typo',
            'internalNote' => 'Attempted provider field correction.',
        ];
        $this->actingAs($this->superAdmin())
            ->patchJson('/admin/api/shipping/shipments/'.$shipment->id.'/operational-details', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('shipment');

        $shipment->forceFill(['source' => 'manual', 'fulfillment_method' => 'external_courier', 'status' => 'delivered'])->save();
        $this->actingAs($this->superAdmin())
            ->patchJson('/admin/api/shipping/shipments/'.$shipment->id.'/operational-details', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('shipment');
    }

    /** @return array{Shipment, User} */
    private function externalShipment(string $number): array
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $order = Order::query()->create([
            'number' => $number, 'email' => strtolower($number).'@example.test', 'currency' => 'MYR',
            'status' => 'shipped', 'subtotal' => 10000, 'discount_amount' => 0,
            'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id, 'sequence' => 1, 'source' => 'manual',
            'fulfillment_method' => 'external_courier', 'carrier_code' => 'JNT', 'carrier_name' => 'J&T',
            'service_name' => 'J&T Standard', 'tracking_number' => 'OLD-'.$number,
            'status' => 'shipped', 'currency' => 'MYR',
        ]);

        return [$shipment, $actor];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }
}
