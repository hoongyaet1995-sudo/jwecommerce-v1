# Catalog managed media and Product Media Gallery

## Purpose

Close the catalog-media operating loop: an administrator uploads or safely imports a catalog image, saves a Category, Collection, or Product, refreshes or re-authenticates, and sees the same durable media consumed by storefront catalog surfaces.

The Product Media Gallery's visual and interaction source is `jw-ecommerce-dashboard (7).zip` (SHA-256 `79C415B135E66B5F64F525B58476BD2D97539BC8E701E033F07A9096BFFA3D0A`). Its selected source files are `src/components/catalog/ProductMediaManager.tsx` (SHA-256 `6C846CCA5DADD52A0326F2448A4100D55E3B09015F4B84823D115536E9CD7DDC`) and its `ProductEditorModal.tsx` integration point (SHA-256 `2855972262CB03325B6E3F07D22E597A4496712EC8CE5510D0518DA0EA7F8DE4`). The archive and extracted source remain local-only.

## Scope

- Port the ZIP's Product Media Gallery DOM, Tailwind classes, visual states, responsive layout, and listed gallery interactions into the current React product editor.
- Add file upload to the existing Category Image URL and Collection Cover Image URL fields using the approved existing `MediaUploadField` visual pattern.
- Use one managed-media upload path and durable MySQL references for Category, Collection, and Product media.
- Persist product image path, alt text, and ordering; position zero is the sole primary storefront image.

Out of scope: SVG support, Data URL persistence, video product gallery, arbitrary URL fetching/proxying, a media-library browsing screen, third-party DnD libraries, public storefront redesign, and route changes.

## Source fidelity and approved variance

The ZIP's `ProductMediaManager` is the exact source for the Product gallery. Category/Collection upload controls are not present in the ZIP; the approved exception is reuse of the existing `MediaUploadField` beside their unchanged URL inputs.

The ZIP uses FileReader Data URLs and accepts SVG. These do not meet the operational security contract. The approved, visible variance is a friendly existing-toast rejection: only JPEG, PNG, WebP, and GIF are accepted, each at most 10 MiB; SVG and Data URLs are rejected. Local upload previews may use temporary browser Object URLs only until the verified managed URL returns.

The ZIP's six Studio Presets remain safe external HTTPS URL choices. They are not uploaded or represented as managed local assets.

## Data model and lifecycle

`cms_media_assets` remains the single uploaded-file registry; its existing server-side MIME, extension/signature, dimensions, checksum, UUID path, and storage rules remain authoritative.

Introduce Catalog media references, separate from CMS document references. A reference contains an asset UUID, owner kind, owner ID, and owner field. Supported owners are Category image, Collection cover, and ProductImage path. All Catalog writes synchronize references in the same transaction as their owner update.

Asset orphan state is computed from both CMS and Catalog references. Replacing, deleting, or reordering Catalog media updates references and only marks an asset orphaned when no CMS or Catalog consumer remains. Existing CMS media behavior must remain unchanged.

`ProductImage` is the durable gallery row: `id`, `path`, `alt`, and `position`. The Catalog API returns product media objects in position order and accepts the same shape. At most twelve images are accepted. Category and Collection continue to store one selected URL in their existing columns.

## API and UI behavior

The existing authenticated upload endpoint is reused; no unauthenticated upload route is added. The Catalog UI uses its response URL only after successful server verification.

For Products, gallery changes remain local until the existing Product Save action. Save atomically validates the complete media array, persists ProductImage rows and references, applies final positions, and updates the first image as primary by ordering. A save is blocked while a file upload is still pending.

Supported Product actions are: native multi-file drag/drop or picker upload, safe direct HTTPS URL add, multiline Batch URLs, source preset append, card DnD reorder, left/right reorder, make-primary, card delete, select-all/multi-delete, lightbox previous/next, URL copy, and per-image Alt Text edit. All post-save changes survive refresh and relogin.

Category and Collection uploads use a single-file existing upload control, then update the existing URL value. Direct URL entry remains available but must meet existing safe external URL policy when saved.

## Validation and security

- Server remains the source of truth for MIME, signature, dimensions, extension and 10 MiB limits.
- Client pre-validation gives immediate feedback only; it cannot bypass server checks.
- Reject SVG, Data URLs, unsafe/malformed/private-network URLs, over-limit galleries, and uploads pending at save time.
- All Catalog mutations remain Admin-authenticated, validated, transactional, and return stable JSON errors to the current toast UI.
- Alt text is text-only, limited to the existing database/API size, and defaults to `{product title} - View #{position + 1}` when omitted.

## Verification

1. Capture ZIP desktop/mobile baselines and selected interaction states before porting; stop if a source baseline cannot be reproduced truthfully.
2. TDD unit/API tests cover upload validation, Category/Collection/Product reference synchronization, shared asset preservation, primary ordering, Alt persistence, gallery cap, unsafe input rejection, and transaction rollback.
3. Rendered browser tests cover all Product interactions plus Category and Collection upload paths using a disposable database and owned server.
4. Real local Admin/browser acceptance covers upload → save → refresh → public consumption for all three entity types.
5. At task completion run focused tests during fixes, then full Laravel/Admin tests, lint, production build, browser evidence, and source/converted desktop/mobile comparison.

## Risks and approvals

- The existing `cms_media_references` table is page-document-only, so reusing it for Catalog would violate ownership semantics; Catalog gets its own reference model while sharing the physical asset registry.
- The source's original Alt map is local-only. Persisting Alt is an approved operational strengthening, with no visual redesign.
- No official storefront route, header, footer, or CMS rendering change is authorized by this task.
