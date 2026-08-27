# Simple Store-Wide Tax Class Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the zone-and-rule tax engine with multiple merchant-managed Tax Classes, at most one active store-wide percentage, server-authoritative checkout calculation, and immutable order evidence.

**Architecture:** `tax_classes` becomes the only operational configuration table and a focused service owns activation. `TaxQuoteService` reads the active class and taxes discounted merchandise only. Admin presentation comes from a new Google AI Studio ZIP; existing Cart and Checkout rows receive server data without structural redesign.

**Tech Stack:** PHP 8.2, Laravel 12, MySQL/MariaDB, React 19, TypeScript, Vite, Blade, Tailwind CSS, PHPUnit, Vitest, Playwright CLI.

**Spec:** `docs/superpowers/specs/2026-08-27-simple-store-wide-tax-class-design.md`

## Global Constraints

- Google AI Studio generated source is the only authority for Admin DOM, classes, copy hierarchy, responsiveness, and interactions.
- Every product automatically uses the active class; no product-level assignment remains.
- Zero or one Tax Class may be active; never more than one.
- Tax is calculated from `max(0, subtotal - merchandise discount)` only.
- Shipping, payment surcharge, and COD fees are not taxable.
- Prices remain tax-exclusive.
- No Tax Zone, Tax Rule, compound, effective-date, address-basis, or inclusive-price runtime behavior remains.
- No active class means zero tax and no storefront tax row.
- Active 0% retains its identity and shows a zero tax row.
- Historical order tax evidence is immutable.
- Refunds remain total-amount records without tax allocation.
- Preserve unrelated dirty worktree changes and deploy only bounded files to XAMPP.

---

## File Structure

- `database/migrations/2026_08_27_000100_simplify_tax_classes_for_store_wide_percentage.php` — add rate and singleton-active columns/index; clear known test configuration and product assignments.
- `app/Models/TaxClass.php` — focused casts/fillable fields and active scope.
- `app/Services/StoreTaxClassService.php` — list, create, update, activate, deactivate, and delete transaction boundary.
- `app/Services/TaxQuoteService.php` — store-wide percentage quote only.
- `app/Http/Controllers/Admin/TaxClassApiController.php` — canonical CRUD/activation API.
- `app/Http/Controllers/Admin/DeprecatedTaxSettingsApiController.php` — explicit legacy API retirement response.
- `app/Services/CartPricingService.php` — pass discounted merchandise to the simplified quote.
- `app/Services/CheckoutService.php` — persist the simple class snapshot without resolving Tax Rules.
- `app/Services/Admin/OrderDataService.php` — expose the simple immutable snapshot.
- `resources/admin/src/integration/taxClassData.ts` — typed canonical Admin client.
- `resources/admin/src/components/settings/TaxClassesSettingAdmin.tsx` — exact generated source adapted to real data.
- `resources/admin/src/context/AdminContext.tsx` — hydrate and mutate canonical Tax Classes only.
- `resources/admin/src/components/catalog/ProductEditorModal.tsx` — generated source with product Tax Class removed.
- `resources/admin/src/types/oscommerceSettings.ts` and `resources/admin/src/types/index.ts` — focused types with product assignment removed.
- `resources/views/storefront/cart.blade.php`, `resources/views/storefront/checkout.blade.php`, `resources/views/storefront/partials/cart-drawer.blade.php`, `resources/views/storefront/success.blade.php`, `resources/js/app.js` — dynamic label/visibility bound to server summaries.
- `docs/superpowers/reports/2026-08-27-simple-tax-source-extraction-manifest.md` — ZIP/source hashes and selected regions.
- `docs/superpowers/reports/2026-08-27-simple-tax-closure-mapping.md` — final requirements and evidence.

### Task 1: Obtain and gate the simplified Google AI Studio source

**Files:**
- Existing: `docs/superpowers/prompts/2026-08-27-google-ai-studio-simple-tax-classes-ui-prompt.md`
- Create: `docs/superpowers/reports/2026-08-27-simple-tax-source-extraction-manifest.md`

**Interfaces:**
- Consumes: the approved design and a user-provided complete Google AI Studio ZIP.
- Produces: immutable ZIP SHA-256, selected source hashes, dependency map, and explicit Admin cutover boundary.

- [ ] **Step 1: Give the prompt to the user**

Use the complete prompt in `docs/superpowers/prompts/2026-08-27-google-ai-studio-simple-tax-classes-ui-prompt.md`. Request a full ZIP and its exact local path.

- [ ] **Step 2: Hash and list the supplied archive without modifying it**

```powershell
$taxZipPath = Read-Host 'Paste the exact generated ZIP path'
$taxZipPath = (Resolve-Path -LiteralPath $taxZipPath).Path
Get-FileHash -Algorithm SHA256 -LiteralPath $taxZipPath
tar -tf $taxZipPath
```

Expected: complete project files including the simplified settings component, Product Editor, routing, types, and dependencies.

- [ ] **Step 3: Extract to a private temporary directory**

```powershell
$taxSourceDir = Join-Path $env:TEMP ("jw-simple-tax-source-" + [guid]::NewGuid())
New-Item -ItemType Directory -Path $taxSourceDir | Out-Null
Expand-Archive -LiteralPath $taxZipPath -DestinationPath $taxSourceDir
```

Expected: no files are written into Laravel during inspection.

- [ ] **Step 4: Record exact selected files and hashes**

The manifest must name the generated equivalents of:

```text
src/components/settings/TaxClassesSettingAdmin.tsx
src/components/catalog/ProductEditorModal.tsx
src/components/layout/AdminSidebar.tsx
src/components/settings/ShopSettingsHub.tsx
src/App.tsx
src/context/AdminContext.tsx
src/types/oscommerceSettings.ts
src/types/index.ts
```

Record SHA-256 for every selected source and label non-selected ZIP regions excluded.

- [ ] **Step 5: Run the generated project build before extraction**

```powershell
npm install
npm run build
```

Expected: exit 0 with no TypeScript error. If it fails, stop at the source gate and report the exact error; do not repair the generated DOM in Laravel.

- [ ] **Step 6: Commit the source manifest only**

```powershell
git add docs/superpowers/reports/2026-08-27-simple-tax-source-extraction-manifest.md
git commit -m "docs: gate simple tax class source"
```

### Task 2: Add the store-wide Tax Class database invariant

**Files:**
- Create: `database/migrations/2026_08_27_000100_simplify_tax_classes_for_store_wide_percentage.php`
- Modify: `app/Models/TaxClass.php`
- Create: `app/Services/StoreTaxClassService.php`
- Test: `tests/Feature/StoreTaxClassServiceTest.php`

**Interfaces:**
- Produces: `StoreTaxClassService::active(): ?TaxClass`, `activate(TaxClass): TaxClass`, `deactivate(TaxClass): TaxClass`, `delete(TaxClass): void`.
- Invariant: `active_slot` is null for inactive rows and `1` for the only active row.

- [ ] **Step 1: Write failing activation and deletion tests**

```php
public function test_activating_one_class_atomically_deactivates_the_previous_class(): void
{
    $sst = TaxClass::factory()->create(['name' => 'SST', 'rate_percent' => '8.0000']);
    $gst = TaxClass::factory()->create(['name' => 'GST', 'rate_percent' => '6.0000']);
    $service = app(StoreTaxClassService::class);

    $service->activate($sst);
    $service->activate($gst);

    $this->assertFalse($sst->fresh()->is_active);
    $this->assertNull($sst->fresh()->active_slot);
    $this->assertTrue($gst->fresh()->is_active);
    $this->assertSame(1, $gst->fresh()->active_slot);
    $this->assertSame(1, TaxClass::query()->where('is_active', true)->count());
}

public function test_active_class_cannot_be_deleted_but_inactive_class_can(): void
{
    $active = TaxClass::factory()->create(['is_active' => true, 'active_slot' => 1]);
    $inactive = TaxClass::factory()->create(['is_active' => false, 'active_slot' => null]);

    $this->expectException(ValidationException::class);
    app(StoreTaxClassService::class)->delete($active);

    app(StoreTaxClassService::class)->delete($inactive);
    $this->assertModelMissing($inactive);
}
```

- [ ] **Step 2: Run the tests to verify red state**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\StoreTaxClassServiceTest.php
```

Expected: FAIL because the migration fields and service do not exist.

- [ ] **Step 3: Add the migration**

```php
Schema::table('tax_classes', function (Blueprint $table): void {
    $table->decimal('rate_percent', 8, 4)->default(0)->after('description');
    $table->unsignedTinyInteger('active_slot')->nullable()->after('is_active');
    $table->unique('name');
    $table->unique('active_slot');
});

DB::table('products')->update(['tax_class_id' => null]);
DB::table('tax_classes')->update(['is_active' => false, 'active_slot' => null, 'rate_percent' => 0]);
```

Delete only known local proof records by exact names after deleting their unreferenced rules:

```php
$proofClasses = ['Browser Acceptance Tax Class', 'Closure Proof Goods 7%'];
$proofZones = ['Browser Acceptance Malaysia Zone', 'Closure Proof Malaysia Zone'];
DB::table('tax_rules')->whereIn('tax_class_id', DB::table('tax_classes')->whereIn('name', $proofClasses)->select('id'))->delete();
DB::table('tax_classes')->whereIn('name', $proofClasses)->delete();
DB::table('tax_zones')->whereIn('name', $proofZones)->whereNotExists(fn ($query) => $query->selectRaw('1')->from('tax_rules')->whereColumn('tax_rules.tax_zone_id', 'tax_zones.id'))->delete();
```

- [ ] **Step 4: Implement the activation service**

```php
public function activate(TaxClass $taxClass): TaxClass
{
    return DB::transaction(function () use ($taxClass): TaxClass {
        TaxClass::query()->lockForUpdate()->get();
        TaxClass::query()->where('is_active', true)->update(['is_active' => false, 'active_slot' => null]);
        TaxClass::query()->whereKey($taxClass)->update(['is_active' => true, 'active_slot' => 1]);

        return $taxClass->fresh();
    });
}
```

Implement `deactivate()` with a locked update and `delete()` with a stable validation exception for active rows.

- [ ] **Step 5: Run migration rollback/forward and service tests**

```powershell
C:\xampp\php\php.exe artisan migrate
C:\xampp\php\php.exe artisan test tests\Feature\StoreTaxClassServiceTest.php tests\Feature\TaxMigrationMariaDbRollbackTest.php
```

Expected: PASS and only one `active_slot=1` row can exist.

- [ ] **Step 6: Commit the data invariant**

```powershell
git add database/migrations/2026_08_27_000100_simplify_tax_classes_for_store_wide_percentage.php app/Models/TaxClass.php app/Services/StoreTaxClassService.php tests/Feature/StoreTaxClassServiceTest.php
git commit -m "feat: add store-wide tax class invariant"
```

### Task 3: Simplify the authoritative quote and checkout snapshot

**Files:**
- Modify: `app/Services/TaxQuoteService.php`
- Modify: `app/Services/CartPricingService.php`
- Modify: `app/Services/CheckoutService.php`
- Modify: `app/Services/Admin/OrderDataService.php`
- Test: `tests/Feature/StoreWideTaxQuoteTest.php`
- Test: `tests/Feature/TaxCheckoutSnapshotTest.php`

**Interfaces:**
- Consumes: `StoreTaxClassService::active()`.
- Produces: `TaxQuoteService::quote(Collection $items, int $merchandiseDiscount = 0): array`.
- Quote keys: `tax`, `status`, `label`, `rate_percent`, `taxable_amount`, and `lines`.

- [ ] **Step 1: Write failing calculation tests**

```php
public function test_active_class_taxes_discounted_merchandise_but_not_shipping_or_surcharge(): void
{
    TaxClass::factory()->create(['name' => 'SST', 'rate_percent' => '8.0000', 'is_active' => true, 'active_slot' => 1]);
    $items = $this->itemsAt(10000);

    $summary = app(CartPricingService::class)->summarize($items, $this->fixedCoupon(1000), 'standard', ['country' => 'MY'], 500);

    $this->assertSame(9000, $summary['tax_quote']['taxable_amount']);
    $this->assertSame(720, $summary['tax']);
    $this->assertSame('SST', $summary['tax_quote']['label']);
}

public function test_no_active_class_and_active_zero_rate_are_distinct(): void
{
    $items = $this->itemsAt(10000);
    $none = app(TaxQuoteService::class)->quote($items);
    TaxClass::factory()->create(['name' => 'Zero Rate', 'rate_percent' => '0.0000', 'is_active' => true, 'active_slot' => 1]);
    $zero = app(TaxQuoteService::class)->quote($items);

    $this->assertSame('not_configured', $none['status']);
    $this->assertSame([], $none['lines']);
    $this->assertSame('matched_zero_rate', $zero['status']);
    $this->assertSame('Zero Rate', $zero['label']);
    $this->assertCount(1, $zero['lines']);
}
```

- [ ] **Step 2: Run the new quote tests to verify failure**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\StoreWideTaxQuoteTest.php
```

Expected: FAIL because the current service still resolves product classes, zones, and rules.

- [ ] **Step 3: Replace rule resolution with the active class**

```php
$subtotal = $items->sum(fn (array $item): int => $item['variant']->price * $item['quantity']);
$taxable = max(0, $subtotal - max(0, $merchandiseDiscount));
$class = $this->classes->active();
if (! $class) {
    return ['tax' => 0, 'status' => 'not_configured', 'label' => null, 'rate_percent' => null, 'taxable_amount' => $taxable, 'lines' => []];
}
$rateUnits = (int) round(((float) $class->rate_percent) * 10000);
$tax = intdiv(($taxable * $rateUnits) + 500000, 1000000);
```

Return one line for an active class, including 0%, with `kind=merchandise` and `tax_type=store_wide_percentage`.

- [ ] **Step 4: Make CartPricingService exclude shipping and surcharge from the quote call**

```php
$taxQuote = $this->taxQuoteService->quote($items, $discount);
```

Keep the total formula `subtotal - discount + shipping + payment_surcharge + tax`.

- [ ] **Step 5: Persist the simple immutable line**

In `CheckoutService::persistTaxSnapshot()`, remove the TaxRule lookup and write:

```php
OrderTaxLine::query()->create([
    'order_id' => $order->id,
    'tax_rule_id' => null,
    'tax_class_id' => $line['tax_class_id'],
    'label' => $line['label'],
    'tax_type' => 'store_wide_percentage',
    'rate_percent' => $line['rate_percent'],
    'taxable_amount' => $line['taxable_amount'],
    'tax_amount' => $line['tax_amount'],
    'configuration_snapshot' => [
        'quoted_at' => now()->toIso8601String(),
        'calculation_mode' => 'store_wide_percentage',
        'tax_class' => $line['tax_class_snapshot'],
    ],
]);
```

- [ ] **Step 6: Update OrderDataService mapping and snapshot tests**

Assert that Order Admin output includes name, rate, taxable amount, tax, quoted timestamp, and `store_wide_percentage`, and that changing/deleting the current class leaves the stored values unchanged.

- [ ] **Step 7: Run the pricing and checkout suite**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\StoreWideTaxQuoteTest.php tests\Feature\TaxCheckoutSnapshotTest.php tests\Feature\CheckoutCommerceRulesTest.php
```

Expected: PASS with no TaxZone or TaxRule fixture required for a charge.

- [ ] **Step 8: Commit the calculation cutover**

```powershell
git add app/Services/TaxQuoteService.php app/Services/CartPricingService.php app/Services/CheckoutService.php app/Services/Admin/OrderDataService.php tests/Feature/StoreWideTaxQuoteTest.php tests/Feature/TaxCheckoutSnapshotTest.php tests/Feature/CheckoutCommerceRulesTest.php
git commit -m "feat: calculate store-wide tax classes"
```

### Task 4: Replace the complex Admin API with canonical Tax Class CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/TaxClassApiController.php`
- Create: `app/Http/Controllers/Admin/DeprecatedTaxSettingsApiController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/AdminAppController.php`
- Test: `tests/Feature/Admin/TaxClassApiTest.php`

**Interfaces:**
- Consumes: `StoreTaxClassService`.
- Produces: `{data: {classes: TaxClassResource[], activeTaxClassId: string|null}}`.

- [ ] **Step 1: Write failing API tests**

```php
public function test_admin_can_create_edit_activate_deactivate_and_delete_tax_classes(): void
{
    $admin = User::factory()->create(['is_admin' => true]);
    $sst = $this->actingAs($admin)->postJson('/admin/api/tax-classes', ['name' => 'SST', 'ratePercent' => 8, 'isActive' => true])
        ->assertCreated()->assertJsonPath('data.activeTaxClassId', '1')->json('data.classes.0');
    $gst = $this->actingAs($admin)->postJson('/admin/api/tax-classes', ['name' => 'GST', 'ratePercent' => 6, 'isActive' => false])
        ->assertCreated()->json('data.classes.1');

    $this->actingAs($admin)->postJson("/admin/api/tax-classes/{$gst['id']}/activate")
        ->assertOk()->assertJsonPath('data.activeTaxClassId', $gst['id']);
    $this->actingAs($admin)->deleteJson("/admin/api/tax-classes/{$gst['id']}")->assertUnprocessable();
    $this->actingAs($admin)->deleteJson("/admin/api/tax-classes/{$sst['id']}")->assertNoContent();
}
```

Add tests for guest denial, duplicate names, rate below 0/above 100, four-decimal acceptance, fifth-decimal rejection, and legacy mutation returning 410.

- [ ] **Step 2: Run the API tests to verify failure**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\Admin\TaxClassApiTest.php
```

Expected: FAIL with missing routes/controller.

- [ ] **Step 3: Implement canonical validation and document response**

```php
$data = $request->validate([
    'name' => ['required', 'string', 'max:255', Rule::unique('tax_classes', 'name')->ignore($taxClass)],
    'ratePercent' => ['required', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/'],
    'isActive' => ['required', 'boolean'],
]);
```

The controller calls the service for every mutation and re-queries MySQL for its response.

- [ ] **Step 4: Register routes and bootstrap URL**

```php
Route::apiResource('/api/tax-classes', TaxClassApiController::class)->except(['show']);
Route::post('/api/tax-classes/{taxClass}/activate', [TaxClassApiController::class, 'activate']);
Route::post('/api/tax-classes/{taxClass}/deactivate', [TaxClassApiController::class, 'deactivate']);
```

Add `taxClassesUrl` to `AdminAppController`. Route old `/api/tax-settings` mutations to a controller returning status 410 and `{message: 'The Tax Settings API was replaced by store-wide Tax Classes.'}`.

- [ ] **Step 5: Run API and authorization tests**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\Admin\TaxClassApiTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit the canonical API**

```powershell
git add app/Http/Controllers/Admin/TaxClassApiController.php app/Http/Controllers/Admin/DeprecatedTaxSettingsApiController.php app/Http/Controllers/Admin/AdminAppController.php routes/web.php tests/Feature/Admin/TaxClassApiTest.php
git commit -m "feat: add simple tax class admin api"
```

### Task 5: Integrate the generated Admin UI without redesign

**Files:**
- Create or Modify from manifest: `resources/admin/src/components/settings/TaxClassesSettingAdmin.tsx`
- Modify from manifest: `resources/admin/src/components/catalog/ProductEditorModal.tsx`
- Modify from manifest: `resources/admin/src/components/layout/AdminSidebar.tsx`
- Modify from manifest: `resources/admin/src/components/settings/ShopSettingsHub.tsx`
- Modify from manifest: `resources/admin/src/App.tsx`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Create: `resources/admin/src/integration/taxClassData.ts`
- Modify: `resources/admin/src/types/oscommerceSettings.ts`
- Modify: `resources/admin/src/types/index.ts`
- Test: `resources/admin/src/integration/taxClassData.test.ts`
- Test: `resources/admin/src/components/settings/TaxClassesSettingAdmin.test.tsx`

**Interfaces:**
- Consumes: `LaravelAdminBootstrap.taxClassesUrl` and Task 4 response document.
- Produces: source-faithful `/admin/settings/tax-classes` UI with real MySQL mutations.

- [ ] **Step 1: Write adapter tests before copying generated UI**

```ts
it('activates through the canonical endpoint and returns the rehydrated document', async () => {
  fetchMock.mockResolvedValue(response({ data: { classes: [gst], activeTaxClassId: gst.id } }));
  const result = await activateTaxClass(bootstrap, gst.id);
  expect(fetchMock).toHaveBeenCalledWith('/admin/api/tax-classes/2/activate', expect.objectContaining({ method: 'POST' }));
  expect(result.activeTaxClassId).toBe('2');
});
```

- [ ] **Step 2: Run targeted Vitest to verify failure**

```powershell
cd resources\admin
npx vitest run src/integration/taxClassData.test.ts
```

Expected: FAIL because the adapter does not exist.

- [ ] **Step 3: Implement the typed adapter**

Expose `fetchTaxClasses`, `createTaxClass`, `updateTaxClass`, `activateTaxClass`, `deactivateTaxClass`, and `deleteTaxClass`; every mutation returns or triggers a fresh canonical document.

- [ ] **Step 4: Copy only approved generated DOM/classes**

Use the source manifest hashes. Copy the generated Tax Classes page, Product Editor removal, route/nav text, modal structures, and required type contracts. Do not copy mock ownership into production context and do not copy unrelated ZIP pages.

- [ ] **Step 5: Bind generated callbacks to AdminContext**

Replace generated mock handlers with adapter calls while retaining the generated component props, DOM, classes, copy, loading states, confirmation states, and responsive behavior.

- [ ] **Step 6: Add route compatibility**

Laravel redirects `/admin/settings/tax-zones` to `/admin/settings/tax-classes`. React route selection renders the new page only for the canonical route.

- [ ] **Step 7: Prove Product Editor no longer exposes or submits Tax Class**

Add a source contract assertion that `ProductEditorModal.tsx` contains no Tax Class label or `taxClassId`, and a Catalog API test that ignores a malicious `taxClassId` input.

- [ ] **Step 8: Run Admin tests, types, and build**

```powershell
cd resources\admin
npx vitest run src/integration/taxClassData.test.ts src/components/settings/TaxClassesSettingAdmin.test.tsx
npm run lint
npm run build
```

Expected: targeted tests, TypeScript, and build pass. Record any unrelated full-suite inventory drift separately.

- [ ] **Step 9: Commit the source-faithful Admin cutover**

```powershell
git add resources/admin/src app/Http/Controllers/Admin/AdminAppController.php docs/superpowers/reports/2026-08-27-simple-tax-source-extraction-manifest.md
git commit -m "feat: cut over admin to simple tax classes"
```

### Task 6: Render dynamic server tax labels across storefront and orders

**Files:**
- Modify: `resources/views/storefront/cart.blade.php`
- Modify: `resources/views/storefront/checkout.blade.php`
- Modify: `resources/views/storefront/partials/cart-drawer.blade.php`
- Modify: `resources/views/storefront/success.blade.php`
- Modify: `resources/js/app.js`
- Modify: `app/Http/Controllers/CheckoutController.php`
- Test: `tests/Feature/StorefrontSimpleTaxDisplayTest.php`

**Interfaces:**
- Consumes: summary `tax_quote.label`, `rate_percent`, `status`, and `tax`.
- Produces: dynamic row for active classes and no row when unconfigured.

- [ ] **Step 1: Write failing HTML and quote-response tests**

```php
public function test_checkout_hides_tax_without_an_active_class_and_labels_an_active_zero_rate(): void
{
    $this->withCart($this->variantAt(10000));
    $this->get('/checkout')->assertOk()->assertDontSee('data-store-tax-row', false);

    TaxClass::factory()->create(['name' => 'Zero Rate', 'rate_percent' => 0, 'is_active' => true, 'active_slot' => 1]);
    $this->get('/checkout')->assertOk()
        ->assertSee('data-store-tax-row', false)
        ->assertSee('Zero Rate (0%)');
}
```

Add a shipping-quote response assertion for the dynamic label and tax amount.

- [ ] **Step 2: Run the display test to verify failure**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\StorefrontSimpleTaxDisplayTest.php
```

Expected: FAIL because existing views use a fixed Estimated Tax label/row.

- [ ] **Step 3: Bind existing rows without changing their visual structure**

Render the current row conditionally when `tax_quote.status !== 'not_configured'`. Its `dt` text is `{$label} ({$formattedRate}%)`; its `dd` uses the server tax amount.

- [ ] **Step 4: Update JavaScript to replace label, amount, and visibility from the shipping response**

No multiplication or percentage fallback is permitted. `app.js` reads only response fields.

- [ ] **Step 5: Render the immutable snapshot on success/order output**

Use the stored order tax line label/rate rather than the active class. Preserve the existing summary DOM/classes.

- [ ] **Step 6: Run storefront tests and build**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\StorefrontSimpleTaxDisplayTest.php tests\Feature\CheckoutCommerceRulesTest.php tests\Feature\TaxCheckoutSnapshotTest.php
npm run build
```

Expected: PASS and no `data-tax-rate` browser calculation returns.

- [ ] **Step 7: Commit the storefront binding**

```powershell
git add app/Http/Controllers/CheckoutController.php resources/views/storefront resources/js/app.js tests/Feature/StorefrontSimpleTaxDisplayTest.php public/build
git commit -m "feat: display simple store tax quotes"
```

### Task 7: Remove dormant runtime ownership and prove no fallback remains

**Files:**
- Modify: `app/Http/Controllers/Admin/CatalogApiController.php`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/SimpleTaxLegacyIsolationTest.php`

**Interfaces:**
- Produces: runtime independence from `tax_zones`, `tax_rules`, `tax_configurations`, `products.tax_class_id`, and legacy `tax_rate_percent`.

- [ ] **Step 1: Write a failing isolation test**

```php
public function test_legacy_tax_data_cannot_change_a_store_wide_quote(): void
{
    $active = TaxClass::factory()->create(['rate_percent' => 5, 'is_active' => true, 'active_slot' => 1]);
    $zone = TaxZone::factory()->create();
    TaxRule::factory()->create(['tax_class_id' => $active->id, 'tax_zone_id' => $zone->id, 'rate_percent' => 99, 'is_enabled' => true]);
    StoreSetting::query()->create(['key' => 'tax_rate_percent', 'value' => ['data' => 88]]);

    $quote = app(TaxQuoteService::class)->quote($this->itemsAt(10000));

    $this->assertSame(500, $quote['tax']);
}
```

Add assertions that Catalog responses contain no `taxClassId` and the seeder creates no active tax class.

- [ ] **Step 2: Run the isolation test to verify any remaining legacy dependency**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\SimpleTaxLegacyIsolationTest.php
```

Expected before cleanup: at least one source or response-contract assertion fails.

- [ ] **Step 3: Remove remaining legacy reads and writes**

Delete tax-class assignment validation/persistence from Catalog, complex tax hydration from AdminContext, and static/global tax seeds. Keep dormant tables only as rollback evidence.

- [ ] **Step 4: Run a repository-level forbidden-runtime scan**

```powershell
rg -n "TaxZone|TaxRule|taxSettingsUrl|tax_rate_percent|taxClassId" app\Services app\Http\Controllers resources\admin\src resources\js routes\web.php database\seeders
```

Expected matches are limited to deprecated controllers, dormant model definitions, migration/rollback code, and tests explicitly proving isolation. Product Editor, Catalog runtime, quote runtime, and storefront JavaScript have no matches.

- [ ] **Step 5: Run the isolation and catalog suites**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\SimpleTaxLegacyIsolationTest.php tests\Feature\Admin\CatalogApiTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit runtime isolation**

```powershell
git add app/Http/Controllers/Admin/CatalogApiController.php resources/admin/src/types/index.ts resources/admin/src/context/AdminContext.tsx database/seeders/DatabaseSeeder.php tests/Feature/SimpleTaxLegacyIsolationTest.php
git commit -m "refactor: retire legacy tax runtime"
```

### Task 8: Deploy to XAMPP and complete browser acceptance

**Files:**
- Create: `docs/superpowers/reports/2026-08-27-simple-tax-closure-mapping.md`
- Create: `output/playwright/simple-tax-*.png`

**Interfaces:**
- Consumes: Tasks 1–7 and the actual MySQL/XAMPP runtime.
- Produces: requirement-by-requirement closure evidence.

- [ ] **Step 1: Copy only the approved bounded files to XAMPP**

Use exact `Copy-Item -LiteralPath` operations for changed tax files. Do not copy unrelated dirty shipping/order work.

- [ ] **Step 2: Run migrations and clear runtime caches**

```powershell
cd C:\xampp\htdocs\jwstudio-ecommerce
C:\xampp\php\php.exe artisan migrate --force
C:\xampp\php\php.exe artisan optimize:clear
```

Expected: migration succeeds and no active class exists.

- [ ] **Step 3: Build both asset bundles**

```powershell
cd C:\xampp\htdocs\jwstudio-ecommerce\resources\admin
npm run lint
npm run build
cd C:\xampp\htdocs\jwstudio-ecommerce
npm run build
```

Expected: all commands exit 0; record the Node/Vite version warning separately if still present.

- [ ] **Step 4: Run the expanded focused suite in XAMPP**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\StoreTaxClassServiceTest.php tests\Feature\StoreWideTaxQuoteTest.php tests\Feature\TaxCheckoutSnapshotTest.php tests\Feature\StorefrontSimpleTaxDisplayTest.php tests\Feature\SimpleTaxLegacyIsolationTest.php tests\Feature\Admin\TaxClassApiTest.php tests\Feature\Admin\CatalogApiTest.php tests\Feature\Admin\OrderApiTest.php
```

Expected: all focused tests pass.

- [ ] **Step 5: Run authenticated Admin browser acceptance**

At `http://127.0.0.1:8500/admin/settings/tax-classes`:

1. Capture the no-active state.
2. Create and activate SST 8%.
3. Refresh and re-login; prove persistence.
4. Create GST 6% inactive.
5. Activate GST and prove SST became inactive.
6. Prove active GST Delete is blocked.
7. Deactivate GST and delete inactive SST.
8. Confirm Product Editor has no Tax Class field.

- [ ] **Step 6: Run storefront and immutable-order acceptance**

1. With SST active, create a discounted cart and verify the formula.
2. Place one local bank-transfer or COD test order.
3. Record `orders.tax_amount` and the `order_tax_lines` snapshot.
4. Activate GST 6%; verify a new Checkout quote uses GST.
5. Open the old order and prove its SST name/rate/amount remain unchanged.
6. Deactivate all classes and prove Cart/Checkout hide the tax row.

- [ ] **Step 7: Capture evidence and browser health**

Save desktop and narrow screenshots for Admin no-active, Admin active/switch, Product Editor, Checkout SST, Checkout GST, Checkout no-active, and historical order snapshot. Record console errors and failed network requests; tax flows require zero console errors and no failed tax requests.

- [ ] **Step 8: Verify MySQL truth**

Read back Tax Classes, active singleton, order aggregate, and snapshot using read-only Artisan/Tinker queries. Confirm known Browser Acceptance and Closure Proof configuration records are absent.

- [ ] **Step 9: Write closure mapping and run final checks**

The report maps every spec requirement to MySQL, API, consumer, UI source hash, automated test, browser action, and screenshot. Then run:

```powershell
git diff --check
cd resources\admin
npm test -- --run
cd ..\..\
C:\xampp\php\php.exe artisan test
```

Do not claim global green if an unrelated baseline test remains red; identify it precisely. Do not mark the goal complete until every simple-tax requirement has direct evidence.

- [ ] **Step 10: Commit the closure evidence**

```powershell
git add docs/superpowers/reports/2026-08-27-simple-tax-closure-mapping.md output/playwright
git commit -m "test: prove simple tax class closure"
```

## Plan Self-Review

- Spec coverage: Tasks 2–4 cover schema, singleton activation, quote authority, API, and immutable snapshots. Task 5 covers generated Admin source and Product Editor removal. Task 6 covers dynamic storefront behavior. Task 7 proves legacy isolation. Task 8 covers migration, MySQL, full browser flow, screenshots, and closure mapping.
- Scope: the tasks form one vertically integrated tax cutover; none is an independent product subsystem requiring a separate spec.
- Type consistency: `ratePercent` is the Admin API field, `rate_percent` is the database/quote field, and `activeTaxClassId` is the canonical document identity.
- Safety: no task invents Admin DOM/classes, applies tax to shipping/fees, reintroduces product assignments, or recalculates historical orders.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-27-simple-store-wide-tax-class-implementation.md`.

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task and review between tasks.
2. **Inline Execution** — execute tasks in this session with `superpowers:executing-plans` and review checkpoints.
