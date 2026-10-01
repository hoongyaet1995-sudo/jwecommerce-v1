# Shared Stock overview UI: localhost acceptance

Recorded 2026-09-30 17:26 UTC. Approved scope: implant the user's selected first Shared Stock visual direction in the existing Admin inventory page, English copy and existing charcoal/purple theme. Local only: no commit, push or deployment. Baseline branch `main`, HEAD `df409dacc088cdc169d5769d5ac478e952feb2c1`; tracked worktree was clean at start. Existing preview/sample HTML, images, preview build, Intake/Audit screenshot and `tmp/` were untracked and preserved.

Reference actually viewed: `tmp/screenshot/ChatGPT Image Oct 1, 2026, 12_47_16 AM.png`, unique exact basename, SHA-256 `A2FFF37AA2AE790C4C8CC5839E92EB3224E8CE0A9E0219245A50FA653F52259B`. The previous Library-download limitation was resolved by this user-supplied local file, without another download attempt.

## Implementation and data boundary

- Existing product/SKU table shows one Shared Stock total, an amber `Awaiting first count` state, purple `First Count`, and `Manage Stock` for counted stock including zero. Both actions use the existing unified drawer. Independent mapped-store buttons and columns remain intact.
- `SharedPoolStoreCard.tsx` presents read-only mapped stores. The old store-name click/keyboard stock modal entry is removed for Shared mode. Warehouse details appear on hover, keyboard focus or their expansion control.
- Real receipt matching uses the shared balance ID, variant, store and exact facility/provider location. Current balance version and target determine confirmed status; missing/mismatched quantities remain Unknown, and warehouses are never summed. The adapter exposes the already-returned balance version. No backend API, mutation, outbound, takeover or Independent inventory contract was changed.
- Warehouse topology is obtained using the existing facilities GET. That existing endpoint may discover Shopee locations; it is not claimed to be a database-only GET. Browser/unit tests intercept or mock it. Verified no-warehouse/store-level topology, unverified discovery and request failure render separately. Saved failure reasons remain visible. JW Storefront uses `Shared ledger` / `JW Ledger`, never external-provider semantics.
- Saved observations come from the existing history API's latest 100 operations. If a destination's receipt falls outside that window, this UI honestly shows Unknown; it does not invent a quantity. This is a known evidence/coverage boundary, not a fresh provider observation.

## Verification

Initial new component test failed because the component was absent. A subsequent exact store-level provider-location regression failed with Unknown instead of the persisted zero; corrected matching passed. A temporary incomplete test mock and an incorrectly disabled current-stock test configuration were diagnosed and corrected; no real inventory data was used.

- Focused Vitest: 13 tests passed across SharedPoolStoreCard, MarketplaceInventoryPage and existing SharedPoolStockActionModal recovery tests. Coverage includes Shared First Count/Manage Stock, zero/Unknown, exact versus stale/wrong-location receipts, per-warehouse readbacks and takeover, failed discovery/provider reason, focus, refresh, no old store action, and existing Independent behavior. Command: `npm run test -- --run src/previews/inventory48/components/marketplace/SharedPoolStoreCard.test.tsx src/previews/inventory48/components/marketplace/MarketplaceInventoryPage.test.tsx src/previews/inventory48/components/marketplace/inventory/SharedPoolStockActionModal.test.tsx`. Evidence: `tmp/shared-stock-ui-focused.log`.
- Final full Admin suite: 742 passed / 4 failed (176 files, 746 tests). The same four baseline failures are marketplaceAttributeImages, marketplaceOperations lost receipt recovery, sourceInventory manifest count, and marketplace30 MappingPage tooltip. These were reproduced before this phase and explicitly accepted for the earlier Intake/Audit release; this local UI phase does not fix or hide them. Evidence: `tmp/shared-stock-ui-vitest.log` and the prior supervision baseline record.
- TypeScript: 168 diagnostics, matching the prior reproduced baseline after normalizing shifted source line numbers. The one new diagnostic from narrowing the old ATP column to Independent was removed. Evidence: `tmp/shared-stock-ui-types.log`; original comparison `tmp/intake-audit-supervision-types-final.log`. The full type check remains failing, not declared clean.
- Admin production build passed; existing large-bundle warning remains. Evidence: `tmp/shared-stock-ui-build.log`. Generated tracked admin manifest/assets are part of this local build; no artifact was deployed.
- PHPUnit: 9 tests / 99 assertions passed: SharedPoolManageStockTest, SharedPoolStockActionReviewTest, CurrentStockCountMutationTest and SharedFirstCountMarketplaceReadTest. Explicit SQLite `:memory:`, empty DB_URL, array cache/session, sync queue, isolated configuration-cache path and disabled marketplace operation flags; provider-read test uses its injected snapshot mock, action review tests prevent stray HTTP and fake queues. Current-stock-only was enabled for the applicable route. No development/production reset or migration occurred. Evidence: `tmp/shared-stock-ui-php.log`.
- Compiled existing Admin App at localhost `/admin/marketplace/inventory`: passed First Count drawer, prohibited old store click, focus/hover warehouse detail, zero/Unknown, reload and Independent route behavior. All APIs and any non-GET requests were intercepted; zero mutation requests reached a server. Document/auth bootstrap and stock/sync data were fixtures. Product/store identities and local product image bytes came from read-only catalog/store queries; fixture-only unrelated shell notices were dismissed before screenshots. This validates the actual app renderer/integration, not a live authenticated Laravel/provider session. Evidence/script: `tmp/shared-stock-ui-browser.log`, `tmp/shared-stock-ui-browser.mjs`.

## Visual evidence and handoff

Viewed and compared desktop screenshot at the reference's 1819 x 865 viewport. The shared quantity, amber uncounted state, purple action and stacked read-only store hierarchy follow the selected direction. Actual shell spacing, real product/store names and local JW semantics are retained rather than copying sample data. Additional focus, multi-warehouse and mobile screenshots were saved; the existing wide SKU table scrolls horizontally on narrow screens.

- `output/playwright/shared-stock-ui-reference-viewport.png` (latest viewed desktop).
- `output/playwright/shared-stock-ui-desktop.png` (full page).
- `output/playwright/shared-stock-ui-focus.png`.
- `output/playwright/shared-stock-ui-multi-warehouse.png` (explicit warehouse QA fixtures).
- `output/playwright/shared-stock-ui-mobile.png`.

Library creation succeeded and filename/size were confirmed by exact lookup: `libfile_1c4a97430f08819180e90840b4cecc74`, OpenAI file ID `file_00000000eff082119d98840a554f1c79`, `shared-stock-ui-reference-viewport.png`, 149012 bytes. Local Library metadata persistence failed because Windows Python lacks `os.setxattr`; no duplicate creation was attempted. Local original screenshot remains intact.

The separately authorized online test-store stock writes were not needed and were not performed. No provider test, authenticated real-data browser acceptance, commit, GitHub sync or deployment is claimed. Before any future release, review this dirty phase and its generated assets, reverify accepted hashes/tests and obtain new synchronization approval. Current source file hashes and final Git status are captured in `tmp/shared-stock-ui-final-evidence.json`; HEAD remains the baseline. Documentation reduces drift but does not guarantee correctness.

## Release approval and preflight: 2026-10-01

The user explicitly requested GitHub synchronization and Hostinger deployment of the accepted first Shared Stock UI, and clickable completion entry points. This supersedes the original localhost-only delivery restriction for this exact phase. The four reproduced Admin failures and 168 TypeScript diagnostics remain disclosed baseline exceptions; no new feature is included.

Preflight matched every accepted source/document/manifest/screenshot SHA-256 in `tmp/shared-stock-ui-final-evidence.json`. Local main, GitHub main and Hostinger HEAD were all `df409dacc088cdc169d5769d5ac478e952feb2c1`. No local Git/SSH/SCP release process or index lock was observed; Hostinger had no tracked drift. Its pre-existing environment backups, RELEASE_COMMIT and old untracked assets are preserved. Production origin remained the approved SSH deploy-key remote. No database migration, reset or credential change is required.

Release checks reuse the unchanged accepted full suite (742 passed / the same four baseline failures), isolated PHP 9/99 and intercepted localhost browser evidence, with fresh focused UI, build and TypeScript preflight results in `tmp/shared-stock-ui-release-*.log`. The actual inventory route is confirmed by `routes/web.php:507`: `/admin/marketplace/inventory`, requiring admin authentication. HTTPS route/asset and exact-SHA results are recorded at completion below; no authenticated live-stock or real-provider-write acceptance is implied.

## Completed Shared Stock UI application release: 2026-10-01 02:18 UTC

Application commit: `7be2a57e797ad8904cbaeac3b1cd71d0fd60168d`. Explicitly scoped commit included the accepted UI/source tests, contract/acceptance records, five browser screenshots and admin build artifacts; samples, preview build, unrelated Intake/Audit screenshot and tmp files were excluded. GitHub main push succeeded. Hostinger `/home/u762348670/apps/jwecommerce` fast-forward and `/usr/bin/php artisan optimize:clear` succeeded. Local, GitHub and Hostinger matched this SHA; server tracked worktree was clean. No migration/reset/credential change or real inventory/provider write was performed.

Fresh release preflight: 13 focused UI tests passed; build passed and its output hashes matched the previously accepted artifacts. TypeScript remained 168 diagnostics with zero differences from the accepted baseline. The unchanged full Admin result was 742 passed / four reproduced baseline failures; isolated PHP 9 tests / 99 assertions and intercepted existing-App browser acceptance remained tied to unchanged source hashes. These baseline failures/type diagnostics are disclosed exceptions, not successful checks.

HTTPS verification at 02:17:55 UTC: [home](https://ecom.jwaistudio.online/) and [admin login](https://ecom.jwaistudio.online/admin/login) returned 200. The actual route [inventory](https://ecom.jwaistudio.online/admin/marketplace/inventory) returned 302 to the admin login, as required without an authenticated session. Main and inventory JS plus inventory CSS returned 200 and matched local hashes. SSH checks independently matched server file hashes:

- `assets/main-BDXP_sOa.js`: `548a53f8fdfcbbafe0726a3ffb3167cbc6dae367a95ff43b59775dc2a9b1dda5`.
- `assets/Inventory48Runtime-DPmWm6Vh.js`: `69e572c902b4ed81ce0db16b8f856016a004baaee52c2c55c60ab83e5156a92d`.
- Inventory CSS: `c2bd3a228063f7ce9a7f5b9511e42721964ef4ed4c4619a5ed09fea9c3b630a7`.

Evidence: `tmp/shared-stock-ui-release-https.json` / release smoke script and logs. Authenticated live inventory rendering and real-provider stock writes were not tested. The completion record is synchronized in a documentation-only follow-up commit with the identical application/bundle content; the final HEAD is reported in the release handoff receipt, avoiding a self-referential commit SHA in this file.
