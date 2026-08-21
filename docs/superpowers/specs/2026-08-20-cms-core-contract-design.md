# CMS Core Contract Design

Date: 2026-08-20  
Status: Approved in chat; awaiting written-spec review  
Scope: CMS-01, CMS-02, CMS-03 and CMS-04, with the directly blocking autosave, media and legacy-write boundaries

## 1. Purpose

Convert Design Layout Pages from an optimistic React mock-style editor into an operational CMS whose draft, published page, preview and public storefront all use one versioned page contract.

The required business closure is:

> Admin edit -> Laravel validation/service -> MySQL draft -> refresh persistence -> explicit publish -> MySQL published snapshot -> public storefront render

Google AI Studio remains the UI and interaction source of truth. This design changes persistence and runtime ownership, not the approved visual layout.

## 2. Confirmed Problems

The current implementation has four connected blockers:

1. Laravel validation silently removes most section fields before persistence.
2. Admin uses a 26-type React renderer while public pages use an independent Blade renderer with an eight-type publishing allowlist.
3. Page lifecycle is represented by one `is_published` boolean, so `archived` cannot persist.
4. Draft and published content share the same rows, so editing a live page either leaks changes directly to public or requires unpublishing it.

Related blockers included in this architecture boundary:

- autosave sends a request for every input change and has no server-side concurrency contract;
- Preview renders React memory instead of the saved MySQL draft;
- media uploads store base64 strings in page JSON;
- legacy `/admin/pages*` mutations bypass the protected CMS API contract;
- hidden sections are incorrectly included in publish capability validation;
- JSON import can overwrite page identity and publication state.

## 3. Goals

- Preserve every valid field in the versioned section JSON, including empty strings, `false`, zero and empty arrays.
- Make MySQL the only runtime fact source for CMS pages.
- Keep exactly one editable draft and one published snapshot per page.
- Keep public output on the last published snapshot while new edits autosave to draft.
- Use the same React section components for Admin Canvas, Preview and public rendering.
- Make publishing an explicit, transactional operation.
- Preserve the current UI structure and source Tailwind patterns.
- Provide stable API errors and optimistic-concurrency protection.
- Migrate existing `pages/page_sections` data without losing the currently stored fields.

## 4. Non-Goals

- Complete revision history, rollback UI or historical version browsing.
- Node-based server-side React rendering.
- Redesigning the PageAdmin toolbar, Inspector, Canvas, Preview or public page sections.
- New Marketplace, AI, reporting, payment-provider or logistics functionality.
- Importing an entire Google AI Studio ZIP.
- Supporting arbitrary executable HTML, JavaScript or custom-code sections.

## 5. Canonical Data Model

### 5.1 `pages`

`pages` owns page identity and operational lifecycle only:

- `id`
- `status`: `draft | published | archived`
- `published_at`, nullable
- `archived_at`, nullable
- timestamps

The existing `is_published` column is migrated into `status` and then retired from runtime use.

### 5.2 `page_documents`

Each page has at most two documents:

- `id`
- `page_id`, foreign key with cascade delete
- `stage`: `draft | published`
- `slug`, indexed
- `schema_version`
- `lock_version`
- `content_hash`
- `document`, MySQL JSON
- timestamps

Constraints:

- unique `(page_id, stage)`
- unique `(stage, slug)`
- a page must have one draft document;
- the published document is created on first publish;
- section IDs are stable string UUIDs inside the document and do not change during publish.

`document` is the complete page contract:

```json
{
  "title": "Brand Story",
  "slug": "brand-story",
  "description": "...",
  "template": "landing",
  "heroImage": null,
  "seoTitle": "...",
  "seoDescription": "...",
  "sections": []
}
```

Page metadata and sections are deliberately stored together so publishing cannot expose half of a page update.

### 5.3 Legacy `page_sections`

`page_sections` is a migration source, not a continuing CMS fact source.

Migration composes each page and its ordered sections into one draft document. If the old page was published, the same canonical document is copied into its initial published document. After verification and route cutover, CMS reads and writes no longer use `page_sections`.

The table is not dropped in the first deployment. It remains temporarily available for rollback evidence and is removed only in a later cleanup migration after acceptance.

## 6. Versioned Page and Section Contract

A single versioned contract manifest defines:

- allowed page fields;
- all section types;
- allowed fields for every section type;
- repeatable-item schemas;
- required fields for a visible section;
- runtime adapter requirements;
- renderer capability;
- prohibited values such as base64 media and executable custom code.

Both Laravel and React consume the same generated/static manifest artifact. Laravel never uses `Request::validate()` in a way that silently drops recognized fields.

Contract behavior:

- recognized fields are validated and preserved losslessly;
- unknown fields return `422 unsupported_contract_field` instead of being deleted;
- missing fields remain missing;
- present empty values remain present;
- Inspector controls use own-property presence, recursively, to decide visibility;
- default section JSON is the source for which Inspector fields initially exist;
- missing default keys are not injected by Inspector fallbacks;
- `custom_code_block` remains prohibited from publishing;
- only visible sections participate in required-field, renderer-capability and adapter validation;
- hidden sections remain in the draft document and do not render in Preview or public.

## 7. Shared Renderer Architecture

The Google AI Studio-derived React `SectionRenderer` and its section components become the only visual section renderer.

Consumers:

1. **Admin Canvas** renders the current local draft state for instant feedback.
2. **Preview** flushes autosave, requests the saved draft runtime payload from Laravel, and renders it through the same React components.
3. **Public** uses the Laravel storefront shell, injects the published runtime payload, and mounts the same React components.

Blade owns only:

- HTML document and SEO shell;
- approved storefront header and footer;
- authenticated Preview shell;
- runtime payload serialization;
- React mount target;
- safe no-JavaScript fallback messaging.

The existing `resources/views/components/storefront/page-section.blade.php` visual branches leave the production rendering path after parity acceptance.

Node SSR is explicitly deferred. Public page sections are client-rendered React inside the Laravel shell for P0.

## 8. Runtime Payload and Adapters

Laravel produces a single `PageRuntimePayload` shape for Preview and public:

```json
{
  "page": {},
  "catalog": {
    "products": [],
    "categories": [],
    "collections": []
  },
  "forms": {
    "contactEndpoint": "...",
    "newsletterEndpoint": "..."
  },
  "links": {},
  "context": {
    "mode": "preview|public",
    "currency": "USD"
  }
}
```

Adapters resolve only referenced operational data:

- Product Grid resolves active referenced product IDs and preserves order and configured limits.
- Collection Tabs resolves mapped category/collection IDs and their eligible products.
- Featured Deal validates the referenced product and future end date.
- Newsletter validates its mapped coupon through Laravel/MySQL; JSON stores the coupon ID, not trusted coupon state.
- Contact and Newsletter use Laravel endpoints and persist operational submissions.
- Media fields accept trusted external URLs or managed local asset URLs, never base64 data.

If a visible section lacks its required adapter or references missing/ineligible data, publication is rejected with a stable field-path error. Admin draft saving remains allowed.

## 9. Autosave and Concurrency

### 9.1 Client behavior

- Inspector changes update local Canvas immediately.
- A 600 ms debounce collapses rapid changes into the latest full draft document.
- Only one save per page is in flight; a newer local state replaces queued stale state.
- The client sends the last acknowledged `lockVersion`.
- Successful responses update the acknowledged version without replacing newer local edits.
- A failed request leaves the local draft dirty and uses the existing error toast.
- A `409` conflict never triggers an automatic refetch that discards local edits.

### 9.2 Mandatory flush points

Autosave must complete successfully before:

- opening Preview;
- publishing or publishing changes;
- changing to another page;
- returning to the Page Directory;
- importing a JSON draft.

### 9.3 Server behavior

Draft save is transactional:

1. lock the draft document row;
2. compare `lock_version`;
3. validate the complete versioned document;
4. normalize only explicitly defined canonical representations;
5. write JSON, slug, hash and incremented version;
6. sync managed media references;
7. return the saved document and new version.

Version mismatch returns HTTP `409` with code `cms_version_conflict` and the current server version. It does not return a replacement document that the UI might apply automatically.

## 10. Preview and Publish

### 10.1 Preview

Preview flow:

1. flush current autosave;
2. request protected draft Preview payload from Laravel;
3. render the returned MySQL draft with the shared React renderer;
4. use the real storefront shell adapters rather than mock Aurora header/footer data.

Preview is never publication and never reads the published document.

### 10.2 Publish

Publish flow:

1. flush autosave;
2. lock the page and draft document;
3. validate visible section fields, renderer capabilities and runtime adapters;
4. atomically create or replace the published document with the complete draft;
5. set page status to `published`;
6. update `published_at`, `published` hash and version;
7. commit;
8. return the authoritative page state and runtime validation result.

Public readers see the old published document until commit and the new complete document after commit.

`hasUnpublishedChanges` is derived from draft and published content hashes.

## 11. Page Lifecycle

### Draft

- autosave is enabled;
- public returns 404;
- selecting Published publishes the current draft.

### Published

- public reads only the published document;
- editing continues to autosave the draft;
- public remains unchanged;
- when hashes differ, PageAdmin shows the approved `Publish Changes` toolbar button;
- the button re-runs the transactional publish flow.

### Unpublish to Draft

- page status becomes `draft`;
- public returns 404;
- the published document remains stored;
- current draft is unchanged.

### Archived

- public returns 404;
- both documents remain stored;
- backend editing remains available;
- selecting Published publishes the current draft, not the old published document.

### Delete

- permanent page deletion cascades to both documents and their managed-reference rows;
- this retains the previously confirmed permanent-delete behavior.

## 12. API Surface

Protected Admin API only:

- `GET /admin/api/pages`
- `POST /admin/api/pages`
- `GET /admin/api/pages/{page}/draft`
- `PATCH /admin/api/pages/{page}/draft`
- `GET /admin/api/pages/{page}/preview`
- `POST /admin/api/pages/{page}/publish`
- `POST /admin/api/pages/{page}/unpublish`
- `POST /admin/api/pages/{page}/archive`
- `POST /admin/api/pages/{page}/duplicate`
- `DELETE /admin/api/pages/{page}`
- `POST /admin/api/media-assets`

Public:

- `GET /pages/{slug}` resolves only a page with status `published` and a matching published document slug.

Legacy ContentController page and page-section mutation routes are removed after migration/cutover verification. No React CMS operation writes through non-API routes or localStorage.

Stable API errors use:

```json
{
  "message": "Human-readable summary",
  "code": "stable_machine_code",
  "errors": {
    "sections.2.buttonLink": ["..."]
  }
}
```

## 13. JSON Import

Import edits only the current page's draft document.

The importer:

- requires the current schema version;
- rejects malformed or unknown contract fields;
- ignores/rejects page ID, lifecycle status, published data and timestamps;
- cannot update a different page;
- cannot publish;
- cannot bypass renderer or adapter rules;
- preserves valid empty values and stable section IDs;
- flushes or explicitly resolves current dirty state before import.

Import does not create a template system in P0.

## 14. Managed Media

The existing Upload control remains visually unchanged.

Behavior changes:

- selected files upload as multipart data to Laravel;
- Laravel validates type, size and dimensions/duration where applicable;
- files are stored on the configured managed disk;
- a media-asset database record owns metadata and URL;
- the section field receives the resulting URL;
- `data:` and base64 media values are rejected by the document contract;
- document saves sync reference records;
- detached assets enter an orphan state and are eligible for cleanup after 30 days;
- externally hosted URLs use an explicit protocol/host safety policy.

## 15. UI/UX Boundary

No Google AI Studio redesign is required for this contract batch.

Allowed visible change:

- one L1 `Publish Changes` button using the existing PageAdmin toolbar button pattern;
- visible only when status is Published and draft/published hashes differ.

All other feedback reuses existing controls and toast styles. Accepted ZIP regions for Collection Tabs, Contact Form and Newsletter are integrated only through source-faithful partial extraction after the core contract is stable.

## 16. Migration and Cutover

1. Back up page/page-section counts and canonical JSON hashes.
2. Create new lifecycle and document schema without deleting legacy data.
3. Compose every existing page into a versioned draft document.
4. Create a published document for each previously published page.
5. Verify page counts, section order, IDs, field keys and public slugs.
6. Switch Admin API reads/writes to page documents.
7. Switch protected Preview to saved draft payload.
8. Mount the shared React renderer on an isolated public preview/canary route.
9. Compare Admin Canvas, Preview and canary public desktop/mobile output.
10. After explicit acceptance, cut `/pages/{slug}` to the shared renderer.
11. Disable legacy mutation routes.
12. Keep legacy tables through the acceptance window; remove them only in a separate cleanup.

Official public-route cutover requires explicit user approval after source-fidelity evidence.

## 17. Test and Acceptance Matrix

### Contract tests

- round-trip every default field for all 26 section types;
- preserve empty string, zero, false, null where allowed and empty arrays;
- reject unknown fields without mutating stored draft;
- prove Inspector field visibility follows key presence;
- prove nested item fields round-trip losslessly.

### Autosave tests

- debounce collapses rapid edits;
- refresh returns the saved draft;
- save failure retains dirty local content;
- two-session conflict returns 409 and does not overwrite either local state silently;
- Preview and Publish wait for flush.

### Lifecycle tests

- Draft public 404;
- first publish returns 200;
- editing Published changes draft only;
- public remains on old snapshot;
- Publish Changes promotes atomically;
- unpublish and archive return public 404 while preserving documents;
- republish uses current draft;
- permanent delete cascades.

### Renderer tests

- all 26 types render through the shared React renderer in Canvas, Preview and public;
- hidden sections render only in editing mode;
- visible adapter-invalid sections block publish;
- desktop, tablet and mobile screenshots match accepted source behavior;
- real storefront shell is present in Preview and public.

### Operational adapters

- Product Grid uses real active MySQL products in configured order;
- Collection Tabs uses real categories/collections and limits;
- Contact submission persists and returns a real success response;
- Newsletter subscription persists, validates the mapped coupon and returns the code only after success;
- media upload returns a managed URL and no page document contains base64.

### Security

- unauthenticated Admin writes fail;
- non-admin writes fail;
- CSRF is enforced;
- unsafe URLs and executable custom code are rejected;
- runtime payload exposes only public-safe catalog and coupon fields.

## 18. Completion Gate

This batch is complete only when:

- all existing stored sections have been migrated and verified;
- every supported field survives save, refresh, Preview and publish;
- public output uses the same React renderer as Admin;
- draft edits never leak to public;
- status transitions match this specification;
- no CMS business entity is written to localStorage or legacy page routes;
- Laravel, TypeScript, API and browser tests pass;
- authenticated Admin and public desktop/mobile evidence is captured;
- official `/pages/{slug}` cutover has explicit approval.

