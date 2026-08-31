<?php

namespace Tests\Feature\Admin;

use App\Jobs\ReconcileEasyParcelTask;
use App\Models\Customer;
use App\Models\EasyParcelReconciliationTask;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Shipping\EasyParcelFulfillmentService;
use App\Services\Shipping\EasyParcelOpenApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class EasyParcelFulfillmentApiTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_quote_refreshes_an_expiring_provider_session_before_the_locked_snapshot_commit(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        Config::set('services.easyparcel.client_id', 'platform-client');
        Config::set('services.easyparcel.client_secret', 'platform-secret');
        $provider = $this->connectedProvider();
        $credential = $provider->credential()->sole();
        $credential->forceFill(['credentials' => array_replace($credential->credentials, [
            'access_token' => 'expiring-quote-token',
            'access_token_expires_at' => now()->addSeconds(30)->utc()->toIso8601String(),
            'refresh_token' => 'quote-refresh-token',
            'refresh_token_expires_at' => now()->addHour()->utc()->toIso8601String(),
        ])])->save();
        $transactionLevels = [];

        Http::fake(function (Request $request) use (&$transactionLevels) {
            $transactionLevels[] = DB::transactionLevel();
            if ($request->url() === EasyParcelOpenApiClient::TOKEN_URL) {
                return Http::response([
                    'token_type' => 'Bearer',
                    'access_token' => 'refreshed-quote-token',
                    'expires_in' => 3600,
                    'refresh_token' => 'rotated-quote-refresh-token',
                    'refresh_token_expires_in' => 7200,
                ]);
            }

            self::assertSame('Bearer refreshed-quote-token', $request->header('Authorization')[0] ?? null);

            return Http::response($this->quotationResponse());
        });

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$this->processingOrder()->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertCreated();

        self::assertSame([0, 0], $transactionLevels);
        self::assertSame('refreshed-quote-token', $credential->fresh()->credentials['access_token']);
    }

    public function test_quote_uses_the_encrypted_authorized_sender_profile_instead_of_the_environment_sender_configuration(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $provider = $this->connectedProvider();
        $credential = $provider->credential()->sole();
        $credential->forceFill(['credentials' => array_replace($credential->credentials, [
            'sender_profile' => [
                'name' => 'Authorized Dispatch',
                'company' => 'Authorized Merchant',
                'phone_number_country_code' => 'MY',
                'phone_number' => '0198887777',
                'email' => 'dispatch@authorized.example',
                'address_1' => '99 Authorized Road',
                'postcode' => '47500',
                'city' => 'Subang Jaya',
                'subdivision_code' => 'MY-10',
                'country_code' => 'MY',
            ],
        ])])->save();

        Http::fake(function (Request $request) {
            self::assertSame('47500', $request['shipment'][0]['sender']['postcode']);
            self::assertSame('MY-10', $request['shipment'][0]['sender']['subdivision_code']);

            return Http::response($this->quotationResponse());
        });

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$this->processingOrder()->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertCreated();
    }

    public function test_connected_oauth_without_a_sender_profile_blocks_quotes_before_any_provider_request(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedProvider();
        $credential = $provider->credential()->sole();
        $credentials = $credential->credentials;
        unset($credentials['sender_profile']);
        $credential->forceFill(['credentials' => $credentials])->save();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$this->processingOrder()->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The EasyParcel sender profile is not configured.');

        Http::assertNothingSent();
    }

    public function test_booking_refreshes_an_expiring_provider_session_before_claiming_the_quote(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        Config::set('services.easyparcel.client_id', 'platform-client');
        Config::set('services.easyparcel.client_secret', 'platform-secret');
        $provider = $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();
        Http::fake([
            EasyParcelFulfillmentService::QUOTATION_URL => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');
        $credential = $provider->credential()->sole();
        $credential->forceFill(['credentials' => array_replace($credential->credentials, [
            'access_token' => 'expiring-booking-token',
            'access_token_expires_at' => now()->addSeconds(30)->utc()->toIso8601String(),
            'refresh_token' => 'booking-refresh-token',
            'refresh_token_expires_at' => now()->addHour()->utc()->toIso8601String(),
        ])])->save();
        $transactionLevels = [];

        Http::fake(function (Request $request) use (&$transactionLevels) {
            $transactionLevels[] = DB::transactionLevel();
            if ($request->url() === EasyParcelOpenApiClient::TOKEN_URL) {
                return Http::response([
                    'token_type' => 'Bearer',
                    'access_token' => 'refreshed-booking-token',
                    'expires_in' => 3600,
                    'refresh_token' => 'rotated-booking-refresh-token',
                    'refresh_token_expires_in' => 7200,
                ]);
            }

            self::assertSame('Bearer refreshed-booking-token', $request->header('Authorization')[0] ?? null);

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertOk();

        self::assertSame([0, 0], $transactionLevels);
        self::assertSame('refreshed-booking-token', $credential->fresh()->credentials['access_token']);
    }

    public function test_booking_same_quote_twice_creates_one_shipment_and_one_provider_booking(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
            'https://api.easyparcel.com/open_api/2026-06/shipment/submit_orders' => Http::response($this->bookingResponse()),
        ]);

        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertCreated()
            ->json('data.id');

        $payload = ['quoteId' => $quoteId, 'serviceCode' => 'EP-CS096'];

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', $payload)
            ->assertOk();
        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', $payload)
            ->assertOk();
        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS097',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('shipments', 1);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'source' => 'easyparcel',
            'provider_reference' => 'ES-2608-TEST1',
            'provider_order_reference' => 'EI-2608-TEST1',
            'tracking_number' => 'TEST-AWB-2608',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);

        Http::assertSentCount(2);
    }

    public function test_processing_order_is_projected_as_pending_awb_without_creating_a_shipment(): void
    {
        $order = $this->processingOrder();

        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'pending-order-'.$order->id)
            ->assertJsonPath('data.0.orderId', (string) $order->id)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.items.0.weightKg', 1.25)
            ->assertJsonPath('data.0.totalWeightKg', 1.25)
            ->assertJsonPath('data.0.dimensionsCm.length', 28)
            ->assertJsonMissingPath('data.0.customerPhone')
            ->assertJsonMissingPath('data.0.customerEmail')
            ->assertJsonMissingPath('data.0.recipientAddress')
            ->assertDontSee('easyparcel-buyer@example.test', false)
            ->assertDontSee('10 Test Customer Road', false)
            ->assertDontSee('+60 12-345 6789', false);

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_easyparcel_shipments_keep_their_provider_lifecycle_in_the_five_shipping_queues(): void
    {
        $fixtures = [
            ['ready', 'pending'],
            ['shipped', 'shipped'],
            ['transit', 'in_transit'],
            ['delivered', 'delivered'],
            ['failed', 'failed_delivery'],
            ['returned', 'returned'],
        ];

        foreach ($fixtures as [$suffix, $shipmentStatus]) {
            $order = $this->processingOrder(
                "queue-{$suffix}@example.test",
                'JW-QUEUE-'.strtoupper($suffix),
            );
            Shipment::query()->create([
                'order_id' => $order->id,
                'sequence' => 1,
                'source' => 'easyparcel',
                'carrier_name' => 'Aramex',
                'tracking_number' => 'AWB-QUEUE-'.strtoupper($suffix),
                'status' => $shipmentStatus,
                'currency' => 'MYR',
            ]);
        }

        $rows = collect($this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->json('data'))
            ->keyBy('trackingNumber');

        self::assertSame('ready_to_ship', data_get($rows, 'AWB-QUEUE-READY.status'));
        self::assertSame('shipped', data_get($rows, 'AWB-QUEUE-SHIPPED.status'));
        self::assertSame('in_transit', data_get($rows, 'AWB-QUEUE-TRANSIT.status'));
        self::assertSame('delivered', data_get($rows, 'AWB-QUEUE-DELIVERED.status'));
        self::assertSame('failed_delivery', data_get($rows, 'AWB-QUEUE-FAILED.status'));
        self::assertSame('returned', data_get($rows, 'AWB-QUEUE-RETURNED.status'));
    }

    public function test_shipment_list_never_falls_back_to_customer_email_as_a_display_name(): void
    {
        $order = $this->processingOrder();
        $order->customer()->update(['first_name' => null, 'last_name' => null]);
        $order->addresses()->update(['first_name' => '', 'last_name' => '']);

        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.customerName', 'Customer')
            ->assertDontSee($order->email, false);
    }

    public function test_quote_maps_documented_payload_and_keeps_package_override_out_of_item_snapshot(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();

        Http::fake(function (Request $request) {
            $this->assertSame('Bearer sandbox-access-token', $request->header('Authorization')[0] ?? null);
            $this->assertSame('47600', $request['shipment'][0]['sender']['postcode']);
            $this->assertSame('MY-10', $request['shipment'][0]['receiver']['subdivision_code']);
            $this->assertSame(1.75, $request['shipment'][0]['weight']);
            $this->assertSame(40.0, $request['shipment'][0]['length']);

            return Http::response($this->quotationResponse());
        });

        $response = $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => [
                    'weightKg' => 1.75,
                    'lengthCm' => 40,
                    'widthCm' => 22,
                    'heightCm' => 12,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.package.weightKg', 1.75)
            ->assertJsonPath('data.services.0.serviceCode', 'EP-CS096')
            ->assertJsonPath('data.services.0.amount', 10.84)
            ->assertJsonMissing(['access_token' => 'sandbox-access-token'])
            ->assertJsonMissing(['address_1' => '10 Test Customer Road']);

        $quote = ShipmentQuote::query()->findOrFail($response->json('data.id'));
        $this->assertSame(40.0, (float) $quote->package_snapshot['length_cm']);
        $this->assertSame(40.0, (float) data_get($quote->request_snapshot, 'quote.shipment.0.length'));
        $this->assertSame(28, $order->items()->sole()->package_snapshot['length_cm']);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_quote_and_booking_require_the_existing_shipping_dispatch_authorization(): void
    {
        $order = $this->processingOrder();
        $package = ['package' => $this->actualPackage()];

        $this->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', $package)
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', $package)
            ->assertForbidden();

        $this->assertDatabaseCount('shipment_quotes', 0);
    }

    public function test_disabled_provider_and_non_processing_order_are_rejected_without_http(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $provider = $this->connectedProvider();
        $provider->update(['enabled' => false]);
        $order = $this->processingOrder();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fulfillment');
        Http::assertNothingSent();

        $provider->update(['enabled' => true]);
        $order->update(['status' => 'shipped']);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_expired_foreign_and_unavailable_service_quotes_are_rejected_without_booking(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $admin = $this->superAdmin();
        $firstOrder = $this->processingOrder();
        $secondOrder = $this->processingOrder('other-buyer@example.test', 'JW-EASYPARCEL-1002');

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$firstOrder->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$secondOrder->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$firstOrder->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS097',
            ])->assertUnprocessable();

        ShipmentQuote::query()->findOrFail($quoteId)->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$firstOrder->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertUnprocessable();

        $this->assertDatabaseCount('shipments', 0);
        Http::assertSentCount(1);
    }

    public function test_uncertain_provider_outcome_is_not_automatically_retried(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $bookingAttempts = 0;
        Http::fake(function (Request $request) use (&$bookingAttempts) {
            if ($request->url() === EasyParcelFulfillmentService::BOOKING_URL) {
                $bookingAttempts++;
            }
            throw new \RuntimeException('sensitive-upstream-marker');
        });
        $request = ['quoteId' => $quoteId, 'serviceCode' => 'EP-CS096'];

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', $request)
            ->assertUnprocessable()
            ->assertJsonMissing(['message' => 'sensitive-upstream-marker'])
            ->assertDontSee('sensitive-upstream-marker', false);
        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', $request)
            ->assertUnprocessable();

        $this->assertSame(1, $bookingAttempts);
        $this->assertDatabaseHas('shipment_quotes', [
            'id' => $quoteId,
            'booking_state' => 'uncertain',
            'last_error' => 'provider_outcome_uncertain',
        ]);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_unknown_http_outcomes_retain_the_claim_for_reconciliation(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $admin = $this->superAdmin();
        $bookingStatus = null;
        Http::fake(function (Request $request) use (&$bookingStatus) {
            if ($request->url() === 'https://api.easyparcel.com/open_api/2026-06/shipment/quotations') {
                return Http::response($this->quotationResponse());
            }

            return Http::response([
                'status_code' => $bookingStatus,
                'message' => 'synthetic intermediary response',
            ], $bookingStatus ?? 500);
        });

        foreach ([201, 302, 400, 409, 422] as $index => $status) {
            $order = $this->processingOrder(
                'unknown-'.$status.'@example.test',
                'JW-EASYPARCEL-UNKNOWN-'.$index,
            );
            $quoteId = $this->actingAs($admin)
                ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                    'package' => $this->actualPackage(),
                ])->assertCreated()->json('data.id');

            $bookingStatus = $status;

            $this->actingAs($admin)
                ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                    'quoteId' => $quoteId,
                    'serviceCode' => 'EP-CS096',
                ])->assertUnprocessable();

            $quote = ShipmentQuote::query()->findOrFail($quoteId);
            $this->assertSame('uncertain', $quote->booking_state);
            $this->assertSame('provider_outcome_uncertain', $quote->last_error);
            $this->assertNotNull($quote->booking_claim_key);
        }

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_provider_disconnect_after_quote_rejects_booking_before_http(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $provider = $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $provider->update([
            'enabled' => false,
            'connection_state' => 'failed',
            'configuration_version' => $provider->configuration_version + 1,
        ]);
        $bookingCalls = 0;
        Http::fake(function () use (&$bookingCalls) {
            $bookingCalls++;

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertUnprocessable();

        $this->assertSame(0, $bookingCalls);
        $this->assertDatabaseHas('shipment_quotes', [
            'id' => $quoteId,
            'booking_state' => 'available',
            'booking_claim_key' => null,
        ]);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_invalid_persisted_booking_snapshot_releases_claim_without_http(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');
        DB::table('shipment_quotes')->where('id', $quoteId)->update([
            'request_snapshot' => json_encode(['quote' => []], JSON_THROW_ON_ERROR),
        ]);

        $bookingCalls = 0;
        Http::fake(function () use (&$bookingCalls) {
            $bookingCalls++;

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertUnprocessable();

        $this->assertSame(0, $bookingCalls);
        $this->assertDatabaseHas('shipment_quotes', [
            'id' => $quoteId,
            'booking_state' => 'failed',
            'booking_claim_key' => null,
            'last_error' => 'request_snapshot_invalid',
        ]);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_definitive_provider_rejection_releases_order_for_manual_dispatch_but_not_same_quote_retry(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $bookingCalls = 0;
        Http::fake(function () use (&$bookingCalls) {
            $bookingCalls++;

            return Http::response([
                'status_code' => 200,
                'message' => '0 request success, 1 request error.',
                'data' => [[
                    'shipments' => [[
                        'status' => 'error',
                        'errors' => ['sensitive-provider-rejection-marker'],
                    ]],
                ]],
            ]);
        });
        $booking = ['quoteId' => $quoteId, 'serviceCode' => 'EP-CS096'];

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', $booking)
            ->assertUnprocessable()
            ->assertDontSee('sensitive-provider-rejection-marker', false);
        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', $booking)
            ->assertUnprocessable();

        $this->assertSame(1, $bookingCalls);
        $this->assertDatabaseHas('shipment_quotes', [
            'id' => $quoteId,
            'booking_state' => 'failed',
            'booking_claim_key' => null,
            'last_error' => 'provider_rejected',
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
                'carrierName' => 'Manual fallback',
                'trackingNumber' => 'MANUAL-AFTER-REJECTION',
            ])->assertCreated();

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'source' => 'manual',
            'tracking_number' => 'MANUAL-AFTER-REJECTION',
        ]);
    }

    public function test_reentrant_booking_attempt_is_rejected_after_the_durable_claim(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $bookingCalls = 0;
        $reentryRejected = false;
        Http::fake(function () use ($admin, &$bookingCalls, $order, &$reentryRejected, $quoteId) {
            $bookingCalls++;
            try {
                app(EasyParcelFulfillmentService::class)->book(
                    $order->fresh(),
                    ShipmentQuote::query()->findOrFail($quoteId),
                    'EP-CS096',
                    $admin,
                );
            } catch (InvalidArgumentException) {
                $reentryRejected = true;
            }

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertOk();

        $this->assertTrue($reentryRejected);
        $this->assertSame(1, $bookingCalls);
        $this->assertDatabaseCount('shipments', 1);
    }

    public function test_legacy_dispatch_is_rejected_while_provider_booking_is_in_flight(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $legacyDispatchRejected = false;
        Http::fake(function () use ($admin, $order, &$legacyDispatchRejected) {
            try {
                app(CheckoutService::class)->dispatch(
                    $order->fresh(),
                    'Legacy courier',
                    'LEGACY-RACE-TRACKING',
                    $admin->id,
                );
            } catch (InvalidArgumentException) {
                $legacyDispatchRejected = true;
            }

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertOk();

        $this->assertTrue($legacyDispatchRejected);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processing',
            'tracking_number' => null,
        ]);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'source' => 'easyparcel']);
    }

    public function test_cancellation_is_rejected_while_provider_booking_is_in_flight(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $cancellationRejected = false;
        Http::fake(function () use ($admin, $order, &$cancellationRejected) {
            try {
                app(CheckoutService::class)->transition($order->fresh(), 'cancelled', $admin->id);
            } catch (InvalidArgumentException) {
                $cancellationRejected = true;
            }

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertOk();

        $this->assertTrue($cancellationRejected);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'source' => 'easyparcel']);
    }

    public function test_finalization_rejects_an_order_changed_outside_the_claim_guard(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        Http::fake(function () use ($order) {
            Order::query()->whereKey($order->id)->update(['status' => 'cancelled']);

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertUnprocessable();

        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseHas('shipment_quotes', [
            'id' => $quoteId,
            'booking_state' => 'uncertain',
            'last_error' => 'provider_outcome_uncertain',
        ]);
    }

    public function test_finalization_rejects_a_competing_manual_shipment(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        Http::fake(function () use ($admin, $order) {
            Shipment::query()->create([
                'order_id' => $order->id,
                'sequence' => 2,
                'source' => 'manual',
                'carrier_name' => 'Competing manual courier',
                'tracking_number' => 'COMPETING-MANUAL-TRACKING',
                'status' => 'shipped',
                'currency' => 'MYR',
                'created_by' => $admin->id,
            ]);

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertUnprocessable();

        $this->assertDatabaseCount('shipments', 1);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'source' => 'manual']);
        $this->assertDatabaseMissing('shipments', ['order_id' => $order->id, 'source' => 'easyparcel']);
        $this->assertDatabaseHas('shipment_quotes', ['id' => $quoteId, 'booking_state' => 'uncertain']);
    }

    public function test_booking_fails_closed_inside_an_ambient_transaction_before_claim_or_http(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');
        $before = ShipmentQuote::query()->findOrFail($quoteId)->getAttributes();

        $bookingCalls = 0;
        Http::fake(function () use (&$bookingCalls) {
            $bookingCalls++;

            return Http::response($this->bookingResponse());
        });
        $this->assertSame(0, DB::connection()->transactionLevel());

        DB::beginTransaction();

        try {
            $this->assertSame(1, DB::connection()->transactionLevel());
            $response = $this->actingAs($admin)
                ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                    'quoteId' => $quoteId,
                    'serviceCode' => 'EP-CS096',
                ]);
            $inside = ShipmentQuote::query()->findOrFail($quoteId)->getAttributes();
        } finally {
            DB::rollBack();
        }

        $response->assertUnprocessable();
        $this->assertSame(0, DB::connection()->transactionLevel());
        $this->assertSame(0, $bookingCalls);
        $this->assertSame($before, $inside);
        $this->assertSame($before, ShipmentQuote::query()->findOrFail($quoteId)->getAttributes());
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_booking_request_uses_documented_fields_and_response_hides_address_label_and_token(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $bookingRequest = null;
        Http::fake(function (Request $request) use (&$bookingRequest) {
            $bookingRequest = $request->data();

            return Http::response($this->bookingResponse());
        });

        $response = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])
            ->assertOk()
            ->assertJsonPath('data.fulfillmentStatus', 'ready_to_ship')
            ->assertJsonPath('data.trackingNumber', 'TEST-AWB-2608')
            ->assertJsonPath('data.hasLabel', true)
            ->assertDontSee('10 Test Customer Road', false)
            ->assertDontSee('app.easyparcel.com', false)
            ->assertDontSee('sandbox-access-token', false);

        $shipmentRequest = data_get($bookingRequest, 'shipment.0');
        $this->assertIsArray($shipmentRequest);
        $this->assertSame('EP-CS096', $shipmentRequest['service_id']);
        $this->assertMatchesRegularExpression('/^JWEP-\d+-[0-9a-f-]{36}$/', $shipmentRequest['reference']);
        $this->assertSame(1.5, $shipmentRequest['weight']);
        $this->assertSame(1.25, $shipmentRequest['item'][0]['weight']);
        $this->assertSame(28, $shipmentRequest['item'][0]['length']);
        $this->assertSame('MYR', $shipmentRequest['item'][0]['currency_code']);
        $this->assertSame('JW Studio Dispatch', $shipmentRequest['sender']['name']);
        $this->assertSame('Sandbox Buyer', $shipmentRequest['receiver']['name']);
        $this->assertFalse($shipmentRequest['feature']['sms_tracking']);

        $shipment = Shipment::query()->findOrFail($response->json('data.id'));
        $this->assertSame('https://app.easyparcel.com/labels/a6', $shipment->label_path);
        $this->assertSame(30.0, (float) $shipment->package_snapshot['length_cm']);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertArrayNotHasKey('label_path', $shipment->toArray());
        $this->assertArrayNotHasKey('tracking_url', $shipment->toArray());
        $serializedQuote = ShipmentQuote::query()->findOrFail($quoteId)->toArray();
        $this->assertArrayNotHasKey('request_snapshot', $serializedQuote);
        $this->assertArrayNotHasKey('response_snapshot', $serializedQuote);
        $this->assertArrayNotHasKey('reconciliation_reference', $serializedQuote);
        $this->assertArrayNotHasKey('booking_claim_key', $serializedQuote);

        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'ready_to_ship')
            ->assertJsonPath('data.0.orderId', (string) $order->id)
            ->assertJsonMissingPath('data.0.customerPhone')
            ->assertJsonMissingPath('data.0.customerEmail')
            ->assertJsonMissingPath('data.0.recipientAddress')
            ->assertDontSee('easyparcel-buyer@example.test', false)
            ->assertDontSee('10 Test Customer Road', false)
            ->assertDontSee('+60 12-345 6789', false);
    }

    public function test_documented_success_without_awb_remains_pending_awb(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $booking = $this->bookingResponse();
        data_set($booking, 'data.0.shipments.0.awb_number', null);
        data_set($booking, 'data.0.shipments.0.awb_url', null);
        data_set($booking, 'data.0.shipments.0.awb_urls_by_format.A6', '');
        data_set($booking, 'data.0.shipments.0.tracking_url', null);
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/submit_orders' => Http::response($booking),
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])
            ->assertOk()
            ->assertJsonPath('data.fulfillmentStatus', 'pending')
            ->assertJsonPath('data.trackingNumber', null)
            ->assertJsonPath('data.hasLabel', false);

        $this->assertDatabaseHas('shipments', [
            'id' => $response->json('data.id'),
            'source' => 'easyparcel',
            'status' => 'pending',
            'tracking_number' => null,
            'label_path' => null,
            'tracking_url' => null,
        ]);
        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.awbGeneratedAt', null);
    }

    public function test_documented_success_with_awb_but_without_label_queues_shipment_details_reconciliation(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();

        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $booking = $this->bookingResponse();
        data_set($booking, 'data.0.shipments.0.awb_url', null);
        data_set($booking, 'data.0.shipments.0.awb_urls_by_format.A6', '');
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/submit_orders' => Http::response($booking),
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])
            ->assertOk()
            ->assertJsonPath('data.fulfillmentStatus', 'ready_to_ship')
            ->assertJsonPath('data.trackingNumber', 'TEST-AWB-2608')
            ->assertJsonPath('data.hasLabel', false);

        $task = EasyParcelReconciliationTask::query()->sole();
        $this->assertSame('shipment_details', $task->type);
        $this->assertSame((int) $response->json('data.id'), $task->shipment_id);
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    public function test_cod_order_uses_only_a_quoted_cod_service_and_submits_documented_cod_fields(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $order->payments()->create([
            'gateway' => 'cod',
            'provider' => 'cod',
            'environment' => 'offline',
            'status' => 'pending',
            'amount' => 10000,
            'currency' => 'MYR',
        ]);
        $admin = $this->superAdmin();
        $codQuotation = $this->quotationResponse();
        data_set($codQuotation, 'data.0.quotations.0.features', [[
            'cod' => [
                'available' => true,
                'min_cod_amount' => 'MYR10.00',
                'max_cod_amount' => 'MYR1000.00',
            ],
        ]]);
        $quotationCalls = 0;
        $bookingCalls = 0;
        $bookingRequest = null;
        Http::fake(function (Request $request) use (&$bookingCalls, &$bookingRequest, $codQuotation, &$quotationCalls) {
            if ($request->url() === 'https://api.easyparcel.com/open_api/2026-06/shipment/quotations') {
                $quotationCalls++;

                return Http::response($quotationCalls === 1 ? $this->quotationResponse() : $codQuotation);
            }

            $bookingCalls++;
            $bookingRequest = $request->data();

            return Http::response($this->bookingResponse());
        });
        $unsupportedQuoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.services.0.supportsCod', false)
            ->json('data.id');

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $unsupportedQuoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertUnprocessable();
        $this->assertSame(0, $bookingCalls);

        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.services.0.supportsCod', true)
            ->json('data.id');

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertOk();

        $this->assertSame(1, $bookingCalls);
        $this->assertSame(100.0, data_get($bookingRequest, 'shipment.0.feature.cod.cod_amount'));
        $this->assertSame('MYR', data_get($bookingRequest, 'shipment.0.feature.cod.cod_currency'));
    }

    public function test_cod_uses_store_base_currency_when_checkout_display_currency_differs(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        StoreSetting::query()->updateOrCreate(['key' => 'currencies'], ['value' => ['data' => [
            ['id' => 'MYR', 'code' => 'MYR', 'title' => 'Malaysian Ringgit', 'symbolLeft' => 'RM ', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 1, 'isDefault' => true, 'isEnabled' => true],
            ['id' => 'USD', 'code' => 'USD', 'title' => 'US Dollar', 'symbolLeft' => '$', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.2245, 'isDefault' => false, 'isEnabled' => true],
        ]]]);
        $order = $this->processingOrder();
        $order->update(['currency' => 'USD', 'currency_exchange_rate' => 0.2245]);
        $order->payments()->create([
            'gateway' => 'cod',
            'provider' => 'cod',
            'environment' => 'offline',
            'status' => 'pending',
            'amount' => 10000,
            'currency' => 'USD',
        ]);
        $admin = $this->superAdmin();
        $quotation = $this->quotationResponse();
        data_set($quotation, 'data.0.quotations.0.features', [['cod' => ['available' => true]]]);
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($quotation),
        ]);

        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $bookingRequest = null;
        Http::fake(function (Request $request) use (&$bookingRequest) {
            $bookingRequest = $request->data();

            return Http::response($this->bookingResponse());
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertOk();

        $this->assertSame(100.0, data_get($bookingRequest, 'shipment.0.feature.cod.cod_amount'));
        $this->assertSame('MYR', data_get($bookingRequest, 'shipment.0.feature.cod.cod_currency'));
        $this->assertSame('MYR', data_get(ShipmentQuote::query()->findOrFail($quoteId)->request_snapshot, 'booking.cod.currency'));
    }

    public function test_full_length_authorized_label_url_and_reconciliation_reference_are_persisted(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $prefix = 'https://app.easyparcel.com/labels/';
        $longLabel = $prefix.str_repeat('a', 2048 - strlen($prefix));
        $booking = $this->bookingResponse();
        data_set($booking, 'data.0.shipments.0.awb_urls_by_format.A6', $longLabel);
        $bookingRequest = null;
        Http::fake(function (Request $request) use ($booking, &$bookingRequest) {
            $bookingRequest = $request->data();

            return Http::response($booking);
        });

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertOk();

        $quote = ShipmentQuote::query()->findOrFail($quoteId);
        $this->assertNotNull($quote->reconciliation_reference);
        $this->assertSame($quote->reconciliation_reference, data_get($bookingRequest, 'shipment.0.reference'));
        $this->assertSame(2048, strlen(Shipment::query()->sole()->label_path));
        $this->assertSame($longLabel, Shipment::query()->sole()->label_path);
    }

    public function test_manual_dispatch_cannot_replace_an_easyparcel_booking(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
            'https://api.easyparcel.com/open_api/2026-06/shipment/submit_orders' => Http::response($this->bookingResponse()),
        ]);

        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');
        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertOk();

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
                'carrierName' => 'Manual courier',
                'trackingNumber' => 'MANUAL-MUST-NOT-REPLACE',
            ])->assertUnprocessable();

        $this->assertDatabaseCount('shipments', 1);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'source' => 'easyparcel',
            'tracking_number' => 'TEST-AWB-2608',
        ]);
    }

    public function test_quotation_rejects_service_identifier_longer_than_the_documented_limit(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $response = $this->quotationResponse();
        data_set($response, 'data.0.quotations.0.courier.service_id', 'EP-TOO-LONG-1');
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($response),
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$this->processingOrder()->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('shipment_quotes', 0);
    }

    public function test_untrusted_label_reference_is_not_persisted_as_an_authorized_label(): void
    {
        Http::preventStrayRequests();
        $this->configureSender();
        $this->connectedProvider();
        $order = $this->processingOrder();
        $admin = $this->superAdmin();
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/quotations' => Http::response($this->quotationResponse()),
        ]);
        $quoteId = $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/quotes', [
                'package' => $this->actualPackage(),
            ])->assertCreated()->json('data.id');

        $booking = $this->bookingResponse();
        data_set($booking, 'data.0.shipments.0.awb_urls_by_format.A6', 'https://attacker.example.test/private-label');
        Http::fake([
            'https://api.easyparcel.com/open_api/2026-06/shipment/submit_orders' => Http::response($booking),
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/easyparcel/bookings', [
                'quoteId' => $quoteId,
                'serviceCode' => 'EP-CS096',
            ])->assertUnprocessable();

        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseHas('shipment_quotes', ['id' => $quoteId, 'booking_state' => 'uncertain']);
    }

    /** @return array{weightKg: float, lengthCm: float, widthCm: float, heightCm: float} */
    private function actualPackage(): array
    {
        return [
            'weightKg' => 1.5,
            'lengthCm' => 30,
            'widthCm' => 20,
            'heightCm' => 10,
        ];
    }

    private function configureSender(): void
    {
        // Sender data is seeded with the encrypted provider credential below.
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
        string $email = 'easyparcel-buyer@example.test',
        string $number = 'JW-EASYPARCEL-1001',
    ): Order {
        $customer = Customer::query()->create([
            'email' => $email,
            'first_name' => 'Sandbox',
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
            'sku' => 'EP-SNAPSHOT-1',
            'title' => 'Sandbox Parcel Item',
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
            'first_name' => 'Sandbox',
            'last_name' => 'Buyer',
            'phone' => '+60 12-345 6789',
            'line_1' => '10 Test Customer Road',
            'city' => 'Shah Alam',
            'state' => 'Selangor',
            'postal_code' => '40100',
            'country' => 'MY',
        ]);
        $order->statusHistory()->create(['to_status' => 'processing']);

        return $order;
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    /** @return array<string, mixed> */
    private function quotationResponse(): array
    {
        return [
            'status_code' => 200,
            'request_id' => 'quote-request-2608',
            'message' => '1 request success, 0 request error.',
            'data' => [[
                'status' => 'success',
                'input' => [
                    'sender' => ['postcode' => '47600', 'subdivision_code' => 'MY-10', 'country' => 'MY'],
                    'receiver' => ['postcode' => '40100', 'subdivision_code' => 'MY-10', 'country' => 'MY'],
                    'parcel_value' => 100,
                    'weight' => 1.5,
                    'width' => 20,
                    'length' => 30,
                    'height' => 10,
                ],
                'quotations' => [[
                    'courier' => [
                        'service_id' => 'EP-CS096',
                        'service_name' => 'Aramex (Pick Up)',
                        'courier_id' => 'EP-CR0AP',
                        'courier_name' => 'Aramex',
                        'courier_logo' => 'https://assets.example.test/aramex.jpg',
                        'delivery_duration' => '1-3 working days',
                        'service_tag' => [],
                        'is_pickup' => true,
                        'is_dropoff' => false,
                    ],
                    'pricing' => [
                        'currency' => 'MYR',
                        'total_amount' => '10.84',
                        'shipment_price' => '10.25',
                        'shipment_tax' => '0.59',
                        'total_features_price' => '0.00',
                        'total_features_tax' => '0.00',
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
                'order_details' => ['order_number' => 'EI-2608-TEST1', 'account_id' => 12345],
                'pricing_breakdown' => [
                    'currency_code' => 'MYR',
                    'total_order_amount' => '10.84',
                    'total_paid_amount' => '10.84',
                    'total_tax_amount' => '0.59',
                    'coupon_redeemed' => '0.00',
                ],
                'shipments' => [[
                    'status' => 'success',
                    'shipment_number' => 'ES-2608-TEST1',
                    'courier_service' => 'Aramex (Pick Up)',
                    'courier' => 'Aramex',
                    'courier_logo' => 'https://assets.example.test/aramex.jpg',
                    'awb_number' => 'TEST-AWB-2608',
                    'awb_url' => 'https://app.easyparcel.com/labels/a4',
                    'awb_urls_by_format' => [
                        'A4' => 'https://app.easyparcel.com/labels/a4',
                        'A5' => '',
                        'A6' => 'https://app.easyparcel.com/labels/a6',
                    ],
                    'tracking_url' => 'https://app.easyparcel.com/tracking/TEST-AWB-2608',
                    'collection_date' => now()->addWeekday()->toDateString(),
                    'weight' => 1.5,
                    'height' => 10,
                    'length' => 30,
                    'width' => 20,
                    'pricing_breakdown' => [
                        'currency_code' => 'MYR',
                        'total_paid_amount' => '10.84',
                        'shipment_price' => '10.25',
                        'shipment_tax_amount' => '0.59',
                        'total_features_price' => '0.00',
                        'total_features_tax_amount' => '0.00',
                        'byoc_charges' => '0.00',
                        'byoc_charges_tax' => '0.00',
                        'coupon_redeemed' => '0.00',
                    ],
                    'sender' => [
                        'point_code' => null,
                        'name' => 'JW Studio Dispatch',
                        'phone_number_country_code' => 'MY',
                        'phone_number' => '123456789',
                        'alternate_phone_number' => null,
                        'alternate_phone_number_country_code' => null,
                        'email' => 'dispatch@example.test',
                        'company_name' => 'JW Studio',
                        'address1' => '18 Test Industry Road',
                        'address2' => null,
                        'city' => 'Subang Jaya',
                        'subdivision_code' => 'MY-10',
                        'postcode' => '47600',
                        'country_code' => 'MY',
                    ],
                    'receiver' => [
                        'point_code' => null,
                        'name' => 'Sandbox Buyer',
                        'phone_number_country_code' => 'MY',
                        'phone_number' => '123456789',
                        'alternate_phone_number' => null,
                        'alternate_phone_number_country_code' => null,
                        'email' => 'easyparcel-buyer@example.test',
                        'company_name' => null,
                        'address1' => '10 Test Customer Road',
                        'address2' => null,
                        'city' => 'Shah Alam',
                        'subdivision_code' => 'MY-10',
                        'postcode' => '40100',
                        'country_code' => 'MY',
                    ],
                    'shipment_items' => [[
                        'content' => 'Sandbox Parcel Item',
                        'weight' => 1.25,
                        'height' => 8,
                        'length' => 28,
                        'width' => 18,
                        'currency_code' => 'MYR',
                        'value' => 100,
                        'quantity' => 1,
                    ]],
                    'features' => [
                        'cod' => null,
                        'insurance_purchase' => [],
                        'privacy_masking' => null,
                        'shipment_tracking_whatsapp' => null,
                        'shipment_tracking_sms' => null,
                        'shipment_tracking_email' => null,
                        'shipment_awb_branding' => null,
                    ],
                    'reference' => 'server-generated-reference',
                ]],
            ]],
        ];
    }
}
