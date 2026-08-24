# Storefront Currency Session Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist a visitor-selected storefront currency in Laravel session and present all canonical storefront amounts through one manually managed exchange-rate context.

**Architecture:** `StorefrontCurrencyContext` wraps the existing MySQL `CurrencySettings` document, request session, conversion, and formatting. Blade and JavaScript obtain currency configuration from that one context; checkout keeps base-cent arithmetic and snapshots currency/rate on the order.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade, Vite JavaScript, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-23-storefront-currency-session-closure.md`

## Global Constraints

- Preserve existing Google AI Studio DOM, Tailwind classes, copy, and interactions; inject data behavior only.
- Currency setting values remain MySQL runtime truth; visitor choice is session-only.
- Keep all cart, coupon, shipping, tax, inventory, and payment arithmetic in base-currency integer cents.
- Do not parse or alter free-form CMS/navigation money text.
- Use focused tests per task; run browser verification and build only at task completion.

---

### Task 1: Currency context and shared formatter

**Files:**
- Create: `app/Support/StorefrontCurrencyContext.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Feature/StorefrontCurrencyContextTest.php`

**Interfaces:**
- Consumes: `App\Support\CurrencySettings`, request session.
- Produces: `current(): array`, `set(string): array`, `format(int|float): string`, `formatWithSnapshot(int|float, string, float): string`, `clientConfig(): array`, and `@storefrontMoney`.

- [ ] **Step 1: Write failing resolver and formatter tests**

```php
public function test_session_currency_overrides_the_default_and_formats_base_cents(): void
{
    $this->seedCurrencies();
    $this->withSession(['storefront_currency' => 'USD'])
        ->get('/shop')
        ->assertSee('$44.68');
}

public function test_disabled_session_currency_falls_back_to_the_default(): void
{
    $this->seedCurrencies();
    $this->withSession(['storefront_currency' => 'GBP'])
        ->get('/shop')
        ->assertSee('RM 199.00');
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php artisan test tests/Feature/StorefrontCurrencyContextTest.php`

Expected: FAIL because no context/directive formats current storefront amounts.

- [ ] **Step 3: Implement the minimal context and Blade directive**

```php
public function format(int|float $baseCents): string
{
    return $this->formatForCurrency($baseCents, $this->current());
}
```

Register the context as a scoped service and the directive in `AppServiceProvider`; share `storefrontCurrency` only with `storefront.*` views.

- [ ] **Step 4: Run the focused test and verify GREEN**

Run: `php artisan test tests/Feature/StorefrontCurrencyContextTest.php`

Expected: PASS.

### Task 2: Session selection endpoint and header handoff

**Files:**
- Create: `app/Http/Controllers/StorefrontCurrencyController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/storefront/nav.blade.php`
- Modify: `resources/js/app.js`
- Modify: `tests/Feature/StorefrontCurrencyContextTest.php`

**Interfaces:**
- Consumes: `StorefrontCurrencyContext::set(string)`.
- Produces: named route `storefront.currency.update`, canonical `[data-atelier-currency-config]` JSON and header selection behavior.

- [ ] **Step 1: Write failing session selection tests**

```php
public function test_enabled_currency_selection_is_persisted_in_the_storefront_session(): void
{
    $this->seedCurrencies();
    $this->postJson(route('storefront.currency.update'), ['currency' => 'SGD'])
        ->assertNoContent()
        ->assertSessionHas('storefront_currency', 'SGD');
}

public function test_disabled_currency_selection_is_rejected(): void
{
    $this->seedCurrencies();
    $this->postJson(route('storefront.currency.update'), ['currency' => 'GBP'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('currency');
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php artisan test tests/Feature/StorefrontCurrencyContextTest.php`

Expected: FAIL because the named route/controller does not exist.

- [ ] **Step 3: Implement endpoint and replace localStorage behavior**

```php
public function __invoke(Request $request, StorefrontCurrencyContext $currency): Response
{
    $data = $request->validate(['currency' => ['required', 'string', 'size:3']]);
    $currency->set($data['currency']);
    return response()->noContent();
}
```

Make `installAtelierCurrency()` use the shared config, POST the selected code with the existing CSRF token, then reload. Remove `atelier_currency` reads/writes.

- [ ] **Step 4: Run the focused test and verify GREEN**

Run: `php artisan test tests/Feature/StorefrontCurrencyContextTest.php`

Expected: PASS.

### Task 3: Order currency-rate snapshot

**Files:**
- Create: `database/migrations/2026_08_23_000000_add_currency_exchange_rate_to_orders_table.php`
- Modify: `app/Models/Order.php`
- Modify: `app/Services/CheckoutService.php`
- Modify: `app/Http/Controllers/CheckoutController.php`
- Modify: `tests/Feature/CheckoutCurrencySnapshotTest.php`

**Interfaces:**
- Consumes: `StorefrontCurrencyContext::current()`.
- Produces: `orders.currency` and `orders.currency_exchange_rate` set in the checkout transaction.

- [ ] **Step 1: Write failing checkout snapshot test**

```php
public function test_checkout_snapshots_the_current_session_currency_and_rate(): void
{
    $this->withSession(['storefront_currency' => 'USD'])
        ->post('/checkout', $this->validCheckoutPayload())
        ->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'currency' => 'USD',
        'currency_exchange_rate' => '0.22450000',
    ]);
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php artisan test tests/Feature/CheckoutCurrencySnapshotTest.php`

Expected: FAIL because the order has no exchange-rate column/snapshot.

- [ ] **Step 3: Implement migration and transaction snapshot**

```php
$table->decimal('currency_exchange_rate', 16, 8)->nullable()->after('currency');
```

Resolve the context once before the transaction, pass the code/rate into `CheckoutService::checkout()`, and create the order with both fields. Do not change base-cent pricing calculations.

- [ ] **Step 4: Run the focused test and verify GREEN**

Run: `php artisan test tests/Feature/CheckoutCurrencySnapshotTest.php`

Expected: PASS.

### Task 4: Replace canonical Blade and JavaScript money rendering

**Files:**
- Modify: `resources/views/storefront/*.blade.php`
- Modify: `resources/views/storefront/partials/cart-drawer.blade.php`
- Modify: `resources/views/components/storefront/{page-section,plp-filters,quick-add-variant-modal,variant-selector,product-card-variant-preview,delivery-options}.blade.php`
- Modify: `resources/js/app.js`
- Modify: `tests/Feature/StorefrontCurrencyContextTest.php`

**Interfaces:**
- Consumes: `@storefrontMoney`, `storefrontCurrency` shared view value, and JavaScript currency config.
- Produces: no hard-coded `$` formatters for canonical amounts; dynamic PDP/quick-add/checkout values format using `formatStorefrontMoney(baseCents)`.

- [ ] **Step 1: Write failing cross-surface rendering tests**

```php
public function test_selected_currency_is_rendered_on_pdp_cart_checkout_and_published_page_products(): void
{
    $this->seedCurrencies();
    foreach (['/products/example', '/cart', '/checkout', '/pages/brand-story'] as $url) {
        $this->withSession(['storefront_currency' => 'USD'])->get($url)
            ->assertOk()
            ->assertDontSee('$199.00', false);
    }
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php artisan test tests/Feature/StorefrontCurrencyContextTest.php`

Expected: FAIL because views and JavaScript still use `$`/`number_format` directly.

- [ ] **Step 3: Replace only structured monetary render points**

Replace direct canonical price output with `@storefrontMoney`. Add client config once in the shared nav/layout and make all dynamic formatter calls use it. Preserve free-form marketing phrases and the source DOM/classes.

- [ ] **Step 4: Run focused tests and verify GREEN**

Run: `php artisan test tests/Feature/StorefrontCurrencyContextTest.php tests/Feature/CheckoutCurrencySnapshotTest.php`

Expected: PASS.

### Task 5: Historical-order rendering and task-boundary verification

**Files:**
- Modify: `resources/views/storefront/{success,account}.blade.php`
- Modify: `tests/Feature/CheckoutCurrencySnapshotTest.php`

**Interfaces:**
- Consumes: `StorefrontCurrencyContext::formatWithSnapshot()` and `Order::currency_exchange_rate`.
- Produces: receipt/success/account values formatted from order snapshot, not active session rate.

- [ ] **Step 1: Write failing historical snapshot test**

```php
public function test_success_page_uses_order_rate_snapshot_after_admin_rate_changes(): void
{
    $order = $this->orderWithSnapshot('USD', 0.2245, 19900);
    $this->changeUsdRateTo(0.10);

    $this->get(route('checkout.success', ['order' => $order->number]))
        ->assertSee('$44.68');
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php artisan test tests/Feature/CheckoutCurrencySnapshotTest.php`

Expected: FAIL because success/account use current hard-coded formatting.

- [ ] **Step 3: Implement snapshot-aware historical presentation**

For existing legacy orders with null rate, use current default rate only as backward-compatible fallback. For snapshot orders, use `formatWithSnapshot()` across success and account records.

- [ ] **Step 4: Run focused tests, migration, build, and browser checks**

Run:
`php artisan test tests/Feature/StorefrontCurrencyContextTest.php tests/Feature/CheckoutCurrencySnapshotTest.php`
`php artisan migrate --force`
`npm run build`

Browser proof:
- Select MYR then USD in header and refresh PDP, PLP, Cart, Drawer, Checkout.
- Switch PDP variant and open Quick Add; confirm dynamic price remains in selected currency.
- Complete a simulated order in USD, change the USD admin rate, and prove success/account receipt stays at the snapshot amount.
- Check desktop/mobile PDP and desktop/mobile PLP for no horizontal overflow or console errors.

- [ ] **Step 5: Commit**

```bash
git add app/Support/StorefrontCurrencyContext.php app/Http/Controllers/StorefrontCurrencyController.php app/Models/Order.php app/Providers/AppServiceProvider.php app/Services/CheckoutService.php database/migrations resources/js/app.js resources/views routes/web.php tests/Feature docs/superpowers
git commit -m "feat: close storefront currency session pricing"
```

## Self-Review

- Session source, rate context, all canonical storefront price surfaces, JS updates, checkout snapshot, and historical receipts are covered by Tasks 1–5.
- Free-form CMS/marketing money copy is explicitly excluded and is not replaced by any task.
- `StorefrontCurrencyContext`, `@storefrontMoney`, `storefront.currency.update`, and `currency_exchange_rate` use the same names in every task.
