<?php

namespace Tests\Feature\Admin;

use App\Domain\Shipping\ShippingPermission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\ShippingWebhookReceipt;
use App\Models\User;
use App\Services\Shipping\EasyParcelCancellationService;
use App\Services\Shipping\EasyParcelShipmentDetailsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class ShipmentApiTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_dispatch_staff_can_preview_and_download_an_official_easyparcel_pdf_without_exposing_its_url(): void
    {
        Http::preventStrayRequests();
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $staff->permissions()->create(['permission' => ShippingPermission::DISPATCH]);
        $shipment = $this->recoverableEasyParcelShipment(
            $this->processingOrder(),
            $this->connectedEasyParcelProvider(),
            'ES-LABEL-1001',
        );
        $shipment->forceFill([
            'tracking_number' => 'TEST-AWB-1001',
            'label_path' => 'https://app.easyparcel.com/labels/private-token-1001',
        ])->save();
        $pdf = "%PDF-1.7\nJW Studio AWB fixture\n%%EOF";
        Http::fake([
            'https://app.easyparcel.com/labels/private-token-1001' => Http::response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($pdf),
            ]),
        ]);
        Log::spy();

        $this->actingAs($staff)
            ->get('/admin/api/shipping/shipments/'.$shipment->id.'/label')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="AWB-TEST-AWB-1001.pdf"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertContent($pdf)
            ->assertDontSee('private-token-1001');

        $this->actingAs($staff)
            ->get('/admin/api/shipping/shipments/'.$shipment->id.'/label/download')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="AWB-TEST-AWB-1001.pdf"')
            ->assertContent($pdf)
            ->assertDontSee('private-token-1001');

        Log::shouldHaveReceived('info')->twice()->withArgs(function (string $message, array $context) use ($shipment, $staff): bool {
            return $message === 'EasyParcel AWB accessed'
                && $context['shipment_id'] === $shipment->id
                && $context['actor_id'] === $staff->id
                && in_array($context['action'], ['preview', 'download'], true)
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'private-token-1001');
        });
    }

    public function test_dispatch_staff_can_preview_an_official_pdf_after_one_allowlisted_easyparcel_s3_redirect(): void
    {
        Http::preventStrayRequests();
        $shipment = $this->recoverableEasyParcelShipment(
            $this->processingOrder(),
            $this->connectedEasyParcelProvider(),
            'ES-LABEL-REDIRECT-1001',
        );
        $shipment->forceFill([
            'tracking_number' => 'TEST-AWB-REDIRECT-1001',
            'label_path' => 'https://app.easyparcel.com/labels/redirect-token-1001',
        ])->save();
        $signedS3Url = 'https://s3-ap-southeast-1.amazonaws.com/easyparcel-labels/signed-token-1001.pdf';
        $pdf = "%PDF-1.7\nRedirected EasyParcel AWB fixture\n%%EOF";
        Http::fake([
            'https://app.easyparcel.com/labels/redirect-token-1001' => Http::response('', 302, [
                'Location' => $signedS3Url,
            ]),
            $signedS3Url => Http::response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($pdf),
            ]),
        ]);

        $this->actingAs($this->superAdmin())
            ->get('/admin/api/shipping/shipments/'.$shipment->id.'/label')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertContent($pdf)
            ->assertDontSee('redirect-token-1001')
            ->assertDontSee('signed-token-1001');
    }

    public function test_easyparcel_label_rejects_a_second_redirect_after_the_allowlisted_s3_hop(): void
    {
        Http::preventStrayRequests();
        $shipment = $this->recoverableEasyParcelShipment(
            $this->processingOrder(),
            $this->connectedEasyParcelProvider(),
            'ES-LABEL-REDIRECT-1002',
        );
        $shipment->forceFill([
            'tracking_number' => 'TEST-AWB-REDIRECT-1002',
            'label_path' => 'https://app.easyparcel.com/labels/redirect-token-1002',
        ])->save();
        $signedS3Url = 'https://s3-ap-southeast-1.amazonaws.com/easyparcel-labels/signed-token-1002.pdf';
        Http::fake([
            'https://app.easyparcel.com/labels/redirect-token-1002' => Http::response('', 302, [
                'Location' => $signedS3Url,
            ]),
            $signedS3Url => Http::response('', 302, [
                'Location' => 'https://cdn.example.test/second-hop-token-1002',
            ]),
        ]);

        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments/'.$shipment->id.'/label')
            ->assertStatus(502)
            ->assertJsonMissing(['redirect-token-1002', 'signed-token-1002', 'second-hop-token-1002']);
    }

    public function test_easyparcel_label_requires_dispatch_permission_and_never_enters_the_shipment_list_payload(): void
    {
        $shipment = $this->recoverableEasyParcelShipment(
            $this->processingOrder(),
            $this->connectedEasyParcelProvider(),
            'ES-LABEL-1002',
        );
        $shipment->forceFill([
            'tracking_number' => 'TEST-AWB-1002',
            'label_path' => 'https://app.easyparcel.com/labels/private-token-1002',
        ])->save();
        $viewer = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $viewer->permissions()->create(['permission' => ShippingPermission::VIEW]);
        $nonAdmin = User::factory()->create(['is_admin' => false, 'is_super_admin' => false]);
        $nonAdmin->permissions()->create(['permission' => ShippingPermission::DISPATCH]);

        $this->getJson('/admin/api/shipping/shipments/'.$shipment->id.'/label')->assertUnauthorized();
        $this->actingAs($nonAdmin)
            ->getJson('/admin/api/shipping/shipments/'.$shipment->id.'/label')
            ->assertUnauthorized();
        $this->actingAs($viewer)
            ->getJson('/admin/api/shipping/shipments/'.$shipment->id.'/label')
            ->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.hasLabel', true)
            ->assertJsonMissingPath('data.0.labelPath')
            ->assertDontSee('private-token-1002', false);
    }

    public function test_easyparcel_label_rejects_missing_manual_and_untrusted_references_without_leaking_them(): void
    {
        Log::spy();
        $admin = $this->superAdmin();
        $order = $this->processingOrder();
        $manual = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'manual',
            'carrier_name' => 'Manual Courier',
            'tracking_number' => 'MANUAL-LABEL-1003',
            'status' => 'shipped',
            'currency' => 'MYR',
            'label_path' => 'https://app.easyparcel.com/labels/manual-should-not-print',
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/shipments/'.$manual->id.'/label')
            ->assertNotFound();

        $easyParcel = $this->recoverableEasyParcelShipment(
            $this->processingOrder('label-1003@example.com', 'JW-LABEL-1003'),
            $this->connectedEasyParcelProvider(),
            'ES-LABEL-1003',
        );
        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/shipments/'.$easyParcel->id.'/label')
            ->assertNotFound();

        foreach ([
            'https://attacker.example.test/private-label-token',
            'http://app.easyparcel.com/labels/private-label-token',
            'https://user:secret@app.easyparcel.com/labels/private-label-token',
        ] as $unsafeUrl) {
            $easyParcel->forceFill(['label_path' => $unsafeUrl])->save();
            $this->actingAs($admin)
                ->getJson('/admin/api/shipping/shipments/'.$easyParcel->id.'/label')
                ->assertUnprocessable()
                ->assertJsonMissing(['private-label-token', 'attacker.example.test', 'secret']);
        }

        Log::shouldHaveReceived('warning')->times(5)->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'EasyParcel AWB access failed'
                && in_array($context['result'], ['unsupported_shipment', 'label_missing', 'invalid_reference'], true)
                && ! str_contains($encoded, 'private-label-token')
                && ! str_contains($encoded, 'attacker.example.test')
                && ! str_contains($encoded, 'secret');
        });
    }

    public function test_easyparcel_label_rejects_redirects_malformed_pdfs_and_oversized_responses(): void
    {
        Http::preventStrayRequests();
        $shipment = $this->recoverableEasyParcelShipment(
            $this->processingOrder(),
            $this->connectedEasyParcelProvider(),
            'ES-LABEL-1004',
        );
        $shipment->forceFill([
            'tracking_number' => 'TEST-AWB-1004',
            'label_path' => 'https://app.easyparcel.com/labels/provider-response-1004',
        ])->save();
        $admin = $this->superAdmin();

        Http::fakeSequence('https://app.easyparcel.com/labels/provider-response-1004')
            ->push('', 302, ['Location' => 'https://cdn.example.test/redirected-label'])
            ->push('%PDF-1.7', 200, ['Content-Type' => 'text/html'])
            ->push('not-a-pdf', 200, ['Content-Type' => 'application/pdf'])
            ->push('%PDF-1.7', 200, ['Content-Type' => 'application/pdf', 'Content-Length' => (string) (10 * 1024 * 1024 + 1)])
            ->push('%PDF-'.str_repeat('x', 10 * 1024 * 1024), 200, ['Content-Type' => 'application/pdf']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($admin)
                ->getJson('/admin/api/shipping/shipments/'.$shipment->id.'/label')
                ->assertStatus(502)
                ->assertJsonMissing(['provider-response-1004', 'cdn.example.test']);
        }
    }

    public function test_easyparcel_label_returns_a_stable_gateway_error_when_the_provider_times_out(): void
    {
        Http::preventStrayRequests();
        $shipment = $this->recoverableEasyParcelShipment(
            $this->processingOrder(),
            $this->connectedEasyParcelProvider(),
            'ES-LABEL-1005',
        );
        $shipment->forceFill([
            'tracking_number' => 'TEST-AWB-1005',
            'label_path' => 'https://app.easyparcel.com/labels/provider-timeout-1005',
        ])->save();
        Http::fake(fn () => throw new ConnectionException('provider timeout'));

        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments/'.$shipment->id.'/label')
            ->assertStatus(502)
            ->assertJsonMissing(['provider-timeout-1005']);
    }

    public function test_manual_dispatch_creates_one_shipment_and_an_append_only_event(): void
    {
        $order = $this->processingOrder();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
                'carrierName' => 'Manual courier',
                'trackingNumber' => 'MANUAL-TRACK-1001',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'tracking_number' => 'MANUAL-TRACK-1001',
            'status' => 'shipped',
            'source' => 'manual',
        ]);
        $this->assertDatabaseHas('shipment_events', [
            'status' => 'shipped',
            'source' => 'manual',
        ]);
    }

    public function test_non_permitted_admin_cannot_dispatch_a_shipment(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->postJson('/admin/api/shipping/orders/'.$this->processingOrder()->id.'/dispatch', [
                'carrierName' => 'Manual courier',
                'trackingNumber' => 'DENIED-1001',
            ])
            ->assertForbidden();
    }

    public function test_event_append_rejects_backward_transition_and_keeps_history_intact(): void
    {
        $admin = $this->superAdmin();
        $order = $this->processingOrder();

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'LIFECYCLE-1001',
        ])->assertCreated();

        $shipment = Shipment::query()->sole();

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
                'status' => 'pending',
                'description' => 'Attempted rewind',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('shipment_events', 1);
    }

    public function test_shipment_index_returns_a_real_order_backed_read_model(): void
    {
        $admin = $this->superAdmin();
        $order = $this->processingOrder();

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'READMODEL-1001',
        ])->assertCreated();

        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.orderNumber', $order->number)
            ->assertJsonPath('data.0.trackingNumber', 'READMODEL-1001');
    }

    public function test_pending_order_defaults_missing_product_package_specs_for_quote_confirmation(): void
    {
        $order = $this->processingOrder('legacy-package@example.com', 'JW-LEGACY-PACKAGE-1001');
        $order->items()->create([
            'sku' => 'LEGACY-SKU-1',
            'title' => 'Legacy product without package specification',
            'package_snapshot' => null,
            'unit_price' => 1000,
            'quantity' => 2,
            'line_total' => 2000,
        ]);

        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.orderNumber', 'JW-LEGACY-PACKAGE-1001')
            ->assertJsonPath('data.0.items.0.weightKg', 1)
            ->assertJsonPath('data.0.totalWeightKg', 2)
            ->assertJsonPath('data.0.dimensionsCm.length', 1)
            ->assertJsonPath('data.0.dimensionsCm.width', 1)
            ->assertJsonPath('data.0.dimensionsCm.height', 1);
    }

    public function test_authorized_staff_can_append_a_forward_event_and_order_delivery_is_recorded(): void
    {
        $admin = $this->superAdmin();
        $order = $this->processingOrder();

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$order->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'FORWARD-1001',
        ])->assertCreated();

        $shipment = Shipment::query()->sole();
        $this->actingAs($admin)->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
            'status' => 'in_transit',
            'description' => 'Accepted at the dispatch depot.',
            'location' => 'Petaling Jaya',
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
            'status' => 'out_for_delivery',
            'description' => 'Assigned to the local delivery route.',
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
            'status' => 'delivered',
            'description' => 'Delivered to the recipient.',
        ])->assertCreated();

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
        $this->assertDatabaseCount('shipment_events', 4);
    }

    public function test_manual_event_endpoint_cannot_mutate_easyparcel_provider_truth(): void
    {
        $order = $this->processingOrder();
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Sandbox Courier',
            'tracking_number' => 'AWB-PROVIDER-ONLY',
            'provider_reference' => 'ES-PROVIDER-ONLY',
            'status' => 'shipped',
            'currency' => 'MYR',
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/events', [
                'status' => 'in_transit',
                'description' => 'Warehouse user cannot confirm a provider milestone.',
                'location' => 'Manual console',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        self::assertSame('shipped', $shipment->fresh()->status);
        self::assertSame('processing', $order->fresh()->status);
        $this->assertDatabaseCount('shipment_events', 0);
    }

    public function test_authorized_admin_can_get_latest_easyparcel_status_from_authenticated_shipment_details(): void
    {
        $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('latest-status@example.test', 'JW-LATEST-STATUS-1001');
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Sandbox Courier',
            'tracking_number' => 'AWB-LATEST-STATUS-1001',
            'provider_reference' => 'ES-LATEST-STATUS-1001',
            'status' => 'ready_to_ship',
            'currency' => 'MYR',
        ]);

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response([
                'status_code' => 200,
                'message' => 'success',
                'data' => [[
                    'shipment_number' => 'ES-LATEST-STATUS-1001',
                    'shipment_details' => [
                        'shipment_status_code' => 5,
                        'shipment_status' => 'Delivered',
                        'awb_number' => 'AWB-LATEST-STATUS-1001',
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertOk()
            ->assertJsonPath('data.shipmentId', (string) $shipment->id)
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.changed', true);

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'source' => 'easyparcel',
            'status' => 'delivered',
        ]);
    }

    public function test_status_refresh_returns_truthful_pending_awb_diagnostics(): void
    {
        config(['services.easyparcel.awb_review_after_minutes' => 10]);
        $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('pending-awb@example.test', 'JW-PENDING-AWB-1001');
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Sandbox Courier',
            'provider_reference' => 'ES-PENDING-AWB-1001',
            'status' => 'pending',
            'currency' => 'MYR',
        ]);
        $shipment->forceFill(['created_at' => now()->subMinutes(11)])->save();

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response([
                'status_code' => 200,
                'message' => 'success',
                'data' => [[
                    'shipment_number' => 'ES-PENDING-AWB-1001',
                    'shipment_details' => [
                        'shipment_status_code' => 7,
                        'shipment_status' => 'Schedule In Arrangement',
                        'awb_number' => null,
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status');

        $response
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.providerStatusCode', 7)
            ->assertJsonPath('data.providerStatus', 'Schedule In Arrangement')
            ->assertJsonPath('data.awbAvailable', false)
            ->assertJsonPath('data.recoveryDisposition', 'needs_review')
            ->assertJsonStructure(['data' => ['checkedAt']]);
        self::assertNotEmpty($response->json('data.checkedAt'));
        Http::assertSentCount(1);
    }

    public function test_provider_cancelled_refresh_creates_durable_recovery_without_cancel_request(): void
    {
        Http::preventStrayRequests();
        $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('provider-cancelled@example.test', 'JW-PROVIDER-CANCELLED-1001');
        $order->items()->create([
            'sku' => 'PROVIDER-CANCELLED-SKU',
            'title' => 'Fresh replacement package source',
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
            'package_snapshot' => [
                'weight_grams' => 1500,
                'length_cm' => 31,
                'width_cm' => 21,
                'height_cm' => 11,
            ],
        ]);
        $shipment = $this->recoverableEasyParcelShipment(
            $order,
            ShippingProvider::query()->where('provider', 'easyparcel')->sole(),
            'ES-PROVIDER-CANCELLED-1001',
        );
        $response = [
            'status_code' => 200,
            'message' => 'success',
            'data' => [[
                'shipment_number' => 'ES-PROVIDER-CANCELLED-1001',
                'shipment_details' => [
                    'shipment_status_code' => 0,
                    'shipment_status' => 'Cancelled',
                    'awb_number' => null,
                ],
            ]],
        ];
        Http::fakeSequence(EasyParcelShipmentDetailsService::DETAILS_URL)
            ->push($response)
            ->push($response);
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $staff->permissions()->create(['permission' => ShippingPermission::DISPATCH]);

        $first = $this->actingAs($staff)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.recoveryDisposition', 'provider_terminal')
            ->assertJsonPath('data.recovery.state', 'awaiting_replacement')
            ->assertJsonPath('data.recovery.cancellationState', 'success')
            ->assertJsonPath('data.recovery.cancellationConfirmed', true)
            ->assertJsonPath('data.recovery.readyToRebook', true);
        $second = $this->actingAs($staff)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertOk()
            ->assertJsonPath('data.recovery.recoveryId', $first->json('data.recovery.recoveryId'));

        $this->assertDatabaseCount('easyparcel_shipment_recoveries', 1);
        $this->assertDatabaseHas('easyparcel_shipment_recoveries', [
            'shipment_id' => $shipment->id,
            'confirmed_by' => null,
            'state' => 'awaiting_replacement',
            'provider_cancel_state' => 'success',
        ]);
        self::assertSame('cancelled', $shipment->fresh()->status);
        self::assertNotNull($shipment->fresh()->cancelled_at);
        Http::assertSentCount(2);

        $rows = $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->json('data');
        $old = collect($rows)->firstWhere('id', (string) $shipment->id);
        $replacement = collect($rows)->firstWhere('id', 'pending-order-'.$order->id);
        self::assertSame('awaiting_replacement', data_get($old, 'easyParcelRecovery.state'));
        self::assertSame('awaiting_replacement', data_get($replacement, 'easyParcelRecovery.state'));
        self::assertSame(1.5, data_get($replacement, 'totalWeightKg'));
        self::assertSame(31, data_get($replacement, 'dimensionsCm.length'));
        self::assertSame(21, data_get($replacement, 'dimensionsCm.width'));
        self::assertSame(11, data_get($replacement, 'dimensionsCm.height'));
        self::assertSame($first->json('data.recovery.recoveryId'), data_get($replacement, 'easyParcelRecovery.recoveryId'));
        self::assertSame($first->json('data.recovery.recoveryId'), $second->json('data.recovery.recoveryId'));
    }

    public function test_provider_on_hold_refresh_is_an_exception_without_recovery_or_rebook(): void
    {
        config(['services.easyparcel.awb_review_after_minutes' => 10]);
        $provider = $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('provider-on-hold@example.test', 'JW-PROVIDER-ON-HOLD-1001');
        $shipment = $this->recoverableEasyParcelShipment($order, $provider, 'ES-PROVIDER-ON-HOLD-1001');
        $shipment->forceFill(['created_at' => now()->subMinutes(60)])->save();
        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response([
                'status_code' => 200,
                'message' => 'success',
                'data' => [[
                    'shipment_number' => 'ES-PROVIDER-ON-HOLD-1001',
                    'shipment_details' => [
                        'shipment_status_code' => 8,
                        'shipment_status' => 'On Hold',
                        'awb_number' => null,
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'on_hold')
            ->assertJsonPath('data.providerStatusCode', 8)
            ->assertJsonPath('data.providerStatus', 'On Hold')
            ->assertJsonPath('data.awbAvailable', false)
            ->assertJsonPath('data.recoveryDisposition', 'none')
            ->assertJsonPath('data.recovery', null);

        self::assertSame('on_hold', $shipment->fresh()->status);
        $this->assertDatabaseCount('easyparcel_shipment_recoveries', 0);
        Http::assertSentCount(1);
    }

    public function test_interactive_on_hold_refresh_accepts_provider_code_zero_and_records_system_recovery(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('on-hold-cancelled@example.test', 'JW-ON-HOLD-CANCELLED-1001');
        $shipment = $this->recoverableEasyParcelShipment($order, $provider, 'ES-ON-HOLD-CANCELLED-1001');
        $shipment->forceFill(['status' => 'on_hold'])->save();
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $staff->permissions()->create(['permission' => ShippingPermission::DISPATCH]);
        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response([
                'status_code' => 200,
                'message' => 'success',
                'data' => [[
                    'shipment_number' => 'ES-ON-HOLD-CANCELLED-1001',
                    'shipment_details' => [
                        'shipment_status_code' => 0,
                        'shipment_status' => 'Cancelled',
                        'awb_number' => null,
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($staff)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.providerStatusCode', 0)
            ->assertJsonPath('data.recoveryDisposition', 'provider_terminal')
            ->assertJsonPath('data.recovery.state', 'awaiting_replacement')
            ->assertJsonPath('data.recovery.cancellationConfirmed', true);

        $this->assertDatabaseHas('easyparcel_shipment_recoveries', [
            'shipment_id' => $shipment->id,
            'confirmed_by' => null,
            'state' => 'awaiting_replacement',
        ]);
        self::assertSame('cancelled', $shipment->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_dispatch_staff_can_confirm_provider_failure_and_receives_rebook_instructions(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('recovery-dispatch@example.test', 'JW-RECOVERY-DISPATCH-1001');
        $shipment = $this->recoverableEasyParcelShipment($order, $provider, 'ES-RECOVERY-DISPATCH-1001');
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $staff->permissions()->create(['permission' => ShippingPermission::DISPATCH]);
        Http::fake([
            EasyParcelCancellationService::CANCELLATION_URL => Http::response([
                'status_code' => 200,
                'message' => '1 request success, 0 request error.',
                'data' => [[
                    'status' => 'success',
                    'message' => 'Shipment Cancelled',
                    'shipment_number' => 'ES-RECOVERY-DISPATCH-1001',
                ]],
            ]),
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/recovery', [
                'reason' => 'EasyParcel portal explicitly reports this booking as failed.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.shipmentId', (string) $shipment->id)
            ->assertJsonPath('data.replacementOrderId', (string) $order->id)
            ->assertJsonPath('data.shipmentStatus', 'cancelled')
            ->assertJsonPath('data.cancellationState', 'success')
            ->assertJsonPath('data.cancellationMessage', 'EasyParcel cancellation confirmed.')
            ->assertJsonPath('data.cancellationConfirmed', true)
            ->assertJsonStructure(['data' => ['recoveryId']]);
        self::assertNotEmpty($response->json('data.recoveryId'));
        $this->assertDatabaseHas('easyparcel_shipment_recoveries', [
            'shipment_id' => $shipment->id,
            'order_id' => $order->id,
            'confirmed_by' => $staff->id,
            'reason' => 'EasyParcel portal explicitly reports this booking as failed.',
            'provider_cancel_state' => 'success',
        ]);
        $rows = $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/shipments')
            ->assertOk()
            ->json('data');
        self::assertTrue(collect($rows)->contains(fn (array $row): bool => $row['id'] === (string) $shipment->id && $row['status'] === 'cancelled'));
        self::assertTrue(collect($rows)->contains(fn (array $row): bool => $row['id'] === 'pending-order-'.$order->id && $row['orderId'] === (string) $order->id));
        Http::assertSentCount(1);
    }

    public function test_view_only_staff_cannot_confirm_provider_failure(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('recovery-viewer@example.test', 'JW-RECOVERY-VIEWER-1001');
        $shipment = $this->recoverableEasyParcelShipment($order, $provider, 'ES-RECOVERY-VIEWER-1001');
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $staff->permissions()->create(['permission' => ShippingPermission::VIEW]);
        Http::fake();

        $this->actingAs($staff)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/recovery', [
                'reason' => 'EasyParcel portal explicitly reports this booking as failed.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('easyparcel_shipment_recoveries', 0);
        self::assertSame('pending', $shipment->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_provider_failure_confirmation_requires_a_bounded_reason(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('recovery-reason@example.test', 'JW-RECOVERY-REASON-1001');
        $shipment = $this->recoverableEasyParcelShipment($order, $provider, 'ES-RECOVERY-REASON-1001');
        Http::fake();

        foreach (['no', str_repeat('x', 501)] as $reason) {
            $this->actingAs($this->superAdmin())
                ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/recovery', compact('reason'))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('reason');
        }

        $this->assertDatabaseCount('easyparcel_shipment_recoveries', 0);
        Http::assertNothingSent();
    }

    public function test_latest_easyparcel_status_rejects_non_easyparcel_shipments_without_provider_request(): void
    {
        Http::fake();
        $order = $this->processingOrder('manual-status@example.test', 'JW-MANUAL-STATUS-1001');
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'manual',
            'carrier_name' => 'Manual Courier',
            'tracking_number' => 'MANUAL-LATEST-STATUS-1001',
            'status' => 'shipped',
            'currency' => 'MYR',
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipment');

        Http::assertNothingSent();
    }

    public function test_latest_easyparcel_status_is_idempotent_when_provider_details_are_unchanged(): void
    {
        $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('idempotent-status@example.test', 'JW-IDEMPOTENT-STATUS-1001');
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Sandbox Courier',
            'tracking_number' => 'AWB-IDEMPOTENT-STATUS-1001',
            'provider_reference' => 'ES-IDEMPOTENT-STATUS-1001',
            'status' => 'ready_to_ship',
            'currency' => 'MYR',
        ]);
        $response = [
            'status_code' => 200,
            'message' => 'success',
            'data' => [[
                'shipment_number' => 'ES-IDEMPOTENT-STATUS-1001',
                'shipment_details' => [
                    'shipment_status_code' => 5,
                    'shipment_status' => 'Delivered',
                    'awb_number' => 'AWB-IDEMPOTENT-STATUS-1001',
                ],
            ]],
        ];
        Http::fakeSequence(EasyParcelShipmentDetailsService::DETAILS_URL)
            ->push($response)
            ->push($response);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertDatabaseCount('shipment_events', 1);
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => 'delivered']);
    }

    public function test_latest_easyparcel_status_shields_provider_failures(): void
    {
        $this->connectedEasyParcelProvider();
        $order = $this->processingOrder('provider-failure@example.test', 'JW-PROVIDER-FAILURE-1001');
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Sandbox Courier',
            'tracking_number' => 'AWB-PROVIDER-FAILURE-1001',
            'provider_reference' => 'ES-PROVIDER-FAILURE-1001',
            'status' => 'ready_to_ship',
            'currency' => 'MYR',
        ]);
        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response([
                'message' => 'provider-sensitive-failure',
            ], 500),
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/shipping/shipments/'.$shipment->id.'/easyparcel/status')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'EasyParcel status is temporarily unavailable. Please try again.',
            ]);

        self::assertSame('ready_to_ship', $shipment->fresh()->status);
        $this->assertDatabaseCount('shipment_events', 0);
    }

    public function test_duplicate_tracking_number_is_rejected_for_a_different_order(): void
    {
        $admin = $this->superAdmin();
        $first = $this->processingOrder();
        $second = $this->processingOrder('shipment-second@example.com', 'JW-SHIPMENT-1002');

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$first->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'UNIQUE-1001',
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/api/shipping/orders/'.$second->id.'/dispatch', [
            'carrierName' => 'Manual courier',
            'trackingNumber' => 'UNIQUE-1001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('trackingNumber');
    }

    public function test_authorized_shipping_viewer_sees_only_safe_webhook_receipt_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $admin->permissions()->create(['permission' => ShippingPermission::VIEW]);
        $order = $this->processingOrder();
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel',
            'tracking_number' => 'AWB-SAFE-01',
            'status' => 'in_transit',
        ]);
        $receivedAt = now()->startOfSecond();
        $processedAt = $receivedAt->copy()->addSecond();
        $receipt = ShippingWebhookReceipt::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'topic' => 'shipment.tracking.update',
            'processing_state' => 'processed',
            'payload_hash' => str_repeat('a', 64),
            'shipment_id' => $shipment->id,
            'correlation_snapshot' => [
                'awb_number' => 'AWB-SAFE-01',
                'receiver_email' => 'must-not-serialize@example.test',
                'receiver_phone' => '+60111111111',
                'receiver_address' => 'Must not serialize',
                'raw_body' => 'must-not-serialize',
            ],
            'attempt_count' => 2,
            'last_error' => null,
            'received_at' => $receivedAt,
            'processed_at' => $processedAt,
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/webhook-receipts')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => (string) $receipt->id,
                    'topic' => 'shipment.tracking.update',
                    'state' => 'processed',
                    'shipmentId' => (string) $shipment->id,
                    'orderNumber' => $order->number,
                    'trackingNumber' => 'AWB-SAFE-01',
                    'receivedAt' => $receivedAt->toIso8601String(),
                    'processedAt' => $processedAt->toIso8601String(),
                    'attemptCount' => 2,
                    'errorCode' => null,
                ]],
            ]);
    }

    public function test_webhook_receipt_read_model_returns_only_the_newest_one_hundred_rows(): void
    {
        $admin = $this->superAdmin();

        foreach (range(1, 101) as $index) {
            ShippingWebhookReceipt::query()->create([
                'provider' => 'easyparcel',
                'environment' => 'sandbox',
                'topic' => 'shipment.tracking.update',
                'processing_state' => 'received',
                'payload_hash' => hash('sha256', 'receipt-'.$index),
                'correlation_snapshot' => ['awb_number' => 'AWB-'.$index],
                'received_at' => now()->addSeconds($index),
            ]);
        }
        ShippingWebhookReceipt::query()->create([
            'provider' => 'another-provider',
            'environment' => 'sandbox',
            'topic' => 'shipment.tracking.update',
            'processing_state' => 'received',
            'payload_hash' => hash('sha256', 'other-provider-receipt'),
            'correlation_snapshot' => ['awb_number' => 'AWB-OTHER-PROVIDER'],
            'received_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/api/shipping/webhook-receipts')
            ->assertOk()
            ->assertJsonCount(100, 'data');

        $this->assertSame('AWB-101', $response->json('data.0.trackingNumber'));
        $this->assertSame('AWB-2', $response->json('data.99.trackingNumber'));
    }

    public function test_webhook_receipt_read_model_requires_admin_authentication_and_shipping_view_permission(): void
    {
        $this->getJson('/admin/api/shipping/webhook-receipts')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->getJson('/admin/api/shipping/webhook-receipts')
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->getJson('/admin/api/shipping/webhook-receipts')
            ->assertForbidden();
    }

    public function test_admin_bootstrap_exposes_stable_webhook_callback_url_without_the_endpoint_token(): void
    {
        config(['services.easyparcel.webhook_path_token' => 'must-not-serialize-webhook-token']);

        $response = $this->actingAs($this->superAdmin())
            ->get('/admin/shipping')
            ->assertOk();

        preg_match('/window\.__JW_ADMIN__\s*=\s*(\{.*\});/sU', $response->getContent(), $matches);
        $bootstrap = json_decode($matches[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(route('admin.api.shipping.webhook-receipts.index'), $bootstrap['easyParcelWebhookReceiptsUrl'] ?? null);
        $this->assertSame(url('/webhooks/easyparcel'), $bootstrap['easyParcelWebhookCallbackUrl'] ?? null);
        $this->assertTrue($bootstrap['easyParcelWebhookConfigured'] ?? false);
        $this->assertStringNotContainsString('must-not-serialize-webhook-token', $response->getContent());
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    private function connectedEasyParcelProvider(): ShippingProvider
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

    private function processingOrder(string $email = 'shipment-buyer@example.com', string $number = 'JW-SHIPMENT-1001'): Order
    {
        $customer = Customer::query()->create([
            'email' => $email,
            'first_name' => 'Shipment',
            'last_name' => 'Buyer',
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
        $order->statusHistory()->create(['to_status' => 'processing']);

        return $order;
    }

    private function recoverableEasyParcelShipment(
        Order $order,
        ShippingProvider $provider,
        string $shipmentNumber,
    ): Shipment {
        $quote = ShipmentQuote::query()->create([
            'order_id' => $order->id,
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'provider_configuration_version' => $provider->configuration_version,
            'package_snapshot' => ['weight_kg' => 1, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10],
            'request_snapshot' => ['booking' => []],
            'response_snapshot' => ['services' => []],
            'reconciliation_reference' => 'JWEP-'.$order->id.'-'.$shipmentNumber,
            'booking_state' => 'booked',
            'booking_claim_key' => 'easyparcel:order:'.$order->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        return Shipment::query()->create([
            'order_id' => $order->id,
            'shipment_quote_id' => $quote->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_code' => 'EP-CR0AP',
            'carrier_name' => 'Aramex',
            'service_code' => 'EP-CS096',
            'service_name' => 'Aramex (Pick Up)',
            'status' => 'pending',
            'currency' => 'MYR',
            'provider_reference' => $shipmentNumber,
        ]);
    }
}
