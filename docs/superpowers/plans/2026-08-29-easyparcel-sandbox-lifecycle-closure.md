# EasyParcel Sandbox Lifecycle Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the EasyParcel Sandbox order path from staff-confirmed package data through quote, booking, AWB, webhook-triggered authenticated reconciliation, and shared shipment/order/customer lifecycle projection.

**Architecture:** Preserve the existing Google AI Studio Shipping UI and existing Laravel quote/booking/tracking services. Add a package-confirmation adapter, a sanitized webhook receipt inbox, and queued reconciliation where webhook payloads are hints only; OAuth-authenticated EasyParcel Shipment Details and Tracking Status responses remain authoritative, with the existing fifteen-minute poll as fallback.

**Tech Stack:** PHP 8.2, Laravel 12, MySQL, Laravel HTTP client/queues/scheduler, React 19, TypeScript 5.8, Vitest 3, Vite 6, Tailwind CSS 4, XAMPP, EasyParcel Open API 2026-06 (Shipment Details uses its documented 2026-03 endpoint path).

**Spec:** `docs/superpowers/specs/2026-08-29-easyparcel-sandbox-lifecycle-closure-design.md`

## Global Constraints

- Make every application-code change first in `C:\xampp\htdocs\jwstudio-ecommerce`.
- Automated Laravel tests must use only `jw_ecommerce_testing`; never run `RefreshDatabase`, `migrate:fresh`, or destructive test commands against `jw_ecommerce`.
- Preserve the approved Google AI Studio DOM structure, classes, light/dark behavior, and component hierarchy; add only data binding and the approved package-input states.
- Never expose or log OAuth tokens, Client Secret, database credentials, full webhook raw bodies, or unnecessary customer/address data.
- A webhook payload is never authoritative because the EasyParcel 2026-06 public documentation defines no signature/HMAC contract.
- Only an OAuth-authenticated EasyParcel response may mutate EasyParcel shipment events, shipment lifecycle, order status, Account, or Order Tracking.
- Sandbox status fixtures must be labelled simulated; only a later separately approved Live physical shipment may prove real carrier-to-Delivered closure.
- Exceptions, cancellations, On Hold, and Returned events never automatically refund, restock, cancel payment, or cancel the order.
- Preserve the user-owned modification to `AGENTS.md`; never stage or commit it.
- Commit after each independently accepted task; push/deploy only after the complete local phase passes.

---

## File Structure

### Package confirmation

- Create `resources/admin/src/components/shipping/easyParcelPackageConfirmation.ts` — pure draft, parsing, validation, and quote invalidation helpers.
- Create `resources/admin/src/components/shipping/easyParcelPackageConfirmation.test.ts` — deterministic package helper tests.
- Modify `resources/admin/src/components/shipping/EasyParcelBookingModal.tsx` — render employee-confirmed weight/dimension fields and request a quote only from a validated draft.
- Modify `resources/admin/src/context/AdminContext.tsx` — accept an explicit `EasyParcelPackageSpec` instead of reconstructing it from the parcel read model.
- Modify `resources/admin/src/components/shipping/ShippingDashboard.tsx` — pass the new quote callback signature.

### Webhook receipt boundary

- Create `database/migrations/2026_08_29_000000_create_shipping_webhook_receipts_table.php` — sanitized, idempotent inbound receipt persistence.
- Create `app/Models/ShippingWebhookReceipt.php` — receipt casts, constants, and shipment/provider relationships.
- Create `app/Services/Shipping/EasyParcelWebhookReceiptService.php` — allow-list topics, normalize correlation metadata, de-duplicate, and classify receipts without mutating commerce state.
- Create `app/Http/Controllers/EasyParcelWebhookController.php` — public JSON receiver with opaque path-token check and bounded response contract.
- Create `tests/Feature/EasyParcelWebhookIngressTest.php` — route, sanitation, idempotency, size, schema, and no-side-effect tests.
- Modify `routes/web.php`, `bootstrap/app.php`, `config/services.php`, and `.env.example` — public route, CSRF exception, and endpoint token configuration.

### Authenticated reconciliation

- Create `app/Services/Shipping/EasyParcelProviderSession.php` — immutable provider/token/configuration snapshot DTO.
- Create `app/Services/Shipping/EasyParcelProviderSessionService.php` — load and revalidate one connected provider session.
- Modify `app/Services/Shipping/EasyParcelTrackingService.php` — reuse the provider-session service and expose targeted `syncShipment()` alongside batch polling.
- Create `app/Services/Shipping/EasyParcelShipmentDetailsService.php` — retrieve authoritative shipment/AWB/label/tracking references by EasyParcel shipment number.
- Create `app/Services/Shipping/EasyParcelWebhookReconciliationService.php` — choose Details/Tracking reconciliation from a receipt topic.
- Create `app/Jobs/ReconcileEasyParcelWebhook.php` — bounded unique queued processing per receipt.
- Modify `app/Services/Shipping/EasyParcelWebhookReceiptService.php` and `app/Http/Controllers/EasyParcelWebhookController.php` — enqueue only matched, supported, non-duplicate receipts after commit.
- Create `tests/Feature/EasyParcelWebhookReconciliationTest.php` — authoritative Details/Tracking, retry, stale-provider, and no-direct-mutation tests.

### Lifecycle and operator read models

- Modify `app/Services/ShipmentService.php` — enforce monotonic terminal projection and explicit exception semantics.
- Modify `app/Services/Admin/ShipmentDataService.php` — project Ready to Ship/In Transit/Delivered/Exceptions consistently.
- Modify `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx` — include Returned in Exceptions without inventing a new visual.
- Modify `tests/Feature/EasyParcelTrackingLifecycleTest.php` and `tests/Feature/Admin/EasyParcelFulfillmentApiTest.php` — status matrix, out-of-order terminal safety, and package/read-model proof.

### Webhook audit UI

- Create `app/Services/Admin/EasyParcelWebhookReceiptDataService.php` — safe operator receipt rows.
- Create `app/Http/Controllers/Admin/EasyParcelWebhookReceiptApiController.php` — protected read-only receipt endpoint.
- Create `resources/admin/src/integration/easyParcelWebhookData.ts` — typed receipt fetcher.
- Create `resources/admin/src/integration/easyParcelWebhookData.test.ts` — API mapping tests.
- Modify `resources/admin/src/types/shipping.ts`, `resources/admin/src/types/index.ts`, `resources/admin/src/context/AdminContext.tsx`, `resources/admin/src/components/shipping/ShippingDashboard.tsx`, and `resources/admin/src/components/shipping/TrackingAndWebhooksTab.tsx` — replace fictional webhook availability copy with real receipt/reconciliation status while retaining manual event entry as explicitly manual.
- Modify `tests/Feature/Admin/ShipmentApiTest.php` and `resources/admin/src/components/shipping/ShippingDashboard.test.ts` — permission, safe serialization, and source-faithful render proof.

### Acceptance and operations

- Create `docs/operations/easyparcel-sandbox-acceptance.md` — exact backup, test-order, Developer Hub topic, evidence, rollback, and later Live-pilot runbook.
- Run targeted/full safe test suites, Admin lint/build, authenticated localhost browser acceptance, same-SHA GitHub/Hostinger release, and deployed Sandbox callback/webhook acceptance.

---

### Task 1: Staff-Confirmed Package Specification

**Files:**
- Create: `resources/admin/src/components/shipping/easyParcelPackageConfirmation.ts`
- Create: `resources/admin/src/components/shipping/easyParcelPackageConfirmation.test.ts`
- Modify: `resources/admin/src/components/shipping/EasyParcelBookingModal.tsx`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Modify: `resources/admin/src/components/shipping/ShippingDashboard.tsx`
- Test: `resources/admin/src/integration/easyParcelFulfillmentData.test.ts`

**Interfaces:**
- Consumes: `ShipmentParcel`, `EasyParcelPackageSpec`, and the existing `requestEasyParcelQuote(bootstrap, orderId, packageSpec)` client.
- Produces: `EasyParcelPackageDraft`, `packageDraftFromParcel(parcel)`, `packageSpecFromDraft(draft)`, and `quoteEasyParcelShipment(parcel, packageSpec): Promise<EasyParcelQuote>`.

- [ ] **Step 1: Write failing pure-helper tests**

```ts
import { describe, expect, it } from 'vitest';
import { packageDraftFromParcel, packageSpecFromDraft } from './easyParcelPackageConfirmation';

describe('EasyParcel package confirmation', () => {
  it('prefills the order snapshot without inventing missing dimensions', () => {
    const draft = packageDraftFromParcel({
      totalWeightKg: 1.25,
      dimensionsCm: null,
    });

    expect(draft).toEqual({
      weightKg: '1.25',
      lengthCm: '',
      widthCm: '',
      heightCm: '',
    });
  });

  it('returns the exact validated two-decimal package sent for quotation', () => {
    expect(packageSpecFromDraft({
      weightKg: '1.25',
      lengthCm: '28',
      widthCm: '18',
      heightCm: '8',
    })).toEqual({ weightKg: 1.25, lengthCm: 28, widthCm: 18, heightCm: 8 });
  });

  it.each(['', '0', '-1', '1.234', 'abc'])('rejects invalid package values: %s', (value) => {
    expect(() => packageSpecFromDraft({
      weightKg: value,
      lengthCm: '28',
      widthCm: '18',
      heightCm: '8',
    })).toThrow('Enter weight and dimensions as positive numbers with up to 2 decimals.');
  });
});
```

- [ ] **Step 2: Run the helper test and verify RED**

Run:

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce\resources\admin
npm test -- --run src/components/shipping/easyParcelPackageConfirmation.test.ts
```

Expected: FAIL because `easyParcelPackageConfirmation.ts` does not exist.

- [ ] **Step 3: Implement the pure package adapter**

```ts
import type { EasyParcelPackageSpec } from '../../integration/easyParcelFulfillmentData';
import type { ShipmentParcel } from '../../types/shipping';

export interface EasyParcelPackageDraft {
  weightKg: string;
  lengthCm: string;
  widthCm: string;
  heightCm: string;
}

type PackageSource = Pick<ShipmentParcel, 'totalWeightKg' | 'dimensionsCm'>;

export const packageDraftFromParcel = (parcel: PackageSource): EasyParcelPackageDraft => ({
  weightKg: parcel.totalWeightKg?.toString() ?? '',
  lengthCm: parcel.dimensionsCm?.length.toString() ?? '',
  widthCm: parcel.dimensionsCm?.width.toString() ?? '',
  heightCm: parcel.dimensionsCm?.height.toString() ?? '',
});

export const packageSpecFromDraft = (draft: EasyParcelPackageDraft): EasyParcelPackageSpec => {
  const pattern = /^\d{1,6}(?:\.\d{1,2})?$/;
  const values = Object.values(draft);
  if (values.some((value) => !pattern.test(value) || Number(value) <= 0 || Number(value) > 999999.99)) {
    throw new Error('Enter weight and dimensions as positive numbers with up to 2 decimals.');
  }

  return {
    weightKg: Number(draft.weightKg),
    lengthCm: Number(draft.lengthCm),
    widthCm: Number(draft.widthCm),
    heightCm: Number(draft.heightCm),
  };
};
```

- [ ] **Step 4: Bind the adapter into the existing modal**

Change the modal contract to:

```ts
interface Props {
  isOpen: boolean;
  parcel: ShipmentParcel | null;
  onClose: () => void;
  onQuote: (parcel: ShipmentParcel, packageSpec: EasyParcelPackageSpec) => Promise<EasyParcelQuote>;
  onBook: (quote: EasyParcelQuote, serviceCode: string) => Promise<EasyParcelBooking>;
  onBooked: () => Promise<void>;
}
```

Initialize the draft whenever a different parcel opens, clear any prior quote when a field changes, and call `packageSpecFromDraft(draft)` inside `fetchRates()`. Do not auto-call EasyParcel when a required field is blank. Render the four inputs inside the existing package-summary cards with the existing border, radius, typography, light, and dark classes. Keep destination and payment cards read-only.

Change the context contract and implementation to:

```ts
quoteEasyParcelShipment: (parcel: ShipmentParcel, packageSpec: EasyParcelPackageSpec) => Promise<EasyParcelQuote>;

const quoteEasyParcelShipment = (
  parcel: ShipmentParcel,
  packageSpec: EasyParcelPackageSpec,
): Promise<EasyParcelQuote> => requestEasyParcelQuote(
  laravelBootstrap,
  parcel.orderId,
  packageSpec,
);
```

- [ ] **Step 5: Run frontend tests, typecheck, and build**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce\resources\admin
npm test -- --run src/components/shipping/easyParcelPackageConfirmation.test.ts src/integration/easyParcelFulfillmentData.test.ts
npm run lint
npm run build
```

Expected: all selected tests PASS, TypeScript exits 0, and Vite produces `public/build-admin` assets.

- [ ] **Step 6: Commit Task 1**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
git add -- resources/admin/src/components/shipping/easyParcelPackageConfirmation.ts resources/admin/src/components/shipping/easyParcelPackageConfirmation.test.ts resources/admin/src/components/shipping/EasyParcelBookingModal.tsx resources/admin/src/context/AdminContext.tsx resources/admin/src/components/shipping/ShippingDashboard.tsx public/build-admin
git commit -m "feat: confirm EasyParcel package before quoting"
```

---

### Task 2: Sanitized, Idempotent Webhook Receipt Endpoint

**Files:**
- Create: `database/migrations/2026_08_29_000000_create_shipping_webhook_receipts_table.php`
- Create: `app/Models/ShippingWebhookReceipt.php`
- Create: `app/Services/Shipping/EasyParcelWebhookReceiptService.php`
- Create: `app/Http/Controllers/EasyParcelWebhookController.php`
- Create: `tests/Feature/EasyParcelWebhookIngressTest.php`
- Modify: `tests/Feature/Admin/EasyParcelFulfillmentMigrationTest.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Modify: `config/services.php`
- Modify: `.env.example`

**Interfaces:**
- Consumes: public EasyParcel JSON topics and existing `Shipment` rows indexed by `provider_reference` or `tracking_number`.
- Produces: `EasyParcelWebhookReceiptService::receive(string $rawBody, array $payload): ShippingWebhookReceipt` and `POST /webhooks/easyparcel/{token}`.

- [ ] **Step 1: Write failing migration and ingress tests**

```php
public function test_supported_webhook_is_sanitized_deduplicated_and_does_not_mutate_order(): void
{
    Queue::fake();
    config(['services.easyparcel.webhook_path_token' => 'test-endpoint-token']);
    [$order, $shipment] = $this->easyParcelShipment('ES-2608-WEB01', 'AWB-WEB01');
    $payload = [
        'topic' => 'shipment.tracking.update',
        'uuid' => 'webhook-test-uuid-123',
        'shipment_number' => $shipment->provider_reference,
        'awb_number' => $shipment->tracking_number,
        'latest_shipment_status_code' => 5,
        'latest_tracking_status' => 'Delivered to recipient',
        'timestamp' => '2026-08-29 12:00:00',
        'receiver_email' => 'must-not-persist@example.test',
    ];

    $this->postJson('/webhooks/easyparcel/test-endpoint-token', $payload)->assertOk();
    $this->postJson('/webhooks/easyparcel/test-endpoint-token', $payload)->assertOk();

    $this->assertDatabaseCount('shipping_webhook_receipts', 1);
    $receipt = ShippingWebhookReceipt::query()->sole();
    self::assertSame('shipment.tracking.update', $receipt->topic);
    self::assertSame($shipment->id, $receipt->shipment_id);
    self::assertStringNotContainsString('must-not-persist@example.test', json_encode($receipt->getAttributes()));
    self::assertSame('processing', $order->fresh()->status);
    self::assertSame('pending', $shipment->fresh()->status);
}

public function test_wrong_token_and_oversized_body_are_rejected_without_receipt(): void
{
    config(['services.easyparcel.webhook_path_token' => 'correct-token']);

    $this->postJson('/webhooks/easyparcel/wrong-token', ['topic' => 'shipment.created'])
        ->assertNotFound();
    $this->call('POST', '/webhooks/easyparcel/correct-token', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['topic' => 'shipment.created', 'padding' => str_repeat('x', 65537)]))
        ->assertStatus(413);

    $this->assertDatabaseCount('shipping_webhook_receipts', 0);
}

/** @return array{Order, Shipment} */
private function easyParcelShipment(string $providerReference, string $awb): array
{
    $order = Order::query()->create([
        'number' => 'JW-WEBHOOK-'.Str::upper(Str::random(8)),
        'email' => 'webhook-fixture@example.test',
        'currency' => 'MYR',
        'status' => 'processing',
        'subtotal' => 10000,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'total' => 10000,
    ]);
    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'sequence' => 1,
        'source' => 'easyparcel',
        'carrier_name' => 'EasyParcel Sandbox Courier',
        'tracking_number' => $awb,
        'status' => 'pending',
        'currency' => 'MYR',
        'provider_reference' => $providerReference,
    ]);

    return [$order, $shipment];
}
```

- [ ] **Step 2: Run ingress tests and verify RED**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelWebhookIngressTest.php
```

Expected: FAIL because the route, model, and table do not exist.

- [ ] **Step 3: Create the receipt schema and model**

Create the table with:

```php
Schema::create('shipping_webhook_receipts', function (Blueprint $table): void {
    $table->id();
    $table->string('provider', 32)->default('easyparcel');
    $table->string('environment', 24)->default('sandbox');
    $table->string('topic', 64);
    $table->string('provider_uuid', 160)->nullable();
    $table->char('payload_hash', 64);
    $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
    $table->json('correlation_snapshot');
    $table->string('processing_state', 24)->default('received');
    $table->string('last_error', 64)->nullable();
    $table->unsignedSmallInteger('attempt_count')->default(0);
    $table->timestamp('received_at');
    $table->timestamp('queued_at')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();

    $table->unique(['provider', 'environment', 'payload_hash'], 'shipping_webhook_receipt_dedupe');
    $table->index(['processing_state', 'received_at']);
});
```

The model casts `correlation_snapshot` to array and timestamps to datetime. Its fillable fields must not include a raw payload column because no such column exists.

- [ ] **Step 4: Implement topic normalization and receipt de-duplication**

Use the exact allow-list:

```php
private const TOPICS = [
    'shipment.created',
    'shipment.awb.update',
    'shipment.status.update',
    'shipment.tracking.update',
    'ondemand.status.update',
];
```

Persist only these normalized fields when present and valid strings/integers: `shipment_number`, `awb_number`, `shipment_status_code`, `latest_shipment_status_code`, `timestamp`, and `event_date`. Compute `payload_hash` from the exact raw body. Correlate first by `provider_reference=shipment_number`, then by `tracking_number=awb_number`. Unsupported On-Demand receipts use `processing_state=ignored`; unknown standard shipments use `processing_state=unmatched`; valid matched standard topics remain `received`.

- [ ] **Step 5: Register the public route and safe controller**

Add configuration:

```php
'easyparcel' => [
    // existing keys remain unchanged
    'webhook_path_token' => env('EASYPARCEL_WEBHOOK_PATH_TOKEN'),
],
```

Add `.env.example` only:

```dotenv
EASYPARCEL_WEBHOOK_PATH_TOKEN=
```

Register:

```php
Route::post('/webhooks/easyparcel/{token}', EasyParcelWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.easyparcel');
```

Add only `webhooks/easyparcel/*` to Laravel's CSRF exception list. The controller must:

1. compare the configured path token with `hash_equals()` and return 404 on mismatch;
2. require JSON and cap raw content at 65,536 bytes;
3. pass decoded associative JSON to `receive()`;
4. return `response()->json(['received' => true])` for a durably classified receipt;
5. return no order, shipment, or customer data.

- [ ] **Step 6: Run migration round-trip and ingress tests**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelWebhookIngressTest.php
C:\xampp\php\php.exe artisan test --filter=EasyParcelFulfillmentMigrationTest
```

Expected: PASS with one receipt for duplicate payloads and no commerce-state mutation.

- [ ] **Step 7: Commit Task 2**

```powershell
git add -- database/migrations/2026_08_29_000000_create_shipping_webhook_receipts_table.php app/Models/ShippingWebhookReceipt.php app/Services/Shipping/EasyParcelWebhookReceiptService.php app/Http/Controllers/EasyParcelWebhookController.php tests/Feature/EasyParcelWebhookIngressTest.php tests/Feature/Admin/EasyParcelFulfillmentMigrationTest.php routes/web.php bootstrap/app.php config/services.php .env.example
git commit -m "feat: receive EasyParcel webhook hints safely"
```

---

### Task 3: Reusable Connected Provider Session and Targeted Tracking Sync

**Files:**
- Create: `app/Services/Shipping/EasyParcelProviderSession.php`
- Create: `app/Services/Shipping/EasyParcelProviderSessionService.php`
- Modify: `app/Services/Shipping/EasyParcelOpenApiClient.php`
- Modify: `app/Services/Shipping/EasyParcelTrackingService.php`
- Modify: `tests/Feature/EasyParcelTrackingLifecycleTest.php`
- Modify: `tests/Feature/Admin/EasyParcelPlatformOauthTest.php`

**Interfaces:**
- Consumes: `ShippingProvider`, encrypted `ShippingProviderCredential`, and an EasyParcel `Shipment`.
- Produces: `EasyParcelOpenApiClient::refreshAccessToken(array $credentials, string $refreshToken): array`, `EasyParcelProviderSessionService::connected(): ?EasyParcelProviderSession`, `assertCurrent(EasyParcelProviderSession): void`, and `EasyParcelTrackingService::syncShipment(Shipment $shipment): int`.

- [ ] **Step 1: Write failing targeted-sync tests**

```php
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

    Http::assertSent(fn (Request $request): bool =>
        $request->data() === ['awb_numbers' => ['AWB-TARGET-1']]
    );
    self::assertSame('in_transit', $first->fresh()->status);
    self::assertSame('pending', Shipment::query()->where('tracking_number', 'AWB-TARGET-2')->sole()->status);
}
```

- [ ] **Step 2: Run targeted test and verify RED**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelTrackingLifecycleTest.php --filter=targeted_tracking
```

Expected: FAIL because `syncShipment()` does not exist.

- [ ] **Step 3: Add refresh-token exchange and extract the provider session boundary**

Define:

```php
final readonly class EasyParcelProviderSession
{
    public function __construct(
        public int $providerId,
        public int $credentialId,
        public int $configurationVersion,
        public string $credentialFingerprint,
        public string $accessToken,
    ) {}
}
```

Add this public client contract using the existing token endpoint, Basic Auth, timeout policy, safe rejection logging, expiry parser, and `tokenCache()` normalizer:

```php
public function refreshAccessToken(array $credentials, string $refreshToken): array
{
    $response = Http::asForm()
        ->acceptJson()
        ->withBasicAuth($credentials['client_id'], $credentials['client_secret'])
        ->connectTimeout(5)
        ->timeout(10)
        ->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

    $payload = $response->json();
    if (! $response->successful() || ! is_array($payload)) {
        throw new EasyParcelVerificationException;
    }

    return $this->tokenCache($payload);
}
```

Move the current provider/credential/token validation and stale-snapshot check from `EasyParcelTrackingService` into `EasyParcelProviderSessionService`. Preserve the current requirements: provider `easyparcel`, environment `sandbox`, enabled, `connected`, Bearer token, and non-empty token.

When the access token expires within one minute, acquire `Cache::lock('easyparcel-oauth-refresh-'.$provider->id, 15)->block(5, ...)`, reload the encrypted credential, verify the refresh token is present and unexpired, exchange it outside a database transaction, and persist the rotated token fields only if the provider configuration and old credential fingerprint are still current. Preserve the old refresh token when EasyParcel omits a replacement. Return the new provider session. A refresh rejection sets a safe provider error and throws `EasyParcelTrackingException`; it never logs or returns either token.

Add tests proving one refresh under concurrent/duplicate session reads, rotated refresh-token persistence, fallback to the old refresh token when omitted, and safe failure when the refresh token is expired.

- [ ] **Step 4: Refactor tracking into batch and targeted entry points**

Keep:

```php
public function syncNextBatch(): int
```

Add:

```php
public function syncShipment(Shipment $shipment): int
{
    $eligible = Shipment::query()
        ->whereKey($shipment->id)
        ->where('source', ShippingProviderConfigurationService::PROVIDER)
        ->whereNotNull('tracking_number')
        ->where('tracking_number', '<>', '')
        ->whereNotIn('status', ['delivered', 'returned'])
        ->get();

    return $this->syncCollection($eligible);
}
```

Both public methods call one private `syncCollection(EloquentCollection $shipments): int`; it fetches one provider session, posts the exact AWB list, normalizes all responses before any writes, records events transactionally, updates `tracking_polled_at`, and calls `assertCurrent()` before commit.

- [ ] **Step 5: Run tracking and OAuth regression suites**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/Admin/EasyParcelPlatformOauthTest.php tests/Feature/Admin/EasyParcelProviderApiTest.php
```

Expected: PASS; scheduled batch behavior, malformed-response safety, stale configuration rejection, and targeted sync all remain green.

- [ ] **Step 6: Commit Task 3**

```powershell
git add -- app/Services/Shipping/EasyParcelProviderSession.php app/Services/Shipping/EasyParcelProviderSessionService.php app/Services/Shipping/EasyParcelOpenApiClient.php app/Services/Shipping/EasyParcelTrackingService.php tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/Admin/EasyParcelPlatformOauthTest.php
git commit -m "refactor: support targeted EasyParcel tracking sync"
```

---

### Task 4: Shipment Details and Queued Webhook Reconciliation

**Files:**
- Create: `app/Services/Shipping/EasyParcelShipmentDetailsService.php`
- Create: `app/Services/Shipping/EasyParcelWebhookReconciliationService.php`
- Create: `app/Jobs/ReconcileEasyParcelWebhook.php`
- Create: `tests/Feature/EasyParcelWebhookReconciliationTest.php`
- Modify: `app/Services/Shipping/EasyParcelWebhookReceiptService.php`
- Modify: `app/Http/Controllers/EasyParcelWebhookController.php`

**Interfaces:**
- Consumes: `ShippingWebhookReceipt`, `EasyParcelProviderSessionService`, existing EasyParcel shipment/provider references, and targeted tracking sync.
- Produces: `EasyParcelShipmentDetailsService::reconcile(Shipment $shipment): Shipment`, `EasyParcelWebhookReconciliationService::reconcile(ShippingWebhookReceipt $receipt): void`, and unique queued `ReconcileEasyParcelWebhook`.

- [ ] **Step 1: Write failing reconciliation tests**

```php
public function test_awb_webhook_uses_authenticated_details_before_updating_shipment(): void
{
    Queue::fake();
    $this->connectedProvider();
    [$order, $shipment] = $this->easyParcelShipmentWithoutAwb('ES-2608-AWB01');

    $receipt = $this->receipt([
        'topic' => 'shipment.awb.update',
        'shipment_number' => 'ES-2608-AWB01',
        'awb_number' => 'FORGED-WEBHOOK-AWB',
    ], $shipment);

    Http::fake([
        EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($this->shipmentDetailsResponse(
            shipmentNumber: 'ES-2608-AWB01',
            awb: 'AUTHENTIC-AWB-01',
        )),
    ]);

    app(EasyParcelWebhookReconciliationService::class)->reconcile($receipt);

    self::assertSame('AUTHENTIC-AWB-01', $shipment->fresh()->tracking_number);
    self::assertNotSame('FORGED-WEBHOOK-AWB', $shipment->fresh()->tracking_number);
    self::assertSame('processing', $order->fresh()->status);
}

public function test_tracking_webhook_dispatches_one_unique_job_and_never_applies_payload_status_directly(): void
{
    Queue::fake();
    config(['services.easyparcel.webhook_path_token' => 'test-token']);
    [$order, $shipment] = $this->easyParcelShipment('ES-2608-TRK01', 'AWB-TRK01');

    $payload = $this->trackingWebhook($shipment, latestCode: 5);
    $this->postJson('/webhooks/easyparcel/test-token', $payload)->assertOk();
    $this->postJson('/webhooks/easyparcel/test-token', $payload)->assertOk();

    Queue::assertPushed(ReconcileEasyParcelWebhook::class, 1);
    self::assertSame('processing', $order->fresh()->status);
    self::assertSame('pending', $shipment->fresh()->status);
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

/** @return array{Order, Shipment} */
private function easyParcelShipmentWithoutAwb(string $providerReference): array
{
    return $this->easyParcelShipment($providerReference, null);
}

/** @return array{Order, Shipment} */
private function easyParcelShipment(string $providerReference, ?string $awb): array
{
    $order = Order::query()->create([
        'number' => 'JW-WEB-'.Str::upper(Str::random(8)),
        'email' => 'webhook-reconcile@example.test',
        'currency' => 'MYR',
        'status' => 'processing',
        'subtotal' => 10000,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'total' => 10000,
    ]);
    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'sequence' => 1,
        'source' => 'easyparcel',
        'carrier_name' => 'EasyParcel Sandbox Courier',
        'tracking_number' => $awb,
        'status' => 'pending',
        'currency' => 'MYR',
        'provider_reference' => $providerReference,
    ]);

    return [$order, $shipment];
}

private function receipt(array $correlation, Shipment $shipment): ShippingWebhookReceipt
{
    return ShippingWebhookReceipt::query()->create([
        'provider' => 'easyparcel',
        'environment' => 'sandbox',
        'topic' => $correlation['topic'],
        'provider_uuid' => null,
        'payload_hash' => hash('sha256', json_encode($correlation, JSON_THROW_ON_ERROR)),
        'shipment_id' => $shipment->id,
        'correlation_snapshot' => $correlation,
        'processing_state' => 'queued',
        'received_at' => now(),
        'queued_at' => now(),
    ]);
}

private function shipmentDetailsResponse(string $shipmentNumber, string $awb): array
{
    return [
        'status_code' => 200,
        'message' => 'success',
        'data' => [[
            'shipment_number' => $shipmentNumber,
            'order_number' => 'EI-2608-AWB01',
            'shipment_details' => [
                'awb_number' => $awb,
                'awb_url' => 'https://app.easyparcel.com/labels/'.$awb,
                'tracking_url' => 'https://easyparcel.com/my/en/track/details/'.$awb,
            ],
        ]],
    ];
}

private function trackingWebhook(Shipment $shipment, int $latestCode): array
{
    return [
        'topic' => 'shipment.tracking.update',
        'uuid' => 'webhook-'.$shipment->id,
        'shipment_number' => $shipment->provider_reference,
        'awb_number' => $shipment->tracking_number,
        'latest_shipment_status_code' => $latestCode,
        'latest_tracking_status' => 'Webhook hint only',
        'timestamp' => '2026-08-29 12:00:00',
    ];
}
```

- [ ] **Step 2: Run reconciliation tests and verify RED**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelWebhookReconciliationTest.php
```

Expected: FAIL because the services and job do not exist.

- [ ] **Step 3: Implement authenticated Shipment Details reconciliation**

Use the documented endpoint constant:

```php
public const DETAILS_URL = 'https://api.easyparcel.com/open_api/2026-03/shipment/details';
```

`reconcile()` must post only:

```php
['shipment_number' => $shipment->provider_reference]
```

Require a successful `status_code=200` response with exactly the requested shipment number. Normalize only allow-listed fields: `shipment_number`, `order_number`, `shipment_details.awb_number`, `shipment_details.awb_url`, and `shipment_details.tracking_url`. Accept label/tracking references only through the existing HTTPS URL policy. Update the existing shipment inside a lock and call `EasyParcelProviderSessionService::assertCurrent()` before commit. Never create a second shipment.

- [ ] **Step 4: Implement topic routing and receipt state transitions**

```php
public function reconcile(ShippingWebhookReceipt $receipt): void
{
    $locked = ShippingWebhookReceipt::query()->findOrFail($receipt->id);
    if (in_array($locked->processing_state, ['processed', 'ignored'], true)) {
        return;
    }

    $shipment = $locked->shipment()->firstOrFail();

    if (in_array($locked->topic, [
        'shipment.created',
        'shipment.awb.update',
        'shipment.status.update',
    ], true)) {
        $shipment = $this->details->reconcile($shipment);
    }

    if ($locked->topic === 'shipment.tracking.update' || filled($shipment->tracking_number)) {
        $this->tracking->syncShipment($shipment);
    }

    $locked->forceFill([
        'processing_state' => 'processed',
        'processed_at' => now(),
        'last_error' => null,
    ])->save();
}
```

On exceptions, increment `attempt_count`, set `processing_state=retrying`, store only a stable code such as `provider_unavailable`, and rethrow so Laravel queue retry/backoff applies.

- [ ] **Step 5: Implement the bounded unique job and enqueue-after-commit**

The job implements `ShouldQueue` and `ShouldBeUnique` with:

```php
public int $tries = 4;
public int $timeout = 30;
public int $uniqueFor = 900;

public function backoff(): array
{
    return [30, 120, 600];
}

public function uniqueId(): string
{
    return 'easyparcel-webhook-receipt-'.$this->receiptId;
}

public function failed(Throwable $exception): void
{
    ShippingWebhookReceipt::query()->whereKey($this->receiptId)->update([
        'processing_state' => 'failed',
        'processed_at' => now(),
        'last_error' => 'retry_exhausted',
    ]);
}
```

Dispatch only when a newly created receipt is matched and its topic is one of the four standard shipment topics. Set `processing_state=queued` and `queued_at` in the same transaction, then use `DB::afterCommit()` to dispatch.

- [ ] **Step 6: Run webhook, tracking, and fulfillment regression tests**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelWebhookIngressTest.php tests/Feature/EasyParcelWebhookReconciliationTest.php tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/Admin/EasyParcelFulfillmentApiTest.php
```

Expected: PASS; forged webhook status/AWB never mutates state, while authenticated Details/Tracking responses do.

- [ ] **Step 7: Commit Task 4**

```powershell
git add -- app/Services/Shipping/EasyParcelShipmentDetailsService.php app/Services/Shipping/EasyParcelWebhookReconciliationService.php app/Jobs/ReconcileEasyParcelWebhook.php tests/Feature/EasyParcelWebhookReconciliationTest.php app/Services/Shipping/EasyParcelWebhookReceiptService.php app/Http/Controllers/EasyParcelWebhookController.php
git commit -m "feat: reconcile EasyParcel webhook hints with OAuth"
```

---

### Task 5: Monotonic Shipment and Order Lifecycle Projection

**Files:**
- Modify: `app/Services/ShipmentService.php`
- Modify: `app/Services/Admin/ShipmentDataService.php`
- Modify: `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx`
- Modify: `tests/Feature/EasyParcelTrackingLifecycleTest.php`
- Modify: `tests/Feature/Admin/EasyParcelFulfillmentApiTest.php`

**Interfaces:**
- Consumes: normalized EasyParcel status events from authenticated Tracking Status responses.
- Produces: monotonic shipment projection, order transitions, and the five canonical Shipping queues.

- [ ] **Step 1: Add failing out-of-order and exception/read-model tests**

```php
public function test_late_non_terminal_event_cannot_regress_a_delivered_easyparcel_shipment(): void
{
    [$customer, $order] = $this->processingOrder('JW-TRACK-MONOTONIC');
    $shipment = $this->easyParcelShipment($order, 'AWB-MONOTONIC');

    app(ShipmentService::class)->recordEasyParcelEvents($shipment, 'AWB-MONOTONIC', [
        [
            'external_event_id' => 'easyparcel:delivered-monotonic',
            'status' => 'delivered',
            'occurred_at' => CarbonImmutable::parse('2026-08-29 12:00:00', 'UTC'),
            'description' => 'Delivered',
            'location' => 'Recipient',
        ],
        [
            'external_event_id' => 'easyparcel:late-in-transit-monotonic',
            'status' => 'in_transit',
            'occurred_at' => CarbonImmutable::parse('2026-08-29 13:00:00', 'UTC'),
            'description' => 'Late courier scan',
            'location' => 'Sorting hub',
        ],
    ]);

    self::assertSame('delivered', $shipment->fresh()->status);
    self::assertSame('delivered', $order->fresh()->status);
    self::assertCount(2, $shipment->events()->get());
}

public function test_returned_easyparcel_shipment_is_projected_into_exceptions_without_commerce_side_effects(): void
{
    [$customer, $order] = $this->processingOrder('JW-TRACK-RETURNED');
    $shipment = $this->easyParcelShipment($order, 'AWB-RETURNED');
    app(ShipmentService::class)->recordEasyParcelEvents($shipment, 'AWB-RETURNED', [[
        'external_event_id' => 'easyparcel:returned-read-model',
        'status' => 'returned',
        'occurred_at' => CarbonImmutable::parse('2026-08-29 12:00:00', 'UTC'),
        'description' => 'Returned to sender',
        'location' => 'Origin hub',
    ]]);

    $row = app(ShipmentDataService::class)->collection()->firstWhere('id', (string) $shipment->id);
    self::assertSame('returned', data_get($row, 'status'));
    self::assertSame('processing', $order->fresh()->status);
    self::assertSame(0, $order->refunds()->count());
}
```

- [ ] **Step 2: Run the new lifecycle tests and verify RED**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelTrackingLifecycleTest.php --filter="monotonic|returned"
```

Expected: at least the delivered regression or Exceptions projection assertion FAILS.

- [ ] **Step 3: Enforce terminal precedence in `ShipmentService`**

Use explicit rank/terminal rules rather than relying only on the latest timestamp:

```php
private const SHIPMENT_STATUS_RANK = [
    'pending' => 10,
    'shipped' => 20,
    'in_transit' => 30,
    'failed_delivery' => 40,
    'returned' => 50,
    'delivered' => 60,
];
```

Append every valid unique event to history. For the shipment summary, keep `delivered` terminal; keep `returned` terminal unless a verified later Delivered event exists; never project `provider_update` as the summary status. Continue advancing only authenticated `shipped`/`in_transit` events from Processing to Shipped, and only authenticated Delivered to Delivered.

- [ ] **Step 4: Align the Shipping queue read model**

Keep these predicates:

```text
Pending AWB: processing order without an EasyParcel shipment, or accepted booking without AWB
Ready to Ship: EasyParcel shipment has AWB and no collected/in-transit/delivered/exception terminal event
In Transit: shipped or in_transit provider lifecycle
Delivered: delivered
Exceptions: failed_delivery or returned
```

Change the existing Exceptions tab filter/count to include both `failed_delivery` and `returned`. Do not treat unknown `provider_update` as Delivered, Returned, or a separate operational state.

- [ ] **Step 5: Run lifecycle, fulfillment, Account, and Order Tracking tests**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/Admin/EasyParcelFulfillmentApiTest.php tests/Feature/OrderTrackingReadModelTest.php tests/Feature/CustomerAccountOperationsTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php
```

Expected: all selected tests PASS and no refund/restock/payment side effects appear.

- [ ] **Step 6: Run Admin typecheck/build and commit Task 5**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce\resources\admin
npm run lint
npm run build
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
git add -- app/Services/ShipmentService.php app/Services/Admin/ShipmentDataService.php resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/Admin/EasyParcelFulfillmentApiTest.php public/build-admin
git commit -m "fix: keep EasyParcel lifecycle projection monotonic"
```

---

### Task 6: Operational Webhook Audit in the Existing Shipping UI

**Files:**
- Create: `app/Services/Admin/EasyParcelWebhookReceiptDataService.php`
- Create: `app/Http/Controllers/Admin/EasyParcelWebhookReceiptApiController.php`
- Create: `resources/admin/src/integration/easyParcelWebhookData.ts`
- Create: `resources/admin/src/integration/easyParcelWebhookData.test.ts`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/AdminAppController.php`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `resources/admin/src/types/shipping.ts`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Modify: `resources/admin/src/components/shipping/ShippingDashboard.tsx`
- Modify: `resources/admin/src/components/shipping/TrackingAndWebhooksTab.tsx`
- Modify: `tests/Feature/Admin/ShipmentApiTest.php`
- Modify: `resources/admin/src/components/shipping/ShippingDashboard.test.ts`

**Interfaces:**
- Consumes: sanitized `ShippingWebhookReceipt` rows and existing Shipping view permission.
- Produces: `GET /admin/api/shipping/webhook-receipts`, `EasyParcelWebhookReceiptRow[]`, and truthful Webhook Audit status in the existing tab.

- [ ] **Step 1: Write failing protected-read-model tests**

```php
public function test_authorized_shipping_viewer_sees_only_safe_webhook_receipt_fields(): void
{
    $admin = $this->superAdmin();
    $receipt = ShippingWebhookReceipt::query()->create([
        'provider' => 'easyparcel',
        'environment' => 'sandbox',
        'topic' => 'shipment.tracking.update',
        'processing_state' => 'processed',
        'payload_hash' => str_repeat('a', 64),
        'correlation_snapshot' => [
            'awb_number' => 'AWB-SAFE-01',
            'receiver_email' => 'must-not-serialize@example.test',
        ],
        'received_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson('/admin/api/shipping/webhook-receipts')
        ->assertOk()
        ->assertJsonPath('data.0.topic', 'shipment.tracking.update')
        ->assertJsonMissing(['receiver_email' => 'must-not-serialize@example.test'])
        ->assertJsonMissing(['payloadHash' => str_repeat('a', 64)]);
}
```

Also assert an unauthenticated request redirects/rejects and a non-admin receives 403.

- [ ] **Step 2: Run API test and verify RED**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php --filter=webhook_receipt
```

Expected: FAIL with route not found.

- [ ] **Step 3: Implement the safe Admin receipt projection**

Return at most 100 newest rows with only:

```php
[
    'id' => (string) $receipt->id,
    'topic' => $receipt->topic,
    'state' => $receipt->processing_state,
    'shipmentId' => $receipt->shipment_id === null ? null : (string) $receipt->shipment_id,
    'orderNumber' => $receipt->shipment?->order?->number,
    'trackingNumber' => data_get($receipt->correlation_snapshot, 'awb_number'),
    'receivedAt' => $receipt->received_at?->toIso8601String(),
    'processedAt' => $receipt->processed_at?->toIso8601String(),
    'attemptCount' => $receipt->attempt_count,
    'errorCode' => $receipt->last_error,
]
```

Do not expose the endpoint token, raw body, payload hash, provider credentials, addresses, email, or phone.

- [ ] **Step 4: Implement the typed frontend client**

```ts
export interface EasyParcelWebhookReceiptRow {
  id: string;
  topic: string;
  state: 'received' | 'queued' | 'processed' | 'retrying' | 'failed' | 'ignored' | 'unmatched';
  shipmentId: string | null;
  orderNumber: string | null;
  trackingNumber: string | null;
  receivedAt: string;
  processedAt: string | null;
  attemptCount: number;
  errorCode: string | null;
}

export const fetchEasyParcelWebhookReceipts = async (
  bootstrap: LaravelAdminBootstrap,
): Promise<EasyParcelWebhookReceiptRow[]> => {
  const response = await window.fetch(bootstrap.easyParcelWebhookReceiptsUrl, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) throw new Error('EasyParcel webhook audit could not be loaded.');
  const payload = await response.json() as { data: EasyParcelWebhookReceiptRow[] };
  return payload.data;
};
```

Add `easyParcelWebhookReceiptsUrl` to the Admin bootstrap payload and types.

- [ ] **Step 5: Bind the existing Tracking & Webhooks visual to truth**

Retain its cards, grid, typography, and dark/light classes. Replace:

- “provider webhooks remain unavailable” with the real configured endpoint state;
- “Manual audit mode” with a truthful provider/queue badge when connected;
- the fictional live webhook simulator claim with the existing clearly labelled Manual Event Entry;
- the footer's hardcoded “Not connected” with the latest real receipt/reconciliation state.

Render the sanitized receipt rows in the existing event-feed language. Keep provider receipts visually distinct from shipment lifecycle events through a small existing badge style; do not add a new page or redesign the tab.

- [ ] **Step 6: Run backend/frontend tests, typecheck, and build**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php tests/Feature/EasyParcelWebhookIngressTest.php tests/Feature/EasyParcelWebhookReconciliationTest.php
Set-Location resources\admin
npm test -- --run src/integration/easyParcelWebhookData.test.ts src/components/shipping/ShippingDashboard.test.ts
npm run lint
npm run build
```

Expected: all selected tests PASS and build exits 0.

- [ ] **Step 7: Commit Task 6**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
git add -- app/Services/Admin/EasyParcelWebhookReceiptDataService.php app/Http/Controllers/Admin/EasyParcelWebhookReceiptApiController.php resources/admin/src/integration/easyParcelWebhookData.ts resources/admin/src/integration/easyParcelWebhookData.test.ts routes/web.php app/Http/Controllers/Admin/AdminAppController.php resources/admin/src/types/index.ts resources/admin/src/types/shipping.ts resources/admin/src/context/AdminContext.tsx resources/admin/src/components/shipping/ShippingDashboard.tsx resources/admin/src/components/shipping/TrackingAndWebhooksTab.tsx tests/Feature/Admin/ShipmentApiTest.php resources/admin/src/components/shipping/ShippingDashboard.test.ts public/build-admin
git commit -m "feat: show EasyParcel webhook reconciliation audit"
```

---

### Task 7: Acceptance Runbook, Regression Gate, and Same-SHA Release

**Files:**
- Create: `docs/operations/easyparcel-sandbox-acceptance.md`
- Verify: all files from Tasks 1–6

**Interfaces:**
- Consumes: package confirmation, quote/booking, webhook receipt, queued reconciliation, lifecycle projection, and audit UI from Tasks 1–6.
- Produces: reproducible acceptance evidence for localhost, GitHub `main`, Hostinger, and the EasyParcel Developer Hub.

- [ ] **Step 1: Write the operations runbook**

The document must contain these exact evidence classes and never merge them:

```text
LOCAL_FIXTURE       official payload contract using Http::fake
LOCAL_BROWSER       authenticated localhost UI using local MySQL application data
SANDBOX_API         real EasyParcel Sandbox OAuth/quote/submit/details/tracking response
SANDBOX_WEBHOOK     EasyParcel Developer Hub endpoint validation/test delivery
LIVE_CARRIER        separately approved physical parcel event from an actual courier
```

Include the accepted order number, backup filename, pre-test status, selected service, EasyParcel shipment/order reference, AWB presence, webhook receipt ids, resulting shipment/order state, screenshots, and rollback action. Explicitly state that `LOCAL_FIXTURE` and `SANDBOX_WEBHOOK` do not prove physical delivery.

- [ ] **Step 2: Run the complete isolated EasyParcel and shipment test set**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan test tests/Feature/Admin/EasyParcelPlatformOauthTest.php tests/Feature/Admin/EasyParcelProviderApiTest.php tests/Feature/Admin/EasyParcelFulfillmentApiTest.php tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/EasyParcelWebhookIngressTest.php tests/Feature/EasyParcelWebhookReconciliationTest.php tests/Feature/Admin/ShipmentApiTest.php
```

Expected: all selected tests PASS against `jw_ecommerce_testing`.

- [ ] **Step 3: Run the full safe regression suite and classify unrelated failures**

```powershell
C:\xampp\php\php.exe artisan test
```

Expected: PASS. If a failure is unrelated, record the exact test, failure, and pre-existing evidence; do not suppress it or claim the full suite is green.

- [ ] **Step 4: Run complete Admin verification**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce\resources\admin
npm test -- --run
npm run lint
npm run build
```

Expected: Vitest PASS, TypeScript exits 0, Vite exits 0.

- [ ] **Step 5: Back up localhost data and perform authenticated browser acceptance**

```powershell
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupDir = 'C:\xampp\htdocs\jwstudio-ecommerce\storage\app\backups'
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
& 'C:\xampp\mysql\bin\mysqldump.exe' -u root jw_ecommerce | Out-File -Encoding utf8 "$backupDir\jw_ecommerce-before-easyparcel-$stamp.sql"
```

In Chrome at `http://127.0.0.1:8500/admin/shipping`:

1. sign in as an authorized admin;
2. select a recorded development Processing order;
3. confirm/edit weight and L/W/H;
4. prove the missing-dimensions block disappears and a real request can be formed;
5. verify quote expiry and COD service restrictions in UI;
6. use HTTP fakes only for local lifecycle UI states, labelled `LOCAL_BROWSER` plus fixture;
7. verify the same shipment timeline in Admin Order, Account, and `/order-tracking` after refresh/new session.

- [ ] **Step 6: Commit the runbook and final local acceptance fixes**

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
git add -- docs/operations/easyparcel-sandbox-acceptance.md
git commit -m "docs: add EasyParcel sandbox acceptance runbook"
git status --short
```

Expected: only the user-owned `M AGENTS.md` remains; no application file is uncommitted.

- [ ] **Step 7: Push the accepted SHA to GitHub**

```powershell
$acceptedSha = git rev-parse HEAD
git push origin main
$remoteSha = git ls-remote origin refs/heads/main | ForEach-Object { ($_ -split "`t")[0] }
if ($remoteSha -ne $acceptedSha) { throw "GitHub main does not match accepted localhost SHA." }
```

- [ ] **Step 8: Deploy the same SHA to Hostinger**

```powershell
$acceptedSha = git rev-parse HEAD
ssh -p 65002 u762348670@145.79.26.34 "cd /apps/jwecommerce && git fetch origin main && git checkout --detach $acceptedSha && /usr/bin/php artisan migrate --force && /usr/bin/php artisan optimize:clear && /usr/bin/php artisan optimize"
$hostSha = ssh -p 65002 u762348670@145.79.26.34 "git -C /apps/jwecommerce rev-parse HEAD"
if ($hostSha.Trim() -ne $acceptedSha.Trim()) { throw "Hostinger does not match accepted localhost SHA." }
```

If `/apps/jwecommerce` is not a Git checkout, stop before changing files and convert the established deployment mechanism into a same-SHA checkout; do not fall back to copying an uncommitted working directory.

- [ ] **Step 9: Configure the deployed webhook without exposing its token**

Generate a 32-byte random production endpoint token directly on Hostinger, store it only in `/apps/jwecommerce/.env`, then clear/rebuild Laravel configuration cache. Register the resulting HTTPS URL in EasyParcel Developer Hub for Topics 1–4. Do not paste the token into Git, this plan, screenshots, logs, or chat.

Verify:

```powershell
curl.exe -sS -o NUL -w "%{http_code}" https://ecom.jwaistudio.online/up
```

Expected: `200`.

- [ ] **Step 10: Perform real Sandbox API and webhook acceptance**

At `https://ecom.jwaistudio.online/admin/shipping`:

1. use a recorded development order with complete package/address/payment data;
2. request real Sandbox quotations;
3. select one returned prepaid courier and submit exactly once;
4. record provider shipment/order reference, AWB/label state, and Ready to Ship projection;
5. repeat with COD only if the returned service supports the exact amount;
6. issue EasyParcel Developer Hub test deliveries for Topics 1–4;
7. prove each receipt is de-duplicated, queued, reconciled, and visible in the audit tab;
8. prove authenticated provider response, not the webhook payload, owns every mutation;
9. wait/run the scheduler once to prove polling fallback;
10. label all static status progression as simulated because Sandbox will not physically advance.

- [ ] **Step 11: Record release evidence and stop before Live**

Add the accepted SHA and evidence locations to the runbook only if it contains no secrets or customer PII, then commit/push/deploy that documentation-only commit through the same SHA sequence. Report Sandbox closure separately from Live closure. Do not authorize a Live account, pay postage, or ship a parcel without a new explicit user approval.

---

## Final Verification Checklist

- [ ] Package fields are employee-editable, validated, and quote-invalidating without rewriting product/order snapshots.
- [ ] Real Sandbox quote and booking use only server-side OAuth credentials.
- [ ] Duplicate clicks/retries create one provider booking and one shipment.
- [ ] AWB-null and uncertain outcomes enter reconciliation, never automatic resubmit.
- [ ] Webhook endpoint persists no raw PII body and returns no commerce data.
- [ ] Duplicate/unknown/malformed/oversized webhook inputs cannot mutate orders.
- [ ] Webhook hints schedule OAuth-authenticated Details/Tracking reconciliation.
- [ ] Existing fifteen-minute polling still passes and acts as fallback.
- [ ] Delivered is monotonic; exception/return paths never refund/restock/cancel automatically.
- [ ] Admin Shipping, Admin Order, Account, and Order Tracking share one timeline truth.
- [ ] Existing Google AI Studio UI structure and dark/light styling remain intact.
- [ ] Automated tests use `jw_ecommerce_testing`, not the development database.
- [ ] Localhost, GitHub `main`, and Hostinger report the same accepted SHA.
- [ ] Sandbox evidence is not described as real carrier delivery.
- [ ] Live pilot remains blocked on a separate explicit approval.
