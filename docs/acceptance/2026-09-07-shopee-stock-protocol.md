# Shopee stock protocol and local OAuth boundary

Status: official-document and local-configuration evidence, not stock-push acceptance.

## Fresh evidence

Authenticated Chrome read:
- https://open.shopee.com/developer-guide/223 (Stock & Price Management)
- https://open.shopee.com/documents/v2/v2.product.update_stock?module=89&type=1

The guide distinguishes seller stock, Shopee-managed stock, reservations and multiple locations. Only seller stock can be updated. The update endpoint accepts one item per request and 1–50 model entries. Model zero represents an item without models. Location IDs come from shop warehouse details; location presence must match the existing stock structure. Do not invent a location or use a summary stock total as a seller-location quantity.

Promotion reservations constrain permissible totals. Cross-border global-product stock follows a different API and remains outside the local-MY implementation boundary. Obtain item stock limits before constructing a push; never clamp to a guessed platform maximum.

Response contains separate success and failure model lists. An HTTP success alone does not prove all models were updated. Reconcile every requested model/location and returned quantity, record partial failures, and classify missing/ambiguous results as uncertain. After an uncertain request, read platform state before deciding whether retry is appropriate. Do not reuse raw provider failure text in customer UI or credential-bearing logs.

These protocol requirements are additional to JW reconciliation, per-store opt-in, current mapping validation, latest-availability computation, queue coalescing and confirmed outcomes already required by the MVP. No endpoint call or stock overwrite occurred in this inspection.

## Local OAuth blocker verified from actual runtime

A sanitized Laravel bootstrap read confirmed:
- Local environment; Shopee provider configured with Sandbox and read operations enabled.
- Partner identifier, key and callback are present; no values were printed.
- No marketplace store records exist locally; inventory accounting remains disabled.
- Application host is `127.0.0.1`, callback host is `ecom.jwaistudio.online`, callback path is `/admin/marketplace/shopee/callback`.

`ShopeeClient::isAuthorizationReady` correctly requires the callback to target the current deployment. Provider configuration also requires HTTPS. Therefore credentials alone cannot enable localhost Connect Store, and copying a live encrypted token/store is not an OAuth acceptance substitute.

User was asked to choose a separate HTTPS test entry reaching local XAMPP, or later approved synchronization and real acceptance on the existing HTTPS deployment. No tunnel, public exposure, callback change, environment edit or deployment was performed. Test-account login/API authorization is recorded separately and does not solve callback routing by itself.

## User-selected acceptance path and local preparation

User subsequently directed: complete local phase testing, then test directly on `ecom.jwaistudio.online`. Proceed with the existing HTTPS deployment path, not a local tunnel. The assistant stated that necessary verified phase code would be synchronized before live acceptance; no additional platform-write capabilities are enabled by this choice.

Fresh preparation: 52 frontend tests across 20 files passed; Admin build passed (`main-CV54FoIc.js`, existing chunk-size warning). Applied only local migrations 000004–000007 after inspecting their additive changes; product-variant stock fingerprint was unchanged and inventory accounting remained disabled. Earlier statements that these migrations were not run are superseded for localhost only.

Read-only SSH confirmed production HEAD remains `eaa605b4969f9e35689463ebd66212ca1d4d8382`; its two existing untracked files were left alone. No commit/push/deployment has yet occurred in this preparation checkpoint. Still complete selective source review/staging, final local runtime checks, synchronization and matching-SHA/live acceptance before declaring this phase delivered. Preserve raw archives and unrelated untracked material.

## Pickup warehouse read adapter

Fresh authenticated Chrome official reference: https://open.shopee.com/documents/v2/v2.shop.get_warehouse_detail?module=92&type=1 . Current request table exposes optional warehouse_type (1 pickup,2 return). The example/error table contains older region references despite its absence from the current request table; do not infer MY availability from that stale region list. Permission failure warehouse.error_not_in_whitelist is a failure, not a zero-stock/default-location result.

Local ShopeeClient::pickupWarehouses uses signed GET with warehouse_type1, validates warehouse IDs/type/location/region/holiday state and projects no street-address data. It preserves distinct warehouse and stock-location identifiers. It does not pick a write destination, infer stock or turn an API error into an empty list. Test-first missing method failed; full ShopeeClientTest passed29 tests/80 assertions. Transport responses were mocked. No actual warehouse API call, stock mutation or deployment occurred; real sandbox permission/response acceptance is pending.

## Actual Sandbox warehouse permission result

Executed locally authored tests/manual/shopee-sandbox-warehouse-probe.php through SSH stdin in the existing approved application runtime (no remote source installation). It enforces sandbox/read gates and exact authorized shop227482706/store1, uses the server token manager and performs one GET /api/v2/shop/get_warehouse_detail with warehouse_type1. Syntax check passed.

Actual provider result: HTTP200, business error warehouse.error_not_in_whitelist, response not a list, warehouse_count null. This proves the current test shop lacks multi-warehouse access; it is not an empty warehouse list or zero-stock result. No stock/location mutation was performed. Do not fabricate a location. Next inspect current item/model seller_stock structure and the documented single-warehouse update contract before preparing any write. A general warehouse-read adapter remains local/unreleased.

## Catalog stock snapshot checkpoint (2026-09-07)

Re-ran the approved sandbox read probe against store 1 / shop 227482706 via the deployed application's existing itemDetails/models client. Item 846055463 has models and no item-level stock_info_v2. Model 12983425 returned seller location MYZ, stock 97, saleable true, summary reserved 0 / available 97, Shopee stock 0 with empty location, and both advance quantities 0. This is an observed sandbox read, not a stock write or physical inventory baseline.

Local ShopeeCatalogService now retains stock_info_v2 as observed_data.stockInfo beside the existing summary stock field. Missing/null metadata replaces prior snapshots with null rather than retaining stale locations or inventing zero. Existing fetched_at identifies observation time; this cache must never authorize writes or prove current provider quantity. No UI changes or provider mutations. This change has not been deployed.

Verification: MarketplaceCatalogTest and ShopeeClientTest: 34 passed, 134 assertions. Regression first failed because stockInfo was discarded, then passed with persistence; null refresh verified. Real probe verifies provider response only, not live persistence of this undeployed change.

## Same-store mapping constraint verification (2026-09-07)

Inspected the inventory read joins and tested an attempted mismatch between mapping.store_id and its listing.store_id. The database rejected the mismatch before the read; the suspected join vulnerability was not reproduced. Removed the exploratory test/change rather than adding redundant application behavior.

Read information_schema.KEY_COLUMN_USAGE on both local MySQL and Hostinger. Both have mp_mapping_listing_store_fk with (listing_variant_id, store_id) referencing marketplace_listing_variants (id, store_id). Thus this invariant is present in the actual runtime schemas, not only SQLite tests or migration source. No production records changed.
