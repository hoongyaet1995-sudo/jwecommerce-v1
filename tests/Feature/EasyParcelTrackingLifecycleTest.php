<?php

namespace Tests\Feature;

use App\Domain\Accounts\OriginalAccountPayloadFactory;
use App\Domain\Orders\OrderTrackingPayloadFactory;
use App\Jobs\SyncEasyParcelTracking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\User;
use App\Services\Shipping\EasyParcelTrackingException;
use App\Services\Shipping\EasyParcelTrackingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EasyParcelTrackingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Carbon::setTestNow('2026-08-28 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_public_easyparcel_webhook_is_not_registered_without_a_documented_signature_contract(): void
    {
        $this->postJson('/webhooks/easyparcel', [
            'topic' => 'shipment.tracking.update',
            'latest_shipment_status_code' => 5,
        ])->assertNotFound();
    }

    public function test_targeted_tracking_sync_queries_only_the_requested_easyparcel_shipment(): void
    {
        $this->connectedProvider();
        [, $firstOrder] = $this->processingOrder('JW-TARGET-1');
        [, $secondOrder] = $this->processingOrder('JW-TARGET-2');
        $first = $this->easyParcelShipment($firstOrder, 'AWB-TARGET-1');
        $this->easyParcelShipment($secondOrder, 'AWB-TARGET-2');

        Http::fake([
            EasyParcelTrackingService::TRACKING_URL => Http::response(
                $this->trackingResponse('AWB-TARGET-1', [[
                    'event_date' => '2026-08-29 12:00:00',
                    'shipment_status_code' => 4,
                    'tracking_status' => 'Parcel in transit',
                    'location' => 'KL Hub',
                ]]),
            ),
        ]);

        self::assertSame(1, app(EasyParcelTrackingService::class)->syncShipment($first));

        Http::assertSent(fn (Request $request): bool => $request->data() === ['awb_numbers' => ['AWB-TARGET-1']]
        );
        self::assertSame('in_transit', $first->fresh()->status);
        self::assertSame(
            'pending',
            Shipment::query()->where('tracking_number', 'AWB-TARGET-2')->sole()->status,
        );
    }

    public function test_authenticated_polling_records_authoritative_history_once_and_projects_both_customer_read_models(): void
    {
        $this->connectedProvider();
        [$customer, $order] = $this->processingOrder('JW-TRACK-POLL-1001');
        $shipment = $this->easyParcelShipment($order, 'AWB-POLL-1001');
        $inTransitResponse = $this->trackingResponse('AWB-POLL-1001', [
            [
                'event_date' => '2026-08-27 02:00:00',
                'shipment_status_code' => 4,
                'tracking_status' => 'Parcel moving between courier hubs',
                'location' => 'Ipoh Hub',
            ],
            [
                'event_date' => '2026-08-27 01:00:00',
                'shipment_status_code' => 3,
                'tracking_status' => 'Parcel collected by courier',
                'location' => 'Penang Hub',
            ],
        ], requestId: 'provider-raw-marker-must-not-persist');
        $deliveredResponse = $this->trackingResponse('AWB-POLL-1001', [
            [
                'event_date' => '2026-08-27 03:00:00',
                'shipment_status_code' => 5,
                'tracking_status' => 'Parcel collected by recipient',
                'location' => 'Kuala Lumpur',
            ],
            [
                'event_date' => '2026-08-27 02:00:00',
                'shipment_status_code' => 4,
                'tracking_status' => 'Parcel moving between courier hubs',
                'location' => 'Ipoh Hub',
            ],
            [
                'event_date' => '2026-08-27 01:00:00',
                'shipment_status_code' => 3,
                'tracking_status' => 'Parcel collected by courier',
                'location' => 'Penang Hub',
            ],
        ], requestId: 'provider-raw-marker-must-not-persist');

        Http::fake([
            EasyParcelTrackingService::TRACKING_URL => Http::sequence()
                ->push($inTransitResponse)
                ->push($inTransitResponse)
                ->push($deliveredResponse),
        ]);

        $service = app(EasyParcelTrackingService::class);
        self::assertSame(1, $service->syncNextBatch());
        self::assertSame(1, $service->syncNextBatch());
        self::assertSame(1, $service->syncNextBatch());

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === EasyParcelTrackingService::TRACKING_URL
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer sandbox-access-token')
                && $request->hasHeader('Accept', 'application/json')
                && $request->data() === ['awb_numbers' => ['AWB-POLL-1001']]
                && ! str_contains($request->url(), 'sandbox-access-token');
        });

        $shipment->refresh();
        $order->refresh();
        $events = $shipment->events()->reorder()->oldest('occurred_at')->oldest('id')->get();

        self::assertCount(3, $events);
        self::assertSame(['shipped', 'in_transit', 'delivered'], $events->pluck('status')->all());
        self::assertSame(['easyparcel', 'easyparcel', 'easyparcel'], $events->pluck('source')->all());
        self::assertCount(3, $events->pluck('external_event_id')->filter()->unique());
        self::assertStringNotContainsString(
            'provider-raw-marker-must-not-persist',
            json_encode($events->map->getAttributes()->all(), JSON_THROW_ON_ERROR),
        );
        self::assertSame('delivered', $shipment->status);
        self::assertSame('2026-08-27 01:00:00', $shipment->dispatched_at?->utc()->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-27 03:00:00', $shipment->delivered_at?->utc()->format('Y-m-d H:i:s'));
        self::assertNotNull($shipment->tracking_polled_at);
        self::assertNull($shipment->cancelled_at);

        self::assertSame('delivered', $order->status);
        self::assertSame('Aramex', $order->tracking_carrier);
        self::assertSame('AWB-POLL-1001', $order->tracking_number);
        self::assertSame('2026-08-27 01:00:00', $order->shipped_at?->utc()->format('Y-m-d H:i:s'));
        self::assertSame(1, $order->statusHistory()->where('to_status', 'shipped')->count());
        self::assertSame(1, $order->statusHistory()->where('to_status', 'delivered')->count());

        $trackingPayload = app(OrderTrackingPayloadFactory::class)->for($order->fresh());
        self::assertSame('delivered', data_get($trackingPayload, 'status'));
        self::assertSame('delivered', data_get($trackingPayload, 'tracking.status'));
        self::assertSame('AWB-POLL-1001', data_get($trackingPayload, 'tracking.number'));
        self::assertSame(
            ['Parcel collected by courier', 'Parcel collected by recipient'],
            collect(data_get($trackingPayload, 'timeline'))
                ->whereIn('description', ['Parcel collected by courier', 'Parcel collected by recipient'])
                ->pluck('description')
                ->values()
                ->all(),
        );

        $accountPayload = app(OriginalAccountPayloadFactory::class)->for($customer->fresh());
        self::assertSame('delivered', data_get($accountPayload, 'orders.0.status'));
        self::assertSame('Aramex', data_get($accountPayload, 'orders.0.trackingCarrier'));
        self::assertSame('AWB-POLL-1001', data_get($accountPayload, 'orders.0.trackingNumber'));
    }

    public function test_exception_and_unknown_codes_never_deliver_cancel_refund_or_restock_an_order(): void
    {
        $this->connectedProvider();
        [, $order] = $this->processingOrder('JW-TRACK-EXCEPTION');
        $shipment = $this->easyParcelShipment($order, 'AWB-EXCEPTION');
        $variant = Product::query()->create([
            'handle' => 'tracking-exception-product',
            'title' => 'Tracking Exception Product',
            'base_price' => 5000,
            'status' => 'active',
        ])->variants()->create([
            'sku' => 'TRACK-EXCEPTION-SKU',
            'title' => 'Default',
            'price' => 5000,
            'stock' => 9,
        ]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'title' => 'Tracking Exception Product',
            'unit_price' => 5000,
            'quantity' => 2,
            'line_total' => 10000,
        ]);
        $payment = $order->payments()->create([
            'gateway' => 'simulated_card',
            'status' => 'paid',
            'reference' => 'tracking-exception-payment',
        ]);

        Http::fake([
            EasyParcelTrackingService::TRACKING_URL => Http::response(
                $this->trackingResponse('AWB-EXCEPTION', [
                    [
                        'event_date' => '2026-08-27 01:00:00',
                        'shipment_status_code' => 0,
                        'tracking_status' => 'Delivered wording from an invalid cancel event',
                        'location' => null,
                    ],
                    [
                        'event_date' => '2026-08-27 02:00:00',
                        'shipment_status_code' => 8,
                        'tracking_status' => 'Delivered wording from an on-hold event',
                        'location' => 'Courier depot',
                    ],
                    [
                        'event_date' => '2026-08-27 03:00:00',
                        'shipment_status_code' => 6,
                        'tracking_status' => 'Returned to sender',
                        'location' => 'Courier depot',
                    ],
                    [
                        'event_date' => '2026-08-27 04:00:00',
                        'shipment_status_code' => 99,
                        'tracking_status' => 'Delivered wording with an undocumented code',
                        'location' => null,
                    ],
                ]),
            ),
        ]);

        app(EasyParcelTrackingService::class)->syncNextBatch();

        self::assertSame('processing', $order->fresh()->status);
        self::assertSame('returned', $shipment->fresh()->status);
        self::assertNull($shipment->fresh()->cancelled_at);
        self::assertSame(
            ['failed_delivery', 'failed_delivery', 'returned', 'provider_update'],
            $shipment->events()->reorder()->oldest('occurred_at')->oldest('id')->pluck('status')->all(),
        );
        self::assertSame(0, $order->statusHistory()->whereIn('to_status', ['delivered', 'cancelled'])->count());
        self::assertSame(0, $order->refunds()->count());
        self::assertSame(9, $variant->fresh()->stock);
        self::assertSame('paid', $payment->fresh()->status);

        $trackingPayload = app(OrderTrackingPayloadFactory::class)->for($order->fresh());
        self::assertContains(
            'Delivery Exception',
            collect(data_get($trackingPayload, 'timeline'))->pluck('title')->all(),
        );
    }

    public function test_malformed_provider_response_fails_closed_without_leaking_or_persisting_raw_data(): void
    {
        $this->connectedProvider();
        [, $order] = $this->processingOrder('JW-TRACK-MALFORMED');
        $shipment = $this->easyParcelShipment($order, 'AWB-MALFORMED');

        Http::fake([
            EasyParcelTrackingService::TRACKING_URL => Http::response([
                'status_code' => 200,
                'request_id' => 'raw-provider-secret-marker',
                'message' => 'provider raw message must not escape',
                'data' => ['results' => [[
                    'status' => 'success',
                    'awb_number' => 'AWB-MALFORMED',
                    'status_log' => [[
                        'event_date' => '2026-08-27 03:00:00',
                        'shipment_status_code' => 5,
                        'tracking_status' => ['malformed-provider-value'],
                        'location' => null,
                    ]],
                ]]],
            ]),
        ]);

        try {
            app(EasyParcelTrackingService::class)->syncNextBatch();
            self::fail('Malformed provider tracking data must fail closed.');
        } catch (EasyParcelTrackingException $exception) {
            self::assertSame('EasyParcel tracking synchronization failed.', $exception->getMessage());
            self::assertStringNotContainsString('raw-provider-secret-marker', $exception->getMessage());
            self::assertStringNotContainsString('sandbox-access-token', $exception->getMessage());
        }

        self::assertSame(0, $shipment->events()->count());
        self::assertSame('pending', $shipment->fresh()->status);
        self::assertNull($shipment->fresh()->tracking_polled_at);
        self::assertSame('processing', $order->fresh()->status);
    }

    /**
     * @param  array{
     *     event_date: string,
     *     shipment_status_code: int,
     *     tracking_status?: string,
     *     location: null
     * }  $malformedLog
     */
    #[DataProvider('missingOrEmptyTrackingStatusProvider')]
    public function test_missing_or_empty_tracking_status_fails_the_entire_batch_before_projection(
        string $case,
        array $malformedLog,
    ): void {
        $this->connectedProvider();
        [, $validOrder] = $this->processingOrder('JW-TRACK-VALID-'.strtoupper($case));
        $validShipment = $this->easyParcelShipment($validOrder, 'AWB-VALID-'.strtoupper($case));
        [, $malformedOrder] = $this->processingOrder('JW-TRACK-MALFORMED-'.strtoupper($case));
        $malformedShipment = $this->easyParcelShipment(
            $malformedOrder,
            'AWB-MALFORMED-'.strtoupper($case),
        );

        Http::fake([
            EasyParcelTrackingService::TRACKING_URL => Http::response([
                'status_code' => 200,
                'request_id' => 'tracking-status-required-'.$case,
                'message' => '2 requests success, 0 request error.',
                'data' => ['results' => [
                    [
                        'status' => 'success',
                        'awb_number' => $validShipment->tracking_number,
                        'shipment_number' => 'ES-VALID-'.$case,
                        'order_number' => 'EI-VALID-'.$case,
                        'latest_shipment_status_code' => 3,
                        'latest_tracking_status' => 'Parcel collected by courier',
                        'latest_event_date' => '2026-08-27 01:00:00',
                        'status_log' => [[
                            'event_date' => '2026-08-27 01:00:00',
                            'shipment_status_code' => 3,
                            'tracking_status' => 'Parcel collected by courier',
                            'location' => 'Penang Hub',
                        ]],
                    ],
                    [
                        'status' => 'success',
                        'awb_number' => $malformedShipment->tracking_number,
                        'shipment_number' => 'ES-MALFORMED-'.$case,
                        'order_number' => 'EI-MALFORMED-'.$case,
                        'latest_shipment_status_code' => 5,
                        'latest_tracking_status' => 'Delivered to recipient',
                        'latest_event_date' => '2026-08-27 02:00:00',
                        'status_log' => [$malformedLog],
                    ],
                ]],
            ]),
        ]);

        $exception = null;
        try {
            app(EasyParcelTrackingService::class)->syncNextBatch();
        } catch (EasyParcelTrackingException $caught) {
            $exception = $caught;
        }

        self::assertSame(0, $validShipment->events()->count());
        self::assertSame(0, $malformedShipment->events()->count());
        self::assertNull($validShipment->fresh()->tracking_polled_at);
        self::assertNull($malformedShipment->fresh()->tracking_polled_at);
        self::assertSame('pending', $validShipment->fresh()->status);
        self::assertSame('pending', $malformedShipment->fresh()->status);
        self::assertSame('processing', $validOrder->fresh()->status);
        self::assertSame('processing', $malformedOrder->fresh()->status);
        self::assertSame(
            0,
            $validOrder->statusHistory()->whereIn('to_status', ['shipped', 'delivered'])->count(),
        );
        self::assertSame(
            0,
            $malformedOrder->statusHistory()->whereIn('to_status', ['shipped', 'delivered'])->count(),
        );
        self::assertInstanceOf(EasyParcelTrackingException::class, $exception);
        self::assertSame('EasyParcel tracking synchronization failed.', $exception->getMessage());
    }

    /**
     * @return array<string, array{string, array{
     *     event_date: string,
     *     shipment_status_code: int,
     *     tracking_status?: string,
     *     location: null
     * }}>
     */
    public static function missingOrEmptyTrackingStatusProvider(): array
    {
        return [
            'missing tracking_status' => ['missing', [
                'event_date' => '2026-08-27 02:00:00',
                'shipment_status_code' => 5,
                'location' => null,
            ]],
            'empty tracking_status' => ['empty', [
                'event_date' => '2026-08-27 02:00:00',
                'shipment_status_code' => 5,
                'tracking_status' => '',
                'location' => null,
            ]],
        ];
    }

    public function test_stale_provider_response_cannot_mutate_shipments_after_configuration_changes(): void
    {
        $provider = $this->connectedProvider();
        [, $order] = $this->processingOrder('JW-TRACK-STALE-PROVIDER');
        $shipment = $this->easyParcelShipment($order, 'AWB-STALE-PROVIDER');

        Http::fake([
            EasyParcelTrackingService::TRACKING_URL => function () use ($provider) {
                $provider->forceFill([
                    'enabled' => false,
                    'connection_state' => 'unverified',
                    'configuration_version' => 2,
                ])->save();
                $credential = $provider->credential()->firstOrFail();
                $credential->forceFill(['credentials' => [
                    'client_id' => 'replacement-client',
                    'client_secret' => 'replacement-secret',
                    'token_type' => 'Bearer',
                    'access_token' => 'replacement-access-token',
                    'access_token_expires_at' => now()->addHour()->utc()->toIso8601String(),
                ]])->save();

                return Http::response($this->trackingResponse('AWB-STALE-PROVIDER', [[
                    'event_date' => '2026-08-27 03:00:00',
                    'shipment_status_code' => 5,
                    'tracking_status' => 'Delivered by stale provider account',
                    'location' => null,
                ]]));
            },
        ]);

        $this->expectException(EasyParcelTrackingException::class);
        $this->expectExceptionMessage('EasyParcel tracking synchronization failed.');

        try {
            app(EasyParcelTrackingService::class)->syncNextBatch();
        } finally {
            self::assertSame(0, $shipment->events()->count());
            self::assertSame('pending', $shipment->fresh()->status);
            self::assertNull($shipment->fresh()->tracking_polled_at);
            self::assertSame('processing', $order->fresh()->status);
        }
    }

    public function test_tracking_response_for_a_replaced_awb_cannot_mutate_the_shipment_or_order(): void
    {
        $this->connectedProvider();
        [, $order] = $this->processingOrder('JW-TRACK-STALE-AWB');
        $shipment = $this->easyParcelShipment($order, 'AWB-ORIGINAL');

        Http::fake([
            EasyParcelTrackingService::TRACKING_URL => function () use ($shipment) {
                $shipment->forceFill(['tracking_number' => 'AWB-REPLACEMENT'])->save();

                return Http::response($this->trackingResponse('AWB-ORIGINAL', [[
                    'event_date' => '2026-08-27 03:00:00',
                    'shipment_status_code' => 5,
                    'tracking_status' => 'Delivered for the original AWB',
                    'location' => null,
                ]]));
            },
        ]);

        try {
            app(EasyParcelTrackingService::class)->syncNextBatch();
            self::fail('A response for a replaced AWB must be rejected.');
        } catch (EasyParcelTrackingException $exception) {
            self::assertSame('EasyParcel tracking synchronization failed.', $exception->getMessage());
        }

        self::assertSame(0, $shipment->events()->count());
        self::assertSame('AWB-REPLACEMENT', $shipment->fresh()->tracking_number);
        self::assertSame('pending', $shipment->fresh()->status);
        self::assertNull($shipment->fresh()->tracking_polled_at);
        self::assertSame('processing', $order->fresh()->status);
    }

    public function test_polling_rotates_the_documented_maximum_batch_of_one_hundred_awbs(): void
    {
        $this->connectedProvider();
        [, $order] = $this->processingOrder('JW-TRACK-BATCH');

        foreach (range(1, 101) as $sequence) {
            $this->easyParcelShipment(
                $order,
                'AWB-BATCH-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                $sequence,
            );
        }

        $requests = [];
        Http::fake([
            EasyParcelTrackingService::TRACKING_URL => function (Request $request) use (&$requests) {
                $awbs = $request->data()['awb_numbers'] ?? [];
                $requests[] = $awbs;

                return Http::response([
                    'status_code' => 200,
                    'message' => count($awbs).' requests success, 0 request error.',
                    'data' => ['results' => collect($awbs)->map(fn (string $awb): array => [
                        'status' => 'success',
                        'awb_number' => $awb,
                        'shipment_number' => 'ES-'.$awb,
                        'order_number' => 'EI-'.$awb,
                        'latest_shipment_status_code' => 7,
                        'latest_tracking_status' => 'Schedule In Arrangement',
                        'latest_event_date' => '2026-08-28 11:00:00',
                        'status_log' => [],
                    ])->all()],
                ]);
            },
        ]);

        $service = app(EasyParcelTrackingService::class);
        self::assertSame(100, $service->syncNextBatch());
        Carbon::setTestNow('2026-08-28 12:15:00');
        self::assertSame(100, $service->syncNextBatch());

        self::assertCount(2, $requests);
        self::assertCount(100, $requests[0]);
        self::assertCount(100, $requests[1]);
        self::assertNotContains('AWB-BATCH-101', $requests[0]);
        self::assertContains('AWB-BATCH-101', $requests[1]);
        self::assertSame(101, Shipment::query()->whereNotNull('tracking_polled_at')->count());
    }

    public function test_tracking_job_has_a_bounded_unique_queue_retry_contract(): void
    {
        $job = new SyncEasyParcelTracking;

        self::assertInstanceOf(ShouldQueue::class, $job);
        self::assertInstanceOf(ShouldBeUnique::class, $job);
        self::assertSame(3, $job->tries);
        self::assertSame(30, $job->timeout);
        self::assertSame([60, 300], $job->backoff());
        self::assertSame(900, $job->uniqueFor);
        self::assertSame('easyparcel-tracking-sandbox', $job->uniqueId());

        $scheduled = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => $event->description === SyncEasyParcelTracking::class);

        self::assertNotNull($scheduled);
        self::assertSame('*/15 * * * *', $scheduled->expression);
    }

    public function test_tracking_poll_state_migration_round_trips_without_losing_shipments(): void
    {
        [, $order] = $this->processingOrder('JW-TRACK-MIGRATION');
        $shipment = $this->easyParcelShipment($order, 'AWB-MIGRATION');
        $shipment->forceFill(['tracking_polled_at' => now()])->save();
        $migrationPath = database_path('migrations/2026_08_28_000000_add_tracking_poll_state_to_shipments.php');
        self::assertFileExists($migrationPath);
        $migration = require $migrationPath;

        $migration->down();

        self::assertFalse(Schema::hasColumn('shipments', 'tracking_polled_at'));
        self::assertTrue(Shipment::query()->whereKey($shipment->id)->exists());

        $migration->up();

        self::assertTrue(Schema::hasColumn('shipments', 'tracking_polled_at'));
        self::assertTrue(Shipment::query()->whereKey($shipment->id)->exists());
        self::assertNull(Shipment::query()->findOrFail($shipment->id)->tracking_polled_at);
    }

    /** @return array{Customer, Order} */
    private function processingOrder(string $number): array
    {
        $user = User::factory()->create(['is_admin' => false]);
        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'email' => strtolower($number).'@example.test',
            'first_name' => 'Tracking',
            'last_name' => 'Customer',
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
        $order->statusHistory()->create([
            'from_status' => 'paid',
            'to_status' => 'processing',
            'note' => 'Preparing order.',
        ]);

        return [$customer, $order];
    }

    private function easyParcelShipment(Order $order, string $awb, int $sequence = 1): Shipment
    {
        return Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => $sequence,
            'source' => 'easyparcel',
            'carrier_code' => 'EP-CR0AP',
            'carrier_name' => 'Aramex',
            'service_code' => 'EP-CS096',
            'service_name' => 'Aramex (Pick Up)',
            'tracking_number' => $awb,
            'status' => 'pending',
            'shipping_cost' => 1084,
            'currency' => 'MYR',
            'provider_reference' => 'ES-'.$awb,
            'provider_order_reference' => 'EI-'.$awb,
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
            ],
        ]);

        return $provider;
    }

    /**
     * @param  list<array{event_date: string, shipment_status_code: int, tracking_status: mixed, location: ?string}>  $events
     * @return array<string, mixed>
     */
    private function trackingResponse(string $awb, array $events, string $requestId = 'tracking-request-2608'): array
    {
        $latest = collect($events)->sortByDesc('event_date')->first();

        return [
            'status_code' => 200,
            'request_id' => $requestId,
            'message' => '1 request success, 0 request error.',
            'data' => ['results' => [[
                'status' => 'success',
                'awb_number' => $awb,
                'shipment_number' => 'ES-'.$awb,
                'order_number' => 'EI-'.$awb,
                'latest_shipment_status_code' => $latest['shipment_status_code'] ?? 7,
                'latest_tracking_status' => $latest['tracking_status'] ?? 'Schedule In Arrangement',
                'latest_event_date' => $latest['event_date'] ?? '2026-08-28 11:00:00',
                'status_log' => $events,
            ]]],
        ];
    }
}
