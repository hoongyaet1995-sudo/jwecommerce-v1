# Catalog Inventory First Count Boundary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Execution method: Native, preserving the user's earlier selection. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically initialize inventory when saving catalog stock, allow product-page quantity edits before First Count, and redirect verified SKUs to their inventory adjustment workflow.

**Architecture:** Use one physical ledger with a durable SKU-level verification marker. Catalog quantities are physical totals, while legacy `product_variants.stock` retains its existing availability semantics as a coordinated compatibility projection. Initialization and verified counting are separate lifecycle transitions.

**Tech Stack:** Laravel/PHP, MySQL, React/TypeScript, existing PHPUnit and Vitest tooling.

**Spec:** `docs/superpowers/specs/2026-09-21-catalog-inventory-first-count-boundary.md` (user approved).

## Global Constraints

- Develop in `C:\xampp\htdocs\jwstudio-ecommerce` first.
- Preserve Product Variant layout and styling except the requested stock-control change.
- Exact CTA: **Edit in Inventory Management**.
- Automatic initialization is not a physical verification.
- Marketplace initialization must not automatically enable external stock pushes.
- First Count records an absolute quantity; it must not add that quantity to existing stock.
- Do not overwrite, stage or commit unrelated dirty checkout/storefront changes. Several inventory allocation files are already dirty; capture their baseline diffs and integrate only attributable hunks.
- No production data correction is authorized by this plan. Read-only reconciliation precedes any legacy-state migration.
- Never run migration/reset tests against the development business database. Set `APP_CONFIG_CACHE` to a nonexistent test-only path, `DB_CONNECTION=sqlite`, and `DB_DATABASE=:memory:` before isolated PHPUnit runs. Use an explicitly isolated MySQL database for locking/concurrency verification.

## Review Focus

1. A metadata save from a stale product editor must not restore sold stock or overwrite a First Count (Tasks 2, 4).
2. Opening stock with reservations must remain sellable without physical verification, but must not permit reductions below reservations (Tasks 2, 3).
3. Legacy automatically provisioned `counted_at` must not falsely establish verification or unlock an existing managed SKU (Task 1).
4. First Count of zero and retry of a confirmed review must preserve ownership and avoid duplicate quantities (Task 3).
5. Disabled/archived SKUs, permissions, URL refresh, and unsaved metadata must not send a stock adjustment to the wrong SKU (Task 5).

## Verified code touchpoints

- `CatalogApiController::replaceProductRelations()` currently passes direct quantity edits to `InventoryMutationService::setAvailable()`. `productData()` exposes the legacy availability field as inventoryQuantity.
- `InventoryMutationService` explicitly represents unallocated availability. `StockAllocationService` subtracts on reservation and does not subtract again on consumption.
- `StoreManagement/CountMutation::apply()` always assigns counted_at. `AccountResolver` and `ModeMutation` also provision counted timestamps, so these are not sufficient verification evidence.
- `ReviewService::confirm()` and `AllocationReviewService::confirm()` own reviewed count/allocation commits and receipts.
- `ProductEditorModal` contains numeric input, bulk stock apply and generated variant defaults, including nonzero demonstration defaults. New real SKU defaults must be zero, not accidental demo opening stock.
- `previews/inventory39/components/marketplace/MarketplaceInventoryPage.tsx` is the active extracted interface, with `handleOpenIntake(item, 'adjustment')` and `StockIntakeAdjustmentModal` already available.

### Task 1: Durable verification state and legacy evidence report

**Files**
- Create `database/migrations/2026_09_21_230000_add_catalog_inventory_ownership_to_product_variants.php`.
- Create `app/Services/Inventory/CatalogInventoryState.php`.
- Create `app/Console/Commands/ReportCatalogInventoryOwnership.php`.
- Create `tests/Feature/CatalogInventoryStateTest.php`.
- Modify `app/Models/ProductVariant.php` only for casts of new state fields.

**Interfaces**
- `CatalogInventoryState::snapshot(int $variantId): array` returns `{initialized:bool, verified:bool, editable:bool, physical:?int, reserved:int, available:int, revision:string, balance_id:?int, adjustment_url:string, repair_required:bool}`.
- `CatalogInventoryState::markVerified(int $variantId, string $operationId): void` is called only inside a confirmed physical mutation transaction.
- `inventory:catalog-ownership-report` emits SKU identity, evidence category and discrepancies without mutating data.

- [ ] Write a failing state test using `StoreInventoryFixture` and isolated migrations. Cover absent balances, initialized zero, explicit verified zero, generated counted timestamp without count audit, and multiple inactive pools.

```php
$sku = (new \Tests\Support\StoreInventoryFixture)->sku();
$state = app(\App\Services\Inventory\CatalogInventoryState::class)->snapshot($sku['variant']->id);
$this->assertFalse($state['verified']);
$this->assertFalse($state['initialized']);
```

- [ ] Run `C:\xampp\php\php.exe vendor/bin/phpunit tests/Feature/CatalogInventoryStateTest.php` with isolated environment; expect missing service before implementation.
- [ ] Add nullable `inventory_initialized_at`, `first_physical_verified_at`, and `first_physical_verification_operation` to product_variants. Do not stamp old SKUs verified during schema migration.

```php
Schema::table('product_variants', function (Blueprint $table) {
    $table->timestamp('inventory_initialized_at')->nullable();
    $table->timestamp('first_physical_verified_at')->nullable();
    $table->string('first_physical_verification_operation', 128)->nullable();
});
```

- [ ] Implement state resolution from the active pool scope and explicit confirmed count/allocation receipts. Existing verified evidence remains locked immediately, even before marker backfill. Ambiguous existing ledger histories return `repair_required=true` and `editable=false`; they must not be presented as verified or silently unlocked. Preserve independent balances when reading totals; exclude inactive shared/warehouse pools.
- [ ] Implement the read-only report and tests for no writes. Existing physical-count evidence is resolved from stored receipts/reviews, not just nonzero values. Document counts of verified, untouched catalog-only and ambiguous records in a local report.
- [ ] Run the state test to green, review schema rollback, and commit only this task's files. Do not run state backfills on business data as part of schema migration.

### Task 2: Transactional catalog stock initialization and edits

**Files**
- Create `app/Services/Inventory/CatalogStockService.php`.
- Create `app/Services/Inventory/CatalogStockProjection.php`.
- Create `database/migrations/2026_09_21_230100_create_catalog_stock_operations.php`.
- Modify `app/Http/Controllers/Admin/CatalogApiController.php`.
- Create `tests/Feature/Admin/CatalogInventoryOwnershipTest.php`.

**Interfaces**
- `CatalogStockService::setPhysical(int $variantId, int $quantity, ?string $expectedRevision, string $operationKey, int $actorId): array` returns the Task 1 snapshot.
- `CatalogStockProjection::refresh(int $variantId): void` computes compatibility website availability from the active shared/website balance and updates stock/stock_version atomically. It never assigns aggregate independent physical stock to storefront availability.
- Catalog response adds `inventoryInitialized`, `inventoryVerified`, `inventoryEditable`, `inventoryRevision`, `inventoryAdjustmentUrl`. Existing inventoryQuantity becomes physical quantity; totalInventory sums those physical quantities.
- Existing-SKU metadata updates can omit inventoryQuantity. An explicit quantity write must provide inventoryRevision; new SKUs require quantity, defaulting to zero in the UI.

- [ ] Add failing API tests for opening 20, changing 20 to 15, explicit zero, unchanged save, forged verified edit, metadata-only stale save, and invalid quantity. Reuse the existing CatalogApiTest product payload shape with unique SKU values.

```php
$this->assertDatabaseHas('inventory_balances', [
    'product_variant_id' => $variantId, 'physical_stock' => 20,
    'reserved_stock' => 0, 'counted_at' => null,
]);
$this->assertDatabaseHas('product_variants', [
    'id' => $variantId, 'first_physical_verified_at' => null,
]);
```

- [ ] Run `C:\xampp\php\php.exe vendor/bin/phpunit tests/Feature/Admin/CatalogInventoryOwnershipTest.php`; verify red assertions against current behavior.
- [ ] Create operation storage with unique operation_key, request_hash, variant_id, balance_id, before/after physical quantities, actor_id, reason and receipt JSON. Validate duplicate key payload hashes; replay returns the prior receipt.
- [ ] Implement configuration -> sorted SKU -> sorted balances lock ordering. Align CatalogApiController transaction entry with AccountResolver's configuration-first ordering. New SKU creation writes metadata and balance atomically, recording `Initial catalog stock`; subsequent edits record `Catalog stock update`. Reject quantities below current reservations or outside signed integer limits. Only no-op existing quantity writes avoid a new operation.

```php
if ($state['verified'] || $state['repair_required']) {
    throw ValidationException::withMessages([
        'inventoryQuantity' => 'Edit this stock in Inventory Management.',
    ]);
}
if ($quantity < $state['reserved']) {
    throw ValidationException::withMessages([
        'inventoryQuantity' => 'Physical stock cannot be lower than reserved stock.',
    ]);
}
```

- [ ] Establish default shared/website ledger for supported configurations without switching existing global mode. Where legacy independent-warehouse configuration is encountered, return a documented reconciliation requirement rather than guessing a warehouse or duplicating balances.
- [ ] Replace direct catalog stock writes; keep price/options/image changes independent. A catalog-only legacy SKU with unresolved active allocations must be reported for reconciliation instead of assuming legacy availability is physical stock.
- [ ] Use request-specific operation keys and replay-safe creation identity; tests repeat the same opening submission and assert one SKU/balance/opening operation. Remove fallback creation paths that directly seed nonzero stock outside the ledger.
- [ ] Batch state reads for product listing responses; test query count growth to prevent one query per variant. Run new tests plus `tests/Feature/Admin/CatalogApiTest.php` under the isolated environment, then commit scoped changes.

### Task 3: Count handoff, storefront orders and external readiness

**Files**
- Modify `app/Services/Inventory/StoreManagement/ReviewService.php`, `AllocationReviewService.php`, `CountMutation.php`, `AccountResolver.php`, `OverviewReader.php`, `ReadinessReader.php`, `ModeCatalogReader.php`, `StoreSyncReadinessReader.php`, `SyncOutbox.php`.
- Integrate narrowly with existing `app/Services/Inventory/StockAllocationService.php`, `PhysicalAllocationService.php`, `OrderAllocationService.php` and `StoreManagement/OrderInventoryRouter.php`; these contain existing local edits.
- Create `tests/Feature/CatalogInventoryOrderLifecycleTest.php`.
- Extend `tests/Feature/StoreInventoryReviewTest.php`, `StoreInventoryAllocationTest.php` and `StoreInventorySyncTest.php`.

**Interfaces**
- Successful explicit count/allocation confirms call Task 1 `markVerified()` with the recorded operation ID, then Task 2 `CatalogStockProjection::refresh()` in the same transaction.
- Initialization remains operational for website orders; verification gates stay on external publishing. Transfer/intake/provisioning must not falsely mark the first count complete.

- [ ] Add failing tests for initial physical 20 -> reserve 3 (physical 20, reserved 3, ATP 17) -> cancel (physical 20, reserved 0, ATP 20), and a separate shipment (physical 17, reserved 0, ATP 17). Repeat events and assert no additional change. Cover inspected return and refund-only paths separately.
- [ ] Add verified-handoff tests: initialized 15 -> counted 18 yields 18; counted zero is permanently locked; an expired/rejected count leaves catalog editable; replay yields the original receipt. Ensure a pending count review becomes stale after a catalog edit.

```php
$this->assertDatabaseHas('inventory_balances', [
    'product_variant_id' => $variantId, 'physical_stock' => 18,
]);
$this->assertTrue(app(\App\Services\Inventory\CatalogInventoryState::class)
    ->snapshot($variantId)['verified']);
```

- [ ] Run those tests red, then coordinate one reservation owner for each order line. Do not route a line through both StockAllocationService and OrderInventoryRouter. Newly initialized website balances participate regardless of the old accounting_enabled flag; existing unmanaged historical order allocations keep their evidenced ownership.
- [ ] Update readers to return recorded physical quantity while distinguishing initialized/unverified from missing inventory. Existing uncounted UI must not mask valid website availability as zero. Keep mode transition and marketplace verification gates intact.
- [ ] Guard external outbox creation and execution against unverified/mapping-ineligible records. Catalog edits never falsely return platform-synced receipts. Count completion queues only eligible external accounts and writes verification in the same transaction.
- [ ] Verify stale catalog vs count and reservation races using two isolated MySQL connections: serialize on shared lock ordering, reject stale revision, and assert quantity never drops below reservations. No concurrency test may target jw_ecommerce.
- [ ] Run targeted lifecycle/readiness/allocation tests to green and inspect the diff against captured pre-task dirty baselines before committing attributable hunks.

### Task 4: Product Variant editable/read-only stock controls

**Files**
- Modify `resources/admin/src/types/index.ts`, `resources/admin/src/components/catalog/ProductEditorModal.tsx`, `resources/admin/src/context/AdminContext.tsx`.
- Create `resources/admin/src/components/catalog/catalogStockPayload.ts`.
- Create `resources/admin/src/components/catalog/catalogStockPayload.test.ts` and `ProductInventoryOwnership.test.tsx`.

**Interfaces**
- Add optional API state fields from Task 2 to the Variant type for legacy fixture compatibility. Unknown ownership is fetched before enabling edits; do not treat a missing response field as unlocked.
- `catalogStockPayload(current, initial)` returns variants with quantity/revision included only for new SKUs or actual editable quantity changes.

- [ ] Test unchanged quantity omission, edited unlocked quantity + revision inclusion, locked quantity omission and mixed-row bulk edit behavior.

```ts
expect(payload.variants[0]).not.toHaveProperty('inventoryQuantity');
expect(edited.variants[0]).toMatchObject({inventoryQuantity: 15, inventoryRevision: 'v1'});
```

- [ ] Run `npm --prefix resources/admin exec -- vitest run src/components/catalog/catalogStockPayload.test.ts` from the package directory if npm resolves a different working directory; verify test discovery before accepting output.
- [ ] Implement dirty-quantity payload tracking based on initial loaded values, preserve metadata save behavior, and set generated real SKU opening quantities to zero. Bulk Set Stock modifies only editable rows and indicates skipped verified rows.
- [ ] Keep existing stock cell styling; replace verified input with read-only physical quantity and the exact CTA. Show a total physical label for independent aggregate values. Surface server conflicts inline, preserving unsaved metadata and requesting refreshed stock state.
- [ ] Add component tests asserting editable before First Count, read-only after refresh, zero verified locked, unchanged metadata save succeeds, and the exact CTA appears. Run component tests and build, then commit scoped files.

### Task 5: Exact-SKU inventory adjustment deep link

**Files**
- Modify `resources/admin/src/previews/inventory39/components/marketplace/MarketplaceInventoryPage.tsx`, `resources/admin/src/previews/inventory39/context/AdminContext.tsx`, `resources/admin/src/integration/storeInventory.ts`.
- Create `resources/admin/src/previews/inventory39/components/marketplace/inventory/InventoryAdjustmentDeepLink.test.tsx`.
- Modify ProductEditorModal navigation from Task 4.

**Interfaces**
- Catalog adjustment URL is `/admin/marketplace/inventory?variant_id=<stable-id>&action=adjustment`.
- After fresh inventory loading, resolve by stable variant ID, not mutable SKU text. Apply list filtering and call existing `handleOpenIntake(item, 'adjustment')` once. Use existing StockIntakeAdjustmentModal.

- [ ] Test URL resolution after loading, one modal open per navigation, refresh reopening, duplicate SKU text with distinct IDs, missing/archived SKU and permission denied. Tests should assert displayed target identity and absence of accidental selection of the first row.
- [ ] Implement query parsing and request sequencing; cancel outdated navigation effects. Clear incompatible catalog filters so a valid target is visible. Shared mode uses shared balance; Independent mode exposes target store choice and validates eligible account on the server.

```ts
const params = new URLSearchParams(window.location.search);
const targetId = params.get('variant_id');
const wantsAdjustment = params.get('action') === 'adjustment';
// Resolve targetId against fresh API records before opening the existing modal.
```

- [ ] Preserve unsaved metadata with the existing guard; if no guard exists in this modal, implement an explicit discard-or-stay confirmation only when actual metadata changes exist. Do not autosave or silently discard.
- [ ] Show actionable unavailable/permission feedback. Run navigation/component tests and existing inventory39 persistence tests, then commit scoped files.

### Task 6: Local acceptance, evidence and scoped release readiness

**Files**
- Create `docs/acceptance/2026-09-21-catalog-inventory-first-count-boundary.md`.
- Update this plan's checkboxes with actual outcomes; update master checklist only for directly proven existing acceptance items.

- [ ] Run the new isolated backend suites, existing CatalogApiTest, StoreInventoryAllocationTest, StoreInventoryReviewTest, StoreInventoryOrderLifecycleTest and relevant checkout lifecycle regression tests. Record exact commands/results. Investigate behavior failures, not merely fixture incompatibilities.
- [ ] Run frontend tests from `resources/admin`: `npx vitest run src/components/catalog/catalogStockPayload.test.ts src/components/catalog/ProductInventoryOwnership.test.tsx src/previews/inventory39`; then `npm run lint` and `npm run build`. Distinguish baseline failures from introduced failures with evidence.
- [ ] Use authenticated localhost browser acceptance with disposable clearly named test SKUs: create 20, edit 15, verify both screens, complete First Count 18, confirm catalog lock after full reload, and use CTA to adjust the same SKU. Test zero, mixed rows, shared/independent mode in isolated fixtures, and unsaved changes. Do not switch business-wide inventory mode merely for UI tests.
- [ ] Confirm a storefront-only fixture purchases/reserves/cancels correctly without First Count and mapped/unmapped external channels do not receive unauthorized broadcasts.
- [ ] Run the read-only legacy report. Document unresolved discrepancies without correcting production data. Check initialization, physical verification, availability and sync statuses are visibly distinct.
- [ ] Save screenshots and receipts; self-review security, lock ordering, idempotency, legacy projections and quantity conservation. Review scoped diff and retain all preexisting unrelated changes.
- [ ] Report implemented scope, verification and any concrete blockers. Follow existing local -> GitHub -> Hostinger authorization and same-SHA verification only after this phase is accepted for release. Do not label local implementation as deployed.

## Plan self-review

- Every spec acceptance criterion maps to Tasks 1-6; all five Review Focus conditions have concrete tests.
- The write lifecycle stays atomic; initialization, confirmation and external eligibility use distinct state.
- Physical quantity and legacy availability projections have explicit separate meanings.
- Existing dirty allocation changes are an integration dependency; they cannot be swept into this release without inspection.
- No app code is changed by preparing this plan. The user reviews this written plan before Native execution, as required by the invoked planning workflow.
