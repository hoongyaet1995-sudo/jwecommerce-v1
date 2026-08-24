# Operational PLP Filters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Google AI Studio PLP Filter Matrix operate on real Laravel/MySQL catalog data and prove Admin-to-storefront persistence.

**Architecture:** `CatalogFilterService` owns normalized request state, eligible variant filtering, facet counts, and pagination. `StorefrontController` adapts the service to each listing route; Blade retains the approved ZIP structure while serializing URL state.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade, Alpine.js, PHPUnit, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-24-operational-plp-filters-design.md`

## Global Constraints

- Preserve selected ZIP DOM/Tailwind/responsive structure.
- MySQL is the operational source; no ZIP mock records, keyword matching, or localStorage.
- Use active products and enabled variants; in-stock additionally requires positive stock.
- Material labels come directly from `product_variants.material`.
- Follow test-first red-green cycles for every behavior.

---

### Task 1: Introduce filter normalization and eligible-variant querying

**Files:**
- Create: `app/Services/CatalogFilterService.php`
- Create: `tests/Feature/StorefrontCatalogFilterTest.php`

**Interfaces:**
- Produces: `CatalogFilterService::for(Request $request, Builder $base, ?string $forcedCategory = null): CatalogFilterResult`.

- [ ] **Step 1: Write failing price and stock behavior tests**

```php
public function test_price_range_excludes_disabled_matching_variants(): void
{
    $visible = Product::factory()->active()->has(ProductVariant::factory()->state(['price' => 25000, 'enabled' => true]))->create();
    $hidden = Product::factory()->active()->has(ProductVariant::factory()->state(['price' => 25000, 'enabled' => false]))->create();
    $this->get('/shop?min_price=20000&max_price=30000')->assertOk()
        ->assertSee($visible->title)->assertDontSee($hidden->title);
}
```

- [ ] **Step 2: Verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php --filter=price_range_excludes_disabled_matching_variants`

Expected: FAIL because current `listingData()` has no minimum price or enabled-variant condition.

- [ ] **Step 3: Implement the minimal service**

```php
public function for(Request $request, Builder $base, ?string $forcedCategory = null): CatalogFilterResult
{
    $filters = $this->normalize($request, $forcedCategory);
    return new CatalogFilterResult($filters, $this->applyFilters($base, $filters)->paginate(12)->withQueryString(), []);
}
```

- [ ] **Step 4: Verify GREEN**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php`

Expected: PASS for category, inclusive price, enabled/stock, multi-color, multi-material, and cross-facet cases.

### Task 2: Add self-excluding facets and published-review rating

**Files:**
- Modify: `app/Services/CatalogFilterService.php`
- Modify: `tests/Feature/StorefrontCatalogFilterTest.php`

**Interfaces:**
- Produces: facet arrays for categories, colors, materials, rating, and price bounds.

- [ ] **Step 1: Write failing published-rating test**

```php
public function test_rating_filter_uses_only_published_reviews(): void
{
    $published = Product::factory()->active()->has(ProductReview::factory()->published()->state(['rating' => 5]))->create();
    $pending = Product::factory()->active()->has(ProductReview::factory()->pending()->state(['rating' => 5]))->create();
    $this->get('/shop?min_rating=4')->assertOk()->assertSee($published->title)->assertDontSee($pending->title);
}
```

- [ ] **Step 2: Verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php --filter=rating_filter_uses_only_published_reviews`

Expected: FAIL because no rating aggregate/filter exists.

- [ ] **Step 3: Implement published aggregates and facet query cloning**

```php
$query->withAvg(['reviews as published_rating' => fn (Builder $reviews) => $reviews->published()], 'rating')
    ->having('published_rating', '>=', $filters->minRating);
```

- [ ] **Step 4: Verify GREEN**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php`

Expected: PASS for rating, count, zero/disabled facet, and dynamic-bound behaviors.

### Task 3: Bind all PLP routes to the filter contract

**Files:**
- Modify: `app/Http/Controllers/StorefrontController.php`
- Modify: `resources/views/storefront/index.blade.php`
- Modify: `tests/Feature/StorefrontCatalogFilterTest.php`

**Interfaces:**
- Consumes: `CatalogFilterResult::toViewData()`.

- [ ] **Step 1: Write failing route-state pagination test**

```php
public function test_second_page_retains_price_parameters(): void
{
    Product::factory()->count(13)->active()->has(ProductVariant::factory()->state(['enabled' => true, 'price' => 30000]))->create();
    $this->get('/shop?min_price=20000&max_price=40000&page=2')->assertOk()
        ->assertSee('min_price=20000')->assertSee('max_price=40000');
}
```

- [ ] **Step 2: Verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php --filter=second_page_retains_price_parameters`

Expected: FAIL because current listing output uses a collection and static pagination.

- [ ] **Step 3: Replace only controller data binding**

```php
return view('storefront.index', $this->catalogFilters->for($request, Product::query()->where('status', 'active'), $forcedCategory)->toViewData() + $routeData);
```

- [ ] **Step 4: Verify GREEN**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php tests/Feature/StorefrontCatalogPathTest.php`

Expected: PASS and category/collection access behavior remains unchanged.

### Task 4: Port the selected ZIP Filter Matrix through Blade adapters

**Files:**
- Modify: `resources/views/components/storefront/plp-filters.blade.php`
- Modify: `resources/views/storefront/index.blade.php`
- Modify: `resources/js/app.js`
- Modify: `tests/Feature/StorefrontCatalogFilterTest.php`

**Interfaces:**
- Consumes: Task 2 facet payload and Task 3 normalized URL state.

- [ ] **Step 1: Write failing source-control contract test**

```php
public function test_plp_has_dual_price_controls_and_real_material_values(): void
{
    $this->get('/shop')->assertOk()->assertSee('aria-label="Minimum price"', false)
        ->assertSee('aria-label="Maximum price"', false)->assertSee('Material', false);
}
```

- [ ] **Step 2: Verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php --filter=plp_has_dual_price_controls_and_real_material_values`

Expected: FAIL because the current view has a single max-price control and no Material facet.

- [ ] **Step 3: Port exact selected UI with data adapters only**

```blade
<input name="min_price" type="range" aria-label="Minimum price" value="{{ $filters->minPrice }}">
<input name="max_price" type="range" aria-label="Maximum price" value="{{ $filters->maxPrice }}">
@foreach($facets['materials'] as $material)
  <input name="material[]" value="{{ $material['value'] }}" @checked($material['selected'])>
@endforeach
```

- [ ] **Step 4: Verify GREEN**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php`

Expected: PASS for dual ranges, active chips, clear actions, empty state, and no static material list.

### Task 5: Prove Admin-to-storefront closure in a real browser

**Files:**
- Modify: `tests/Feature/Admin/CatalogApiTest.php`
- Create: `resources/admin/tests/storefront-plp-filter-closure.spec.ts`
- Create: `docs/superpowers/reports/2026-08-24-operational-plp-filters-acceptance.md`

**Interfaces:**
- Consumes: existing authenticated Admin Catalog API and completed public PLP contract.

- [ ] **Step 1: Write failing persistence test**

```php
public function test_variant_material_update_drives_storefront_filtering(): void
{
    $this->actingAs($this->admin)->putJson("/admin/api/products/{$product->id}", $payloadWithTitaniumVariant)->assertOk();
    $this->get('/shop?material[]=Titanium')->assertOk()->assertSee($product->title);
}
```

- [ ] **Step 2: Verify service and Admin tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCatalogFilterTest.php tests/Feature/Admin/CatalogApiTest.php`

Expected: PASS.

- [ ] **Step 3: Write Playwright proof around the real Admin edit and fresh PLP request**

```ts
await adminPage.getByRole('button', { name: /save product/i }).click();
await storefrontPage.goto('/shop?material[]=Titanium');
await expect(storefrontPage.getByText(testProductTitle)).toBeVisible();
```

- [ ] **Step 4: Run final browser/build checks and capture screenshots**

Run: `npm run build`

Run: `npx playwright test resources/admin/tests/storefront-plp-filter-closure.spec.ts`

Expected: both exit 0; report before/after desktop, mobile, and shared-URL evidence.
