# Customer Coupon Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Admin-managed coupon-code campaigns the single operational truth for the customer account Voucher tab, cart application, checkout, and customer use history.

**Architecture:** Create a focused eligibility service and account view-model factory over `coupons` and `coupon_usages`. A protected account action applies a coupon only to the existing server-side cart session; checkout remains the locked finalizer that writes use-ledger and immutable order snapshots. Existing account Blade DOM/Tailwind remains intact and receives real view-model bindings only.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade, Alpine.js, PHPUnit, existing Admin React Coupon API, in-app Browser.

**Spec:** `docs/superpowers/specs/2026-09-02-customer-coupon-closure-design.md`

## Global Constraints

- Work only in `C:\xampp\htdocs\jwstudio-ecommerce`; localhost is first acceptance truth.
- Keep `coupons`, `coupon_usages`, and order coupon snapshots as MySQL/Laravel truth. Do not create a customer Voucher Wallet.
- Preserve the approved Google AI Studio account DOM, classes, assets, copy ownership, responsiveness, icons, and hierarchy. This is L0/L1 binding only.
- Do not reset, drop, import over, or destructively alter `jw_ecommerce`.
- Automated tests use isolated databases. Browser validation uses only controlled local session data.
- Do not create real charges, shipping bookings, pickups, wallet changes, commits, GitHub pushes, or Hostinger deployments.
- Application remains session-only until checkout. Only `CheckoutService` writes `coupon_usages`, increments `usage_count`, and snapshots the order.
- Do not update P1-016; it remains partial until its separate browser matrix is rerun.

## File structure

- Create `app/Services/CustomerCouponEligibilityService.php`: current-rule, customer-quota, cart-threshold, and session-application contract.
- Create `app/Domain/Accounts/CustomerCouponViewModelFactory.php`: account card/tab view model from coupons and owned usage records.
- Create `app/Http/Controllers/CustomerCouponController.php`: protected account apply endpoint.
- Modify `app/Domain/Accounts/OriginalAccountPayloadFactory.php`: add `vouchers` and remove only the vouchers gap.
- Modify `app/Http/Controllers/CartController.php`: consume shared validation while preserving the current cart route and flash contract.
- Modify `routes/web.php`: add one protected account-coupon route.
- Modify `resources/views/storefront/account.blade.php`: replace mock data and Alpine-only business behavior with real binding forms/statuses.
- Create `tests/Feature/CustomerCouponClosureTest.php`; modify `tests/Feature/OriginalAccountWorkspacePreviewTest.php`.
- Create `docs/superpowers/reports/2026-09-02-customer-coupon-closure-mapping.md` only after verification.

---

### Task 1: Establish service contracts with isolated failing tests

**Files:**
- Create: `tests/Feature/CustomerCouponClosureTest.php`
- Create: `app/Services/CustomerCouponEligibilityService.php`

**Interfaces:**
- Produces `CustomerCouponEligibilityService::availabilityFor(Customer $customer, Coupon $coupon): CustomerCouponAvailability`.
- Produces `CustomerCouponEligibilityService::apply(Customer $customer, string $code, Request $request): CustomerCouponApplicationResult`.
- Application may write only `coupon_code` to the passed server-side session.

- [ ] **Step 1: Add a failing test for valid account application and no premature ledger mutation.**

```php
public function test_customer_can_apply_a_current_coupon_to_their_cart_session_without_creating_usage(): void
{
    [$customer, $variant] = $this->customerWithVariant();
    Coupon::query()->create([
        'code' => 'ACCOUNT10', 'name' => 'Account ten',
        'discount_type' => 'percentage', 'value' => 10,
        'min_spend' => 10000, 'per_customer_limit' => 1, 'is_active' => true,
    ]);

    $this->actingAs($customer->user, 'customer')
        ->withSession(['cart' => [$variant->id => 1]])
        ->post(route('customer.coupons.apply'), ['code' => 'account10'])
        ->assertRedirect(route('customer.account'))
        ->assertSessionHas('coupon_code', 'ACCOUNT10')
        ->assertSessionHas('coupon_status.type', 'success');

    $this->assertDatabaseCount('coupon_usages', 0);
    $this->assertDatabaseHas('coupons', ['code' => 'ACCOUNT10', 'usage_count' => 0]);
}
```

- [ ] **Step 2: Add failing invalid, below-threshold, and personal-limit tests.**

```php
public function test_personally_exhausted_coupon_cannot_replace_the_existing_cart_coupon(): void
{
    [$customer, $variant] = $this->customerWithVariant();
    $coupon = Coupon::factory()->create(['code' => 'ONCEONLY', 'per_customer_limit' => 1, 'is_active' => true]);
    $this->recordCouponUsage($coupon, $customer);

    $this->actingAs($customer->user, 'customer')
        ->withSession(['cart' => [$variant->id => 1], 'coupon_code' => 'KEEP10'])
        ->post(route('customer.coupons.apply'), ['code' => 'ONCEONLY'])
        ->assertRedirect(route('customer.account'))
        ->assertSessionHas('coupon_code', 'KEEP10')
        ->assertSessionHas('coupon_status.type', 'error');
}
```

- [ ] **Step 3: Run the test file and confirm it fails before production work.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerCouponClosureTest.php --compact`

Expected: FAIL because `customer.coupons.apply` and the service contract do not exist.

- [ ] **Step 4: Implement result objects and the minimal service.**

```php
final readonly class CustomerCouponApplicationResult
{
    public function __construct(public bool $applied, public string $message, public ?string $code = null) {}
}

final class CustomerCouponEligibilityService
{
    public function availabilityFor(Customer $customer, Coupon $coupon): CustomerCouponAvailability;
    public function apply(Customer $customer, string $code, Request $request): CustomerCouponApplicationResult;
}
```

Use case-insensitive code lookup; reject missing, inactive, out-of-window, globally exhausted, customer-exhausted, or cart-below-minimum codes with stable messages. Match usages by `customer_id` first and normalized email only for legacy guest usage. Never mutate coupon counters or ledger rows.

- [ ] **Step 5: Run the focused test file.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerCouponClosureTest.php --compact`

Expected: PASS with isolated test DB.

- [ ] **Step 6: Inspect the task diff.**

Run: `git diff --check; git diff -- app/Services/CustomerCouponEligibilityService.php tests/Feature/CustomerCouponClosureTest.php`

Expected: no whitespace error and no migration.

### Task 2: Add the protected account action and preserve cart behavior

**Files:**
- Create: `app/Http/Controllers/CustomerCouponController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/CartController.php`
- Modify: `tests/Feature/CustomerCouponClosureTest.php`

**Interfaces:**
- Produces protected named route `customer.coupons.apply` at `POST /account/coupons/apply`.
- Redirects only to `customer.account`; caller-controlled URLs are ignored.
- Existing `POST /cart/coupon` remains compatible for cart/checkout callers.

- [ ] **Step 1: Add failing route/authorization tests.**

```php
public function test_guest_cannot_apply_a_coupon_from_the_account_endpoint(): void
{
    $this->post(route('customer.coupons.apply'), ['code' => 'ACCOUNT10'])
        ->assertRedirect(route('customer.login'));
}

public function test_account_coupon_apply_never_honors_a_supplied_return_url(): void
{
    [$customer, $variant] = $this->customerWithVariant();
    Coupon::factory()->create(['code' => 'SAFE10', 'is_active' => true]);

    $this->actingAs($customer->user, 'customer')->withSession(['cart' => [$variant->id => 1]])
        ->post(route('customer.coupons.apply'), ['code' => 'SAFE10', 'return_to' => 'https://invalid.example'])
        ->assertRedirect(route('customer.account'));
}
```

- [ ] **Step 2: Run the focused tests.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerCouponClosureTest.php --compact`

Expected: FAIL until the protected route/controller are registered.

- [ ] **Step 3: Register and implement the action.**

```php
Route::post('/coupons/apply', [CustomerCouponController::class, 'apply'])->name('coupons.apply');

public function apply(Request $request, CustomerCouponEligibilityService $coupons): RedirectResponse
{
    $data = $request->validate(['code' => ['required', 'string', 'max:255']]);
    $result = $coupons->apply($request->user('customer')->customer, $data['code'], $request);

    return redirect()->route('customer.account')->with('coupon_status', [
        'type' => $result->applied ? 'success' : 'error',
        'message' => $result->message,
    ]);
}
```

Keep the route inside the existing customer-auth account group. Adapt `CartController::applyCoupon()` to call the service and retain its `coupon_error` plus cart/checkout redirect behavior.

- [ ] **Step 4: Run account plus cart/checkout regressions.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerCouponClosureTest.php tests/Feature/CheckoutCommerceRulesTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Verify route scope.**

Run: `C:\xampp\php\php.exe artisan route:list --name=customer.coupons.apply; git diff --check`

Expected: one protected account action and no migration.

### Task 3: Replace the account voucher mock owner with a real view model

**Files:**
- Create: `app/Domain/Accounts/CustomerCouponViewModelFactory.php`
- Modify: `app/Domain/Accounts/OriginalAccountPayloadFactory.php`
- Modify: `resources/views/storefront/account.blade.php`
- Modify: `tests/Feature/CustomerCouponClosureTest.php`
- Modify: `tests/Feature/OriginalAccountWorkspacePreviewTest.php`

**Interfaces:**
- `accountPayload['vouchers']` contains `eligible`, `used`, `expired`, and `counts`.
- Cards contain code, title, description, discount type/value, minimum spend, truthful status, and no other customer PII.
- Remove only `vouchers` from `accountPayload['gaps']`.

- [ ] **Step 1: Add failing payload ownership and mock-removal tests.**

```php
public function test_account_payload_has_only_this_customers_real_coupon_usage_history(): void
{
    [$owner] = $this->customerWithVariant();
    [$other] = $this->customerWithVariant();
    $coupon = Coupon::factory()->create(['code' => 'REAL20', 'per_customer_limit' => 2, 'is_active' => true]);
    $this->recordCouponUsage($coupon, $owner);
    $this->recordCouponUsage($coupon, $other);

    $payload = app(OriginalAccountPayloadFactory::class)->for($owner->fresh());

    self::assertSame(1, data_get($payload, 'vouchers.counts.used'));
    self::assertSame('REAL20', data_get($payload, 'vouchers.used.0.code'));
    self::assertNotContains('vouchers', $payload['gaps']);
}

public function test_account_never_renders_legacy_mock_codes_or_fixed_coupon_counts(): void
{
    [$customer] = $this->customerWithVariant();

    $this->actingAs($customer->user, 'customer')->get(route('customer.account'))
        ->assertDontSee('VIP20-ATELIER')
        ->assertDontSee('Eligible & Active (4)', false)
        ->assertSee('Eligible & Active (0)', false);
}
```

- [ ] **Step 2: Run the account tests before implementation.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerCouponClosureTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php --compact`

Expected: FAIL because payload and Blade own static voucher data.

- [ ] **Step 3: Implement the view-model factory and payload binding.**

```php
return [
    'eligible' => $eligible->values()->all(),
    'used' => $usages->map(fn (CouponUsage $usage) => $this->usedCard($usage))->values()->all(),
    'expired' => $unavailable->values()->all(),
    'counts' => ['eligible' => $eligible->count(), 'used' => $usages->count(), 'expired' => $unavailable->count()],
];
```

Eager-load coupons and order references. A coupon can be both currently eligible and present in used history if its customer limit allows another use. Treat disabled/date/global/customer-exhausted rules as unavailable; do not invent a wallet expiry.

- [ ] **Step 4: Bind the existing Blade UI without redesign.**

```blade
<form method="POST" action="{{ route('customer.coupons.apply') }}" class="flex flex-col sm:flex-row gap-3">
    @csrf
    <input name="code" value="{{ old('code') }}" ...>
    <button type="submit" ...>...</button>
</form>

@forelse($vouchers['eligible'] as $voucher)
    {{-- retain existing card wrappers/classes; replace fields only --}}
@empty
    {{-- reuse the existing account empty-state pattern/classes --}}
@endforelse
```

Render server-flashed `coupon_status` through the existing success/error panel. Each card's Apply button must submit its displayed code and CSRF token. Remove fixed tab/dashboard counts, hard-coded code allowlist, static array, and Alpine `applyVoucher()`.

- [ ] **Step 5: Run payload/render tests.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerCouponClosureTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php --compact`

Expected: PASS.

- [ ] **Step 6: Mechanically check that legacy business data is gone.**

Run: `git diff --check; rg -n "VIP20-ATELIER|STUDIO50-GIFT|FREESHIP-EXPRESS|\['VIP20'|Eligible & Active \(4\)" resources/views/storefront/account.blade.php`

Expected: no legacy mock business value match.

### Task 4: Prove Admin-to-account propagation and checkout-only finalization

**Files:**
- Modify: `tests/Feature/CustomerCouponClosureTest.php`
- Modify: `tests/Feature/CheckoutCommerceRulesTest.php` only if a required cross-surface assertion is absent.

**Interfaces:**
- Admin Coupon API is the producer; account reads real rules after refresh.
- Account application cannot create use history; successful checkout does.

- [ ] **Step 1: Add failing consumer/immutability tests.**

```php
public function test_admin_coupon_disable_moves_the_rule_out_of_the_customer_eligible_list_after_refresh(): void
{
    $admin = User::factory()->create(['is_admin' => true]);
    $coupon = Coupon::factory()->create(['code' => 'LIVE10', 'is_active' => true]);
    [$customer] = $this->customerWithVariant();

    $this->actingAs($admin)->patchJson("/admin/api/coupons/{$coupon->id}", $this->couponPayload($coupon, ['isActive' => false]))->assertOk();

    $payload = app(OriginalAccountPayloadFactory::class)->for($customer->fresh());
    self::assertSame(0, data_get($payload, 'vouchers.counts.eligible'));
    self::assertSame('LIVE10', data_get($payload, 'vouchers.expired.0.code'));
}
```

- [ ] **Step 2: Run the focused suite and observe failure.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerCouponClosureTest.php tests/Feature/CheckoutCommerceRulesTest.php --compact`

Expected: new tests fail only until the view-model/service contract is complete.

- [ ] **Step 3: Complete only required implementation.**

Do not add Admin UI, migration, wallet schema, payment code, or shipping code. Preserve `CheckoutService` row locking and coupon/order snapshot behavior as final authority.

- [ ] **Step 4: Run targeted tests and static checks.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CustomerCouponClosureTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php tests/Feature/CheckoutCommerceRulesTest.php --compact; C:\xampp\php\php.exe artisan test --testsuite=Feature --filter="Coupon|CheckoutCommerceRules|OriginalAccountWorkspace" --compact; git diff --check`

Expected: PASS and no whitespace errors.

### Task 5: Run local browser acceptance and write closure evidence

**Files:**
- Create: `output/acceptance/customer-coupon-2026-09-02/README.md`
- Create: `output/acceptance/customer-coupon-2026-09-02/browser.md`
- Create: `output/acceptance/customer-coupon-2026-09-02/reconciliation.md`
- Create: `docs/superpowers/reports/2026-09-02-customer-coupon-closure-mapping.md`

**Interfaces:**
- Evidence traces Admin Coupon action -> Laravel service/action -> MySQL -> account/cart/checkout/Admin consumer.
- Artifacts are redacted and contain no credentials, tokens, payment data, or customer PII.

- [ ] **Step 1: Capture baseline and controlled fixture identifiers.**

Run:

```powershell
git rev-parse HEAD
git status --short
C:\xampp\php\php.exe artisan about
C:\xampp\php\php.exe artisan migrate:status
```

- [ ] **Step 2: At desktop width, verify Admin create/update/disable causes the controlled customer's account card/count/status to change after refresh.**

Collect DOM, screenshot, and console error output. Use a controlled local session only. If typing a disposable password is needed, obtain the required action-time confirmation first.

- [ ] **Step 3: Verify application and failure loops.**

Apply a valid code to a qualifying cart; verify account status, cart/checkout pricing, unchanged ledger and usage count before checkout. Separately verify invalid, disabled, personally exhausted, and below-minimum inputs retain the prior cart coupon and show server-generated errors.

- [ ] **Step 4: Complete one safe simulated/offline checkout and reconcile.**

Verify success/account Used tab/protected Admin order/`orders.coupon_id`/`orders.coupon_code`/`orders.discount_amount`/`coupon_usages` agree. Do not dispatch, quote, book, or contact a carrier.

- [ ] **Step 5: At 390px, verify cards, tabs, form, failure state, empty state, cart/checkout reachability, no horizontal overflow, and zero relevant console errors.**

Save redacted screenshots outside source UI directories.

- [ ] **Step 6: Write the mapping report.**

Record exact SHA, evidence paths, tests, browser results, closure status for each mapped capability, and remaining excluded mock account concepts. Do not change P1-016.

- [ ] **Step 7: Run final safety checks.**

Run:

```powershell
git diff --check
git status --short
```

Expected: only planned source/docs/evidence changes; no database reset, real payment, shipment, push, or deployment.
