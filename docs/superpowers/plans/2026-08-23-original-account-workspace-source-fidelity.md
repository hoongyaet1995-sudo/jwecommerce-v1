# Original My Account Workspace Source-Fidelity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver an isolated, source-faithful Google AI Studio My Account workspace preview backed by real customer, order, address, wishlist, and buyer-review data.

**Architecture:** The official `/account` route stays untouched. A customer-scoped payload factory maps Laravel data to source component values, and a Blade/Alpine adapter mounts the selected source page at `/account/preview/original-account`. Password, voucher, 2FA, sessions, points, tier, and benefits keep their source mock behavior and are recorded as backend contract gaps.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade, Alpine.js, Tailwind CSS, Vite, PHPUnit, browser screenshots.

**Spec:** `docs/superpowers/specs/2026-08-23-original-account-workspace-source-fidelity-design.md`

## Global Constraints

- `(7).zip` is the visual and interaction source of truth for the selected Account page and direct shell dependencies.
- Preserve source DOM, copy, Tailwind utilities, responsive behavior, and local interactions; inject only data and events.
- Keep raw source private and do not replace official `/account` until explicit preview acceptance.
- Scope every query and mutation to the authenticated `customer` guard user.
- Keep review originals private and use the existing customer-scoped media route.
- Do not represent Password, Voucher, 2FA, sessions, points, tier, or benefits as operational Laravel features.

---

## File Structure

- `docs/superpowers/reports/2026-08-23-original-account-source-extraction-manifest.md` — source hashes, dependencies, adapter/gap matrix, and evidence.
- `app/Domain/Accounts/OriginalAccountPayloadFactory.php` — customer-only source-shaped values.
- `app/Http/Controllers/CustomerAccountController.php` — isolated preview action only.
- `resources/views/storefront/account-original-preview.blade.php` — source-faithful page adapter.
- `routes/web.php` — protected preview route only.
- `tests/Feature/OriginalAccountWorkspacePreviewTest.php` — route preservation, isolation, history, and eligibility coverage.

### Task 1: Lock Source Contract and Route Isolation

**Files:**
- Create: `docs/superpowers/reports/2026-08-23-original-account-source-extraction-manifest.md`
- Create: `tests/Feature/OriginalAccountWorkspacePreviewTest.php`

**Interfaces:**
- Produces source SHA-256 inventory and an `official /account` preservation assertion.

- [ ] **Step 1: Write the route-preservation test**

```php
public function test_official_account_route_is_not_replaced_by_preview(): void
{
    $this->actingAs($this->customerUser(), 'customer')
        ->get(route('customer.account'))
        ->assertOk()
        ->assertDontSee('data-original-account-workspace', false);
}
```

- [ ] **Step 2: Run the test**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=official_account_route`

Expected: PASS; the current official route has no preview marker.

- [ ] **Step 3: Record exact source files and contracts**

Hash the ZIP, `AccountPage.tsx`, `AccountSidebar.tsx`, all seven account tabs, and direct announcement/navbar/footer dependencies. Classify every field as `available adapter`, `mock retained`, or `backend contract gap`; include `official /account cutover: not authorized`.

- [ ] **Step 4: Validate source contract**

Run: `rg -n "TODO|TBD" docs/superpowers/reports/2026-08-23-original-account-source-extraction-manifest.md; C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=official_account_route`

Expected: no `rg` output; PHPUnit PASS.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/reports/2026-08-23-original-account-source-extraction-manifest.md tests/Feature/OriginalAccountWorkspacePreviewTest.php
git commit -m "test: lock original account preview contract"
```

### Task 2: Add the Real Customer Payload and Preview Route

**Files:**
- Create: `app/Domain/Accounts/OriginalAccountPayloadFactory.php`
- Modify: `app/Http/Controllers/CustomerAccountController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/OriginalAccountWorkspacePreviewTest.php`

**Interfaces:**
- Consumes `Customer` with `user`, `orders.items.reviews`, `orders.addresses`, `addresses`, and `wishlistItems.product.images.variants`.
- Produces `OriginalAccountPayloadFactory::for(Customer $customer): array` containing `user`, `orders`, `addresses`, `wishlist`, `dashboard`, and `gaps`.

- [ ] **Step 1: Write failing customer isolation test**

```php
public function test_preview_contains_only_signed_in_customer_orders(): void
{
    [$owner, $ownerOrder] = $this->customerWithOrder('delivered');
    [, $otherOrder] = $this->customerWithOrder('processing');

    $this->actingAs($owner->user, 'customer')
        ->get(route('customer.account.original-preview'))
        ->assertOk()->assertSee($ownerOrder->number)->assertDontSee($otherOrder->number);
}
```

- [ ] **Step 2: Run failing test**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=only_signed_in_customer_orders`

Expected: FAIL because the route is missing.

- [ ] **Step 3: Implement payload factory and isolated action**

```php
public function for(Customer $customer): array
{
    $customer->loadMissing(['user', 'orders.items.reviews', 'orders.addresses', 'addresses', 'wishlistItems.product.images', 'wishlistItems.product.variants']);
    return ['user' => $this->userPayload($customer), 'orders' => $this->orderPayloads($customer), 'addresses' => $this->addressPayloads($customer), 'wishlist' => $this->wishlistPayloads($customer), 'dashboard' => $this->dashboardPayload($customer), 'gaps' => ['password', 'vouchers', 'two_factor', 'sessions', 'membership_points']];
}
```

Add `CustomerAccountController::originalPreview(Request $request, OriginalAccountPayloadFactory $factory): View`; use `$request->user('customer')->customer` only. Register `GET /account/preview/original-account` named `customer.account.original-preview` inside the existing customer-auth group. Do not modify the existing `/account` route.

- [ ] **Step 4: Run payload test**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=only_signed_in_customer_orders`

Expected: PASS with only owner data rendered.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Accounts/OriginalAccountPayloadFactory.php app/Http/Controllers/CustomerAccountController.php routes/web.php tests/Feature/OriginalAccountWorkspacePreviewTest.php
git commit -m "feat: add original account preview payload"
```

### Task 3: Port the Full Original Account Workspace

**Files:**
- Create: `resources/views/storefront/account-original-preview.blade.php`
- Modify: `tests/Feature/OriginalAccountWorkspacePreviewTest.php`

**Interfaces:**
- Consumes `$accountPayload` from the payload factory and existing customer profile/address/logout endpoints.
- Produces original AccountPage/Sidebar/7-tab source composition identified by `data-original-account-workspace`.

- [ ] **Step 1: Write failing seven-tab test**

```php
public function test_preview_renders_every_original_account_tab(): void
{
    $this->actingAs($this->customerUser(), 'customer')
        ->get(route('customer.account.original-preview'))
        ->assertOk()->assertSee('Dashboard')->assertSee('Profile Setting')
        ->assertSee('Change Password')->assertSee('My Address')
        ->assertSee('Latest Purchase')->assertSee('Purchase History')
        ->assertSee('Eligible Voucher')->assertSee('data-original-account-workspace', false);
}
```

- [ ] **Step 2: Run failing tab test**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=renders_every_original_account_tab`

Expected: FAIL because the view is absent.

- [ ] **Step 3: Port only the approved source boundary**

Use exact DOM/class/copy order from `AccountPage`, `AccountSidebar`, `DashboardTab`, `ProfileTab`, `SecurityTab`, `AddressTab`, `LatestPurchaseTab`, `PurchaseHistoryTab`, `VouchersTab`, and direct shell files. Use Blade values and Alpine only for source tab/modal state, forms, and values. Existing real routes handle Profile, Address, Wishlist, and Logout. Do not add a custom account UI or an official-route navigation entry.

- [ ] **Step 4: Run source and preservation tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter="original_account|renders_every"`

Expected: PASS; source tabs render and official account remains separate.

- [ ] **Step 5: Commit**

```bash
git add resources/views/storefront/account-original-preview.blade.php tests/Feature/OriginalAccountWorkspacePreviewTest.php
git commit -m "feat: mount original account workspace preview"
```

### Task 4: Bind Original Purchase History to Real Buyer Reviews

**Files:**
- Modify: `app/Domain/Accounts/OriginalAccountPayloadFactory.php`
- Modify: `resources/views/storefront/account-original-preview.blade.php`
- Modify: `tests/Feature/OriginalAccountWorkspacePreviewTest.php`

**Interfaces:**
- Consumes `ProductReviewEligibilityService`, `ProductReview` attempts, `customer.reviews.store`, and `customer.reviews.media.show`.
- Produces per-item `reviewState`: `eligible`, `pending`, `published`, `rejected`, or `ineligible`.

- [ ] **Step 1: Write failing eligibility tests**

```php
public function test_delivered_purchase_history_item_shows_source_review_action(): void
{
    [$customer] = $this->customerWithOrder('delivered');
    $this->actingAs($customer->user, 'customer')->get(route('customer.account.original-preview'))
        ->assertOk()->assertSee('Review this product');
}

public function test_processing_purchase_history_item_does_not_show_review_action(): void
{
    [$customer] = $this->customerWithOrder('processing');
    $this->actingAs($customer->user, 'customer')->get(route('customer.account.original-preview'))
        ->assertOk()->assertDontSee('Review this product');
}
```

- [ ] **Step 2: Run failing eligibility tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=purchase_history_item`

Expected: FAIL until the source Purchase History adapter is bound.

- [ ] **Step 3: Bind exact source review behavior**

Derive review state server-side. Preserve the source modal’s rating, title, comment, source-approved photo chooser/previews/removal, validation and success markup. Post `FormData` only to `customer.reviews.store`. Rejected attempts restore the original source action; pending/published prevent another active attempt.

- [ ] **Step 4: Run review regression suite**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=purchase_history_item; C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php tests/Feature/Admin/ProductReviewAuthorizationTest.php`

Expected: all focused tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Accounts/OriginalAccountPayloadFactory.php resources/views/storefront/account-original-preview.blade.php tests/Feature/OriginalAccountWorkspacePreviewTest.php
git commit -m "feat: bind original purchase history reviews"
```

### Task 5: Capture Evidence and Hold Cutover

**Files:**
- Modify: `docs/superpowers/reports/2026-08-23-original-account-source-extraction-manifest.md`
- Create: `output/product-review-closure/account-original-desktop.png`
- Create: `output/product-review-closure/account-original-mobile.png`
- Create: `output/product-review-closure/account-original-purchase-history.png`
- Create: `output/product-review-closure/account-original-review-pending.png`

**Interfaces:**
- Consumes authenticated browser account and preview route.
- Produces source/preview evidence matrix and explicit no-cutover state.

- [ ] **Step 1: Capture desktop/mobile preview baselines**

Open `http://127.0.0.1:8500/account/preview/original-account` as the approved customer; capture full-page desktop and mobile images.

- [ ] **Step 2: Validate source tabs and real state**

Click all seven tabs. Confirm only real signed-in customer orders appear in Latest Purchase/Purchase History. Record Password, Voucher, 2FA, devices, points, tier, and benefits as mock-retained gaps rather than operational behavior.

- [ ] **Step 3: Validate real review flow**

Open a delivered Purchase History item’s source review modal, choose valid image(s), submit, confirm pending state, and screenshot it. Do not publish through a database shortcut.

- [ ] **Step 4: Build and test**

Run: `npm run build; C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php tests/Feature/ProductReviewOperationalClosureTest.php tests/Feature/Admin/ProductReviewAuthorizationTest.php`

Expected: build succeeds and all focused tests PASS.

- [ ] **Step 5: Update evidence and request formal cutover approval**

Record hashes, screenshots, browser outcomes, build/tests, adapters, all mock gaps, and `official /account cutover: not authorized`. Present this evidence to the user. Do not edit official `/account`.

- [ ] **Step 6: Commit metadata only**

```bash
git add docs/superpowers/reports/2026-08-23-original-account-source-extraction-manifest.md
git commit -m "docs: record original account preview evidence"
```
