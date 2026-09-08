# Marketplace handoff to a fresh Codex task

User explicitly requested a new conversation carrying all work because Side Chat fails in the old task. Do not fork the broken history. Canonical development: C:/xampp/htdocs/jwstudio-ecommerce. Read AGENTS.md. Preserve ALL dirty/untracked files; no reset/clean or blanket staging. No current release approval for this local phase.

## Scope and sources

Read `docs/acceptance/2026-09-07-marketplace-mvp-boundary.md` (later sections supersede earlier checkpoints) and `2026-09-07-lazada-readiness.md`. User approved R1 Shopee + Lazada multi-store, catalog reads/mapping/reviewed draft imports, order reads/status, JW inventory accounting and opt-in stock pushes, confirmed LLM suggestions. Publishing/title/image/price writes, JW shipping/AWB actions, TikTok/Shopify are deferred. Seller Center owns publishing and fulfillment actions. Cron acceptance last. Preserve ZIP30 UI and accepted ZIP32 import UI. Dashboard C:/xampp/htdocs/jwstudio-dashboard is READONLY protocol reference.

## Git/runtime

HEAD verified eaa605b4969f9e35689463ebd66212ca1d4d8382; last known GitHub/Hostinger release same, reverify before release. Current changes NOT committed/deployed. Hostinger alias jw-hostinger, application /home/u762348670/apps/jwecommerce, ecom.jwaistudio.online. Never print .env/token/key values. Local PHP C:/xampp/php/php.exe, HTTP 127.0.0.1:8500. Node C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node.exe. Frontend cwd resources/admin. Existing source preview 8532, Vite 8510; revalidate processes.

## New local code (inspect actual files)

- Inventory StockAllocationService + migrations 000004..000005, MarketplaceOrderInventoryService, exception handling, inventory read APIs. Default disabled. Legacy product_variants.stock is AVAILABLE UNALLOCATED stock, not physical: checkout already decrements it. Never double-subtract reservations. No historical replay/physical reconstruction. UI gap prompt docs/google-ai-studio/prompts/2026-09-07-marketplace-inventory-multistore.md; user mandates existing /admin/marketplace/inventory. Await source for compound UI; backend can proceed disabled.
- Orders 30-day request split into <=14-day provider windows, migration000006. AutomaticOrders service/migration000007 disabled; cron call added but no host cron acceptance. No business DB migrations000004..000007 run yet; tests isolated DB only.
- LazadaAuthorizationUrl, LazadaTokenResponse, LazadaClient, LazadaAuthorizationService, LazadaTokenManager, LazadaOAuthController; routes POST /admin/api/marketplace/lazada/authorize and GET /admin/marketplace/oauth/lazada/callback. Config lazada defaults disabled, no real credentials copied. Token exchange/refresh real HTTP implementation but all acceptance so far fake HTTP with real local services/database. MY local sellers only; seller_id from country_user_info, not user_id/email. Encrypted tokens and both expiries, replay/duplicate/wrong reconnect guards. Connections API now handles Lazada identity and OAuth readiness; other Lazada capabilities false.
- Current latest change: resources/admin/src/integration/marketplaceOperations.ts allows Lazada authorization requests and validates official auth.lazada.com/oauth/authorize destination. New tests in marketplaceOperations.test.ts. 13 tests passed at 16:12 MYT. No build/browser verification AFTER this change. Need inspect callers/components for remaining hardcodes and test actual modal click before claiming connected UI.
- Earlier import UI refinements remain dirty: stable reviewed SKU generation, actual draft/link counts, single-variant import drawer. Dormant publishing brand metadata hook changes exist, OUTSIDE MVP; preserve but do not expand/accidentally release as enabled.
- Current tracked build deletes main-BhZMiV5w.js and has untracked main-C2hKKs9t.js with modified manifest. This earlier build DOES NOT include newest Lazada TS change. Rebuild and inspect scope before release.

## Evidence and remaining work

Latest broad test checkpoint before newest routes/capability/frontend work: 208 passed /1259 assertions, filter Lazada|Marketplace|Shopee|StockAllocation|CheckoutCommerceRules. Latest targeted PHP Lazada+MarketplaceAuthorization 35 passed/217 assertions. Latest TS operational contract13 passed. Rerun all relevant tests fresh. MySQL manual stock-allocation race script modes compete, duplicate, order-compete, order-duplicate; isolated disposable DB only. Order race modes not rerun after fulfillment_flag fixture guard update.

Remaining: real Lazada OAuth/callback coexistence, frontend browser acceptance, real provider catalog/orders, inventory reconciliation/UI/stock push, LLM engine/suggestions, final scheduler and real platform evidence. Shopee sandbox earlier real catalog/orders succeeded (store227482706), but inventory/AI not closed. No current real provider write enabled.

## Lazada official app/callback evidence

Chrome user authorized app139209 JW Studio ERP, statusTesting. Registered Callback https://www.jwaistudio.online/admin/commerce/marketplace-sync/lazada/callback. Read Edit then Cancel, NO configuration changed. Official docs require redirect matching: https://open.lazada.com/apps/doc/doc?docId=108260&nodeId=10777. Cannot presume multiple callbacks. User permits callback configuration, but don't silently break Dashboard or introduce shared OAuth gateway; resolve coexistence/decision first. Testing status does not prove seller sandbox. Chrome session may remain logged in, discover tabs normally; never dump app secret or user keys.

## Side Chat diagnosis and new task purpose

Old task ID01a062c5-39ab-7d11-97b9-e9be9c528bc9. Logs C:/Users/User/AppData/Local/Codex/Logs/2026/09/07/codex-desktop-ff360c48-2925-4b2e-9c43-9ccfffe2b8d4-35880-t0-i1-021256-0.log lines536-537 and4784-4785 show thread/fork fails: history projection for01a07290-4895-7933-a366-c7b1411652a2 expected ordinal15291, got15290. Do not alter Codex databases or clear history. Fresh task is a workaround, not proven Side Chat repair. Ask user to try Side Chat there. Old goal remains incomplete; user needs to pause it to avoid two writers. Do not claim old goal complete merely due to transfer.
