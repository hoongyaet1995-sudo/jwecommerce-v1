# Order Tracking Operational Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a source-faithful, privacy-safe Order Tracking preview backed by Laravel/MySQL and reconcile guest purchase ownership into My Account.

**Architecture:** A dedicated service persists hashed, expiring verification attempts and grants a session-scoped read capability only after code validation. A read model adapts canonical order, history, shipment and event data into the ZIP result state. Checkout and registration preserve a one-to-one Customer/email identity without altering historical order snapshots.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade, Alpine/vanilla browser adapter, PHPUnit, Vite/Tailwind.

**Spec:** `docs/superpowers/specs/2026-08-27-order-tracking-operational-closure-design.md`

## Global Constraints

- Preserve the selected ZIP DOM/classes/copy/responsive state structure; replace mock data ownership only.
- No browser/localStorage mock orders, addresses, codes or generated tracking events.
- Codes are hashed, expire in 10 minutes, are single-use and are never returned by production endpoints.
- Lookup and verify failures must not disclose order/account existence.
- MySQL historical order snapshots remain immutable.
- Build only `/order-tracking/preview` until explicit official-route cutover approval.

---

### Task 1: Verification persistence and service contract

**Files:**
- Create: `database/migrations/2026_08_27_000000_create_order_tracking_verifications_table.php`
- Create: `app/Models/OrderTrackingVerification.php`
- Create: `app/Services/OrderTrackingService.php`
- Test: `tests/Feature/OrderTrackingVerificationTest.php`

**Interfaces:**
- Produces `OrderTrackingService::request(string $number, string $email): void`, `verify(string $attemptId, string $code): bool`, and `verifiedOrderId(string $attemptId): ?int`.
- The controller stores only the attempt UUID in the session; the service stores an order ID, normalized-email SHA-256 hash, code hash, expiry, sent/verified/consumed timestamps and request metadata.

- [ ] **Step 1: Write the failing test**

```php
public function test_valid_order_and_email_create_a_hashed_expiring_attempt_without_exposing_the_code(): void
{
    Mail::fake();
    $order = $this->orderFor('guest@example.test');

    $this->post(route('order-tracking.request'), ['order_number' => $order->number, 'email' => 'GUEST@example.test'])
        ->assertRedirect(route('order-tracking.preview', ['step' => 'verify']));

    $this->assertDatabaseHas('order_tracking_verifications', ['order_id' => $order->id]);
    self::assertNotSame('123456', OrderTrackingVerification::firstOrFail()->code_hash);
    Mail::assertQueued(OrderTrackingCodeMail::class);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingVerificationTest.php --filter=valid_order`

Expected: FAIL because routes/service/model/migration do not exist.

- [ ] **Step 3: Write minimal implementation**

Create an indexed attempt model and service. Normalize with `Str::lower(trim($email))` and `Str::upper(trim($number))`; generate `random_int(100000, 999999)`, hash with `Hash::make`, store an expiry at `now()->addMinutes(10)`, and queue the code only when the exact order/email pair exists. The controller always redirects to the verification state.

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingVerificationTest.php --filter=valid_order`

Expected: PASS.

### Task 2: Privacy, expiry, throttle and verified session contract

**Files:**
- Modify: `app/Services/OrderTrackingService.php`
- Create: `app/Http/Controllers/OrderTrackingController.php`
- Modify: `routes/web.php`
- Create: `app/Mail/OrderTrackingCodeMail.php`
- Create: `resources/views/mail/order-tracking-code.blade.php`
- Test: `tests/Feature/OrderTrackingVerificationTest.php`

**Interfaces:**
- Produces public POST routes `order-tracking.request`, `order-tracking.verify`, and `order-tracking.resend` protected by a narrow `throttle:order-tracking` limiter.
- A success response writes `order_tracking.verified_attempt_id` to session; all failure responses return the same source generic message.

- [ ] **Step 1: Write the failing tests**

```php
public function test_mismatched_lookup_uses_the_same_verify_redirect_and_never_creates_an_order_payload(): void
{
    Mail::fake();
    $response = $this->post(route('order-tracking.request'), ['order_number' => 'JW-UNKNOWN-1000', 'email' => 'nobody@example.test']);
    $response->assertRedirect(route('order-tracking.preview', ['step' => 'verify']));
    Mail::assertNothingQueued();
}

public function test_expired_or_wrong_code_cannot_grant_a_verified_order_session(): void
{
    $attempt = $this->expiredAttemptFor($this->orderFor('guest@example.test'));
    $this->withSession(['order_tracking.attempt_id' => $attempt->public_id])
        ->post(route('order-tracking.verify'), ['code' => '123456'])
        ->assertSessionMissing('order_tracking.verified_attempt_id');
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingVerificationTest.php --filter="mismatched|expired"`

Expected: FAIL because the generic route/session contract is absent.

- [ ] **Step 3: Write minimal implementation**

Define `RateLimiter::for('order-tracking', ...)` in `AppServiceProvider`; never return an order identifier from lookup. Verify hashes only for non-expired, unconsumed attempts, mark a successful attempt consumed, and redirect invalid requests with the ZIP generic error. Reuse the exact source mail/title copy but never log/render the raw code outside mail/testing transport.

- [ ] **Step 4: Run tests to verify they pass**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingVerificationTest.php`

Expected: PASS.

### Task 3: Canonical order tracking read model

**Files:**
- Create: `app/Domain/Orders/OrderTrackingPayloadFactory.php`
- Modify: `app/Http/Controllers/OrderTrackingController.php`
- Test: `tests/Feature/OrderTrackingReadModelTest.php`

**Interfaces:**
- Produces `OrderTrackingPayloadFactory::for(Order $order): array` with source-ready customer-safe item, address, payment status, shipment and timeline fields.
- Consumes eager-loaded `items`, `addresses`, `payments`, `statusHistory`, `shipments.events`, `taxLines`.

- [ ] **Step 1: Write the failing test**

```php
public function test_verified_payload_uses_persisted_shipment_events_and_never_invents_an_eta(): void
{
    $order = $this->shippedOrderWithManualShipment();
    $payload = app(OrderTrackingPayloadFactory::class)->for($order);

    self::assertSame('Manual Warehouse', data_get($payload, 'tracking.carrier'));
    self::assertSame('TRACK-1001', data_get($payload, 'tracking.number'));
    self::assertNull(data_get($payload, 'tracking.estimatedDelivery'));
    self::assertContains('Manually dispatched via Manual Warehouse · TRACK-1001', data_get($payload, 'timeline.*.description'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingReadModelTest.php`

Expected: FAIL because the factory does not exist.

- [ ] **Step 3: Write minimal implementation**

Map canonical lifecycle and event rows in chronological order. Use a public-safe payment label/status only; never expose gateway references, payment payload, customer phone, or internal note. Only render carrier/tracking/location/ETA fields when persisted. Return a no-tracking state rather than a default carrier or projected delivery date.

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingReadModelTest.php`

Expected: PASS.

### Task 4: Source-faithful Blade preview and browser adapter

**Files:**
- Create: `resources/views/storefront/order-tracking.blade.php`
- Modify: `resources/js/app.js`
- Modify: `app/Http/Controllers/OrderTrackingController.php`
- Test: `tests/Feature/OrderTrackingPreviewTest.php`

**Interfaces:**
- Preview GET route renders the selected ZIP lookup/verify/result DOM with server-provided state and data attributes.
- The adapter owns only code-cell focus/paste/countdown and form loading UI; all business state round-trips through Laravel POSTs.

- [ ] **Step 1: Write the failing test**

```php
public function test_preview_preserves_tracking_lookup_dom_without_demo_controls_or_fixture_data(): void
{
    $this->get(route('order-tracking.preview'))
        ->assertOk()
        ->assertSee('Track Your Order')
        ->assertSee('ORDER SUPPORT')
        ->assertSee('data-order-tracking-code-cell', false)
        ->assertDontSee('Quick Test Autofill')
        ->assertDontSee('DEFAULT_ORDER_HISTORY');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingPreviewTest.php`

Expected: FAIL because the preview route/view do not exist.

- [ ] **Step 3: Write minimal implementation**

Port the approved selected page hierarchy/classes/copy. Reuse `<x-storefront.nav>` and `<x-storefront.footer>` once rather than duplicate the generated app shell. Keep mock-demo controls excluded under the approved operational variance, but retain form/verification/result state structure. Add only data attributes and CSRF/form directives required for Laravel binding.

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingPreviewTest.php`

Expected: PASS.

### Task 5: Guest-to-account Customer reconciliation

**Files:**
- Modify: `app/Services/CheckoutService.php`
- Modify: `app/Http/Controllers/CustomerAuthController.php`
- Test: `tests/Feature/CustomerGuestOrderReconciliationTest.php`

**Interfaces:**
- Produces one Customer record per normalized email. `CheckoutService` uses that Customer. Registration links the existing guest Customer to the new User instead of creating another Customer.

- [ ] **Step 1: Write the failing test**

```php
public function test_registration_claims_the_existing_guest_customer_and_its_orders_by_email(): void
{
    $guest = Customer::factory()->create(['email' => 'guest@example.test', 'user_id' => null]);
    $order = Order::factory()->create(['customer_id' => $guest->id, 'email' => 'guest@example.test']);

    $this->post(route('customer.register.store'), $this->registrationPayload('guest@example.test'))
        ->assertRedirect('/account');

    self::assertSame(1, Customer::query()->whereRaw('lower(email) = ?', ['guest@example.test'])->count());
    self::assertSame($guest->id, $order->fresh()->customer_id);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerGuestOrderReconciliationTest.php`

Expected: FAIL because registration currently creates a second Customer.

- [ ] **Step 3: Write minimal implementation**

Within one database transaction, create the User, lock the normalized-email Customer row, attach the User to it if it has no user, and only create a Customer when none exists. Preserve historical order/address snapshots and do not overwrite guest profile fields from checkout.

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerGuestOrderReconciliationTest.php`

Expected: PASS.

### Task 6: Build and acceptance evidence

**Files:**
- Create: `docs/superpowers/reports/2026-08-27-order-tracking-fidelity-evidence.md`
- Test: `tests/Feature/OrderTrackingVerificationTest.php`, `tests/Feature/OrderTrackingReadModelTest.php`, `tests/Feature/OrderTrackingPreviewTest.php`, `tests/Feature/CustomerGuestOrderReconciliationTest.php`

- [ ] **Step 1: Run targeted automated verification**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OrderTrackingVerificationTest.php tests/Feature/OrderTrackingReadModelTest.php tests/Feature/OrderTrackingPreviewTest.php tests/Feature/CustomerGuestOrderReconciliationTest.php`

Expected: PASS.

- [ ] **Step 2: Build assets**

Run: `npm run build`

Expected: PASS.

- [ ] **Step 3: Capture browser evidence**

Capture source and preview desktop/mobile lookup and verification states, plus real Admin dispatch -> refreshed verified result proof. Record deviations, mail transport truth and preview URL in the evidence matrix.

- [ ] **Step 4: Request official cutover authorization**

Do not register official `/order-tracking` until the user accepts preview evidence and supplies/accepts the mail-delivery boundary.
