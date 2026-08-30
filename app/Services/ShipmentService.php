<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\Shipping\EasyParcelBookingGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShipmentService
{
    /** @var array<string, int> */
    private const SHIPMENT_STATUS_RANK = [
        'pending' => 10,
        'shipped' => 20,
        'in_transit' => 30,
        'failed_delivery' => 40,
        'on_hold' => 40,
        'cancelled' => 40,
        'returned' => 50,
        'delivered' => 60,
    ];

    /** @var list<string> */
    private const EASYPARCEL_EVENT_STATUSES = [
        'pending',
        'shipped',
        'in_transit',
        'delivered',
        'failed_delivery',
        'on_hold',
        'cancelled',
        'returned',
        'provider_update',
    ];

    public function __construct(private readonly EasyParcelBookingGuard $easyParcelBookings) {}

    public function dispatchManually(Order $order, User $actor, string $carrierName, string $trackingNumber): Shipment
    {
        return DB::transaction(function () use ($order, $actor, $carrierName, $trackingNumber): Shipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== 'processing') {
                throw new InvalidArgumentException('Only processing orders can be dispatched.');
            }

            $this->easyParcelBookings->assertManualDispatchAllowed($lockedOrder);

            $shipment = Shipment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('source', 'manual')
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

            if ($lockedShipment->source !== 'manual') {
                throw new InvalidArgumentException('EasyParcel lifecycle is provider-managed and cannot accept manual events.');
            }

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

    /**
     * @param  list<array{
     *     external_event_id: string,
     *     status: string,
     *     occurred_at: CarbonInterface,
     *     description: string,
     *     location: ?string
     * }>  $events
     */
    public function recordEasyParcelEvents(
        Shipment $shipment,
        string $expectedTrackingNumber,
        array $events,
    ): int {
        return DB::transaction(function () use ($shipment, $expectedTrackingNumber, $events): int {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if ($lockedShipment->order_id !== $lockedOrder->id
                || $lockedShipment->source !== 'easyparcel'
                || ! hash_equals($expectedTrackingNumber, (string) $lockedShipment->tracking_number)) {
                throw new InvalidArgumentException('Only EasyParcel shipments accept provider tracking events.');
            }

            return $this->recordEasyParcelEventsOnLockedShipment($lockedOrder, $lockedShipment, $events);
        });
    }

    /**
     * @param  list<array{
     *     external_event_id: string,
     *     status: string,
     *     occurred_at: CarbonInterface,
     *     description: string,
     *     location: ?string
     * }>  $events
     */
    public function reconcileEasyParcelDetails(
        Shipment $shipment,
        string $expectedProviderReference,
        ?string $expectedTrackingNumber,
        ?string $providerOrderReference,
        ?string $trackingNumber,
        ?string $labelPath,
        ?string $trackingUrl,
        string $providerStatus,
        array $events,
    ): Shipment {
        return DB::transaction(function () use (
            $shipment,
            $expectedProviderReference,
            $expectedTrackingNumber,
            $providerOrderReference,
            $trackingNumber,
            $labelPath,
            $trackingUrl,
            $providerStatus,
            $events,
        ): Shipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if ($lockedShipment->order_id !== $lockedOrder->id
                || $lockedShipment->source !== 'easyparcel'
                || $lockedShipment->provider_reference !== $expectedProviderReference
                || $lockedShipment->tracking_number !== $expectedTrackingNumber) {
                throw new InvalidArgumentException('Only the current EasyParcel shipment accepts provider details.');
            }

            $updates = array_filter([
                'provider_order_reference' => $providerOrderReference,
                'tracking_number' => $trackingNumber,
                'label_path' => $labelPath,
                'tracking_url' => $trackingUrl,
            ], static fn (?string $value): bool => $value !== null);
            if ($updates !== []) {
                $lockedShipment->forceFill($updates)->save();
            }

            if (blank($lockedShipment->tracking_number)
                && in_array($lockedShipment->status, ['pending', 'on_hold'], true)
                && in_array($providerStatus, ['pending', 'on_hold'], true)
                && $lockedShipment->status !== $providerStatus) {
                $lockedShipment->forceFill(['status' => $providerStatus])->save();
            }

            $this->recordEasyParcelEventsOnLockedShipment($lockedOrder, $lockedShipment, $events);

            return $lockedShipment->fresh(['events']);
        });
    }

    /**
     * @param  list<array{
     *     external_event_id: string,
     *     status: string,
     *     occurred_at: CarbonInterface,
     *     description: string,
     *     location: ?string
     * }>  $events
     */
    private function recordEasyParcelEventsOnLockedShipment(
        Order $lockedOrder,
        Shipment $lockedShipment,
        array $events,
    ): int {
        $created = 0;
        foreach ($events as $event) {
            $this->assertValidEasyParcelEvent($event);

            $stored = ShipmentEvent::query()->firstOrCreate([
                'shipment_id' => $lockedShipment->id,
                'external_event_id' => $event['external_event_id'],
            ], [
                'status' => $event['status'],
                'occurred_at' => $event['occurred_at'],
                'location' => $event['location'],
                'description' => $event['description'],
                'source' => 'easyparcel',
                'created_by' => null,
            ]);

            if (! $stored->wasRecentlyCreated) {
                continue;
            }

            $created++;
        }

        if ($created > 0) {
            $projection = $this->projectEasyParcelShipmentLifecycle($lockedShipment);
            $this->projectEasyParcelLifecycleToOrder($lockedOrder, $lockedShipment, $projection);
        }

        return $created;
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

    /**
     * @param  array{
     *     external_event_id: string,
     *     status: string,
     *     occurred_at: CarbonInterface,
     *     description: string,
     *     location: ?string
     * }  $event
     */
    private function assertValidEasyParcelEvent(array $event): void
    {
        if (! str_starts_with($event['external_event_id'], 'easyparcel:')
            || mb_strlen($event['external_event_id']) > 160
            || ! in_array($event['status'], self::EASYPARCEL_EVENT_STATUSES, true)
            || trim($event['description']) === ''
            || mb_strlen($event['description']) > 2000
            || ($event['location'] !== null && mb_strlen($event['location']) > 255)) {
            throw new InvalidArgumentException('The EasyParcel tracking event is invalid.');
        }
    }

    /**
     * @param  array{
     *     status: string,
     *     dispatch_event: ?ShipmentEvent,
     *     delivery_event: ?ShipmentEvent
     * }  $projection
     */
    private function projectEasyParcelLifecycleToOrder(
        Order $order,
        Shipment $shipment,
        array $projection,
    ): void {
        $trackingSnapshot = [];
        if (filled($shipment->carrier_name)
            && (blank($order->tracking_carrier) || $order->tracking_carrier === $shipment->carrier_name)) {
            $trackingSnapshot['tracking_carrier'] = $shipment->carrier_name;
        }
        if (filled($shipment->tracking_number)
            && (blank($order->tracking_number) || $order->tracking_number === $shipment->tracking_number)) {
            $trackingSnapshot['tracking_number'] = $shipment->tracking_number;
        }
        if ($trackingSnapshot !== []) {
            $order->forceFill($trackingSnapshot)->save();
        }

        if ($projection['dispatch_event'] !== null) {
            $this->advanceEasyParcelOrderToShipped(
                $order,
                $shipment,
                $projection['dispatch_event']->occurred_at,
            );
        }

        if ($projection['status'] !== 'delivered' || $projection['delivery_event'] === null) {
            return;
        }

        if ($order->status === 'processing') {
            $this->advanceEasyParcelOrderToShipped(
                $order,
                $shipment,
                $projection['delivery_event']->occurred_at,
            );
        }

        if ($order->status !== 'shipped') {
            return;
        }

        $from = $order->status;
        $order->forceFill(['status' => 'delivered'])->save();
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => 'delivered',
            'user_id' => null,
            'note' => 'EasyParcel tracking confirmed delivery.',
        ]);
    }

    private function advanceEasyParcelOrderToShipped(
        Order $order,
        Shipment $shipment,
        CarbonInterface $occurredAt,
    ): void {
        if ($order->status !== 'processing') {
            return;
        }

        $from = $order->status;
        $order->forceFill([
            'status' => 'shipped',
            'tracking_carrier' => $shipment->carrier_name,
            'tracking_number' => $shipment->tracking_number,
            'shipped_at' => $order->shipped_at ?? $occurredAt,
        ])->save();
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => 'shipped',
            'user_id' => null,
            'note' => 'EasyParcel tracking confirmed carrier handoff.',
        ]);
    }

    /**
     * @return array{
     *     status: string,
     *     dispatch_event: ?ShipmentEvent,
     *     delivery_event: ?ShipmentEvent
     * }
     */
    private function projectEasyParcelShipmentLifecycle(Shipment $shipment): array
    {
        $providerEvents = ShipmentEvent::query()
            ->where('shipment_id', $shipment->id)
            ->where('source', 'easyparcel');
        $projectableEvents = (clone $providerEvents)
            ->whereIn('status', array_keys(self::SHIPMENT_STATUS_RANK))
            ->get();
        $projectedStatus = $shipment->status;
        $firstDelivery = (clone $providerEvents)
            ->where('status', 'delivered')
            ->oldest('occurred_at')
            ->oldest('id')
            ->first();
        $qualifyingDelivery = null;

        if ($projectedStatus === 'delivered') {
            $qualifyingDelivery = $firstDelivery;
        } elseif ($projectedStatus === 'returned') {
            $latestReturn = (clone $providerEvents)
                ->where('status', 'returned')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first();
            $qualifyingDelivery = (clone $providerEvents)
                ->where('status', 'delivered')
                ->where('occurred_at', '>', $latestReturn->occurred_at)
                ->oldest('occurred_at')
                ->oldest('id')
                ->first();
            $projectedStatus = $qualifyingDelivery === null ? 'returned' : 'delivered';
        } else {
            $firstTerminal = (clone $providerEvents)
                ->whereIn('status', ['delivered', 'returned'])
                ->oldest('id')
                ->first();
            if ($firstTerminal?->status === 'delivered') {
                $projectedStatus = 'delivered';
                $qualifyingDelivery = $firstTerminal;
            } elseif ($firstTerminal?->status === 'returned') {
                $qualifyingDelivery = (clone $providerEvents)
                    ->where('status', 'delivered')
                    ->where('occurred_at', '>', $firstTerminal->occurred_at)
                    ->oldest('occurred_at')
                    ->oldest('id')
                    ->first();
                $projectedStatus = $qualifyingDelivery === null ? 'returned' : 'delivered';
            } else {
                $currentRank = self::SHIPMENT_STATUS_RANK[$projectedStatus] ?? 0;
                foreach ($projectableEvents as $event) {
                    if (self::SHIPMENT_STATUS_RANK[$event->status] > $currentRank) {
                        $projectedStatus = $event->status;
                        $currentRank = self::SHIPMENT_STATUS_RANK[$event->status];
                    }
                }
            }
        }

        $firstDispatch = (clone $providerEvents)
            ->whereIn('status', ['shipped', 'in_transit'])
            ->oldest('occurred_at')
            ->oldest('id')
            ->first();
        $dispatchEvent = $firstDispatch ?? ($projectedStatus === 'delivered' ? $qualifyingDelivery : null);
        $deliveredAt = $shipment->delivered_at;
        if ($projectedStatus === 'delivered' && $qualifyingDelivery !== null) {
            $deliveredAt = $shipment->status === 'delivered'
                ? ($shipment->delivered_at ?? $qualifyingDelivery->occurred_at)
                : $qualifyingDelivery->occurred_at;
        }

        $shipment->forceFill([
            'status' => $projectedStatus,
            'dispatched_at' => $shipment->dispatched_at ?? $dispatchEvent?->occurred_at,
            'delivered_at' => $deliveredAt,
        ])->save();

        return [
            'status' => $projectedStatus,
            'dispatch_event' => $dispatchEvent,
            'delivery_event' => $qualifyingDelivery,
        ];
    }
}
