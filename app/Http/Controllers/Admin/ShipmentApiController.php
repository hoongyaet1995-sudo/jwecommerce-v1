<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Shipping\ShippingPermission;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\ShipmentService;
use App\Services\Admin\ShipmentDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ShipmentApiController extends Controller
{
    public function index(Request $request, ShipmentDataService $data): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::VIEW), 403);

        return response()->json(['data' => $data->collection()->values()]);
    }

    public function dispatch(Request $request, Order $order, ShipmentService $shipments): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        $validated = $request->validate([
            'carrierName' => ['required', 'string', 'max:120'],
            'trackingNumber' => ['required', 'string', 'max:160'],
        ]);

        try {
            $shipment = $shipments->dispatchManually($order, $request->user(), $validated['carrierName'], $validated['trackingNumber']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['trackingNumber' => $exception->getMessage()]);
        }

        return response()->json(['data' => ['id' => (string) $shipment->id]], 201);
    }

    public function appendEvent(Request $request, Shipment $shipment, ShipmentService $shipments): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::UPDATE_STATUS), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:in_transit,out_for_delivery,delivered,failed_delivery,returned'],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $event = $shipments->appendManualEvent($shipment, $request->user(), $validated['status'], $validated['description'], $validated['location'] ?? null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return response()->json(['data' => ['id' => (string) $event->id, 'status' => $event->status]], 201);
    }
}
