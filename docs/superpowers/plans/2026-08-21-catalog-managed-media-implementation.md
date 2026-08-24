# Catalog Managed Media and Product Media Gallery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Category, Collection, and Product media uploads durable, safe, and storefront-consumable while porting ZIP 7's Product Media Gallery with source-faithful UI and interactions.

**Architecture:** Keep `cms_media_assets` as the single verified uploaded-file registry and add Catalog-specific references rather than overloading page-document references. Product images become durable records containing URL, alt text, and position; Category and Collection continue to store their chosen URL while Catalog references protect managed files from cleanup. The ZIP gallery remains a React presentation component, with a narrow adapter between its interactions and the Catalog API.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, React 19, TypeScript, Vite, Tailwind CSS, Vitest, PHPUnit, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-21-catalog-managed-media-design.md`

## Global Constraints

- ZIP 7 `ProductMediaManager.tsx` is the Product Gallery visual/interaction source of truth; preserve its selected DOM, classes, copy, states, and responsiveness.
- Category/Collection upload surface may reuse the existing `MediaUploadField` only; no new visual design is authorized.
- Accept JPEG, PNG, WebP, GIF only, maximum 10 MiB each; reject SVG and Data URLs in client and server paths.
- Store only server-verified managed URLs or `MediaUrlPolicy::isSafe()` external HTTPS URLs; never persist browser Data URLs.
- Product media maximum is twelve entries, indexed `position=0..n-1`; first entry is primary.
- No third-party drag-drop library, public route change, storefront redesign, or Git commit is authorized.
- Follow the fast closure policy: one independent review per task; grouped same-class fixes use focused tests; full tests/build/browser only at task completion.

---

## File structure

- `app/Domain/Catalog/CatalogMediaReferenceSynchronizer.php` — Catalog managed-asset ownership, locking, and reference synchronization.
- `app/Domain/Media/ManagedMediaOrphanState.php` — shared CMS/Catalog orphan-state reconciliation.
- `app/Models/CatalogMediaReference.php` — Catalog reference model.
- `database/migrations/YYYY_MM_DD_HHMMSS_create_catalog_media_references.php` — owner-scoped asset reference table.
- `app/Domain/Cms/MediaReferenceSynchronizer.php` — delegate orphan state to shared lifecycle service.
- `app/Models/CmsMediaAsset.php` — cleanup query checks both CMS and Catalog references.
- `app/Http/Controllers/Admin/CatalogApiController.php` — safe URL validation, Product media-object contract, atomic media/reference updates.
- `resources/admin/src/integration/pageData.ts` — upload return shape and Catalog request typing.
- `resources/admin/src/types/index.ts` — `ProductMediaImage` and Product media contract.
- `resources/admin/src/components/catalog/CatalogMediaUploadField.tsx` — extracted existing upload control behavior for Category/Collection reuse; source-compatible styles only.
- `resources/admin/src/components/catalog/CategoryAdmin.tsx` / `CollectionAdmin.tsx` — mount the approved existing upload control.
- `resources/admin/src/components/catalog/ProductMediaManager.tsx` — ZIP 7 source-faithful gallery adapted to managed URLs and durable alt data.
- `resources/admin/src/components/catalog/ProductEditorModal.tsx` — mount the source gallery and block save during uploads.
- `tests/Feature/Admin/CatalogMediaLifecycleTest.php` / `tests/Feature/Admin/CatalogApiTest.php` — persistence, security, rollback, storefront consumption.
- `resources/admin/src/components/catalog/__tests__/ProductMediaManager.test.tsx` / `resources/admin/src/integration/__tests__/catalogMediaData.test.ts` — UI adapter and client validation behavior.
- `resources/admin/tests/catalog-media-gallery.spec.ts` — isolated rendered browser evidence.
- `.superpowers/sdd/2026-08-20-cms-core-contract-implementation/task-17-*` — source extraction manifest, fidelity matrix, concise result/risk report.

## Task 1: Source manifest and fidelity baseline

**Files:**
- Create: `.superpowers/sdd/2026-08-20-cms-core-contract-implementation/task-17-source-extraction-manifest.md`
- Create: `.superpowers/sdd/2026-08-20-cms-core-contract-implementation/task-17-fidelity-evidence.md`
- Create: `resources/admin/tests/catalog-media-source-baseline.spec.ts`

**Consumes:** ZIP SHA-256 and selected source hashes in the approved spec.

**Produces:** Accepted source desktop/mobile screenshots and a manifest identifying selected component, integration point, local state, assets, dependency set, approved security variance, and exact source interaction assertions.

- [ ] **Step 1: Write a source-baseline Playwright test that opens the extracted ZIP application and asserts the Gallery header, dropzone, preset grid, batch URL dialog, lightbox, Alt modal, primary badge, and mobile grid.**

```ts
test('ZIP 7 ProductMediaManager exposes the approved media interactions', async ({ page }) => {
  await page.goto(sourceUrl);
  await openProductMediaTab(page);
  await expect(page.getByText('Product Images & Media Assets')).toBeVisible();
  await expect(page.getByText('Drag and drop product images here')).toBeVisible();
  await page.getByRole('button', { name: 'Batch URLs' }).click();
  await expect(page.getByText('Batch Import Image URLs')).toBeVisible();
});
```

- [ ] **Step 2: Run the source baseline and verify it fails if the source app cannot start or an asserted source state is missing.**

Run: `cd resources/admin; npx playwright test tests/catalog-media-source-baseline.spec.ts --config=playwright.task17.source.config.ts`

Expected: source app runs and the test captures the source state; otherwise stop and document the blocking mismatch before production work.

- [ ] **Step 3: Add the source config and manifest, recording only the selected component and direct ProductEditorModal dependency.**

```md
| Region | ZIP file | SHA-256 | State/data boundary | Approved variance |
|---|---|---|---|---|
| Product Gallery | `src/components/catalog/ProductMediaManager.tsx` | `6C846...` | image list and local modal state | managed URLs replace Data URLs; SVG rejected |
```

- [ ] **Step 4: Re-run the source baseline; inspect the saved desktop/mobile screenshots and record only accepted evidence.**

Run: `cd resources/admin; npx playwright test tests/catalog-media-source-baseline.spec.ts --config=playwright.task17.source.config.ts`

Expected: PASS with accepted screenshots under `test-results/task-17/source/`.

- [ ] **Step 5: Request the single independent Task 1 review; address only grouped fidelity/source-manifest findings with the focused source test.**

## Task 2: Shared managed-media lifecycle for Catalog

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_catalog_media_references.php`
- Create: `app/Models/CatalogMediaReference.php`
- Create: `app/Domain/Media/ManagedMediaOrphanState.php`
- Create: `app/Domain/Catalog/CatalogMediaReferenceSynchronizer.php`
- Modify: `app/Domain/Cms/MediaReferenceSynchronizer.php`
- Modify: `app/Models/CmsMediaAsset.php`
- Test: `tests/Feature/Admin/CatalogMediaLifecycleTest.php`

**Consumes:** existing `CmsMediaAsset`, `CmsMediaReference`, `MediaReferenceSynchronizer`, `MediaUrlPolicy`, and the Task 1 media source contract.

**Produces:** `CatalogMediaReferenceSynchronizer::syncCategory(Category $category)`, `syncCollection(Collection $collection)`, `syncProduct(Product $product)`, and removal methods that run inside the owning write transaction.

- [ ] **Step 1: Write failing lifecycle tests for Catalog references and cross-domain asset retention.**

```php
public function test_shared_managed_asset_is_not_orphaned_until_both_cms_and_catalog_references_are_removed(): void
{
    $asset = $this->managedAsset();
    $category = Category::factory()->create(['image_url' => $asset->url]);
    $this->catalogMedia->syncCategory($category);
    $this->cmsMedia->sync($this->pageDocumentUsing($asset->url));

    $this->catalogMedia->removeCategory($category);
    $this->assertNull($asset->fresh()->orphaned_at);

    $this->cmsMedia->removeReferencesForPage($categoryPage);
    $this->assertNotNull($asset->fresh()->orphaned_at);
}
```

- [ ] **Step 2: Run the focused lifecycle test and verify RED because Catalog reference storage and synchronization do not exist.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/CatalogMediaLifecycleTest.php`

Expected: FAIL for missing table/model/service or missing Catalog reference behavior.

- [ ] **Step 3: Add the migration, model, and shared orphan-state service.**

```php
Schema::create('catalog_media_references', function (Blueprint $table): void {
    $table->id();
    $table->uuid('asset_id');
    $table->string('owner_type', 32);
    $table->unsignedBigInteger('owner_id');
    $table->string('field', 64);
    $table->timestamps();
    $table->foreign('asset_id')->references('id')->on('cms_media_assets')->cascadeOnDelete();
    $table->unique(['owner_type', 'owner_id', 'field'], 'catalog_media_references_owner_field_unique');
    $table->index('asset_id');
});
```

`ManagedMediaOrphanState::refresh(Collection $lockedAssets)` must consider both `cms_media_references` and `catalog_media_references` before setting `orphaned_at`.

- [ ] **Step 4: Implement Catalog synchronization with URL-to-asset lookup, sorted asset locking, external HTTPS no-op references, and remove methods.**

```php
public function syncProduct(Product $product): void
{
    foreach ($product->images()->orderBy('position')->get() as $image) {
        $this->syncOwner('product_image', $image->getKey(), 'path', $image->path);
    }
}
```

- [ ] **Step 5: Run the lifecycle tests and CMS media regression.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/CatalogMediaLifecycleTest.php tests/Feature/Admin/CmsMediaAssetApiTest.php`

Expected: PASS; a shared asset stays non-orphaned while any CMS or Catalog reference exists.

- [ ] **Step 6: Request the single independent Task 2 review; batch only lifecycle/locking/cleanup findings and use the same focused PHP tests.**

## Task 3: Catalog API media-object contract

**Files:**
- Modify: `app/Http/Controllers/Admin/CatalogApiController.php`
- Modify: `app/Models/ProductImage.php`
- Modify: `app/Domain/Catalog/CatalogMediaReferenceSynchronizer.php`
- Test: `tests/Feature/Admin/CatalogApiTest.php`
- Test: `tests/Feature/Admin/CatalogMediaLifecycleTest.php`

**Consumes:** Task 2 lifecycle services and existing `MediaUrlPolicy`.

**Produces:** Product response `images: Array<{id:string,path:string,alt:string,position:number}>`; Category/Collection safe-upload URL support; transactional Catalog reference updates.

- [ ] **Step 1: Add failing API tests for the exact Product media object, twelve-entry cap, safe URL policy, Alt/position persistence, primary ordering, and rollback.**

```php
$this->actingAs($admin)->patchJson("/admin/api/catalog/products/{$product->id}", [
    ...$payload,
    'images' => [
        ['id' => (string) $second->id, 'path' => $second->path, 'alt' => 'Rear view', 'position' => 0],
        ['id' => (string) $first->id, 'path' => $first->path, 'alt' => 'Front view', 'position' => 1],
    ],
])->assertOk()->assertJsonPath('data.images.0.alt', 'Rear view');

$this->assertDatabaseHas('product_images', ['id' => $second->id, 'position' => 0, 'alt' => 'Rear view']);
```

- [ ] **Step 2: Run the focused API tests and verify RED because the current API accepts string arrays and deletes/recreates untyped image rows.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/CatalogApiTest.php tests/Feature/Admin/CatalogMediaLifecycleTest.php`

Expected: FAIL on `images.*.path`, returned object shape, cap, or reference persistence.

- [ ] **Step 3: Implement the validated media-object request contract and response adapter.**

```php
'images' => ['array', 'max:12'],
'images.*.id' => ['nullable', 'integer'],
'images.*.path' => ['required', 'string', 'max:2048', function (string $attribute, string $value, Closure $fail): void {
    if (! MediaUrlPolicy::isSafe($value)) $fail('The media URL is not allowed.');
}],
'images.*.alt' => ['nullable', 'string', 'max:255'],
'images.*.position' => ['required', 'integer', 'min:0', 'max:11'],
```

Normalize the accepted array to its submitted order, assign contiguous positions, default blank Alt to `{title} - View #{n}`, replace ProductImage rows inside the existing transaction, then call `CatalogMediaReferenceSynchronizer::syncProduct($product->fresh())`.

- [ ] **Step 4: Apply the same safe URL validator and Catalog reference synchronization to Category `image` and Collection `coverImage` saves/deletes.**

```php
if (! MediaUrlPolicy::isSafe((string) ($data['image'] ?? ''))) {
    throw ValidationException::withMessages(['image' => 'The media URL is not allowed.']);
}
```

- [ ] **Step 5: Run focused API/lifecycle tests and target Pint.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/CatalogApiTest.php tests/Feature/Admin/CatalogMediaLifecycleTest.php`

Run: `C:\xampp\php\php.exe vendor\bin\pint --test app/Http/Controllers/Admin/CatalogApiController.php app/Domain/Catalog/CatalogMediaReferenceSynchronizer.php`

Expected: PASS with exact media objects, safe URLs, atomic writes, and stable cleanup state.

- [ ] **Step 6: Request the single independent Task 3 review; batch only contract/validation/transaction findings and rerun these focused tests.**

## Task 4: Source-faithful Catalog upload UI and Product Media Gallery

**Files:**
- Create: `resources/admin/src/components/catalog/CatalogMediaUploadField.tsx`
- Create: `resources/admin/src/components/catalog/ProductMediaManager.tsx`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `resources/admin/src/integration/pageData.ts`
- Modify: `resources/admin/src/components/catalog/CategoryAdmin.tsx`
- Modify: `resources/admin/src/components/catalog/CollectionAdmin.tsx`
- Modify: `resources/admin/src/components/catalog/ProductEditorModal.tsx`
- Test: `resources/admin/src/components/catalog/__tests__/ProductMediaManager.test.tsx`
- Test: `resources/admin/src/integration/__tests__/catalogMediaData.test.ts`

**Consumes:** Task 3 `images` object response/request contract and existing `uploadMediaAsset` endpoint.

**Produces:** source-faithful gallery events whose local state has type `ProductMediaImage[]`, plus the approved single-field upload controls.

- [ ] **Step 1: Write failing Vitest tests for client reject-before-upload behavior, successful file URL replacement, reorder/set-primary/step order, Alt object updates, batch deletion, URL policy, and save-blocking pending uploads.**

```tsx
it('replaces an optimistic Object URL with the verified managed URL before save', async () => {
  render(<ProductMediaManager images={[]} onChange={onChange} upload={async () => '/storage/cms/media/id.jpg'} />);
  await user.upload(screen.getByLabelText(/browse files/i), imageFile);
  await waitFor(() => expect(onChange).toHaveBeenLastCalledWith([
    expect.objectContaining({ path: '/storage/cms/media/id.jpg', alt: 'Aether - View #1', position: 0 }),
  ]));
});
```

- [ ] **Step 2: Run the focused frontend tests and verify RED because the Gallery component and typed Catalog media adapter do not yet exist.**

Run: `cd resources/admin; npm test -- --run src/components/catalog/__tests__/ProductMediaManager.test.tsx src/integration/__tests__/catalogMediaData.test.ts`

Expected: FAIL for missing `ProductMediaManager`, `ProductMediaImage`, or Catalog media upload adapter.

- [ ] **Step 3: Port the ZIP ProductMediaManager structure/classes and replace only its state/data boundary.**

```tsx
type ProductMediaImage = { id?: string; path: string; alt: string; position: number };

const processFiles = async (files: FileList | File[]) => {
  const eligible = validateCatalogImageFiles(files);
  const optimistic = eligible.map(file => ({ file, preview: URL.createObjectURL(file) }));
  setPendingUploads(optimistic.length);
  const uploaded = await Promise.all(optimistic.map(async ({ file }) => ({
    path: await uploadMediaAsset(readAdminBootstrap(), file),
    alt: defaultAlt(productTitle, images.length),
  })));
  onChange(appendAndPosition(images, uploaded));
  setPendingUploads(0);
};
```

Keep ZIP gallery DOM/classes/copy intact except the approved SVG/Data URL rejection copy and the use of durable Alt values.

- [ ] **Step 4: Extract the existing MediaUploadField behavior into `CatalogMediaUploadField` without changing its rendered structure/classes, then mount it beside the Category and Collection URL fields.**

```tsx
<CatalogMediaUploadField
  label="Image URL"
  value={editingCategory.image ?? ''}
  onChange={(image) => setEditingCategory({ ...editingCategory, image })}
/>
```

- [ ] **Step 5: Replace ProductEditorModal's old string URL grid with the new component and prevent Save while `pendingUploads > 0`.**

```tsx
<ProductMediaManager
  images={formData.images}
  onChange={(images) => setFormData({ ...formData, images })}
  onPendingChange={setMediaUploadsPending}
  productTitle={formData.title || 'Product'}
/>
```

- [ ] **Step 6: Run focused frontend tests and typecheck.**

Run: `cd resources/admin; npm test -- --run src/components/catalog/__tests__/ProductMediaManager.test.tsx src/integration/__tests__/catalogMediaData.test.ts`

Run: `cd resources/admin; npm run lint`

Expected: PASS; no SVG/Data URL becomes a saveable image; Product form cannot save during upload.

- [ ] **Step 7: Request the single independent Task 4 review; batch only source-fidelity, accessibility, pending-state, and interaction defects with focused frontend tests.**

## Task 5: Rendered acceptance and final gate

**Files:**
- Create: `resources/admin/playwright.task17.config.ts`
- Create: `resources/admin/tests/catalog-media-gallery.spec.ts`
- Modify: `.superpowers/sdd/2026-08-20-cms-core-contract-implementation/task-17-fidelity-evidence.md`
- Create: `.superpowers/sdd/2026-08-20-cms-core-contract-implementation/task-17-report.md`

**Consumes:** Tasks 1-4.

**Produces:** accepted source-versus-converted evidence and real operational proof for all three Catalog media owners.

- [ ] **Step 1: Write failing rendered tests for each real operating loop.**

```ts
test('product upload, reorder, Alt update, refresh and storefront primary consumption are durable', async ({ page }) => {
  await signInAdmin(page);
  await openProductMedia(page);
  await uploadFixtureImages(page, ['one.png', 'two.webp']);
  await setPrimary(page, 'two.webp');
  await editAlt(page, 'two.webp', 'Midnight headphone rear detail');
  await saveProduct(page);
  await page.reload();
  await expectProductMediaOrder(page, ['two.webp', 'one.png']);
  await expectPublicPrimaryImage(page, 'two.webp');
});
```

- [ ] **Step 2: Run the rendered test and verify RED before test fixture wiring or UI completion.**

Run: `cd resources/admin; npx playwright test tests/catalog-media-gallery.spec.ts --config=playwright.task17.config.ts`

Expected: FAIL until all upload, persistence, and storefront-consumption boundaries exist.

- [ ] **Step 3: Add disposable SQLite seed/setup/teardown that creates an admin, Category, dynamic Collection, Product, variant, and image fixtures; never touch live MySQL.**

```ts
test.afterAll(async () => {
  await stopOwnedServer();
  await removeIsolatedDatabase();
});
```

- [ ] **Step 4: Verify all accepted flows in desktop and mobile widths: Category upload/save/refresh/public consumption; Collection cover upload/save/refresh/public consumption; Product multi-file upload, safe URL/preset/batch URL, reorder/primary/Alt/lightbox/copy/select-delete/save/refresh/public consumption; invalid SVG/Data URL/oversize rejection.**

Run: `cd resources/admin; npx playwright test tests/catalog-media-gallery.spec.ts --config=playwright.task17.config.ts`

Expected: PASS with source and converted screenshots saved under `test-results/task-17/`.

- [ ] **Step 5: Perform the completion gate once: Laravel suite, Admin suite, lint, production build, target Pint, and browser suite.**

Run: `C:\xampp\php\php.exe artisan test`

Run: `cd resources/admin; npm test -- --run; npm run lint; npm run build`

Run: `C:\xampp\php\php.exe vendor\bin\pint --test app/Domain/Catalog app/Domain/Media app/Http/Controllers/Admin/CatalogApiController.php`

Run: `cd resources/admin; npx playwright test tests/catalog-media-gallery.spec.ts --config=playwright.task17.config.ts`

Expected: all executable tests/build/browser checks pass; any platform-specific skip is reported with its exact reason.

- [ ] **Step 6: Inspect original and converted screenshots, record result/key evidence/risks/approval point only, remove temporary extracted source and isolated browser data, then request the single independent Task 5 acceptance review.**
