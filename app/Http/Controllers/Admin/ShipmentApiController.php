<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Shipping\ShippingPermission;
use App\Exceptions\ActiveShipmentCorrectionExists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartShipmentCorrectionRequest;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Services\Admin\ShipmentDataService;
use App\Services\ShipmentService;
use App\Services\Shipping\EasyParcelFailedBookingRecoveryService;
use App\Services\Shipping\EasyParcelFulfillmentService;
use App\Services\Shipping\EasyParcelLabelService;
use App\Services\Shipping\EasyParcelReconciliationPublisher;
use App\Services\Shipping\EasyParcelReconciliationTaskService;
use App\Services\Shipping\EasyParcelShipmentDetailsService;
use App\Services\Shipping\EasyParcelStatusNormalizer;
use App\Services\Shipping\EasyParcelTrackingException;
use App\Services\Shipping\FulfilmentCorrectionService;
use App\Services\Shipping\PackageSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ShipmentApiController extends Controller
{
    public function startCorrection(
        StartShipmentCorrectionRequest $request,
        Shipment $shipment,
        FulfilmentCorrectionService $corrections,
    ): JsonResponse {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        try {
            $correction = $corrections->start($shipment, $request->user(), [
                'reason_code' => $request->string('reasonCode')->toString(),
                'internal_note' => $request->string('internalNote')->toString(),
                'external_action_state' => $request->string('externalActionState')->toString(),
                'external_action_note' => $request->filled('externalActionNote')
                    ? $request->string('externalActionNote')->toString()
                    : null,
            ]);
        } catch (ActiveShipmentCorrectionExists $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'active_correction_exists',
            ], 409);
        }

        return response()->json(['data' => [
            'id' => (string) $correction->id,
            'orderId' => (string) $correction->order_id,
            'sourceShipmentId' => (string) $correction->source_shipment_id,
            'status' => $correction->status,
            'externalActionState' => $correction->external_action_state,
        ]], 201);
    }

    public function index(Request $request, ShipmentDataService $data): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::VIEW), 403);

        return response()->json(['data' => $data->collection()->values()]);
    }

    public function showEasyParcelLabel(
        Request $request,
        Shipment $shipment,
        EasyParcelLabelService $labels,
    ): Response {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        return $labels->response($shipment, $request->user(), false);
    }

    public function downloadEasyParcelLabel(
        Request $request,
        Shipment $shipment,
        EasyParcelLabelService $labels,
    ): Response {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        return $labels->response($shipment, $request->user(), true);
    }

    public function dispatch(Request $request, Order $order, ShipmentService $shipments): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        $validated = $request->validate([
            'fulfillmentMethod' => ['sometimes', 'in:external_courier,manual_delivery'],
            'courierCode' => ['nullable', 'string', 'max:80'],
            'courierName' => ['nullable', 'string', 'max:120'],
            'carrierName' => ['nullable', 'string', 'max:120'],
            'trackingNumber' => ['nullable', 'string', 'max:160'],
            'trackingUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'driverName' => ['nullable', 'string', 'max:120'],
            'handoffReference' => ['nullable', 'string', 'max:160'],
            'auditNote' => ['nullable', 'required_with:fulfillmentMethod', 'string', 'min:3', 'max:2000'],
        ]);
        $method = $validated['fulfillmentMethod'] ?? null;
        $requiredFields = $method === 'manual_delivery'
            ? ['driverName']
            : ($method === 'external_courier' ? ['courierCode', 'courierName', 'trackingNumber'] : ['carrierName', 'trackingNumber']);
        $missing = [];
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $validated) || trim((string) $validated[$field]) === '') {
                $missing[$field] = 'This field is required for the selected fulfilment method.';
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        try {
            if (isset($validated['fulfillmentMethod'])) {
                $shipment = $shipments->dispatchTeamManaged($order, $request->user(), [
                    'fulfillment_method' => $validated['fulfillmentMethod'],
                    'carrier_code' => $validated['courierCode'] ?? null,
                    'carrier_name' => $validated['courierName'] ?? null,
                    'tracking_number' => $validated['trackingNumber'] ?? null,
                    'tracking_url' => $validated['trackingUrl'] ?? null,
                    'driver_name' => $validated['driverName'] ?? null,
                    'handoff_reference' => $validated['handoffReference'] ?? null,
                    'audit_note' => $validated['auditNote'],
                ]);
            } else {
                $shipment = $shipments->dispatchManually($order, $request->user(), $validated['carrierName'], $validated['trackingNumber']);
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['trackingNumber' => $exception->getMessage()]);
        }

        return response()->json(['data' => [
            'id' => (string) $shipment->id,
            'fulfillmentMethod' => $shipment->fulfillment_method ?? 'external_courier',
            'managementMode' => 'team_managed',
            'externalCourierName' => $shipment->fulfillment_method === 'manual_delivery' ? null : $shipment->carrier_name,
            'driverName' => $shipment->driver_name,
            'handoffReference' => $shipment->handoff_reference,
            'trackingNumber' => $shipment->tracking_number,
        ]], 201);
    }

    public function quoteEasyParcel(
        Request $request,
        Order $order,
        EasyParcelFulfillmentService $fulfillment,
    ): JsonResponse {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        $validated = $request->validate([
            'package' => ['required', 'array'],
            'package.weightKg' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'package.lengthCm' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'package.widthCm' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'package.heightCm' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
        ]);

        try {
            $package = new PackageSpec(
                (float) $validated['package']['weightKg'],
                (float) $validated['package']['lengthCm'],
                (float) $validated['package']['widthCm'],
                (float) $validated['package']['heightCm'],
            );
            $quote = $fulfillment->quote($order, $package, $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['fulfillment' => $exception->getMessage()]);
        } catch (Throwable) {
            return response()->json(['message' => 'EasyParcel fulfillment is temporarily unavailable.'], 503);
        }

        return response()->json(['data' => $this->quoteData($quote)], 201);
    }

    public function bookEasyParcel(
        Request $request,
        Order $order,
        EasyParcelFulfillmentService $fulfillment,
    ): JsonResponse {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        $validated = $request->validate([
            'quoteId' => ['required', 'integer', 'min:1'],
            'serviceCode' => ['required', 'string', 'max:10'],
        ]);
        $quote = ShipmentQuote::query()->find($validated['quoteId']);

        if ($quote === null) {
            throw ValidationException::withMessages(['quoteId' => 'The selected quote is unavailable.']);
        }

        try {
            $shipment = $fulfillment->book($order, $quote, $validated['serviceCode'], $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['fulfillment' => $exception->getMessage()]);
        } catch (Throwable) {
            return response()->json(['message' => 'EasyParcel fulfillment is temporarily unavailable.'], 503);
        }

        return response()->json(['data' => $this->bookingData($shipment)]);
    }

    public function reconcileEasyParcel(
        Request $request,
        Order $order,
        EasyParcelReconciliationTaskService $tasks,
        EasyParcelReconciliationPublisher $publisher,
    ): JsonResponse {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        try {
            $task = $tasks->retryForOrder($order);
            $publisher->publish($task);
            $task->refresh();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reconciliation' => $exception->getMessage()]);
        }

        return response()->json(['data' => [
            'taskId' => (string) $task->id,
            'state' => $task->state,
        ]], 202);
    }

    public function refreshEasyParcelStatus(
        Request $request,
        Shipment $shipment,
        EasyParcelShipmentDetailsService $details,
        EasyParcelFailedBookingRecoveryService $recoveries,
        EasyParcelStatusNormalizer $statuses,
        ShipmentDataService $shipmentData,
    ): JsonResponse {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        if ($shipment->source !== 'easyparcel' || blank($shipment->provider_reference)) {
            throw ValidationException::withMessages([
                'shipment' => 'Only provider-backed EasyParcel shipments can retrieve a latest status.',
            ]);
        }

        $beforeStatus = $shipment->status;
        $beforeEventCount = $shipment->events()->count();

        try {
            $result = $details->reconcileWithSnapshot($shipment);
        } catch (EasyParcelTrackingException) {
            return response()->json([
                'message' => 'EasyParcel status is temporarily unavailable. Please try again.',
            ], 503);
        }

        $shipment = $result['shipment'];
        $providerDetails = $result['details'];
        $awbAvailable = $providerDetails['awb_number'] !== null;
        $providerStatus = $statuses->normalize($providerDetails['shipment_status_code']);
        $recovery = null;
        if ($providerStatus === 'cancelled') {
            $recovery = $recoveries->recordProviderCancellation(
                $shipment,
                null,
                $providerDetails['shipment_status_code'],
                $providerDetails['shipment_status'],
                $result['checked_at'],
            );
        }
        $shipment->refresh();

        return response()->json(['data' => [
            'shipmentId' => (string) $shipment->id,
            'status' => $shipment->status,
            'changed' => $shipment->status !== $beforeStatus
                || $shipment->events()->count() !== $beforeEventCount,
            'providerStatusCode' => $providerDetails['shipment_status_code'],
            'providerStatus' => $providerDetails['shipment_status'],
            'checkedAt' => $result['checked_at']->toIso8601String(),
            'awbAvailable' => $awbAvailable,
            'recovery' => $shipmentData->recoveryData($recovery),
            'recoveryDisposition' => $this->recoveryDisposition(
                $shipment,
                $providerStatus,
                $awbAvailable,
            ),
        ]]);
    }

    public function confirmEasyParcelProviderFailure(
        Request $request,
        Shipment $shipment,
        EasyParcelFailedBookingRecoveryService $recoveries,
    ): JsonResponse {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $recovery = $recoveries->confirm($shipment, $request->user(), $validated['reason']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        $shipment->refresh();

        return response()->json(['data' => [
            'recoveryId' => (string) $recovery->id,
            'shipmentId' => (string) $shipment->id,
            'replacementOrderId' => (string) $recovery->order_id,
            'shipmentStatus' => $shipment->status,
            'recoveryState' => $recovery->state,
            'cancellationState' => $recovery->provider_cancel_state,
            'cancellationMessage' => $recovery->provider_cancel_message,
            'cancellationConfirmed' => $recovery->provider_cancel_state === 'success',
        ]]);
    }

    public function appendEvent(Request $request, Shipment $shipment, ShipmentService $shipments): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::UPDATE_STATUS), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:in_transit,out_for_delivery,delivered,failed_delivery,returned,cancelled'],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'voidConfirmed' => ['accepted_if:status,cancelled'],
        ]);

        try {
            $event = $shipments->appendManualEvent($shipment, $request->user(), $validated['status'], $validated['description'], $validated['location'] ?? null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return response()->json(['data' => ['id' => (string) $event->id, 'status' => $event->status]], 201);
    }

    /** @return array<string, mixed> */
    private function quoteData(ShipmentQuote $quote): array
    {
        $services = data_get($quote->response_snapshot, 'services', []);

        return [
            'id' => (string) $quote->id,
            'orderId' => (string) $quote->order_id,
            'provider' => $quote->provider,
            'environment' => $quote->environment,
            'expiresAt' => $quote->expires_at?->toIso8601String(),
            'package' => [
                'weightKg' => data_get($quote->package_snapshot, 'weight_kg'),
                'lengthCm' => data_get($quote->package_snapshot, 'length_cm'),
                'widthCm' => data_get($quote->package_snapshot, 'width_cm'),
                'heightCm' => data_get($quote->package_snapshot, 'height_cm'),
            ],
            'services' => collect(is_array($services) ? $services : [])->map(fn (array $service): array => [
                'serviceCode' => $service['service_code'],
                'serviceName' => $service['service_name'],
                'courierCode' => $service['courier_code'],
                'courierName' => $service['courier_name'],
                'deliveryDuration' => $service['delivery_duration'],
                'isPickup' => $service['is_pickup'],
                'isDropoff' => $service['is_dropoff'],
                'supportsCod' => $service['supports_cod'],
                'currency' => $service['currency'],
                'amount' => $service['amount_minor'] / 100,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function bookingData(Shipment $shipment): array
    {
        return [
            'id' => (string) $shipment->id,
            'orderId' => (string) $shipment->order_id,
            'quoteId' => (string) $shipment->shipment_quote_id,
            'provider' => $shipment->source,
            'fulfillmentStatus' => filled($shipment->tracking_number) ? 'ready_to_ship' : 'pending',
            'courierCode' => $shipment->carrier_code,
            'courierName' => $shipment->carrier_name,
            'serviceCode' => $shipment->service_code,
            'serviceName' => $shipment->service_name,
            'trackingNumber' => $shipment->tracking_number,
            'hasLabel' => filled($shipment->label_path),
            'currency' => $shipment->currency,
            'amount' => $shipment->shipping_cost === null ? null : $shipment->shipping_cost / 100,
        ];
    }

    private function recoveryDisposition(Shipment $shipment, string $providerStatus, bool $awbAvailable): string
    {
        if ($providerStatus === 'cancelled') {
            return 'provider_terminal';
        }

        if (in_array($providerStatus, ['on_hold', 'returned', 'failed_delivery', 'provider_update'], true)) {
            return 'needs_review';
        }

        if ($awbAvailable) {
            return 'none';
        }

        if ($providerStatus === 'booking_failed') {
            return 'provider_terminal';
        }

        $thresholdMinutes = max(0, (int) config('services.easyparcel.awb_review_after_minutes', 10));

        return $shipment->created_at?->lt(now()->subMinutes($thresholdMinutes))
            ? 'needs_review'
            : 'none';
    }
}
