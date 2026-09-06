# Sandbox OAuth isolated entry — local candidate

User approved this entry strategy on 2026-09-06. This is NOT synchronization approval.

## Scope

- `MARKETPLACE_OPERATIONS_ENABLED=false` defaults closed for the new catalog/order/import API group, including fetch and retry. Direct authenticated requests receive sanitized 503 before controller execution. Catalog and order page jobs also check the flag after claiming their expected page and before calling the provider service: disabled runs become failed with `marketplace_operations_disabled`, retaining offsets/cursors and counters for explicit retry. The existing foundation/manual mapping and OAuth lifecycle endpoints are unchanged. Configuration changes require config-cache refresh and restarting long-lived workers; this is not cancellation of an HTTP request already in flight. No worker is to be started in the OAuth-only phase.

- New protected entry: `/admin/marketplace/oauth-acceptance`.
- `MARKETPLACE_OAUTH_ACCEPTANCE_ENABLED=false` by default; explicitly enabled locally for browser acceptance only.
- Entry also requires `MARKETPLACE_SHOPEE_ENVIRONMENT=sandbox`; live configuration returns 404.
- Existing Marketplace pages and navigation remain untouched. Existing local-only `/oauth-preview` guard remains.
- When acceptance is enabled in sandbox, OAuth callback redirects to the isolated entry, whose result is read from the actor-bound server-session receipt, not a query parameter.
- ZIP 30 modal is reused. Credentials and API keys never enter its bootstrap. No catalog/order task or stock push is automatically started by OAuth.

## Verification

- Real local MySQL duplicate-connection check: `php tests/manual/marketplace-oauth-mysql-race.php store` passed. Two independent authorization attempts for the same shop competed after the state-lock barrier: one succeeded, one duplicate rejected, exactly one store and one encrypted token persisted. Store DDL is cloned without rows from the actual local table, and actual state/token migrations and authorization service run in an isolated disposable schema. Provider HTTP is simulated; this is database concurrency evidence, not Shopee API acceptance. Both state and store modes re-ran successfully; test databases removed afterward.

- Real local MySQL concurrency: `C:/xampp/php/php.exe tests/manual/marketplace-oauth-mysql-race.php` passed. Two independent PHP processes reached a parent-held row-lock barrier; after release exactly one state consume won, the other rejected, with one persisted consumed record. Uses the actual OAuthStateService and state migrations in a generated isolated schema; schema removed afterward, no application data changed. This proves state consumption only, not duplicate store creation or mapping concurrency.

- 56 PHP Marketplace/Shopee tests, 343 assertions passed on the latest rerun, including unauthenticated/non-admin denial, disabled entry, sandbox production-environment access, live rejection, callback destination and both HTTP/job operational gates. The new disabled-page regression was observed failing before the two job guards were added.
- Feature tests use SQLite/provider HTTP fixtures. The separate MySQL checks above passed again; real Shopee OAuth remains unverified.
- Chrome local invalid callback returned to the new entry with title `SANDBOX ACCEPTANCE — Connect Marketplace Store`.
- Frontend latest isolated build `oauth-DKQwHYIX.js`; 10 frontend tests passed on the latest rerun. Build must accompany the selected code release.
- Authenticated browser acceptance at 390 x 844: the modal scrolls to both bottom actions without horizontal clipping. Invalid callback shows a failure card and remains failed after reload; no success is inferred from the URL. Evidence: `output/playwright/marketplace-30/oauth-acceptance-mobile.png`, `oauth-acceptance-mobile-actions.png`, `oauth-acceptance-mobile-failed.png`. Desktop failure capture: `oauth-acceptance-desktop-failed.png`.
- Remaining lifecycle browser coverage: successful official consent, reconnection and disconnection are not yet accepted. The isolated entry currently covers first connection/result only; backend reconnect/disconnect tests do not replace merchant browser acceptance. This candidate is for limited Sandbox OAuth validation, not general Marketplace release.

## Before asking for exact synchronization approval

### Proposed coherent release file boundary (not yet staged)

Include only the following reviewed Marketplace phase paths, resolving files explicitly before staging:

- Modified `.env.example`, `config/queue.php`, `routes/web.php`, `app/Services/Marketplace/MarketplaceFoundationService.php`, and its feature test.
- New `config/marketplace.php`, Marketplace OAuth/catalog/order/import controllers, operations gate middleware, Shopee/OAuth/import/order services, encrypted token model and the two Shopee queue jobs.
- Marketplace migrations `2026_09_06_000002` through `000010` and their focused Marketplace/Shopee feature tests, plus the opt-in MySQL race script.
- `resources/admin/src/integration/marketplaceOperations.ts` and test; scoped `src/previews/marketplace30` source dependencies; `vite.marketplace-oauth.config.ts`; OAuth Blade view; exact `public/build-marketplace-preview/manifest.json` and its referenced assets.
- Current Marketplace acceptance/design documents only, selected by explicit filename after review.

The dormant catalog/order/import implementation is included as a coherent dependency set but its API group defaults disabled. Do not enable `MARKETPLACE_OPERATIONS_ENABLED` for the OAuth-only release. No Marketplace queue worker in this phase.

Exclude `.env`, downloaded ZIPs, raw Temp extraction, `tmp`, node_modules, unrelated acceptance reports, PDFs, checkout/storefront modifications, previews 28/29, source-demo HTML entrypoints, and unrelated generated assets. Screenshots are evidence, not public web assets. GitHub/Hostinger synchronization must still be explicitly approved; do not use `git add .`.

1. Review explicit release source/migration/build allowlist. Dirty worktree includes later catalog/import/order APIs and unrelated artifacts; do not blanket deploy everything.
2. Mobile entry/failure and MySQL checks are complete. Perform successful consent and subsequent connection lifecycle acceptance in the approved HTTPS phase; do not claim those passed beforehand.
3. Verify deployment callback/session configuration and sandbox credential provenance without printing secrets. Preserve APP_KEY used for token encryption; disable APP_DEBUG in production.
4. Prepare rollback as disabling acceptance/provider access while retaining connection history; do not roll back/drop token/store tables as routine rollback.
5. Obtain exact GitHub/Hostinger release approval. Only then deploy the accepted commit, set production-specific sandbox configuration, and perform real seller consent.

Actual signed authorization is still disabled on localhost because APP_URL and the configured HTTPS callback target different deployments. Do not bypass this guard or move authorization codes across databases manually.

## Final local artifact check (2026-09-06)

Rebuilt with `npm exec vite -- build --config vite.marketplace-oauth.config.ts` from `resources/admin`; successful and the two asset names remain unchanged. The operational entry imports the ZIP 30 connection modal, logo and context bridge plus the tracked shared Marketplace request helper. The bridge's AdminContext reference is type-only; the isolated entry does not mount the demo provider. Source dependencies needed for typechecking may contain fixtures, but no demo HTML entrypoint is part of the public acceptance build. Do not equate source fixture presence with a connected store.

Exact public artifact allowlist and SHA256:

| Path | SHA256 |
| --- | --- |
| `public/build-marketplace-preview/manifest.json` | `AF26A4EDBB77183FA6A021556048A7B7C239B65774DB483C7991D323A17A0666` |
| `public/build-marketplace-preview/assets/oauth-DKQwHYIX.js` | `A7CA37D046B4197A687861739EEDA58FC88471949787AD6ABB774B6659FA1C92` |
| `public/build-marketplace-preview/assets/oauth-C3U2LsF9.css` | `5EC8672735DED0CD0F7A6F98738E700F7F9940C2DE484930D05F1E70022A5A3E` |

Include `app/Console/Commands/MarketplaceReadiness.php` in the source release boundary. Latest read-only readiness output: sandbox, provider configured, no missing tables, database queue, OAuth callback NOT ready; exit 1 is expected for the local/HTTPS deployment mismatch. This is the remaining environment boundary for official consent, not a reason to weaken callback binding. Worker and real provider acceptance are explicitly unverified.

No files have been staged or committed by this check, and no GitHub or Hostinger synchronization has taken place. Await explicit phase approval before release.
