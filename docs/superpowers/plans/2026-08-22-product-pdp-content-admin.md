# Product PDP Content Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist and source-faithfully edit product PDP rich content and SEO in the protected Admin catalog experience without changing storefront rendering.

**Architecture:** Add product-owned PDP content, curated reviews, and ordered product-to-product relationships to MySQL. Extend the existing `CatalogApiController` product envelope atomically, then adapt the selected ZIP's Tab 4 and Tab 5 components to that canonical contract. Public storefront controllers, routes, and Blade views remain outside this plan.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL/SQLite Feature tests, React, TypeScript, Vite, Playwright, Tailwind/Lucide from the supplied ZIP.

**Spec:** `docs/superpowers/specs/2026-08-22-product-pdp-content-admin-design.md`

## Global Constraints

- Preserve the selected ZIP DOM hierarchy, Tailwind classes, copy, motion, responsive behavior, and interactions for Tabs 4 and 5.
- Do not alter public storefront routes, Blade PDP/PLP renderers, carts, checkout, or public payloads.
- All Admin reads and writes use protected `/admin/api/catalog` endpoints; MySQL is the only runtime source of truth.
- Use Laravel validation, authorization, and a single transaction for each product create/update.
- Use the existing centralized deletion confirmation UI for destructive Tab 4 actions.
- No new visible UI beyond the selected ZIP boundary.
- Do not run broad regressions until the final completion gate; during repair run focused tests only.

---

### Task 1: Canonical PDP content schema and model relations

**Files:**
- Create: `database/migrations/2026_08_22_140000_create_product_pdp_content_tables.php`
- Create: `app/Models/ProductPdpContent.php`
- Create: `app/Models/ProductPdpReview.php`
- Create: `app/Models/ProductPdpRelatedProduct.php`
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/Admin/ProductPdpContentPersistenceTest.php`

**Interfaces:**
- Produces `Product::pdpContent(): HasOne`, `Product::pdpReviews(): HasMany`, and `Product::pdpRelatedProducts(): HasMany`.
- Produces one-to-one `product_pdp_contents` with JSON arrays: `highlights`, `specifications`, `shipping_policy`, `custom_accordions`; scalar `use_store_default_shipping`, `rating_score`, `review_count`, `configurator_label`.
- Produces `product_pdp_reviews` ordered by `sort_order` and `product_pdp_related_products` ordered by `sort_order` with unique source/related pairs.

- [ ] **Step 1: Write the failing persistence test**

```php
public function test_product_pdp_content_relations_preserve_ordered_content_and_related_products(): void
{
    $product = Product::factory()->create();
    $related = Product::factory()->create(['status' => 'active']);

    $product->pdpContent()->create([
        'highlights' => [['id' => 'h-1', 'text' => 'Beta titanium', 'badge' => 'PATENTED']],
        'specifications' => [['id' => 's-1', 'group' => 'Materials', 'label' => 'Frame', 'value' => 'Titanium']],
        'use_store_default_shipping' => false,
        'shipping_policy' => ['shippingBadgeText' => 'Express included'],
        'custom_accordions' => [['id' => 'a-1', 'title' => "What's in the box", 'content' => 'Case']],
        'rating_score' => 4.9,
        'review_count' => 142,
        'configurator_label' => 'FINISH / OPTION',
    ]);
    $product->pdpRelatedProducts()->create(['related_product_id' => $related->id, 'sort_order' => 0]);
    $product->pdpReviews()->create(['author' => 'Ava', 'rating' => 5, 'review_date' => '2026-08-22', 'title' => 'Excellent', 'comment' => 'Clear optics', 'verified_buyer' => true, 'sort_order' => 0]);

    $fresh = $product->fresh(['pdpContent', 'pdpRelatedProducts', 'pdpReviews']);
    $this->assertSame('Beta titanium', $fresh->pdpContent->highlights[0]['text']);
    $this->assertSame($related->id, $fresh->pdpRelatedProducts[0]->related_product_id);
    $this->assertSame('Ava', $fresh->pdpReviews[0]->author);
}
```

- [ ] **Step 2: Run the test to verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ProductPdpContentPersistenceTest.php`

Expected: FAIL because the PDP models/relations and tables do not exist.

- [ ] **Step 3: Create the migration and models**

Implement exact constraints:

```php
Schema::create('product_pdp_related_products', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
    $table->unsignedSmallInteger('sort_order');
    $table->timestamps();
    $table->unique(['product_id', 'related_product_id']);
});
```

Use model casts for all JSON arrays, `use_store_default_shipping` as boolean, and `rating_score` as decimal.

- [ ] **Step 4: Run the persistence test to verify GREEN**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ProductPdpContentPersistenceTest.php`

Expected: PASS.

### Task 2: Atomic catalog API contract

**Files:**
- Modify: `app/Http/Controllers/Admin/CatalogApiController.php`
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/Admin/CatalogPdpContentApiTest.php`

**Interfaces:**
- Consumes Task 1 model relations.
- `productData(Product $product): array` emits `pdpContent`, `crossSellProductIds`, and `reviews`.
- `validatedProduct()` accepts the source object shape and yields stable 422 paths.
- `replaceProductRelations()` writes content, ordered related products, and reviews in the existing DB transaction.

- [ ] **Step 1: Write API RED tests**

```php
public function test_admin_can_save_and_refetch_complete_pdp_content(): void
{
    $response = $this->actingAs($this->admin)->patchJson("/admin/api/catalog/products/{$this->product->id}", $this->catalogPayload([
        'pdpContent' => ['highlights' => [['id' => 'h-1', 'text' => 'Precision', 'badge' => 'ZEISS']], 'useStoreDefaultShipping' => false, 'shippingPolicy' => ['shippingBadgeText' => 'Express included']],
        'crossSellProductIds' => [(string) $this->related->id],
        'reviews' => [['id' => 'r-1', 'author' => 'Ava', 'rating' => 5, 'date' => '2026-08-22', 'title' => 'Excellent', 'comment' => 'Clear optics', 'verifiedBuyer' => true]],
    ]);

    $response->assertOk()->assertJsonPath('data.pdpContent.highlights.0.text', 'Precision');
    $this->getJson('/admin/api/catalog')->assertOk()->assertJsonPath('products.0.crossSellProductIds.0', (string) $this->related->id);
}
```

Add separate tests for unauthenticated 401, non-admin 401/403 according to existing middleware behavior, self-link 422, archived related product 422, more than four 422, invalid review rating 422, malformed nested item 422, and a forced child-write exception proving transaction rollback.

- [ ] **Step 2: Run the API tests to verify RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/CatalogPdpContentApiTest.php`

Expected: FAIL because the new request keys are rejected or not returned.

- [ ] **Step 3: Implement validation, serialization, and atomic persistence**

Add validation rules for each field; use UUID/string IDs only as UI identifiers, while relation IDs are real positive product IDs. Serialize no unpublished/archived candidate in selectable related-products data. Set `shipping_policy` to null when `useStoreDefaultShipping=true`.

- [ ] **Step 4: Run the API tests to verify GREEN**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/CatalogPdpContentApiTest.php`

Expected: PASS.

### Task 3: ZIP source extraction evidence and Admin data types

**Files:**
- Create: `.superpowers/sdd/2026-08-22-product-pdp-content-admin/source-extraction-manifest.md`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `resources/admin/src/integration/adminBootstrap.ts` only if the existing catalog adapter requires new type narrowing
- Test: `resources/admin/src/components/catalog/productPdpContentContract.test.ts`

**Interfaces:**
- Defines TypeScript `ProductHighlightItem`, `ProductSpecItem`, `ProductShippingPolicy`, `ProductCustomAccordion`, `ProductSeedReview`, and optional Product content fields matching Task 2 JSON exactly.
- Makes current Catalog API payload hydrate without localStorage or mock fallback.

- [ ] **Step 1: Write a RED type/adapter contract test**

```ts
it('hydrates complete PDP content from the canonical catalog product envelope', () => {
  const product = readCatalogProduct({ pdpContent: { highlights: [{ id: 'h-1', text: 'Precision' }], useStoreDefaultShipping: true }, crossSellProductIds: ['2'], reviews: [] });
  expect(product.highlights?.[0].text).toBe('Precision');
  expect(product.crossSellProductIds).toEqual(['2']);
});
```

- [ ] **Step 2: Run the test to verify RED**

Run: `cd resources/admin; npm test -- --run src/components/catalog/productPdpContentContract.test.ts`

Expected: FAIL because the TypeScript Product contract does not include the PDP fields.

- [ ] **Step 3: Add source manifest and exact TypeScript types**

Record ZIP SHA, exact selected source paths, source component hashes, DOM boundaries, source desktop/mobile screenshots, and each data dependency classification. Add types that exactly match the Laravel envelope; do not add public renderer types.

- [ ] **Step 4: Run the contract test to verify GREEN**

Run: `cd resources/admin; npm test -- --run src/components/catalog/productPdpContentContract.test.ts`

Expected: PASS.

### Task 4: Source-faithful Tab 4 and Tab 5 adapter

**Files:**
- Create: `resources/admin/src/components/catalog/ProductExtraInfoEditor.tsx`
- Modify: `resources/admin/src/components/catalog/ProductEditorModal.tsx`
- Modify: `resources/admin/src/components/catalog/ProductAdmin.tsx` only if save/error propagation needs an existing callback connection
- Test: `resources/admin/src/components/catalog/ProductEditorModal.pdpContent.test.tsx`
- Test: `resources/admin/tests/product-pdp-content-admin.spec.ts`

**Interfaces:**
- Consumes Task 3 Product types and existing `saveProduct(product)` API action.
- `ProductExtraInfoEditor({ formData, products, onChange })` is source-faithful and edits local Product state only.
- Save remains owned by `ProductEditorModal` and triggers the Task 2 atomic API write.

- [ ] **Step 1: Write focused React RED tests**

```tsx
it('keeps source tab order and writes a highlight only into unsaved editor state', async () => {
  render(<ProductEditorModal product={product} isOpen onClose={vi.fn()} />);
  await user.click(screen.getByRole('button', { name: /4\. PDP Rich Content & Specs/i }));
  await user.type(screen.getByPlaceholderText(/New highlight point/i), 'Beta titanium');
  await user.click(screen.getByRole('button', { name: /Add Point/i }));
  expect(screen.getByDisplayValue('Beta titanium')).toBeVisible();
  expect(saveProduct).not.toHaveBeenCalled();
});
```

Add focused coverage for all six sub-sections, templates, 1–4 related selection limit, central delete-confirmation invocation, and Tab 5 SEO SERP values.

- [ ] **Step 2: Run React tests to verify RED**

Run: `cd resources/admin; npm test -- --run src/components/catalog/ProductEditorModal.pdpContent.test.tsx`

Expected: FAIL because Tab 4 and the selected component do not exist.

- [ ] **Step 3: Port the selected ZIP components without redesign**

Copy the selected source DOM/Tailwind classes and local interaction flow. Replace only source mock `useAdmin()` values with existing products and ProductEditor `formData`; route all destructive actions through the existing global confirmation modal. Do not port or activate any storefront renderer code.

- [ ] **Step 4: Run React tests to verify GREEN**

Run: `cd resources/admin; npm test -- --run src/components/catalog/ProductEditorModal.pdpContent.test.tsx src/components/catalog/productPdpContentContract.test.ts`

Expected: PASS.

### Task 5: Authenticated browser acceptance and completion gate

**Files:**
- Create: `.superpowers/sdd/2026-08-22-product-pdp-content-admin/fidelity-evidence.md`
- Create: `.superpowers/sdd/2026-08-22-product-pdp-content-admin/report.md`
- Test: `resources/admin/tests/product-pdp-content-admin.spec.ts`

**Interfaces:**
- Consumes the completed catalog API and Admin modal.
- Produces screenshot/hash evidence for source and converted desktop/mobile surfaces, saved database facts, and an explicit “storefront unchanged” proof.

- [ ] **Step 1: Write and run browser RED for save/reload**

```ts
test('admin saves PDP content then reloads the exact content without changing a public PDP', async ({ page }) => {
  await signInAsAdmin(page);
  await page.goto('/admin/catalog/products');
  await openProductEditor(page);
  await page.getByRole('button', { name: /PDP Rich Content & Specs/i }).click();
  await addHighlight(page, 'Beta titanium');
  await page.getByRole('button', { name: /Save Product & SKUs/i }).click();
  await page.reload();
  await expect(page.getByDisplayValue('Beta titanium')).toBeVisible();
});
```

- [ ] **Step 2: Run the browser test to verify RED**

Run: `cd resources/admin; npx playwright test tests/product-pdp-content-admin.spec.ts --reporter=line`

Expected: FAIL before the completed adapter/API save flow exists.

- [ ] **Step 3: Run final acceptance gates after all focused tests are green**

Run:

```powershell
C:\xampp\php\php.exe artisan test
Set-Location resources/admin; npm test -- --run
npm run lint
npm run build
npx playwright test tests/product-pdp-content-admin.spec.ts --reporter=line
```

Expected: Laravel/API, Admin unit, lint, build, and authenticated browser acceptance all pass. Record any known unrelated suite constraint separately rather than hiding it.

- [ ] **Step 4: Write concise evidence/report**

Record only outcome, ZIP/source hashes, desktop/mobile screenshots, API/database proof, risks, and the explicit future storefront-consumption approval point.
