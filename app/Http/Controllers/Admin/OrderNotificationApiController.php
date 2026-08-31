<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\NotificationEventCatalog;
use App\Domain\Notifications\NotificationPermission;
use App\Domain\Notifications\RenderedNotification;
use App\Http\Controllers\Controller;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderNotificationApiController extends Controller
{
    public function __construct(private readonly NotificationDispatchService $dispatches) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::DELIVERIES_VIEW);
        $dispatches = $order->notificationDispatches()->latest('id')->limit(100)->get();

        return response()->json([
            'data' => $dispatches->map($this->dispatchData(...))->values(),
        ]);
    }

    public function preview(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::DELIVERIES_VIEW);
        $validated = $this->validateOrderAction($request);
        $rendered = $this->dispatches->preview($order, $validated['eventKey'], $this->extra($validated));

        return response()->json([
            'data' => $this->previewData($validated['eventKey'], $rendered),
        ]);
    }

    public function sendCurrent(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::CURRENT_SEND);
        $validated = $this->validateOrderAction($request);
        $dispatch = $this->dispatches->sendCurrent(
            $order,
            $validated['eventKey'],
            $request->user(),
            $this->extra($validated),
        );

        return response()->json(['data' => $this->dispatchData($dispatch)], 201);
    }

    public function retry(Request $request, NotificationDispatch $dispatch): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::DELIVERIES_RETRY);

        return response()->json([
            'data' => $this->dispatchData($this->dispatches->retry($dispatch, $request->user())),
        ]);
    }

    public function resend(Request $request, NotificationDispatch $dispatch): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::DELIVERIES_RESEND);

        return response()->json([
            'data' => $this->dispatchData($this->dispatches->resend($dispatch, $request->user())),
        ], 201);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(NotificationPermission::allows($request->user(), $permission), 403);
    }

    /** @return array<string, mixed> */
    private function validateOrderAction(Request $request): array
    {
        return $request->validate([
            'eventKey' => ['required', 'string', Rule::in(array_keys(NotificationEventCatalog::all()))],
            'staffNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'paymentProofReason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, string>
     */
    private function extra(array $validated): array
    {
        return array_filter([
            'staff_note' => $validated['staffNote'] ?? null,
            'payment_proof_reason' => $validated['paymentProofReason'] ?? null,
        ], static fn (?string $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    private function previewData(string $eventKey, RenderedNotification $rendered): array
    {
        return [
            'eventKey' => $eventKey,
            'subject' => $rendered->subject,
            'preheader' => $rendered->preheader,
            'heading' => $rendered->heading,
            'body' => $rendered->body,
            'ctaText' => $rendered->ctaText,
            'ctaUrl' => $rendered->ctaUrl,
            'variables' => $rendered->variables,
        ];
    }

    /** @return array<string, mixed> */
    private function dispatchData(NotificationDispatch $dispatch): array
    {
        return [
            'id' => $dispatch->public_id,
            'eventKey' => $dispatch->event_key,
            'channel' => $dispatch->channel,
            'state' => $dispatch->state,
            'source' => $dispatch->source,
            'subject' => $dispatch->rendered_subject,
            'templateVersion' => $dispatch->template_version,
            'attemptCount' => $dispatch->attempt_count,
            'queuedAt' => $dispatch->queued_at?->toISOString(),
            'sentAt' => $dispatch->sent_at?->toISOString(),
            'failedAt' => $dispatch->failed_at?->toISOString(),
            'errorCode' => $dispatch->last_error_code,
            'resendOfId' => $dispatch->resendOf?->public_id,
            'createdAt' => $dispatch->created_at?->toISOString(),
        ];
    }
}
