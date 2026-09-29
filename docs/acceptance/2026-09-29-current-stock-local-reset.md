# Current Stock local acceptance — 2026-09-29

The local development MySQL database `jw_ecommerce` was backed up before the schema migration and stock-ledger reset. The private backup is `storage/app/private/current-stock-reset/jw_ecommerce-20260929-090703.sql` (3,043,684 bytes; SHA-256 `da6c29111cdfcc9bc1781f44880d887ff3e2d99e0f2fdac2e4aa0e56e9d6126f`). Its header identifies `jw_ecommerce`, and the dump has a completion footer. It is not committed.

`inventory:reset-current-stock` dry run showed 3 balance rows, 8 physical movements, 8 count reviews, 1 reconciliation, 1 safety event, 3 sandbox attempts and 2 inventory movements. The reset receipt `local-current-stock-20260929-01` shows those effect tables empty afterwards. All 3 balance rows now have `current_stock = NULL` (not First Counted). It preserved 27 JW orders and 100 Marketplace orders and wrote 127 historical-order tombstones. Catalog variant stock projections were reset to 0. No provider stock write was made by the reset. Local `.env` has `INVENTORY_CURRENT_STOCK_ONLY=true`; the repository example remains false until a separately approved production cutover.

The independent two-SKU SQLite canary at `storage/app/inventory-two-sku-canary.sqlite` was backed up before migration at `storage/app/private/current-stock-reset/inventory-two-sku-canary-before-current-stock-20260929.sqlite` (SHA-256 `8b8f62718b221f1faf7db5bca145c2271c5fa84d52c5a7f3552f357110d9af85`). It had no orders. Browser acceptance on `http://127.0.0.1:8768/admin/marketplace/inventory` used `wmstest001` (Black) and `TX_TEST_001` (Beige):

| Operation | Browser result | Ledger result |
| --- | --- | --- |
| First Count + allocation, Black | Saved without review blocker | JW Storefront 6, Lazada canary A 4, B 0 |
| Actual Intake, Black | +2 saved | JW Storefront 6 → 8 |
| Audit Adjustment, Black | Absolute 7 saved | JW Storefront 8 → 7 |
| Stock Transfer, Black | 1 unit transferred; execution pipeline shown | JW Storefront 7 → 6, Lazada A 4 → 5 |
| First Count + allocation, Beige | Saved without review blocker | JW Storefront 3, Lazada A 2, B 0 |

The overview, Manage Stock, First Count/Allocate, Intake/Audit and Transfer UI showed `Current Stock` as the sole operational quantity in this canary. The Lazada canary store is deliberately offline: provider sync checks returned unavailable / not confirmed, while JW ledger mutations succeeded. This verifies separation of ledger entry from provider delivery, not real Lazada acceptance. The temporary QA account was disabled after browser testing and retained only as the stock-audit actor.

Focused Current Stock PHP tests: 12 passed, 58 assertions. Targeted admin UI tests: 30 passed. The admin bundle built successfully. A full repository TypeScript check still reports unrelated existing errors in other preview components; the edited inventory48 files produced no TypeScript errors in the filtered check. The pre-existing legacy `CheckoutCommerceRulesTest` quick-add stock assertion is still a separate baseline issue.

Production remains on the prior mode. Before changing it, take a production-specific inventory and backup, migrate the production schema, create the production cutover/tombstones, set `INVENTORY_CURRENT_STOCK_ONLY=true`, deploy the same verified Git SHA, and check live HTTPS and a real connected Marketplace stock readback. The two-SKU canary is not evidence of a successful real provider push.

Marketplace reversal coverage remains bounded: Lazada canceled order items and Shopee canceled orders restore Current Stock idempotently. The Marketplace Hub currently accepts Lazada trade-order change notifications only; refund/reverse-order notifications are not yet routed through Hub to Ecommerce, so a Lazada refund cannot be claimed as automatically restocked. Do not use this local acceptance as evidence of end-to-end refund automation.
