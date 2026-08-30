<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EasyParcelShipmentRecovery;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\User;
use App\Services\Admin\ShipmentDataService;
use App\Services\ShipmentService;
use App\Services\Shipping\EasyParcelBookingGuard;
use App\Services\Shipping\EasyParcelCancellationService;
use App\Services\Shipping\EasyParcelFailedBookingRecoveryService;
use App\Services\Shipping\EasyParcelFulfillmentService;
use App\Services\Shipping\PackageSpec;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class EasyParcelFailedBookingRecoveryTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_dispatch_staff_can_confirm_failure_and_provider_cancel_result_is_audited_once(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [$quote, $shipment] = $this->bookedAttempt($order, $provider);
        $transactionLevels = [];

        Http::fake(function (Request $request) use (&$transactionLevels) {
            $transactionLevels[] = DB::transactionLevel();

            return Http::response([
                'status_code' => 200,
                'message' => '1 request success, 1 request error.',
                'data' => [
                    [
                        'status' => 'error',
                        'message' => 'Shipment Not Found',
                        'shipment_number' => 'ES-2608-OTHER',
                    ],
                    [
                        'status' => 'success',
                        'message' => "Shipment\r\nCancelled",
                        'shipment_number' => 'ES-2608-FAILED1',
                    ],
                ],
            ]);
        });

        $service = app(EasyParcelFailedBookingRecoveryService::class);
        $first = $service->confirm($shipment, $actor, 'Provider portal reports a failed booking.');
        $second = $service->confirm($shipment->fresh(), User::factory()->create(), 'This must not replace the audit.');

        self::assertSame($first->id, $second->id);
        self::assertSame([0], $transactionLevels);
        self::assertSame('awaiting_replacement', $first->fresh()->state);
        self::assertSame('success', $first->fresh()->provider_cancel_state);
        self::assertSame('EasyParcel cancellation confirmed.', $first->fresh()->provider_cancel_message);
        self::assertSame('ES-2608-FAILED1', $first->fresh()->provider_shipment_number);
        self::assertSame($actor->id, $first->fresh()->confirmed_by);
        self::assertSame('Provider portal reports a failed booking.', $first->fresh()->reason);
        self::assertNotNull($first->fresh()->confirmed_at);
        self::assertSame('cancelled', $shipment->fresh()->status);
        self::assertNotNull($shipment->fresh()->cancelled_at);
        self::assertSame('failed', $quote->fresh()->booking_state);
        self::assertNull($quote->fresh()->booking_claim_key);
        $this->assertDatabaseCount('easyparcel_shipment_recoveries', 1);
        Http::assertSentCount(1);
    }

    public function test_durable_cancel_claim_rejects_a_late_details_awb_before_cancel_finalization(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [$quote, $shipment] = $this->bookedAttempt($order, $provider);
        $lateWriteRejected = false;

        Http::fake(function (Request $request) use ($shipment, &$lateWriteRejected) {
            self::assertSame(EasyParcelCancellationService::CANCELLATION_URL, $request->url());
            self::assertSame('cancelling', $shipment->failedBookingRecovery()->sole()->state);
            self::assertSame('claimed', $shipment->failedBookingRecovery()->sole()->provider_cancel_state);

            try {
                app(ShipmentService::class)->reconcileEasyParcelDetails(
                    $shipment,
                    (string) $shipment->provider_reference,
                    null,
                    (string) $shipment->provider_order_reference,
                    'AWB-LATE-AFTER-CANCEL-CLAIM',
                    'https://app.easyparcel.com/labels/AWB-LATE-AFTER-CANCEL-CLAIM',
                    'https://easyparcel.com/my/en/track/details/AWB-LATE-AFTER-CANCEL-CLAIM',
                    'pending',
                    [],
                );
            } catch (InvalidArgumentException $exception) {
                $lateWriteRejected = $exception->getMessage() === 'Terminal EasyParcel recovery history is immutable.';
            }

            return Http::response([
                'status_code' => 200,
                'data' => [[
                    'status' => 'success',
                    'message' => 'Shipment Cancelled',
                    'shipment_number' => 'ES-2608-FAILED1',
                ]],
            ]);
        });

        $recovery = app(EasyParcelFailedBookingRecoveryService::class)
            ->confirm($shipment, $actor, 'Provider portal reports a failed booking.');

        self::assertTrue($lateWriteRejected);
        self::assertSame('awaiting_replacement', $recovery->state);
        self::assertSame('success', $recovery->provider_cancel_state);
        self::assertSame('cancelled', $shipment->fresh()->status);
        self::assertNull($shipment->fresh()->tracking_number);
        self::assertSame('failed', $quote->fresh()->booking_state);
        Http::assertSentCount(1);
    }

    public function test_awb_committed_before_manual_confirmation_prevents_recovery_creation(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [$quote, $shipment] = $this->bookedAttempt($order, $provider);
        $shipment->forceFill([
            'tracking_number' => 'AWB-COMMITTED-FIRST',
            'label_path' => 'https://app.easyparcel.com/labels/AWB-COMMITTED-FIRST',
        ])->save();
        Http::fake();

        try {
            app(EasyParcelFailedBookingRecoveryService::class)
                ->confirm($shipment, $actor, 'This must not replace an AWB-backed shipment.');
            self::fail('An AWB-backed shipment must not enter failed-booking recovery.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'This EasyParcel shipment is not eligible for failed-booking recovery.',
                $exception->getMessage(),
            );
        }

        self::assertSame('AWB-COMMITTED-FIRST', $shipment->fresh()->tracking_number);
        self::assertSame('pending', $shipment->fresh()->status);
        self::assertSame('booked', $quote->fresh()->booking_state);
        self::assertNotNull($quote->fresh()->booking_claim_key);
        $this->assertDatabaseCount('easyparcel_shipment_recoveries', 0);
        Http::assertNothingSent();
    }

    public function test_cancel_finalization_refuses_to_terminalize_a_contaminated_claim(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [$quote, $shipment] = $this->bookedAttempt($order, $provider);

        Http::fake(function () use ($shipment) {
            Shipment::query()->whereKey($shipment->id)->update([
                'tracking_number' => 'AWB-CONTAMINATED-CLAIM',
                'updated_at' => now(),
            ]);

            return Http::response([
                'status_code' => 200,
                'data' => [[
                    'status' => 'success',
                    'message' => 'Shipment Cancelled',
                    'shipment_number' => 'ES-2608-FAILED1',
                ]],
            ]);
        });

        try {
            app(EasyParcelFailedBookingRecoveryService::class)
                ->confirm($shipment, $actor, 'Provider portal reports a failed booking.');
            self::fail('A contaminated cancellation claim must not release rebooking.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The EasyParcel recovery identity changed before completion.',
                $exception->getMessage(),
            );
        }

        $recovery = EasyParcelShipmentRecovery::query()->sole();
        self::assertSame('cancelling', $recovery->state);
        self::assertSame('claimed', $recovery->provider_cancel_state);
        self::assertSame('pending', $shipment->fresh()->status);
        self::assertSame('AWB-CONTAMINATED-CLAIM', $shipment->fresh()->tracking_number);
        self::assertSame('booked', $quote->fresh()->booking_state);
        self::assertNotNull($quote->fresh()->booking_claim_key);
    }

    public function test_cancel_error_is_audited_but_employee_confirmation_still_releases_rebooking(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [$quote, $shipment] = $this->bookedAttempt($order, $provider);

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/cancel' => Http::response([
                'status_code' => 200,
                'message' => '0 requests success, 2 request error.',
                'data' => [
                    [
                        'status' => 'success',
                        'message' => 'Shipment Cancelled',
                        'shipment_number' => 'ES-2608-OTHER',
                    ],
                    [
                        'status' => 'error',
                        'message' => "Jane Buyer jane@example.test +60123456789 10 Customer Road\r\nBearer provider-secret-token",
                        'shipment_number' => 'ES-2608-FAILED1',
                    ],
                ],
            ]),
        ]);

        $recovery = app(EasyParcelFailedBookingRecoveryService::class)
            ->confirm($shipment, $actor, 'EasyParcel portal explicitly reports Failed.');

        self::assertSame('error', $recovery->provider_cancel_state);
        self::assertSame('EasyParcel cancellation unconfirmed.', $recovery->provider_cancel_message);
        $audit = json_encode($recovery->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Jane Buyer', $audit);
        self::assertStringNotContainsString('jane@example.test', $audit);
        self::assertStringNotContainsString('+60123456789', $audit);
        self::assertStringNotContainsString('10 Customer Road', $audit);
        self::assertStringNotContainsString('provider-secret-token', $audit);
        self::assertSame('booking_failed', $shipment->fresh()->status);
        self::assertSame('failed', $quote->fresh()->booking_state);
        self::assertNull($quote->fresh()->booking_claim_key);
        $rows = app(ShipmentDataService::class)->collection();
        $failed = $rows->firstWhere('id', (string) $shipment->id);
        $replacement = $rows->firstWhere('id', 'pending-order-'.$order->id);
        self::assertSame('error', data_get($failed, 'easyParcelRecovery.cancellationState'));
        self::assertFalse(data_get($failed, 'easyParcelRecovery.cancellationConfirmed'));
        self::assertSame('EasyParcel cancellation unconfirmed.', data_get($replacement, 'easyParcelRecovery.cancellationMessage'));
        self::assertTrue(data_get($replacement, 'easyParcelRecovery.readyToRebook'));

        $providerConfirmed = app(EasyParcelFailedBookingRecoveryService::class)->recordProviderCancellation(
            $shipment->fresh(),
            $actor,
            0,
            'Cancelled',
            now(),
        );
        self::assertSame($recovery->id, $providerConfirmed->id);
        self::assertSame('error', $providerConfirmed->provider_cancel_state);
        self::assertSame('EasyParcel cancellation unconfirmed.', $providerConfirmed->provider_cancel_message);
        self::assertSame('booking_failed', $shipment->fresh()->status);

        app(EasyParcelBookingGuard::class)->assertQuoteAllowed($order->fresh());
        $this->addToAssertionCount(1);
    }

    public function test_provider_verified_cancellation_is_system_attributed_without_an_employee_confirmer(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $order = $this->processingOrder(
            email: 'system-provider-cancel@example.test',
            number: 'JW-SYSTEM-PROVIDER-CANCEL',
        );
        [$quote, $shipment] = $this->bookedAttempt($order, $provider);
        Http::fake();

        $recovery = app(EasyParcelFailedBookingRecoveryService::class)->recordProviderCancellation(
            $shipment,
            null,
            0,
            'Cancelled',
            now(),
        );

        self::assertNull($recovery->confirmed_by);
        self::assertSame('EasyParcel reports Cancelled (code 0).', $recovery->reason);
        self::assertSame('awaiting_replacement', $recovery->state);
        self::assertSame('cancelled', $shipment->fresh()->status);
        self::assertSame('failed', $quote->fresh()->booking_state);
        Http::assertNothingSent();
    }

    public function test_provider_code_zero_snapshot_can_complete_an_existing_cancel_claim(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [, $shipment] = $this->bookedAttempt($order, $provider);
        $claimed = $this->claimedRecovery($shipment, $actor, now());
        Http::fake();

        $reconciled = app(ShipmentService::class)->reconcileEasyParcelDetails(
            $shipment,
            (string) $shipment->provider_reference,
            null,
            (string) $shipment->provider_order_reference,
            null,
            null,
            null,
            'cancelled',
            [],
        );
        $recovery = app(EasyParcelFailedBookingRecoveryService::class)->recordProviderCancellation(
            $reconciled,
            null,
            0,
            'Cancelled',
            now(),
        );

        self::assertSame($claimed->id, $recovery->id);
        self::assertSame('awaiting_replacement', $recovery->state);
        self::assertSame('success', $recovery->provider_cancel_state);
        self::assertSame('cancelled', $shipment->fresh()->status);
        self::assertNull($shipment->fresh()->tracking_number);
        Http::assertNothingSent();
    }

    public function test_active_or_uncertain_easyparcel_attempt_still_blocks_duplicate_booking(): void
    {
        $provider = $this->connectedProvider();
        $guard = app(EasyParcelBookingGuard::class);

        foreach (['pending', 'ready_to_ship', 'on_hold', 'in_transit'] as $index => $status) {
            $order = $this->processingOrder(
                email: 'active-'.$index.'@example.test',
                number: 'JW-ACTIVE-'.$index,
            );
            [, $shipment] = $this->bookedAttempt($order, $provider, $status, 'ES-2608-ACTIVE'.$index);

            try {
                $guard->assertQuoteAllowed($order);
                self::fail('An active EasyParcel '.$status.' shipment must block a new quote.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Only unbooked processing orders can be quoted.', $exception->getMessage());
            }

            self::assertSame($status, $shipment->fresh()->status);
        }

        $uncertainOrder = $this->processingOrder(
            email: 'uncertain@example.test',
            number: 'JW-UNCERTAIN',
        );
        $this->quote($uncertainOrder, $provider, 'uncertain');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only unbooked processing orders can be quoted.');
        $guard->assertQuoteAllowed($uncertainOrder);
    }

    public function test_confirmation_fails_closed_inside_an_ambient_transaction_before_cancel_http(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [, $shipment] = $this->bookedAttempt($order, $provider);
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/cancel' => Http::response([
                'status_code' => 200,
                'data' => [[
                    'status' => 'success',
                    'message' => 'Shipment Cancelled',
                    'shipment_number' => 'ES-2608-FAILED1',
                ]],
            ]),
        ]);

        try {
            DB::transaction(fn () => app(EasyParcelFailedBookingRecoveryService::class)
                ->confirm($shipment, $actor, 'This must fail before provider HTTP.'));
            self::fail('Recovery must not inherit an ambient database transaction.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'EasyParcel failed-booking recovery must start outside an existing database transaction.',
                $exception->getMessage(),
            );
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('easyparcel_shipment_recoveries', 0);
    }

    public function test_cancellation_service_fails_closed_inside_an_ambient_transaction_before_http(): void
    {
        Http::preventStrayRequests();
        $this->connectedProvider();
        Http::fake();

        try {
            DB::transaction(fn () => app(EasyParcelCancellationService::class)
                ->cancel('ES-2608-FAILED1', 'Portal failure confirmed.'));
            self::fail('Cancellation must not inherit an ambient database transaction.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'EasyParcel cancellation must start outside an existing database transaction.',
                $exception->getMessage(),
            );
        }

        Http::assertNothingSent();
    }

    public function test_on_hold_shipment_is_not_eligible_for_employee_failure_confirmation(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [, $shipment] = $this->bookedAttempt($order, $provider, 'on_hold');
        Http::fake();

        try {
            app(EasyParcelFailedBookingRecoveryService::class)
                ->confirm($shipment, $actor, 'On hold is not provider booking failure.');
            self::fail('On-hold shipment must not enter manual failed-booking recovery.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'This EasyParcel shipment is not eligible for failed-booking recovery.',
                $exception->getMessage(),
            );
        }

        self::assertSame('on_hold', $shipment->fresh()->status);
        $this->assertDatabaseCount('easyparcel_shipment_recoveries', 0);
        Http::assertNothingSent();
    }

    public function test_stale_claim_becomes_recoverable_without_another_provider_request(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [$quote, $shipment] = $this->bookedAttempt($order, $provider);
        $recovery = $this->claimedRecovery($shipment, $actor, now()->subMinutes(6));
        Http::fake();

        $result = app(EasyParcelFailedBookingRecoveryService::class)
            ->confirm($shipment, User::factory()->create(), 'A repeated reason must not replace the audit.');

        self::assertSame($recovery->id, $result->id);
        self::assertSame('awaiting_replacement', $result->state);
        self::assertSame('error', $result->provider_cancel_state);
        self::assertSame('EasyParcel cancellation unconfirmed.', $result->provider_cancel_message);
        self::assertSame('booking_failed', $shipment->fresh()->status);
        self::assertSame('failed', $quote->fresh()->booking_state);
        self::assertNull($quote->fresh()->booking_claim_key);
        app(EasyParcelBookingGuard::class)->assertQuoteAllowed($order->fresh());
        Http::assertNothingSent();
    }

    public function test_fresh_claim_remains_protected_without_another_provider_request(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [$quote, $shipment] = $this->bookedAttempt($order, $provider);
        $recovery = $this->claimedRecovery($shipment, $actor, now()->subMinutes(4));
        Http::fake();

        $result = app(EasyParcelFailedBookingRecoveryService::class)
            ->confirm($shipment, User::factory()->create(), 'A repeated reason must not replace the audit.');

        self::assertSame($recovery->id, $result->id);
        self::assertSame('cancelling', $result->state);
        self::assertSame('claimed', $result->provider_cancel_state);
        self::assertSame('pending', $shipment->fresh()->status);
        self::assertSame('booked', $quote->fresh()->booking_state);
        self::assertNotNull($quote->fresh()->booking_claim_key);
        Http::assertNothingSent();
    }

    public function test_recovery_snapshots_provider_identity_and_restricts_audit_owner_deletion(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [, $shipment] = $this->bookedAttempt($order, $provider);
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/cancel' => Http::response([
                'status_code' => 200,
                'data' => [[
                    'status' => 'success',
                    'message' => 'Shipment Cancelled',
                    'shipment_number' => 'ES-2608-FAILED1',
                ]],
            ]),
        ]);

        $recovery = app(EasyParcelFailedBookingRecoveryService::class)
            ->confirm($shipment, $actor, 'Preserve immutable audit ownership.');

        self::assertSame('ES-2608-FAILED1', $recovery->provider_shipment_number);

        try {
            $order->delete();
            self::fail('An order owning a recovery audit must not be deleted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        try {
            $shipment->delete();
            self::fail('A shipment owning a recovery audit must not be deleted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('easyparcel_shipment_recoveries', [
            'id' => $recovery->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'provider_shipment_number' => 'ES-2608-FAILED1',
        ]);
    }

    public function test_replacement_booking_uses_next_sequence_and_completes_recovery_link(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $provider = $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true]);
        $order = $this->processingOrder();
        [, $failedShipment] = $this->bookedAttempt($order, $provider);

        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://api.easyparcel.com/open_api/2026-06/shipment/cancel' => Http::response([
                    'status_code' => 200,
                    'message' => '1 request success, 0 request error.',
                    'data' => [[
                        'status' => 'success',
                        'message' => 'Shipment Cancelled',
                        'shipment_number' => 'ES-2608-FAILED1',
                    ]],
                ]),
                EasyParcelFulfillmentService::QUOTATION_URL => Http::response($this->quotationResponse()),
                EasyParcelFulfillmentService::BOOKING_URL => Http::response($this->bookingResponse()),
                default => Http::response([], 500),
            };
        });

        $recovery = app(EasyParcelFailedBookingRecoveryService::class)
            ->confirm($failedShipment, $actor, 'Portal failure confirmed before replacement.');
        $fulfillment = app(EasyParcelFulfillmentService::class);
        $replacementQuote = $fulfillment->quote(
            $order->fresh(),
            new PackageSpec(1.5, 30, 20, 10),
            $actor,
        );
        $replacement = $fulfillment->book(
            $order->fresh(),
            $replacementQuote,
            'EP-CS096',
            $actor,
        );

        self::assertSame(2, $replacement->sequence);
        self::assertSame('ES-2608-REPLACEMENT1', $replacement->provider_reference);
        self::assertSame('complete', $recovery->fresh()->state);
        self::assertSame($replacement->id, $recovery->fresh()->replacement_shipment_id);
        self::assertNotNull($recovery->fresh()->completed_at);
        self::assertSame('cancelled', $failedShipment->fresh()->status);
        $this->assertDatabaseCount('shipments', 2);
    }

    /** @return array{ShipmentQuote, Shipment} */
    private function bookedAttempt(
        Order $order,
        ShippingProvider $provider,
        string $status = 'pending',
        string $shipmentNumber = 'ES-2608-FAILED1',
    ): array {
        $quote = $this->quote($order, $provider, 'booked');
        $quote->forceFill([
            'selected_service_code' => 'EP-CS096',
            'booking_claim_key' => 'easyparcel:order:'.$order->id,
            'booking_started_at' => now()->subMinutes(20),
            'booked_at' => now()->subMinutes(19),
        ])->save();

        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'shipment_quote_id' => $quote->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_code' => 'EP-CR0AP',
            'carrier_name' => 'Aramex',
            'service_code' => 'EP-CS096',
            'service_name' => 'Aramex (Pick Up)',
            'tracking_number' => null,
            'status' => $status,
            'shipping_cost' => 1084,
            'currency' => 'MYR',
            'provider_reference' => $shipmentNumber,
            'provider_order_reference' => 'EI-'.$shipmentNumber,
            'package_snapshot' => [
                'weight_kg' => 1.5,
                'length_cm' => 30,
                'width_cm' => 20,
                'height_cm' => 10,
            ],
        ]);

        return [$quote, $shipment];
    }

    private function claimedRecovery(
        Shipment $shipment,
        User $actor,
        \DateTimeInterface $claimedAt,
    ): EasyParcelShipmentRecovery {
        return EasyParcelShipmentRecovery::query()->create([
            'shipment_id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'confirmed_by' => $actor->id,
            'state' => 'cancelling',
            'reason' => 'Original portal failure confirmation.',
            'provider_shipment_number' => $shipment->provider_reference,
            'provider_cancel_state' => 'claimed',
            'provider_cancel_claimed_at' => $claimedAt,
            'confirmed_at' => $claimedAt,
        ]);
    }

    private function quote(Order $order, ShippingProvider $provider, string $state): ShipmentQuote
    {
        return ShipmentQuote::query()->create([
            'order_id' => $order->id,
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'provider_configuration_version' => $provider->configuration_version,
            'package_snapshot' => [
                'weight_kg' => 1.5,
                'length_cm' => 30,
                'width_cm' => 20,
                'height_cm' => 10,
            ],
            'request_snapshot' => ['booking' => []],
            'response_snapshot' => ['services' => []],
            'reconciliation_reference' => 'JWEP-'.$order->id.'-'.fake()->uuid(),
            'booking_state' => $state,
            'booking_claim_key' => $state === 'uncertain' ? 'easyparcel:order:'.$order->id : null,
            'last_error' => $state === 'uncertain' ? 'provider_outcome_uncertain' : null,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    private function connectedProvider(): ShippingProvider
    {
        $provider = ShippingProvider::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'enabled' => true,
            'connection_state' => 'connected',
            'configuration_version' => 1,
            'public_metadata' => ['apiVersion' => '2026-06'],
            'last_verified_at' => now(),
        ]);

        ShippingProviderCredential::query()->create([
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'credentials' => [
                'client_id' => 'sandbox-client',
                'client_secret' => 'sandbox-secret',
                'token_type' => 'Bearer',
                'access_token' => 'sandbox-access-token',
                'access_token_expires_at' => now()->addHour()->utc()->toIso8601String(),
                'sender_profile' => [
                    'name' => 'JW Studio Dispatch',
                    'company' => 'JW Studio',
                    'phone_number_country_code' => 'MY',
                    'phone_number' => '123456789',
                    'email' => 'dispatch@example.test',
                    'address_1' => '18 Test Industry Road',
                    'postcode' => '47600',
                    'city' => 'Subang Jaya',
                    'subdivision_code' => 'MY-10',
                    'country_code' => 'MY',
                ],
            ],
        ]);

        return $provider;
    }

    private function processingOrder(
        string $email = 'easyparcel-recovery@example.test',
        string $number = 'JW-EASYPARCEL-RECOVERY-1',
    ): Order {
        $customer = Customer::query()->create([
            'email' => $email,
            'first_name' => 'Recovery',
            'last_name' => 'Buyer',
            'phone' => '+60 12-345 6789',
        ]);
        $order = Order::query()->create([
            'number' => $number,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        $order->items()->create([
            'sku' => 'EP-RECOVERY-1',
            'title' => 'Recovery Parcel Item',
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
            'package_snapshot' => [
                'weight_grams' => 1250,
                'length_cm' => 28,
                'width_cm' => 18,
                'height_cm' => 8,
            ],
        ]);
        $order->addresses()->create([
            'type' => 'shipping',
            'first_name' => 'Recovery',
            'last_name' => 'Buyer',
            'phone' => '+60 12-345 6789',
            'line_1' => '10 Test Customer Road',
            'city' => 'Shah Alam',
            'state' => 'Selangor',
            'postal_code' => '40100',
            'country' => 'MY',
        ]);

        return $order;
    }

    private function configureSender(): void
    {
        // Sender data is seeded with the encrypted provider credential below.
    }

    /** @return array<string, mixed> */
    private function quotationResponse(): array
    {
        return [
            'status_code' => 200,
            'request_id' => 'replacement-quote-request',
            'message' => '1 request success, 0 request error.',
            'data' => [[
                'status' => 'success',
                'quotations' => [[
                    'courier' => [
                        'service_id' => 'EP-CS096',
                        'service_name' => 'Aramex (Pick Up)',
                        'courier_id' => 'EP-CR0AP',
                        'courier_name' => 'Aramex',
                        'delivery_duration' => '1-3 working days',
                        'is_pickup' => true,
                        'is_dropoff' => false,
                    ],
                    'pricing' => [
                        'currency' => 'MYR',
                        'total_amount' => '10.84',
                    ],
                    'features' => [],
                ]],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function bookingResponse(): array
    {
        return [
            'status_code' => 200,
            'message' => '1 request success, 0 request error.',
            'data' => [[
                'order_details' => ['order_number' => 'EI-2608-REPLACEMENT1'],
                'shipments' => [[
                    'status' => 'success',
                    'shipment_number' => 'ES-2608-REPLACEMENT1',
                    'awb_number' => 'REPLACEMENT-AWB-2608',
                    'awb_url' => 'https://app.easyparcel.com/labels/replacement-a4',
                    'awb_urls_by_format' => [
                        'A6' => 'https://app.easyparcel.com/labels/replacement-a6',
                    ],
                    'tracking_url' => 'https://app.easyparcel.com/tracking/REPLACEMENT-AWB-2608',
                    'pricing_breakdown' => [
                        'currency_code' => 'MYR',
                        'total_paid_amount' => '10.84',
                    ],
                ]],
            ]],
        ];
    }
}
