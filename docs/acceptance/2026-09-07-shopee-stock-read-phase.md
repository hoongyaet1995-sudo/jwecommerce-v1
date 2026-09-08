# Shopee stock read phase — local acceptance

Scope: preserve per-model stock_info_v2 in catalog snapshots, clear unavailable snapshot data on refresh, and add a signed pickup warehouse read adapter with address exclusion and explicit provider error handling. Four tracked files only: ShopeeCatalogService.php, ShopeeClient.php, MarketplaceCatalogTest.php, ShopeeClientTest.php. No schema, UI, configuration or platform write changes.

Fresh regression on 2026-09-07: `C:/xampp/php/php.exe artisan test --compact --filter="Marketplace|Shopee|InventoryMutation|StockAllocation"` — 196 passed, 1252 assertions, 14.76 seconds. Includes catalog, mapping/import, order and inventory regression; fake provider tests are not real platform acceptance.

Real sandbox stock probe independently returned model 12983425 / location MYZ / available 97 / reserved 0. Warehouse API previously returned warehouse.error_not_in_whitelist, so warehouse success remains unverified for this account. The new persistence path is local only; post-release catalog refresh and database inspection are needed to verify actual snapshot persistence on Hostinger.

Release candidate includes the four tracked files above. Existing unrelated untracked source exports, artifacts and notes are excluded. GitHub/Hostinger synchronization has not occurred for this phase. All stock write gates remain outside this scope.
