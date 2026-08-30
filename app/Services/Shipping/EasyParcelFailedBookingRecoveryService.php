<?php

namespace App\Services\Shipping;

use App\Models\EasyParcelShipmentRecovery;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class EasyParcelFailedBookingRecoveryService
{
    private const CANCELLING = 'cancelling';

    private const AWAITING_REPLACEMENT = 'awaiting_replacement';

    private const COMPLETE = 'complete';

    private const CANCEL_CLAIMED = 'claimed';

    private const CANCEL_CLAIM_LEASE_MINUTES = 5;

    private const CANCELLATION_UNCONFIRMED_MESSAGE = 'EasyParcel cancellation unconfirmed.';

    public function __construct(
        private readonly EasyParcelCancellationService $cancellations,
    ) {}

    public function confirm(Shipment $shipment, User $actor, string $reason): EasyParcelShipmentRecovery
    {
        if (DB::connection()->transactionLevel() !== 0) {
            throw new InvalidArgumentException('EasyParcel failed-booking recovery must start outside an existing database transaction.');
        }

        $reason = $this->reason($reason);

        /** @var array{recovery: EasyParcelShipmentRecovery, should_cancel: bool, shipment_number: string} $claim */
        $claim = DB::transaction(function () use ($actor, $reason, $shipment): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $lockedQuote = $lockedShipment->shipment_quote_id === null
                ? null
                : ShipmentQuote::query()->lockForUpdate()->find($lockedShipment->shipment_quote_id);

            $existing = EasyParcelShipmentRecovery::query()
                ->where('shipment_id', $lockedShipment->id)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof EasyParcelShipmentRecovery) {
                if ($this->claimIsStale($existing)) {
                    if ($existing->order_id !== $lockedOrder->id
                        || $existing->shipment_id !== $lockedShipment->id
                        || $existing->provider_shipment_number !== $lockedShipment->provider_reference
                        || $lockedShipment->source !== ShippingProviderConfigurationService::PROVIDER
                        || $lockedShipment->status !== 'pending'
                        || filled($lockedShipment->tracking_number)
                        || ! $lockedQuote instanceof ShipmentQuote
                        || $lockedQuote->order_id !== $lockedOrder->id) {
                        throw new InvalidArgumentException('The EasyParcel recovery identity changed before completion.');
                    }

                    $lockedShipment->forceFill([
                        'status' => 'booking_failed',
                        'cancelled_at' => null,
                    ])->save();
                    $lockedQuote->forceFill([
                        'booking_state' => 'failed',
                        'booking_claim_key' => null,
                        'last_error' => 'provider_failure_confirmed',
                    ])->save();
                    $existing->forceFill([
                        'state' => self::AWAITING_REPLACEMENT,
                        'provider_cancel_state' => 'error',
                        'provider_cancel_message' => self::CANCELLATION_UNCONFIRMED_MESSAGE,
                    ])->save();
                }

                return [
                    'recovery' => $existing->fresh(),
                    'should_cancel' => false,
                    'shipment_number' => (string) $lockedShipment->provider_reference,
                ];
            }

            if (! $actor->exists
                || $lockedShipment->order_id !== $lockedOrder->id
                || $lockedShipment->source !== ShippingProviderConfigurationService::PROVIDER
                || $lockedShipment->status !== 'pending'
                || filled($lockedShipment->tracking_number)
                || ! is_string($lockedShipment->provider_reference)
                || trim($lockedShipment->provider_reference) === ''
                || ! $lockedQuote instanceof ShipmentQuote
                || $lockedQuote->order_id !== $lockedOrder->id) {
                throw new InvalidArgumentException('This EasyParcel shipment is not eligible for failed-booking recovery.');
            }

            $otherUnresolved = EasyParcelShipmentRecovery::query()
                ->where('order_id', $lockedOrder->id)
                ->where('state', '!=', self::COMPLETE)
                ->lockForUpdate()
                ->exists();
            if ($otherUnresolved) {
                throw new InvalidArgumentException('This order already has an unresolved EasyParcel recovery.');
            }

            $recovery = EasyParcelShipmentRecovery::query()->create([
                'shipment_id' => $lockedShipment->id,
                'order_id' => $lockedOrder->id,
                'confirmed_by' => $actor->id,
                'state' => self::CANCELLING,
                'reason' => $reason,
                'provider_shipment_number' => trim($lockedShipment->provider_reference),
                'provider_cancel_state' => self::CANCEL_CLAIMED,
                'provider_cancel_message' => null,
                'provider_cancel_claimed_at' => now(),
                'confirmed_at' => now(),
            ]);

            return [
                'recovery' => $recovery,
                'should_cancel' => true,
                'shipment_number' => trim($lockedShipment->provider_reference),
            ];
        });

        if (! $claim['should_cancel']) {
            return $claim['recovery']->fresh();
        }

        try {
            $result = $this->cancellations->cancel($claim['shipment_number'], $reason);
        } catch (Throwable) {
            $result = new EasyParcelCancellationResult(
                'error',
                self::CANCELLATION_UNCONFIRMED_MESSAGE,
            );
        }

        return DB::transaction(function () use ($claim, $result): EasyParcelShipmentRecovery {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($claim['recovery']->order_id);
            $lockedShipment = Shipment::query()
                ->lockForUpdate()
                ->findOrFail($claim['recovery']->shipment_id);
            $lockedQuote = $lockedShipment->shipment_quote_id === null
                ? null
                : ShipmentQuote::query()->lockForUpdate()->find($lockedShipment->shipment_quote_id);
            $recovery = EasyParcelShipmentRecovery::query()
                ->whereKey($claim['recovery']->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recovery->state !== self::CANCELLING
                || $recovery->provider_cancel_state !== self::CANCEL_CLAIMED) {
                return $recovery->fresh();
            }
            if ($recovery->order_id !== $lockedOrder->id
                || $recovery->shipment_id !== $lockedShipment->id
                || $lockedShipment->order_id !== $lockedOrder->id
                || $recovery->provider_shipment_number !== $lockedShipment->provider_reference
                || $lockedShipment->source !== ShippingProviderConfigurationService::PROVIDER
                || $lockedShipment->status !== 'pending'
                || filled($lockedShipment->tracking_number)
                || ! $lockedQuote instanceof ShipmentQuote
                || $lockedQuote->order_id !== $lockedOrder->id) {
                throw new InvalidArgumentException('The EasyParcel recovery identity changed before completion.');
            }

            $providerCancelled = $result->state === 'success';
            $lockedShipment->forceFill([
                'status' => $providerCancelled ? 'cancelled' : 'booking_failed',
                'cancelled_at' => $providerCancelled ? now() : null,
            ])->save();
            $lockedQuote->forceFill([
                'booking_state' => 'failed',
                'booking_claim_key' => null,
                'last_error' => 'provider_failure_confirmed',
            ])->save();
            $recovery->forceFill([
                'state' => self::AWAITING_REPLACEMENT,
                'provider_cancel_state' => $result->state,
                'provider_cancel_message' => $result->message,
            ])->save();

            return $recovery->fresh();
        });
    }

    public function recordProviderCancellation(
        Shipment $shipment,
        ?User $actor,
        int $providerStatusCode,
        string $providerStatus,
        CarbonInterface $checkedAt,
    ): EasyParcelShipmentRecovery {
        if ($providerStatusCode !== 0 || trim($providerStatus) === '') {
            throw new InvalidArgumentException('The EasyParcel provider-terminal recovery evidence is invalid.');
        }

        return DB::transaction(function () use (
            $actor,
            $checkedAt,
            $providerStatus,
            $shipment,
        ): EasyParcelShipmentRecovery {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $lockedQuote = $lockedShipment->shipment_quote_id === null
                ? null
                : ShipmentQuote::query()->lockForUpdate()->find($lockedShipment->shipment_quote_id);
            $existing = EasyParcelShipmentRecovery::query()
                ->where('shipment_id', $lockedShipment->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof EasyParcelShipmentRecovery) {
                if ($existing->order_id !== $lockedOrder->id
                    || $existing->provider_shipment_number !== $lockedShipment->provider_reference
                    || ! $lockedQuote instanceof ShipmentQuote
                    || $lockedQuote->order_id !== $lockedOrder->id) {
                    throw new InvalidArgumentException('The EasyParcel recovery identity changed before completion.');
                }

                if (in_array($existing->state, [self::AWAITING_REPLACEMENT, self::COMPLETE], true)) {
                    return $existing->fresh();
                }

                if ($lockedShipment->source !== ShippingProviderConfigurationService::PROVIDER
                    || ! in_array($lockedShipment->status, ['pending', 'on_hold', 'cancelled'], true)
                    || filled($lockedShipment->tracking_number)) {
                    throw new InvalidArgumentException('The EasyParcel recovery identity changed before completion.');
                }

                $lockedShipment->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => $checkedAt,
                ])->save();
                $lockedQuote->forceFill([
                    'booking_state' => 'failed',
                    'booking_claim_key' => null,
                    'last_error' => 'provider_cancelled_booking',
                ])->save();
                $existing->forceFill([
                    'state' => $existing->replacement_shipment_id === null
                        ? self::AWAITING_REPLACEMENT
                        : self::COMPLETE,
                    'provider_cancel_state' => 'success',
                    'provider_cancel_message' => 'EasyParcel cancellation confirmed by provider status code 0.',
                    'provider_cancel_claimed_at' => $checkedAt,
                ])->save();

                return $existing->fresh();
            }

            if (($actor !== null && ! $actor->exists)
                || $lockedShipment->order_id !== $lockedOrder->id
                || $lockedShipment->source !== ShippingProviderConfigurationService::PROVIDER
                || ! in_array($lockedShipment->status, ['pending', 'on_hold', 'cancelled'], true)
                || filled($lockedShipment->tracking_number)
                || ! is_string($lockedShipment->provider_reference)
                || trim($lockedShipment->provider_reference) === ''
                || ! $lockedQuote instanceof ShipmentQuote
                || $lockedQuote->order_id !== $lockedOrder->id) {
                throw new InvalidArgumentException('This EasyParcel shipment is not eligible for provider-terminal recovery.');
            }

            $otherUnresolved = EasyParcelShipmentRecovery::query()
                ->where('order_id', $lockedOrder->id)
                ->where('state', '!=', self::COMPLETE)
                ->lockForUpdate()
                ->exists();
            if ($otherUnresolved) {
                throw new InvalidArgumentException('This order already has an unresolved EasyParcel recovery.');
            }

            $lockedShipment->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => $checkedAt,
            ])->save();
            $lockedQuote->forceFill([
                'booking_state' => 'failed',
                'booking_claim_key' => null,
                'last_error' => 'provider_cancelled_booking',
            ])->save();

            return EasyParcelShipmentRecovery::query()->create([
                'shipment_id' => $lockedShipment->id,
                'order_id' => $lockedOrder->id,
                'confirmed_by' => $actor?->id,
                'state' => self::AWAITING_REPLACEMENT,
                'reason' => sprintf('EasyParcel reports %s (code 0).', trim($providerStatus)),
                'provider_shipment_number' => trim($lockedShipment->provider_reference),
                'provider_cancel_state' => 'success',
                'provider_cancel_message' => 'EasyParcel cancellation confirmed by provider status code 0.',
                'provider_cancel_claimed_at' => $checkedAt,
                'confirmed_at' => $checkedAt,
            ]);
        });
    }

    public function hasImmutableHistory(Shipment $shipment): bool
    {
        if (in_array($shipment->status, ['booking_failed', 'cancelled'], true)) {
            return true;
        }

        return EasyParcelShipmentRecovery::query()
            ->where('shipment_id', $shipment->id)
            ->whereIn('state', [self::CANCELLING, self::AWAITING_REPLACEMENT, self::COMPLETE])
            ->exists();
    }

    public function completeForReplacement(Shipment $replacement): void
    {
        $complete = function () use ($replacement): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($replacement->order_id);
            $lockedReplacement = Shipment::query()->lockForUpdate()->findOrFail($replacement->id);
            if ($lockedReplacement->order_id !== $lockedOrder->id
                || $lockedReplacement->source !== ShippingProviderConfigurationService::PROVIDER
                || in_array($lockedReplacement->status, ['booking_failed', 'cancelled'], true)) {
                throw new InvalidArgumentException('The replacement EasyParcel shipment is invalid.');
            }

            $recovery = EasyParcelShipmentRecovery::query()
                ->where('order_id', $lockedOrder->id)
                ->where('state', self::AWAITING_REPLACEMENT)
                ->whereNull('replacement_shipment_id')
                ->lockForUpdate()
                ->first();
            if (! $recovery instanceof EasyParcelShipmentRecovery) {
                return;
            }

            $failedShipment = Shipment::query()->lockForUpdate()->findOrFail($recovery->shipment_id);
            if ($failedShipment->id === $lockedReplacement->id
                || $lockedReplacement->sequence <= $failedShipment->sequence) {
                throw new InvalidArgumentException('The replacement EasyParcel shipment sequence is invalid.');
            }

            $recovery->forceFill([
                'replacement_shipment_id' => $lockedReplacement->id,
                'state' => self::COMPLETE,
                'completed_at' => now(),
            ])->save();
        };

        if (DB::connection()->transactionLevel() === 0) {
            DB::transaction($complete);

            return;
        }

        $complete();
    }

    private function reason(string $reason): string
    {
        $reason = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $reason) ?? '');
        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('The failed-booking confirmation reason must be between 3 and 500 characters.');
        }

        return $reason;
    }

    private function claimIsStale(EasyParcelShipmentRecovery $recovery): bool
    {
        return $recovery->state === self::CANCELLING
            && $recovery->provider_cancel_state === self::CANCEL_CLAIMED
            && $recovery->provider_cancel_claimed_at !== null
            && $recovery->provider_cancel_claimed_at->lte(
                now()->subMinutes(self::CANCEL_CLAIM_LEASE_MINUTES),
            );
    }
}
