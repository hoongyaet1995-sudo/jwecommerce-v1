# Source Extraction Manifest: Safe Deletion and Variant Visuals

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (8).zip`
- ZIP SHA-256: `0461732561AE1BACE7C03BBB2056A0519044E14A45F91B87B7A07653FC7C34D7`
- Target Laravel project: `C:\xampp\htdocs\jwstudio-ecommerce`
- Target mounted surfaces: Admin management views and Product Editor Modal.
- Requested functions: global deletion confirmation and product option visual
  configuration.
- Explicitly excluded ZIP regions: unrelated dashboard, reports, marketplace,
  CRM visual redesigns, and any Media Library implementation.
- Acceptance intent: selected source UI mounted to existing Admin, with durable
  catalog data for visual swatches and no official storefront route cutover.

## Source baseline

The ZIP was extracted only to a temporary directory outside the repository and
built with the existing compatible dependency runtime on 2026-08-21. Its
`npm run build` completed with 2,358 transformed modules and only Vite's
source-native >500 KB chunk advisory.

Captured source evidence under `output/playwright/`:

- `task17-source-variant-matrix-desktop.png`
- `task17-source-variant-picker-color.png`
- `task17-source-variant-picker-image.png`
- `task17-source-delete-product.png`
- `task17-source-delete-product-mobile.png`

The actual source flows confirmed the Product list swatch preview, editor
display-mode selection, Auto-Detect control, matrix visual column, palette,
image gallery reuse, and destructive product confirmation. The source browser
reported one external resource console error; no source interaction failure was
observed. Its local option upload uses `FileReader` data URLs, which is retained
as visual behavior only and replaced at the adapter boundary by the existing
managed-media upload path.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/common/DeleteConfirmationModal.tsx` | `40dded9fe51adc9f0bf3d83b6d17ed5b75bb441248c51995f9e8f039efacf21c` | shared destructive-action dialog | new shared Admin component | selected |
| `src/components/catalog/VariantVisualPickerModal.tsx` | `b58d94be44f11e3ecf2ae761b9ea50e3e4a73c388446dffada02e15608151a7d` | visual swatch modal | catalog variant editor | selected |
| `src/components/catalog/ProductEditorModal.tsx` | `47490fc543bea70fab2fe4fce8237a284f7497976983269b19e39563a324db8b` | option mode and matrix UI | existing product editor | selected region only |
| `src/components/catalog/ProductAdmin.tsx` | `6df57f90acbb96f3986c001e377a93a9aa0fd6d8523c86d52c03c279286dfe3f` | product-list miniature visuals/deletion affordance | existing product admin | selected region only |
| `src/components/catalog/ProductMediaManager.tsx` | `b5482d62e18ce997bd1353b79aee00fb9e63a791d99b022c9fb56e233ef461ca` | picker dependency and media deletion state | existing product media manager | existing-compatible dependency |
| `src/types/index.ts` | `decf46fc20630bda7ab353bca6bc4cee4c0f25d46c92725968d96eb73a08014c` | ZIP mock types | adapter reference only | selected |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| Existing `/admin/api/catalog` API and `CatalogApiController` | product and variant persistence | adapter-needed | extend with validated contract |
| `products.options` and `product_variants` | durable visual configuration | backend contract gap | migration/serializer task |
| existing CMS media upload and `CatalogMediaReferenceSynchronizer` | option image upload/reuse/orphan tracking | existing-compatible | reuse |
| existing Admin toast/errors | API failure display | existing-compatible | reuse |
| Current customer/promotion/shipping deletion APIs | determine global-confirm coverage | audit needed | include only durable operations |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| destructive confirmation | delete trigger | dialog, impacts, cancel/escape/confirm | available adapter |
| palette and hex | visual-picker colour mode | one active colour; image cleared | backend contract gap |
| local option image | visual-picker image mode | upload then preview; colour cleared | available adapter after visual contract |
| gallery reuse | product image tile | durable existing media path selected | available adapter after visual contract |
| auto-detect/sync | matrix toolbar/modal checkbox | matching variants update locally before Save | backend contract gap |
| preset gallery | product media preset card | first-party/mock URL appended | mock retained |

## Approval record

- Initial source boundary approved by user on 2026-08-21.
- Dependency expansion approved by user on 2026-08-21: MySQL → API → Admin →
  storefront variant-visual lifecycle.
- Official route cutover approved: no.
