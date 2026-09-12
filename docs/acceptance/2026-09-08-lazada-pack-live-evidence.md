# Lazada official test seller packing

## Consolidated regression after label and RTS integration

`php artisan test --filter="Lazada|Shopee|Marketplace|Warehouse|StockAllocation"` passed 286 tests / 1695 assertions. This is automated regression evidence, not additional live-provider acceptance.

`npm run lint` failed: marketplace32 ImportProductReviewModal test has an incomplete Product fixture cast; marketplace33 and marketplace34 preview type indexes reference missing sibling modules (rma for 33, plus payment, oscommerceSettings, support, reports, aiAutoReply, promotions, storefrontHeader and notifications). These failures must be resolved before claiming the full frontend type check passes. The previous Vite build passing does not establish TypeScript correctness.

## Actual RTS confirmation

After the RTS order snapshot was persisted, the local MySQL WMS probe processed that real provider order twice using temporary synthetic stock and routing. The result remained reserved: physical 10, reserved 2, safety 2, available 6, with two distinct one-unit allocations. Transaction rollback was verified. This validates the RTS reservation boundary against the actual test order; it does not certify physical stock counts or activate live inventory accounting.

The local manual RTS command used official_test credentials for seller 300163725140, order 512827127743475 and package FP021111611256684. Attempt 2 succeeded with package item_err_code 0. A separate GetOrderItems read confirmed both items 512827127843475 and 512827127943475 are ready_to_ship and retain the expected package. Re-running the stable request returned succeeded, resent=false. Two subsequent order pulls retained one database order. No persistent fulfillment switch or deployment was performed. This proves test-seller RTS, not physical courier handover/delivery or WMS stock consumption. Authenticated RTS UI submission remains outstanding.

Local runtime: C:/xampp/htdocs/jwstudio-ecommerce. No deployment performed.

- Official test credential revalidated against provider; store 3, seller 300163725140.
- Order 512827127743475; selected items 512827127843475 and 512827127943475.
- The dispatcher refreshed item eligibility and platform-default TFS allocation before sending the pack request.
- Actual provider response confirmed both items with item code 0, package FP021111611256684, tracking 649171064406.
- Fulfillment attempt 1 persisted as succeeded with both item results.
- Re-running the fixed-identity manual command returned succeeded, resent=false; it did not dispatch again.
- Subsequent order pull executed twice: one order returned each time, one database order before and after replay.
- Related automated verification: 6 tests, 23 assertions passed for dispatch, attempts, preflight, client, and result parsing.

This proves packing with the official borrowed test seller, not customer OAuth completion, RTS, label retrieval, carrier delivery, or complete WMS acceptance. The fulfillment flag was enabled only inside this local manual process. No persistent configuration switch was enabled.

Reproduction: `php tests/manual/lazada-test-order-pack.php` uses a stable request identity and never automatically resends a claimed attempt.

## Provider readback and label response

`lazada-packed-order-verify.php` independently queried GetOrderItems and confirmed both selected item IDs are packed with the same package ID stored in the attempt.

PrintAWB was called through LazadaClient with the officially documented getDocumentReq wrapper. The real response contained file, pdf_url, and doc_type=PDF. Its URL host was sellercenter.lazada.com.my, with an /oss/galaxy/proxy/logistics-waybill-oss-sg.oss-ap-southeast-1.aliyuncs.com/PDF/ path. Signed query credentials were not logged. This proves label URL generation only; PDF downloading, content validation, private storage, UI delivery and RTS remain outstanding.

Official contract: https://open.lazada.com/apps/doc/api?path=%2Forder%2Fpackage%2Fdocument%2Fget

## Actual PDF download

## Authenticated local browser download

At 16:30 MY time the authenticated localhost OMS drawer showed packed status, MYR amounts, and Available for download. Clicking the existing Print AWB Label button changed feedback to Downloading labels then PDF download requested, restoring the button. Windows Downloads contained lazada-FP021111611256684.pdf, 129969 bytes, timestamp 2026-09-08 16:30:08. This confirms an actual saved browser download through the protected order endpoint, beyond the prior manual service call. The existing drawer rendering regression test passed. The PDF has not yet been visually inspected. RTS remains pending.

The local LazadaLabelPdf downloader fetched a fresh signed provider URL successfully: 129969 bytes, SHA-256 4df8353a4d112a0dd51dcc97fef4e33b339dae829a80d790deb999086c61818c. It checked the PDF header and EOF marker, restricted HTTPS host/proxy path, disabled redirects, and bounded transport size. This is structural byte validation, not a PDF parser or visual rendering check. No recipient content or signed query was printed. Two tests cover accepted PDF bytes, rejected HTML, and rejection of foreign URLs before network access. Private persistent storage and authenticated UI delivery remain pending.
# Packing readback recovery — local follow-up

## Warehouse-aware reconciliation version

Reconciliation fingerprints now include inventory configuration, the store fulfillment route and warehouse record, and physical balances/pool identities for mapped or allocated variants. Previously changing the route or physical count without changing legacy availability left the fingerprint unchanged. Regression reproduced that failure, then passed with 2 tests / 12 assertions. This extends change detection only: the eventual activation transaction must still align locks with warehouse/mode writers and validate coverage, cutoff, actor and stock readiness; fingerprint equality alone is not activation authority.

## Real order SKU association

`tests/manual/lazada-order-sku-import.php` obtained item 14971376157 / SKU 128558080469 through real signed product detail read because it was absent from the previously captured catalog page. It captured only that selected SKU and imported it with the existing audited import service as JW-SBX-LZ-SHIRT-001, variant 102. Repeated import returned 102. Re-running the real reconciliation read probe resolved all four outstanding lines across the three local orders to variant 102 with available stock zero. Platform snapshot quantity was not promoted to physical warehouse stock. No platform write occurred.

The earlier temporary WMS fixture probe intentionally refuses to replace an existing mapping and therefore now requires a separately isolated fixture if rerun; do not remove this real association just to rerun that probe.

## Lazada reconciliation read model

Fixed reconciliation demand/page dispatch that previously assumed Shopee statuses and item_list for every store. Lazada now reuses its item lifecycle normalizer, preserves separate order_item_id demand keys, resolves mappings using product_id:sku_id, and exposes warehouse identity and per-line demand classification. It remains read-only; activation_ready remains false. The store activation write service is not present in the inspected application paths and must still be implemented with explicit cutoff/review protection.

Reconciliation regression passed 57 tests / 394 assertions. `tests/manual/lazada-reconciliation-read-probe.php` checked all three saved real Lazada orders: four separate outstanding one-unit lines, all at MY4NA1T7CK-WH-10007, all correctly reported unmapped. No allocations or platform writes were made by this probe. This identifies SKU association plus reviewed activation as the next integration boundary.

## Complete localhost browser fulfillment — order 512830544343475

On 2026-09-08 the authenticated JW owner used localhost:8500 OMS: Pack Order succeeded (attempt 3); refresh displayed packed and enabled the PDF/Dispatch actions; Print AWB Label saved Downloads/lazada-FP021111606801576.pdf (131779 bytes); Dispatch succeeded (RTS attempt 4). Independent real `/order/items/get` read via `tests/manual/lazada-browser-order-verify.php` confirmed item 512830544443475 ready_to_ship and package FP021111606801576, matching the persisted pack result. No manual dispatch script was used for this order.

The temporary local fulfillment env gate was returned to false after the test. No production changes. The order is unmapped and no WMS allocation was claimed. Full combined WMS/fulfillment activation is still outstanding.

Browser testing exposed naive DB task timestamps being interpreted as browser-local time (09:08 instead of 17:08 MY). Order-detail serialization now emits ISO timestamps with the application timezone offset. Regression: LazadaOrderDetailTest 2 passed / 19 assertions. Reopening the real drawer showed PACK 05:08 PM and RTS 05:10 PM, agreeing with the provider snapshot time. UI structure/classes were unchanged.

## Fresh pending orders available

Authenticated Seller Center (Newest Order Created / To Pack) showed order 512830544343475 at 2026-09-08 16:56 MY and 509552044443475 at 17:04 MY, each one Plain T-Shirt item. `tests/manual/lazada-capture-pending-test-orders.php` performed a real Laravel pull for 16:50–17:10 and persisted exactly two pending orders as local IDs 2 and 3. These are candidates for full drawer-driven fulfillment acceptance. Their creation mechanism is not established by this observation; do not attribute them to the unsuccessful Create Test Case submission. No fulfillment write was issued in this capture.

## Two-warehouse route ownership probe

Executed `tests/manual/lazada-real-order-wms-probe.php` against local MySQL after expanding it to two synthetic physical warehouses. The saved real provider order remains ready_to_ship. Its two separate item IDs reserved one unit each in the first warehouse; the store route was then changed to the second warehouse and the same order processed again. Both allocations retained the first balance, physical stock stayed 10, reservations stayed 2, ATP stayed 6 (safety 2), and the second warehouse retained zero reservations. Probe passed and allocation rollback was verified. This is real saved order data with synthetic local stock, not live warehouse count or production activation evidence. No provider stock write was performed.

Added LazadaPackingReconciliation and connected the existing order-scoped reconciliation endpoint and drawer action to pack attempts as well as RTS. Only uncertain/partial attempts qualify. Fresh provider item reads must match the order identity, contain every selected item exactly once, and provide valid package IDs with packed or later fulfillment states. Missing/pending items retain the protected attempt. No pack request is resent. Confirmed recovery refreshes the order snapshot separately; snapshot failure does not erase confirmation.

Verification: backend packing reconciliation, RTS endpoint and RTS dispatch tests passed (5 tests, 40 assertions); drawer interaction tests passed (6 tests), covering pack and RTS confirmation/unconfirmed responses and double-click suppression. These are simulated-provider regression results, not a real lost-response recovery claim. TypeScript checking passed. Local only, no release.

Official Create Test Case browser inspection exposed ORDER / Normal item 3021651369. Submitted test name JW-WMS-E2E-20260908-B once; the modal remained open and the subsequently queried list remained empty. No successful order creation is claimed and no blind repeat was submitted. New full browser pack-to-RTS acceptance remains pending.
