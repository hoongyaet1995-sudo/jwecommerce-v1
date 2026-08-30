<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

final class EasyParcelCancellationService
{
    public const CANCELLATION_URL = 'https://api.easyparcel.com/open_api/'.EasyParcelOpenApiClient::API_VERSION.'/shipment/cancel';

    private const FAILURE_MESSAGE = 'EasyParcel cancellation could not be confirmed.';

    public function __construct(
        private readonly EasyParcelProviderSessionService $providerSessions,
    ) {}

    public function cancel(string $shipmentNumber, string $remark): EasyParcelCancellationResult
    {
        $shipmentNumber = trim($shipmentNumber);
        $remark = trim($remark);
        if ($shipmentNumber === '' || mb_strlen($shipmentNumber) > 160
            || $remark === '' || mb_strlen($remark) > 500) {
            throw new InvalidArgumentException('The EasyParcel cancellation request is invalid.');
        }

        try {
            $providerSession = $this->providerSessions->connected();
            if ($providerSession === null) {
                return $this->error();
            }

            $response = Http::acceptJson()
                ->withToken($providerSession->accessToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::CANCELLATION_URL, [
                    'cancel_list' => [[
                        'shipment_number' => $shipmentNumber,
                        'remark' => $remark,
                    ]],
                ]);
            $payload = $response->json();

            if ($response->status() !== 200
                || ! is_array($payload)
                || ($payload['status_code'] ?? null) !== 200) {
                return $this->error();
            }

            $data = $payload['data'] ?? null;
            if (! is_array($data) || ! array_is_list($data)) {
                return $this->error();
            }

            $matches = array_values(array_filter(
                $data,
                static fn (mixed $item): bool => is_array($item)
                    && is_string($item['shipment_number'] ?? null)
                    && hash_equals($shipmentNumber, $item['shipment_number']),
            ));
            if (count($matches) !== 1) {
                return $this->error();
            }

            $result = $matches[0];
            $status = $result['status'] ?? null;
            if ($status !== 'success' && $status !== 'error') {
                return $this->error();
            }

            $message = $this->safeMessage($result['message'] ?? null)
                ?? ($status === 'success' ? 'Shipment Cancelled' : self::FAILURE_MESSAGE);

            return new EasyParcelCancellationResult($status, $message);
        } catch (Throwable) {
            return $this->error();
        }
    }

    private function error(): EasyParcelCancellationResult
    {
        return new EasyParcelCancellationResult('error', self::FAILURE_MESSAGE);
    }

    private function safeMessage(mixed $message): ?string
    {
        if (! is_string($message)) {
            return null;
        }

        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? '';
        $message = preg_replace('/\b(Bearer)\s+[A-Za-z0-9._~+\/=:-]+/i', '$1 [REDACTED]', $message) ?? '';
        $message = preg_replace(
            '/\b(access_token|refresh_token|client_secret|authorization)\s*[:=]\s*[^\s,;]+/i',
            '$1=[REDACTED]',
            $message,
        ) ?? '';
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? '');

        return $message === '' ? null : mb_substr($message, 0, 500);
    }
}
