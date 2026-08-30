<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

final class EasyParcelCancellationService
{
    public const CANCELLATION_URL = 'https://api.easyparcel.com/open_api/'.EasyParcelOpenApiClient::API_VERSION.'/shipment/cancel';

    private const SUCCESS_MESSAGE = 'EasyParcel cancellation confirmed.';

    private const FAILURE_MESSAGE = 'EasyParcel cancellation unconfirmed.';

    public function __construct(
        private readonly EasyParcelProviderSessionService $providerSessions,
    ) {}

    public function cancel(string $shipmentNumber, string $remark): EasyParcelCancellationResult
    {
        if (DB::connection()->transactionLevel() !== 0) {
            throw new InvalidArgumentException('EasyParcel cancellation must start outside an existing database transaction.');
        }

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

            return new EasyParcelCancellationResult(
                $status,
                $status === 'success' ? self::SUCCESS_MESSAGE : self::FAILURE_MESSAGE,
            );
        } catch (Throwable) {
            return $this->error();
        }
    }

    private function error(): EasyParcelCancellationResult
    {
        return new EasyParcelCancellationResult('error', self::FAILURE_MESSAGE);
    }
}
