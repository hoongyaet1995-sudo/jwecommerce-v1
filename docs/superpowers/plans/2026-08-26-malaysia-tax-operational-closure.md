# Malaysia Tax Operational Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Checkout global 8.875% calculation with merchant-published, MySQL-backed tax rules whose quotes and order tax lines are authoritative and auditable.

**Architecture:** A `TaxQuoteService` resolves explicit product tax classes against published effective tax rules and a destination address. Laravel supplies every Cart and Checkout tax amount; JavaScript only renders server responses. The existing Google AI Studio Tax Zones and storefront summary DOMs are preserved, while Admin state moves from localStorage to protected APIs and MySQL.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL/MariaDB, Blade, Vite/React TypeScript, Tailwind, PHPUnit feature tests.

**Spec:** `docs/superpowers/specs/2026-08-26-malaysia-tax-operational-closure-design.md`

## Global Constraints

- Preserve the accepted Google AI Studio DOM, Tailwind utilities, copy hierarchy, and responsive behavior; bind data and logic only.
- MySQL is the only operational owner; tax rules must never be owned by React localStorage.
- Merchants may enable a configured tax rule without an SST registration number; registration metadata is optional and non-blocking.
- Do not infer tax rate, exemption, duty-free treatment, or product class from product names, regions, or countries.
- No generic 8% / 8.875% fallback may remain in Checkout, Cart, or browser JavaScript.
- Existing orders retain immutable tax facts despite later configuration changes.
- Do not stage or overwrite unrelated dirty worktree files.

---

## File Structure

- `database/migrations/2026_08_26_000100_create_tax_configuration_tables.php` — tax configuration, geographic zones, classes, rules, and order tax-line schema.
- `app/Models/TaxConfiguration.php`, `TaxZone.php`, `TaxClass.php`, `TaxRule.php`, `OrderTaxLine.php` — Eloquent ownership and relations.
- `app/Services/TaxQuoteService.php` — single tax calculation contract.
- `app/Services/CartPricingService.php` — delegates tax computation to `TaxQuoteService`; retains existing pricing and shipping behavior.
- `app/Services/CheckoutService.php` — persists a tax quote snapshot in the checkout transaction.
- `app/Models/Product.php`, `app/Models/Order.php` — tax-class and tax-line relations.
- `app/Http/Controllers/Admin/TaxConfigurationApiController.php` — protected document read/write API.
- `app/Http/Controllers/CheckoutController.php` — returns server tax quote with shipping quote response.
- `routes/web.php` — Admin tax API routes only.
- `resources/admin/src/integration/taxConfigurationData.ts` — typed Admin GET/PUT client.
- `resources/admin/src/context/AdminContext.tsx` — hydrates and saves tax data through Laravel instead of `aurora_admin_*` tax localStorage.
- `resources/admin/src/components/settings/TaxZonesSettingAdmin.tsx` — retains source UI and consumes API-backed state.
- `resources/views/components/storefront/delivery-options.blade.php`, `resources/js/app.js` — remove browser math and render the server summary.
- `tests/Feature/TaxQuoteServiceTest.php`, `tests/Feature/Admin/TaxConfigurationApiTest.php`, `tests/Feature/CheckoutCommerceRulesTest.php` — policy, persistence, authorization, and downstream regression proof.

### Task 1: Create the tax-domain schema and immutable order evidence

**Files:**
- Create: `database/migrations/2026_08_26_000100_create_tax_configuration_tables.php`
- Create: `app/Models/TaxConfiguration.php`
- Create: `app/Models/TaxZone.php`
- Create: `app/Models/TaxClass.php`
- Create: `app/Models/TaxRule.php`
- Create: `app/Models/OrderTaxLine.php`
- Modify: `app/Models/Product.php`
- Modify: `app/Models/Order.php`
- Test: `tests/Feature/TaxQuoteServiceTest.php`

**Interfaces:**
- Produces `TaxRule::matchesAddress(array $address): bool` and `TaxRule::isEffectiveAt(CarbonInterface $at): bool`.
- Produces `Product::taxClass(): BelongsTo` and `Order::taxLines(): HasMany`.
- Consumes product prices in integer cents and merchant rule rates as decimal percentages.

- [ ] **Step 1: Write failing schema and relationship tests**

```php
public function test_a_product_has_an_explicit_tax_class_and_orders_retain_tax_lines(): void
{
    $class = TaxClass::factory()->create(['code' => 'standard-goods']);
    $product = Product::factory()->create(['tax_class_id' => $class->id]);
    $order = Order::factory()->create();
    $line = OrderTaxLine::factory()->create([
        'order_id' => $order->id,
        'tax_class_id' => $class->id,
        'taxable_amount' => 10000,
        'tax_amount' => 600,
    ]);

    $this->assertTrue($product->taxClass->is($class));
    $this->assertTrue($order->taxLines->first()->is($line));
}
```

- [ ] **Step 2: Run the new test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\TaxQuoteServiceTest.php`

Expected: FAIL because tax models, factories, columns, and relations do not exist.

- [ ] **Step 3: Add the migration and minimal models**

```php
$table->foreignId('tax_class_id')->nullable()->after('category_id')->constrained()->nullOnDelete();

Schema::create('tax_rules', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('tax_class_id')->constrained()->restrictOnDelete();
    $table->foreignId('tax_zone_id')->constrained()->restrictOnDelete();
    $table->string('label');
    $table->string('tax_type', 40);
    $table->decimal('rate_percent', 8, 4);
    $table->boolean('applies_to_shipping')->default(false);
    $table->unsignedSmallInteger('priority')->default(1);
    $table->boolean('is_enabled')->default(false);
    $table->timestamp('effective_from')->nullable();
    $table->timestamp('effective_to')->nullable();
    $table->timestamps();
});
```

Create `order_tax_lines` with `order_id`, nullable `tax_rule_id`, nullable `tax_class_id`, `label`, `tax_type`, `rate_percent`, `taxable_amount`, `tax_amount`, `configuration_snapshot` JSON, and timestamps. Use unsigned integer cents for money; preserve deleted rules/classes by `nullOnDelete()`.

- [ ] **Step 4: Add factories only for the five new models and run the test**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\TaxQuoteServiceTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the isolated schema deliverable**

```powershell
git add database/migrations app/Models tests/Feature/TaxQuoteServiceTest.php
git commit -m "feat: add tax configuration domain models"
```

### Task 2: Make `TaxQuoteService` the sole calculation authority

**Files:**
- Create: `app/Services/TaxQuoteService.php`
- Modify: `app/Services/CartPricingService.php`
- Test: `tests/Feature/TaxQuoteServiceTest.php`

**Interfaces:**
- Produces `TaxQuoteService::quote(Collection $items, int $discount, int $shipping, int $paymentSurcharge, ?array $address, ?CarbonInterface $at = null): array`.
- Return value: `['amount' => int, 'status' => 'configured'|'not_configured'|'no_matching_rule', 'lines' => list<array>]`.
- `CartPricingService::summarize()` returns its existing keys plus `tax_quote` and continues to return `tax` in cents.

- [ ] **Step 1: Add failing behavior tests**

```php
public function test_no_published_rule_never_falls_back_to_a_global_percentage(): void
{
    StoreSetting::query()->updateOrCreate(['key' => 'tax_rate_percent'], ['value' => ['data' => 8.875]]);
    $summary = app(CartPricingService::class)->summarize($this->itemsForProductWithoutTaxClass());

    $this->assertSame(0, $summary['tax']);
    $this->assertSame('not_configured', $summary['tax_quote']['status']);
}

public function test_published_matching_rule_calculates_only_its_assigned_product_and_optional_shipping(): void
{
    $rule = $this->publishedDomesticRule(rate: 6, appliesToShipping: false);
    $summary = app(CartPricingService::class)->summarize($this->itemsForTaxClass($rule->tax_class_id), null, 'standard', ['country' => 'MY', 'state' => 'Selangor']);

    $this->assertSame(600, $summary['tax']);
    $this->assertCount(1, $summary['tax_quote']['lines']);
}
```

- [ ] **Step 2: Run the tax service tests to verify failure**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\TaxQuoteServiceTest.php`

Expected: FAIL because `CartPricingService` still reads `tax_rate_percent`.

- [ ] **Step 3: Implement deterministic rule resolution**

```php
public function quote(Collection $items, int $discount, int $shipping, int $paymentSurcharge, ?array $address, ?CarbonInterface $at = null): array
{
    $configuration = TaxConfiguration::query()->where('is_enabled', true)->where('is_published', true)->first();
    if (! $configuration) return ['amount' => 0, 'status' => 'not_configured', 'lines' => []];

    // Resolve only enabled, effective rules matching the product class and destination.
    // Allocate order discount proportionally across taxable merchandise lines using integer cents.
}
```

Rules must be sorted by `priority`, then ID. Equal-priority matching rules for the same class and address are rejected by Admin validation, not summed implicitly. Include shipping only once when the selected matching rule explicitly allows it. Payment surcharge remains excluded unless a later approved rule extends this contract.

- [ ] **Step 4: Remove the global percentage branch and prove behavior**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\TaxQuoteServiceTest.php tests\Feature\CheckoutCommerceRulesTest.php`

Expected: PASS; no assertion may use `tax_rate_percent` as a pricing source.

- [ ] **Step 5: Commit service authority**

```powershell
git add app/Services/TaxQuoteService.php app/Services/CartPricingService.php tests/Feature/TaxQuoteServiceTest.php tests/Feature/CheckoutCommerceRulesTest.php
git commit -m "feat: calculate taxes from published rules"
```

### Task 3: Persist the tax quote atomically with the order

**Files:**
- Modify: `app/Services/CheckoutService.php`
- Modify: `app/Models/Order.php`
- Test: `tests/Feature/CheckoutCommerceRulesTest.php`

**Interfaces:**
- Consumes `CartPricingService::summarize()['tax_quote']`.
- Produces one `order_tax_lines` row per quoted tax line in the same database transaction as `orders`, `order_items`, and `order_payments`.

- [ ] **Step 1: Write a failing checkout snapshot test**

```php
public function test_checkout_persists_the_tax_rule_snapshot_and_later_rule_changes_do_not_rewrite_the_order(): void
{
    [$rule, $order] = $this->checkoutWithPublishedTaxRule(6);
    $original = $order->taxLines()->firstOrFail();
    $rule->update(['rate_percent' => 10]);

    $this->assertSame(600, $order->fresh()->tax_amount);
    $this->assertSame(6.0, (float) $original->rate_percent);
}
```

- [ ] **Step 2: Run the target regression test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\CheckoutCommerceRulesTest.php --filter=tax_rule_snapshot`

Expected: FAIL because checkout only stores aggregate `tax_amount`.

- [ ] **Step 3: Persist quote lines inside the existing transaction**

```php
foreach ($summary['tax_quote']['lines'] as $line) {
    $order->taxLines()->create([
        'tax_rule_id' => $line['tax_rule_id'],
        'tax_class_id' => $line['tax_class_id'],
        'label' => $line['label'],
        'tax_type' => $line['tax_type'],
        'rate_percent' => $line['rate_percent'],
        'taxable_amount' => $line['taxable_amount'],
        'tax_amount' => $line['tax_amount'],
        'configuration_snapshot' => $line['configuration_snapshot'],
    ]);
}
```

Keep the existing inventory lock, coupon lock, shipping validation, payment-method validation, and payment-proof behavior unchanged.

- [ ] **Step 4: Run complete checkout regressions**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\CheckoutCommerceRulesTest.php`

Expected: PASS, including COD, bank transfer, shipping quote, address, and tax snapshot paths.

- [ ] **Step 5: Commit the order-audit deliverable**

```powershell
git add app/Services/CheckoutService.php app/Models/Order.php tests/Feature/CheckoutCommerceRulesTest.php
git commit -m "feat: snapshot tax lines on checkout"
```

### Task 4: Bind the existing Admin Tax Zones UI to Laravel and MySQL

**Files:**
- Create: `app/Http/Controllers/Admin/TaxConfigurationApiController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/AdminAppController.php`
- Create: `resources/admin/src/integration/taxConfigurationData.ts`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Modify: `resources/admin/src/components/settings/TaxZonesSettingAdmin.tsx`
- Test: `tests/Feature/Admin/TaxConfigurationApiTest.php`

**Interfaces:**
- Produces `GET /admin/api/tax-configuration` and `PUT /admin/api/tax-configuration`.
- API document: `{configuration, zones, classes, rules}`.
- Consumes `LaravelAdminBootstrap.taxConfigurationUrl` and follows the existing `fetchCommerceSettings` / `saveCommerceSettings` error contract.

- [ ] **Step 1: Write failing authorization, persistence, and conflict tests**

```php
public function test_admin_can_persist_and_reload_a_published_tax_rule_without_a_registration_number(): void
{
    $admin = User::factory()->create(['is_admin' => true]);
    $payload = $this->taxDocument(enabled: true, registrationNumber: null);

    $this->actingAs($admin)->putJson('/admin/api/tax-configuration', $payload)
        ->assertOk()
        ->assertJsonPath('data.configuration.isEnabled', true)
        ->assertJsonPath('data.rules.0.ratePercent', 6);

    $this->actingAs($admin)->getJson('/admin/api/tax-configuration')
        ->assertOk()
        ->assertJsonPath('data.rules.0.label', 'Domestic standard rule');
}

public function test_guest_cannot_read_or_write_tax_configuration(): void
{
    $this->getJson('/admin/api/tax-configuration')->assertUnauthorized();
    $this->putJson('/admin/api/tax-configuration', [])->assertUnauthorized();
}
```

- [ ] **Step 2: Run the API test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\Admin\TaxConfigurationApiTest.php`

Expected: FAIL because routes and controller do not exist.

- [ ] **Step 3: Implement the protected document API and validation**

Use one database transaction to upsert configuration, zones, classes, and rules. Validate unique `code` values, rate range `0..100`, allowed address selectors, `effective_to >= effective_from`, and reject two enabled, overlapping, equal-priority rules for the same class/zone. Do not require registration metadata.

```php
Route::get('/api/tax-configuration', [TaxConfigurationApiController::class, 'show']);
Route::put('/api/tax-configuration', [TaxConfigurationApiController::class, 'update']);
```

- [ ] **Step 4: Replace Admin tax localStorage ownership without changing its UI**

```ts
const document = await fetchTaxConfiguration(bootstrap);
setTaxZones(document.zones);
setTaxClasses(document.classes);
setTaxRates(document.rules);
setGlobalTaxEngine(document.configuration);
```

Remove only tax-specific `safeParseArray` and `localStorage.setItem` paths. Keep unrelated Admin local state untouched. On save, use the existing toast/error pattern and rehydrate from the API response.

- [ ] **Step 5: Run backend and type/build checks**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\Admin\TaxConfigurationApiTest.php tests\Feature\Admin\CommerceConfigurationApiTest.php`

Run: `npm run build`

Expected: both commands succeed.

- [ ] **Step 6: Commit the Admin binding**

```powershell
git add app/Http/Controllers/Admin/TaxConfigurationApiController.php app/Http/Controllers/Admin/AdminAppController.php routes/web.php resources/admin/src tests/Feature/Admin/TaxConfigurationApiTest.php
git commit -m "feat: persist admin tax configuration"
```

### Task 5: Render server tax quotes in Cart and Checkout; remove browser tax math

**Files:**
- Modify: `app/Http/Controllers/CheckoutController.php`
- Modify: `resources/views/components/storefront/delivery-options.blade.php`
- Modify: `resources/views/storefront/cart.blade.php`
- Modify: `resources/views/storefront/partials/cart-drawer.blade.php`
- Modify: `resources/views/storefront/checkout.blade.php`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/CheckoutCommerceRulesTest.php`

**Interfaces:**
- `POST /checkout/shipping-quote` already returns `data.summary`; its `summary.tax` and `summary.tax_quote` are now authoritative.
- Browser rendering accepts server `summary` and must not read `data-tax-rate`.

- [ ] **Step 1: Write failing response and DOM-contract tests**

```php
public function test_shipping_quote_returns_server_calculated_tax_and_checkout_does_not_expose_a_tax_rate_attribute(): void
{
    $response = $this->postJson('/checkout/shipping-quote', [
        'country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000', 'delivery' => 'standard',
    ]);

    $response->assertOk()->assertJsonPath('data.summary.tax', 600);
    $this->get('/checkout')->assertDontSee('data-tax-rate', false);
}
```

- [ ] **Step 2: Run the target test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\CheckoutCommerceRulesTest.php --filter=server_calculated_tax`

Expected: FAIL because the delivery component still emits `data-tax-rate`.

- [ ] **Step 3: Render only the returned summary**

Remove `data-tax-rate` and `installDeliveryMatrix` multiplication. When the quoted delivery HTML replaces the matrix, update the existing summary `dt` rows from `payload.data.summary.shipping`, `.tax`, and `.total`; use the existing `storefrontMoney` formatter. If `tax_quote.status !== 'configured'`, retain the approved summary row but display `Tax calculated at checkout` rather than a fabricated amount.

- [ ] **Step 4: Run storefront regression tests and build assets**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\CheckoutCommerceRulesTest.php tests\Feature\TaxQuoteServiceTest.php`

Run: `npm run build`

Expected: both commands succeed and the source UI structure is unchanged apart from data attributes required for rendering.

- [ ] **Step 5: Commit storefront binding**

```powershell
git add app/Http/Controllers/CheckoutController.php resources/views resources/js/app.js tests/Feature/CheckoutCommerceRulesTest.php public/build-admin
git commit -m "feat: render checkout tax from server quotes"
```

### Task 6: Remove mock seeds and perform closure acceptance

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `resources/admin/src/data/oscommerceSettingsData.ts`
- Modify: `resources/admin/src/types/oscommerceSettings.ts`
- Test: `tests/Feature/TaxQuoteServiceTest.php`
- Create: `docs/superpowers/reports/2026-08-26-malaysia-tax-closure-mapping.md`

**Interfaces:**
- Removes `tax_rate_percent` as an operational seed and invalid default tax classes/rates.
- Reports all mapped capabilities as `CLOSED`, `ADMIN_UI_GAP`, or `DORMANT` with evidence.

- [ ] **Step 1: Write a failing seed safety test**

```php
public function test_database_seed_does_not_create_a_global_tax_percentage_or_enabled_chargeable_rule(): void
{
    $this->seed();

    $this->assertDatabaseMissing('store_settings', ['key' => 'tax_rate_percent']);
    $this->assertFalse(TaxRule::query()->where('is_enabled', true)->exists());
}
```

- [ ] **Step 2: Run the safety test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\TaxQuoteServiceTest.php --filter=database_seed_does_not_create`

Expected: FAIL because the current seeder writes `8.875`.

- [ ] **Step 3: Remove static tax claims and seed only inactive foundations**

Delete `tax_rate_percent = 8.875`; remove source-array labels that claim all physical goods are 8%, exports are automatically 0%, or named locations are always duty-free. Seed only inactive Malaysia address foundations and no enabled chargeable rule.

- [ ] **Step 4: Run full automated verification**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\TaxQuoteServiceTest.php tests\Feature\CheckoutCommerceRulesTest.php tests\Feature\Admin\TaxConfigurationApiTest.php tests\Feature\Admin\CommerceConfigurationApiTest.php`

Run: `npm run build`

Run: `git diff --check`

Expected: all tests pass, build exits 0, and no whitespace errors are reported.

- [ ] **Step 5: Run authenticated browser acceptance**

1. Log in as Admin and create a Malaysia rule without registration metadata; save, refresh, and verify it persists.
2. In a separate storefront session, add an explicitly classified product, select a matching Malaysia address, and confirm Cart/Checkout tax equals the server quote.
3. Change the address to a non-matching zone and confirm tax is not guessed.
4. Place a COD or bank-transfer order; verify `orders.tax_amount` and `order_tax_lines` match the checkout quote.
5. Change the rule rate in Admin; confirm the historical order tax line remains unchanged.
6. Capture before/after screenshots and record browser console output.

- [ ] **Step 6: Write the closure mapping report and commit**

```powershell
git add database/seeders resources/admin/src/data resources/admin/src/types tests/Feature/TaxQuoteServiceTest.php docs/superpowers/reports/2026-08-26-malaysia-tax-closure-mapping.md
git commit -m "chore: remove mock tax defaults and document closure"
```

## Plan Self-Review

- Spec coverage: Tasks 1–3 cover MySQL ownership, product classification, authoritative calculation, and immutable order evidence. Task 4 covers Admin persistence and authorization. Task 5 covers storefront consumption and removes browser math. Task 6 removes mock fixtures and requires end-to-end proof.
- Placeholder scan: no unresolved placeholders or deferred implementation steps are included.
- Interface consistency: `TaxQuoteService` produces `tax_quote`, `CartPricingService` exposes it, `CheckoutService` snapshots it, and the shipping quote endpoint renders it.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-26-malaysia-tax-operational-closure.md`.

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task and review after each task.
2. **Inline Execution** — execute the tasks in this session with review checkpoints.
