# CMS All-Section Public Renderer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every one of the 26 Add Section types editable, previewable, publishable, and rendered from its published MySQL snapshot at `/pages/{slug}`.

**Architecture:** Preserve the existing source-faithful React `SectionRenderer` as the single visual implementation for Builder, saved Preview, and public pages. First certify every existing branch through manifest-driven runtime and browser tests, then replace the stale 10/26 capability gate. The existing public page route will dispatch canonical published documents to the React renderer while preserving the legacy Blade path for pages without a published `PageDocument`.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, React, TypeScript, Vite, Vitest, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-21-cms-all-section-public-renderer-design.md`

## Global Constraints

- Preserve existing Google AI Studio-derived SectionRenderer DOM, Tailwind classes, copy, responsiveness, and interactions; this is L0 data/release work only.
- MySQL `page_documents` remains the sole draft/published truth; no localStorage business ownership.
- New Add Section templates remain `isVisible: true`; never mass-change historical hidden sections.
- Preview consumes saved drafts; the official route consumes published snapshots only.
- Keep real catalog, coupon, and form validation; remove only stale renderer-capability blocking.
- Do not cut over the official route until all 26 renderer checks pass.

---

### Task 1: Certify the existing 26 renderer branches

**Files:**
- Modify: `tests/Feature/Cms/SharedPageRendererTest.php`
- Modify: `tests/Feature/Cms/PageRuntimePayloadTest.php`
- Modify: `resources/admin/src/cms/__tests__/PageRuntimeContext.test.tsx`
- Modify: `resources/admin/tests/shared-page-renderer.spec.ts`
- Reuse: `tests/Fixtures/Cms/page-document-all-sections.v1.json`

**Interfaces:**
- Consumes: `PageRuntimePayloadFactory::forDraft(Page)`, `PageRuntimePayloadFactory::forPublished(Page)`, `VisiblePageRuntimePayload::filter(array)`, `PageSections`.
- Produces: fixture-driven proof that all 26 valid visible sections bootstrap and render in `preview` and `public` modes.

- [ ] **Step 1: Write a failing Laravel 26-type payload test**

```php
$document = $this->allSectionsDocument();
$page = $this->pageWithDocuments(status: 'published', draft: $document, published: $document);
$payload = app(PageRuntimePayloadFactory::class)->forPublished($page->fresh());
$visible = app(VisiblePageRuntimePayload::class)->filter($payload);
$this->assertSame(array_keys($this->manifest()['section']['sectionTypes']), array_column($visible['page']['document']['sections'], 'type'));
```

- [ ] **Step 2: Run the Laravel test and capture the first failed type/path**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Cms/SharedPageRendererTest.php --filter=every_manifest_section_type`

Expected: RED until public payload facts and JSON map tokens are valid for every type.

- [ ] **Step 3: Write a failing React public-render table for every type**

```tsx
it.each(allSectionTypes)('%s renders in public mode', type => {
  const value = publicPayloadWithOnly(type);
  expect(() => renderToStaticMarkup(<PageRuntimeProvider payload={value}><PageSections sections={value.page.document.sections} mode="public" /></PageRuntimeProvider>)).not.toThrow();
});
```

- [ ] **Step 4: Run the React table**

Run: `npm test -- --run src/cms/__tests__/PageRuntimeContext.test.tsx`

Expected: RED names each existing renderer assumption that is unsafe in public mode.

- [ ] **Step 5: Add a browser assertion that every type appears in saved Preview and public mode at desktop and mobile widths**

```ts
for (const type of allSectionTypes) {
  await assertSavedPreviewAndPublicPage(type, { desktop: 1440, mobile: 390 });
}
```

- [ ] **Step 6: Commit the certification tests**

```powershell
git add tests/Feature/Cms/SharedPageRendererTest.php tests/Feature/Cms/PageRuntimePayloadTest.php resources/admin/src/cms/__tests__/PageRuntimeContext.test.tsx resources/admin/tests/shared-page-renderer.spec.ts
git commit -m "test: certify all CMS section renderers"
```

### Task 2: Repair only matrix-proven public runtime gaps

**Files:**
- Modify only when a Task 1 test proves necessity: `app/Domain/Cms/PageRuntimePayloadFactory.php`
- Modify only when a Task 1 test proves necessity: `app/Domain/Cms/RuntimeAdapterRegistry.php`
- Modify only when a Task 1 test proves necessity: `app/Domain/Cms/VisiblePageRuntimePayload.php`
- Modify only when a Task 1 test proves necessity: `resources/admin/src/cms/PageRuntimeContext.tsx`
- Modify only when a Task 1 test proves necessity: `resources/admin/src/components/pages/SectionRenderer.tsx`
- Test: Task 1 files

**Interfaces:**
- Consumes: canonical page documents and active catalog/coupon/form records.
- Produces: browser-valid public payloads; public catalog data stays safe; section-ID maps serialize as objects.

- [ ] **Step 1: For each RED case, add one minimal assertion before production edits**

```php
$payload = app(PageRuntimePayloadFactory::class)->forPublished($page);
$this->assertSame(['expected-id'], array_column($payload['catalog']['products'], 'id'));
```

- [ ] **Step 2: Run only that regression and verify its failure names one missing fact, invalid JSON token, or renderer assumption**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Cms/PageRuntimePayloadTest.php tests/Feature/Cms/SharedPageRendererTest.php --filter=all_section` or `npm test -- --run src/cms/__tests__/PageRuntimeContext.test.tsx`

- [ ] **Step 3: Implement the smallest adapter/token/default correction**

```php
$payload['runtime']['sections'] = $facts === [] ? new stdClass : (object) $facts;
```

Use the existing adapter only for the exact `productIds`, `tabs`, or `newsletterCouponId` fact declared in the manifest. Do not add a visual branch or change Tailwind markup.

- [ ] **Step 4: Run complete Laravel and React matrices**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Cms/PageRuntimePayloadTest.php tests/Feature/Cms/SharedPageRendererTest.php`

Run: `npm test -- --run src/cms/__tests__/PageRuntimeContext.test.tsx`

- [ ] **Step 5: Commit runtime corrections**

```powershell
git add app/Domain/Cms resources/admin/src/cms resources/admin/src/components/pages/SectionRenderer.tsx tests/Feature/Cms
git commit -m "fix: complete CMS shared renderer runtime"
```

### Task 3: Open publication for all certified section types

**Files:**
- Modify: `resources/cms/page-contract.v1.json`
- Modify: `tests/Unit/Domain/Cms/PageDocumentContractTest.php`
- Modify: `tests/Feature/Admin/PagePublicationApiTest.php`
- Modify: `resources/admin/src/cms/__tests__/task11SourcePartials.test.ts`

**Interfaces:**
- Consumes: Task 1/2 proof for every renderer.
- Produces: `PageDocumentContract::assertPublishable()` accepts all valid visible Add Section types while retaining legitimate data validation.

- [ ] **Step 1: Write the failing all-type publication test**

```php
$document = $this->fixture();
app(PageDocumentContract::class)->assertPublishable($document, $this->validFacts($document));
$this->assertTrue(true);
```

- [ ] **Step 2: Verify RED contains only stale `renderer.capable: false` errors**

Run: `C:\xampp\php\php.exe artisan test tests/Unit/Domain/Cms/PageDocumentContractTest.php --filter=every_add_section_type_is_publishable`

- [ ] **Step 3: Change every certified manifest definition to `renderer.capable: true`**

```json
"media_text_split": { "fields": ["subtitle", "content", "mediaUrl", "mediaType", "mediaPosition", "mediaAspectRatio", "buttonText", "buttonLink", "textAlignment", "backgroundColor", "textColor", "paddingY", "customColors"], "publishRequired": ["title"], "renderer": { "capable": true }, "runtimeRequirements": [] }
```

Apply the same one-field release only to each of the other 15 Task 1-certified definitions.

- [ ] **Step 4: Verify valid pages publish while invalid runtime references still fail**

Run: `C:\xampp\php\php.exe artisan test tests/Unit/Domain/Cms/PageDocumentContractTest.php tests/Feature/Admin/PagePublicationApiTest.php`

- [ ] **Step 5: Commit the capability release**

```powershell
git add resources/cms/page-contract.v1.json tests/Unit/Domain/Cms/PageDocumentContractTest.php tests/Feature/Admin/PagePublicationApiTest.php resources/admin/src/cms/__tests__/task11SourcePartials.test.ts
git commit -m "feat: publish every CMS section type"
```

### Task 4: Route canonical published pages to the shared public renderer

**Files:**
- Modify: `app/Http/Controllers/StorefrontController.php`
- Test: `tests/Feature/Cms/SharedPageRendererTest.php`
- Test: `tests/Feature/Admin/PagePublicationApiTest.php`

**Interfaces:**
- Consumes: `Page::publishedDocument()` and `StorefrontPageController::__invoke(Page): Response`.
- Produces: `GET /pages/{page:slug}` renders a canonical published document through React and preserves legacy Blade pages without a published document.

- [ ] **Step 1: Write failing official-route dispatch tests**

```php
$canonical = $this->canonicalPublishedPage($this->allSectionsDocument());
$this->get('/pages/'.$canonical->publishedDocument->slug)->assertOk()->assertSee('id="cms-page-runtime"', false);
$legacy = $this->legacyPublishedPage();
$this->get('/pages/'.$legacy->slug)->assertOk()->assertViewIs('storefront.page');
```

- [ ] **Step 2: Run the official-route tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Cms/SharedPageRendererTest.php --filter=official_route`

Expected: RED for canonical dispatch and green legacy behavior.

- [ ] **Step 3: Add a single dispatch branch before existing legacy rendering**

```php
if ($page->status === 'published' && $page->publishedDocument()->exists()) {
    return app(StorefrontPageController::class)($page->load('publishedDocument'));
}
```

- [ ] **Step 4: Verify draft/archive/unpublish and legacy paths**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Cms/SharedPageRendererTest.php tests/Feature/Admin/PagePublicationApiTest.php`

- [ ] **Step 5: Commit route cutover**

```powershell
git add app/Http/Controllers/StorefrontController.php tests/Feature/Cms/SharedPageRendererTest.php tests/Feature/Admin/PagePublicationApiTest.php
git commit -m "feat: render published CMS documents publicly"
```

### Task 5: Prove normal-user closure in an authenticated browser

**Files:**
- Modify: `resources/admin/tests/shared-page-renderer.spec.ts`
- Create: `resources/admin/playwright.task15.config.ts`
- Create: `resources/admin/tests/fixtures/task15-browser-seed.php`
- Update: `docs/superpowers/specs/2026-08-21-cms-all-section-public-renderer-design.md`

**Interfaces:**
- Consumes: protected Admin pages API, Preview, publish endpoint, and official public route.
- Produces: browser evidence for Add Section -> save -> Preview -> publish -> public route across all 26 types.

- [ ] **Step 1: Write a failing operator journey for Media Text Split**

```ts
await page.getByRole('button', {name: 'Add Section'}).click();
await page.getByRole('button', {name: /Media Text Split/i}).click();
await page.getByRole('button', {name: 'Publish'}).click();
await expect(page.getByText('cannot be rendered')).toHaveCount(0);
await publicPage.goto(`/pages/${slug}`);
await expect(publicPage.locator('[data-cms-page-unavailable]')).toHaveCount(0);
```

- [ ] **Step 2: Run the exact browser test and record RED**

Run: `npx playwright test tests/shared-page-renderer.spec.ts --config=playwright.task15.config.ts --workers=1`

- [ ] **Step 3: Expand this fixture-driven journey to all 26 types at 1440px and 390px**

```ts
for (const type of allSectionTypes) await assertSavedPreviewAndPublicPage(type, {desktop: 1440, mobile: 390});
```

- [ ] **Step 4: Run final verification**

Run: `npx playwright test tests/shared-page-renderer.spec.ts --config=playwright.task15.config.ts --workers=1`

Run: `npm test -- --run`

Run: `npm run lint`

Run: `npm run build`

Run: `C:\xampp\php\php.exe artisan test`

- [ ] **Step 5: Commit closure proof**

```powershell
git add resources/admin/tests resources/admin/playwright.task15.config.ts docs/superpowers/specs/2026-08-21-cms-all-section-public-renderer-design.md
git commit -m "test: verify CMS sections from editor to public page"
```

## Plan Self-Review

- Tasks 1–3 cover all 26 types and publication capability; Task 4 completes public-route consumption; Task 5 proves the actual operator/customer journey on desktop and mobile.
- Hidden historic sections, legitimate runtime validation, source-fidelity UI constraints, and draft/published separation are explicit constraints and tested behavior.
- All names and interfaces refer to existing code: `PageRuntimePayloadFactory`, `VisiblePageRuntimePayload`, `PageSections`, `PageDocumentContract`, `StorefrontController`, and `StorefrontPageController`.
