# Source Extraction Manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (38).zip`
- ZIP SHA-256: `2DD039D2C66A725F2E02E2849AE4141A5846EC5403895D2193F79E5E872EC884`
- Target Laravel project and route: `C:\xampp\htdocs\jwstudio-ecommerce`, `/admin/marketplace/mapping`
- Requested page/region/function: Push JW Master Product to Marketplace, Category & Attributes step, size-chart selection and publishing
- Explicitly excluded ZIP regions: developer state testers, UI States Spec, mock publishing controls, unrelated Marketplace pages
- Acceptance intent: staff can choose an existing JW product image as a marketplace size chart and publish it through the real Shopee or Lazada server-side adapter.

## Source baseline

- Source startup/build command: `bun install && bun run dev`
- Desktop viewport and baseline path: 1536 x 768, Marketplace Product & SKU Mapping > Push JW Master Product to Marketplace
- Mobile viewport and baseline path: 390 x 844, same modal flow
- Known source errors or inert controls: ZIP publishing is mock state; provider calls remain Laravel-owned.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/marketplace/PublishProductModal.tsx` | `21BC3027910AFFDF43B01264F98747DD3300487707E0E5690A05EA0C64ED9BCC` | modal structure and Category & Attributes placement | `resources/admin/src/previews/marketplace32/components/marketplace/PublishProductModal.tsx` | selected |
| `src/components/marketplace/publish/ShopeeVariationImagesSection.tsx` | `125DC8DDD834E71658F4FE9E7D12998DB61B498A5C817684B528E51BE52EF1FD` | image candidate cards, selected state, preview behavior | `resources/admin/src/marketplace/PublishingSizeChart.tsx` | selected |
| `src/components/marketplace/publish/OptionalAttributesSection.tsx` | `29BD75E91DE5F8ABC19E4F69B12E1BA834AD5720A21F72158548A3ECF3DA49D6` | collapsible optional header, filled count, searchable selects, custom values, unit selectors, and chips | `resources/admin/src/marketplace/MarketplaceAttributeSections.tsx` and `publish37/OptionalAttributesSection.tsx` | selected |
| `src/components/catalog/ProductMediaManager.tsx` | `B5482D62E18CE997BD1353B79AEE00FB9E63A791D99B022C9FB56E233EF461CA` | upload, thumbnail, preview, replacement, and error presentation | `resources/admin/src/marketplace/MarketplaceImageAttribute.tsx` | selected for Task 4 |
| `src/components/marketplace/PublishProductModal.tsx` radio cards at the Initial Listing Stock Mode region | `21BC3027910AFFDF43B01264F98747DD3300487707E0E5690A05EA0C64ED9BCC` | binary single-choice card pattern | `resources/admin/src/marketplace/MarketplaceAttributeControl.tsx` | selected |
| `src/index.css` | `4E9840B9AA92BF9FCFEE5B715D2943580BAA4334FF6B29C7C0D93F942AF2C75D` | existing Tailwind visual system | existing Admin React build | existing-compatible |

The extraction excludes `UI States Spec`, interactive state testers, fabricated demo schemas, and developer diagnostics. Provider metadata and current JW product media remain Laravel-owned adapters; only the selected presentation and interaction regions are transferred.

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| JW publishing source images | only owned, saved images may be selected | direct | available adapter |
| marketplace image receipts | upload/migrate selected asset server-side | direct | available adapter |
| Shopee `size_chart` payload | bind uploaded Shopee image identity | direct | adapter-needed |
| Lazada size-chart template list | show seller-owned templates instead of a raw numeric field | direct | adapter-needed |
| Lazada batch size-chart update | attach template ID or Lazada image URL after item creation | direct | adapter-needed |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| Select size-chart image | Category & Attributes step | source-faithful image cards with preview and selected state | available adapter |
| Select Lazada template | Lazada category exposes Size Chart | verified seller template dropdown | available adapter |
| No size chart | optional category requirement | explicit None selection; no provider field sent | available adapter |
| Required size chart missing | provider schema requires it | Next action disabled with actionable validation | available adapter |

## Approval record

- Initial source boundary approved by: user, 2026-09-20 ("UI/UX can use the Google AI Studio ZIP design")
- Dependency expansion approved by: same request; backend adapters are required for operational acceptance
- Official route cutover approved by: same request for the existing Push JW Master Product flow
