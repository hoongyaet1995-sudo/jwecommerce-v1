# Category / Collection responsive banners — local acceptance

Scope: shared original Theme image controls in full-page Category/Collection editors; desktop field retained, optional mobile URL, 9-point focus, alt text, hide toggle. Add nullable JSON image_settings to both tables. Managed mobile assets participate in existing media reference lifecycle. All 11 WebsiteDesignService themes use entity banner data; global All products media remains unchanged. Original layouts retained.

Verification ledger (2026-11-02, elapsed/token accounting unavailable unless tool measured):
- Local migration applied successfully; existing desktop URLs untouched.
- PHP syntax check passed.
- CatalogBannerMediaTest + CatalogTaxonomySlugTest: first run 1 failure (reserved example.com URL blocked by existing media policy); fixture changed to allowed public image host. Issue resolved, cumulative 1.
- All-theme render test first failed because adjacent Blade @else@include produced literal include text; diagnostic rerun added theme/URL assertion evidence, cumulative 2. Inserted directive line breaks; resolved. 6 tests / 132 assertions passed.
- After extracting shared CatalogBannerMedia service, expanded run included WebsiteDesignTest: banner/slug checks passed; 2 existing assertViewIs checks reported response is not a view. Actual HTML content assertions were not failing. Separate diagnostic issue cumulative 1; not declared baseline or passing. Investigate response wrapper rather than change production logic to satisfy view-object tests.
- Admin build passed with existing sample-font/image and chunk-size warnings. Final build after styling hide switch passed.
- Normal local Category 98 save/reload persisted mobile URL, right/top focus and alt; DB verified. Collection 55 save persisted center/middle focus and alt, mobile omitted to exercise desktop fallback. Original images and manual product relationships preserved.
- Browser current logistics theme: desktop and 390px mobile category image loaded; object-position right top and mobile source present; no horizontal overflow. Screenshots tmp/catalog-banner-controls.png, tmp/catalog-banner-desktop.png, tmp/catalog-banner-mobile.png.

No commit, push or deployment. All-theme automated output coverage is complete; visual browser evidence currently covers active logistics theme, not eleven manual theme audits.

Theme test diagnostic follow-up: adding HTTP 200 assertion confirmed both responses were successful. MarketingStorefrontTracking appends consent HTML using response->setContent, replacing Laravel response original View with a string. Isolated those two view-object tests from that unrelated middleware; production tracking is unchanged. Cumulative diagnostic failures 2. Next run checks theme assertions after this isolation change. Final Admin build and 3 Vitest page-editor checks passed.

Final: 10 PHP tests / 161 assertions passed, 3 Vitest checks passed, Admin build passed. Theme diagnostic issue resolved after middleware isolation; no production behavior changed for tracking. Collection normal save/reload and storefront desktop fallback verified, image loaded, alt retained, no overflow; screenshot tmp/collection-banner-controls.png and tmp/collection-banner-desktop.png.

## Compact banner card integration — 2026-11-02
- Replaced the shared Category/Collection control with approved Desktop/Mobile tabs, one responsive preview, upload/replace/remove actions, collapsible URL and focal grid, alt text and hide switch. Existing data contract, upload API and storefront renderers unchanged.
- First file-write command used a duplicated relative path and failed before modification; corrected canonical path. The concurrently started build therefore checked the previous control only. Final build after the actual edit passed (14.79 seconds); existing asset/chunk warnings unchanged.
- ProductPageEditor Vitest: 3 passed. No backend changes in this UI-only follow-up.
- Normal Category 98: tab switch, focus selection/Done, URL show/hide, save and refresh checked. Collection 55: Mobile fallback, focus show/hide and Light mode checked. Dark mode screenshot tmp/catalog-banner-integrated.png; Light mode tmp/collection-banner-integrated-light.png.
- Local only. No commit, push or deployment.
