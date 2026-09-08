# Marketplace OAuth/catalog implementation status

## Superseding checkpoint — 2026-09-06 OAuth release acceptance

### OAuth result adapter update

- Database-race error boundary verified: injected Laravel unique-constraint exception already falls under the existing RuntimeException catch (PDOException inheritance). Added regression proving safe failed redirect and sanitized result with no token. No redundant production patch. This is exception-boundary evidence, NOT real concurrent MySQL acceptance.

- Subsequent identity-field correction: operational Shop Name / Shop ID inputs are read-only, with explicit retrieval-after-authorization guidance; no silently ignored merchant input. Demo mode remains unchanged. Fresh 10 frontend tests, typecheck and build passed (`oauth-DKQwHYIX.js`); Chrome AX confirms both fields no longer settable. Production-entry strategy has been asked as a separate approval, not assumed.

- Added admin-only `/admin/api/marketplace/oauth-result`: callback stores a ten-minute actor-bound server-session receipt; query parameters cannot fabricate a success. New authorization clears the old receipt. Public result includes only selected store metadata, not tokens.
- ZIP 30 `ConnectStoreModal` now consumes this existing endpoint through isolated `oauth.tsx`; success retains source DOM/classes but replaces fixed token duration and automatic-sync/task claims with authorization-only facts. Cancel/failure use existing source cards. No official route replaced.
- Latest verification: **51 PHP tests / 306 assertions**, **10 frontend tests**, TypeScript and isolated Vite build passed. Entry `oauth-DJRhR_Xw.js`; adapted modal SHA256 `17119DF226B5DB75CB0D46CCBEE004AB492F44395D62BA58B760CC8525B88085`.
- Chrome actual local admin login followed an invalid callback, then opened the isolated preview: observed **OAuth Handshake Failed**, not success; screenshot inspected in conversation. No provider request was initiated by the missing-state callback. The old Playwright CLI session was terminal/missing; used Chrome instead, not assumed still running.
- Result presentation is connected in preview, but official callback navigation still returns to old Auth entry. Real successful Seller OAuth and production cutover remain unaccepted. Mobile result comparison remains pending.

The earlier sections below are historical, not current configuration or test results.

- Fresh PHP verification: **49 passed, 291 assertions** (`php artisan test --compact --filter='Marketplace|ShopeeClient'`), SQLite and simulated HTTP only.
- Frontend operations/modal: **9 passed**. TypeScript check and isolated OAuth build passed (`oauth-DxSIaXhg.js`).
- Readiness: sandbox credentials configured privately, required tables present, database queue selected; **oauth_callback_ready=false**. Local APP_URL and production HTTPS callback intentionally cannot share the OAuth session/database. Worker and real provider acceptance remain unverified.
- Authenticated browser confirmed authorization disabled with this mismatch, with no demo success. Screenshots: `output/playwright/marketplace-30/oauth-release-desktop.png` and `oauth-release-mobile.png`.
- Local migrations now include token/application binding, catalog/order runs and draft import identity. Order snapshots and explicit draft import are implemented locally, not real-provider accepted.
- Read-only Shopee console inspection is in `2026-09-06-shopee-console-readiness.md`; Test/Live redirect domains now show Ecommerce. Live credentials remain dormant. No seller authorization or remote write occurred.

### Release gates still open

1. Preview route is local-only. A protected production entry requires explicit cutover approval, not simply deleting the environment guard.
2. Callback returns to the old Connect Store UI. Bind ZIP 30 success/cancel/failure presentation to server-confirmed results and real store metadata, never a user-editable success query alone.
3. Real-mode nickname/handle inputs are not transmitted. Resolve these misleading inputs while retaining the platform-returned store identity as authoritative.
4. Complete browser result/reconnect/disconnect tests and mobile interactions; screenshots alone are insufficient.
5. Verify MySQL concurrent duplicate authorization and sanitized failure handling. Prepare an explicit source/build/migration allowlist: the dirty tree includes non-OAuth work and unrelated artifacts.
6. Request exact GitHub/Hostinger synchronization approval after local gates pass, then perform real Seller OAuth on the approved HTTPS deployment.

User approved preparing OAuth release acceptance, NOT immediate synchronization. No phase commit, push, deployment, official-entry cutover, publication, shipping or stock write has occurred. The overall goal stays active; broader catalog/order UI, provider verification, queue recovery, publication, fulfillment and stock gates remain open.

---

## Historical checkpoint (superseded above)

This is a local implementation checkpoint, not a production or real-provider acceptance claim.

## Implemented

- Shopee signed authorization URL, token exchange, shop-bound identity lookup and refresh transport.
- One-use hashed state bound to actor/platform/deployment/reconnect store, with expiry.
- Encrypted per-store token records; metadata response excludes credentials.
- Admin-only authorization/callback/disconnect endpoints, replay/cancel/wrong-store protection.
- Same-store variant uniqueness and listing/store composite FK; cross-store mapping reuse.
- Catalog list/details/models read path, idempotent external snapshot page writes.
- Database queue catalog runs, progress, failed-run retry and active-run reuse.
- Server-side flat variant catalog pagination and filters tested with 320 synthetic rows.
- ZIP 30 isolated demo preview only; approved minimal mobile modal scroll correction.

## Evidence and limits

- Latest suite: `C:/xampp/php/php.exe artisan test --filter='Marketplace|ShopeeClient'`: 32 tests, 155 assertions passed.
- Feature suite uses isolated SQLite and simulated HTTP. Does not prove MySQL concurrency or real provider compatibility.
- Local MySQL migrations through `2026_09_06_000005` applied successfully. No production migration.
- Official Shopee docs fetch returned HTTP 403. Protocol references are local Dashboard; get_model_list contract still needs official/real verification.
- Official Marketplace UI still uses its prior data adapter. ZIP 30 has NOT replaced the official entry.
- Catalog job currently reads NORMAL listings only; other status sweeps and full recovery/worker acceptance remain incomplete.
- No real seller authorization, live catalog import, remote listing publication, marketplace order ingestion, stock synchronization or fulfillment acceptance yet.

## Real authorization configuration gate

Read-only inspection found all five MARKETPLACE_SHOPEE environment keys absent from local `.env`.
Configure these securely, never in chat, browser fields or committed source:

- `MARKETPLACE_SHOPEE_ENABLED`
- `MARKETPLACE_SHOPEE_ENVIRONMENT` (sandbox or production)
- `MARKETPLACE_SHOPEE_PARTNER_ID`
- `MARKETPLACE_SHOPEE_PARTNER_KEY`
- `MARKETPLACE_SHOPEE_CALLBACK_URL`

Callback path: `/admin/marketplace/shopee/callback` on this deployment's approved HTTPS origin, with no query/fragment. Must be registered/accepted by Shopee and preserve the initiating Admin session. Do not overwrite Dashboard callback configuration. Independent-deployment callback support is an unresolved external gate.

## Next required work

1. Bind ZIP 30 isolated operational preview to real endpoints; remove simulated authorization and developer-only controls under the approved plan. Obtain official-entry cutover acceptance.
2. Harden queue recovery, status sweeps, audit events, credential/config binding, concurrent MySQL tests and expiration/error classification.
3. Configure approved HTTPS callback/credentials and have seller complete real authorization, then read-only catalog acceptance.
4. Implement and verify product import/publish, orders, stock and fulfillment in original roadmap order; then subsequent providers. No capability is complete merely because a shared page exists.

Queue worker (when explicitly ready to execute tasks): `php artisan queue:work marketplace --queue=marketplace --timeout=1200 --tries=1`. Connection retry_after is 1500 seconds. Worker has not been started for real stores.

No GitHub push or Hostinger deployment has been performed for this phase.
