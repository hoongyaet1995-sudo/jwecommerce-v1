<?php

namespace Tests\Feature\Admin;

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

    /** @return array{Order, Shipment, User} */
    private function fixtures(string $number): array
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $order = Order::query()->create([
            'number' => $number, 'email' => 'ledger@example.test', 'currency' => 'MYR',
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
}
