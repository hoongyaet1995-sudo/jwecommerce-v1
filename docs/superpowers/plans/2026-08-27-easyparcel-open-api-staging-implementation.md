# EasyParcel Open API Sandbox and Fulfillment Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publish JW Ecommerce at `ecom.jwaistudio.online` and connect Pending AWB through EasyParcel Open API Sandbox.

**Architecture:** Variant package dimensions snapshot into order items. Processing orders without provider bookings project as Pending AWB. Persisted EasyParcel quotes form the selection boundary; confirmation atomically creates one shipment. OAuth credentials are encrypted server records; the ZIP UI consumes only safe state.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Eloquent encrypted casts, Laravel HTTP/queue/scheduler, React/TypeScript, Vite, EasyParcel Open API OAuth, Hostinger Linux.

**Spec:** `docs/superpowers/specs/2026-08-27-easyparcel-open-api-staging-design.md`

## Global Constraints

- Preserve DOM/classes/interactions from `jw-ecommerce-dashboard (14).zip`; change behavior only.
- EasyParcel Open API Sandbox is the only active provider; never use Individual API or production credentials.
- Never emit secrets/tokens in JSON, HTML, logs, Git, screenshots, or reports.
- Other courier cards are truthful `Not configured`, without fixtures, fake production, wallet, labels, or AWBs.
- Delivered alone advances the order; exceptions never cancel/refund/restock automatically.
- Use `C:\xampp\php\php.exe` locally. Keep `output/` untracked.

## Task 1: Add variant package facts and order snapshots

**Files:** Create package migrations; modify `ProductVariant`, `OrderItem`, `CatalogApiController`, `CheckoutService`, and the existing Variant Matrix owner; test `CatalogApiTest` and new `CheckoutPackageSnapshotTest`.

**Interface:** `OrderItem::package_snapshot = {weight_grams,length_cm,width_cm,height_cm}` and product variants gain nullable positive package dimensions.

- [ ] **Step 1: Write the failing tests**

```php
public function test_checkout_snapshots_variant_package_specification(): void
{
    $order = $this->checkoutVariant(['weight_grams'=>420,'package_length_cm'=>30.5,'package_width_cm'=>12,'package_height_cm'=>8]);
    $this->assertSame(420, $order->items()->sole()->package_snapshot['weight_grams']);
}
```

- [ ] **Step 2: Run red**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CheckoutPackageSnapshotTest.php`

Expected: FAIL because `order_items` has no snapshot.

- [ ] **Step 3: Implement minimal schema/API/snapshot**

```php
'package_snapshot' => ['weight_grams'=>$variant->weight_grams,'length_cm'=>$variant->package_length_cm,'width_cm'=>$variant->package_width_cm,'height_cm'=>$variant->package_height_cm],
```

Validate dimensions as `nullable|numeric|gt:0|max:9999`; use existing Variant Matrix classes.

- [ ] **Step 4: Run green**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/CatalogApiTest.php tests/Feature/CheckoutPackageSnapshotTest.php`

Expected: PASS including variants with null legacy dimensions.

- [ ] **Step 5: Commit**

```powershell
git add database/migrations app/Models/ProductVariant.php app/Models/OrderItem.php app/Http/Controllers/Admin/CatalogApiController.php app/Services/CheckoutService.php tests/Feature/Admin/CatalogApiTest.php tests/Feature/CheckoutPackageSnapshotTest.php; git commit -m "feat: snapshot variant package specifications"
```

## Task 2: Add encrypted EasyParcel Sandbox provider setup

**Files:** Create provider/credential migrations and models, `app/Services/Shipping/EasyParcelOpenApiClient.php`, provider configuration service/controller; modify `AdminAppController` and `routes/web.php`; test new `EasyParcelProviderApiTest`.

**Interface:** Safe `GET/PUT /admin/api/shipping/providers/easyparcel` and `POST .../test-connection`; browser DTO has state only, never OAuth values.

- [ ] **Step 1: Write the failing safe-DTO test**

```php
$response = $this->actingAs($this->superAdmin())->putJson('/admin/api/shipping/providers/easyparcel',['environment'=>'sandbox','clientId'=>'client','clientSecret'=>'secret','enabled'=>false]);
$response->assertOk()->assertJsonMissing(['clientSecret'=>'secret']);
```

- [ ] **Step 2: Run red**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/EasyParcelProviderApiTest.php`

Expected: FAIL because the table and routes are missing.

- [ ] **Step 3: Implement encrypted storage and bounded Open API OAuth test**

```php
protected function casts(): array { return ['credentials'=>'encrypted:array','last_verified_at'=>'datetime']; }
```

Use current Developer Hub OAuth docs, redact provider errors, and enable only after a Sandbox verification.

- [ ] **Step 4: Run green**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/EasyParcelProviderApiTest.php`

Expected: PASS; non-admin receives 403 and no response contains credentials/token.

- [ ] **Step 5: Commit**

```powershell
git add database/migrations app/Models/ShippingProvider.php app/Models/ShippingProviderCredential.php app/Services/Shipping app/Http/Controllers/Admin/ShippingProviderApiController.php app/Http/Controllers/Admin/AdminAppController.php routes/web.php tests/Feature/Admin/EasyParcelProviderApiTest.php; git commit -m "feat: add encrypted easyparcel sandbox provider setup"
```

## Task 3: Quote and idempotently book Sandbox shipments

**Files:** Create `ShipmentQuote` migration/model and `EasyParcelFulfillmentService`; modify `Shipment`, `ShipmentService`, shipment read model/controller/routes; test new `EasyParcelFulfillmentApiTest`.

**Interface:** Pending-AWB projection, quote request and booking APIs. `quote(Order, PackageSpec, User): ShipmentQuote`; `book(Order, ShipmentQuote, serviceCode, User): Shipment`.

- [ ] **Step 1: Write the failing idempotency test**

```php
public function test_booking_same_quote_twice_creates_one_shipment(): void
{
    $this->bookQuote(); $this->bookQuote();
    $this->assertDatabaseCount('shipments', 1);
}
```

- [ ] **Step 2: Run red**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/EasyParcelFulfillmentApiTest.php`

Expected: FAIL because only manual dispatch exists.

- [ ] **Step 3: Implement locked quote/booking transaction**

```php
return DB::transaction(function () use ($order, $providerBooking) {
    $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
    return Shipment::query()->firstOrCreate(['order_id'=>$locked->id,'source'=>'easyparcel'], $providerBooking);
});
```

Reject expired/foreign quote, unavailable service, disabled provider, or non-processing order. Store provider reference, quote/package snapshots, AWB and label reference.

- [ ] **Step 4: Run green**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/EasyParcelFulfillmentApiTest.php tests/Feature/Admin/ShipmentApiTest.php tests/Feature/Admin/OrderApiTest.php`

Expected: PASS; manual dispatch remains independent.

- [ ] **Step 5: Commit**

```powershell
git add database/migrations app/Models/ShipmentQuote.php app/Services/Shipping/EasyParcelFulfillmentService.php app/Models/Shipment.php app/Services/ShipmentService.php app/Services/Admin/ShipmentDataService.php app/Http/Controllers/Admin/ShipmentApiController.php routes/web.php tests/Feature/Admin/EasyParcelFulfillmentApiTest.php; git commit -m "feat: book idempotent easyparcel sandbox shipments"
```

## Task 4: Normalize tracking and project orders safely

**Files:** Create provider webhook migration/model, tracking service/job/controller; modify `ShipmentService`, `OrderTrackingPayloadFactory`, routes; test new `EasyParcelTrackingLifecycleTest`.

**Interface:** `POST /webhooks/easyparcel`; event external ID de-duplicates. Delivered advances order; failed/returned remain exceptions.

- [ ] **Step 1: Write the failing provider-event test**

```php
$this->postJson('/webhooks/easyparcel',$this->signedDeliveredPayload())->assertOk();
$this->postJson('/webhooks/easyparcel',$this->signedDeliveredPayload())->assertOk();
$this->assertSame('delivered',$this->shipment->fresh()->order->status);
```

- [ ] **Step 2: Run red**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelTrackingLifecycleTest.php`

Expected: FAIL because webhook storage/mapping does not exist.

- [ ] **Step 3: Implement verified webhook with polling fallback**

```php
if (! $this->verifier->valid($request)) abort(401);
$event = ShippingProviderWebhookEvent::firstOrCreate(['provider'=>'easyparcel','external_event_id'=>$externalId],['payload'=>$redacted]);
if ($event->wasRecentlyCreated) $this->tracking->record($shipment,$mappedEvent);
```

If Open API supplies no signature contract, disable public callbacks and poll with provider authentication only.

- [ ] **Step 4: Run green**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/OrderTrackingReadModelTest.php`

Expected: PASS; repeated events do not duplicate timeline entries.

- [ ] **Step 5: Commit**

```powershell
git add database/migrations app/Models app/Services/Shipping app/Jobs app/Http/Controllers/EasyParcelWebhookController.php app/Services/ShipmentService.php app/Domain/Orders/OrderTrackingPayloadFactory.php routes/web.php tests/Feature/EasyParcelTrackingLifecycleTest.php; git commit -m "feat: sync easyparcel tracking events safely"
```

## Task 5: Restore ZIP Shipping UI and bind Laravel APIs

**Files:** Create source-extraction manifest and `easyParcelProviderData.ts`/`easyParcelFulfillmentData.ts`; modify shipping types, `AdminContext`, `ShippingDashboard`, `ShippingFulfillmentTab`, `CourierConfigTab`, `CourierConfigModal`, `ThermalAWBModal`, `TrackingAndWebhooksTab`, build assets; test `ShippingDashboard.test.ts`.

**Interface:** Consumes Tasks 2-4 APIs. Browser never persists courier/shipment state locally.

- [ ] **Step 1: Write the source manifest and failing client test**

```ts
await saveEasyParcelCredentials(bootstrap,{environment:'sandbox',clientId:'client',clientSecret:'secret'});
expect(global.fetch).toHaveBeenCalledWith(expect.stringContaining('/shipping/providers/easyparcel'),expect.objectContaining({method:'PUT'}));
```

- [ ] **Step 2: Run red**

Run: `npm --prefix resources/admin test -- ShippingDashboard.test.ts`

Expected: FAIL because provider API clients are absent.

- [ ] **Step 3: Restore exact ZIP structures and replace mock callbacks**

```ts
const quotes = await requestEasyParcelQuote(bootstrap,orderId,actualPackage);
const shipment = await confirmEasyParcelBooking(bootstrap,orderId,quoteId,serviceCode);
await refreshShipments();
```

No localStorage/random tracking/mock wallet; unconfigured cards remain source-faithful and inactive.

- [ ] **Step 4: Run green and build**

Run: `npm --prefix resources/admin test -- ShippingDashboard.test.ts`

Run: `npm run build`

Expected: PASS and standard generated assets only.

- [ ] **Step 5: Commit**

```powershell
git add docs/source-extraction resources/admin/src/integration resources/admin/src/types/shipping.ts resources/admin/src/context/AdminContext.tsx resources/admin/src/components/shipping public/build-admin; git commit -m "feat: bind source-faithful easyparcel fulfillment UI"
```

## Task 6: Release the full ecommerce application and prove Sandbox browser workflow

**Files:** Create safe deployment script/runbook and redacted acceptance report. Host-only: private `~/apps/jwecommerce/releases/<commit>` plus `current`, dedicated DB, private `.env`, and public `ecom` entry only to `current/public`.

**Interface:** Consumes Tasks 1-5 and produces the full HTTPS ecommerce deployment. User creates Sandbox Open API app for `https://ecom.jwaistudio.online`, then enters credentials in admin UI at action time.

- [ ] **Step 1: Write/run artifact validator**

```php
$this->assertFileExists($artifact.'/public/index.php');
$this->assertFileDoesNotExist($artifact.'/.env');
```

- [ ] **Step 2: Preflight and create recoverable release**

Run: `ssh -p <port> <host> "php -v"`

Expected: host supports project PHP; retain default ecom entry backup and prior release. Never touch the main-site app.

- [ ] **Step 3: Configure host environment and migrate the dedicated ecommerce DB**

Set the ecommerce URL, real SMTP, dedicated DB, queue/cache only in host `.env`; install/build, migrate once, link storage, optimize caches.

- [ ] **Step 4: Verify deployed health**

Run: `curl -I https://ecom.jwaistudio.online`

Expected: HTTPS works, migrations use the dedicated ecommerce DB, unauthenticated admin API is denied.

- [ ] **Step 5: Run real browser acceptance and commit safe evidence**

```text
Processing & Verified -> Pending AWB -> actual package confirmation -> real quote
-> chosen returned service -> one test AWB/label -> Ready to Ship
-> reconciled In Transit -> Delivered or truthful Exception
```

Refresh/re-login after each transition, attempt duplicate booking once, and prove state in Orders, Shipping, Account and privacy-gated Order Tracking. Commit only runbook/report; never credentials, `.env`, data dump or `output/`.

## Plan self-review

- [x] Package data, encrypted provider setup, quote/booking, tracking, ZIP UI, full ecommerce release, SMTP and browser proof each have a task.
- [x] The plan forbids legacy API, production credentials, fake courier state, secret exposure, automatic financial actions and main-site overwrite.
- [x] Task interfaces are ordered and every task has red/green verification plus focused commit guidance.
