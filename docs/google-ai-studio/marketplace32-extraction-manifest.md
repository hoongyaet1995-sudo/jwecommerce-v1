# Marketplace ZIP 32 source extraction manifest

## Boundary

- Archive: `C:/Users/User/Downloads/jw-ecommerce-dashboard (32).zip`
- SHA256: `9285DB80782F060DCF20F854EBACEEB44C96ACB49642A8000F33D31D181BE6D2`
- Private extracted baseline: `C:/Users/User/.codex/tmp/jw-marketplace32-source-9285db80`
- Target: canonical JW Ecommerce; isolated Marketplace preview (route pending baseline).
- Selected: mapping workspace and import review, single-variant creation/linking, publish wizard, AI suggestion confirmation.
- Excluded: CMS motion changes, storefront, other Admin modules.
- User supplied ZIP 32 as the requested design completion. No ZIP 32 official-route cutover yet.

## Selected components

All source paths below are under `src/components/marketplace/`.

| Component | SHA256 |
| --- | --- |
| ImportProductReviewModal.tsx | 1BD5D270921343ECE43983685665D6EA982128DF49AEF8C11A7621CDF7E2614D |
| ProductMappingModal.tsx | 673E58801DB8B1F0397CF11391A9491EC8CA56941CBA0E8743019319C6956C6C |
| PublishProductModal.tsx | 66CCE53C75482131AE408F3853A4F337CE2782FE909D1D96AD3FC805E650326A |
| ConfirmSuggestionsModal.tsx | 80C66786AAD63D2BDA79499C6554E515531F918C33CFF01D3E0EDB5DBA065EB0 |
| MarketplaceMappingPage.tsx | 3612636F514E01E247E7D4EB32B5A9618F4231AB2029D399A34249BE2DE183B0 |

Direct dependencies: React, lucide-react, MarketplaceLogo, common SubNavHeader, source types and selected AdminContext values/actions. Do not import the full AdminContext into production or copy raw ZIP wholesale.

## Data boundaries

| Journey | Existing adapter | Gap |
| --- | --- | --- |
| Whole import | Saved catalog to draft with zero stock | Reviewed SKU overrides, per-variant existing links and batch per-item results |
| Single variant | Existing product or explicit new draft endpoint | Connect reviewed source drawer; validate SKU overrides/metadata contract |
| Publishing | Local category/attribute/brand readers; logistics client | Real async creation, channel validation/media, uncertain-result reconciliation, provider identity mapping |
| AI confirmation | Manual mapping conflict validation | Shared AI Engine, suggestion generation and verified batch acceptance |

## Source behavior requiring real adapter replacement

- Import executes context calls without awaiting and immediately reports complete.
- Missing SKU fallback uses random numbers during useMemo analysis.
- Publish uses timer transitions and random job/external IDs; uncertainty copy promises automatic retry.
- Category/brand/package values are hardcoded defaults; initial stock defaults to warehouse_stock; ongoing sync defaults false.
- Source considers Shopee/Lazada operational by platform name; real capability must come from backend.
- Existing-listing detection depends on mapped variants only, which cannot prove an external item does not exist.

Preserve source visuals in isolated baseline; do not expose simulated success as real behavior. Missing contracts remain mock-only in source preview and unavailable in operational adapters until implemented. Never blindly retry an uncertain external creation.

## Evidence pending

Source run and desktop/mobile screenshots; source interaction baseline; isolated Laravel extraction; adapter tests; desktop/mobile comparison; user cutover acceptance. No visual-parity or operational-completion claim yet.

## Source browser checkpoint

- Private Vite source server started on http://127.0.0.1:8532 using bundled Node; no Laravel routes replaced.
- Chrome source navigation: Marketplace Hub -> Product & SKU Mapping -> Import Product to JW Master opens the new review modal.
- Captured in-conversation desktop screenshot of original import review. Dark-mode product header/table has pale background with low-contrast light text; source issue, not an adapter regression. Needs separate visual decision before parity can be accepted as quality-complete.
- Link Variant -> Create Master SKU & Link opens the embedded destination drawer. Default explicitly selects new draft; existing-parent radio and editable SKU are present. Zero physical stock and channel-reference stock are separately displayed. No create/confirm action was submitted during this checkpoint.
- Existing mapping footer still promises orders will recognize and deduct stock; operational copy must reflect the actual enabled inventory pipeline, not imply unfinished stock handling works.
- Mobile screenshots, remaining wizard/AI states and converted preview still pending.

## Continued source interaction baseline

- Added localhost-only `journeyPreview=32` selection on the existing protected mapping route. Normal mapping rendering uses the original modal; selected preview injects ConnectedVariantModal with server catalog and admin product values. No production hostname activation or navigation cutover. Local Admin build passed (2861 modules, existing large-chunk warning).
- Authenticated Chrome visit to `http://127.0.0.1:8500/admin/marketplace/mapping?journeyPreview=32`: load completed, 10 local JW catalog products, 0 connected stores, no matching external products, import capability enabled. No API error was visible. Thus the mounted real-data entry exists, but real-catalog modal/write browser acceptance is blocked by absent local store/catalog data; source fixtures were not inserted as operational records.
- ConnectedVariantModal creates using the saved listing id, checks selected product membership, and delegates mapping/unlink to the existing protected mapping endpoint. React component tests cover delayed create success and selected-id unlink; remote platform calls are not performed by these tests. Source mapping save/unlink now await and catch failures; these are intentional interaction-adapter deltas, not visual redesign.

- Post-baseline adapter delta: ProductMappingModal single-variant creation now awaits its context operation, disables repeated confirmation while pending, closes only on resolved completion, and emits an error toast on rejection. JSX hierarchy/classes are unchanged; its extracted file hash now intentionally differs from the original listed hash. Other source modal simulated behavior remains unadapted. React DOM test first caught premature close, then passed with rejected promise retaining the drawer. Focused frontend run: 12 passed. Preview host still provides simulation callbacks, not real API integration.

- Independent Vite visual harness now runs at `http://127.0.0.1:8534/marketplace32.html`; build output stays in `tmp/marketplace32-build`. This is not a Laravel route or an operational integration. The four source dialogs retain original hashes and consume an explicit context boundary; harness callbacks record simulated intent only and do not persist sample actions.
- Only selected source product/category/connection/listing fixture declarations and source CSS are included. Shared compatible ZIP30 preview types are type-only dependencies, not its runtime context or fixtures.
- Vite build passed (1679 modules). Isolated TypeScript check of marketplace32/main.tsx passed after resolving preview-only type dependencies. Official entry was not changed.
- Converted mobile capture `output/playwright/marketplace32-converted-import-mobile.png` visually compared with original at 390x844: selected modal dimensions, text blocks, cards and sticky footer align; backdrop differs because harness replaces the out-of-scope Admin background. This is limited visual evidence, not full interaction parity or business acceptance.

- Began isolated extraction under `resources/admin/src/previews/marketplace32/components/marketplace/`: ImportProductReviewModal and its MarketplaceLogo dependency. Import modal hash exactly matches ZIP32 (`1BD5D270921343ECE43983685665D6EA982128DF49AEF8C11A7621CDF7E2614D`); no JSX/classes/behavior edits. Logo hash `3C963ED3E12747B6BCB7FAEACED916358C39B5D441C1A5EC8CB15A3587C5EF98`.
- These files are not mounted or imported into the official build. Narrow context/types and preview host remain to be added; no runnable converted preview or build-success claim yet. Source's immediate simulated success is retained only in this unmounted baseline, not operational code.

- Saved original-source Import Review viewport screenshots: `output/playwright/marketplace32-source-import-mobile.png` (390x844) and `output/playwright/marketplace32-source-import-desktop.png` (1440x900). Mobile image visually inspected: modal fits viewport, safety/default metrics readable, sticky Cancel/Confirm footer visible; product rows require internal scrolling. This is not yet proof of every mobile interaction. Only observed console error was missing favicon.ico (404).

- Chrome original ZIP32: selected Nexus master product, advanced Destination -> Attributes -> Pricing & Sync. No Publish action submitted.
- Attributes currently declare all required fields verified merely from source defaults; real integration must replace that assertion with platform validation. Category choices and warranty remain fixed source examples.
- Pricing step defaults to +5% and current physical warehouse stock (112 fixture units); ongoing sync checkbox is unchecked. Source screenshot captured in conversation. Operational implementation must use explicit reviewed allocation and calculated available stock, not copy physical stock blindly.
- Closed wizard and opened Accept AI Suggestions: 10 fixture candidates, zero selected by default, Confirm 0 Mappings disabled. Candidate explanations and percentage scores are fixtures, not evaluated model confidence. No mapping confirmed.
- This completes desktop navigation evidence for the non-submitting wizard and AI entry state only; mobile baseline, success/error states, extraction and converted browser comparisons remain outstanding.
