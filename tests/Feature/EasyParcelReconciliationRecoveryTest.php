<?php

namespace Tests\Feature;

use App\Jobs\ReconcileEasyParcelTask;
use App\Models\EasyParcelReconciliationTask;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\ShippingWebhookReceipt;
use App\Models\User;
use App\Services\Shipping\EasyParcelBookingReconciliationService;
use App\Services\Shipping\EasyParcelReconciliationPublisher;
use App\Services\Shipping\EasyParcelReconciliationSweeper;
use App\Services\Shipping\EasyParcelReconciliationTaskProcessor;
use App\Services\Shipping\EasyParcelReconciliationTaskService;
use App\Services\Shipping\EasyParcelShipmentDetailsService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class EasyParcelReconciliationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-29 12:00:00');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_durable_reconciliation_outbox_schema_supports_publication_and_worker_leases(): void
    {
        self::assertTrue(Schema::hasTable('easyparcel_reconciliation_tasks'));
        foreach ([
            'task_key',
            'type',
            'shipment_quote_id',
            'shipment_id',
            'shipping_webhook_receipt_id',
            'application_reference',
            'state',
            'available_at',
            'published_at',
            'publication_attempt_count',
            'processing_attempt_count',
            'lease_token',
            'lease_expires_at',
            'last_error',
            'completed_at',
            'alerted_at',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('easyparcel_reconciliation_tasks', $column), $column);
        }
    }

    public function test_lost_submit_response_is_recovered_by_application_reference_without_resubmitting_booking(): void
    {
        $this->connectedProvider();
        [$order, $quote] = $this->claimedQuote('uncertain');
        $quote->forceFill(['selected_service_code' => null])->save();
        $task = app(EasyParcelReconciliationTaskService::class)->ensureBooking($quote);
        $task->forceFill(['state' => 'queued', 'published_at' => now()])->save();
        $details = $this->shipmentDetailsResponse($quote, 'ES-RECOVERED-01', 'AWB-RECOVERED-01');

        Http::fake([
            EasyParcelBookingReconciliationService::LIST_URL => Http::response([
                'status_code' => 200,
                'message' => 'success',
                'data' => [[
                    'shipment_number' => 'ES-RECOVERED-01',
                    'awb_number' => 'AWB-RECOVERED-01',
                ]],
                'pagination' => ['has_more' => false],
            ]),
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($details),
        ]);

        self::assertTrue(app(EasyParcelReconciliationTaskProcessor::class)->process($task->id));
        self::assertFalse(app(EasyParcelReconciliationTaskProcessor::class)->process($task->id));

        $shipment = Shipment::query()->sole();
        self::assertSame($order->id, $shipment->order_id);
        self::assertSame($quote->id, $shipment->shipment_quote_id);
        self::assertSame('ES-RECOVERED-01', $shipment->provider_reference);
        self::assertSame('AWB-RECOVERED-01', $shipment->tracking_number);
        self::assertSame('booked', $quote->fresh()->booking_state);
        self::assertSame('EP-CS096', $quote->fresh()->selected_service_code);
        self::assertSame('resolved', $task->fresh()->state);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'submit_orders'));
    }

    public function test_receipt_outbox_survives_dispatch_failure_and_stale_queue_is_republished(): void
    {
        Queue::fake();
        config(['services.easyparcel.webhook_path_token' => 'outbox-token']);
        $shipment = $this->easyParcelShipment('ES-OUTBOX-01', 'AWB-OUTBOX-01');
        $realDispatcher = app(Dispatcher::class);
        $failingDispatcher = $this->mock(Dispatcher::class);
        $failingDispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $failingDispatcher);

        $this->postJson('/webhooks/easyparcel/outbox-token', [
            'topic' => 'shipment.tracking.update',
            'shipment_number' => $shipment->provider_reference,
            'awb_number' => $shipment->tracking_number,
        ])->assertOk();

        $task = EasyParcelReconciliationTask::query()->sole();
        self::assertSame('queued', $task->state);
        self::assertSame(1, $task->publication_attempt_count);
        self::assertSame(ShippingWebhookReceipt::query()->sole()->id, $task->shipping_webhook_receipt_id);

        $this->app->instance(Dispatcher::class, $realDispatcher);
        Carbon::setTestNow(now()->addMinutes(3));
        app(EasyParcelReconciliationPublisher::class)->sweep();

        self::assertSame(2, $task->fresh()->publication_attempt_count);
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    public function test_worker_lease_prevents_overlapping_details_provider_calls(): void
    {
        $this->connectedProvider();
        $shipment = $this->easyParcelShipment('ES-LEASE-01', null);
        $task = app(EasyParcelReconciliationTaskService::class)->ensureShipmentDetails($shipment);
        $task->forceFill(['state' => 'queued', 'published_at' => now()])->save();
        $providerCalls = 0;
        $overlapClaimed = null;

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => function () use (&$overlapClaimed, &$providerCalls, $shipment, $task) {
                $providerCalls++;
                $overlapClaimed = app(EasyParcelReconciliationTaskProcessor::class)->process($task->id);

                return Http::response($this->shipmentDetailsResponseForShipment(
                    $shipment,
                    'AWB-LEASE-01',
                ));
            },
        ]);

        self::assertTrue(app(EasyParcelReconciliationTaskProcessor::class)->process($task->id));
        self::assertFalse($overlapClaimed);
        self::assertSame(1, $providerCalls);
        self::assertSame('AWB-LEASE-01', $shipment->fresh()->tracking_number);
        self::assertSame('resolved', $task->fresh()->state);
    }

    public function test_sweeper_republishes_a_processing_task_only_after_its_worker_lease_expires(): void
    {
        Queue::fake();
        $shipment = $this->easyParcelShipment('ES-LEASE-RECOVERY-01', null);
        $task = app(EasyParcelReconciliationTaskService::class)->ensureShipmentDetails($shipment);
        $task->forceFill([
            'state' => 'processing',
            'published_at' => now(),
            'lease_token' => 'abandoned-worker-lease',
            'lease_expires_at' => now()->addMinutes(2),
        ])->save();

        app(EasyParcelReconciliationSweeper::class)->sweep();

        self::assertSame('processing', $task->fresh()->state);
        Queue::assertNothingPushed();

        Carbon::setTestNow(now()->addMinutes(3));
        app(EasyParcelReconciliationSweeper::class)->sweep();

        $task->refresh();
        self::assertSame('queued', $task->state);
        self::assertNull($task->lease_token);
        self::assertNull($task->lease_expires_at);
        self::assertSame('worker_lease_expired', $task->last_error);
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    public function test_early_unmatched_webhook_is_recorrelated_through_provider_reference_without_resubmitting(): void
    {
        Queue::fake();
        config(['services.easyparcel.webhook_path_token' => 'early-receipt-token']);
        $this->connectedProvider();
        [$order, $quote] = $this->claimedQuote('uncertain');
        $details = $this->shipmentDetailsResponse($quote, 'ES-EARLY-RECEIPT-01', null);
        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($details),
        ]);

        $this->postJson('/webhooks/easyparcel/early-receipt-token', [
            'topic' => 'shipment.created',
            'shipment_number' => 'ES-EARLY-RECEIPT-01',
        ])->assertOk();

        $receipt = ShippingWebhookReceipt::query()->sole();
        self::assertSame('unmatched', $receipt->processing_state);
        self::assertNull($receipt->shipment_id);
        $task = EasyParcelReconciliationTask::query()
            ->where('type', EasyParcelReconciliationTaskService::TYPE_WEBHOOK_RECEIPT)
            ->sole();

        self::assertTrue(app(EasyParcelReconciliationTaskProcessor::class)->process($task->id));

        $shipment = Shipment::query()->sole();
        self::assertSame($order->id, $shipment->order_id);
        self::assertSame($quote->id, $shipment->shipment_quote_id);
        self::assertSame('ES-EARLY-RECEIPT-01', $shipment->provider_reference);
        self::assertSame($shipment->id, $receipt->fresh()->shipment_id);
        self::assertSame('processed', $receipt->fresh()->processing_state);
        self::assertSame('resolved', $task->fresh()->state);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'submit_orders'));
    }

    public function test_sweeper_schedules_awb_null_details_and_emits_one_bounded_age_alert(): void
    {
        Queue::fake();
        config(['services.easyparcel.awb_alert_after_minutes' => 60]);
        $shipment = $this->easyParcelShipment('ES-AWB-AGE-01', null);
        $shipment->forceFill([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ])->save();
        Log::spy();

        app(EasyParcelReconciliationSweeper::class)->sweep();
        app(EasyParcelReconciliationSweeper::class)->sweep();

        $task = EasyParcelReconciliationTask::query()
            ->where('type', EasyParcelReconciliationTaskService::TYPE_SHIPMENT_DETAILS)
            ->where('shipment_id', $shipment->id)
            ->sole();
        self::assertNotNull($task->alerted_at);
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'EasyParcel shipment is still missing an AWB.'
                && $context['shipment_id'] === $shipment->id,
        );
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    public function test_sweeper_schedules_details_when_awb_exists_but_label_is_missing(): void
    {
        Queue::fake();
        $shipment = $this->easyParcelShipment('ES-LABEL-RECOVERY-01', 'AWB-LABEL-RECOVERY-01');
        $shipment->forceFill([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ])->save();
        Log::spy();

        app(EasyParcelReconciliationSweeper::class)->sweep();

        $task = EasyParcelReconciliationTask::query()
            ->where('type', EasyParcelReconciliationTaskService::TYPE_SHIPMENT_DETAILS)
            ->where('shipment_id', $shipment->id)
            ->sole();
        self::assertSame('queued', $task->state);
        Log::shouldNotHaveReceived('warning');
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    public function test_sweeper_reopens_a_resolved_details_task_when_the_label_is_still_missing(): void
    {
        Queue::fake();
        $this->connectedProvider();
        $shipment = $this->easyParcelShipment('ES-LABEL-REOPEN-01', 'AWB-LABEL-REOPEN-01');
        $task = app(EasyParcelReconciliationTaskService::class)->ensureShipmentDetails($shipment);
        $task->forceFill([
            'state' => 'resolved',
            'available_at' => null,
            'published_at' => now()->subHour(),
            'last_error' => null,
            'completed_at' => now()->subHour(),
        ])->save();
        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response(
                $this->shipmentDetailsResponseForShipment($shipment, $shipment->tracking_number),
            ),
        ]);

        app(EasyParcelReconciliationSweeper::class)->sweep();

        $task->refresh();
        self::assertSame('queued', $task->state);
        self::assertNull($task->completed_at);
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);

        self::assertTrue(app(EasyParcelReconciliationTaskProcessor::class)->process($task->id));

        self::assertSame('resolved', $task->fresh()->state);
        self::assertNotNull($shipment->fresh()->label_path);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'submit_orders'));
    }

    public function test_details_reconciliation_retries_when_awb_exists_but_label_is_still_unavailable(): void
    {
        $this->connectedProvider();
        $shipment = $this->easyParcelShipment('ES-LABEL-PENDING-01', 'AWB-LABEL-PENDING-01');
        $task = app(EasyParcelReconciliationTaskService::class)->ensureShipmentDetails($shipment);
        $task->forceFill(['state' => 'queued', 'published_at' => now()])->save();
        $details = $this->shipmentDetailsResponseForShipment($shipment, $shipment->tracking_number);
        data_set($details, 'data.0.shipment_details.awb_url', null);

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($details),
        ]);

        self::assertTrue(app(EasyParcelReconciliationTaskProcessor::class)->process($task->id));

        $task->refresh();
        self::assertSame('retrying', $task->state);
        self::assertSame('label_not_available', $task->last_error);
        self::assertNull($shipment->fresh()->label_path);
    }

    public function test_authorized_retry_requeues_reconciliation_without_calling_submit_order(): void
    {
        Queue::fake();
        [$order, $quote] = $this->claimedQuote('uncertain');
        $task = app(EasyParcelReconciliationTaskService::class)->ensureBooking($quote);
        $task->forceFill(['state' => 'failed', 'last_error' => 'retry_exhausted'])->save();
        $plainAdmin = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);

        $this->actingAs($plainAdmin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/reconciliation')
            ->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/reconciliation')
            ->assertAccepted()
            ->assertJsonPath('data.taskId', (string) $task->id)
            ->assertJsonPath('data.state', 'queued');

        self::assertSame('queued', $task->fresh()->state);
        Http::assertNothingSent();
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    /** @return array{Order, ShipmentQuote} */
    private function claimedQuote(string $bookingState): array
    {
        $provider = ShippingProvider::query()->first() ?? $this->connectedProvider();
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $order = Order::query()->create([
            'number' => 'JW-RECOVERY-'.str()->upper(str()->random(8)),
            'email' => 'recovery@example.test',
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 1084,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 1084,
        ]);
        $quote = ShipmentQuote::query()->create([
            'order_id' => $order->id,
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'provider_configuration_version' => $provider->configuration_version,
            'package_snapshot' => ['weight_kg' => 1.25, 'length_cm' => 28, 'width_cm' => 18, 'height_cm' => 8],
            'request_snapshot' => ['booking' => ['collection_date' => '2026-08-31', 'sender' => [], 'receiver' => [], 'items' => [], 'cod' => null]],
            'response_snapshot' => ['services' => [[
                'service_code' => 'EP-CS096',
                'service_name' => 'Aramex (Pick Up)',
                'courier_code' => 'EP-CR0AP',
                'courier_name' => 'Aramex',
                'delivery_duration' => '1-3 working days',
                'is_pickup' => true,
                'is_dropoff' => false,
                'supports_cod' => false,
                'currency' => 'MYR',
                'amount_minor' => 1084,
            ]]],
            'selected_service_code' => 'EP-CS096',
            'reconciliation_reference' => 'JWEP-'.$order->id.'-'.str()->uuid(),
            'booking_state' => $bookingState,
            'booking_claim_key' => 'easyparcel:order:'.$order->id,
            'booking_started_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes(10),
            'created_by' => $actor->id,
        ]);

        return [$order, $quote];
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
                'token_type' => 'Bearer',
                'access_token' => 'sandbox-access-token',
                'access_token_expires_at' => now()->addHour()->utc()->toIso8601String(),
            ],
        ]);

        return $provider;
    }

    private function easyParcelShipment(string $providerReference, ?string $awb): Shipment
    {
        $order = Order::query()->create([
            'number' => 'JW-DETAILS-'.str()->upper(str()->random(8)),
            'email' => 'details@example.test',
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 1084,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 1084,
        ]);

        return Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_code' => 'EP-CR0AP',
            'carrier_name' => 'Aramex',
            'service_code' => 'EP-CS096',
            'service_name' => 'Aramex (Pick Up)',
            'tracking_number' => $awb,
            'status' => 'pending',
            'currency' => 'MYR',
            'shipping_cost' => 1084,
            'provider_reference' => $providerReference,
        ]);
    }

    /** @return array<string, mixed> */
    private function shipmentDetailsResponse(ShipmentQuote $quote, string $shipmentNumber, ?string $awb): array
    {
        return [
            'status_code' => 200,
            'message' => 'success',
            'data' => [[
                'shipment_number' => $shipmentNumber,
                'order_number' => 'EI-RECOVERED-01',
                'shipment_status_code' => 7,
                'shipment_status' => 'Schedule In Arrangement',
                'shipment_details' => [
                    'awb_number' => $awb,
                    'awb_url' => $awb === null ? null : 'https://app.easyparcel.com/labels/'.$awb,
                    'tracking_url' => $awb === null ? null : 'https://easyparcel.com/my/en/track/details/'.$awb,
                    'reference' => $quote->reconciliation_reference,
                ],
                'courier' => [
                    'service_id' => 'EP-CS096',
                    'courier_id' => 'EP-CR0AP',
                    'courier_name' => 'Aramex',
                    'service_types' => 'Aramex (Pick Up)',
                ],
                'pricing' => [
                    'currency_code' => 'MYR',
                    'total_price' => '10.84',
                ],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function shipmentDetailsResponseForShipment(Shipment $shipment, ?string $awb): array
    {
        return [
            'status_code' => 200,
            'message' => 'success',
            'data' => [[
                'shipment_number' => $shipment->provider_reference,
                'order_number' => 'EI-DETAILS-01',
                'shipment_status_code' => 7,
                'shipment_status' => 'Schedule In Arrangement',
                'shipment_details' => [
                    'awb_number' => $awb,
                    'awb_url' => $awb === null ? null : 'https://app.easyparcel.com/labels/'.$awb,
                    'tracking_url' => $awb === null ? null : 'https://easyparcel.com/my/en/track/details/'.$awb,
                    'reference' => null,
                ],
            ]],
        ];
    }
}
