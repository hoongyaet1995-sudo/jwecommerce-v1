<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShipmentService
{
    public function dispatchManually(Order $order, User $actor, string $carrierName, string $trackingNumber): Shipment
    {
        return DB::transaction(function () use ($order, $actor, $carrierName, $trackingNumber): Shipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== 'processing') {
                throw new InvalidArgumentException('Only processing orders can be dispatched.');
            }

            $shipment = Shipment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('sequence', 1)
                ->lockForUpdate()
                ->first();

            if ($shipment && $shipment->tracking_number && $shipment->tracking_number !== $trackingNumber) {
                throw new InvalidArgumentException('This order already has a dispatched shipment.');
            }

            $trackingAlreadyAssigned = Shipment::query()
                ->where('tracking_number', trim($trackingNumber))
                ->when($shipment, fn ($query) => $query->whereKeyNot($shipment->id))
                ->exists();
            if ($trackingAlreadyAssigned) {
                throw new InvalidArgumentException('This tracking number is already assigned to another shipment.');
            }

            $shipment ??= new Shipment(['order_id' => $lockedOrder->id, 'sequence' => 1]);
            $shipment->fill([
                'source' => 'manual',
                'carrier_name' => trim($carrierName),
                'tracking_number' => trim($trackingNumber),
                'status' => 'shipped',
                'currency' => $lockedOrder->currency,
                'shipping_cost' => $lockedOrder->shipping_amount,
                'dispatched_at' => now(),
                'created_by' => $actor->id,
            ]);
            $shipment->save();

            ShipmentEvent::query()->create([
                'shipment_id' => $shipment->id,
                'status' => 'shipped',
                'occurred_at' => now(),
                'description' => 'Manually dispatched via '.$shipment->carrier_name.' · '.$shipment->tracking_number,
                'source' => 'manual',
                'created_by' => $actor->id,
            ]);

            $from = $lockedOrder->status;
            $lockedOrder->update([
                'status' => 'shipped',
                'tracking_carrier' => $shipment->carrier_name,
                'tracking_number' => $shipment->tracking_number,
                'shipped_at' => $shipment->dispatched_at,
            ]);
            OrderStatusHistory::query()->create([
                'order_id' => $lockedOrder->id,
                'from_status' => $from,
                'to_status' => 'shipped',
                'user_id' => $actor->id,
                'note' => 'Dispatched via '.$shipment->carrier_name.' · '.$shipment->tracking_number,
            ]);

            return $shipment->fresh(['events']);
        });
    }

    public function appendManualEvent(Shipment $shipment, User $actor, string $status, string $description, ?string $location = null): ShipmentEvent
    {
        return DB::transaction(function () use ($shipment, $actor, $status, $description, $location): ShipmentEvent {
            $lockedShipment = Shipment::query()->with('order')->lockForUpdate()->findOrFail($shipment->id);

            if (! in_array($status, $this->allowedNextStatuses($lockedShipment->status), true)) {
                throw new InvalidArgumentException('This shipment cannot transition from '.$lockedShipment->status.' to '.$status.'.');
            }

            $occurredAt = now();
            $lockedShipment->update([
                'status' => $status,
                'delivered_at' => $status === 'delivered' ? $occurredAt : $lockedShipment->delivered_at,
            ]);

            if ($status === 'delivered' && $lockedShipment->order->status !== 'delivered') {
                $from = $lockedShipment->order->status;
                $lockedShipment->order->update(['status' => 'delivered']);
                OrderStatusHistory::query()->create([
                    'order_id' => $lockedShipment->order_id,
                    'from_status' => $from,
                    'to_status' => 'delivered',
                    'user_id' => $actor->id,
                    'note' => $description,
                ]);
            }

            return ShipmentEvent::query()->create([
                'shipment_id' => $lockedShipment->id,
                'status' => $status,
                'occurred_at' => $occurredAt,
                'location' => $location,
                'description' => trim($description),
                'source' => 'manual',
                'created_by' => $actor->id,
            ]);
        });
    }

    /** @return list<string> */
    private function allowedNextStatuses(string $status): array
    {
        return match ($status) {
            'shipped' => ['in_transit', 'failed_delivery', 'returned'],
            'in_transit' => ['out_for_delivery', 'failed_delivery', 'returned'],
            'out_for_delivery' => ['delivered', 'failed_delivery', 'returned'],
            default => [],
        };
    }
}
