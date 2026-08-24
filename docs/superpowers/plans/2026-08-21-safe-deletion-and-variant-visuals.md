# Safe Deletion and Variant Visuals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver source-faithful Admin deletion confirmation and durable
product-option visual swatches without changing official storefront routes.

**Architecture:** A shared Admin confirmation modal owns only intent and
accessibility state; each caller retains its existing operation and provides
truthful impact facts. Variant visuals are canonical Catalog data: product
option configuration stays in `products.options`, variant overrides are
validated and serialized through the Catalog API, and visual-image paths use
the existing media/reference system. The Admin and storefront consume the
same returned data shape.

**Tech Stack:** Laravel 12/PHP 8.2, MySQL, React/TypeScript, Vite, Tailwind,
Vitest, Laravel Feature tests, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-21-safe-deletion-and-variant-visuals-design.md`

## Global Constraints

- `jw-ecommerce-dashboard (8).zip` is the visual source for selected regions;
  preserve its selected DOM, classes, copy and responsive interactions.
- Use MySQL/API state as the only truth; no localStorage or persisted FileReader
  data URLs.
- Allow PNG, JPEG, WebP and GIF only; reject SVG for product-option images.
- Keep official `/pages/{slug}` and all other public route ownership unchanged.
- During fix batches run focused tests only; run independent review once per
  task; run full tests/build/browser only at each completed subproject gate.
- Reuse existing toast/error patterns; do not introduce a new visual language.
- Do not delete, reset, or overwrite user data; browser fixtures use owned,
  isolated data and clean it up by exact identifiers.

---

## File map

| File | Responsibility |
| --- | --- |
| `resources/admin/src/components/common/DeleteConfirmationModal.tsx` | ZIP-faithful shared confirmation UI and focus/pending behavior |
| `resources/admin/src/components/catalog/{ProductAdmin,CategoryAdmin,CollectionAdmin,ProductMediaManager}.tsx` | catalog deletion callers and factual impact copy |
| `resources/admin/src/components/pages/{PageAdmin,SectionInspector}.tsx` | page/section callers |
| `resources/admin/src/components/menus/{HeaderMenuAdmin,FooterMenuAdmin}.tsx` | menu-node callers |
| `resources/admin/src/components/{customers/CustomerAdmin,promotions/PromotionsHub,shipping/{SmartRoutingTab,ShippingRatesTab}}.tsx` | audited true-operation deletion callers |
| `app/Http/Controllers/Admin/CatalogApiController.php` | validation, persistence and API serialization for visual data |
| `app/Models/ProductVariant.php` | variant visual fillable/cast contract |
| `database/migrations/2026_08_21_*_add_visual_to_product_variants.php` | nullable durable override fields |
| `app/Domain/Media/CatalogMediaReferenceSynchronizer.php` | option/variant visual image reference synchronization |
| `resources/admin/src/types/index.ts` | discriminated visual type and API-facing Catalog types |
| `resources/admin/src/components/catalog/{ProductEditorModal,VariantVisualPickerModal,ProductAdmin}.tsx` | source-faithful option/matrix/picker/list UI |
| storefront Catalog/PDP/PLP view-model and selector components found by Task 6 inventory | public consumers of serialized visual data |

## Task 1: Capture ZIP baseline and freeze adapter contract

**Files:**
- Modify: `docs/superpowers/specs/2026-08-21-safe-deletion-and-variant-visuals-source-manifest.md`
- Create: `test-results/safe-deletion-variant-visuals/source/*` (local evidence only)
- Test: no production test; record a source browser baseline.

**Interfaces:**
- Consumes: ZIP SHA-256 `0461732561AE1BACE7C03BBB2056A0519044E14A45F91B87B7A07653FC7C34D7`.
- Produces: completed manifest with selected source screenshots, build command,
  dependency states and source mock limitations.

- [ ] Start the ZIP outside the repository with its declared package manager and
  capture selected desktop/mobile flows: product delete dialog, image delete,
  option display mode, color picker, image picker and batch sync.
- [ ] Record exact component/source hashes already listed in the manifest and
  identify each selected source state as existing adapter, retained mock or
  backend contract gap.
- [ ] Verify that the ZIP's local `FileReader` image preview is visual-only;
  record the project override that upload must produce a managed media URL and
  that SVG is rejected.
- [ ] Stop all owned source processes and update the manifest with baseline
  paths and browser-console result.

## Task 2: Shared delete confirmation primitive

**Files:**
- Create: `resources/admin/src/components/common/DeleteConfirmationModal.tsx`
- Create: `resources/admin/src/components/common/DeleteConfirmationModal.test.tsx`
- Modify: `resources/admin/src/components/catalog/ProductMediaManager.tsx`

**Interfaces:**
- Produces `DeleteConfirmationModalProps` with `open`, `title`, `itemType`,
  `itemName`, `itemDetails`, `warningMessage`, `impactItems`, `confirmLabel`,
  `pending`, `onConfirm`, `onCancel`, and `returnFocusRef`.
- Product Media consumes the component but still emits only a modified images
  array; product persistence remains with `ProductEditorModal`.

- [ ] Write Vitest/React-render tests proving closed dialog renders nothing;
  Cancel, backdrop and Escape call only `onCancel`; Confirm calls `onConfirm`
  once; pending disables confirm/cancel; and focus returns to the trigger.
- [ ] Run the focused test and confirm RED because the component is missing.
- [ ] Port the exact selected ZIP modal DOM/classes/copy. Add only the required
  `pending`, focus-return and `aria-describedby` adapter behavior; do not move
  deletion logic into the modal.
- [ ] Replace ProductMediaManager's direct single/batch `remove()` invocations
  with a pending deletion intent showing one image or selected image count.
- [ ] Re-run focused Vitest tests and a ProductMediaManager interaction test;
  require GREEN before review.
- [ ] Request one independent review limited to accessibility, call-once,
  pending state, and ZIP structure fidelity. Batch any same-class findings.

## Task 3: Roll out confirmation to real deletion operations

**Files:**
- Modify: `resources/admin/src/components/catalog/ProductAdmin.tsx`
- Modify: `resources/admin/src/components/catalog/CategoryAdmin.tsx`
- Modify: `resources/admin/src/components/catalog/CollectionAdmin.tsx`
- Modify: `resources/admin/src/components/pages/PageAdmin.tsx`
- Modify: `resources/admin/src/components/pages/SectionInspector.tsx`
- Modify: `resources/admin/src/components/menus/HeaderMenuAdmin.tsx`
- Modify: `resources/admin/src/components/menus/FooterMenuAdmin.tsx`
- Modify only when underlying call is durable: `resources/admin/src/components/customers/CustomerAdmin.tsx`, `resources/admin/src/components/promotions/PromotionsHub.tsx`, `resources/admin/src/components/shipping/{SmartRoutingTab,ShippingRatesTab}.tsx`
- Create: `resources/admin/tests/delete-confirmation.spec.ts`
- Test: `resources/admin/src/components/common/DeleteConfirmationModal.test.tsx`

**Interfaces:**
- Consumes Task 2 modal and existing AdminContext/API deletion callbacks.
- Produces a caller matrix in the test that documents target, factual impacts,
  Cancel behavior and durable endpoint (or explicit mock-only exclusion).

- [ ] Add focused caller tests that click each listed durable delete trigger,
  assert the exact item label and derived impact lines, cancel and verify no
  callback/API call, then confirm and verify one callback/API call.
- [ ] Run the new caller test suite and capture RED for direct-delete callsites.
- [ ] Add local deletion-intent state in each caller; do not alter its existing
  API callback or response handling. Use these factual impacts:
  product SKU/variant count and storefront availability; category children and
  product count; collection product count/type; page section count; section
  title/type; menu child count; media selection count.
- [ ] Audit customer, promotion and shipping callbacks against their actual
  API/service paths. Integrate only durable calls; record mock-only controls
  in the test matrix with no false database claim.
- [ ] Run focused caller tests, then request one independent review. Apply all
  same-class caller/integrity findings in one focused fix batch.
- [ ] At the Task 3 subproject gate run Admin tests/lint/build and one
  authenticated browser test covering product cancel/confirm, page section
  cancel/confirm and a menu child delete; retain screenshots and clean fixture
  data.

## Task 4: Define and persist the variant visual contract

**Files:**
- Create: `database/migrations/2026_08_21_160000_add_visual_fields_to_product_variants.php`
- Modify: `app/Models/ProductVariant.php`
- Modify: `app/Http/Controllers/Admin/CatalogApiController.php`
- Modify: `app/Domain/Media/CatalogMediaReferenceSynchronizer.php`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `tests/Feature/Admin/CatalogApiTest.php`
- Create: `tests/Feature/Admin/ProductVariantVisualTest.php`

**Interfaces:**
- `ProductOption` becomes `{ id, name, values, displayMode, valueVisuals }`,
  where `valueVisuals[value]` is a `VariantVisual` union.
- Variant payload adds optional `visualType`, `colorCode`, `optionImage`.
- API returns exactly that contract, omitting non-applicable members.

- [ ] Write Laravel tests for create/update/read round trips of color, image
  and none; the rejected cases are both color+image, invalid Hex, data URL,
  SVG image URL, unsafe URL and unknown matching option value.
- [ ] Run focused Feature tests and confirm RED for missing migration/validation.
- [ ] Add nullable visual columns with a reversible migration. Existing rows
  migrate as no override; existing product `options` remain valid and receive
  no fabricated visual values.
- [ ] Implement one normalization method in `CatalogApiController` that
  validates discriminated visual objects, uses the existing allowed catalog
  media URL policy, rejects SVG, and errors at the exact option/variant path.
- [ ] Extend serialization and relation replacement so image paths synchronize
  as catalog media references and reference removal happens after changed or
  deleted configurations are persisted transactionally.
- [ ] Run focused Laravel tests and inspect migration `--pretend` before any
  local-MySQL migration application.
- [ ] Request one independent review of migration reversibility, validation,
  media-reference lifecycle, and API shape. Batch same-class fixes using only
  focused tests.

## Task 5: Port ZIP variant visual Admin UI

**Files:**
- Create: `resources/admin/src/components/catalog/VariantVisualPickerModal.tsx`
- Create: `resources/admin/src/components/catalog/VariantVisualPickerModal.test.tsx`
- Modify: `resources/admin/src/components/catalog/ProductEditorModal.tsx`
- Modify: `resources/admin/src/components/catalog/ProductAdmin.tsx`
- Modify: `resources/admin/src/components/catalog/ProductMediaManager.tsx`
- Create: `resources/admin/tests/variant-visual-editor.spec.ts`

**Interfaces:**
- Consumes Task 4 `ProductOption.valueVisuals` and variant visual fields.
- Picker `onSave(VariantVisual, applyToAllMatching)` returns only a normalized
  union; image uploads call the existing CMS media endpoint and return a
  durable URL before `onSave`.

- [ ] Write focused unit tests for color/image/none mutual exclusivity, Hex
  normalization, auto-detection, 16 source palettes, gallery-path selection,
  rejected SVG/local data URL, and matching-variant synchronization.
- [ ] Write a Playwright editor test that sets an option display mode, opens a
  picker, saves color then image, reloads after Catalog API save, and verifies
  the matrix/list previews use the persisted result.
- [ ] Run focused tests to record RED before component/UI integration.
- [ ] Port selected ZIP markup/classes for the visual picker, display-mode
  controls, Auto-Detect button and variant visual column. Adapt handlers only;
  preserve selected DOM/copy/responsive behavior. Product gallery image reuse
  must select the stored `ProductMedia.path`, not create a browser data URL.
- [ ] Preserve configurations across Cartesian regeneration when the option
  value still exists. Before discarding an orphaned visual show the shared
  confirmation modal with the affected value/variant count.
- [ ] Run focused unit/browser tests, then obtain one independent UI/source
  review. Apply a single batch of fidelity or behavioral fixes.

## Task 6: Consume variant visuals on storefront data paths

**Files:**
- Modify: actual public Catalog/PDP selector components identified with
  `rg -n "options|variants|color_hex|image_url" resources/views app resources/admin/src` at task start
- Modify: the public product view-model/controller only if it does not already
  expose Task 4 fields
- Create: `tests/Feature/Storefront/ProductVariantVisualTest.php`
- Create: `resources/admin/tests/variant-visual-storefront.spec.ts`

**Interfaces:**
- Consumes Task 4 serialized visual fields and product media policy.
- Produces source-compatible swatches in existing PDP/PLP visual zones; no
  new cart layout.

- [ ] Inventory exact currently-rendered PDP, PLP/product-card and cart line
  consumers before edit; write their names into the task report and do not
  widen beyond these consumers.
- [ ] Write Feature/browser tests proving active product visuals render color,
  image, pill and select states; clicking an allowed option selects the
  matching real SKU and unknown/archived products expose no visual data.
- [ ] Run focused tests to establish RED for absent public serialization/UI.
- [ ] Add only data adapters and selected controls within the existing public
  DOM zones. Keep existing product image fallback and cart layout unchanged.
- [ ] Run focused tests, request one independent review, and batch only
  consumer/data-leak/fidelity fixes.

## Task 7: Completion gate and evidence

**Files:**
- Modify: `docs/superpowers/specs/2026-08-21-safe-deletion-and-variant-visuals-source-manifest.md`
- Create: `.superpowers/sdd/2026-08-21-safe-deletion-and-variant-visuals/report.md`
- Create: `test-results/safe-deletion-variant-visuals/{source,converted}/*` (local evidence only)

- [ ] Run the complete relevant Laravel Feature suite, Admin Vitest suite,
  lint and production build after Tasks 3 and 6 are both accepted.
- [ ] Run authenticated Admin browser flows for deletion and variant visuals,
  plus public PDP/PLP desktop/mobile browser flows against a disposable owned
  dataset. Assert refresh and re-login durability; inspect screenshots rather
  than relying only on test success.
- [ ] Record the exact commands/results, ZIP/component hashes, desktop/mobile
  comparisons, mock-only exclusions, and any source-native differences.
- [ ] Confirm no official storefront route was changed, stop only owned test
  processes, remove only owned fixture data/files, and verify cleanup by exact
  identifiers.
- [ ] Report results, risks and approval points concisely. The remaining
  approval is only any future Media Library scope or public route cutover.
