# Automatic Marketplace Store Inventory Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove manual inventory takeover and automatically enroll every connected marketplace store in JW inventory while gating only external ATP synchronization on explicit readiness.

**Architecture:** Treat the presence of an `inventory_store_accounts` row as inventory membership and reinterpret its operational state as `setup_required`, `managed`, or `paused`. A focused enrollment service creates accounts at connection time, a readiness reader classifies setup blockers, and a sync-state mutation activates or pauses external writes without changing ledger ownership. The global mode conversion includes all enrolled accounts, while `SyncOutbox` publishes only to `managed` accounts.

**Tech Stack:** Laravel 12, PHP 8.x, MySQL, React 19, TypeScript, Vitest, Tailwind CSS, PHPUnit feature tests

**Spec:** `docs/superpowers/specs/2026-09-21-automatic-store-inventory-management-design.md`

## Global Constraints

- Preserve the Google AI Studio ZIP39 DOM structure and Tailwind design language; change only the controls and states required by the approved specification.
- JW Ecom Storefront is always managed by JW and is always an Independent Stock allocation destination.
- Connecting or migrating a store must never copy marketplace quantity into JW physical stock.
- Marketplace snapshots remain reference data only.
- `Setup Required` and `Sync Paused` stores belong to the global inventory mode but receive no external ATP broadcast.
- Physical stock must remain conserved across Shared and Independent mode conversion.
- Do not modify EasyParcel, shipping booking, delivery, payment, or storefront behavior.
- Develop and verify in `C:\xampp\htdocs\jwstudio-ecommerce` before any GitHub or Hostinger synchronization.

## Review Focus

- A connected store with zero listings must enroll as `Setup Required` without creating stock or falsely becoming sync-active; pin this in Task 2.
- Archived, draft, deleted, or sandbox-only listings must not generate production readiness blockers; pin this in Task 3.
- A store whose OAuth connection expires after activation must remain enrolled while external sync becomes non-active; pin this in Task 3.
- A mode switch that allocates stock to `Setup Required` or `Sync Paused` accounts must conserve stock and enqueue no external write for those accounts; pin this in Task 5.
- Repeated enrollment, pause, resume, and migration execution must be idempotent and must not duplicate accounts, activations, ledger balances, or audit events; pin this across Tasks 2, 4, and 7.

---

## File Structure

- `database/migrations/2026_09_21_000001_normalize_inventory_store_sync_states.php` — normalize website and connected-store account membership/state without moving stock.
- `app/Services/Inventory/StoreManagement/StoreEnrollmentService.php` — idempotently enroll website and marketplace accounts.
- `app/Services/Inventory/StoreManagement/StoreSyncReadinessReader.php` — return grouped setup requirements and the effective sync state.
- `app/Services/Inventory/StoreManagement/StoreSyncStateMutation.php` — pause/resume external synchronization and create audit records.
- `app/Services/Inventory/StoreManagement/OverviewReader.php` — expose operational store states and grouped readiness.
- `app/Services/Inventory/StoreManagement/ModeMutation.php` — allocate to every enrolled account while broadcasting only to active sync accounts.
- `app/Services/Inventory/StoreManagement/ModeCatalogReader.php` — remove manual-management ownership from catalog eligibility.
- `app/Services/Inventory/StoreManagement/SyncOutbox.php` — preserve the active-sync write gate.
- `app/Http/Controllers/Admin/StoreInventorySettingsController.php` and `routes/web.php` — replace takeover/pause routes with readiness/pause-sync/resume-sync routes.
- Marketplace authorization services — call enrollment immediately after a connected store is persisted.
- `resources/admin/src/integration/storeInventory.ts` — expose readiness and sync-state APIs and adapt the new status model.
- `resources/admin/src/previews/inventory39/context/AdminContext.tsx` — remove takeover behavior and provide pause/resume sync actions.
- `resources/admin/src/previews/inventory39/components/marketplace/inventory/InventorySettingsModal.tsx` — render Setup Required, Inventory Sync Active, and Sync Paused.
- `resources/admin/src/previews/inventory39/components/marketplace/MarketplaceInventoryPage.tsx` — connect sync-state actions and keep mode preparation actionable.
- Focused PHP and Vitest tests — prove enrollment, readiness, migration, mode conservation, sync gating, and visible failures.

### Task 1: Define the durable account and sync-state contract

**Files:**
- Create: `database/migrations/2026_09_21_000001_normalize_inventory_store_sync_states.php`
- Modify: `app/Services/Inventory/StoreManagement/OverviewReader.php`
- Test: `tests/Feature/StoreInventoryAutomaticEnrollmentTest.php`

**Interfaces:**
- Produces: account states `setup_required | managed | paused`; account presence means JW inventory membership.
- Produces: overview fields `inventory_membership: 'enrolled'` and `sync_state: 'setup_required' | 'active' | 'paused'`.

- [ ] **Step 1: Write the failing migration/overview contract test**

```php
public function test_website_is_always_enrolled_and_connected_stores_expose_sync_state(): void
{
    $store = (new StoreInventoryFixture)->store('lazada', 'AUTO-STATE');
    DB::table('marketplace_stores')->where('id', $store->id)->update(['status' => 'connected']);

    $overview = $this->actingAs($this->admin())
        ->getJson('/admin/api/inventory/store-management/overview')->assertOk();

    $overview->assertJsonPath('website_account.inventory_membership', 'enrolled')
        ->assertJsonPath('website_account.sync_state', 'active')
        ->assertJsonPath('stores.0.inventory_membership', 'enrolled')
        ->assertJsonPath('stores.0.sync_state', 'setup_required');
    $this->assertDatabaseHas('inventory_store_accounts', ['account_key' => 'website', 'management_status' => 'managed']);
    $this->assertDatabaseHas('inventory_store_accounts', ['marketplace_store_id' => $store->id, 'management_status' => 'setup_required']);
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryAutomaticEnrollmentTest.php --filter=website_is_always_enrolled`

Expected: FAIL because connected stores are still returned as `not_managed` and no `sync_state` is present.

- [ ] **Step 3: Add the normalization migration**

```php
public function up(): void
{
    DB::table('inventory_store_accounts')->where('account_key', 'website')->update([
        'management_status' => 'managed', 'updated_at' => now(),
    ]);
    DB::table('marketplace_stores')->where('status', 'connected')->orderBy('id')->get()->each(function ($store): void {
        DB::table('inventory_store_accounts')->insertOrIgnore([
            'account_key' => 'marketplace:'.$store->id, 'marketplace_store_id' => $store->id,
            'management_status' => 'setup_required', 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    });
}
```

The migration must preserve existing `managed` and `paused` values and must not insert or update `inventory_balances`.

- [ ] **Step 4: Adapt OverviewReader output**

Map `management_status` as follows:

```php
'inventory_membership' => 'enrolled',
'sync_state' => match ($status) {
    'managed' => 'active',
    'paused' => 'paused',
    default => 'setup_required',
},
```

- [ ] **Step 5: Run the test and verify GREEN**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryAutomaticEnrollmentTest.php`

Expected: PASS, with no physical-balance rows created by enrollment.

- [ ] **Step 6: Commit**

```powershell
git add database/migrations/2026_09_21_000001_normalize_inventory_store_sync_states.php app/Services/Inventory/StoreManagement/OverviewReader.php tests/Feature/StoreInventoryAutomaticEnrollmentTest.php
git commit -m "feat: normalize automatic inventory enrollment"
```

### Task 2: Enroll stores automatically when connections are persisted

**Files:**
- Create: `app/Services/Inventory/StoreManagement/StoreEnrollmentService.php`
- Modify: `app/Services/Marketplace/ShopeeAuthorizationService.php`
- Modify: `app/Services/Marketplace/LazadaAuthorizationService.php`
- Modify: `app/Services/Marketplace/LazadaTestConnectionService.php`
- Modify: `app/Http/Controllers/Admin/MarketplaceApiController.php`
- Test: `tests/Feature/StoreInventoryAutomaticEnrollmentTest.php`

**Interfaces:**
- Produces: `StoreEnrollmentService::enroll(int $storeId): object`.
- Guarantees: one account per store, no balance creation, no marketplace write, idempotent repeated calls.

- [ ] **Step 1: Write failing enrollment tests**

```php
public function test_enrollment_is_idempotent_and_does_not_import_provider_stock(): void
{
    $store = (new StoreInventoryFixture)->store('shopee', 'AUTO-ENROLL');
    $service = app(StoreEnrollmentService::class);
    $first = $service->enroll($store->id);
    $second = $service->enroll($store->id);

    $this->assertSame($first->id, $second->id);
    $this->assertSame(1, DB::table('inventory_store_accounts')->where('marketplace_store_id', $store->id)->count());
    $this->assertSame(0, DB::table('inventory_balances')->count());
    $this->assertSame('setup_required', $second->management_status);
}
```

Add connection-service assertions that successful Shopee, Lazada, and official-test connection persistence creates the account.

- [ ] **Step 2: Run the tests and verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryAutomaticEnrollmentTest.php`

Expected: FAIL because `StoreEnrollmentService` does not exist.

- [ ] **Step 3: Implement the enrollment service**

```php
final class StoreEnrollmentService
{
    public function enroll(int $storeId): object
    {
        DB::table('inventory_store_accounts')->insertOrIgnore([
            'account_key' => 'marketplace:'.$storeId,
            'marketplace_store_id' => $storeId,
            'management_status' => 'setup_required',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->firstOrFail();
    }
}
```

- [ ] **Step 4: Invoke enrollment after each connection transaction commits**

Call `app(StoreEnrollmentService::class)->enroll($id)` only after the store and token are durably saved. Do not call readiness activation or stock synchronization in the OAuth callback.

- [ ] **Step 5: Run provider connection and enrollment tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryAutomaticEnrollmentTest.php tests/Feature/MarketplaceAuthorizationTest.php tests/Feature/LazadaAuthorizationPersistenceTest.php tests/Feature/LazadaTestConnectionTest.php`

Expected: PASS with automatic enrollment proven for standard and official-test connection persistence.

- [ ] **Step 6: Commit**

```powershell
git add app/Services/Inventory/StoreManagement/StoreEnrollmentService.php app/Services/Marketplace/ShopeeAuthorizationService.php app/Services/Marketplace/LazadaAuthorizationService.php app/Services/Marketplace/LazadaTestConnectionService.php app/Http/Controllers/Admin/MarketplaceApiController.php tests/Feature/StoreInventoryAutomaticEnrollmentTest.php
git commit -m "feat: enroll connected marketplace stores automatically"
```

### Task 3: Replace takeover readiness with grouped sync readiness

**Files:**
- Create: `app/Services/Inventory/StoreManagement/StoreSyncReadinessReader.php`
- Modify: `app/Http/Controllers/Admin/StoreInventorySettingsController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/StoreInventorySyncReadinessTest.php`

**Interfaces:**
- Produces: `StoreSyncReadinessReader::read(int $storeId): array`.
- Produces JSON: `{ready, effective_state, requirements:[{code,count,ids,next_action}], review_token}`.
- Route: `GET /admin/api/inventory/store-management/stores/{store}/sync-readiness`.

- [ ] **Step 1: Write failing readiness tests**

```php
public function test_readiness_groups_relevant_blockers_and_ignores_inactive_records(): void
{
    $context = $this->storeWithActiveMappedAndArchivedUnmappedListings();
    $response = $this->actingAs($context['admin'])
        ->getJson('/admin/api/inventory/store-management/stores/'.$context['store']->id.'/sync-readiness')
        ->assertOk()->assertJsonPath('ready', false)
        ->assertJsonPath('effective_state', 'setup_required');

    $requirements = collect($response->json('requirements'));
    $this->assertSame(1, $requirements->where('code', 'initial_count_required')->count());
    $this->assertSame(1, $requirements->firstWhere('code', 'initial_count_required')['count']);
    $this->assertNull($requirements->firstWhere('code', 'unmapped_inactive_listing'));
}
```

Add tests for a zero-listing connected store, expired OAuth after activation, unmapped active listing, missing provider snapshot, and unresolved historical orders.

- [ ] **Step 2: Run the test and verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventorySyncReadinessTest.php`

Expected: FAIL with route not found.

- [ ] **Step 3: Implement relevant-listing filtering and grouping**

Use active provider listing status plus `ModeCatalogReader` production eligibility. Return one requirement per code:

```php
$requirements = collect($rawBlockers)->groupBy('code')->map(function ($rows, $code): array {
    return [
        'code' => $code,
        'count' => $rows->sum(fn ($row) => count($row['ids'] ?? [$row['variant_id'] ?? null])),
        'ids' => $rows->flatMap(fn ($row) => $row['ids'] ?? [$row['variant_id'] ?? null])->filter()->unique()->values()->all(),
        'next_action' => $rows->first()['next_action'],
    ];
})->values()->all();
```

Do not use marketplace stock quantity to satisfy an initial physical-count requirement.

- [ ] **Step 4: Add the new read route and remove frontend dependence on takeover readiness**

```php
Route::get('/api/inventory/store-management/stores/{store}/sync-readiness', [StoreInventorySettingsController::class, 'syncReadiness'])->whereNumber('store');
```

Keep old takeover routes temporarily for compatibility, but mark them unused by the new Admin bundle.

- [ ] **Step 5: Run readiness and legacy takeover tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventorySyncReadinessTest.php tests/Feature/StoreInventoryTakeoverTest.php`

Expected: PASS. Legacy compatibility must remain explicit until Task 7 removes or redirects it.

- [ ] **Step 6: Commit**

```powershell
git add app/Services/Inventory/StoreManagement/StoreSyncReadinessReader.php app/Http/Controllers/Admin/StoreInventorySettingsController.php routes/web.php tests/Feature/StoreInventorySyncReadinessTest.php tests/Feature/StoreInventoryTakeoverTest.php
git commit -m "feat: expose grouped inventory sync readiness"
```

### Task 4: Implement pause and resume as external-sync controls

**Files:**
- Create: `app/Services/Inventory/StoreManagement/StoreSyncStateMutation.php`
- Modify: `app/Http/Controllers/Admin/StoreInventorySettingsController.php`
- Modify: `routes/web.php`
- Modify: `app/Services/Inventory/StoreManagement/SyncOutbox.php`
- Test: `tests/Feature/StoreInventorySyncStateTest.php`

**Interfaces:**
- Produces: `pause(int $storeId, User $actor, string $reason): array`.
- Produces: `resume(int $storeId, User $actor, string $reason, string $reviewToken): array`.
- Routes: `POST .../{store}/pause-sync` and `POST .../{store}/resume-sync`.

- [ ] **Step 1: Write failing pause/resume tests**

```php
public function test_pause_and_resume_preserve_inventory_and_are_idempotent(): void
{
    $context = $this->readyManagedStore();
    $before = DB::table('inventory_balances')->where('id', $context['balance']->id)->first();

    $this->actingAs($context['admin'])->postJson($context['base'].'/pause-sync', ['reason' => 'API maintenance'])->assertOk();
    $this->postJson($context['base'].'/pause-sync', ['reason' => 'API maintenance'])->assertOk();
    $readiness = $this->getJson($context['base'].'/sync-readiness')->json();
    $this->postJson($context['base'].'/resume-sync', ['reason' => 'API restored', 'review_token' => $readiness['review_token']])->assertOk();

    $after = DB::table('inventory_balances')->where('id', $context['balance']->id)->first();
    $this->assertSame($before->physical_stock, $after->physical_stock);
    $this->assertSame($before->reserved_stock, $after->reserved_stock);
    $this->assertSame(1, DB::table('inventory_store_accounts')->where('marketplace_store_id', $context['store']->id)->count());
}
```

- [ ] **Step 2: Run the test and verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventorySyncStateTest.php`

Expected: FAIL with route not found.

- [ ] **Step 3: Implement mutation and auditing**

`pause` changes only `management_status` to `paused`. `resume` re-runs readiness under a transaction and changes the state to `managed` only when the review token matches and readiness is clear. Both write one idempotent `inventory_store_operations` audit receipt per logical state transition; repeated calls return the current receipt without duplicating an operation.

```php
return ['store_id' => $storeId, 'sync_state' => $status === 'managed' ? 'active' : 'paused',
    'inventory_membership' => 'enrolled', 'preserved_reservations' => $reserved];
```

- [ ] **Step 4: Keep SyncOutbox gated to active sync accounts**

Continue selecting only `management_status = 'managed'`. Add an assertion that `setup_required` and `paused` accounts never receive queued sync tasks.

- [ ] **Step 5: Run state, sync, and order lifecycle tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventorySyncStateTest.php tests/Feature/StoreInventorySyncTest.php tests/Feature/StoreInventoryOrderLifecycleTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add app/Services/Inventory/StoreManagement/StoreSyncStateMutation.php app/Http/Controllers/Admin/StoreInventorySettingsController.php routes/web.php app/Services/Inventory/StoreManagement/SyncOutbox.php tests/Feature/StoreInventorySyncStateTest.php tests/Feature/StoreInventorySyncTest.php
git commit -m "feat: separate inventory membership from sync state"
```

### Task 5: Make global mode conversion include all enrolled accounts

**Files:**
- Modify: `app/Services/Inventory/StoreManagement/ModeMutation.php`
- Modify: `app/Services/Inventory/StoreManagement/ModeCatalogReader.php`
- Modify: `app/Services/Inventory/StoreManagement/AccountResolver.php`
- Test: `tests/Feature/StoreInventoryModeTest.php`

**Interfaces:**
- Consumes: account presence and `sync_state` semantics from Tasks 1-4.
- Guarantees: allocation accepts `website` and `marketplace:{id}` for every enrolled account; external stages include active sync accounts only.

- [ ] **Step 1: Write failing mode-allocation tests**

```php
public function test_independent_split_can_allocate_to_setup_required_and_paused_accounts_without_broadcasting(): void
{
    $context = $this->sharedReadySkuWithEnrolledStores(['setup_required', 'paused']);
    $distribution = [(string) $context['variant']->id => [
        'website' => 4,
        'marketplace:'.$context['stores'][0]->id => 3,
        'marketplace:'.$context['stores'][1]->id => 3,
    ]];

    $response = $this->actingAs($context['admin'])->postJson('/admin/api/inventory/store-management/mode', [
        'target' => 'independent_stores', 'configuration_version' => 0, 'distribution' => $distribution,
    ])->assertOk()->assertJsonPath('physical_total', 10);

    $this->assertCount(0, $response->json('external_stages'));
    $this->assertSame(10, (int) DB::table('inventory_balances')->whereNotNull('counted_at')->sum('physical_stock'));
}
```

Add a companion test with one active marketplace account and assert only that store receives an external stage. Assert repeated submission fails on configuration version without duplicating balances.

- [ ] **Step 2: Run the mode tests and verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryModeTest.php`

Expected: FAIL because only `managed` marketplace accounts are currently included.

- [ ] **Step 3: Change allocation eligibility from active sync to enrollment**

Build destination accounts from all `inventory_store_accounts`, always including `website`. Use `SyncOutbox` status filtering only after the internal split is committed.

- [ ] **Step 4: Remove manual-management classification from ModeCatalogReader**

`store_not_managed` must no longer exclude a SKU from global mode allocation. Retain exclusions for sandbox/test, inactive catalog state, missing first count, and reservation conflicts.

- [ ] **Step 5: Run mode, catalog, read, and transfer tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryModeTest.php tests/Feature/StoreInventoryReadTest.php tests/Feature/StoreInventoryTransferTest.php`

Expected: PASS with strict stock conservation.

- [ ] **Step 6: Commit**

```powershell
git add app/Services/Inventory/StoreManagement/ModeMutation.php app/Services/Inventory/StoreManagement/ModeCatalogReader.php app/Services/Inventory/StoreManagement/AccountResolver.php tests/Feature/StoreInventoryModeTest.php tests/Feature/StoreInventoryReadTest.php
git commit -m "feat: allocate inventory across enrolled stores"
```

### Task 6: Replace takeover UI with store sync status and actionable setup

**Files:**
- Modify: `resources/admin/src/integration/storeInventory.ts`
- Modify: `resources/admin/src/previews/inventory39/context/AdminContext.tsx`
- Modify: `resources/admin/src/previews/inventory39/components/marketplace/inventory/inventoryTypes.ts`
- Modify: `resources/admin/src/previews/inventory39/components/marketplace/inventory/InventorySettingsModal.tsx`
- Modify: `resources/admin/src/previews/inventory39/components/marketplace/MarketplaceInventoryPage.tsx`
- Create: `resources/admin/src/previews/inventory39/components/marketplace/inventory/InventorySettingsSyncState.test.tsx`

**Interfaces:**
- Consumes: `sync-readiness`, `pause-sync`, and `resume-sync` APIs.
- Produces UI states: `setup_required | active | paused`; actions `Resolve Setup | Pause Sync | Resume Sync`.

- [ ] **Step 1: Write failing UI tests**

```tsx
it('shows setup requirements instead of takeover controls', async () => {
  renderSettings({syncState: 'setup_required', requirements: [
    {code: 'unmapped_listings', count: 3, ids: [1, 2, 3], next_action: 'Map active listings.'},
  ]});
  expect(screen.queryByText('Takeover Inventory')).toBeNull();
  expect(screen.queryByText('Not Managed by JW')).toBeNull();
  expect(screen.getByText('Setup Required')).toBeTruthy();
  fireEvent.click(screen.getByText('Resolve Setup'));
  expect(screen.getByText('3 active listings require SKU mapping')).toBeTruthy();
});
```

Add tests for active, paused, visible API failure, website always active, and a mode-allocation destination list containing setup-required stores.

- [ ] **Step 2: Run the UI test and verify RED**

Run: `cd resources/admin; npx vitest run src/previews/inventory39/components/marketplace/inventory/InventorySettingsSyncState.test.tsx`

Expected: FAIL because takeover copy and callbacks still exist.

- [ ] **Step 3: Add API adapters and types**

```ts
export type InventoryStoreSyncState = 'setup_required' | 'active' | 'paused';
export type InventorySetupRequirement = {code:string; count:number; ids:Array<number|string>; nextAction:string};
```

Expose `syncReadiness(id)`, `pauseSync(id, reason)`, and `resumeSync(id, reason, reviewToken)`. Remove new Admin usage of `takeover()` and `pause()`.

- [ ] **Step 4: Preserve ZIP39 structure while replacing controls**

Keep the existing grouped store cards, spacing, borders, platform logos, modal width, and dark-theme classes. Replace only status copy and right-side actions. Render a nested readiness panel in the existing modal body and show caught API errors in a red status card.

- [ ] **Step 5: Ensure Global Stock Mode shows explicit blockers and eligible destinations**

The allocation step must initialize all enrolled destination accounts. If there are zero destinations, show `No inventory accounts are available` as a visible blocker. The Admin Sign-off button must never be silently disabled.

- [ ] **Step 6: Run focused frontend tests and build**

Run: `cd resources/admin; npx vitest run src/previews/inventory39/components/marketplace/inventory/InventorySettingsSyncState.test.tsx src/previews/inventory39/components/marketplace/inventory/InitialStockCountModalPersistence.test.tsx src/previews/inventory39/components/marketplace/MarketplaceInventoryCountResolver.test.ts`

Run: `cd resources/admin; npm run build`

Expected: all focused tests pass and `public/build-admin/manifest.json` references the new Inventory39 bundle.

- [ ] **Step 7: Commit**

```powershell
git add resources/admin/src/integration/storeInventory.ts resources/admin/src/previews/inventory39/context/AdminContext.tsx resources/admin/src/previews/inventory39/components/marketplace/MarketplaceInventoryPage.tsx resources/admin/src/previews/inventory39/components/marketplace/inventory/inventoryTypes.ts resources/admin/src/previews/inventory39/components/marketplace/inventory/InventorySettingsModal.tsx resources/admin/src/previews/inventory39/components/marketplace/inventory/InventorySettingsSyncState.test.tsx public/build-admin
git commit -m "feat: replace inventory takeover with sync readiness"
```

### Task 7: Migrate existing stores, retire takeover behavior, and verify end to end

**Files:**
- Delete: `app/Services/Inventory/StoreManagement/TakeoverMutation.php`
- Rename: `app/Services/Inventory/StoreManagement/ReadinessReader.php` to `app/Services/Inventory/StoreManagement/ModeReadinessReader.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/StoreInventoryTakeoverTest.php`
- Create: `tests/Feature/StoreInventoryExistingStoreMigrationTest.php`
- Modify: `docs/acceptance/jw-ecommerce-p1-p4-master-checklist.md`

**Interfaces:**
- Consumes: the final enrollment/readiness/sync-state APIs.
- Produces: no employee-accessible takeover endpoints or frontend calls; idempotent migration evidence.

- [ ] **Step 1: Write the failing migration regression test**

```php
public function test_existing_connected_stores_migrate_without_stock_duplication(): void
{
    $beforePhysical = (int) DB::table('inventory_balances')->whereNotNull('counted_at')->sum('physical_stock');
    $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

    $connected = DB::table('marketplace_stores')->where('status', 'connected')->count();
    $enrolled = DB::table('inventory_store_accounts')->whereNotNull('marketplace_store_id')->count();
    $this->assertSame($connected, $enrolled);
    $this->assertSame($beforePhysical, (int) DB::table('inventory_balances')->whereNotNull('counted_at')->sum('physical_stock'));
}
```

Also assert the old takeover and pause routes return HTTP 410 with the compatibility response below and cannot mutate physical stock.

- [ ] **Step 2: Run the regression test and verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryExistingStoreMigrationTest.php`

Expected: FAIL until the compatibility response and migration behavior match the final contract.

- [ ] **Step 3: Retire obsolete takeover endpoints and classes**

Delete `TakeoverMutation`, rename the remaining mode-readiness class, and remove every frontend call to `/takeover` and `/pause`. Retain the two old POST routes for one compatibility release and return HTTP 410 with:

```json
{"message":"Inventory takeover is automatic. Use sync-readiness, pause-sync, or resume-sync."}
```

- [ ] **Step 4: Run the complete inventory suite**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryAccountsTest.php tests/Feature/StoreInventoryAutomaticEnrollmentTest.php tests/Feature/StoreInventoryCutoverTest.php tests/Feature/StoreInventoryExistingStoreMigrationTest.php tests/Feature/StoreInventoryLegacyModeMigrationTest.php tests/Feature/StoreInventoryModeTest.php tests/Feature/StoreInventoryOrderLifecycleTest.php tests/Feature/StoreInventoryReadTest.php tests/Feature/StoreInventoryReviewTest.php tests/Feature/StoreInventorySyncReadinessTest.php tests/Feature/StoreInventorySyncStateTest.php tests/Feature/StoreInventorySyncTest.php tests/Feature/StoreInventoryTransferTest.php tests/Feature/MarketplaceInventoryIngestionTest.php tests/Feature/LazadaInventoryIngestionTest.php tests/Feature/SandboxInventoryWriteGuardTest.php`

Expected: every inventory test passes; no test assumes manual takeover.

- [ ] **Step 5: Run frontend regression and production build**

Run: `cd resources/admin; npx vitest run src/previews/inventory39`

Run: `cd resources/admin; npm run build`

Expected: all Inventory39 tests pass and build completes. Record unrelated repository-wide TypeScript failures separately if `npm run lint` remains red outside Inventory39.

- [ ] **Step 6: Perform localhost browser acceptance**

Verify through the authenticated `/admin/marketplace/inventory` UI:

1. No takeover language remains.
2. JW Storefront displays Inventory Active.
3. Connected test stores display Setup Required, Active, or Paused with visible reasons.
4. Shared-to-Independent allocation includes JW Storefront and connected stores.
5. Allocation conserves each SKU total and reaches Admin Sign-off.
6. Setup Required stores receive no external sync tasks.
7. Pause/resume changes only synchronization state.

- [ ] **Step 7: Commit final cleanup and evidence**

```powershell
git add app/Services/Inventory/StoreManagement app/Http/Controllers/Admin/StoreInventorySettingsController.php routes/web.php tests/Feature/StoreInventoryTakeoverTest.php tests/Feature/StoreInventoryExistingStoreMigrationTest.php docs/acceptance
git commit -m "refactor: remove manual inventory takeover workflow"
```

- [ ] **Step 8: Present the coherent local phase for release approval**

Report test counts, build result, browser evidence, migration impact, and the exact local commit SHA. Synchronize to GitHub and Hostinger only under the existing explicit release authorization or after receiving fresh synchronization approval, then verify Local/GitHub/Production SHA equality and repeat live HTTPS acceptance.
