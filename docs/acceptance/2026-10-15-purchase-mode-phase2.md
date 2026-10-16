# Purchase modes phase 2 — shared storefront contract

Authorized: shared business rules plus theme-owned presentation for Default, Home & Deco, Beauty, Food, Tech, Baby, Jewelry, Fashion and Universal. Preserve dirty phase1/inventory/social sources and approved layouts. Baseline HEAD4241c21; phase1 local changes remain uncommitted.

Rules: buy retains existing active/enabled/verified-stock requirements; quote uses existing exact/from/hidden price and replaces purchasing with enquiry presentation; display has no price or transaction controls. Non-buy never enters Quick Buy. Related, saved and CMS product cards must use the same rules. Hidden prices must not survive in public variant JSON. No fake enquiry success or booking availability. Phase3 owns enquiry submission; keep phase1 draft-only publication gate for both new modes until full release readiness is established. Protected unsaved theme demos may exercise modes without altering live catalog.

Backend: guard cart add/update; prune non-buy/inactive stale cart entries with visible feedback; guard checkout against fresh locked product state before creating orders or allocating stock. Retain unknown-stock and permissions rules. Tests are guarded SQLite memory only. Existing real local phase1 Draft remains untouched for front-end work.

Verification matrix: 9 themes x buy/quote-exact/quote-from/quote-hidden/display on PLP/PDP; shared Quick Buy gate; related/saved/CMS cards; forged add/update; stale cart; direct checkout; raw hidden-price JSON; desktop/mobile visual samples for each theme. Automated rendering is not visual acceptance. Record each result and unresolved gap explicitly.

Issue ledger: initial planned TDD red for cart accepting a quote variant and missing shared rendering; expected red separate from unexpected failures. No unexpected failures yet. Elapsed/token accounting unavailable. No sync/deployment authorized.

2026-10-15: planned initial red confirmed (PATCH missing variant_id guard); after shared guards four backend tests passed, 20 assertions, 2.82s. Full renderer test identified a test selector false positive: admin demo JavaScript contains the trigger string. Tightened to actual button markup; this is test diagnosis, no storefront failure observed. Unexpected test-selector failures: 1, resolved.

Verification checkpoint: PHP 5 tests/138 assertions passed (4.31s), all nine quote-hidden/display PLP/PDP renders. Front build passed 1832 modules/40.89s, app-DpMnZGUY.js. Next run expands exact/from/buy and shared modal gate, plus existing QuickAdd/variant/CMS/account/checkout regressions. Admin build required by CMS consumer changes; no catalog writes during phase2.

Regression batch: 39 passed, 12 failed in 20.64s. Diagnostic evidence: existing QuickAdd tests expect the old default modal while HEAD already defaults to Universal; managed-inventory tests expect legacy availability but current-stock-only configuration is active. Capturing JUnit on a diagnostic run with expanded mode tests to classify all failures (no blind retry). Do not alter inventory behavior to satisfy stale fixtures. Front/Admin builds both passed (Admin3151 modules/13.02s).

Diagnostic run: 40 passed/12 failed,17.71s; JUnit output/purchase-mode-phase2/regression.xml. New CMS issue (2 occurrences): explicit SQL projection omitted purchase fields, causing existing buy prices to redact. Added both columns to the bounded projection. Legacy inventory failures observed twice; modal text mismatch twice. Further legacy runs will explicitly use legacy configuration; no app configuration changed.

Latest focused result: 26 passed/466 assertions/8.29s (all five configurations across 9 theme PLP/PDP plus backend, account projection, existing variant/CMS coverage). Vitest49 passed/3.45s after updating explicit public-field allowlist expectation for new purchase metadata; schema expectation failure1 resolved. Legacy-config checkout diagnostic18 passed/1 failed; underlying WebsiteInventoryAvailability returns null for that managed fixture before purchase-mode rules (existing inventory path unchanged). Cumulative managed-fixture failures3: Attention, stop rerunning that fixture during this phase; remains outside purchase-mode closure. Two existing modal-copy assertions remain incompatible with HEAD Universal defaults.

## Local verification result

Final focused PHP command: `php artisan test --compact --filter='ProductPurchaseFrontendTest|ProductPurchaseModeTest|ProductListingTest|CustomerAccountOperationsTest|StorefrontVariantVisualTest|WebsiteThemeDemoTest' --log-junit output/purchase-mode-phase2/final.xml`: **31 passed, 699 assertions, 12.63s**. This includes all existing protected demo scenarios plus 9 themes x 5 configurations x PLP/PDP.

Vitest command: `npx vitest run src/cms/purchasePresentation.test.ts src/cms/__tests__/productListing.test.tsx src/components/pages/EditorialProductGrid.test.tsx src/cms/__tests__/PageRuntimeContext.test.tsx --reporter=dot`: **49 passed, 3.45s**. Full project typecheck was not run. Both Vite builds passed; Admin emits the existing large-chunk advisory.

Browser: Chrome at390x844 and desktop1536 viewport. Each theme's quote/from PDP inspected on desktop; each quote/hidden PDP and display-only PLP checked on mobile. Mobile document widths382px at390px viewport (no horizontal overflow). Hidden PDP variant prices/compareAt/stock all null. Each buy-mode mobile Quick Buy opened and exposed its real confirmation control; no order/payment was submitted. Saved screenshot frames captured immediately after opening can precede the browser's paint; modal visibility is also recorded in the DOM matrix, and selected modal screenshots are recaptured after paint. No claim of pixel-by-pixel visual diff against a source ZIP.

| Theme | PLP/PDP 5-mode server matrix | Desktop quote | Mobile hidden quote/display PLP | Buy Quick Buy open |
|---|---|---|---|---|
| Default | Pass | Pass | Pass | Pass |
| Home & Deco | Pass | Pass | Pass | Pass |
| Beauty | Pass | Pass | Pass | Pass |
| Food | Pass | Pass | Pass | Pass |
| Tech | Pass | Pass | Pass | Pass |
| Baby | Pass | Pass | Pass | Pass |
| Jewelry | Pass | Pass | Pass | Pass |
| Fashion | Pass | Pass; quote prefix width adjusted | Pass | Pass |
| Universal | Pass | Pass | Pass | Pass |

Read-only local MySQL check: original26 buy products remain; Phase1 acceptance Draft remains quote/from,19900minor units,4 variants. `output/purchase-mode-phase2/catalog-readonly-evidence.json` matches Phase1 evidence. No Phase2 catalog record edits.

## Remaining boundaries and regression exceptions

- Real enquiry submission, persistence, notifications and CRM follow-up remain Phase3. Quote button deliberately disabled with an honest unavailable message in Admin previews. Both non-buy modes remain Draft-only; not advertised as published or operational.
- Legacy checkout inventory fixture: three observed failures (Attention). It expects18 units after provisioning physical20/safety2 but leaves reserved stock uninitialized; current availability returns null. Test also asserts cart quantity3 after adding2. No inventory code or this concurrently dirty test changed; automatic reruns stopped for this fixture.
- Two QuickAdd assertions use obsolete copy: default expects old specification link but HEAD defaults the shared component to Universal; Home & Deco expects `Product details` while current source says `View full details`. No approved UI rewritten to satisfy old wording.
- Initial selector false-positive1 and bounded CMS projection missing purchase columns2 are resolved. Runtime key allowlist expectation1 updated for the added public metadata. Historical failure accounting before this phase is unavailable; it has not been reset.
- Local only, no commit/push/deployment. A release still needs coherent dirty-source isolation, approval, and exact-SHA verification. Do not call the entire legacy regression suite green.

## Review links (authenticated Admin, unsaved fixtures)

- [Universal quote/from PDP](http://127.0.0.1:8500/admin/website-design/preview?theme=universal&surface=pdp&purchaseMode=quote&priceDisplay=from)
- [Universal hidden-price quote PDP](http://127.0.0.1:8500/admin/website-design/preview?theme=universal&surface=pdp&purchaseMode=quote&priceDisplay=hide)
- [Universal display-only PLP](http://127.0.0.1:8500/admin/website-design/preview?theme=universal&surface=plp&purchaseMode=display&priceDisplay=hide)
- Replace theme with default/home_deco/beauty/food/tech/baby/jewelry/fashion to review the same shared contract in that presentation. These parameters do not override purchase modes on public routes.

Browser final: real localhost `/products/food-design-sample-olive-oil?variant=159` retains Add to cart and no enquiry entry. Browser error log empty for acceptance tab. Fashion quote price now fits one line; buy-mode CSS unchanged. Phase2 source hashes recorded in `output/purchase-mode-phase2/source-hashes.json`. Test timestamps below are from JUnit, not reconstructed wall-clock estimates.

| Run | Recorded timestamp | Tests/assertions | Purpose |
|---|---|---|---|
| regression | unavailable | See regression.xml | Initial broader regression diagnosis |
| focused | unavailable | See focused.xml | Purchase/CMS/variant/account after projection correction |
| legacy-checkout | unavailable | See legacy-checkout.xml | Explicit legacy inventory configuration diagnostic |
| final | unavailable | See final.xml | Final purchase/theme-demo regression |
