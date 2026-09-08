# Official Mapping local integration

- `/admin/marketplace/mapping` now mounts the ZIP 30 page via `OperationalMapping`, bypassing the legacy unpaginated Marketplace provider.
- Catalog query loader supplies server pagination and real mapping metadata; PUT/DELETE mapping calls retain backend validation. Server conflict checks are supplied to the source modal.
- Import, publishing and AI acceptance remain disabled. The publish candidate count is unknown, not inferred from one page. Page-only platform counts are omitted.
- Fetch requires one selected connected, catalog-enabled store; a queued response is explicitly reported as queued, not completed. Task progress UI remains pending.
- Build, TypeScript and seven targeted tests passed. Build retains the existing large-chunk warning.
- Real local browser reached the new page; publishing disabled. Catalog summary/products returned HTTP 503, visibly reported as an error. Catalog business acceptance is NOT complete. Investigate local read gate before further browser validation.
- Screenshot: `output/playwright/marketplace-30/mapping-official-local.png`. No deployment or real Marketplace writes in this checkpoint.

## Follow-up local browser verification

- Root cause of 503: local `.env` omitted the read flag, defaulting false. Set local `MARKETPLACE_READ_OPERATIONS_ENABLED=true` and explicitly retained `MARKETPLACE_OPERATIONS_ENABLED=false`; cleared configuration cache. No credentials were changed.
- Runtime confirmed sandbox, zero stores/catalog/orders/jobs, ten JW products. Real empty-state browser check now returns no alerts, with publishing disabled.
- Browser-only intercepted catalog fixture with total 320 verified grouped page 2 and flat endpoint selection. Rendered footer is `Page 2 of 22`, requests use page=2/per_page=15. Initial text-locator timeout was a locator issue; body text and screenshot confirm the footer. Routes removed and page reloaded after the fixture.
- Fixture screenshot: `output/playwright/marketplace-30/mapping-page-two-fixture.png`. Fixture is not real catalog/scale/performance acceptance and writes nothing to DB.
- Follow-ups visible from screenshot: disabled controls retain bright source styling; absent observed images need safe source-compatible handling. Global Admin showed zero loaded Products while DB contains ten: verify root catalog lazy-loading before accepting the JW selector. No claim of complete mapping workflow yet.

## JW selector follow-up

- Catalog API returns ten products; after clearing browser routes and reloading, the sidebar and mapping modal both show ten. No lazy-loading bug was established; no root catalog changes made.
- Real JW catalog plus intercepted external fixture opened the ZIP modal at desktop and 390px. Screenshots: `mapping-jw-selector-fixture.png` and `mapping-jw-selector-mobile-fixture.png` in the same output folder. No mapping saved against the artificial listing.
- Found actual DTO mismatch: root Product images are `(ProductMedia|string)[]`, source modal expects `string[]`; objects produced `[object Object]` requests. OperationalMapping now maps media objects to their `path` at the presentation boundary. TypeScript/build verification performed; browser recheck of corrected images remains next.

## Browser save boundary and regression

- Rechecked modal image sources: no object-string URLs. The fixture selects an actual JW product/variant, intercepts PUT twice (409 then 200), confirms failure keeps the dialog open and retry success closes it. Both requests were intercepted; this is UI persistence-boundary evidence, not real DB mapping acceptance.
- Full Marketplace/Shopee regression initially exposed four tests inheriting local read=true. Added forced false defaults for both Marketplace gates in phpunit.xml; feature tests explicitly enable capabilities where needed. Rerun: 78 passed, 553 assertions.
- Post-test runtime remains local sandbox/read=true/write=false, no stores/listings/orders/jobs, ten products and unchanged local stock fingerprint. No deployment performed.
