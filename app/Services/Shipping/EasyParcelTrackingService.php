<?php

namespace App\Services\Shipping;

use App\Models\Shipment;
use App\Services\ShipmentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

final class EasyParcelTrackingService
{
    public const TRACKING_URL = 'https://api.easyparcel.com/open_api/'.EasyParcelOpenApiClient::API_VERSION.'/shipment/tracking_status';

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly EasyParcelProviderSessionService $providerSessions,
        private readonly EasyParcelStatusNormalizer $statuses,
    ) {}

    public function syncNextBatch(): int
    {
        return $this->syncCollection($this->nextBatch());
    }

    public function syncShipment(Shipment $shipment): int
    {
        $eligible = Shipment::query()
            ->whereKey($shipment->id)
            ->where('source', ShippingProviderConfigurationService::PROVIDER)
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '<>', '')
            ->get();

        return $this->syncCollection($eligible);
    }

    /** @param EloquentCollection<int, Shipment> $shipments */
    private function syncCollection(EloquentCollection $shipments): int
    {
        if ($shipments->isEmpty()) {
            return 0;
        }

        $providerSession = $this->providerSessions->connected();
        if ($providerSession === null) {
            return 0;
        }

        $awbNumbers = $shipments->pluck('tracking_number')->all();

        try {
            $response = Http::acceptJson()
                ->withToken($providerSession->accessToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::TRACKING_URL, ['awb_numbers' => $awbNumbers]);
            $payload = $response->json();

            if (! $response->successful() || ! is_array($payload)) {
                throw new EasyParcelTrackingException;
            }

            $normalized = $this->normalizeResponse($payload, $shipments);
        } catch (EasyParcelTrackingException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new EasyParcelTrackingException;
        }

        $polledAt = now();
        try {
            DB::transaction(function () use ($shipments, $normalized, $polledAt, $providerSession): void {
                foreach ($shipments as $shipment) {
                    $this->shipments->recordEasyParcelEvents(
                        $shipment,
                        (string) $shipment->tracking_number,
                        $normalized[$shipment->id],
                    );

                    Shipment::query()->whereKey($shipment->id)->update([
                        'tracking_polled_at' => $polledAt,
                        'updated_at' => $polledAt,
                    ]);
                }

                $this->providerSessions->assertCurrent($providerSession);
            });
        } catch (EasyParcelTrackingException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new EasyParcelTrackingException;
        }

        return $shipments->count();
    }

    /** @return EloquentCollection<int, Shipment> */
    private function nextBatch(): EloquentCollection
    {
        return Shipment::query()
            ->where('source', ShippingProviderConfigurationService::PROVIDER)
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '<>', '')
            ->whereNotIn('status', ['delivered', 'returned'])
            ->orderByRaw('CASE WHEN tracking_polled_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('tracking_polled_at')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  EloquentCollection<int, Shipment>  $shipments
     * @return array<int, list<array{
     *     external_event_id: string,
     *     status: string,
     *     occurred_at: CarbonImmutable,
     *     description: string,
     *     location: ?string
     * }>>
     */
    private function normalizeResponse(array $payload, EloquentCollection $shipments): array
    {
        $results = data_get($payload, 'data.results');
        if (($payload['status_code'] ?? null) !== 200
            || ! is_array($results)
            || ! array_is_list($results)) {
            throw new EasyParcelTrackingException;
        }

        $expected = $shipments->keyBy('tracking_number');
        $normalized = $shipments->mapWithKeys(fn (Shipment $shipment): array => [$shipment->id => []])->all();
        $seen = [];

        foreach ($results as $result) {
            if (! is_array($result)
                || ! is_string($result['awb_number'] ?? null)
                || ! $expected->has($result['awb_number'])
                || isset($seen[$result['awb_number']])) {
                throw new EasyParcelTrackingException;
            }

            $awb = $result['awb_number'];
            $seen[$awb] = true;
            $shipment = $expected->get($awb);
            $status = $result['status'] ?? null;

            if ($status === 'not_found') {
                continue;
            }

            $logs = $result['status_log'] ?? null;
            if ($status !== 'success' || ! is_array($logs) || ! array_is_list($logs)) {
                throw new EasyParcelTrackingException;
            }

            if (! is_string($result['shipment_number'] ?? null)
                || $result['shipment_number'] !== $shipment->provider_reference) {
                throw new EasyParcelTrackingException;
            }

            $providerOrderReference = $shipment->provider_order_reference;
            if (is_string($providerOrderReference)
                && $providerOrderReference !== ''
                && (! is_string($result['order_number'] ?? null)
                    || $result['order_number'] !== $providerOrderReference)) {
                throw new EasyParcelTrackingException;
            }

            foreach ($logs as $log) {
                $normalized[$shipment->id][] = $this->normalizeEvent($awb, $log);
            }

            usort($normalized[$shipment->id], function (array $left, array $right): int {
                $timestamp = strcmp(
                    $left['occurred_at']->format('U.u'),
                    $right['occurred_at']->format('U.u'),
                );

                return $timestamp !== 0
                    ? $timestamp
                    : strcmp($left['external_event_id'], $right['external_event_id']);
            });
        }

        if (count($seen) !== $shipments->count()) {
            throw new EasyParcelTrackingException;
        }

        return $normalized;
    }

    /**
     * @return array{
     *     external_event_id: string,
     *     status: string,
     *     occurred_at: CarbonImmutable,
     *     description: string,
     *     location: ?string
     * }
     */
    private function normalizeEvent(string $awb, mixed $log): array
    {
        if (! is_array($log) || ! is_int($log['shipment_status_code'] ?? null)) {
            throw new EasyParcelTrackingException;
        }

        $code = $log['shipment_status_code'];
        $status = $this->statuses->normalize($code);
        $occurredAt = $this->eventTime($log['event_date'] ?? null);
        $description = $this->statuses->description($log['tracking_status'] ?? null);
        $location = $this->normalizedText($log['location'] ?? null, 255);

        try {
            $identity = json_encode([
                'provider' => ShippingProviderConfigurationService::PROVIDER,
                'awb' => $awb,
                'occurred_at' => $occurredAt->format('Y-m-d\TH:i:s.u\Z'),
                'shipment_status_code' => $code,
                'tracking_status' => $description,
                'location' => $location,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new EasyParcelTrackingException;
        }

        return [
            'external_event_id' => 'easyparcel:'.hash('sha256', $identity),
            'status' => $status,
            'occurred_at' => $occurredAt,
            'description' => $description,
            'location' => $location,
        ];
    }

    private function eventTime(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || trim($value) !== $value) {
            throw new EasyParcelTrackingException;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, 'UTC');
            if ($date === false || $date->format('Y-m-d H:i:s') !== $value) {
                throw new EasyParcelTrackingException;
            }

            return $date->utc();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw new EasyParcelTrackingException;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            throw new EasyParcelTrackingException;
        }
    }

    private function normalizedText(mixed $value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            throw new EasyParcelTrackingException;
        }

        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $normalized = is_string($normalized) ? preg_replace('/\s+/u', ' ', trim($normalized)) : null;
        if (! is_string($normalized) || $normalized === '' || mb_strlen($normalized) > $maxLength) {
            throw new EasyParcelTrackingException;
        }

        return $normalized;
    }
}
