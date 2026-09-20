# Marketplace Dynamic Attributes Acceptance Evidence

## Scope and source fidelity

- Canonical local runtime: `C:\xampp\htdocs\jwstudio-ecommerce`
- Acceptance route: `http://127.0.0.1:8500/admin/marketplace/mapping`
- Implementation commit: `8b93f8b`
- Google AI Studio archive: `C:\Users\User\Downloads\jw-ecommerce-dashboard (38).zip`
- Archive SHA-256: `2DD039D2C66A725F2E02E2849AE4141A5846EC5403895D2193F79E5E872EC884`
- Exact source component hashes and adapter boundaries: `docs/fidelity/2026-09-20-marketplace-size-chart-extraction-manifest.md`
- Selected source regions: `PublishProductModal` wizard shell and field styling, `OptionalAttributesSection` accordion/chips/search/unit controls, `ProductMediaManager` upload and preview treatment, and the source radio-card pattern.
- Excluded source regions: UI States Spec, interactive state testers, fabricated demo schemas, mock publishing actions, and unrelated Marketplace pages.

The production UI renders current Shopee or Lazada category metadata through one canonical control layer. The Google AI Studio DOM/classes remain the presentation source; Laravel and React adapters own provider data, validation, uploads, and submission.

## Implemented control coverage

| Provider metadata | Rendered review control | Saved provider value |
| --- | --- | --- |
| text / rich text | text input or textarea | bounded string |
| numeric with optional unit | native numeric input plus unit selector | finite number and verified unit |
| date | native date input | validated provider date |
| single enum / radio-compatible enum | searchable select or source radio cards | one verified option |
| multiple enum | searchable multi-select and chips | unique verified options within provider maximum |
| searchable provider values | async searchable select with retained selection | verified provider value IDs/names |
| image, including Lazada Size Chart and SKU thumbnails | owned JW image selection, upload, preview, replace, remove | internal image ID at review, provider image identity before write |
| unsupported mandatory metadata | disabled explanatory card | submission blocked |

Required attributes render before the ZIP-derived Optional Attributes section. Optional fields retain the filled counter and expand/collapse behavior. Store, category, product revision, variant SKU, and control-kind changes clear incompatible values and ignore late metadata responses.

## Automated verification

### Focused frontend

Command:

```text
npm test -- --run src/marketplace/publishingAttributes src/marketplace/MarketplaceAttributeControl.test.tsx src/marketplace/MarketplaceAttributeSections.test.tsx src/marketplace/MarketplaceImageAttribute.test.tsx src/previews/marketplace32/PublishProductModal.test.tsx src/integration/submitLazadaReview.test.ts src/integration/submitShopeeReview.test.ts
```

Result: **8 files, 30 tests passed**.

### Focused backend

Command:

```text
C:\xampp\php\php.exe artisan test tests/Feature/MarketplaceAttributeImageUploadTest.php tests/Feature/LazadaReviewedAttributesTest.php tests/Feature/LazadaProductImageServiceTest.php tests/Feature/LazadaListingPreflightTest.php tests/Feature/ShopeePublishingVariantsTest.php tests/Feature/ShopeeProductImageServiceTest.php tests/Feature/MarketplacePublishingTaskTest.php
```

Result: **37 tests, 221 assertions passed**.

The boundary tests cover MIME/size/image-integrity validation, owned media, numeric/date/enum/multi cardinality, required fields, SKU identity, schema drift, stale product revision, Lazada current-schema reload, Size Chart binding, Shopee provider preflight, idempotency, uncertain results, and provider requests without unresolved top-level `jw_*` fields.

### Build and broader baseline

- `npm run build`: **passed**, 3,009 modules transformed. Vite reported the existing large-chunk warning.
- `npm test -- --run`: **141 files / 583 tests passed; 3 unrelated existing failures** in `ImportProductReviewModal`, `MarketplaceMappingPage`, and the source component inventory count.
- `npm run lint`: still blocked by the repository's existing cross-module TypeScript baseline in AI Auto Reply, Shipping, old Marketplace previews, CMS motion, and `OperationalMapping`. No new canonical attribute-control file appeared in the diagnostics.

## Browser evidence

Authenticated localhost checks were performed at 1536 x 960 and 390 x 844 without submitting a listing.

- `output/playwright/marketplace-dynamic-attributes/desktop-publishing-shell.png`
- `output/playwright/marketplace-dynamic-attributes/mobile-publishing-shell.png`

The ZIP-derived three-step modal shell, responsive single-column mobile layout, scroll region, and sticky action footer render on the official Mapping route. The browser flow stopped before any provider write.

Live category-by-category browser inspection could not be completed in this local runtime because the copied Lazada official-test lease expired on 2026-09-19 and the local Shopee store has no active access token. The same control paths are covered with provider-shaped fixtures in the focused tests above. Renewing each local test-store credential will allow staff to repeat jewellery, fashion, date/enum, searchable Shopee, and rapid category-switch checks without another build.

## Release boundary

No marketplace product-create, inventory-write, GitHub push, or Hostinger deployment was performed. Localhost remains the development truth until explicit synchronization approval.
