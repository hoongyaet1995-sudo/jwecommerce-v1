# Original Account Official Cutover Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render the already accepted, source-faithful original account workspace at the official customer `/account` route without changing customer ownership or buyer-review behavior.

**Architecture:** Reuse `OriginalAccountPayloadFactory` and the accepted `storefront.account-original-preview` Blade adapter from the isolated preview. Change only `CustomerAccountController::show` so the official route produces that existing view and payload. Keep the preview route intact as a non-default regression/acceptance route.

**Tech Stack:** Laravel 12, PHP 8.2, Blade, Alpine.js, Tailwind/Vite, MySQL customer-auth runtime.

**Spec:** `docs/superpowers/specs/2026-08-23-original-account-workspace-source-fidelity-design.md`

## Global Constraints

- Google AI Studio v7 source remains the only visual and interaction source; do not alter selected DOM hierarchy, copy, assets, Tailwind classes, responsive behavior, or visible UI.
- The user explicitly authorizes replacing official `/account` only; do not change the route URI, name, middleware, review/checkout/payment business rules, or unrelated dirty worktree files.
- Official `/account` and isolated `/account/preview/original-account` must both be customer-guarded and return the same original account workspace adapter plus customer-scoped operational payload.
- Preserve existing real orders, addresses, cart, and buyer-review eligibility/status behavior.
- Test first: prove the official route still renders the old `storefront.account` view before implementation, then change only the controller boundary needed to make it render the accepted adapter.

---

### Task 1: Official account adapter cutover

**Files:**
- Modify: `app/Http/Controllers/CustomerAccountController.php:15-31`
- Modify: `tests/Feature/OriginalAccountWorkspacePreviewTest.php:17-42`
- Modify: `docs/superpowers/reports/2026-08-23-original-account-source-extraction-manifest.md`

**Interfaces:**
- Consumes: `Request::user('customer')`, `OriginalAccountPayloadFactory::forCustomer(Customer $customer): array`, `storefront.account-original-preview`.
- Produces: `GET /account` and `GET /account/preview/original-account` render the same accepted adapter with independent, authenticated customer payloads.

- [ ] **Step 1: Write the failing official-cutover test**

Replace the current official-route expectation with this behavior:

```php
$this->actingAs($this->customerUser(), 'customer')
    ->get(route('customer.account'))
    ->assertOk()
    ->assertViewIs('storefront.account-original-preview')
    ->assertViewHas('accountPayload')
    ->assertSee('data-original-account-preview', false);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=official_account_route`

Expected: FAIL because `CustomerAccountController::show` still returns `storefront.account` and does not provide `accountPayload`.

- [ ] **Step 3: Implement the minimal controller cutover**

Make `show(Request $request, OriginalAccountPayloadFactory $factory): View` use the same authenticated customer lookup, `abort_unless`, payload factory call, and `storefront.account-original-preview` return as `originalPreview`. Do not move, copy, or alter Blade markup. Retain `originalPreview` for the acceptance route.

- [ ] **Step 4: Run focused regression checks**

Run:

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php tests/Feature/ProductReviewOperationalClosureTest.php tests/Feature/Admin/ProductReviewAuthorizationTest.php
C:\xampp\php\php.exe -l app\Http\Controllers\CustomerAccountController.php
```

Expected: all tests pass; the official route is customer-guarded, customer-scoped, source-faithful, and continues to expose the accepted review behavior.

- [ ] **Step 5: Record cutover evidence**

Update the source manifest to state that official `/account` is user-authorized and now renders the accepted adapter, while the preview route remains a regression surface. Do not imply that source-only mock regions became operational.

