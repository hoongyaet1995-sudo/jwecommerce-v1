# Lazada R1 readiness — 2026-09-07

Mode: AUDIT_ONLY for this checkpoint; target remains OPERATIONAL_CLOSURE.

## Current evidence

- Authenticated Chrome inspected application 139209 (JW Studio ERP). App status is Testing. This does not prove the authorized seller is a sandbox seller.
- Registered callback is `https://www.jwaistudio.online/admin/commerce/marketplace-sync/lazada/callback`.
- Opened Edit to inspect its contract: one Callback URL text field with authorization-code receipt description. No multiple-callback support was established. Cancelled without saving; no application configuration changed.
- Read-only Dashboard reference: `app/Services/Commerce/Marketplace/LazadaOpenApiClient.php` performs real HTTP, sorted path-prefixed uppercase HMAC-SHA256 signing, millisecond timestamps and regional API routing. It is not a stub. Do not copy its broad response/logging contract wholesale.
- Dashboard `MarketplaceSyncController` uses its configured Lazada callback when authorizing; replacing the registered callback could affect existing reauthorization.
- Ecommerce `MarketplaceOAuthController` currently implements Shopee start/callback and application matching only. Lazada capabilities are explicitly false. Adding credentials alone cannot enable Lazada safely.

## Capability mapping

| ID | Producer / consumer | Owner / persisted truth | Status | Missing proof |
| --- | --- | --- | --- | --- |
| lazada.oauth | Connect Store / official seller authorization | Admin initiates; server state and encrypted per-store tokens | BACKEND_CONTRACT_GAP | Lazada adapter, state-bound callback, actual seller identity and refresh/reconnect tests |
| lazada.callback | System config / provider redirect | Deployment-owned callback, no merchant secret fields | BACKEND_CONTRACT_GAP | Official callback matching rules and coexistence with current Dashboard callback |
| lazada.catalog-orders | Mapping and OMS / protected read APIs | External snapshots in MySQL | BACKEND_CONTRACT_GAP | Lazada read adapters, pagination, real provider acceptance |
| marketplace.inventory-ui | `/admin/marketplace/inventory` / stock management | JW stock accounting and store policies | ADMIN_UI_GAP | Approved multi-store configuration source; do not create another management page |

No capability is declared closed here. No secret was copied or displayed, no token exchange was attempted, and no stock/order/catalog mutation occurred.

## Next implementation boundary

1. Establish official Lazada redirect matching and token/identity contracts before changing the registered callback. Do not infer wildcard or multiple-URL support from a text field.
2. Implement a disabled-by-default Lazada provider using existing encrypted-token and one-time-state ownership, without modifying Dashboard.
3. Preserve current merchant UI and expose availability only after the deployment callback and credentials are ready.
4. Keep R1 to the approved Shopee/Lazada catalog, mapping/import, order reads, inventory and confirmed AI suggestions. Publishing and fulfillment writes remain excluded.

Release: local evidence only; not committed, pushed or deployed. No formal-entry cutover performed.

## Local authorization contracts added

- Official reference inspected: https://open.lazada.com/apps/doc/doc?docId=108260&nodeId=10777 . Its authorization guidance requires redirect URI matching and distinguishes country store seller IDs from authorization account IDs.
- `LazadaAuthorizationUrl` builds the official authorization URL with state and rejects a callback outside the current HTTPS deployment. Provider-side registration remains a separate gate; the helper does not prove it.
- `LazadaTokenResponse` validates successful token response completeness, positive expiry durations and the R1 Malaysian local-seller identity. It uses `country_user_info.seller_id`, not `user_id` or the account email. Ambiguous/missing seller identities, non-MY and cross-border authorizations are rejected without returning provider payloads in errors.
- Red/green tests run against these actual helpers: `php artisan test --compact --filter=Lazada` passed 5 tests / 46 assertions. These are local fixture tests, not real OAuth evidence.
- Neither helper is wired to a public authorization start/callback yet. Token HTTP exchange, encrypted persistence, refresh, reconnect, browser and real seller acceptance remain pending. Lazada remains unavailable in merchant capabilities.

### Follow-on local implementation (supersedes helper-only checkpoint)

`LazadaClient` now performs signed token exchange/refresh HTTP calls to the official auth host, without redirects or automatic retries. Config defaults disabled. `LazadaAuthorizationService` consumes actor/deployment/provider-bound state, saves encrypted tokens and both expiries, rejects duplicate stores and wrong-store reconnects, and retains a system-generated seller-ID label until actual shop-name retrieval is implemented. Testing app approval is not represented as a fictitious sandbox API environment.

A failing regression exposed configuration replacement during token exchange: tokens could have been saved under a different app key. The service now rejects a changed configuration before persistence. Tests use fake HTTP with actual service/database behavior; there is still no real seller authorization evidence, route/UI wiring, refresh manager or deployment in this checkpoint.

### Fresh-task frontend acceptance, 16:20 MYT

The old task confirmed it stopped project writes. The user confirmed Side Chat opens in the fresh task; this establishes the new-task workaround, not a repair of old history. Existing dirty files were retained in the canonical XAMPP directory.

Current routes, encrypted authorization persistence, refresh manager and capability wiring were included in a fresh PHP regression: 213 tests / 1,292 assertions passed for `Lazada|Marketplace|Shopee|StockAllocation|CheckoutCommerceRules`. Frontend authorization contracts and modal/page tests passed 22 tests, including new Lazada selection, locked existing-store reconnect and validated official navigation with malicious-host rejection. These use injected/fake requests, not real seller consent.

Admin Vite build passed, producing `main-CV54FoIc.js`; the existing large-chunk warning remains. Authenticated Chrome on `http://127.0.0.1:8500/admin/marketplace/auth` rendered the connection list and opened Connect Store. With current local configuration, all providers including Lazada and the proceed button correctly remain disabled. A screenshot was emitted and captured console warnings/errors were empty. This desktop check does not prove an enabled OAuth redirect or a real connection; no fake store/configuration was inserted. No DOM or style changes were made in this checkpoint.

Re-ran disposable-schema MySQL `order-compete` and `order-duplicate`: both passed with one allocation, available stock three and one event. No business migrations were applied. `git diff --check` passed.

Real OAuth remains gated on application/callback ownership. The user has been asked whether to preserve Dashboard with a separate Ecommerce application or deliberately migrate application 139209 to Ecommerce. Dashboard source still uses its existing configured callback; no registration or credential changes were made. Inventory, Lazada catalog/orders, LLM suggestions and final scheduler/provider acceptance remain unfinished. No commit, push or deployment was performed.
