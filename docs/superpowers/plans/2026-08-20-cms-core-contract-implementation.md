# CMS Core Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the current lossy, single-state CMS with a versioned MySQL draft/published document model and one source-faithful React renderer shared by Admin Canvas, saved-draft Preview and public pages.

**Architecture:** `pages` owns lifecycle and `page_documents` owns the complete versioned JSON contract in exactly two stages, draft and published. Laravel services validate and atomically save/promote documents; React uses a debounced optimistic draft controller with lock versions; public Laravel shells mount the same React section components with Laravel-built runtime payloads.

**Tech Stack:** PHP 8.x, Laravel 12, MySQL/InnoDB JSON, React 19, TypeScript 5.8, Vite 6, Tailwind CSS 4, Vitest, PHPUnit, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-20-cms-core-contract-design.md`

## Global Constraints

- Google AI Studio ZIP source remains the absolute UI/UX source of truth; do not redesign, simplify, refactor or replace selected DOM/classes/copy.
- Work only in `C:\xampp\htdocs\jwstudio-ecommerce` unless the user explicitly changes scope.
- Current XAMPP runtime has no `.git`; do not initialize Git or fabricate commits. At each commit checkpoint, record SHA-256 hashes of touched files if execution is still in this non-Git workspace.
- MySQL is the only CMS runtime fact source; React must not persist pages in localStorage.
- Keep exactly one draft and at most one published document per page; do not add revision history or rollback UI.
- Public rendering remains client-side React inside the Laravel storefront shell; do not add Node SSR.
- Preserve empty strings, zero, `false`, allowed `null` values and empty arrays; never silently delete recognized fields.
- Missing JSON keys remain missing and do not produce Inspector controls; existing empty keys remain editable.
- Hidden sections persist in draft but do not render or participate in publish validation.
- Media upload must return managed URLs; page documents must reject base64/data URLs.
- Official `/pages/{slug}` cutover requires fresh explicit user approval after canary desktop/mobile evidence.
- No new Google AI Studio design is required; the sole new visible control is the approved L1 `Publish Changes` button using the existing toolbar pattern.

---

## File Structure

### Shared CMS contract

- Create `resources/cms/page-contract.v1.json` — canonical allowed fields, section schemas, item schemas, adapter requirements and renderer capability.
- Create `app/Domain/Cms/PageDocumentContract.php` — load/cache manifest, validate documents without lossy filtering, return stable field-path errors.
- Create `app/Domain/Cms/PageDocumentHasher.php` — recursively canonicalize object keys while preserving array order, then return a SHA-256 document hash.
- Create `resources/admin/src/cms/pageContract.ts` — typed manifest helpers, own-property field checks and client contract types.

### Persistence and lifecycle

- Create migration `database/migrations/2026_08_20_120000_create_page_documents_and_cms_lifecycle.php`.
- Create `app/Models/PageDocument.php` and modify `app/Models/Page.php`.
- Create `app/Domain/Cms/PageDraftService.php` — create/save draft with optimistic lock.
- Create `app/Domain/Cms/PageCrudService.php` — list, create, duplicate and permanently delete page identities with their draft documents.
- Create `app/Domain/Cms/PagePublicationService.php` — publish, unpublish and archive transactions.
- Create `app/Domain/Cms/LegacyPageDocumentMigrator.php` — compose existing `pages/page_sections` into documents and verify counts/hashes.
- Create `app/Domain/Cms/Data/MigrationResult.php` — immutable migration count/hash verification result.

### Runtime adapters and media

- Create `app/Domain/Cms/PageRuntimePayloadFactory.php`.
- Create `app/Domain/Cms/MediaReferenceSynchronizer.php`.
- Create migrations/models/controllers for `cms_media_assets` and `cms_media_references`.
- Create migrations/models/controllers for `cms_contact_submissions` and `newsletter_subscriptions` required by the accepted Contact and Newsletter sections.

### API and rendering

- Refactor `app/Http/Controllers/Admin/PageApiController.php` into thin service calls.
- Create `app/Http/Controllers/Admin/PagePreviewController.php`.
- Create `app/Http/Controllers/StorefrontPageController.php`.
- Create `app/Support/ViteManifestEntry.php` so Admin and public React entries resolve safely from one manifest.
- Create `resources/admin/src/public-page.tsx` and `resources/views/storefront/cms-page-react.blade.php`.

### React Admin

- Modify `resources/admin/src/types/index.ts` and `resources/admin/src/integration/pageData.ts`.
- Create `resources/admin/src/cms/pageDraftAutosave.ts` as a testable non-visual state machine.
- Modify `AdminContext.tsx`, `PageAdmin.tsx`, `PagePreviewModal.tsx`, `SectionInspector.tsx` and `SectionRenderer.tsx`.
- Integrate only approved partials from ZIP4/ZIP5/ZIP6 after the core contract is passing.

---

### Task 1: Lock the Versioned Contract With Failing Round-Trip Tests

**Files:**
- Create: `resources/cms/page-contract.v1.json`
- Create: `tests/Fixtures/Cms/page-document-all-sections.v1.json`
- Create: `tests/Unit/Domain/Cms/PageDocumentContractTest.php`
- Create: `resources/admin/src/cms/pageContract.ts`
- Create: `resources/admin/src/cms/__tests__/pageContract.test.ts`

**Interfaces:**
- Produces: `PageDocumentContract::validate(array $document): array`
- Produces: `PageDocumentContract::assertPublishable(array $document, array $runtimeFacts): void`
- Produces: `hasOwnContractField(value: object, key: string): boolean`
- Consumes: the 26 `PageSectionType` values and default JSON fields from the accepted source.

- [ ] **Step 1: Add a complete fixture containing every current section type and every defined empty/non-empty field**

The fixture must use stable section IDs and include sentinel values that expose lossy handling:

```json
{
  "title": "Contract Fixture",
  "slug": "contract-fixture",
  "description": "",
  "template": "landing",
  "heroImage": null,
  "seoTitle": "Contract Fixture",
  "seoDescription": "",
  "sections": [
    {
      "id": "sec-hero-banner",
      "type": "hero_banner",
      "title": "Hero",
      "subtitle": "",
      "content": "Body",
      "buttonText": "",
      "buttonLink": "/collections/all",
      "secondaryButtonText": "Learn",
      "secondaryButtonLink": "#learn",
      "overlayOpacity": 0,
      "isVisible": true,
      "items": []
    }
  ]
}
```

Expand the fixture through all 26 types using the actual keys from `AddSectionModal.tsx`, the accepted ZIP4/5/6 partials and `types/index.ts`; do not invent visual fields.

The top-level manifest matrix must contain these source-owned field sets in addition to common `id,type,title,isVisible`:

| Section type | Source-owned fields |
|---|---|
| `hero_banner` | `subtitle,content,mediaUrl,mediaType,buttonText,buttonLink,secondaryButtonText,secondaryButtonLink,textAlignment,backgroundColor,textColor,paddingY,overlayOpacity,customColors` |
| `hero_slider` | `subtitle,content,buttonText,buttonLink,secondaryButtonText,secondaryButtonLink,textAlignment,backgroundColor,textColor,paddingY,overlayOpacity,sliderInterval,sliderAutoplay,sliderHeight,items,customColors` |
| `split_hero` | `subtitle,content,mediaUrl,mediaType,buttonText,buttonLink,secondaryButtonText,secondaryButtonLink,textAlignment,backgroundColor,textColor,paddingY,dealBadge,cardBadge,cardTitle,cardSubtitle,cardPrice,cardExtra,customColors` |
| `announcement_bar` | `subtitle,content,tickerSpeed,backgroundColor,textColor,paddingY,items,customColors` |
| `media_text_split` | `subtitle,content,mediaUrl,mediaType,mediaPosition,mediaAspectRatio,buttonText,buttonLink,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `rich_text` | `subtitle,content,quote,authorName,authorRole,authorAvatar,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `timeline_story` | `subtitle,content,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `stats_counter` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `product_grid` | `subtitle,content,productIds,columnsCount,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `collection_tabs` | `subtitle,content,tabCategories,tabs,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `featured_deal` | `subtitle,content,mediaUrl,mediaType,dealEndDate,discountPercent,dealBadge,buttonText,buttonLink,secondaryButtonText,secondaryButtonLink,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `pricing_table` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `media_gallery` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `video_embed` | `subtitle,content,videoEmbedUrl,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `before_after` | `subtitle,content,beforeImageUrl,afterImageUrl,beforeLabel,afterLabel,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `instagram_feed` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `feature_cards` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `icon_list` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `comparison_matrix` | `subtitle,content,comparisonDimensionName,comparisonOurBrandName,comparisonCompetitorName,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `faq_accordion` | `subtitle,content,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `contact_form` | `subtitle,content,formEmail,contactPhone,contactAddress,contactButtonText,formSuccessMessage,subjectPlaceholder,categoryPlaceholder,messagePlaceholder,inquiryCategories,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `store_locator` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `testimonials_slider` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `logo_cloud` | `subtitle,content,columnsCount,textAlignment,backgroundColor,textColor,paddingY,items,customColors` |
| `cta_strip` | `subtitle,content,buttonText,buttonLink,secondaryButtonText,secondaryButtonLink,textAlignment,backgroundColor,textColor,paddingY,customColors` |
| `newsletter_signup` | `subtitle,content,buttonText,formSuccessMessage,newsletterCouponId,textAlignment,backgroundColor,textColor,paddingY,customColors` |

`tabs` items are exactly `{id,label,sourceType:'category'|'collection',sourceId,productLimit:3|4|6|8}`. Contact inquiry categories are exactly `{id,label}`. General `items` use only the keys declared by the accepted `SectionItem` source interface; per-section requiredness is encoded in the manifest.

- [ ] **Step 2: Write PHP tests that require lossless validation and explicit rejection**

```php
public function test_all_known_fields_round_trip_without_loss(): void
{
    $input = json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')), true, flags: JSON_THROW_ON_ERROR);
    $validated = app(PageDocumentContract::class)->validate($input);

    $this->assertSame($input, $validated);
    $this->assertSame('', data_get($validated, 'sections.0.subtitle'));
    $this->assertSame(0, data_get($validated, 'sections.0.overlayOpacity'));
    $this->assertSame([], data_get($validated, 'sections.0.items'));
}

public function test_unknown_fields_are_rejected_instead_of_removed(): void
{
    $input = $this->fixture();
    $input['sections'][0]['inventedField'] = 'must fail';

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(PageDocumentContract::class)->validate($input);
}
```

- [ ] **Step 3: Run the contract test and verify it fails because the contract class does not exist**

Run:

```powershell
C:\xampp\php\php.exe artisan test tests\Unit\Domain\Cms\PageDocumentContractTest.php
```

Expected: FAIL naming missing `App\Domain\Cms\PageDocumentContract`.

- [ ] **Step 4: Implement the manifest and minimal lossless validator**

`page-contract.v1.json` must define exact JSON types and presence rules. `PageDocumentContract` must walk the input recursively, collect path-specific errors, and return the original typed values after validation; it must not use `Arr::only()` or validated-array filtering.

```php
final class PageDocumentContract
{
    public const VERSION = 1;

    public function validate(array $document): array;

    public function assertPublishable(array $document, array $runtimeFacts): void;
}
```

- [ ] **Step 5: Add TypeScript own-property tests**

```ts
it('distinguishes a missing field from a present empty field', () => {
  expect(hasOwnContractField({ subtitle: '' }, 'subtitle')).toBe(true);
  expect(hasOwnContractField({}, 'subtitle')).toBe(false);
});
```

Run:

```powershell
npm test -- --run src/cms/__tests__/pageContract.test.ts
```

Expected: PASS.

- [ ] **Step 6: Run PHP and TypeScript contract checks together**

Run both commands from their respective roots. Expected: all new contract tests PASS.

- [ ] **Step 7: Checkpoint**

If Git exists, commit `test(cms): lock versioned page contract`. Otherwise record `Get-FileHash` output for the five task files in the execution log.

---

### Task 2: Add Draft and Published Document Persistence

**Files:**
- Create: `database/migrations/2026_08_20_120000_create_page_documents_and_cms_lifecycle.php`
- Create: `app/Models/PageDocument.php`
- Modify: `app/Models/Page.php`
- Create: `tests/Feature/Admin/CmsPageDocumentSchemaTest.php`

**Interfaces:**
- Produces: `Page::draftDocument(): HasOne`
- Produces: `Page::publishedDocument(): HasOne`
- Produces: `PageDocument::STAGE_DRAFT` and `PageDocument::STAGE_PUBLISHED`
- Produces: `PageDocumentHasher::hash(array $document): string`

- [ ] **Step 1: Write the failing schema/model test**

```php
public function test_page_owns_one_draft_and_one_published_document(): void
{
    $page = Page::query()->create(['status' => 'draft']);
    $page->documents()->create([
        'stage' => 'draft',
        'slug' => 'brand-story',
        'schema_version' => 1,
        'lock_version' => 1,
        'content_hash' => hash('sha256', '{}'),
        'document' => ['title' => 'Brand Story', 'slug' => 'brand-story', 'sections' => []],
    ]);

    $this->assertSame('brand-story', $page->fresh()->draftDocument->slug);
    $this->assertNull($page->fresh()->publishedDocument);
}
```

- [ ] **Step 2: Run and verify migration/model failure**

Run:

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\Admin\CmsPageDocumentSchemaTest.php
```

Expected: FAIL because `status`/`page_documents` do not exist.

- [ ] **Step 3: Implement migration and models**

Migration requirements:

```php
$table->enum('status', ['draft', 'published', 'archived'])->default('draft');
$table->timestamp('published_at')->nullable();
$table->timestamp('archived_at')->nullable();

$table->foreignId('page_id')->constrained()->cascadeOnDelete();
$table->enum('stage', ['draft', 'published']);
$table->string('slug');
$table->unsignedSmallInteger('schema_version');
$table->unsignedBigInteger('lock_version')->default(1);
$table->char('content_hash', 64);
$table->json('document');
$table->unique(['page_id', 'stage']);
$table->unique(['stage', 'slug']);
```

Remove the legacy unique index on `pages.slug` and make legacy `pages.slug`/`pages.title` nullable so new canonical pages do not require duplicate identity data. Do not drop `is_published`, `metadata`, the nullable legacy columns or `page_sections` yet; they remain rollback evidence only and are never read by the new services.

Implement `PageDocumentHasher` with recursive key sorting for JSON objects and original-order preservation for JSON lists. Add a unit assertion proving two objects with different key insertion order produce the same hash while two section arrays in different order do not.

- [ ] **Step 4: Run schema test and full migration refresh test**

Run:

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\Admin\CmsPageDocumentSchemaTest.php
```

Expected: PASS.

- [ ] **Step 5: Checkpoint**

Commit `feat(cms): add draft and published document storage`, or record hashes in the non-Git execution log.

---

### Task 3: Migrate and Verify Existing Page Data

**Files:**
- Create: `app/Domain/Cms/LegacyPageDocumentMigrator.php`
- Create: `app/Domain/Cms/Data/MigrationResult.php`
- Create: `app/Console/Commands/MigrateLegacyCmsDocuments.php`
- Create: `tests/Feature/Admin/LegacyCmsDocumentMigrationTest.php`
- Modify: the Task 2 migration only if the migration delegates to the migrator safely.

**Interfaces:**
- Consumes: `PageDocumentContract::validate()`
- Produces: `LegacyPageDocumentMigrator::migratePage(Page $page): MigrationResult`
- Produces command: `cms:migrate-page-documents --verify`

- [ ] **Step 1: Write a failing migration test with title-only and item-rich legacy rows**

```php
public function test_legacy_sections_are_composed_in_position_order_without_field_loss(): void
{
    $page = Page::query()->create(['slug' => 'test-page-1', 'title' => 'Test Page 1', 'is_published' => true]);
    $page->sections()->create(['type' => 'rich_text', 'position' => 1, 'content' => ['title' => 'Second', 'isVisible' => true]]);
    $page->sections()->create(['type' => 'hero_slider', 'position' => 0, 'content' => ['title' => 'First', 'isVisible' => true, 'items' => [['id' => 'slide-1', 'title' => 'Slide']]]]);

    $result = app(LegacyPageDocumentMigrator::class)->migratePage($page);

    $this->assertSame(['hero_slider', 'rich_text'], collect($page->fresh()->draftDocument->document['sections'])->pluck('type')->all());
    $this->assertNotNull($page->fresh()->publishedDocument);
    $this->assertTrue($result->verified);
}
```

- [ ] **Step 2: Run and verify missing migrator failure**

Expected: FAIL naming `LegacyPageDocumentMigrator`.

- [ ] **Step 3: Implement deterministic composition and verification**

The migrator must:

- order legacy rows by `position`;
- convert numeric row IDs to stable section string IDs without collisions;
- merge `type` and `content` without dropping keys;
- compose page metadata into the complete document;
- validate the result;
- create draft and, when `is_published`, published documents in one transaction;
- map `is_published=true` to `pages.status=published` and `false` to `pages.status=draft`;
- compare source section count/order/key sets with composed output;
- refuse to overwrite already migrated documents unless `--force` is explicitly supplied in a non-production recovery run.

- [ ] **Step 4: Run the test and dry-run verification against current MySQL**

Run tests first. Then run read-only verification:

```powershell
C:\xampp\php\php.exe artisan cms:migrate-page-documents --verify
```

Expected for the current snapshot before migration: one page, four sections, zero key losses; command exits 0 without writing under `--verify`.

- [ ] **Step 5: Checkpoint**

Commit `feat(cms): migrate legacy pages into canonical documents`, or record hashes.

---

### Task 4: Implement Optimistically Locked Draft API

**Files:**
- Create: `app/Domain/Cms/PageDraftService.php`
- Create: `app/Domain/Cms/PageCrudService.php`
- Create: `app/Domain/Cms/Exceptions/CmsVersionConflict.php`
- Refactor: `app/Http/Controllers/Admin/PageApiController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Admin/PageDraftApiTest.php`

**Interfaces:**
- Consumes: `PageDocumentContract::validate()`
- Produces: `PageDraftService::save(Page $page, array $document, int $expectedVersion): PageDocument`
- Produces: `PageCrudService::create(array $document): Page`
- Produces: `PageCrudService::duplicate(Page $page): Page`
- Produces: `PageCrudService::delete(Page $page): void`
- Produces endpoints `GET /admin/api/pages/{page}/draft` and `PATCH /admin/api/pages/{page}/draft`

- [ ] **Step 1: Write failing API tests for round-trip and conflict**

```php
public function test_draft_save_round_trips_all_section_fields(): void
{
    $response = $this->actingAs($this->admin())->patchJson(
        route('admin.api.pages.draft.update', $page),
        ['lockVersion' => 1, 'document' => $this->fixture()]
    )->assertOk();

    $response->assertJsonPath('data.document.sections.0.subtitle', '');
    $response->assertJsonPath('data.document.sections.0.overlayOpacity', 0);
    $this->assertSame($this->fixture(), $page->fresh()->draftDocument->document);
}

public function test_stale_draft_save_returns_409_without_overwrite(): void
{
    $this->actingAs($this->admin())->patchJson($url, ['lockVersion' => 0, 'document' => $changed])
        ->assertConflict()
        ->assertJsonPath('code', 'cms_version_conflict');

    $this->assertSame($original, $page->fresh()->draftDocument->document);
}
```

- [ ] **Step 2: Run and verify endpoint failure**

Expected: 404/missing route.

- [ ] **Step 3: Implement transactional compare-and-swap**

```php
public function save(Page $page, array $document, int $expectedVersion): PageDocument
{
    return DB::transaction(function () use ($page, $document, $expectedVersion) {
        $draft = $page->draftDocument()->lockForUpdate()->firstOrFail();
        if ($draft->lock_version !== $expectedVersion) {
            throw CmsVersionConflict::for($draft->lock_version);
        }
        $validated = $this->contract->validate($document);
        $draft->update([
            'slug' => $validated['slug'],
            'document' => $validated,
            'content_hash' => $this->hasher->hash($validated),
            'lock_version' => $draft->lock_version + 1,
        ]);
        return $draft->fresh();
    });
}
```

Return stable `{message, code, errors}` responses. Never refetch/replace client data as part of a conflict response.

- [ ] **Step 4: Run draft tests plus existing Page API tests**

Expected: new tests PASS; existing tests may now fail only where their old API assumptions must be deliberately migrated in Task 5.

- [ ] **Step 5: Move list/create/duplicate/delete onto page documents**

`GET /admin/api/pages` returns page identity, lifecycle status, draft summary, `lockVersion`, `hasPublishedSnapshot` and `hasUnpublishedChanges`. Create validates and writes a new page plus draft in one transaction. Duplicate copies the source draft into a new Draft page with a unique slug and fresh stable section IDs. Delete permanently cascades both documents and media references.

- [ ] **Step 6: Add API tests for create, duplicate and delete**

Prove duplicate never copies Published lifecycle, duplicate section IDs do not collide with the source, and delete removes the page/documents but leaves still-referenced shared media assets.

- [ ] **Step 7: Checkpoint**

Commit `feat(cms): add lossless versioned draft API`, or record hashes.

---

### Task 5: Implement Publication and Lifecycle Transactions

**Files:**
- Create: `app/Domain/Cms/PagePublicationService.php`
- Create: `app/Domain/Cms/Exceptions/CmsPublicationBlocked.php`
- Modify: `app/Http/Controllers/Admin/PageApiController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Admin/PagePublicationApiTest.php`

**Interfaces:**
- Produces: `publish(Page $page, int $expectedDraftVersion): Page`
- Produces: `unpublish(Page $page): Page`
- Produces: `archive(Page $page): Page`
- Consumes: `PageDocumentContract::assertPublishable()` and runtime facts from Task 7.

- [ ] **Step 1: Write lifecycle tests**

Tests must prove:

```php
public function test_editing_published_page_does_not_change_public_snapshot(): void;
public function test_publish_promotes_complete_draft_atomically(): void;
public function test_unpublish_and_archive_keep_both_documents_but_return_public_404(): void;
public function test_republish_from_archived_uses_current_draft(): void;
public function test_hidden_unsupported_section_does_not_block_publish(): void;
public function test_visible_adapter_invalid_section_blocks_publish_with_field_path(): void;
```

- [ ] **Step 2: Run and verify missing lifecycle endpoint failures**

Expected: 404 for publish/unpublish/archive routes.

- [ ] **Step 3: Implement publication transaction**

The service locks page and draft, checks expected version, validates only visible sections for publication, and upserts the full published document. It sets `status=published` and `published_at` in the same transaction.

- [ ] **Step 4: Implement unpublish/archive without deleting snapshots**

`unpublish()` sets `status=draft`, clears `archived_at`, and leaves both documents. `archive()` sets `status=archived` and `archived_at=now()` and leaves both documents.

- [ ] **Step 5: Run lifecycle, draft and existing public tests**

Expected: all lifecycle/draft tests PASS. Replace old boolean expectations with explicit status/snapshot assertions.

- [ ] **Step 6: Checkpoint**

Commit `feat(cms): add atomic publication lifecycle`, or record hashes.

---

### Task 6: Replace Base64 Media With Managed Asset Uploads

**Files:**
- Create: `database/migrations/2026_08_20_121000_create_cms_media_assets.php`
- Create: `app/Models/CmsMediaAsset.php`
- Create: `app/Models/CmsMediaReference.php`
- Create: `app/Http/Controllers/Admin/CmsMediaAssetController.php`
- Create: `app/Domain/Cms/MediaReferenceSynchronizer.php`
- Modify: `routes/web.php`
- Modify: `resources/admin/src/components/pages/SectionInspector.tsx`
- Create: `tests/Feature/Admin/CmsMediaAssetApiTest.php`

**Interfaces:**
- Produces endpoint `POST /admin/api/media-assets`
- Produces response `{data: {id: string, url: string, mimeType: string, size: number}}`
- Produces `MediaReferenceSynchronizer::sync(PageDocument $document): void`

- [ ] **Step 1: Write upload and rejection tests**

```php
Storage::fake('public');
$response = $this->actingAs($admin)->postJson(route('admin.api.media-assets.store'), [
    'file' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
])->assertCreated();
$response->assertJsonPath('data.mimeType', 'image/jpeg');
Storage::disk('public')->assertExists($response->json('data.path'));
```

Add contract tests rejecting `data:image/png;base64,...` in every media field.

- [ ] **Step 2: Run and verify missing route/table failures**

- [ ] **Step 3: Implement multipart upload and reference sync**

Allow exact MIME/size policies documented in validation code. Store metadata, return a URL, extract managed URLs from saved page documents, and sync references in the same draft save transaction.

- [ ] **Step 4: Replace only the upload behavior in the existing source UI**

Keep the current `MediaUploadField` DOM/classes. Replace `FileReader.readAsDataURL()` with `FormData` upload and call `onChange(response.data.url)` after success. Reuse the existing toast/error pattern; do not redesign the field.

- [ ] **Step 5: Add orphan cleanup test**

Prove detached assets are marked orphaned, referenced assets are protected, and only assets orphaned for at least 30 days are cleanup-eligible.

- [ ] **Step 6: Run PHP tests, Admin lint and build**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\Admin\CmsMediaAssetApiTest.php
npm run lint
npm run build
```

- [ ] **Step 7: Checkpoint**

Commit `feat(cms): store managed media outside page JSON`, or record hashes.

---

### Task 7: Build Runtime Payload Adapters

**Files:**
- Create: `app/Domain/Cms/PageRuntimePayloadFactory.php`
- Create: `app/Domain/Cms/RuntimeAdapterRegistry.php`
- Create: `tests/Feature/Cms/PageRuntimePayloadTest.php`
- Modify: `app/Domain/Cms/PageDocumentContract.php`

**Interfaces:**
- Produces: `PageRuntimePayloadFactory::forDraft(Page $page): array`
- Produces: `PageRuntimePayloadFactory::forPublished(Page $page): array`
- Produces: `RuntimeAdapterRegistry::factsFor(array $document): array`
- Consumes real `Product`, `Category`, `Collection` and `Coupon` models.

- [ ] **Step 1: Write failing adapter tests using real database records**

Prove:

- Product Grid returns only requested active products, in section order.
- Missing/inactive product facts block publication but not draft saving.
- Collection Tabs resolve category/collection mappings and product limits.
- Newsletter returns only public-safe coupon facts and rejects exhausted/inactive coupons.
- Preview and public payloads share the same shape while reading different document stages.

- [ ] **Step 2: Run and verify missing factory failures**

- [ ] **Step 3: Implement registry and payload factory with eager loading**

Collect all referenced IDs first, then issue bounded eager-loaded queries; do not query once per section or product card.

```php
public function forPublished(Page $page): array
{
    abort_unless($page->status === 'published' && $page->publishedDocument, 404);
    return $this->build($page->publishedDocument, 'public');
}
```

- [ ] **Step 4: Feed adapter facts into publication validation**

Errors must use exact field paths such as `sections.2.tabs.0.sourceId` and stable code `cms_runtime_reference_invalid`.

- [ ] **Step 5: Run payload/publication tests and query-count assertions**

Expected: PASS with no N+1 growth as the number of Product Grid cards increases.

- [ ] **Step 6: Checkpoint**

Commit `feat(cms): resolve real runtime data for page sections`, or record hashes.

---

### Task 8: Replace Per-Keystroke Saving With Debounced Draft Autosave

**Files:**
- Create: `resources/admin/src/cms/pageDraftAutosave.ts`
- Create: `resources/admin/src/cms/__tests__/pageDraftAutosave.test.ts`
- Modify: `resources/admin/src/integration/pageData.ts`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Modify: `resources/admin/src/components/pages/PageAdmin.tsx`

**Interfaces:**
- Produces: `PageDraftAutosave.schedule(document)`, `.flush()`, `.dispose()` and state `{dirty, saving, acknowledgedVersion, conflict}`
- Produces API methods `fetchPageDraft`, `savePageDraft`, `publishPage`, `unpublishPage`, `archivePage`, `fetchPagePreview`.

- [ ] **Step 1: Write fake-timer autosave tests**

```ts
it('collapses rapid edits into one save containing the latest document', async () => {
  vi.useFakeTimers();
  const save = vi.fn().mockResolvedValue({ lockVersion: 2 });
  const controller = new PageDraftAutosave({ delayMs: 600, lockVersion: 1, save });
  controller.schedule(doc('A'));
  controller.schedule(doc('AB'));
  controller.schedule(doc('ABC'));
  await vi.advanceTimersByTimeAsync(600);
  expect(save).toHaveBeenCalledTimes(1);
  expect(save).toHaveBeenCalledWith(doc('ABC'), 1);
});
```

Also test flush, save failure retaining dirty state, a newer edit during an in-flight save, and 409 conflict without destructive refetch.

- [ ] **Step 2: Run and verify missing class failure**

- [ ] **Step 3: Implement the pure autosave controller**

Keep it independent of React so timing and queue behavior remain deterministic. PageAdmin owns one controller per current page and subscribes to its state.

- [ ] **Step 4: Replace `savePage()` calls in section/title handlers**

Handlers update AdminContext/local page state immediately, then call `schedule()`. Remove success toast per keystroke. Keep success feedback for explicit publish/import actions only.

- [ ] **Step 5: Add mandatory flushes**

Await `flush()` before Preview, Publish Changes, status changes, page switch and Page Directory navigation. If flush fails, cancel the navigation/action and keep current edits visible.

- [ ] **Step 6: Add the approved Publish Changes state without redesign**

When `status === 'published' && hasUnpublishedChanges`, render one button using the existing toolbar action classes. Its handler awaits `flush()`, calls `publishPage(pageId, acknowledgedVersion)`, then refreshes lifecycle/hash metadata. Selecting Draft calls unpublish; selecting Archived calls archive; selecting Published from Draft/Archived publishes the current draft.

- [ ] **Step 7: Run Vitest, lint and build**

Expected: autosave tests PASS, TypeScript clean, Admin build clean.

- [ ] **Step 8: Checkpoint**

Commit `feat(cms): add debounced conflict-safe autosave`, or record hashes.

---

### Task 9: Enforce JSON-Key-Driven Inspector Controls and Safe Import

**Files:**
- Modify: `resources/admin/src/components/pages/SectionInspector.tsx`
- Modify: `resources/admin/src/components/pages/PageAdmin.tsx`
- Create: `resources/admin/src/cms/pageImport.ts`
- Create: `resources/admin/src/cms/__tests__/pageImport.test.ts`

**Interfaces:**
- Consumes: `hasOwnContractField()` from Task 1.
- Produces: `parseDraftImport(raw: string, currentPageId: string): PageDocument`.

- [ ] **Step 1: Write import tests**

Tests must reject ID/status/published/timestamps, unsupported schema versions, unknown fields and malformed sections. They must preserve existing empty values and cannot change the current page ID.

- [ ] **Step 2: Add Inspector component-level assertions or extracted visibility-function tests**

```ts
expect(visibleInspectorFields({ title: 'A', subtitle: '' })).toContain('subtitle');
expect(visibleInspectorFields({ title: 'A' })).not.toContain('subtitle');
```

- [ ] **Step 3: Implement key-presence guards around each control group**

Do not alter the source control DOM/classes. Wrap existing controls with manifest-driven presence checks, including nested repeatable items.

- [ ] **Step 4: Implement strict draft-only import**

Import returns only the document body, then schedules/flushes it through the normal Draft API. It never invokes publish and never sends imported page identity or lifecycle fields.

- [ ] **Step 5: Run Vitest, lint and build**

- [ ] **Step 6: Checkpoint**

Commit `fix(cms): preserve contract fields in inspector and import`, or record hashes.

---

### Task 10: Mount the Shared React Renderer for Saved Preview and Canary Public Pages

**Files:**
- Modify: `resources/admin/src/components/pages/SectionRenderer.tsx`
- Create: `resources/admin/src/cms/PageRuntimeContext.tsx`
- Modify: `resources/admin/src/components/pages/PagePreviewModal.tsx`
- Create: `resources/admin/src/public-page.tsx`
- Modify: `resources/admin/vite.config.ts`
- Create: `app/Support/ViteManifestEntry.php`
- Create: `app/Http/Controllers/Admin/PagePreviewController.php`
- Create: `app/Http/Controllers/StorefrontPageController.php`
- Create: `resources/views/storefront/cms-page-react.blade.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Cms/SharedPageRendererTest.php`

**Interfaces:**
- Produces: `<PageRuntimeProvider payload={payload}>`
- Produces Vite entries `src/main.tsx` and `src/public-page.tsx`
- Produces protected HTML route `/admin/pages/{page}/preview`, protected draft payload endpoint and protected canary route `/admin/cms-canary/pages/{page}`.

- [ ] **Step 1: Write failing controller/manifest tests**

Prove the canary route embeds draft payload, public controller embeds published payload, hidden sections are absent outside editing mode, and both resolve the `src/public-page.tsx` manifest entry safely.

- [ ] **Step 2: Extract runtime data from AdminContext without changing section markup**

Replace `useAdmin().products` inside `SectionRenderer` with `usePageRuntime().catalog.products`. In Admin Canvas, adapt current AdminContext catalog into the same payload shape. Do not change the rendered section DOM/classes.

- [ ] **Step 3: Make Preview load the saved draft through the protected Laravel shell**

After flush, `PagePreviewModal` loads `/admin/pages/{page}/preview` in an iframe inside the existing Preview stage. The protected Laravel route injects the saved draft payload and uses the same storefront shell/React entry as public. Existing Preview toolbar, outline and viewport controls remain source-faithful; viewport controls change the iframe stage width. Remove mock Aurora header/footer data from Preview.

- [ ] **Step 4: Add the standalone public entry**

```tsx
const payload = readPageRuntimeBootstrap();
createRoot(document.getElementById('cms-page-root')!).render(
  <PageRuntimeProvider payload={payload}>
    <PageSections sections={payload.page.sections} mode="public" />
  </PageRuntimeProvider>,
);
```

- [ ] **Step 5: Configure multi-entry Vite output and shared safe manifest resolution**

Both Admin and Laravel public shell must resolve only manifest paths that pass the existing traversal/safe-character checks.

- [ ] **Step 6: Build and run canary tests**

```powershell
npm run lint
npm run build
C:\xampp\php\php.exe artisan test tests\Feature\Cms\SharedPageRendererTest.php
```

- [ ] **Step 7: Checkpoint**

Commit `feat(cms): share React renderer across canvas preview and public canary`, or record hashes.

---

### Task 11: Integrate the Accepted ZIP4/ZIP5/ZIP6 CMS Partials

**Files:**
- Source archives, read-only/private:
  - `C:\Users\User\Downloads\jw-ecommerce-dashboard (4).zip`
  - `C:\Users\User\Downloads\jw-ecommerce-dashboard (5).zip`
  - `C:\Users\User\Downloads\jw-ecommerce-dashboard (6).zip`
- Modify only accepted regions in:
  - `resources/admin/src/types/index.ts`
  - `resources/admin/src/components/pages/AddSectionModal.tsx`
  - `resources/admin/src/components/pages/SectionInspector.tsx`
  - `resources/admin/src/components/pages/SectionRenderer.tsx`

**Interfaces:**
- Consumes the canonical contract/runtime APIs from Tasks 1–10.
- Produces source-faithful Collection Tabs, Contact Form and Newsletter Coupon regions without ZIP mock ownership.

- [ ] **Step 1: Read `google-ai-studio-laravel-fidelity/SKILL.md` and create an extraction manifest**

Record ZIP SHA-256, exact component hashes, accepted visual boundaries and dependencies. Keep raw extracted sources outside the repository.

- [ ] **Step 2: Re-run each source ZIP baseline**

Capture desktop/mobile and interaction evidence for Collection Tabs, Contact Form and Newsletter Coupon before adapting backend data.

- [ ] **Step 3: Port only the accepted component regions**

Preserve source DOM/classes/copy/interactions. Replace mock catalog/coupon/submission values only at the narrow runtime value/event boundary defined by `PageRuntimePayload`.

- [ ] **Step 4: Extend the canonical manifest/fixture for accepted fields**

Include `tabs`, Contact fields and `newsletterCouponId`; rerun full round-trip tests so no new field can be silently stripped.

- [ ] **Step 5: Run source-fidelity browser comparisons, lint, build and contract tests**

Material source differences block completion; do not conceal them through self-authored restyling.

- [ ] **Step 6: Checkpoint**

Commit `feat(cms): integrate accepted CMS source partials`, or record hashes and source manifest.

---

### Task 12: Close Contact and Newsletter Operational Loops

**Files:**
- Create: `database/migrations/2026_08_20_122000_create_cms_contact_submissions.php`
- Create: `database/migrations/2026_08_20_123000_create_newsletter_subscriptions.php`
- Create: `app/Models/CmsContactSubmission.php`
- Create: `app/Models/NewsletterSubscription.php`
- Create: `app/Http/Controllers/Storefront/CmsContactSubmissionController.php`
- Create: `app/Http/Controllers/Storefront/NewsletterSubscriptionController.php`
- Create: `app/Domain/Cms/NewsletterSubscriptionService.php`
- Modify: `routes/web.php`
- Modify: `resources/admin/src/components/pages/SectionRenderer.tsx` only at submit handlers.
- Create: `tests/Feature/Cms/CmsFormSubmissionTest.php`

**Interfaces:**
- Produces public POST endpoints from `PageRuntimePayload.forms`.
- Consumes canonical Coupon service/model and page/section IDs.

- [ ] **Step 1: Write failing persistence, validation, rate-limit and coupon tests**

Prove contact content persists, newsletter email uniqueness is enforced appropriately, invalid/exhausted coupons do not return codes, successful subscription returns the mapped code, and no card/sensitive data is stored.

- [ ] **Step 2: Implement services/controllers with transactions and throttling**

Newsletter service revalidates coupon eligibility at submission time. Page JSON owns only coupon ID. Coupon code is returned only after successful subscription.

- [ ] **Step 3: Replace local `setContactSuccess(true)`/`setIsSubmitted(true)` ownership**

Submit to runtime endpoints, then use the existing source success/error states. Keep form DOM/classes unchanged.

- [ ] **Step 4: Run Feature tests, lint and build**

- [ ] **Step 5: Checkpoint**

Commit `feat(cms): persist contact and newsletter submissions`, or record hashes.

---

### Task 13: Remove Legacy Mutation Bypasses and Update Regression Coverage

**Files:**
- Modify: `routes/web.php`
- Modify or retire page methods in `app/Http/Controllers/Admin/ContentController.php`
- Retire from runtime: `resources/views/admin/pages.blade.php`
- Update: `tests/Feature/Admin/PageApiTest.php`
- Create: `tests/Feature/Admin/CmsLegacyRouteTest.php`
- Repair: `resources/admin/src/integration/__tests__/sourceInventory.test.ts` or restore its required manifest with truthful content.

**Interfaces:**
- Removes all non-API CMS write paths.
- Preserves `/admin/pages` React host GET route.

- [ ] **Step 1: Write failing route tests**

Assert POST/PATCH/DELETE legacy page and page-section routes are absent or return 405, while protected `/admin/api/pages*` routes remain.

- [ ] **Step 2: Remove legacy mutation routes and dead write forms**

Do not remove unrelated menu routes from `ContentController`.

- [ ] **Step 3: Expand API regression tests**

Cover create, draft save, duplicate, reorder, publish, unpublish, archive, delete, authorization, validation, conflict and refresh consistency.

- [ ] **Step 4: Repair the currently failing Vitest inventory suite truthfully**

The current failure references missing `docs/admin-react/component-manifest.md`. Either restore a current generated/verified manifest or change the test to point at the canonical inventory artifact; do not add an empty file merely to turn the test green.

- [ ] **Step 5: Run focused and full suites**

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\Admin tests\Feature\Cms
npm test -- --run
npm run lint
npm run build
```

Expected: zero failing CMS/Admin suites.

- [ ] **Step 6: Checkpoint**

Commit `chore(cms): remove legacy writes and complete regression coverage`, or record hashes.

---

### Task 14: Canary Browser Acceptance and Official Route Cutover Gate

**Files:**
- Create: `resources/admin/tests/cms-page-builder.spec.ts`
- Create evidence directory under the existing project evidence convention; do not add a new top-level folder.
- Modify official public route/view only after explicit approval.

**Interfaces:**
- Consumes a local acceptance admin supplied through `CMS_E2E_EMAIL` and `CMS_E2E_PASSWORD`; never hardcode production credentials.
- Produces desktop/mobile screenshots and interaction results for Admin, Preview and canary public.

- [ ] **Step 1: Write the real-browser acceptance flow**

Playwright must:

1. log into `http://127.0.0.1:8500`;
2. create a Draft page;
3. add representative text, media, commerce, Contact and Newsletter sections;
4. edit subtitle, content, buttons, links, layout and colors;
5. wait for autosave and refresh;
6. prove every value remains;
7. open Preview and prove it uses saved draft;
8. publish and open canary public;
9. edit the published draft and prove public remains unchanged;
10. click Publish Changes and prove public updates;
11. unpublish, archive and delete with the specified status behavior;
12. repeat layout evidence at desktop and mobile widths.

- [ ] **Step 2: Run the full database/API/frontend gate before browser work**

```powershell
C:\xampp\php\php.exe artisan test
npm test -- --run
npm run lint
npm run build
```

Expected: all pass; do not proceed on red tests.

- [ ] **Step 3: Run Playwright against the authenticated XAMPP runtime**

```powershell
$env:CMS_E2E_EMAIL='local-acceptance-admin@example.test'
$env:CMS_E2E_PASSWORD='local-only-secret'
npx playwright test tests/cms-page-builder.spec.ts
```

Use a dedicated local acceptance account, not an existing operator account. Remove acceptance records after evidence capture through explicit application cleanup code, not broad SQL deletion.

- [ ] **Step 4: Present canary fidelity evidence and request cutover approval**

Report source ZIP/component hashes, Admin Canvas/Preview/canary screenshots, DOM/interaction results, test output, runtime payload facts and remaining gaps. Stop here until the user explicitly authorizes replacing `/pages/{slug}`.

- [ ] **Step 5: After approval, cut the official route to `StorefrontPageController` and React shell**

Keep the legacy Blade component available for one rollback window but remove it from active routing. Re-run public desktop/mobile and status lifecycle flows on `/pages/brand-story`.

- [ ] **Step 6: Final verification and checkpoint**

Run the full test/build/browser gate again. Record final file hashes because the current runtime is not a Git checkout.

---

## Execution Checkpoints

The plan should be executed in four review batches:

1. **Contract and storage:** Tasks 1–3.
2. **Draft/publish/media/runtime backend:** Tasks 4–7.
3. **Admin autosave/shared renderer/source partials/forms:** Tasks 8–12.
4. **Bypass removal, regression and canary cutover:** Tasks 13–14.

At each batch boundary:

- run all tests introduced so far;
- run `npm run lint` and the Admin build when React files changed;
- query MySQL counts and document hashes without printing sensitive content;
- report failures before continuing;
- do not cross the official public-route cutover gate without explicit approval.
