# Home Editorial Product Grid — local phase

Approved: existing product_listing, reusable Editorial Grid, desktop four/mobile two, category/collection/manual sources, real product data, native wishlist. No publication or release authorized.
Baseline HEAD f76a9536066e136002f282c2ae3da6c3406c93a4; existing Hero/Shop Navigation and unrelated dirty work retained. Page 54 already contains a Product Listing; preserve its user settings when applying layout.

## Issue ledger
Unexpected failures: 0 at phase start. Historical unrelated failures remain in their own phase ledger. Elapsed/token accounting unavailable.

## Runs
- Before run: intentional initial TDD red, PageDocumentContractTest editorial manual round-trip. Purpose: prove new preset/source/action fields require canonical ownership before implementation.

- Initial red confirmed: unsupported preset/source/action ownership (expected). Added schema, conditional publication rule, server/Admin manual feed and native wishlist presentation. Next: focused PHP contract/runtime checks.

- PHP contract/runtime: 44 tests, 396 assertions passed. Frontend implemented, before focused rendering/bootstrap/Inspector tests and build. One scripting decode interruption in AddSectionModal resolved with explicit UTF-8; no tests rerun blindly.

- Frontend 12 tests passed; first build passed (13.62s). Readback found script stopped before Inspector integration, now completed in UTF-8. Before regression/build rerun: actual shared Inspector wiring changed, optional wishlist bootstrap validation added. This is new evidence, not an unchanged retry.

- Unexpected failure count 1: global rendered-role fixture omitted product runtime facts, unlike payment/delivery fixtures. Added a catalog product to the fixture; before targeted regression run. Product rendering was already verified separately.

- Frontend 12 tests passed after realistic catalog fixture; issue resolved. Build passed 13.43s. Existing customer wishlist POST/DELETE tests passed (13 assertions). Before additional scope test: verify new presentation excludes preview personal/session data.

- Actual page now contained two sections, not the earlier three-section snapshot. Preserved current user-edited Hero and navigation, inserted one Editorial Product Grid through Add Section. Selected existing catalog table/coffee table/lamp/box; switched layouts, ratio and category, returned handpicked, saved through Exit to Pages. Draft preview confirms order/prices. Desktop 4 x273px; mobile 2 x156px at390px, no horizontal overflow. PDP and guest login navigation verified. Wishlist mutation remains disabled in preview. Public authenticated native flow covered by database tests, not live customer browser.
- Before final affected regression: preserve all 27 Inspector group hooks; avoid advertising product-base comparison when selected variant has no compare-at price. Run shared Inspector and legacy ProductListing backend checks.

- Shared frontend regression 17 tests passed; legacy ProductListing backend 4 tests/30 assertions passed. Inspection found offered carousel intervals must remain [3,3.5,5,7]; corrected before any invalid value was saved, added schema-bound interval test and variant discount regression. Before final focused tests/build after these changes.

- New frontend 19 tests passed. Review before closure: Editorial Grid requires wishlist presentation but no quick-add modal/query; scoped purchase assets to legacy carousel. Added HTTP public-route/manual-publish test and bootstrap ownership guard. Before final PHP/runtime/frontend checks. No real page publication was performed.

- Public manual HTTP publish/render and wishlist scope regression: 7 PHP tests/56 assertions passed. Runtime bootstrap+grid 40 frontend tests passed. Added ownership/unsafe login bootstrap coverage before final frontend run/build, since the optional account context is new.

- Bootstrap ownership/login test passed (35 runtime +6 grid tests). Final publication requirement explicitly substitutes productIds for sourceId in manual feeds, including an empty-selection test even with supplied positive runtime facts. Before final contract/runtime regression.

- Distinct issue: generic required-value helper accepts empty arrays. New strengthened manual-publish test failed once while 50 other tests passed; this issue cumulative failure count 1 (fixture issue previously resolved separately). Added only a Product Listing empty-selection guard, preserving other section publication behavior. Before rerun after this concrete code change.

- Final backend regression 51 tests/453 assertions passed; empty-selection issue resolved. Build passed 12.46s. Saved section/preset persisted after reopening actual Editor. Before final focused contract check: map manual runtime errors to productIds, the visible Inspector setting, instead of hidden taxonomy sourceId.

## Local outcome
Complete locally: Product Listing Editorial Grid registered in Add Section, reusable shared renderer; category/collection/manual feed, ordered ID selection, real catalog photos/prices, optional real comparison prices, native customer wishlist. Content/Design/Advanced use bounded controls and visual layout/columns/ratio cards. Existing carousel capabilities retained, static grid has no unused carousel/quick-add runtime. Shared page container and subtle-lift preserved.
Page 54 draft now has Hero + Shop Navigation + Product Listing; user-edited first two sections untouched. Four selected catalog products persist after Exit to Pages and reopen. Page status remains user-selected published; Publish Changes was not clicked, so current acceptance is the authenticated draft customer preview, not a newly published storefront snapshot.
Evidence: output/home-editorial-products/section.jpg, mobile.jpg, desktop-full.jpg, inspector.jpg. Desktop 1536px four273px tracks; mobile390px two156px tracks and no horizontal overflow. All four photos loaded; product navigation and customer login route browser verified. Preview wishlist disabled intentionally; authenticated wishlist storage/removal and contextual isolation validated in PHP tests.
Final checks: affected PHP aggregate51 tests passed (453 assertions before final mapping assertion); final focused contract28 tests/175 assertions passed. Frontend54 unique affected tests passed across runs (runtime35, grid6, unifiedInspector5, sharedRendering3, bootstrap5). Final Vite build passed12.46s with existing chunk-size advisory. Repo-wide type check was not rerun; previous phase's pre-existing unrelated type failures are not claimed resolved. Scoped diff check has no whitespace errors. No commit/push/deploy or real-page publication. No unresolved issue in this phase; fixture omission and empty-array required-value issues each failed once and were resolved. Time/token total unavailable.
