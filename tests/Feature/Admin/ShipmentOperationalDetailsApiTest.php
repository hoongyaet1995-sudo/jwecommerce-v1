<?php

namespace Tests\Feature\Admin;

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
}
