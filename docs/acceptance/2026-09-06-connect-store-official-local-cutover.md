# Connect Store official-entry local cutover

User approved official entry replacement on 2026-09-06. Local `/admin/marketplace/auth` now mounts ZIP 30 MarketplaceAuthPage through OperationalConnectStore, using protected connections API and real authorization callback.

- Source structure/classes retained. Removed operational fake quota, webhook success, fixed four-store count and merchant developer-reference card. Unavailable write/automatic-sync controls disabled.
- Persisted store identity/expiry comes from connections API; no client-created authorization identity. Missing last-sync data says Unavailable, not a fabricated timestamp.
- Disconnect callback awaits protected API completion and retains history. Provider revocation is not claimed.
- Local authenticated Chrome/Playwright page loaded new official surface. Local DB correctly has zero connections; it is not populated from production or mock stores.
- Connect Store opened the real authorization modal. Local provider capability was false, so official authorization was disabled (expected local deployment/callback configuration), no fake consent/success appeared.
- Screenshots: `output/playwright/marketplace-30/connect-store-official-local.png` and `connect-store-official-local-mobile.png`.
- Admin build passed (existing large-bundle warning), TypeScript passed; Auth page plus OAuth modal tests 5 passed.

Pending: inspect screenshot rendering, real connected-state browser and disconnect-error acceptance, bundle source/mock exclusion audit, callback return-to-main handling, coherent release synchronization. No deployment or live official-entry completion claimed yet.

## Callback follow-through

- Callback now always returns to `/admin/marketplace/auth?connection=...`, including when the legacy Sandbox acceptance flag is set. Auxiliary acceptance route remains available under its existing gate.
- Official container reads `/oauth-result`; it does not trust the query string. Verified success/cancellation/failure uses the existing source modal. Starting a new connect clears the previous visible result.
- Authorization feature suite: 17 tests, 92 assertions passed. Two source Auth page tests passed including callback result -> close -> new connect; TypeScript passed.
- Shared source subnavigation now imports ContextBridge, avoiding the demo model dependency through this entry.
- Mobile screenshot inspected: modal fits the viewport and has an internal scroll area. Longer Unavailable platform badges sit close to logos; final visual acceptance is not yet claimed. Main connected-state acceptance remains pending.

## Connected-card and failure browser checks

- Local Playwright intercepted only connection metadata, OAuth receipt and a failing disconnect endpoint. Real production tokens and connection were not changed. This is UI acceptance, not a real platform disconnect.
- Connected card rendered supplied shop identity; confirm disconnect issued exactly one request, failure toast appeared, modal stayed open and retry enabled. Cancel retained the connection. Configured Shopee enabled official-authorization action.
- Removed an additional source demo Webhook Secret block in operational mode after a failing regression test. Developer secret copying is not exposed.
- Screenshots: `connect-store-connected-fixture.png`, `connect-store-disconnect-failure-fixture.png`, `connect-store-available-mobile-fixture.png` under the existing output directory. Connected-card desktop screenshot inspected.
- Latest full Marketplace/Shopee regression: 78 passed, 549 assertions. Build passed with large-chunk warning; diff check clean.
- Ready to synchronize the coherent Connect Store cutover and supporting catalog contracts after release approval. Mapping page server controls are not yet fully mounted as the official mapping runtime; do not advertise mapping UI completion.
