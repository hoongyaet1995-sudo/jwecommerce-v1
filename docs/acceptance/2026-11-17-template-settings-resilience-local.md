# Template settings failure/retry and database-independent error views — local acceptance

Scope: canonical C:/xampp/htdocs/jwstudio-ecommerce only. No commit, push, deployment, database connection config, session config, cache clearing or production actions. Existing unrelated dirty work retained. App-code baseline5 files and scoped delta stored under output/template-settings-resilience-20261117/before and review.patch.

## Behavior
- Existing PageTemplateModal layout retained. Loading/error banner now names Shop profile / Commerce settings and distinguishes HTTP errors, expired session/access, network and unexpected responses. No raw response body, credentials or SQL appears.
- Manual Retry uses the same persisted GET readers; both must succeed before nonblank templates can be used. Blank Page stays available. No automatic retries or settings writes. Loading disables Retry, ref guard prevents concurrent duplicate attempts. Request generation discards late results after close/unmount; blank creation also releases the in-flight guard. Search/category/selection component state remains during Retry.
- Existing settings save/upload functions unchanged byte-for-byte. No substitute settings or demo defaults used for failed loads.
- Global brand composer skips Laravel errors::/errors. and debug exception renderer views, including their error layouts. Normal views still resolve existing saved branding. Standard error page HTML/layout/status remain framework defaults; no redesign or DB fallback settings.

## Verification
- Declared initial TDD reds confirmed missing Retry/message and8 unwanted brand calls across404/419/500/503 before implementation; retained separately from unexpected failures.
-12 frontend tests passed: actual mounted modal gating/Retry/loading, persisted-source503 fail then fresh read recovery,401/403/419/503 safe errors, network/malformed responses, currencies regression.
-4 PHP tests /19 assertions: framework404/419/500/503 render without brand lookups; PDO failure500 remains static/no exception leakage; real HTTP route failure rendered; normal view still receives saved name.
-TypeScript and final client/SSR builds passed. Existing optional source-assets/chunk-size warnings unchanged.
-Authenticated real local Admin: CDP intercepted only localhost Fetch GET Shop profile503; failure source/status shown, templates blocked, one paused Retry read with disabled button; cleared fault and real reads restored selection, search retained. Commerce settings ConnectionFailed on390px likewise showed network error and recovered via Retry. Final build error/recovery rechecked with readable existing neutral palette. No page created or settings saved. All temporary interception patterns and viewport overrides removed.
-Six protected tables + envHash exactly equal before/final: pages132, page_documents149, menus2, menu_items38, store_settings21, cms_media_assets346. Existing layouts/data/settings and database config preserved.

## Diagnostics
Wrong-root build command was stopped during transformation before public/build output (Nov4timestamps remained); proper Admin build completed. Browser interception required explicit Fetch resource patterns and empty-pattern cleanup; one screenshot timeout resolved with bounded mobileclip/filter. These were tool/dispatch diagnostics, not application regression failures. Historical ledgers retained; elapsed/time tokens unavailable.

Evidence: output/template-settings-resilience-20261117/{source-allowlist,source-hashes,write-boundary,preservation}.json; data-before/data-final.json; failure-final.png, recovered.png, mobile-failure.png. Error repair does not fix the Hostinger intermittent socket rejection itself.
