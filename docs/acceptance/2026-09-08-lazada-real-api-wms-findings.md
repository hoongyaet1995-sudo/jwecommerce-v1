# Lazada real-provider findings and remaining WMS work — 2026-09-08

## Evidence

Official API Explorer application 139209 generated a Loan Test Account access token.
Seller API verified seller 300163725140, short code MY4NA1T7CK, Zappyworks Test.
The token was also used through local Laravel LazadaClient, not only API Explorer.
Five successful read requests covered seller, product list, item details, order list and order items.
No OAuth callback was used; customer OAuth completion is not claimed. A local encrypted official_test connection has since been persisted with a one-hour local lease; provider expiry and refresh token remain null.
No token, signature, signed URL, buyer details or provider secret is included here.

Observed contract differences repaired locally:
- /products/get uses data.total_products (2132 observed).
- /product/item/get uses data.primary_category.
- /order/items/get returns data as a list directly.
- Order items contain separate order_item_id values for the same SKU and expose warehouse_code.
- Example catalog warehouse code dropshipping differs from an order warehouse code MY4NA1T7CK-WH-10007; these must not be equated without warehouse API verification.

Reproduction: tests/manual/lazada-test-read-probe.php reads the token from standard input, checks the approved application and seller, and prints only non-secret diagnostic fields. It performs no database or provider mutations. Never put credentials in tracked files.

## Authoritative implementation gap

MarketplaceOrderSnapshotService::saveLazada saves encrypted snapshots but does not call inventory processing.
MarketplaceOrderInventoryService::process permits only Shopee and applies a whole-order terminal state.
StockAllocationService already delegates physical accounting to PhysicalAllocationService.
Independent warehouse allocation resolves a store route and saves the chosen inventory_balance_id, retaining allocation warehouse ownership for subsequent consume/release.

## Required implementation and acceptance

1. Add a Lazada item lifecycle processor using order_item_id as line identity; do not group same-SKU units into one lifecycle.
2. Verify supported status semantics against official documentation and actual test transitions before enabling mutations. Pending/packed, shipment, cancellation and return scenarios need separate evidence.
3. Keep per-item terminal records even for cancellation before allocation; an old replay must never create a fresh reservation. Do not reuse the Shopee whole-order terminal shortcut for mixed outcomes.
4. Apply the store activation cutoff from created_at, ignoring historical imports for deductions.
5. Resolve mapped SKU by destination store, external product_id and sku_id. Preserve existing allocation ownership if a mapping or route subsequently changes.
6. Verify platform warehouse code against the selected route. Unknown or mismatched locations require an exception, not silent routing.
7. Run allocation changes in a transaction/savepoint so an invalid line preserves the fetched order plus an exception without partial stock mutation.
8. Replays and stale snapshots must be idempotent; verify two identical SKUs with one cancellation and one shipment, insufficient ATP, unverified balance, historical orders and route changes.
9. Keep physical returns dependent on warehouse receipt/count; platform refund/return status alone cannot restore physical stock.
10. Test-token persistence is implemented locally with explicit credential provenance and a local lease distinct from provider expiry. Get Token did not supply refresh or expiry in the observed UI. Customer OAuth remains a separate acceptance requirement.

## Delivery state

Current changes are local, not a release.
Real persistence captured 50 products / 77 listing variants and one order; repeated capture did not duplicate records. Catalog pagination uses 50 because the provider rejected limit 100 with E019.
A real catalog item was imported as draft JW product 53 / variant 94, SKU JW-SBX-LZ-MOUSE-001, zero physical stock, two images and sanitized description. Repeated import returned the same product.
Warehouse APIs returned nine active locations via result.module. Catalog and order warehouse codes must retain their distinct identities.
Consolidated local regression: 45 tests passed, 376 assertions across LazadaClient, LazadaCatalogService, LazadaOrderService, LazadaOAuthRoutes, LazadaTokenManager, LazadaTestConnection, MarketplaceDescription and MarketplaceImport. These automated tests do not prove live provider writes or customer OAuth.
Item lifecycle processing, warehouse-aware stock write/read-back, rendered UI acceptance and customer OAuth remain open. Existing stock transport requires verification of payload signing and warehouse fields before claiming operational multiwarehouse writes.

## Stock transport correction

Official API Explorer Get Parameters for /product/stock/sellable/update confirmed the payload parameter, XML Request/Product/Skus/Sku structure and per-warehouse WarehouseCode/SellableQuantity fields. No stock request was submitted from Explorer during this inspection.
Reference: https://open.lazada.com/apps/doc/doc?docId=121234&nodeId=42715
Local transport now sends signed form parameters including the XML payload to this absolute sellable-stock endpoint. The dispatcher passes the reviewed task platform_location_id. This replaces the previous unsigned raw business JSON body and unqualified Quantity update.
Regression evidence: LazadaClientTest plus MarketplaceStockSyncTaskTest: 14 tests passed, 53 assertions, including signature coverage, selected warehouse, ATP value, stale task rejection and dispatch-once behavior. Real provider write/read-back remains unverified; product-create transport is a separate unresolved path.

## Real stock transport and readback evidence

Subsequent local Laravel probes used the encrypted official_test credential:
- Empty SKU XML payload sent as signed form parameters to /product/stock/sellable/update returned HTTP 200, code 0. This establishes transport acceptance only; no stock was targeted.
- tests/manual/lazada-stock-readback-probe.php read item 14984118945 / SKU 128571056069 at dropshipping with sellableQuantity 20, submitted an absolute SellableQuantity 20 through LazadaClient, then fetched the item again and observed 20.
- This proves real authenticated stock endpoint acceptance and same-value readback for one explicitly selected warehouse. It does not prove a quantity transition, multiple warehouse isolation, WMS task dispatch, race handling or order reservation integration.
- The process-local write flag was enabled only inside the probe; no .env gate or production configuration was changed.

Real quantity transition subsequently verified: the same SKU dropshipping sellable stock read 20, an absolute write of 19 was accepted, and the following read returned 19. The restoration sequence encountered a provider/read exception; a separate read-only recovery invocation subsequently observed 20 and exited without issuing another write. Final original stock is verified restored. The item response exposed only dropshipping, so this does not prove isolation between two stocked warehouses. Diagnostic scripts now distinguish initial read, decrement, decrement readback, restore and restore readback stages. Do not automatically repeat a failed write sequence.

## Product transport correction

Product creation now serializes the reviewed JSON document into the signed payload form parameter instead of sending raw unsigned business JSON. The regression checks decoded payload equality and HMAC inclusion. LazadaClientTest passed 7 tests / 19 assertions; MarketplacePublishingTaskTest and LazadaReadClientTest passed 10 tests / 46 assertions.
Real /product/create diagnostic sent an empty Product object with no category, attributes or SKUs; HTTP 200 returned code 1 and E001: Parameter Attributes is mandatory. This demonstrates signed JSON payload reaching product validation, not successful creation. No product identity was returned. Actual complete product creation remains open.

## Full test product attempt

Read the existing test mouse category 10000664, attributes, two hosted images and package dimensions from the provider. Created a separate local draft and queued publishing task 1 using fixed request UUID 0c3bf093-0422-4dc5-8d3e-e6a8ba967c39 and SellerSku JW-SBX-LZ-WMS-20260908. Submitted zero stock and inactive status through MarketplacePublishingDispatchService. It raised PublishingResultUncertain, so no success or remote identity is claimed. The fixed request script refuses to dispatch an existing attempt again.
Subsequent read-only /products/get filtered by that SellerSku returned no matching SKU; total_products was absent. This is insufficient proof of non-creation and does not justify retrying. Keep task uncertain while inspecting provider validation/response evidence. No global publish gate or production configuration changed.

Authenticated Seller Center browser reconciliation: Manage Products -> All (2132 products) -> Product Name filter JW Sandbox WMS Mouse 20260908 returned "No product under this status or filter". The original Mouse item 14984118945 was visibly present with stock 20 before filtering, independently corroborating restoration. No seller product was edited during this browser check. The All search adds evidence of current absence but cannot recover the original creation response or rule out delayed processing; task 1 remains uncertain.
The Seller Center banner also lists MY campaign restrictions on Add/Edit Product for 2026-09-08 19:30-20:30 and 23:30-2026-09-09 00:30 local time. This banner alone is not evidence that the earlier request occurred inside those windows or failed for that reason.

Category discovery now supports signed /category/attributes/get reads. Real category 10000664 returned 39 attributes, with mandatory brand, package_width, package_height, package_length, package_weight, price and SellerSku. These names are present in task 1 payload; missing one of these named fields is not established as its failure cause. Brand options were not returned in this response, so allowed brand membership remains unverified. Read-client regression passed 3 tests / 9 assertions. Manual reproduction: tests/manual/lazada-category-probe.php (read-only).

## Lazada item lifecycle normalization

LazadaInventoryPlan now normalizes each order_item_id separately with one unit, exact provider warehouse identity, fulfillment checks and historical cutoff. Pending/packed/ready-to-ship retain reservations; shipped/delivered consume; canceled releases. Returned status does not authorize physical restock. Unknown states require review. Official references: docId 121329 (item-level cancellation), 121328 (partial packing), 121233 (inventory accounting).
Five parser tests passed with 11 assertions. A read-only run over persisted real order 512827127743475 produced two separate one-unit reservation plans for item IDs 512827127843475 and 512827127943475, both SKU 128558080469 at MY4NA1T7CK-WH-10007. No stock was changed. Database executor, per-item terminal tombstones, route checks, activation and replay integration remain to be connected; the parser alone is not WMS completion.

Added marketplace_inventory_items migration and LazadaInventoryItemState persistence boundary. It retains cancellation tombstones even without allocation, separates item identities, rejects terminal reversal/warehouse identity drift, and checks allocation ownership/status when attached. Local migration applied. Parser plus item-state tests passed 7 tests / 16 assertions. Still not wired into order ingestion or physical stock mutation; the executor must call state recording inside the same transaction as allocation changes and enforce activation/routing.

LazadaInventoryExecutor now applies normalized items atomically through StockAllocationService and stores item states in the same transaction. New allocations require the exact store platform warehouse route and SKU mapping; existing allocations retain their original balance. Database regression verifies two reservations, one cancellation plus one shipment, idempotent replay, and rollback of the first allocation when the second item has a warehouse mismatch. Ingestion/policy wiring and real order execution remain pending; this internal executor is not automatically enabled by order reads.

Order-ingestion wiring subsequently implemented: saveLazada invokes the shared inventory processor for new/updated snapshots and replays of the latest stored snapshot. Lazada processing requires the existing global accounting flag plus enabled/reconciled per-store policy and cutoff. Per-item executor exceptions retain the fetched order and record the existing inventory exception envelope. Mixed item results do not set an order-wide terminal state. Regression across Lazada ingestion/order service/execution and existing Shopee ingestion passed 20 tests / 121 assertions. No runtime accounting policy was enabled during this change; real order allocation acceptance remains pending.

Local MySQL runtime probe now exercised persisted real order 512827127743475 through MarketplaceOrderInventoryService twice with a temporary synthetic physical stock fixture (10 physical, 2 safety, initial ATP 8) and exact platform warehouse route MY4NA1T7CK-WH-10007. Result: two distinct one-unit reservations, physical 10, reserved 2, compatibility availability 6, no inventory exception. Changes were enclosed in an outer transaction and rolled back; original order allocation count was verified restored. This proves the saved real payload integrates with MySQL allocation and replay, not a live warehouse count or real platform cancellation/shipment transition. Script: tests/manual/lazada-real-order-wms-probe.php. No provider calls or persistent activation performed by this probe.

### Listing reconciliation follow-up
Seller-SKU filtered GET returned an empty data object, not an explicit zero total. Full read-only catalog scan inspected 1100 products without the test SKU, then failed at offset 1100. This is incomplete evidence and does not establish non-creation. No create retry was sent; task remains uncertain. The diagnostic script now reports scanned count and failed offset without credentials.


### Catalog timeout isolation
Replayed offset 1100 directly: HTTP 200 with provider code ServiceTimeout. A 25-product request also timed out. Requests for 1 and 10 products at that same offset succeeded, both reporting total 2132. This identifies a provider batch-read timeout, not a local offset validator failure. The read-only reconciliation script now supports bounded page size/start/page-count and reports only HTTP status/provider code. Full absence proof remains incomplete; no publishing retry was performed.


### Remaining catalog traversal completed
Read-only continuation at offset 1120 reached the end successfully: 1012 products read, reported total 2132, exact test SellerSku matches empty. Earlier traversals covered offsets 0-1099 and 1100-1119. Together these traversals cover the reported catalog range, but are not an atomic snapshot of the shared test seller. ServiceTimeout was bypassed by bounded recursive batch splitting down to a single product. No create retry or task status mutation was made; provider request-result evidence is still required to resolve the uncertain original attempt.


### Original create request located in official API Access Log
Chrome official console, JW Studio ERP (139209), Malaysia, /product/create, 2026-09-08: the 15:11:21 request contains the exact JW-SBX-LZ-WMS-20260908 SellerSku and returned ISP code 4221 with message E500: Create product failed. Request ID: 0bafca0117888514807047433. No created item identity is reported. This establishes a provider-side failed response rather than a client login failure. It does not by itself prove there were no partial effects, so the uncertain task is not automatically requeued. Earlier 15:09:09 and 15:08:55 empty-product probes returned code 1 / E001 Attributes mandatory and are separate requests. Raw logs include credentials and are intentionally not copied into this document.


### Controlled No Brand creation attempt
Local official-test lease was expired; revalidated the existing encrypted test credential through LazadaTestConnectionService against the real seller before continuing (store 3). New distinct task 2, SellerSku JW-SBX-LZ-NOBRAND-20260908, uses No Brand instead of source No band, zero quantity and inactive status. Provider returned HTTP 200, code 4221, request ID 2151f12817888540134216584. Changing brand did not resolve the provider failure. Original task 1 was not replayed; task 2 remains uncertain. No successful creation is claimed. Scripts retain stable request UUIDs to prevent blind repeat writes.


### Consolidated local regression and MySQL replay
Command: php artisan test --filter='Lazada|Shopee|Marketplace|Warehouse|StockAllocation'. Result: 262 passed, 1594 assertions, exit 0. Manual lazada-real-order-wms-probe.php rerun against local MySQL with saved real provider order and synthetic stock fixture: physical 10, reserved 2, available 6; two distinct order-item allocations each quantity 1; repeated processing passed, rollback verified. This is not evidence of a fresh platform shipment or an actual warehouse count. OAuth customer flow and real successful Lazada product creation remain outstanding.

