<?php

namespace App\Services\Shipping;

use App\Models\Shipment;
use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Services\ShipmentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

final class EasyParcelTrackingService
{
    public const TRACKING_URL = 'https://api.easyparcel.com/open_api/'.EasyParcelOpenApiClient::API_VERSION.'/shipment/tracking_status';

    private const BATCH_SIZE = 100;

    public function __construct(private readonly ShipmentService $shipments) {}

    public function syncNextBatch(): int
    {
        $shipments = $this->nextBatch();
        if ($shipments->isEmpty()) {
            return 0;
        }

        $providerSnapshot = $this->connectedProviderSnapshot();
        if ($providerSnapshot === null) {
            return 0;
        }

        $awbNumbers = $shipments->pluck('tracking_number')->all();

        try {
            $response = Http::acceptJson()
                ->withToken($providerSnapshot['access_token'])
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
            DB::transaction(function () use ($shipments, $normalized, $polledAt, $providerSnapshot): void {
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

                $this->assertProviderSnapshotCurrent($providerSnapshot);
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
     * @return array{
     *     provider_id: int,
     *     credential_id: int,
     *     configuration_version: int,
     *     credential_fingerprint: string,
     *     access_token: string
     * }|null
     */
    private function connectedProviderSnapshot(): ?array
    {
        $provider = ShippingProvider::query()
            ->with('credential')
            ->where('provider', ShippingProviderConfigurationService::PROVIDER)
            ->where('environment', ShippingProviderConfigurationService::ENVIRONMENT)
            ->first();

        if ($provider === null || ! $provider->enabled || $provider->connection_state !== 'connected') {
            return null;
        }

        $credential = $provider->credential;
        $credentials = $credential?->credentials;
        if (! is_array($credentials)
            || ($credentials['token_type'] ?? null) !== 'Bearer'
            || ! is_string($credentials['access_token'] ?? null)
            || $credentials['access_token'] === ''
            || ! is_string($credentials['access_token_expires_at'] ?? null)) {
            throw new EasyParcelTrackingException;
        }

        try {
            $expiresAt = Carbon::parse($credentials['access_token_expires_at']);
        } catch (Throwable) {
            throw new EasyParcelTrackingException;
        }

        if (! $expiresAt->isAfter(now()->addMinute())) {
            throw new EasyParcelTrackingException;
        }

        return [
            'provider_id' => $provider->id,
            'credential_id' => $credential->id,
            'configuration_version' => $provider->configuration_version,
            'credential_fingerprint' => $this->credentialFingerprint($credentials),
            'access_token' => $credentials['access_token'],
        ];
    }

    /**
     * @param  array{
     *     provider_id: int,
     *     credential_id: int,
     *     configuration_version: int,
     *     credential_fingerprint: string,
     *     access_token: string
     * }  $snapshot
     */
    private function assertProviderSnapshotCurrent(array $snapshot): void
    {
        $provider = ShippingProvider::query()->lockForUpdate()->find($snapshot['provider_id']);
        $credential = ShippingProviderCredential::query()->lockForUpdate()->find($snapshot['credential_id']);

        if ($provider === null
            || $credential === null
            || $credential->shipping_provider_id !== $provider->id
            || $provider->provider !== ShippingProviderConfigurationService::PROVIDER
            || $provider->environment !== ShippingProviderConfigurationService::ENVIRONMENT
            || ! $provider->enabled
            || $provider->connection_state !== 'connected'
            || $provider->configuration_version !== $snapshot['configuration_version']
            || ! hash_equals(
                $snapshot['credential_fingerprint'],
                $this->credentialFingerprint($credential->credentials ?? []),
            )) {
            throw new EasyParcelTrackingException;
        }
    }

    /** @param array<string, mixed> $credentials */
    private function credentialFingerprint(array $credentials): string
    {
        $canonical = [];
        foreach ([
            'client_id',
            'client_secret',
            'token_type',
            'access_token',
            'access_token_expires_at',
            'refresh_token',
            'refresh_token_expires_at',
        ] as $key) {
            $canonical[$key] = $credentials[$key] ?? null;
        }

        return hash('sha256', serialize($canonical));
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
        $status = $this->normalizedStatus($code);
        $occurredAt = $this->eventTime($log['event_date'] ?? null);
        $description = $this->normalizedText($log['tracking_status'] ?? null, 2000)
            ?? $this->defaultDescription($status);
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

    private function normalizedStatus(int $code): string
    {
        return match ($code) {
            0, 8 => 'failed_delivery',
            2, 7 => 'pending',
            3, 11 => 'shipped',
            4 => 'in_transit',
            5 => 'delivered',
            6 => 'returned',
            default => 'provider_update',
        };
    }

    private function defaultDescription(string $status): string
    {
        return match ($status) {
            'pending' => 'EasyParcel is preparing the shipment.',
            'shipped' => 'EasyParcel confirmed carrier handoff.',
            'in_transit' => 'The shipment is in transit.',
            'delivered' => 'EasyParcel confirmed delivery.',
            'failed_delivery' => 'EasyParcel reported a delivery exception.',
            'returned' => 'EasyParcel reported a return.',
            default => 'EasyParcel reported a shipment update.',
        };
    }
}
