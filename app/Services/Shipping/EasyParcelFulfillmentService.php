<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingProvider;
use App\Models\User;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class EasyParcelFulfillmentService
{
    public const BOOKING_URL = 'https://api.easyparcel.com/open_api/'.EasyParcelOpenApiClient::API_VERSION.'/shipment/submit_orders';

    public const QUOTATION_URL = 'https://api.easyparcel.com/open_api/'.EasyParcelOpenApiClient::API_VERSION.'/shipment/quotations';

    private const QUOTE_TTL_MINUTES = 15;

    public function __construct(
        private readonly EasyParcelBookingGuard $bookingGuard,
        private readonly CurrencySettings $currencies,
        private readonly EasyParcelProviderSessionService $providerSessions,
        private readonly EasyParcelReconciliationTaskService $reconciliationTasks,
        private readonly EasyParcelReconciliationPublisher $reconciliationPublisher,
    ) {}

    public function quote(Order $order, PackageSpec $package, User $actor): ShipmentQuote
    {
        try {
            $providerSession = $this->providerSessions->connected();
        } catch (Throwable) {
            throw new InvalidArgumentException('The EasyParcel Sandbox connection is unavailable.');
        }
        if ($providerSession === null) {
            throw new InvalidArgumentException('The EasyParcel Sandbox connection is unavailable.');
        }
        $context = $this->quoteContext($order, $package, $providerSession);

        try {
            $response = Http::acceptJson()
                ->withToken($providerSession->accessToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::QUOTATION_URL, $context['quote_payload']);
        } catch (Throwable) {
            throw new InvalidArgumentException('EasyParcel Sandbox could not provide a quotation.');
        }

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)) {
            throw new InvalidArgumentException('EasyParcel Sandbox could not provide a quotation.');
        }

        $responseSnapshot = $this->normalizeQuotation($payload);

        return DB::transaction(function () use ($actor, $context, $package, $providerSession, $responseSnapshot): ShipmentQuote {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($context['order_id']);
            $provider = ShippingProvider::query()->lockForUpdate()->findOrFail($context['provider_id']);

            if ($lockedOrder->status !== 'processing'
                || $lockedOrder->shipments()->where('source', ShippingProviderConfigurationService::PROVIDER)->exists()) {
                throw new InvalidArgumentException('Only unbooked processing orders can be quoted.');
            }

            if (! $provider->enabled
                || $provider->connection_state !== 'connected'
                || $provider->configuration_version !== $context['provider_configuration_version']) {
                throw new InvalidArgumentException('The EasyParcel Sandbox connection changed while quoting.');
            }

            $this->providerSessions->assertCurrent($providerSession);

            return ShipmentQuote::query()->create([
                'order_id' => $lockedOrder->id,
                'shipping_provider_id' => $provider->id,
                'provider' => ShippingProviderConfigurationService::PROVIDER,
                'environment' => ShippingProviderConfigurationService::ENVIRONMENT,
                'provider_configuration_version' => $provider->configuration_version,
                'package_snapshot' => $package->snapshot(),
                'request_snapshot' => $context['request_snapshot'],
                'response_snapshot' => $responseSnapshot,
                'reconciliation_reference' => 'JWEP-'.$lockedOrder->id.'-'.Str::uuid(),
                'booking_state' => 'available',
                'expires_at' => now()->addMinutes(self::QUOTE_TTL_MINUTES),
                'created_by' => $actor->id,
            ]);
        });
    }

    public function book(Order $order, ShipmentQuote $quote, string $serviceCode, User $actor): Shipment
    {
        if (DB::connection()->transactionLevel() !== 0) {
            throw new InvalidArgumentException('EasyParcel booking must start outside an existing database transaction.');
        }

        try {
            $providerSession = $this->providerSessions->connected();
        } catch (Throwable) {
            throw new InvalidArgumentException('The EasyParcel Sandbox connection is unavailable.');
        }
        if ($providerSession === null) {
            throw new InvalidArgumentException('The EasyParcel Sandbox connection is unavailable.');
        }

        $claim = $this->claimBooking($order, $quote, $serviceCode, $providerSession);
        if ($claim['existing'] instanceof Shipment) {
            return $claim['existing'];
        }

        /** @var ShipmentQuote $claimedQuote */
        $claimedQuote = $claim['quote'];
        try {
            $bookingPayload = $this->bookingPayload($claimedQuote, $claim['service']);
        } catch (InvalidArgumentException $exception) {
            $this->markBookingOutcome($claimedQuote->id, 'failed', 'request_snapshot_invalid');

            throw $exception;
        }

        try {
            $response = Http::acceptJson()
                ->withToken($providerSession->accessToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::BOOKING_URL, $bookingPayload);
        } catch (Throwable) {
            $this->markBookingOutcome($claimedQuote->id, 'uncertain', 'provider_outcome_uncertain');

            throw new InvalidArgumentException('EasyParcel booking outcome is uncertain and requires reconciliation.');
        }

        $payload = $response->json();
        if ($response->status() !== 200 || ! is_array($payload)) {
            $this->markBookingOutcome($claimedQuote->id, 'uncertain', 'provider_outcome_uncertain');

            throw new InvalidArgumentException('EasyParcel booking outcome is uncertain and requires reconciliation.');
        }

        if ($this->isExplicitBookingFailure($payload)) {
            $this->markBookingOutcome($claimedQuote->id, 'failed', 'provider_rejected');

            throw new InvalidArgumentException('EasyParcel rejected the shipment booking.');
        }

        try {
            $providerBooking = $this->normalizeBooking($payload);

            return $this->finalizeBooking($claimedQuote, $claim['service'], $providerBooking, $actor, $providerSession);
        } catch (InvalidArgumentException $exception) {
            $this->markBookingOutcome($claimedQuote->id, 'uncertain', 'provider_outcome_uncertain');

            throw $exception;
        } catch (Throwable) {
            $existing = Shipment::query()->where('shipment_quote_id', $claimedQuote->id)->first();
            if ($existing !== null) {
                return $existing->fresh(['events']);
            }

            $this->markBookingOutcome($claimedQuote->id, 'uncertain', 'provider_outcome_uncertain');

            throw new InvalidArgumentException('EasyParcel booking outcome is uncertain and requires reconciliation.');
        }
    }

    /**
     * @return array{
     *     order_id: int,
     *     provider_id: int,
     *     provider_configuration_version: int,
     *     quote_payload: array<string, mixed>,
     *     request_snapshot: array<string, mixed>
     * }
     */
    private function quoteContext(
        Order $order,
        PackageSpec $package,
        EasyParcelProviderSession $providerSession,
    ): array {
        return DB::transaction(function () use ($order, $package, $providerSession): array {
            $lockedOrder = Order::query()
                ->with(['customer', 'items', 'addresses', 'payments'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status !== 'processing'
                || $lockedOrder->shipments()->where('source', ShippingProviderConfigurationService::PROVIDER)->exists()) {
                throw new InvalidArgumentException('Only unbooked processing orders can be quoted.');
            }

            if ($lockedOrder->items->isEmpty()) {
                throw new InvalidArgumentException('The order has no immutable item snapshot to quote.');
            }

            $address = $lockedOrder->addresses->firstWhere('type', 'shipping');
            if (! $address instanceof OrderAddress) {
                throw new InvalidArgumentException('The order has no shipping address to quote.');
            }

            $provider = ShippingProvider::query()
                ->where('provider', ShippingProviderConfigurationService::PROVIDER)
                ->where('environment', ShippingProviderConfigurationService::ENVIRONMENT)
                ->lockForUpdate()
                ->first();
            if ($provider === null) {
                throw new InvalidArgumentException('The EasyParcel Sandbox connection is unavailable.');
            }
            if ($provider->id !== $providerSession->providerId) {
                throw new InvalidArgumentException('The EasyParcel Sandbox connection changed while quoting.');
            }
            $this->providerSessions->assertCurrent($providerSession);
            $sender = $this->sender();
            $receiver = $this->receiver($lockedOrder, $address);
            $packageSnapshot = $package->snapshot();
            $parcelValue = $this->money(max(0, $lockedOrder->subtotal - $lockedOrder->discount_amount));
            $cod = $this->codSnapshot($lockedOrder);

            $quoteShipment = [
                'sender' => [
                    'postcode' => $sender['postcode'],
                    'subdivision_code' => $sender['subdivision_code'],
                    'country' => $sender['country_code'],
                ],
                'receiver' => [
                    'postcode' => $receiver['postcode'],
                    'subdivision_code' => $receiver['subdivision_code'],
                    'country' => $receiver['country_code'],
                ],
                'parcel_value' => $parcelValue,
                'weight' => $packageSnapshot['weight_kg'],
                'width' => $packageSnapshot['width_cm'],
                'length' => $packageSnapshot['length_cm'],
                'height' => $packageSnapshot['height_cm'],
            ];

            return [
                'order_id' => $lockedOrder->id,
                'provider_id' => $provider->id,
                'provider_configuration_version' => $provider->configuration_version,
                'quote_payload' => ['shipment' => [$quoteShipment]],
                'request_snapshot' => [
                    'api_version' => EasyParcelOpenApiClient::API_VERSION,
                    'quote' => ['shipment' => [$quoteShipment]],
                    'booking' => [
                        'collection_date' => now()->addWeekday()->toDateString(),
                        'sender' => $sender,
                        'receiver' => $receiver,
                        'items' => $this->bookingItems($lockedOrder, $package),
                        'cod' => $cod,
                    ],
                ],
            ];
        });
    }

    /**
     * @return array{
     *     existing: Shipment|null,
     *     quote?: ShipmentQuote,
     *     service?: array<string, mixed>
     * }
     */
    private function claimBooking(
        Order $order,
        ShipmentQuote $quote,
        string $serviceCode,
        EasyParcelProviderSession $providerSession,
    ): array {
        return DB::transaction(function () use ($order, $quote, $serviceCode, $providerSession): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedQuote = ShipmentQuote::query()->lockForUpdate()->find($quote->id);

            if ($lockedQuote === null || $lockedQuote->order_id !== $lockedOrder->id) {
                throw new InvalidArgumentException('The selected quote does not belong to this order.');
            }

            $existing = Shipment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('source', ShippingProviderConfigurationService::PROVIDER)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if ($existing->shipment_quote_id === $lockedQuote->id
                    && $existing->service_code === $serviceCode) {
                    return ['existing' => $existing->fresh(['events'])];
                }

                throw new InvalidArgumentException('This order already has a different EasyParcel booking.');
            }

            if ($lockedOrder->status !== 'processing') {
                throw new InvalidArgumentException('Only processing orders can be booked.');
            }

            if ($lockedQuote->provider !== ShippingProviderConfigurationService::PROVIDER
                || $lockedQuote->environment !== ShippingProviderConfigurationService::ENVIRONMENT) {
                throw new InvalidArgumentException('The selected quote is not an EasyParcel Sandbox quote.');
            }

            if ($lockedQuote->expires_at === null || ! $lockedQuote->expires_at->isFuture()) {
                throw new InvalidArgumentException('The selected quote has expired.');
            }

            if ($lockedQuote->booking_state !== 'available' || $lockedQuote->booking_claim_key !== null) {
                throw new InvalidArgumentException('This quote has already been submitted and cannot be retried automatically.');
            }

            $service = $this->serviceFor($lockedQuote, $serviceCode);
            if ($service === null) {
                throw new InvalidArgumentException('The selected EasyParcel service is unavailable for this quote.');
            }
            $this->assertServiceSupportsRequest($lockedQuote, $service);

            $provider = ShippingProvider::query()->lockForUpdate()->find($lockedQuote->shipping_provider_id);
            if ($provider === null
                || $provider->provider !== ShippingProviderConfigurationService::PROVIDER
                || $provider->environment !== ShippingProviderConfigurationService::ENVIRONMENT
                || $provider->configuration_version !== $lockedQuote->provider_configuration_version
                || $provider->id !== $providerSession->providerId) {
                throw new InvalidArgumentException('The EasyParcel Sandbox connection changed after this quote was created.');
            }
            $this->providerSessions->assertCurrent($providerSession);
            $claimKey = ShippingProviderConfigurationService::PROVIDER.':order:'.$lockedOrder->id;

            $anotherClaim = ShipmentQuote::query()
                ->where('booking_claim_key', $claimKey)
                ->whereKeyNot($lockedQuote->id)
                ->exists();
            if ($anotherClaim) {
                throw new InvalidArgumentException('This order already has an EasyParcel booking attempt.');
            }

            $lockedQuote->forceFill([
                'booking_state' => 'in_progress',
                'booking_claim_key' => $claimKey,
                'booking_started_at' => now(),
                'selected_service_code' => $service['service_code'],
                'last_error' => null,
            ])->save();

            return [
                'existing' => null,
                'quote' => $lockedQuote->fresh(),
                'service' => $service,
            ];
        });
    }

    /** @param array<string, mixed> $service @return array<string, mixed> */
    private function bookingPayload(ShipmentQuote $quote, array $service): array
    {
        $snapshot = $quote->request_snapshot;
        $booking = is_array($snapshot['booking'] ?? null) ? $snapshot['booking'] : null;
        $package = $quote->package_snapshot;

        if (! is_array($booking)
            || ! is_array($booking['sender'] ?? null)
            || ! is_array($booking['receiver'] ?? null)
            || ! is_array($booking['items'] ?? null)
            || ! is_array($package)) {
            throw new InvalidArgumentException('The persisted shipment request is incomplete.');
        }

        $currency = (string) ($service['currency'] ?? '');
        $items = collect($booking['items'])->map(function (mixed $item) use ($currency): array {
            if (! is_array($item)) {
                throw new InvalidArgumentException('The persisted shipment request is incomplete.');
            }

            return [...$item, 'currency_code' => $currency];
        })->values()->all();

        $feature = [
            'sms_tracking' => false,
            'email_tracking' => false,
            'whatsapp_tracking' => false,
        ];
        $cod = $booking['cod'] ?? null;
        if ($cod !== null) {
            if (! is_array($cod)
                || ! is_numeric($cod['amount'] ?? null)
                || (float) $cod['amount'] <= 0
                || $this->currency($cod['currency'] ?? null) !== $currency
                || ! ($service['supports_cod'] ?? false)) {
                throw new InvalidArgumentException('The persisted shipment request is incomplete.');
            }

            $feature['cod'] = [
                'cod_amount' => (float) number_format((float) $cod['amount'], 2, '.', ''),
                'cod_currency' => $currency,
            ];
        }

        return ['shipment' => [[
            'reference' => $quote->reconciliation_reference,
            'service_id' => $service['service_code'],
            'collection_date' => $booking['collection_date'],
            'weight' => $package['weight_kg'],
            'height' => $package['height_cm'],
            'length' => $package['length_cm'],
            'width' => $package['width_cm'],
            'item' => $items,
            'sender' => $booking['sender'],
            'receiver' => $booking['receiver'],
            'feature' => $feature,
        ]]];
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  array<string, mixed>  $providerBooking
     */
    private function finalizeBooking(
        ShipmentQuote $quote,
        array $service,
        array $providerBooking,
        User $actor,
        EasyParcelProviderSession $providerSession,
    ): Shipment {
        [$shipment, $detailsTaskId] = DB::transaction(function () use ($actor, $providerBooking, $providerSession, $quote, $service): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($quote->order_id);
            $lockedQuote = ShipmentQuote::query()->lockForUpdate()->findOrFail($quote->id);
            $existing = Shipment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('source', ShippingProviderConfigurationService::PROVIDER)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->shipment_quote_id !== $lockedQuote->id
                    || $existing->service_code !== $service['service_code']) {
                    throw new InvalidArgumentException('This order already has a different EasyParcel booking.');
                }

                return [$existing->fresh(['events']), null];
            }

            $this->bookingGuard->assertFinalizationAllowed($lockedOrder, $lockedQuote);

            if ($lockedQuote->booking_state !== 'in_progress'
                || $lockedQuote->booking_claim_key !== ShippingProviderConfigurationService::PROVIDER.':order:'.$lockedOrder->id) {
                throw new InvalidArgumentException('This EasyParcel booking claim is no longer valid.');
            }

            $this->providerSessions->assertCurrent($providerSession);

            $shipment = Shipment::query()->create([
                'order_id' => $lockedOrder->id,
                'shipment_quote_id' => $lockedQuote->id,
                'sequence' => 1,
                'source' => ShippingProviderConfigurationService::PROVIDER,
                'carrier_code' => $service['courier_code'],
                'carrier_name' => $service['courier_name'],
                'service_code' => $service['service_code'],
                'service_name' => $service['service_name'],
                'tracking_number' => $providerBooking['awb_number'],
                'status' => 'pending',
                'shipping_cost' => $providerBooking['amount_minor'],
                'currency' => $providerBooking['currency'],
                'label_path' => $providerBooking['label_reference'],
                'provider_reference' => $providerBooking['shipment_number'],
                'provider_order_reference' => $providerBooking['order_number'],
                'tracking_url' => $providerBooking['tracking_url'],
                'quote_snapshot' => [
                    'quoted_at' => $lockedQuote->created_at?->toIso8601String(),
                    'expires_at' => $lockedQuote->expires_at?->toIso8601String(),
                    'request_id' => data_get($lockedQuote->response_snapshot, 'request_id'),
                    'service' => $service,
                ],
                'package_snapshot' => $lockedQuote->package_snapshot,
                'created_by' => $actor->id,
            ]);

            $lockedQuote->forceFill([
                'selected_service_code' => $service['service_code'],
                'booking_state' => 'booked',
                'booked_at' => now(),
                'last_error' => null,
            ])->save();

            $taskId = blank($shipment->tracking_number) || blank($shipment->label_path)
                ? $this->reconciliationTasks->ensureShipmentDetails($shipment)->id
                : null;

            return [$shipment->fresh(['events']), $taskId];
        });

        if (is_int($detailsTaskId)) {
            $this->reconciliationPublisher->publish($detailsTaskId);
        }

        return $shipment;
    }

    private function markBookingOutcome(int $quoteId, string $state, string $error): void
    {
        $taskId = DB::transaction(function () use ($error, $quoteId, $state): ?int {
            $quote = ShipmentQuote::query()->lockForUpdate()->find($quoteId);
            if ($quote !== null && $quote->booking_state === 'in_progress') {
                $changes = ['booking_state' => $state, 'last_error' => $error];
                if ($state === 'failed') {
                    $changes['booking_claim_key'] = null;
                }

                $quote->forceFill($changes)->save();

                return $state === 'uncertain'
                    ? $this->reconciliationTasks->ensureBooking($quote)->id
                    : null;
            }

            return null;
        });

        if (is_int($taskId)) {
            $this->reconciliationPublisher->publish($taskId);
        }
    }

    /** @param array<string, mixed> $payload @return array{request_id: string|null, services: list<array<string, mixed>>} */
    private function normalizeQuotation(array $payload): array
    {
        $data = data_get($payload, 'data.0');
        $quotations = is_array($data) ? ($data['quotations'] ?? null) : null;

        if ((int) ($payload['status_code'] ?? 0) !== 200
            || ! is_array($data)
            || ($data['status'] ?? null) !== 'success'
            || ! is_array($quotations)
            || $quotations === []) {
            throw new InvalidArgumentException('EasyParcel Sandbox returned no available quotation.');
        }

        $services = [];
        foreach ($quotations as $quotation) {
            if (! is_array($quotation)
                || ! is_array($quotation['courier'] ?? null)
                || ! is_array($quotation['pricing'] ?? null)) {
                throw new InvalidArgumentException('EasyParcel Sandbox returned an invalid quotation.');
            }

            $courier = $quotation['courier'];
            $pricing = $quotation['pricing'];
            $service = [
                'service_code' => $this->requiredString($courier['service_id'] ?? null, 10),
                'service_name' => $this->requiredString($courier['service_name'] ?? null, 160),
                'courier_code' => $this->requiredString($courier['courier_id'] ?? null, 80),
                'courier_name' => $this->requiredString($courier['courier_name'] ?? null, 120),
                'delivery_duration' => $this->optionalString($courier['delivery_duration'] ?? null, 160),
                'is_pickup' => (bool) ($courier['is_pickup'] ?? false),
                'is_dropoff' => (bool) ($courier['is_dropoff'] ?? false),
                'supports_cod' => $this->supportsCod($quotation['features'] ?? null),
                'currency' => $this->currency($pricing['currency'] ?? null),
                'amount_minor' => $this->decimalToMinor($pricing['total_amount'] ?? null),
            ];

            if (array_key_exists($service['service_code'], $services)) {
                throw new InvalidArgumentException('EasyParcel Sandbox returned duplicate service identifiers.');
            }
            $services[$service['service_code']] = $service;
        }

        return [
            'request_id' => $this->optionalString($payload['request_id'] ?? null, 160),
            'services' => array_values($services),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function normalizeBooking(array $payload): array
    {
        $data = data_get($payload, 'data.0');
        $shipment = data_get($payload, 'data.0.shipments.0');

        if ((int) ($payload['status_code'] ?? 0) !== 200
            || ! is_array($data)
            || ! is_array($shipment)
            || ($shipment['status'] ?? null) !== 'success') {
            throw new InvalidArgumentException('EasyParcel booking outcome is uncertain and requires reconciliation.');
        }

        $label = data_get($shipment, 'awb_urls_by_format.A6');
        if (! is_string($label) || trim($label) === '') {
            $label = $shipment['awb_url'] ?? null;
        }

        return [
            'order_number' => $this->requiredString(data_get($data, 'order_details.order_number'), 160),
            'shipment_number' => $this->requiredString($shipment['shipment_number'] ?? null, 160),
            'awb_number' => $this->optionalString($shipment['awb_number'] ?? null, 160),
            'label_reference' => $this->optionalHttpsUrl($label),
            'tracking_url' => $this->optionalHttpsUrl($shipment['tracking_url'] ?? null),
            'currency' => $this->currency(data_get($shipment, 'pricing_breakdown.currency_code')),
            'amount_minor' => $this->decimalToMinor(data_get($shipment, 'pricing_breakdown.total_paid_amount')),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function isExplicitBookingFailure(array $payload): bool
    {
        $shipment = data_get($payload, 'data.0.shipments.0');

        return (int) ($payload['status_code'] ?? 0) === 200
            && is_array($shipment)
            && ($shipment['status'] ?? null) === 'error';
    }

    /** @return array<string, mixed>|null */
    private function serviceFor(ShipmentQuote $quote, string $serviceCode): ?array
    {
        $services = data_get($quote->response_snapshot, 'services', []);
        if (! is_array($services)) {
            return null;
        }

        foreach ($services as $service) {
            if (is_array($service) && ($service['service_code'] ?? null) === $serviceCode) {
                return $service;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $service */
    private function assertServiceSupportsRequest(ShipmentQuote $quote, array $service): void
    {
        $cod = data_get($quote->request_snapshot, 'booking.cod');
        if ($cod === null) {
            return;
        }

        if (! is_array($cod)
            || ! ($service['supports_cod'] ?? false)
            || ($cod['currency'] ?? null) !== ($service['currency'] ?? null)) {
            throw new InvalidArgumentException('The selected EasyParcel service does not support this COD order.');
        }
    }

    private function supportsCod(mixed $features): bool
    {
        if (! is_array($features)) {
            return false;
        }

        foreach ($features as $feature) {
            if (is_array($feature)
                && is_array($feature['cod'] ?? null)
                && ($feature['cod']['available'] ?? null) === true) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function sender(): array
    {
        $sender = config('services.easyparcel.sender');
        if (! is_array($sender)) {
            throw new InvalidArgumentException('The EasyParcel sender profile is not configured.');
        }

        $required = [
            'name',
            'phone_number_country_code',
            'phone_number',
            'address_1',
            'postcode',
            'city',
            'subdivision_code',
            'country_code',
        ];
        foreach ($required as $key) {
            if (! is_string($sender[$key] ?? null) || trim($sender[$key]) === '') {
                throw new InvalidArgumentException('The EasyParcel sender profile is not configured.');
            }
        }

        return array_filter([
            'name' => trim($sender['name']),
            'company' => $this->optionalString($sender['company'] ?? null, 255),
            'phone_number_country_code' => strtoupper(trim($sender['phone_number_country_code'])),
            'phone_number' => $this->phoneNumber($sender['phone_number'], $sender['phone_number_country_code']),
            'email' => $this->optionalString($sender['email'] ?? null, 255),
            'address_1' => trim($sender['address_1']),
            'address_2' => $this->optionalString($sender['address_2'] ?? null, 255),
            'postcode' => $this->postcode($sender['postcode']),
            'city' => trim($sender['city']),
            'subdivision_code' => strtoupper(trim($sender['subdivision_code'])),
            'country_code' => $this->countryCode($sender['country_code']),
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, string> */
    private function receiver(Order $order, OrderAddress $address): array
    {
        $country = $this->countryCode($address->country);
        $name = trim($address->first_name.' '.$address->last_name);
        $phone = $address->phone ?? $order->customer?->phone;

        if ($name === '' || ! is_string($phone) || trim($phone) === '') {
            throw new InvalidArgumentException('The order shipping contact is incomplete.');
        }

        return array_filter([
            'name' => $name,
            'phone_number_country_code' => $country,
            'phone_number' => $this->phoneNumber($phone, $country),
            'email' => $this->optionalString($order->email, 255),
            'address_1' => trim($address->line_1),
            'address_2' => $this->optionalString($address->line_2, 255),
            'postcode' => $this->postcode($address->postal_code),
            'city' => trim($address->city),
            'subdivision_code' => $this->subdivisionCode($country, $address->state),
            'country_code' => $country,
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @return list<array<string, int|float|string>> */
    private function bookingItems(Order $order, PackageSpec $actualPackage): array
    {
        return $order->items->map(function ($item) use ($actualPackage): array {
            $snapshot = is_array($item->package_snapshot) ? $item->package_snapshot : [];

            return [
                'content' => Str::limit(trim($item->title), 120, ''),
                'weight' => $this->weightFromSnapshot($snapshot, $actualPackage->weightKg),
                'height' => $this->dimensionFromSnapshot($snapshot, 'height_cm', $actualPackage->heightCm),
                'length' => $this->dimensionFromSnapshot($snapshot, 'length_cm', $actualPackage->lengthCm),
                'width' => $this->dimensionFromSnapshot($snapshot, 'width_cm', $actualPackage->widthCm),
                'currency_code' => 'MYR',
                'value' => $this->money($item->unit_price),
                'quantity' => $item->quantity,
            ];
        })->values()->all();
    }

    /** @return array{amount: float, currency: string}|null */
    private function codSnapshot(Order $order): ?array
    {
        $payment = $order->payments->firstWhere('gateway', 'cod');
        if ($payment === null) {
            return null;
        }

        if (! is_numeric($payment->amount) || (int) $payment->amount <= 0) {
            throw new InvalidArgumentException('The COD payment snapshot is incomplete.');
        }

        return [
            'amount' => $this->money((int) $payment->amount),
            'currency' => $this->currency(data_get($this->currencies->default(), 'code')),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function weightFromSnapshot(array $snapshot, float $fallback): float
    {
        $grams = $snapshot['weight_grams'] ?? null;
        if (! is_numeric($grams) || (float) $grams <= 0) {
            return (float) number_format($fallback, 2, '.', '');
        }

        return ceil(((float) $grams / 1000) * 100) / 100;
    }

    /** @param array<string, mixed> $snapshot */
    private function dimensionFromSnapshot(array $snapshot, string $key, float $fallback): float
    {
        $value = $snapshot[$key] ?? null;

        return is_numeric($value) && (float) $value > 0
            ? (float) number_format((float) $value, 2, '.', '')
            : (float) number_format($fallback, 2, '.', '');
    }

    private function money(int $minor): float
    {
        return (float) number_format($minor / 100, 2, '.', '');
    }

    private function decimalToMinor(mixed $value): int
    {
        $string = is_int($value) || is_float($value) || is_string($value) ? trim((string) $value) : '';
        if (! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $string, $matches)) {
            throw new InvalidArgumentException('EasyParcel returned an invalid price.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function currency(mixed $value): string
    {
        $currency = is_string($value) ? strtoupper(trim($value)) : '';
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('EasyParcel returned an invalid currency.');
        }

        return $currency;
    }

    private function requiredString(mixed $value, int $max): string
    {
        $string = $this->optionalString($value, $max);
        if ($string === null) {
            throw new InvalidArgumentException('EasyParcel returned an incomplete response.');
        }

        return $string;
    }

    private function optionalString(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('EasyParcel returned an invalid response.');
        }

        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('EasyParcel returned an invalid response.');
        }

        return $value;
    }

    private function optionalHttpsUrl(mixed $value): ?string
    {
        $url = $this->optionalString($value, 2048);
        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);
        $host = is_array($parts) && is_string($parts['host'] ?? null)
            ? strtolower($parts['host'])
            : null;
        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || $host === null
            || isset($parts['user'])
            || isset($parts['pass'])
            || ($host !== 'easyparcel.com' && ! str_ends_with($host, '.easyparcel.com'))) {
            throw new InvalidArgumentException('EasyParcel returned an invalid reference URL.');
        }

        return $url;
    }

    private function postcode(mixed $value): string
    {
        $postcode = is_string($value) || is_int($value) ? trim((string) $value) : '';
        if ($postcode === '' || mb_strlen($postcode) > 10) {
            throw new InvalidArgumentException('The shipment postcode is invalid.');
        }

        return $postcode;
    }

    private function phoneNumber(string $value, string $country): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strtoupper($country) === 'MY') {
            if (str_starts_with($digits, '60')) {
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }
        }

        if (strlen($digits) < 7 || strlen($digits) > 20) {
            throw new InvalidArgumentException('The shipment phone number is invalid.');
        }

        return $digits;
    }

    private function countryCode(string $value): string
    {
        $normalized = strtoupper(trim($value));
        $normalized = match ($normalized) {
            'MALAYSIA' => 'MY',
            'SINGAPORE' => 'SG',
            default => $normalized,
        };

        if (! preg_match('/^[A-Z]{2}$/', $normalized)) {
            throw new InvalidArgumentException('The shipment country is invalid.');
        }

        return $normalized;
    }

    private function subdivisionCode(string $country, ?string $state): string
    {
        $normalized = strtoupper(trim((string) $state));
        if (preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', $normalized)) {
            return $normalized;
        }

        if ($country !== 'MY') {
            throw new InvalidArgumentException('The shipment subdivision is invalid.');
        }

        $key = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', trim((string) $state)) ?? '');
        $code = [
            'johor' => 'MY-01',
            'kedah' => 'MY-02',
            'kelantan' => 'MY-03',
            'melaka' => 'MY-04',
            'malacca' => 'MY-04',
            'negeri sembilan' => 'MY-05',
            'pahang' => 'MY-06',
            'penang' => 'MY-07',
            'pulau pinang' => 'MY-07',
            'perak' => 'MY-08',
            'perlis' => 'MY-09',
            'selangor' => 'MY-10',
            'terengganu' => 'MY-11',
            'sabah' => 'MY-12',
            'sarawak' => 'MY-13',
            'kuala lumpur' => 'MY-14',
            'wilayah persekutuan kuala lumpur' => 'MY-14',
            'labuan' => 'MY-15',
            'putrajaya' => 'MY-16',
        ][$key] ?? null;

        if ($code === null) {
            throw new InvalidArgumentException('The shipment subdivision is invalid.');
        }

        return $code;
    }
}
