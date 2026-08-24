# Safe Deletion and Variant Visuals Design

## Purpose

Prevent irreversible mistakes in the Admin while making product-option visuals
real, durable catalogue data. The supplied Google AI Studio ZIP remains the
visual and interaction source for the selected modal and product-editor
regions. No official storefront route changes are included.

## Scope and delivery order

This work is two independently releasable subprojects.

### A. Global safe deletion

Create one source-faithful `DeleteConfirmationModal` and use it at existing
Admin delete affordances. It never performs deletion itself: only an explicit
confirm invokes the existing operation. Escape, backdrop close, Cancel, and
the close icon all leave data unchanged. While the operation is pending, the
confirm action is disabled and the modal remains open. API failure leaves the
record untouched and uses the existing toast/error treatment.

The first delivery covers existing real API/transaction-backed operations:

- Product, category and collection deletion.
- CMS page and page-section removal.
- Header and footer menu column/link removal where a real menu save/delete
  operation already exists.
- Product Media Gallery single and batch removal.

The modal receives an item type, label, metadata, impact lines and action
copy. Callers compute accurate impact lines from the loaded record; no impact
number is invented. Examples include product SKU/variant count, category
children, collection product count, page section count, and selected media
count.

Customer, promotion and shipping controls are audited in the same task. If a
control is backed by a durable delete operation, it receives the modal. If it
is currently local/mock only, it may receive visual confirmation but is
explicitly excluded from claiming database deletion until its service/API is
closed in the relevant P0 module.

### B. Product option visual contract

Support exactly one visual representation for a product option value and for a
variant-specific override:

```ts
type VariantVisual =
  | { visualType: 'color'; colorCode: string; optionImage?: never }
  | { visualType: 'image'; optionImage: string; colorCode?: never }
  | { visualType: 'none'; colorCode?: never; optionImage?: never };
```

`ProductOption` gains a display mode (`color_swatch`, `image_swatch`, `pill`,
`select`) and a map keyed by option value. `ProductVariant` gains an optional
override using the same union. Option-level values are the normal source;
variant overrides are used only when explicitly configured. `none` falls back
to the configured display mode's ordinary text label.

The database owns both representations: product option configuration is stored
in the existing `products.options` JSON document; variant overrides are stored
in explicit nullable `product_variants` columns (or a single constrained JSON
field if migration inspection proves it is the established variant-extension
pattern). The final migration choice must preserve old records as `none`, be
reversible, and be validated by Laravel on every catalog create/update.

Images may be either a managed `/storage/cms/media/...` asset or a URL allowed
by the existing catalog media policy. Local image selection uploads through the
existing media endpoint first and stores its durable path. Product-gallery
reuse stores that existing image path. Browser-only `FileReader` data URLs are
never persisted. PNG, JPEG, WebP and GIF retain the existing media policy;
SVG is rejected for this and all product-option upload paths.

The Admin editor ports the ZIP's source-faithful visual picker, 16-palette
selection, native color input, Hex entry, auto-detection, option display-mode
selector, variant-matrix visual column, batch matching synchronization, and
ProductAdmin miniature preview. Existing ProductMediaManager remains the
source for reusable product images and upload validation.

Public consumers use the same serialized contract:

- PDP option controls render colour swatches, image swatches, pills, or a
  select based on the option display mode.
- PLP/Product cards may show a bounded set of available colour/image visual
  indicators, never untrusted image/data URLs.
- Cart/checkout line presentation continues using the chosen variant and its
  primary product image unless a verified variant image already exists; this
  task does not invent a new cart layout.

## Data integrity and security

- API validation rejects a visual object containing both colour and image,
  invalid `visualType`, invalid Hex, unmanaged/local data URLs, disallowed
  file MIME, oversized files, and media URLs outside existing policy.
- Any visual image path is added to and removed from `catalog_media_references`
  together with the owning option/variant so orphan state remains accurate.
- Matrix regeneration preserves visuals for retained option-value pairs and
  drops only overrides whose option combination no longer exists; the UI must
  show that intentional loss before save.
- Product delete confirmation explains storefront removal and does not bypass
  existing history/transaction safeguards.
- Delete confirmation is accessibility-complete: dialog semantics, labelled
  actions, keyboard Escape, focus hand-off to trigger, and a pending state.

## Source fidelity boundary

The selected ZIP components are listed in the paired source extraction
manifest. Their DOM hierarchy, Tailwind classes, copy, modal states,
responsive behavior and local interactions are preserved in the selected
regions. Laravel/React adapters only inject real state, callbacks and error
states. The user-approved SVG exclusion overrides source copy/accept lists.

No Media Library is introduced here. Curated presets remain an explicitly
mock/first-party demo shortcut; a library browser requires a separate approved
feature boundary and UI source.

## Tests and acceptance

Each subproject starts with focused failing tests and uses one independent
review. During a fix batch only focused tests run. On each subproject's
completion run relevant Laravel/Admin tests, build, and a real authenticated
browser flow.

Safe deletion acceptance proves Cancel/Escape/backdrop do not mutate data,
Confirm mutates exactly once, impacts match record facts, and a failed API
call preserves the modal and data.

Variant visual acceptance proves create/edit/reload/re-login durability,
mutual exclusivity, upload/gallery reuse, invalid input rejection, batch
matching behavior, matrix regeneration rules, Admin list preview, and PDP /
PLP consumption from MySQL.

## Exclusions and approvals

- No official public route cutover.
- No Media Library, AI image generation, third-party image search, or SVG.
- No redesign outside the selected ZIP regions.
- No claims that local/mock CRM, promotions or shipping deletion is database
  closed until those modules have their respective P0 service contracts.

Approved in chat on 2026-08-21: deliver safe deletion first, then the complete
variant-visual MySQL → API → Admin → storefront lifecycle.
