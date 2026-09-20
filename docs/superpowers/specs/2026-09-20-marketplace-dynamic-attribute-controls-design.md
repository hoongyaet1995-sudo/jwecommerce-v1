# Marketplace Dynamic Attribute Controls Design

Date: 2026-09-20

Status: Approved direction; implementation plan pending review

Scope: JW Ecommerce Admin `Push JW Master Product to Marketplace` workflow for Shopee and Lazada

## Problem

The Category & Attributes step currently renders only a partial interpretation of marketplace category schemas. Lazada image and date attributes are unsupported, enum inputs use weak browser-native controls, and several required SKU-level fields render as plain text. Shopee distinguishes selection, custom entry, multiple selection, validation type, units, and searchable values, but the UI does not apply all of that metadata consistently. Optional attributes also need the complete Google AI Studio collapsible experience already supplied in the source ZIP.

This prevents a universal ecommerce product from being reviewed correctly across fashion, jewellery, utilities, pharmacy, electronics, and other category taxonomies. The form must be generated from the selected store's current provider schema rather than from category-specific hardcoding.

## Goals

- Render every supported Shopee and Lazada category attribute with a control appropriate to the provider metadata.
- Keep normal/product attributes and SKU/variant attributes distinct.
- Show mandatory attributes immediately and optional attributes in the source-faithful collapsible panel.
- Support marketplace image attributes with an authenticated upload, preview, replacement, validation, provider migration, and reviewed submission flow.
- Preserve typed values through category review, server validation, staging, and provider submission.
- Block publishing when a mandatory provider field is unsupported or incomplete, with an actionable field-level message.
- Reuse the selected Google AI Studio ZIP DOM, classes, interaction patterns, and assets. Developer-only preview controls are excluded.

## Non-goals

- Hardcode attributes for individual categories or merchant industries.
- Infer regulated pharmacy documents or compliance requirements that are absent from the provider schema.
- Add arbitrary PDF/file attachments when Shopee or Lazada product attribute APIs do not declare such a field.
- Change the publishing wizard structure, marketplace category selector, pricing rules, or inventory semantics.
- Send a real provider publish request during automated browser acceptance.

## Design Source

The visual source is `jw-ecommerce-dashboard (38).zip`. The implementation will reuse:

- `OptionalAttributesSection.tsx` for the collapsible header, filled count, searchable selects, custom values, units, and multi-select chips.
- `ProductMediaManager.tsx` and existing media preview patterns for image selection, upload, preview, replacement, progress, and errors.
- Existing input, textarea, select, badge, validation, dark-mode, and responsive grid classes from `PublishProductModal.tsx`.

The source archive stays outside the repository. A fidelity manifest will record the archive hash, selected source files, excluded developer tools, and adapter boundaries.

## Architecture

### 1. Canonical attribute descriptor

Provider responses will be normalized into one UI descriptor while preserving the raw provider identity required for submission.

Each descriptor contains:

- stable key: platform, scope, provider attribute ID/name, and SKU when applicable;
- provider: `shopee` or `lazada`;
- scope: `product` or `variant`;
- label, help text, mandatory flag, sale-property flag, and variant SKU;
- control kind: text, textarea/rich text, integer, decimal, date, single select, multi select, enum with custom entry, multi-enum with custom entry, or image;
- provider options with IDs and display values;
- custom-value permission, maximum selected values, validation type, and units;
- current typed value and completion/validation state;
- dependency metadata when the provider response supplies it;
- `supported` plus a safe reason when the current provider field cannot be completed.

Values will use a typed union instead of comma-separated strings:

- scalar text, number, or date;
- one provider option or custom value;
- an ordered list of provider options/custom values;
- an internal JW image identity plus preview metadata.

Provider adapters serialize these values only at the staging boundary.

### 2. Lazada type mapping

| Lazada `input_type` | JW control | Submission value |
| --- | --- | --- |
| `text` | Text input | String |
| `richText` | Source-faithful multiline/rich text editor | HTML/string accepted by Lazada |
| `numeric` | Number input with finite-number validation | Numeric string |
| `date` | Date picker | Provider date string |
| `singleSelect` | Searchable single select | Selected `en_name` |
| `multiSelect` | Searchable multi-select with chips | Comma-separated selected `en_name` values at serialization only |
| `enumInput` | Searchable single select plus custom value | Selected or custom string |
| `multiEnumInput` | Searchable multi-select plus custom chips | Comma-separated values at serialization only |
| `img` | JW image upload/selector with preview | Lazada-owned `.slatic.net` URL after server migration |

Input type matching is case-insensitive. The existing special Size Chart workflow remains an image-purpose specialization of the same image control and provider migration pipeline.

### 3. Shopee type mapping

| Shopee metadata | JW control |
| --- | --- |
| `input_type = 1` single dropdown | Searchable single select |
| `input_type = 2` single combo box | Searchable single select plus custom value |
| `input_type = 3` free text | Control selected from `input_validation_type` |
| `input_type = 4` multiple dropdown | Searchable multi-select with chips |
| `input_type = 5` multiple combo box | Searchable multi-select with custom chips |
| `support_search_value = true` | Provider-backed attribute search integrated into the same control |
| `format_type = 2` | Unit selector displayed beside the typed/custom value |
| validation integer/float/date/string | Number/date/text input and matching client/server validation |

`max_value_count` limits multi-select choices. A small binary provider option set may use the ZIP radio pattern only when the provider metadata clearly represents one required single choice; it remains the same single-value data contract. UI presentation never changes provider semantics.

### 4. Required and optional layout

- Mandatory fields appear first in the existing responsive two-column grid.
- SKU-level labels include the SKU or variant title, and each variant retains a separate value.
- Optional fields appear in the ZIP `OptionalAttributesSection` panel.
- The panel shows `filled / total`, Expand/Collapse, field descriptions, dependent badges, units, chips, and custom value actions.
- Collapsing the panel preserves state.
- Automatic JW values remain visible as reviewed values when useful; staff can edit them when the provider allows it.
- Unsupported mandatory fields stay visible, explain the missing capability, and disable Next/Publish. Unsupported optional fields may be omitted from submission but remain visible when staff need to understand the provider requirement.

### 5. Category and state lifecycle

Changing store, category, product, product revision, or enabled variants creates a new schema identity. The client cancels stale requests and keeps values only when the new descriptor has the same provider identity, scope, SKU, type, and allowed option. Other values are cleared to prevent cross-category submission.

Provider schema, brands, searchable values, and logistics remain store-scoped. Late responses from a previously selected category cannot overwrite the current form.

### 6. Image attribute workflow

The image control offers two source-faithful actions:

1. choose an existing image owned by the selected JW Master Product; or
2. upload a JPG/PNG/WebP image from inside the attribute field.

An upload is attached to the selected JW product media collection and returns an internal image ID. Client validation checks file type and configured size before upload; the Laravel endpoint repeats MIME, size, authorization, product ownership, and image-integrity checks. The UI shows upload progress, thumbnail, preview, replace, and remove actions.

The reviewed request stores only the internal image ID. During staging:

- Shopee uploads/reuses the owned image through Media Space and binds the returned image ID.
- Lazada migrates/reuses the owned image and binds the returned `.slatic.net` URL.

Raw user-entered external URLs are not accepted. Existing marketplace image receipts remain checksum-bound so changed bytes require a new upload/migration.

If a future provider schema declares a non-image file field, it must receive a separate allowlisted file-purpose adapter with provider documentation, MIME/size constraints, storage rules, and tests. The generic attribute renderer will show an unsupported mandatory-field message until that adapter exists.

## Backend Contracts

### Schema endpoints

Existing category attribute endpoints continue returning provider metadata, expanded to retain all fields needed by the normalizer: input/validation type, format type, units, option IDs/names, custom-value support, maximum value count, search support, mandatory/sale-property flags, and any documented dependency metadata.

Responses remain store- and category-bound. Unknown or malformed mandatory metadata causes a safe unavailable state rather than a text-input fallback.

### Reviewed payload

The browser sends structured reviewed attribute values plus internal image IDs. It never sends provider credentials, Lazada image URLs, Shopee Media Space IDs, or unverified arbitrary field names.

The stage controller validates:

- selected store/product/category ownership;
- descriptor identity and current source revision;
- value type, cardinality, options, custom-value permission, units, and dates/numbers;
- image ownership and purpose;
- complete values for all current mandatory fields and sale properties.

The provider preflight independently reloads the current schema and validates the serialized payload before any product creation request.

### Publishing safety

Image migration/upload is completed before the create-product request where the provider requires the final image identity in the create payload. Lazada Size Chart remains a documented post-create update. A confirmed parent followed by a failed post-create update remains `uncertain` with the external item ID preserved, preventing duplicate creation.

## Error Handling and UX

- Schema load failure: keep category selected, show Retry category requirements, and disable progress.
- Search/load-more failure: preserve selected values and expose retry beside the affected control.
- Invalid field: show the provider-specific rule below the field and focus the first invalid field when staff continue.
- Upload failure: retain the prior confirmed image; show retry/replace without losing other attribute values.
- Category changed during upload: discard the stale field binding while retaining the safely uploaded JW product media asset.
- Provider schema changed before dispatch: reject the saved request and require a fresh review.
- Mandatory unsupported field: name the attribute and type; do not silently coerce it to text.

## Security and Data Integrity

- Authenticate and authorize every schema, search, media, staging, and dispatch endpoint.
- Use server-side credentials only; redact provider responses from user-facing errors and logs.
- Allowlist provider field identities from the freshly loaded schema.
- Prevent prototype keys and duplicate normal/SKU identities.
- Validate uploads by decoded MIME and image content, not filename extension.
- Preserve provider option IDs for Shopee and canonical `en_name` values for Lazada.
- Reject NaN, infinity, invalid dates, duplicate multi-values, excessive cardinality, missing units, and stale image receipts.

## Testing

### Frontend unit and interaction tests

- Every Lazada input type renders the intended control and serializes correctly.
- Every Shopee input type and validation type renders and validates correctly.
- Multi-select chips, custom values, units, provider search, filled counts, and collapse state work.
- Normal and SKU attributes do not overwrite one another.
- Category/store/product changes clear incompatible values and ignore late responses.
- Image select, upload, preview, replace, removal, progress, and failure states work.
- Unsupported mandatory fields block Next/Publish.
- Existing Size Chart behavior uses the common image pipeline.

### Backend tests

- Schema normalization rejects malformed identities and metadata.
- Stage validation rejects fabricated option values, incorrect types, invalid units/cardinality, stale source revisions, and foreign image IDs.
- Shopee image attributes bind current Media Space identities.
- Lazada image attributes bind current migrated image URLs.
- Provider preflight catches schema drift before product creation.
- Post-create Size Chart failure preserves parent identity and uncertain status.

### Browser acceptance

Use connected test stores without completing the final external Publish action:

- Lazada jewellery category with numeric SKU attributes, multi-select optional attributes, and required `img` color thumbnails.
- Lazada fashion category with Size Chart and variant sale properties.
- A Lazada category with date, text/rich text, single select, enum input, and optional fields where available.
- Shopee category covering required dropdown/custom fields, units, multi-select, searchable values, and optional attributes.
- Desktop and mobile modal layouts, including long attribute lists and sticky footer behavior.

Real provider submission is a separate acceptance action performed only after staff review the final payload and intentionally click Publish.

## Acceptance Criteria

- Selecting a supported Shopee or Lazada leaf category loads its current mandatory and optional fields.
- Each supported provider type renders a suitable control with provider constraints.
- Optional Attributes matches the ZIP interaction and displays accurate filled counts.
- Required image fields can be uploaded or selected, previewed, and successfully staged using internal identities.
- Required/variant fields block progress until complete; optional fields never block when empty.
- Switching categories cannot leak stale values into the new payload.
- The server validates the same rules independently and sends only provider-valid serialized values.
- Targeted frontend/backend tests, TypeScript checks, production build, and authenticated localhost browser acceptance pass before release approval is requested.

## Implementation Boundary

All application changes begin in `C:\xampp\htdocs\jwstudio-ecommerce`. The phase remains local until tests, build, and browser evidence are complete. GitHub `main` and Hostinger receive the exact accepted commit only after explicit synchronization approval under the repository delivery contract.
