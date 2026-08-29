<?php

namespace App\Services\Shipping;

use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

final class EasyParcelShipmentDetailsService
{
    public const DETAILS_URL = 'https://api.easyparcel.com/open_api/2026-03/shipment/details';

    public function __construct(
        private readonly EasyParcelProviderSessionService $providerSessions,
    ) {}

    public function reconcile(Shipment $shipment): Shipment
    {
        try {
            $shipmentNumber = $this->requiredString($shipment->provider_reference, 160);
            if ($shipment->source !== ShippingProviderConfigurationService::PROVIDER
                || $shipment->provider_reference !== $shipmentNumber) {
                throw new EasyParcelTrackingException;
            }

            $providerSession = $this->providerSessions->connected();
            if ($providerSession === null) {
                throw new EasyParcelTrackingException;
            }

            $response = Http::acceptJson()
                ->withToken($providerSession->accessToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::DETAILS_URL, ['shipment_number' => $shipmentNumber]);
            $payload = $response->json();

            if (! $response->successful() || ! is_array($payload)) {
                throw new EasyParcelTrackingException;
            }

            $details = $this->normalizeResponse($payload, $shipmentNumber);

            return DB::transaction(function () use (
                $shipment,
                $shipmentNumber,
                $details,
                $providerSession,
            ): Shipment {
                $locked = Shipment::query()->lockForUpdate()->find($shipment->id);
                if (! $locked instanceof Shipment
                    || $locked->source !== ShippingProviderConfigurationService::PROVIDER
                    || $locked->provider_reference !== $shipmentNumber) {
                    throw new EasyParcelTrackingException;
                }

                $updates = array_filter([
                    'provider_order_reference' => $details['order_number'],
                    'tracking_number' => $details['awb_number'],
                    'label_path' => $details['awb_url'],
                    'tracking_url' => $details['tracking_url'],
                ], static fn (?string $value): bool => $value !== null);

                if ($updates !== []) {
                    $locked->forceFill($updates)->save();
                }

                $this->providerSessions->assertCurrent($providerSession);

                return $locked;
            });
        } catch (EasyParcelTrackingException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new EasyParcelTrackingException;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     shipment_number: string,
     *     order_number: ?string,
     *     awb_number: ?string,
     *     awb_url: ?string,
     *     tracking_url: ?string
     * }
     */
    private function normalizeResponse(array $payload, string $requestedShipmentNumber): array
    {
        $data = $payload['data'] ?? null;
        if (($payload['status_code'] ?? null) !== 200
            || ! is_array($data)
            || ! array_is_list($data)
            || count($data) !== 1
            || ! is_array($data[0])) {
            throw new InvalidArgumentException('EasyParcel returned an invalid response.');
        }

        $shipment = $data[0];
        $rawShipmentNumber = $shipment['shipment_number'] ?? null;
        $shipmentNumber = $this->requiredString($rawShipmentNumber, 160);
        if (! is_string($rawShipmentNumber)
            || ! hash_equals($requestedShipmentNumber, $rawShipmentNumber)
            || ! hash_equals($requestedShipmentNumber, $shipmentNumber)) {
            throw new InvalidArgumentException('EasyParcel returned a mismatched shipment.');
        }

        $shipmentDetails = $shipment['shipment_details'] ?? null;
        if (! is_array($shipmentDetails) || array_is_list($shipmentDetails)) {
            throw new InvalidArgumentException('EasyParcel returned an invalid response.');
        }

        return [
            'shipment_number' => $shipmentNumber,
            'order_number' => $this->optionalString($shipment['order_number'] ?? null, 160),
            'awb_number' => $this->optionalString($shipmentDetails['awb_number'] ?? null, 160),
            'awb_url' => $this->optionalHttpsUrl($shipmentDetails['awb_url'] ?? null),
            'tracking_url' => $this->optionalHttpsUrl($shipmentDetails['tracking_url'] ?? null),
        ];
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
}
