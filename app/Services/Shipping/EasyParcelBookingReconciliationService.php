<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Services\Notifications\OrderNotificationMilestoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class EasyParcelBookingReconciliationService
{
    public const LIST_URL = 'https://api.easyparcel.com/open_api/2026-06/shipment/list';

    private const LIST_LIMIT = 250;

    public function __construct(
        private readonly EasyParcelBookingGuard $bookingGuard,
        private readonly EasyParcelProviderSessionService $providerSessions,
        private readonly EasyParcelShipmentDetailsService $details,
        private readonly EasyParcelReconciliationTaskService $tasks,
        private readonly EasyParcelReconciliationPublisher $publisher,
        private readonly EasyParcelFailedBookingRecoveryService $failedBookingRecoveries,
        private readonly EasyParcelStatusNormalizer $statuses,
        private readonly OrderNotificationMilestoneService $notifications,
    ) {}

    public function reconcile(ShipmentQuote $quote, ?string $hintedShipmentNumber = null): ?Shipment
    {
        $snapshot = ShipmentQuote::query()->find($quote->id);
        if (! $snapshot instanceof ShipmentQuote
            || $snapshot->provider !== ShippingProviderConfigurationService::PROVIDER
            || $snapshot->environment !== ShippingProviderConfigurationService::operationalEnvironment()
            || blank($snapshot->reconciliation_reference)
            || blank($snapshot->booking_claim_key)) {
            throw new EasyParcelTrackingException;
        }

        $session = $this->providerSessions->connected();
        if ($session === null) {
            throw new EasyParcelTrackingException;
        }

        $shipmentNumbers = $hintedShipmentNumber === null
            ? $this->shipmentNumbers($snapshot, $session)
            : [$this->boundedString($hintedShipmentNumber, 160)];

        foreach ($shipmentNumbers as $shipmentNumber) {
            $details = $this->details->lookup($shipmentNumber, $session);
            if (! is_string($details['reference'])
                || ! hash_equals($snapshot->reconciliation_reference, $details['reference'])) {
                continue;
            }

            return $this->materialize($snapshot, $details, $session);
        }

        throw new EasyParcelReconciliationPendingException('booking_not_visible');
    }

    public function reconcileShipmentNumber(string $shipmentNumber): Shipment
    {
        $session = $this->providerSessions->connected();
        if ($session === null) {
            throw new EasyParcelTrackingException;
        }

        $details = $this->details->lookup($this->boundedString($shipmentNumber, 160), $session);
        $reference = $details['reference'];
        if (! is_string($reference)) {
            throw new EasyParcelReconciliationPendingException('application_reference_missing');
        }

        $quote = ShipmentQuote::query()
            ->where('provider', ShippingProviderConfigurationService::PROVIDER)
            ->where('environment', ShippingProviderConfigurationService::operationalEnvironment())
            ->where('reconciliation_reference', $reference)
            ->whereNotNull('booking_claim_key')
            ->first();
        if (! $quote instanceof ShipmentQuote) {
            throw new EasyParcelReconciliationPendingException('application_reference_unmatched');
        }

        return $this->materialize($quote, $details, $session);
    }

    /** @return list<string> */
    private function shipmentNumbers(ShipmentQuote $quote, EasyParcelProviderSession $session): array
    {
        $startedAt = $quote->booking_started_at ?? $quote->created_at ?? now();

        try {
            $response = Http::acceptJson()
                ->withToken($session->accessToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::LIST_URL, [
                    'date_from' => $startedAt->copy()->subDay()->toDateString(),
                    'date_to' => now()->addDay()->toDateString(),
                    'limit' => self::LIST_LIMIT,
                ]);
            $payload = $response->json();
        } catch (Throwable) {
            throw new EasyParcelTrackingException;
        }

        $rows = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (is_array($rows) && ! array_is_list($rows)) {
            $rows = $rows['shipments'] ?? null;
        }
        if (! $response->successful()
            || ! is_array($payload)
            || (int) ($payload['status_code'] ?? 0) !== 200
            || ! is_array($rows)
            || ! array_is_list($rows)
            || count($rows) > self::LIST_LIMIT) {
            throw new EasyParcelTrackingException;
        }

        $shipmentNumbers = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new EasyParcelTrackingException;
            }
            $shipmentNumber = $this->boundedString($row['shipment_number'] ?? null, 160);
            $shipmentNumbers[$shipmentNumber] = $shipmentNumber;
        }

        return array_values($shipmentNumbers);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function materialize(
        ShipmentQuote $quote,
        array $details,
        EasyParcelProviderSession $session,
    ): Shipment {
        [$shipment, $detailsTaskId] = DB::transaction(function () use ($details, $quote, $session): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($quote->order_id);
            $lockedQuote = ShipmentQuote::query()->lockForUpdate()->findOrFail($quote->id);
            $existing = Shipment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('source', ShippingProviderConfigurationService::PROVIDER)
                ->whereNotIn('status', ['booking_failed', 'cancelled'])
                ->lockForUpdate()
                ->first();
            if ($existing instanceof Shipment) {
                if ($existing->shipment_quote_id !== $lockedQuote->id
                    || $existing->provider_reference !== $details['shipment_number']) {
                    throw new EasyParcelTrackingException;
                }

                return [$existing, null];
            }

            $this->bookingGuard->assertFinalizationAllowed($lockedOrder, $lockedQuote);
            if (! in_array($lockedQuote->booking_state, ['in_progress', 'uncertain'], true)
                || $lockedQuote->booking_claim_key !== ShippingProviderConfigurationService::PROVIDER.':order:'.$lockedOrder->id
                || ! is_string($details['reference'])
                || ! hash_equals($lockedQuote->reconciliation_reference, $details['reference'])) {
                throw new EasyParcelTrackingException;
            }

            $service = $this->selectedService($lockedQuote, $details['service_code']);
            if (is_string($details['service_code'])
                && ! hash_equals($service['service_code'], $details['service_code'])) {
                throw new EasyParcelTrackingException;
            }

            $this->providerSessions->assertCurrent($session);
            $shipment = Shipment::query()->create([
                'order_id' => $lockedOrder->id,
                'shipment_quote_id' => $lockedQuote->id,
                'sequence' => ((int) Shipment::query()
                    ->where('order_id', $lockedOrder->id)
                    ->max('sequence')) + 1,
                'source' => ShippingProviderConfigurationService::PROVIDER,
                'carrier_code' => $details['courier_code'] ?? $service['courier_code'],
                'carrier_name' => $details['courier_name'] ?? $service['courier_name'],
                'service_code' => $service['service_code'],
                'service_name' => $details['service_name'] ?? $service['service_name'],
                'tracking_number' => $details['awb_number'],
                'status' => 'pending',
                'shipping_cost' => $details['amount_minor'] ?? $service['amount_minor'],
                'currency' => $details['currency'] ?? $service['currency'],
                'label_path' => $details['awb_url'],
                'provider_reference' => $details['shipment_number'],
                'provider_order_reference' => $details['order_number'],
                'tracking_url' => $details['tracking_url'],
                'quote_snapshot' => [
                    'quoted_at' => $lockedQuote->created_at?->toIso8601String(),
                    'expires_at' => $lockedQuote->expires_at?->toIso8601String(),
                    'request_id' => data_get($lockedQuote->response_snapshot, 'request_id'),
                    'service' => $service,
                ],
                'package_snapshot' => $lockedQuote->package_snapshot,
                'created_by' => $lockedQuote->created_by,
            ]);
            $lockedQuote->forceFill([
                'selected_service_code' => $service['service_code'],
                'booking_state' => 'booked',
                'booked_at' => now(),
                'last_error' => null,
            ])->save();

            $this->failedBookingRecoveries->completeForReplacement($shipment);

            if (filled($shipment->tracking_number)
                && $this->statuses->allowsReadyToShipNotification($details['shipment_status_code'])) {
                $this->notifications->record(
                    $lockedOrder,
                    'ready_to_ship',
                    'shipment',
                    $shipment->id,
                );
            }

            $taskId = null;
            if (blank($shipment->tracking_number) || blank($shipment->label_path)) {
                $taskId = $this->tasks->ensureShipmentDetails($shipment)->id;
            }

            return [$shipment, $taskId];
        });

        if (is_int($detailsTaskId)) {
            $this->publisher->publish($detailsTaskId);
        }

        return $shipment->fresh(['events']);
    }

    /** @return array<string, mixed> */
    private function selectedService(ShipmentQuote $quote, mixed $providerServiceCode): array
    {
        $selectedCode = $quote->selected_service_code ?? $providerServiceCode;
        $services = data_get($quote->response_snapshot, 'services');
        if (! is_string($selectedCode) || ! is_array($services)) {
            throw new EasyParcelTrackingException;
        }
        foreach ($services as $service) {
            if (is_array($service) && ($service['service_code'] ?? null) === $selectedCode) {
                return $service;
            }
        }

        throw new EasyParcelTrackingException;
    }

    private function boundedString(mixed $value, int $max): string
    {
        if (! is_string($value)
            || $value === ''
            || trim($value) !== $value
            || mb_strlen($value) > $max) {
            throw new EasyParcelTrackingException;
        }

        return $value;
    }
}
