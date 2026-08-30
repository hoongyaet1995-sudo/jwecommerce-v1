<?php

namespace App\Services\Shipping;

use App\Models\ShippingWebhookReceipt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EasyParcelWebhookReceiptService
{
    private const TOPICS = [
        'shipment.created',
        'shipment.awb.update',
        'shipment.status.update',
        'shipment.tracking.update',
        'ondemand.status.update',
    ];

    private const RECONCILABLE_TOPICS = [
        'shipment.created',
        'shipment.awb.update',
        'shipment.status.update',
        'shipment.tracking.update',
    ];

    public function __construct(
        private readonly EasyParcelWebhookCorrelationService $correlation,
        private readonly EasyParcelReconciliationTaskService $tasks,
        private readonly EasyParcelReconciliationPublisher $publisher,
    ) {}

    /** @param array<string, mixed> $payload */
    public function receiveAndQueue(string $rawBody, array $payload): ShippingWebhookReceipt
    {
        [$identity, $values] = $this->receiptAttributes($rawBody, $payload);

        [$receipt, $taskId] = DB::transaction(function () use ($identity, $values): array {
            try {
                ShippingWebhookReceipt::query()->create([...$identity, ...$values]);
            } catch (UniqueConstraintViolationException) {
                // The locking current read below returns the concurrent durable receipt.
            }

            $locked = ShippingWebhookReceipt::query()
                ->where($identity)
                ->lockForUpdate()
                ->firstOrFail();

            $taskId = null;
            if (in_array($locked->topic, self::RECONCILABLE_TOPICS, true)) {
                if ($locked->shipment_id !== null && $locked->processing_state === 'received') {
                    $locked->forceFill([
                        'processing_state' => 'queued',
                        'queued_at' => now(),
                    ])->save();
                }

                $taskId = $this->tasks->ensureWebhookReceipt($locked)->id;
            }

            return [$locked, $taskId];
        });

        if (is_int($taskId)) {
            $this->publisher->publish($taskId);
        }

        return $receipt->fresh();
    }

    /** @param array<string, mixed> $payload */
    public function receive(string $rawBody, array $payload): ShippingWebhookReceipt
    {
        [$identity, $values] = $this->receiptAttributes($rawBody, $payload);

        return ShippingWebhookReceipt::query()->firstOrCreate($identity, $values);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{array<string, string>, array<string, mixed>}
     */
    private function receiptAttributes(string $rawBody, array $payload): array
    {
        $topic = $this->topic($payload['topic'] ?? null);
        $snapshot = $this->correlationSnapshot($payload);
        $shipment = $topic === 'ondemand.status.update' ? null : $this->correlation->shipment($snapshot);
        $processingState = $topic === 'ondemand.status.update'
            ? 'ignored'
            : ($shipment === null ? 'unmatched' : 'received');

        return [
            [
                'provider' => ShippingProviderConfigurationService::PROVIDER,
                'environment' => ShippingProviderConfigurationService::operationalEnvironment(),
                'payload_hash' => hash('sha256', $rawBody),
            ],
            [
                'topic' => $topic,
                'provider_uuid' => $this->string($payload['uuid'] ?? null, 160),
                'shipment_id' => $shipment?->id,
                'correlation_snapshot' => $snapshot,
                'processing_state' => $processingState,
                'received_at' => now(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int|string>
     */
    private function correlationSnapshot(array $payload): array
    {
        $snapshot = [];

        foreach (['shipment_number', 'awb_number'] as $field) {
            $value = $this->string($payload[$field] ?? null, 160);
            if ($value !== null) {
                $snapshot[$field] = $value;
            }
        }

        foreach (['shipment_status_code', 'latest_shipment_status_code'] as $field) {
            if (is_int($payload[$field] ?? null)) {
                $snapshot[$field] = $payload[$field];
            }
        }

        foreach (['timestamp', 'event_date'] as $field) {
            $value = $this->string($payload[$field] ?? null, 64);
            if ($value !== null) {
                $snapshot[$field] = $value;
            }
        }

        return $snapshot;
    }

    private function topic(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, self::TOPICS, true)) {
            throw ValidationException::withMessages([
                'topic' => 'EasyParcel sent an unsupported webhook topic.',
            ]);
        }

        return $value;
    }

    private function string(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)
            || $value === ''
            || trim($value) !== $value
            || ! mb_check_encoding($value, 'UTF-8')
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
            || mb_strlen($value) > $maxLength) {
            return null;
        }

        return $value;
    }
}
