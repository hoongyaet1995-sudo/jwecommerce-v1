# Source Cart Drawer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Recreate the exported React `CartDrawer.tsx` as a real right-side Laravel storefront cart drawer backed by the existing session cart and server pricing.

**Architecture:** Extract the current cart read model into `CartViewData`, so the full `/cart` page and a new drawer fragment receive identical item, coupon, cross-sell and pricing values. Render the fragment from the shared storefront layout, then use small progressive-enhancement JavaScript to open it and replace only its server-rendered content after cart mutations.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS, Vite, vanilla JavaScript, Lucide, PHPUnit.

## Global Constraints

- The provided exported source, especially `src/components/pages/CartDrawer.tsx`, is the visual and interaction specification.
- Reuse real MySQL catalog data, Laravel session cart state and `CartPricingService`; no runtime mock catalog or client-side pricing.
- Preserve `/cart` as the functional no-JavaScript and full-page fallback.
- All cart mutations remain CSRF-protected and validated server-side.
- Do not collect, render or store payment-card details.

---

### Task 1: Share the Cart Read Model and Drawer Fragment Response

**Files:**
- Create: `app/Services/CartViewData.php`
- Create: `resources/views/storefront/partials/cart-drawer.blade.php`
- Modify: `app/Http/Controllers/CartController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CartTest.php`

**Interfaces:**
- Produces `CartViewData::for(Request $request): array{items: Collection, coupon: ?Coupon, summary: array, addons: Collection}`.
- Produces named GET route `cart.drawer` that renders only the drawer partial.
- `CartController::show()` consumes the same `CartViewData` output as `/cart`.

- [ ] **Step 1: Write failing fragment tests**

```php
public function test_cart_drawer_fragment_uses_the_server_cart_and_source_structure(): void
{
    $this->seed(DatabaseSeeder::class);
    $variant = ProductVariant::query()->where('sku', 'SKU-AETH-ANC-OBS')->firstOrFail();

    $this->withSession(['cart' => [$variant->id => 1]])
        ->get(route('cart.drawer'))
        ->assertOk()
        ->assertSee('Studio Cart (1)')
        ->assertSee('Complimentary Express Shipping')
        ->assertSee('Curated Companion Add-ons')
        ->assertSee('Proceed to Checkout');
}
```

- [ ] **Step 2: Run the single test and confirm route-not-found failure**

Run: `C:\xampp\php\php.exe artisan test --filter=cart_drawer_fragment_uses_the_server_cart`

Expected: FAIL because `cart.drawer` is not defined.

- [ ] **Step 3: Implement the smallest shared cart data provider, route and source-faithful partial**

```php
final class CartViewData
{
    public function for(Request $request): array
    {
        // Build variants from session, resolve valid coupon, call CartPricingService,
        // and select two database-backed add-ons.
    }
}
```

The partial uses the source drawer’s header, shipping meter, item list, +/- quantity controls, remove control, add-ons, coupon area, server totals and checkout action. Its outer element is `id="cart-drawer-content"` so JavaScript can replace it after a mutation.

- [ ] **Step 4: Run the fragment test and existing cart test class**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CartTest.php`

Expected: PASS.

### Task 2: Render the Drawer Globally and Turn the Cart Icon into Its Source Interaction

**Files:**
- Modify: `resources/views/components/layouts/app.blade.php`
- Modify: `resources/views/components/storefront/nav.blade.php`
- Test: `tests/Feature/CartTest.php`

**Interfaces:**
- `data-cart-drawer-open` opens the drawer and preserves its normal `href="/cart"` fallback.
- `#cart-drawer` is an accessible `role="dialog"` off-canvas drawer rendered on storefront responses.

- [ ] **Step 1: Write a failing storefront document assertion**

```php
public function test_storefront_navigation_exposes_the_cart_drawer_trigger_and_dialog(): void
{
    $this->get('/')
        ->assertOk()
        ->assertSee('data-cart-drawer-open', false)
        ->assertSee('id="cart-drawer"', false)
        ->assertSee('aria-label="Open Shopping Cart"', false);
}
```

- [ ] **Step 2: Run the test and confirm the missing-drawer failure**

Run: `C:\xampp\php\php.exe artisan test --filter=storefront_navigation_exposes_the_cart_drawer_trigger`

Expected: FAIL because the layout does not render a drawer dialog.

- [ ] **Step 3: Render the partial in the storefront layout and add trigger data attributes**

```blade
<a href="{{ route('cart.show') }}" data-cart-drawer-open aria-haspopup="dialog">
    {{-- existing source visual cart icon --}}
</a>
```

The drawer uses a fixed full-screen overlay, blur/dark backdrop, right-anchored `max-w-md` panel, `aria-hidden`, close button and `data-cart-drawer-close`; it must stay inert and translated off canvas until JavaScript opens it.

- [ ] **Step 4: Run the targeted document test and CartTest class**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CartTest.php`

Expected: PASS.

### Task 3: Add Progressive Enhancement for Open, Close and Server Mutations

**Files:**
- Modify: `resources/js/app.js`
- Modify: `app/Http/Controllers/CartController.php`
- Test: `tests/Feature/CartTest.php`

**Interfaces:**
- Cart mutation forms inside the drawer carry `data-cart-drawer-form`.
- When JavaScript is available, submitting one requests the existing protected endpoint and refetches `route('cart.drawer')`.
- Close behavior supports the close button, backdrop and Escape and returns focus to the trigger.

- [ ] **Step 1: Write a failing test for the server fragment after a mutation**

```php
public function test_drawer_fragment_reflects_a_cart_quantity_mutation(): void
{
    $product = Product::query()->create(['handle' => 'drawer-tool', 'title' => 'Drawer Tool', 'base_price' => 1250, 'status' => 'active']);
    $variant = ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'DRAWER-001', 'title' => 'Standard', 'price' => 1250, 'stock' => 4]);

    $this->withSession(['cart' => [$variant->id => 1]])
        ->patch(route('cart.items.update', $variant), ['quantity' => 2])
        ->assertRedirect('/cart');

    $this->get(route('cart.drawer'))->assertOk()->assertSee('$25.00');
}
```

- [ ] **Step 2: Run the test and confirm it initially fails because the new partial is absent**

Run: `C:\xampp\php\php.exe artisan test --filter=drawer_fragment_reflects_a_cart_quantity_mutation`

Expected: FAIL before Task 1 implementation; after Task 1 it serves as the mutation regression test.

- [ ] **Step 3: Implement the delegated browser behavior without a duplicate cart store**

```js
document.addEventListener('click', (event) => {
  const trigger = event.target.closest('[data-cart-drawer-open]');
  if (trigger) { event.preventDefault(); openDrawer(trigger); }
});
```

Implement static-class toggling for overlay/panel, body scroll lock, Escape/overlay close, Focus restore and form interception. The successful form request may follow the existing redirect, then `GET /cart/drawer` replaces `#cart-drawer-content` and re-renders Lucide icons.

- [ ] **Step 4: Run CartTest class**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CartTest.php`

Expected: PASS.

### Task 4: Verify Source State and Regression Coverage

**Files:**
- Test: `tests/Feature/CartTest.php`
- Verify: browser at `http://127.0.0.1:8500/`

- [ ] **Step 1: Run focused and full automated tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/CartTest.php`

Run: `C:\xampp\php\php.exe artisan test`

Expected: zero failing tests.

- [ ] **Step 2: Create a production frontend build**

Run: `npm run build`

Expected: Vite exits 0 and updates the production manifest.

- [ ] **Step 3: Browser-verify the actual drawer journey**

At desktop source viewport, add a database product to cart, click the navigation cart icon, verify a right-side panel overlays the unchanged page, then verify close button, backdrop, Escape, quantity mutation and checkout navigation. Capture a source/target state comparison using the user-selected browser.
