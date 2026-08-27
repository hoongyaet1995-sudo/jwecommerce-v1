# Shipment and Event Operational Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Make manual order dispatch, shipment lifecycle events and Admin Shipping operational through Laravel/MySQL while retaining accepted Google AI Studio UI structures.

**Architecture:** Add a canonical shipment aggregate beside immutable checkout-rate snapshots on orders. ShipmentService owns manual dispatch, append-only events, status validation and compatibility projection to order tracking fields; the Shipping Dashboard reads Laravel data rather than fixture parcels/localStorage.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, React/TypeScript/Vite Admin, PHPUnit Feature tests, Chrome acceptance.

**Spec:** docs/superpowers/specs/2026-08-25-shipment-event-operational-closure-design.md

## Global Constraints

- Preserve accepted Google AI Studio Shipping/Admin DOM hierarchy and Tailwind classes; inject data binding only.
- MySQL is the only operational truth; no localStorage or fixture array may own shipment/event behavior.
- Do not call EasyParcel, store credentials, generate provider labels or claim a connected courier in P0.2.
- orders.shipping_snapshot remains immutable checkout history; shipment events are append-only operations history.
- Existing /admin/api/orders/{order}/tracking must call the same service as new shipment APIs.
- Do not stage unrelated, already-uncommitted checkout/payment/shipping-rate changes.

---

## File structure

| File | Responsibility |
| --- | --- |
| database/migrations/2026_08_25_000300_create_shipments_and_events_tables.php | Shipment/event schema and constraints. |
| app/Models/Shipment.php; app/Models/ShipmentEvent.php | Eloquent shipment aggregate and events. |
| app/Domain/Shipping/ShippingPermission.php | shipping.view, shipping.dispatch, shipping.update_status. |
| app/Services/ShipmentService.php | Transactional dispatch, lifecycle validation, audit and order projection. |
| app/Services/Admin/ShipmentDataService.php | Existing Admin ShipmentParcel view-model adapter. |
| app/Http/Controllers/Admin/ShipmentApiController.php | Protected shipment list, dispatch and event APIs. |
| app/Http/Controllers/Admin/OrderApiController.php; app/Services/Admin/OrderOperationsService.php | Compatibility delegation to ShipmentService. |
| app/Http/Controllers/Admin/AdminAppController.php; routes/web.php | Bootstrap endpoint/permissions and protected routes. |
| resources/admin/src/integration/shipmentData.ts | Browser API client. |
| resources/admin/src/types/shipping.ts; resources/admin/src/context/AdminContext.tsx | Persisted shipment state instead of localStorage fixtures. |
| resources/admin/src/components/shipping/*.tsx | Existing operational tab bindings only. |
| tests/Feature/Admin/ShipmentApiTest.php | Persistence, authorization, lifecycle and projection tests. |

### Task 1: Create canonical shipment and event records

**Files:**
- Create: database/migrations/2026_08_25_000300_create_shipments_and_events_tables.php
- Create: app/Models/Shipment.php
- Create: app/Models/ShipmentEvent.php
- Test: tests/Feature/Admin/ShipmentApiTest.php

**Interfaces:** Produces Shipment::events(): HasMany and ShipmentEvent::shipment(): BelongsTo. A shipment belongs to an order and has a per-order sequence.

- [ ] **Step 1: Write the failing persistence test**

~~~
public function test_manual_dispatch_creates_one_shipment_and_an_append_only_event(): void
{
    $order = $this->processingOrder();
    $this->actingAs($this->superAdmin())
        ->postJson("/admin/api/shipping/orders/{$order->id}/dispatch", [
            'carrierName' => 'Manual courier', 'trackingNumber' => 'MANUAL-TRACK-1001',
        ])->assertCreated();
    $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'tracking_number' => 'MANUAL-TRACK-1001', 'status' => 'shipped', 'source' => 'manual']);
    $this->assertDatabaseHas('shipment_events', ['status' => 'shipped', 'source' => 'manual']);
}
~~~

- [ ] **Step 2: Run the test and confirm it fails**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php --filter=manual_dispatch_creates_one_shipment

Expected: FAIL because the route/tables/models do not exist.

- [ ] **Step 3: Write the migration and models**

~~~
Schema::create('shipments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('sequence')->default(1);
    $table->string('source', 24)->default('manual');
    $table->string('carrier_code', 80)->nullable();
    $table->string('carrier_name', 120);
    $table->string('service_code', 120)->nullable();
    $table->string('service_name', 160)->nullable();
    $table->string('tracking_number', 160)->nullable()->unique();
    $table->string('status', 32)->default('pending');
    $table->unsignedInteger('shipping_cost')->nullable();
    $table->string('currency', 3)->default('MYR');
    $table->string('label_path')->nullable();
    $table->string('provider_reference', 160)->nullable();
    $table->timestamp('dispatched_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->unique(['order_id', 'sequence']);
    $table->index(['status', 'created_at']);
});
~~~

Create shipment_events with shipment_id, status, occurred_at, nullable location, description, source, nullable external_event_id and created_by; add unique [shipment_id, external_event_id]. Add timestamp casts and relationships.

- [ ] **Step 4: Migrate and rerun the test**

Run: C:\xampp\php\php.exe artisan migrate
Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php --filter=manual_dispatch_creates_one_shipment

Expected: PASS with exactly one shipment and one event.

- [ ] **Step 5: Commit only Task 1 files**

Run: git add database/migrations/2026_08_25_000300_create_shipments_and_events_tables.php app/Models/Shipment.php app/Models/ShipmentEvent.php tests/Feature/Admin/ShipmentApiTest.php
Run: git commit -m "feat: add shipment lifecycle records"

### Task 2: Implement authorization and transactional lifecycle rules

**Files:**
- Create: app/Domain/Shipping/ShippingPermission.php
- Create: app/Services/ShipmentService.php
- Modify: app/Models/User.php
- Test: tests/Feature/Admin/ShipmentApiTest.php

**Interfaces:** Produces ShipmentService::dispatchManually(Order $order, User $actor, string $carrierName, string $trackingNumber): Shipment and ShipmentService::appendManualEvent(Shipment $shipment, User $actor, string $status, string $description, ?string $location): ShipmentEvent.

- [ ] **Step 1: Write failing authorization and transition tests**

~~~
public function test_non_permitted_admin_cannot_dispatch_a_shipment(): void
{
    $this->actingAs($this->plainAdmin())
        ->postJson("/admin/api/shipping/orders/{$this->processingOrder()->id}/dispatch", ['carrierName' => 'Manual courier', 'trackingNumber' => 'DENIED-1001'])
        ->assertForbidden();
}

public function test_event_append_rejects_backward_transition_and_keeps_history_intact(): void
{
    $shipment = $this->dispatchedShipment();
    $this->actingAs($this->superAdmin())
        ->postJson("/admin/api/shipping/shipments/{$shipment->id}/events", ['status' => 'pending', 'description' => 'Attempted rewind'])
        ->assertUnprocessable();
    $this->assertDatabaseCount('shipment_events', 1);
}
~~~

- [ ] **Step 2: Run tests and confirm they fail**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php --filter="non_permitted_admin|backward_transition"

Expected: FAIL because permissions/lifecycle service are absent.

- [ ] **Step 3: Add the minimal policy and service**

~~~
final class ShippingPermission
{
    public const VIEW = 'shipping.view';
    public const DISPATCH = 'shipping.dispatch';
    public const UPDATE_STATUS = 'shipping.update_status';

    public static function allows(User $user, string $permission): bool
    {
        return $user->is_super_admin || ($user->is_admin && $user->permissions()->where('permission', $permission)->exists());
    }
}
~~~

Use DB::transaction() and lockForUpdate(). Manual dispatch requires order status processing plus non-empty carrier/tracking; creates/reuses sequence 1; appends shipped; updates order tracking_carrier, tracking_number and shipped_at; adds OrderStatusHistory. Allow shipped -> in_transit -> out_for_delivery -> delivered, plus failed_delivery/returned from in-transit states. Reject duplicate tracking and backward transitions.

- [ ] **Step 4: Run the lifecycle suite**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php

Expected: PASS for authorization, duplicate tracking, forward transition and append-only event tests.

- [ ] **Step 5: Commit only Task 2 files**

Run: git add app/Domain/Shipping/ShippingPermission.php app/Services/ShipmentService.php app/Models/User.php tests/Feature/Admin/ShipmentApiTest.php
Run: git commit -m "feat: enforce shipment lifecycle audit"

### Task 3: Add protected shipment endpoints and converge the existing tracking route

**Files:**
- Create: app/Services/Admin/ShipmentDataService.php
- Create: app/Http/Controllers/Admin/ShipmentApiController.php
- Modify: app/Http/Controllers/Admin/OrderApiController.php
- Modify: app/Services/Admin/OrderOperationsService.php
- Modify: app/Http/Controllers/Admin/AdminAppController.php
- Modify: routes/web.php
- Test: tests/Feature/Admin/ShipmentApiTest.php
- Test: tests/Feature/Admin/OrderApiTest.php

**Interfaces:** Produces GET /admin/api/shipping/shipments, POST /admin/api/shipping/orders/{order}/dispatch and POST /admin/api/shipping/shipments/{shipment}/events. The legacy order tracking route calls the same dispatch service.

- [ ] **Step 1: Write failing endpoint/compatibility tests**

~~~
public function test_shipment_index_returns_a_real_order_backed_read_model(): void
{
    $shipment = $this->dispatchedShipment();
    $this->actingAs($this->superAdmin())->getJson('/admin/api/shipping/shipments')
        ->assertOk()->assertJsonPath('data.0.id', (string) $shipment->id)
        ->assertJsonPath('data.0.orderNumber', $shipment->order->number)
        ->assertJsonPath('data.0.trackingNumber', $shipment->tracking_number);
}

public function test_existing_order_tracking_route_creates_the_same_canonical_shipment(): void
{
    $order = $this->processingOrder();
    $this->actingAs($this->superAdmin())
        ->patchJson("/admin/api/orders/{$order->id}/tracking", ['carrier' => 'Manual courier', 'trackingNumber' => 'COMPAT-1001'])->assertOk();
    $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'tracking_number' => 'COMPAT-1001']);
}
~~~

- [ ] **Step 2: Run the endpoint tests and confirm failure**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php tests/Feature/Admin/OrderApiTest.php --filter="shipment_index|same_canonical_shipment"

Expected: FAIL because the shipment API/read-model is missing.

- [ ] **Step 3: Implement the adapter and endpoints**

ShipmentDataService joins shipment, order, shipping address, items and events into current ShipmentParcel property names. It may return null/empty values; it must never invent dimensions, driver, provider charge, AWB, postcode validation or wallet facts. Controller validation: carrierName max 120, trackingNumber max 160; event status from in_transit/out_for_delivery/delivered/failed_delivery/returned; description max 2000; nullable location max 255. Extend Admin bootstrap only with shipmentsUrl and shippingPermissions.

- [ ] **Step 4: Run the API suite**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php tests/Feature/Admin/OrderApiTest.php

Expected: PASS; both entry points use one shipment implementation and unpermitted staff receive 403.

- [ ] **Step 5: Commit only Task 3 files**

Run: git add app/Services/Admin/ShipmentDataService.php app/Http/Controllers/Admin/ShipmentApiController.php app/Http/Controllers/Admin/OrderApiController.php app/Services/Admin/OrderOperationsService.php app/Http/Controllers/Admin/AdminAppController.php routes/web.php tests/Feature/Admin/ShipmentApiTest.php tests/Feature/Admin/OrderApiTest.php
Run: git commit -m "feat: expose operational shipment APIs"

### Task 4: Bind existing operational Shipping tabs to persisted shipments

**Files:**
- Create: resources/admin/src/integration/shipmentData.ts
- Create: resources/admin/src/integration/shipmentData.test.ts
- Modify: resources/admin/src/types/shipping.ts
- Modify: resources/admin/src/context/AdminContext.tsx
- Modify: resources/admin/src/components/shipping/ShippingDashboard.tsx
- Modify: resources/admin/src/components/shipping/ShippingOverviewTab.tsx
- Modify: resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx
- Modify: resources/admin/src/components/shipping/TrackingAndWebhooksTab.tsx

**Interfaces:** Consumes Task 3 Admin bootstrap/API contract. Produces fetchShipments() and appendShipmentEvent() plus Admin state refreshed from Laravel.

- [ ] **Step 1: Write failing browser-client tests**

~~~
it('loads persisted shipments using the authenticated Laravel bootstrap URL', async () => {
  global.fetch = vi.fn().mockResolvedValue(jsonResponse({ data: [{ id: '7', orderNumber: 'JW-100', trackingNumber: 'MANUAL-100' }] }));
  await expect(fetchShipments(bootstrap)).resolves.toEqual([{ id: '7', orderNumber: 'JW-100', trackingNumber: 'MANUAL-100' }]);
});

it('uses the CSRF token when appending a manual event', async () => {
  await appendShipmentEvent(bootstrap, '7', { status: 'in_transit', description: 'Collected', location: 'Subang' });
  expect(global.fetch).toHaveBeenCalledWith(expect.stringContaining('/shipping/shipments/7/events'), expect.objectContaining({ method: 'POST', headers: expect.objectContaining({ 'X-CSRF-TOKEN': bootstrap.csrfToken }) }));
});
~~~

- [ ] **Step 2: Run client tests and confirm failure**

Run: npm --prefix resources/admin test -- shipmentData.test.ts

Expected: FAIL because the integration client is absent.

- [ ] **Step 3: Implement data binding without visible redesign**

Follow resources/admin/src/integration/orderData.ts for CSRF/error handling. Replace only shipmentParcels initialization, persistence and event mutation in AdminContext; do not load/write STORAGE_KEYS.SHIPMENT_PARCELS. Dashboard overview/fulfillment/tracking consume persisted data. Omit existing provider-only action/counter DOM nodes when no provider connection exists; do not add mock replacement copy, modals, cards or hierarchy. Do not invoke random AWB, simulated webhook, pickup, wallet, routing or reconciliation actions from operational tabs.

- [ ] **Step 4: Run Admin test/build**

Run: npm --prefix resources/admin test -- shipmentData.test.ts
Run: npm --prefix resources/admin run build

Expected: PASS and successful Vite build.

- [ ] **Step 5: Commit only Task 4 files and generated admin assets**

Run: git add resources/admin/src/integration/shipmentData.ts resources/admin/src/integration/shipmentData.test.ts resources/admin/src/types/shipping.ts resources/admin/src/context/AdminContext.tsx resources/admin/src/components/shipping/ShippingDashboard.tsx resources/admin/src/components/shipping/ShippingOverviewTab.tsx resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx resources/admin/src/components/shipping/TrackingAndWebhooksTab.tsx public/build-admin
Run: git commit -m "feat: bind shipping dashboard to shipments"

### Task 5: Prove Admin -> MySQL -> Account tracking closure

**Files:**
- Modify: tests/Feature/Admin/ShipmentApiTest.php
- Modify: tests/Feature/Admin/OrderApiTest.php
- Modify: tests/Feature/OriginalAccountWorkspacePreviewTest.php only if existing order projection needs a test.
- Modify: docs/superpowers/reports/2026-08-25-shipping-operational-closure-mapping.md

**Interfaces:** Consumes Tasks 1–4. Produces explicit closure evidence and retains EasyParcel/provider capabilities as dormant.

- [ ] **Step 1: Write the cross-surface test**

~~~
public function test_manual_dispatch_survives_refresh_and_is_visible_in_customer_account_history(): void
{
    $order = $this->processingOrderFor($this->customer());
    $this->dispatchAsAdmin($order, 'Manual courier', 'ACCOUNT-TRACK-1001');
    $this->actingAs($this->customerUser())->get('/account')->assertOk()
        ->assertSee('Manual courier')->assertSee('ACCOUNT-TRACK-1001');
}
~~~

- [ ] **Step 2: Run cross-surface test and assess compatibility projection**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=manual_dispatch_survives_refresh

Expected: PASS after Task 2 because dispatch updates the existing order projection; do not redesign Account.

- [ ] **Step 3: Update closure mapping accurately**

Mark only rate quote, manual dispatch, lifecycle audit and dashboard read-model CLOSED. Keep EasyParcel, provider labels, webhooks, routing, pickup, wallet and reconciliation DORMANT/MOCK_ONLY.

- [ ] **Step 4: Run targeted regression and Chrome acceptance**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php tests/Feature/Admin/OrderApiTest.php tests/Feature/CheckoutCommerceRulesTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php
Run: npm --prefix resources/admin run build

Chrome: use a real COD/manual-payment order, set it processing, record a manual carrier/tracking reference in existing Admin Orders, refresh /admin/shipping, refresh/re-login and verify the same reference in /account. Confirm invalid backward event and unpermitted staff return existing error responses. Do not create provider or fake courier data.

- [ ] **Step 5: Commit only Task 5 proof/docs files**

Run: git add tests/Feature/Admin/ShipmentApiTest.php tests/Feature/Admin/OrderApiTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php docs/superpowers/reports/2026-08-25-shipping-operational-closure-mapping.md
Run: git commit -m "test: verify manual shipment closure"

## Plan self-review

- Tasks 1–3 implement the shipment aggregate, audit lifecycle and compatibility bridge.
- Task 4 removes P0.2 Shipping operational state from browser fixtures without expanding the UI.
- Task 5 proves persistence, authorization and customer visibility.
- EasyParcel calls, labels, webhooks, routing, pickups, wallet and reconciliation are intentionally excluded and remain dormant.
