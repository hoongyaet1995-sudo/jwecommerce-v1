# Lazada Test-Store Isolated Multiwarehouse Acceptance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the four facility stock actions and exact Lazada warehouse stock confirmation usable on the approved test seller without changing production sellers or activating the global rebaseline.

**Architecture:** Reuse the existing facility ledger, review/confirm APIs, facility sync outbox and Inventory48 UI. A server-side acceptance gate restricts operations to a verified, isolated SKU/facility in the designated test seller. Existing global epoch and all-store freeze services remain dormant; every provider write targets a server-resolved listing and warehouse code and becomes confirmed only after exact readback.

**Tech Stack:** Laravel 12, PHP 8, MySQL, SQLite test database, React/TypeScript Admin, Lazada official API.

**Spec:** `docs/superpowers/specs/2026-09-24-lazada-test-store-isolated-multiwarehouse-acceptance-design.md`

## Global Constraints

- Development root: `C:\xampp\htdocs\jwstudio-ecommerce`; preserve the existing dirty worktree and stage only files belonging to this phase.
- Eligible seller: connected `platform=lazada`, `account_mode=test`, `external_shop_id=300163725140`. Its `environment=live` is not evidence of production approval.
- Never set `inventory_configuration.active_rebaseline_epoch_id`, freeze global checkout, clear legacy quantities, or write a production seller in this phase.
- Unknown or failed facility discovery remains `verification_pending` and blocks writes. Never infer a zero or no-warehouse topology.
- The account/variant/facility review and confirm steps must recheck eligibility under lock; browser state is only display state.
- Keep the Google AI Studio Inventory48 DOM/classes/assets and conditional action pattern. All operational copy is English.
- Keep `MARKETPLACE_MULTIWAREHOUSE_TEST_ACCEPTANCE_ENABLED` disabled by default, with test-store identity enforced in code even when enabled.
- While test acceptance is enabled, its store/variant/facility policy applies even if a broader operations or stock-sync flag is also true; a production seller can never inherit the test exception.
- PHPUnit must use the isolated SQLite database; do not run migration tests against `jw_ecommerce` MySQL.

## Review Focus

1. A forged production-store or different test-store ID must return a validation/authorization error before changing a balance or issuing a provider task (Task 1 test).
2. A mapped SKU with a counted legacy balance or reservation must fail eligibility even when its selected facility is uncounted (Task 1 test).
3. A review prepared before a mapping, facility scan, binding or balance version change must not confirm (Task 2 test).
4. A provider timeout after a possible write must stay uncertain and Check Status must use readback before Resync (Task 3 test).
5. Two destinations that share a JW physical owner must not duplicate the count or transfer quantity in the list summary (Task 4 test/browser check).

---

### Task 1: Server-side isolated acceptance policy and read model

**Files:**
- Create: `app/Services/Inventory/StoreManagement/LazadaTestFacilityAcceptance.php`
- Modify: `config/marketplace.php`, `.env.example`
- Modify: `app/Http/Controllers/Admin/StoreInventoryReadController.php`, `routes/web.php`
- Test: `tests/Feature/LazadaTestFacilityAcceptanceTest.php`

**Interfaces:**
- `LazadaTestFacilityAcceptance::inspect(int $storeId, int $variantId, ?int $facilityId = null): array` returns `eligible`, named `blockers`, verified topology revision, mapping ID and a `facilities` list with ID, eligibility and counted state; it never writes.
- `LazadaTestFacilityAcceptance::assertEligible(int $storeId, int $variantId, int $facilityId): array` throws a validation error on any blocker and returns the inspected identity for a reviewed transaction.
- `GET /admin/api/inventory/store-management/stores/{store}/skus/{variant}/test-facility-acceptance` returns the read model to an authenticated Admin with `Cache-Control: no-store`.

- [ ] **Step 1: Write a failing feature test** using `StoreInventoryFixture` that seeds the approved test seller, another production Lazada seller, two facilities and one mapped new SKU. Assert approved eligibility; reject a forged production store, unmapped SKU, inactive/unbound facility, legacy counted balance, reservation and open order separately. Assert both sellers' balances and sync-task counts remain unchanged after rejected requests. The essential assertion is:

```php
$result = app(LazadaTestFacilityAcceptance::class)->inspect($testStoreId, $variantId, $facilityId);
$this->assertTrue($result['eligible']);
$poolId = DB::table('inventory_pools')->insertGetId([
    'pool_key' => 'warehouse:'.$warehouseId, 'kind' => 'warehouse',
    'warehouse_id' => $warehouseId, 'created_at' => now(), 'updated_at' => now(),
]);
DB::table('inventory_balances')->insert([
    'inventory_pool_id' => $poolId, 'product_variant_id' => $variantId,
    'physical_stock' => 1, 'reserved_stock' => 0, 'safety_stock' => 0,
    'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
]);
$blocked = app(LazadaTestFacilityAcceptance::class)->inspect($testStoreId, $variantId, $facilityId);
$this->assertContains('legacy_stock_present', array_column($blocked['blockers'], 'code'));
```

- [ ] **Step 2: Run the test red** with `$env:APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='; php artisan test --compact tests/Feature/LazadaTestFacilityAcceptanceTest.php`. Expect the new class/route to be absent, not a database connection error.
- [ ] **Step 3: Implement the narrow policy.** Require both config flag and exact store identity, `verified` scan, active facility bound to active JW warehouse, one listing mapping, no competing counted legacy owner, reservations, open orders, unfinished task or alternate channel ownership for that SKU. Re-read inside transactions in Task 2. Never use the global epoch as a shortcut. Add the read-only Admin route; return named blockers without tokens.

```php
// The first gate precedes any balance creation or provider task.
if (config('marketplace.multiwarehouse_test_acceptance_enabled') !== true
    || $store?->platform !== 'lazada' || $store?->account_mode !== 'test'
    || $store?->external_shop_id !== '300163725140' || $store?->status !== 'connected') {
    $blockers[] = ['code' => 'test_seller_not_approved'];
}
if ($scan?->status !== 'verified') {
    $blockers[] = ['code' => 'provider_topology_unverified'];
}
```

- [ ] **Step 4: Run the focused test and existing registry tests green**; then commit only Task 1 files.

### Task 2: Route First Count, Intake, Audit and Transfer through the policy

**Files:**
- Modify: `app/Services/Inventory/StoreManagement/FacilityStockReview.php`
- Modify: `app/Services/Inventory/StoreManagement/FacilityTransferReview.php`
- Modify: `app/Services/Inventory/StoreManagement/SyncOutbox.php`
- Test: `tests/Feature/LazadaTestFacilityStockActionsTest.php`

**Interfaces:**
- Consume `LazadaTestFacilityAcceptance::assertEligible()` from Task 1 for each reviewed facility/SKU before review and before confirm.
- Existing `FacilityStockReview::review/confirm` and `FacilityTransferReview::review/confirm` response shapes remain compatible with `storeInventoryApi.facilityStockAction()` and `.facilityTransfer()`.

- [ ] **Step 1: Write failing tests** for a test SKU with two bound facilities: count A to 10 and B to 4, intake A +3, audit A to 11, transfer 2 from A to B, and assert final A=9/B=6. Check second count rejection, source ATP floor, idempotent confirmation, stale version, and zero mutation to a seeded production seller. Change mapping/topology between review and confirm and assert failure.
- [ ] **Step 2: Run the new test red** with `php artisan test --compact tests/Feature/LazadaTestFacilityStockActionsTest.php` under the isolated PHPUnit environment. Expect failure at the test policy gate before stock changes.
- [ ] **Step 3: Add the test-only authorization branch.** Preserve the existing general operations flag for future production rollout; if only the test flag is enabled, every source/destination must pass Task 1's gate. A transfer must use two distinct facility IDs and distinct JW physical owners in the designated seller. Keep both local balance writes in one transaction and retain version/idempotency checks.

```php
if (config('marketplace.multiwarehouse_test_acceptance_enabled') === true) {
    app(LazadaTestFacilityAcceptance::class)->assertEligible($storeId, $variantId, $facilityId);
}
// For transfer, perform the same assertion for both endpoint store IDs and facility IDs.
```

- [ ] **Step 4: Queue exact facility tasks after confirmed mutations.** The test branch must pass the same server-resolved facility/listing identity to `SyncOutbox::enqueueFacilityBalance`, including both endpoints of a transfer. It must not create shopwide tasks or activate a global epoch. Run the new test plus `MarketplaceFacilityStockReviewTest.php` and `MarketplaceFacilityTransferReviewTest.php`; commit only Task 2 files.

### Task 3: Allowlisted Lazada write and exact-location readback

**Files:**
- Modify: `app/Jobs/ProcessFacilityInventorySync.php`
- Modify: `app/Services/Inventory/StoreManagement/FacilitySyncRecovery.php`
- Modify: `app/Services/Inventory/StoreManagement/LazadaStoreInventoryPublisher.php`
- Test: `tests/Feature/LazadaTestFacilitySyncTest.php`

**Interfaces:**
- Consume Task 1 policy for the task's `store_id`, `variant_id` and `marketplace_store_facility_id` immediately before dispatch and after provider readback.
- Existing `FacilitySyncRecovery::details/check/resync` remains the UI contract; `synced` requires exact `provider_location_id` and `observed_quantity` matching the latest balance version.

- [ ] **Step 1: Write failing fake-provider tests** that dispatch one task for each of A and B and assert the publisher receives only the designated test seller, listing variant and each exact `warehouse_code`. Seed a production task and assert it is blocked. Model accepted-but-mismatched readback, token expiry and timeout-after-write; assert they are not reported as synced or automatically retried as a second local mutation.
- [ ] **Step 2: Run the new test red** with isolated SQLite. Expect the test-only worker gate to block dispatch or an incorrect stock state, never an actual API call.
- [ ] **Step 3: Implement the explicit test-store dispatch branch** behind the disabled-by-default flag and current seller identity. Recheck mapping, facility code, task balance version and policy immediately before write. Preserve the publisher's strict facility preflight and same-code readback. Keep uncertain tasks for Check Status; allow Resync only after latest-version reconciliation.

```php
if (config('marketplace.multiwarehouse_test_acceptance_enabled') === true) {
    app(LazadaTestFacilityAcceptance::class)->assertEligible(
        (int) $task->marketplace_store_id,
        (int) $task->product_variant_id,
        (int) $task->marketplace_store_facility_id,
    );
}
$listing->sync_provider_location_id = $facility->provider_facility_id;
$listing->sync_strict_facility = true;
```

- [ ] **Step 4: Run the new test plus `LazadaStoreInventoryPublisherTest.php`, `MarketplaceFacilitySyncRecoveryTest.php` and `MarketplaceFacilityStockReviewTest.php`; commit only Task 3 files.

### Task 4: Show exact action eligibility and outcomes in Inventory48

**Files:**
- Modify: `resources/admin/src/integration/storeInventory.ts`
- Modify: `resources/admin/src/previews/inventory48/components/marketplace/inventory/ManageStockDrawer.tsx`
- Modify: `resources/admin/src/previews/inventory48/components/marketplace/inventory/InitialStockCountModal.tsx`
- Modify: `resources/admin/src/previews/inventory48/components/marketplace/MarketplaceInventoryPage.tsx`
- Test: `resources/admin/src/previews/inventory48/components/marketplace/inventory/LazadaTestActions.test.tsx`

**Interfaces:**
- Consume Task 1 `test-facility-acceptance` response and existing Task 2/3 review-confirm and sync endpoints. No client-supplied marketplace warehouse code is trusted for a write.

- [ ] **Step 1: Write a failing UI test** for `uncounted`, `counted`, `unmapped`, `verification_pending` and `legacy_stock_present` responses. Assert First Count alone before count; Intake/Audit after count; Transfer only with two counted facilities and positive source ATP; an eligible failure displays the named blocker and no successful toast.
- [ ] **Step 2: Run `npx vitest run src/previews/inventory48/components/marketplace/inventory/LazadaTestActions.test.tsx` red.** It should fail on actual action visibility/state, not a missing test dependency.
- [ ] **Step 3: Bind the read model to the existing drawer controls** without altering ZIP DOM/classes. Keep selected store/facility and page position stable after successful confirmation; refresh affected balances and show separate `JW ledger saved` and `Lazada stock confirmed/pending` statuses. Use “Verified Facility Physical Quantity” when targeting one facility. Remove any visible scenario/debug controls from the operational route.

```tsx
const acceptance = await storeInventoryApi.testFacilityAcceptance(storeId, variantId);
const selected = acceptance.facilities.find((row: { id: number }) => row.id === Number(facilityId));
const canCount = selected?.eligible === true && selected.counted === false;
const canIntakeOrAudit = selected?.eligible === true && selected.counted === true;
```

- [ ] **Step 4: Run the UI test, `npx vite build --config vite.inventory48.config.ts` and authenticated localhost browser checks for desktop/mobile.** Confirm the two facilities' shared owner is summed once. Commit only Task 4 files.

### Task 5: Candidate audit, scoped live release and acceptance evidence

**Files:**
- Create: `docs/acceptance/lazada-test-store-multiwarehouse-acceptance-2026-09-24.md`
- Modify: `docs/acceptance/jw-ecommerce-p1-p4-master-checklist.md` only for verified status/evidence
- No application code change unless a failure is reproduced and repaired with a new failing test.

**Interfaces:** Acceptance report records selected seller/variant/listing/facility IDs (no tokens), exact commit SHA, four review receipts, JW balances, stock task IDs, provider observed code/quantity and remaining blockers.

- [ ] **Step 1: Run a read-only candidate audit.** Prefer TOMS if mapped and eligible; otherwise identify a mapped new test SKU. Record before counts, outstanding orders and provider warehouse list. Do not fill a First Count with inferred provider stock.
- [ ] **Step 2: Run focused PHP suites, Admin build and authenticated localhost browser matrix.** Include production-seller noninterference, stale review, no/single/multi topology, failed discovery, failed readback and repeat confirmation. Stop release if any test fails.
- [ ] **Step 3: Stage only phase files from the dirty local worktree, commit and push the accepted phase to GitHub `main`.** Deploy that exact SHA to `/apps/jwecommerce` on Hostinger and confirm localhost, GitHub and Hostinger SHA match. The existing user authorization covers this scoped test-store release; do not execute the all-store cutover.
- [ ] **Step 4: On `https://ecom.jwaistudio.online/admin/marketplace/inventory`, perform the four actions with staff-supplied physical quantities for one or two eligible test warehouses.** Capture before/after JW ledger and exact Lazada stock readback. Never invent a physical count or execute a stock write when the merchant has not supplied or verified that count. If credentials or warehouse scan fail, record the blocker and leave balances untouched.
- [ ] **Step 5: Update the acceptance record with producer → service → MySQL → consumer → permission/failure → tests → browser evidence.** Mark this isolated path closed only after all four actions and exact provider readback are observed; leave all-store production and Shopee full acceptance open.
