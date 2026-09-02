<?php

namespace App\Services\Shipping;

use App\Exceptions\ActiveShipmentCorrectionExists;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\ShipmentCorrection;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FulfilmentCorrectionService
{
    public function completeForReplacement(Shipment $replacement): void
    {
        DB::transaction(function () use ($replacement): void {
            $lockedReplacement = Shipment::query()->lockForUpdate()->findOrFail($replacement->id);
            $correction = ShipmentCorrection::query()
                ->where('active_order_id', $lockedReplacement->order_id)
                ->lockForUpdate()
                ->first();

            if ($correction === null) {
                return;
            }
            if ($correction->source_shipment_id === $lockedReplacement->id) {
                throw ValidationException::withMessages(['shipment' => 'A replacement must be a new shipment attempt.']);
            }

            $correction->forceFill([
                'replacement_shipment_id' => $lockedReplacement->id,
                'active_order_id' => null,
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();
        });
    }

    /** @param array{reason_code:string,internal_note:string,external_action_state:string,external_action_note?:?string} $input */
    public function start(Shipment $shipment, User $actor, array $input): ShipmentCorrection
    {
        return DB::transaction(function () use ($shipment, $actor, $input): ShipmentCorrection {
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedShipment->order_id);

            if ($lockedShipment->status === 'delivered' || $lockedShipment->delivered_at !== null) {
                throw ValidationException::withMessages(['shipment' => 'Delivered shipments must use the return or reshipment workflow.']);
            }
            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'Cancelled orders cannot start a fulfilment correction.']);
            }
            if (ShipmentCorrection::query()->where('active_order_id', $order->id)->lockForUpdate()->exists()) {
                throw new ActiveShipmentCorrectionExists;
            }

            $snapshot = $lockedShipment->only([
                'id', 'order_id', 'sequence', 'source', 'fulfillment_method', 'carrier_code',
                'carrier_name', 'service_code', 'service_name', 'tracking_number', 'status',
                'shipping_cost', 'currency', 'provider_reference', 'provider_order_reference',
                'tracking_url', 'driver_name', 'handoff_reference', 'shipment_quote_id',
                'quote_snapshot', 'package_snapshot', 'dispatched_at', 'delivered_at', 'cancelled_at',
            ]);

            $correction = ShipmentCorrection::query()->create([
                'order_id' => $order->id,
                'source_shipment_id' => $lockedShipment->id,
                'active_order_id' => $order->id,
                'reason_code' => $input['reason_code'],
                'internal_note' => $input['internal_note'],
                'original_snapshot' => $snapshot,
                'external_action_state' => $input['external_action_state'],
                'external_action_note' => $input['external_action_note'] ?? null,
                'status' => 'awaiting_replacement',
                'created_by' => $actor->id,
            ]);

            $previousShipmentStatus = $lockedShipment->status;
            $lockedShipment->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
            ShipmentEvent::query()->create([
                'shipment_id' => $lockedShipment->id,
                'status' => 'cancelled',
                'occurred_at' => now(),
                'description' => 'Shipment attempt voided for fulfilment correction: '.$input['internal_note'],
                'source' => 'manual',
                'created_by' => $actor->id,
            ]);

            $orderUpdates = ['status' => 'processing'];
            if ($order->tracking_number === $lockedShipment->tracking_number) {
                $orderUpdates['tracking_number'] = null;
                $orderUpdates['tracking_carrier'] = null;
                $orderUpdates['shipped_at'] = null;
            }
            $fromOrderStatus = $order->status;
            $order->forceFill($orderUpdates)->save();
            if ($fromOrderStatus !== 'processing') {
                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'from_status' => $fromOrderStatus,
                    'to_status' => 'processing',
                    'user_id' => $actor->id,
                    'note' => 'Shipment attempt '.$lockedShipment->id.' corrected; customer order remains active. Previous shipment status: '.$previousShipmentStatus.'.',
                ]);
            }

            return $correction->fresh();
        });
    }
}
