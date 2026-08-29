<?php

namespace App\Services\Shipping;

use App\Jobs\ReconcileEasyParcelWebhook;
use App\Models\Shipment;
use App\Models\ShippingWebhookReceipt;
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

    /** @param array<string, mixed> $payload */
    public function receiveAndQueue(string $rawBody, array $payload): ShippingWebhookReceipt
    {
        return DB::transaction(function () use ($rawBody, $payload): ShippingWebhookReceipt {
            $receipt = $this->receive($rawBody, $payload);

            if ($receipt->wasRecentlyCreated !== true
                || $receipt->shipment_id === null
                || $receipt->processing_state !== 'received'
                || ! in_array($receipt->topic, self::RECONCILABLE_TOPICS, true)) {
                return $receipt;
            }

            $receipt->forceFill([
                'processing_state' => 'queued',
                'queued_at' => now(),
            ])->save();

            $receiptId = $receipt->id;
            DB::afterCommit(static function () use ($receiptId): void {
                ReconcileEasyParcelWebhook::dispatch($receiptId);
            });

            return $receipt;
        });
    }

    /** @param array<string, mixed> $payload */
    public function receive(string $rawBody, array $payload): ShippingWebhookReceipt
    {
        $topic = $this->topic($payload['topic'] ?? null);
        $snapshot = $this->correlationSnapshot($payload);
        $shipment = $topic === 'ondemand.status.update' ? null : $this->shipment($snapshot);
        $processingState = $topic === 'ondemand.status.update'
            ? 'ignored'
            : ($shipment === null ? 'unmatched' : 'received');

        return ShippingWebhookReceipt::query()->firstOrCreate(
            [
                'provider' => ShippingProviderConfigurationService::PROVIDER,
                'environment' => ShippingProviderConfigurationService::ENVIRONMENT,
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
        );
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

    /** @param array<string, int|string> $snapshot */
    private function shipment(array $snapshot): ?Shipment
    {
        $providerReference = $snapshot['shipment_number'] ?? null;
        if (is_string($providerReference)) {
            $shipment = Shipment::query()->where('provider_reference', $providerReference)->first();
            if ($shipment !== null) {
                return $shipment;
            }
        }

        $awb = $snapshot['awb_number'] ?? null;

        return is_string($awb)
            ? Shipment::query()->where('tracking_number', $awb)->first()
            : null;
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
