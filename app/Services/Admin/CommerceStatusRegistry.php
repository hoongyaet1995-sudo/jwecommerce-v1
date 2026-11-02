<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class CommerceStatusRegistry
{
    public const DEFAULTS = [
        'order' => [
            ['pending', 'Pending Payment', 'Awaiting payment.'], ['paid', 'Payment Confirmed', 'Payment confirmed; fulfillment not started.'],
            ['processing', 'Processing & Verified', 'Ready for fulfillment.'], ['on_hold', 'On Hold', 'Processing paused for review.'],
            ['completed', 'Completed', 'Delivery completed. Updated through the shipment workflow.'], ['cancelled', 'Cancelled', 'Order cancelled.'],
        ],
        'shipment' => [
            ['pending', 'Preparing', 'Preparing delivery arrangements.'], ['ready_to_ship', 'Packed & AWB Generated', 'Ready for courier handoff.'],
            ['shipped', 'Dispatched', 'Handed over for delivery.'], ['picked_up', 'Picked Up', 'Collected by the courier.'], ['in_transit', 'In Transit', 'Travelling to the recipient.'],
            ['out_for_delivery', 'Out for Delivery', 'On the final delivery route.'], ['delivered', 'Delivered', 'Delivery completed.'],
            ['failed_delivery', 'Delivery Failed', 'Delivery needs attention.'], ['returned', 'Returned', 'Returned by the courier.'], ['cancelled', 'Cancelled', 'Shipment cancelled; retained in history.'],
            ['booking_failed', 'Booking Failed', 'Booking failed; check recovery before rebooking.'], ['on_hold', 'On Hold', 'Delivery paused.'], ['provider_update', 'Awaiting Provider Update', 'Provider outcome requires review.'],
        ],
    ];

    private ?array $names = null;

    public function name(string $scope, string $code): string
    {
        $this->names ??= DB::table('commerce_status_definitions')->get()->mapWithKeys(fn ($row) => [$row->scope.':'.$row->code => $row->name])->all();

        return $this->names[$scope.':'.$code] ?? str_replace('_', ' ', ucfirst($code));
    }

    public static function orderCode(string $status): string
    {
        return match ($status) {
            'pending_payment' => 'pending','shipped','ready_to_ship' => 'processing','delivered' => 'completed',default => $status
        };
    }

    public function collection(string $scope): array
    {
        $counts = ($scope === 'order' ? Order::query() : Shipment::query())->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $mapped = [];
        foreach ($counts as $status => $count) {
            $key = $scope === 'order' ? self::orderCode($status) : $status;
            $mapped[$key] = ($mapped[$key] ?? 0) + (int) $count;
        }

        return DB::table('commerce_status_definitions')->where('scope', $scope)->orderBy('sort_order')->get()->map(fn ($row) => [
            'id' => $row->code, 'code' => $row->code, 'name' => $row->name, 'description' => $row->description, 'sortOrder' => $row->sort_order,
            'ordersCount' => $mapped[$row->code] ?? 0, 'color' => 'indigo', 'isDefault' => $scope === 'order' && $row->code === 'pending',
            'isDefaultPaid' => false, 'isDefaultCancelled' => $row->code === 'cancelled', 'notifyCustomer' => false, 'publicVisibility' => true,
            'inventoryAction' => 'none', 'emailTemplate' => '', 'systemManaged' => $scope === 'shipment' || $row->code === 'completed',
        ])->all();
    }
}
