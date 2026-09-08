# Marketplace MVP — approved boundary, 2026-09-07

Authority: user's ten questionnaire answers and subsequent scope confirmation. This supersedes broader implementation sequencing for MVP; it does not claim previously unfinished capabilities are complete.

## Fresh-task source and goal clarification

The user re-supplied `jw-ecommerce-dashboard (32).zip` as the latest visual source. Direct archive SHA256: `9285db80782f060dcf20f854ebaceeb44c96acb49642a8000f33d31d181be6d2`. Preserve existing accepted integrations; supplying the archive is not permission for a blanket official-route replacement. The archive's AI confirmation modal matches the extracted ZIP 32 component. Its inventory page still uses platform-keyed channel relationships, with no inspected multi-store reconciliation/activation flow. Shared AI Engine configuration and real Marketplace inference are also absent. These gaps are confirmed from the archive, not inferred solely from extracted files.

The updated overall Goal prioritizes Shopee closure, then Lazada, then TikTok/Shopify only along an approved extension route. Controlled pushes/fulfillment remain overall roadmap work; the earlier MVP deferrals are not evidence those broader goals are complete. Real listing/shipping/inventory overwrites and GitHub/Hostinger synchronization still require the applicable authorization. Source re-supply did not grant those actions.

Shared AI Engine implementation design for review: `docs/superpowers/specs/2026-09-07-marketplace-ai-suggestions-design.md`. This is a proposed architecture, not implemented capability. The pending Lazada application ownership question need not block safe Shopee work.

### Automatic scheduling concurrency evidence

Added and ran `php tests/manual/marketplace-automatic-orders-mysql-race.php`: two independent PHP processes compete for the same synthetic connected Shopee store in a disposable local MySQL schema. They produce one automatic 30-day run and one database queue job; exactly one process enqueues and the other skips. A subsequent invocation also skips. The queue job remains unconsumed, and stray HTTP requests are prohibited. The disposable schema is removed afterward; no business migrations, provider calls, stock changes or host cron operations occur.

Fresh `MarketplaceAutomaticOrders|RunMarketplaceCron` regression passed five tests / 27 assertions; diff check passed. This proves local scheduling deduplication under contention, not crash recovery between run commit and queue insertion, real automatic provider fetching, or host scheduler acceptance. The existing after-commit dispatch boundary still requires crash/dispatch-failure handling assessment before automatic operation is enabled.

### Automatic initial dispatch atomicity repair

Failure injection with actual SQLite commits reproduced an orphan automatic run: a missing queue table caused dispatch to fail after the run had committed, leaving one queued run and no job. `MarketplaceAutomaticOrders` now requires the resolved database queue to share the application connection and inserts the job before transaction commit. Database isolation keeps workers from observing an uncommitted run/job. Queue failure rolls back the run; restoring the table allows scheduling again. Sync and separate-connection queues are rejected before scheduling.

Fresh full `Lazada|Marketplace|Shopee|StockAllocation|CheckoutCommerceRules` regression: 216 tests / 1,313 assertions passed. The real MySQL two-process scheduling test also passed after the repair. PHP syntax and diff checks passed. No provider requests, business migrations, configuration enablement or deployment occurred.

This closes the initial automatic-dispatch failure gap only. Source inspection also found after-commit dispatch in manual order fetch/retry and `PullShopeeOrders` page continuation. Continuation commits its page counter before dispatch, so its existing expected-page failure guard may miss a post-commit queue failure. These paths need corresponding failure injection and repair before claiming end-to-end scheduling reliability; no broad scheduler closure is claimed.

### Order continuation dispatch repair

Real-commit failure injection reproduced continuation stuck as queued after a queue insert failure. `PullShopeeOrders` now queues the next page before committing page progress and requires the configured queue to use the application database connection. Failed insertion rolls back cursor/page/count changes, allowing the existing failure handler to mark the original page failed. Restoring the queue and retrying the original page advances once and creates one next-page job. Provider reads in this test are mocked; no real platform result is claimed.

Fresh broad regression passed 217 tests / 1,322 assertions before adding the final recovery assertions. The final dispatch suite passed four tests / 35 assertions including recovery. Diff check passed. Manual fetch/retry initial dispatch remains to repair; no deployment or live queue consumption occurred.

### Manual fetch/retry dispatch repair and account authorization

Authenticated HTTP failure injection reproduced both remaining order entry defects: failed fetch retained an orphan queued run; failed retry changed the original failed run to queued and erased its error. Both controller dispatches now insert before the transaction commit with the same-database queue requirement. Tests restore the queue and verify successful submission with exactly one run and one job. Fresh broad regression passed 219 tests / 1,344 assertions; diff check passed. No business database or remote runtime was mutated.

The user explicitly authorized Chrome login and API use of their Shopee/Lazada test accounts and supplied official documentation/console links. Chrome confirmed the Shopee Test Order page is authenticated and shows two READY_TO_SHIP orders in local-MY shop 227482706; Pickup/Deliver controls are disabled. Lazada service-provider home is authenticated as JW Tech Solutions. This proves console access, not a completed Ecommerce OAuth connection or that every Lazada endpoint/account is sandbox-isolated. No secret was printed, callback changed or platform mutation submitted in this check.

Provided Lazada API link `/service/market/subs/query` is documented as Service Market subscription lookup, not seller catalog/orders. Use the documented Seller/Product/Order categories for subsequent integration; do not confuse its No Authorization Required label with seller API permissions. Existing MVP boundaries and explicit approval requirements for production writes/releases remain in effect.

## Approved scope

- Shopee and Lazada, multiple stores per platform; explicit target store for operations.
- Catalog fetch, variants, server pagination/search, manual same-store one-to-one mapping, cross-store reuse, reviewed single/batch import and conflict checks.
- Imports create drafts with zero physical stock. Linking existing variants must not reset their inventory or price.
- No new listing publishing or product title/image/price updates in MVP. Merchants manage these in Seller Center.
- Order ingestion, details, platform status and JW SKU association. Shipping, AWB and packing actions remain in Seller Center.
- JW inventory ownership, marketplace reservations/releases/shipping deductions and opt-in per-store available-stock pushes after reconciliation.
- Initial order window: 30 days subject to platform constraints; subsequent incremental jobs and manual refresh.
- LLM mapping suggestions required, with employee confirmation and authoritative conflict revalidation.
- TikTok/Shopify, refunds/disputes, promotions, coupons, advertising, chat, settlements, reviews, bundles, preorders and special cross-border products deferred.

Inventory pushes are explicitly distinct from excluded product-data updates. Reading platform fulfillment/cancellation transitions is required even though JW fulfillment actions are excluded.

## Delivery gates

Mode: OPERATIONAL_CLOSURE. Laravel/MySQL own business facts. Preserve approved Google AI Studio presentation (ZIP 30 lifecycle; accepted ZIP 32 import journeys). Credentials remain server-side; tokens encrypted. No simulation is evidence of real provider acceptance.

Local development and acceptance first; coherent phase approval precedes GitHub/Hostinger synchronization. Queue/cron acceptance is last, but mandatory before claiming automatic operation. Existing unfinished publishing code remains dormant and must not be presented as an MVP capability.

## Current inspected inventory boundary

Read directly from the working tree on 2026-09-07:

- `app/Services/CheckoutService.php`: checkout locks variants and immediately decrements `product_variants.stock`; cancellation increments stock.
- `app/Services/Returns/ReturnRequestService.php`: received returns increment variant stock.
- `app/Services/Marketplace/MarketplaceOrderSnapshotService.php`: versioned encrypted order snapshots; no inventory effects.
- `app/Services/Marketplace/MarketplaceOrderDetailService.php`: current mapping is advisory; reading details deliberately does not reserve inventory.

Consequently, existing `stock` cannot simply be relabeled physical-on-hand and have reservations subtracted again. That would double-count existing self-operated orders. A shared inventory accounting contract and legacy reconciliation must precede enabling marketplace writes. Historical orders must not be replayed blindly as new deductions.

## Next inventory contract and evidence requirements

Status: BACKEND_CONTRACT_GAP; no stock-writing closure claimed.

Producer: accepted marketplace order versions and explicit operator reconciliation/enablement. Consumer: shared JW availability and opt-in linked store stock updates. Ownership: system-managed calculations, operator-managed activation. MySQL owner for reservation/event accounting is not yet implemented.

1. Inventory service must define physical, reserved, available and safety stock consistently with checkout, cancellation and returns; retain existing historical facts.
2. Persist immutable order-line SKU associations when inventory effects begin. Later mapping changes cannot move historical reservations to another SKU.
3. Deduplicate inventory effects by store/order/line/lifecycle event; lock shared SKU rows for cross-store concurrency.
4. Unknown, unlinked, malformed or insufficient-stock orders become actionable exceptions, not guessed deductions.
5. Initial 30-day history requires a reconciliation boundary; do not deduct already-accounted historical shipments or release reservations never created.
6. Cancellation releases only owned outstanding reservations; shipping consumes once. Stale statuses and repeated notifications cannot reverse terminal effects.
7. Push jobs read current authoritative availability, coalesce superseded updates and stop for disconnected/invalid/paused stores. Log confirmed provider outcomes without secrets.
8. Verify checkout competing with marketplace reservations, two-store concurrent orders, cancellation replay, delayed shipping, returns, remapping, initial history, failed pushes and recovery using MySQL plus real Sandbox acceptance.

Next implementation batch: establish and test the shared accounting/legacy compatibility contract before wiring order ingestion to inventory mutation. No UI redesign or live inventory overwrite is authorized by this document.

## Local allocation accounting checkpoint

Implemented `StockAllocationService` and additive migration `2026_09_07_000004_create_stock_allocations.php`. The compatibility column `product_variants.stock` remains unallocated availability. A reservation removes availability once; consumption records the shipment without another decrement; cancellation returns only a held allocation. Immutable owner/line identity and SKU/quantity prevent silent remapping or replay reopening a terminal allocation. Events are transactional with stock changes.

This is not a new claim that legacy stock is physical-on-hand. Physical reconciliation, safety-stock policy, historical cutoff, order ingestion activation, provider stock pushes and merchant controls remain incomplete. No production caller is wired to the allocation service yet; no migration was run on the development business database or Hostinger.

Fresh local evidence:

- `php artisan test --compact --filter='StockAllocation|CheckoutCommerceRules|Marketplace|Shopee'`: 179 tests, 1,078 assertions passed.
- `php tests/manual/stock-allocation-mysql-race.php compete`: two real processes requested seven each from ten; one allocation, stock three, one event.
- `php tests/manual/stock-allocation-mysql-race.php duplicate`: duplicate simultaneous requests yielded one allocation, stock three, one event.
- Both MySQL scripts used synthetic data in independently generated local schemas and removed those schemas on completion. They do not prove platform acceptance.
- Checkout integration tests prove reserved marketplace units are unavailable to checkout, cancellation makes them available again, and cancelling a self-operated order does not release another order's reservation.

Next: connect versioned marketplace order ingestion through per-store activation/reconciliation and persisted line identity, retaining explicit exceptions for historical or unmapped lines. Before any stock push, reconcile physical stock and verify actual Sandbox lifecycle behavior.

## Inventory UI ownership and Lazada authorization clarification

User explicitly confirmed `/admin/marketplace/inventory` (Inventory & WMS) owns inventory management. Do not create a competing inventory entry. Lazada application 139209 may be accessed using the authorized Chrome session and its callback may be configured; check existing Dashboard callback compatibility first. Application Testing status alone does not establish that all seller operations are sandbox operations.

Fresh source inspection found `resources/admin/src/marketplace/context/AdminContext.tsx` always supplies an empty `marketplaceInventory` array; inventory actions only show unavailable notices. `MarketplaceWorkspace.tsx` also always renders a stale global Open API not connected notice on this surface. Therefore the empty page does not prove a zero-stock database.

The existing inventory component's platform-keyed `channelMappings` cannot represent multiple stores on the same platform. Per-store activation/reconciliation and multi-store matrix interactions are an ADMIN_UI_GAP pending source completion; backend implementation may continue with writes disabled. Source-grounded brief: `docs/google-ai-studio/prompts/2026-09-07-marketplace-inventory-multistore.md`. No replacement screen or invented store-level controls were implemented.

## Order-to-allocation integration checkpoint

Local snapshot ingestion now calls `MarketplaceOrderInventoryService`, gated by `MARKETPLACE_INVENTORY_ACCOUNTING_ENABLED` (default false) plus an enabled, reconciled per-store policy and order-creation cutoff. Policies currently have no merchant activation endpoint; do not enable by editing records as an acceptance shortcut. No development or remote business migrations/configuration were applied.

The service uses the latest persisted snapshot, frozen allocation SKU identity and a transaction savepoint per order. An unmapped or insufficient line rolls back all inventory effects while retaining the order and a sanitized exception code. Historical orders are recorded for reconciliation. Cancellation releases held allocations only; persisted terminal state prevents cancellation-before-reservation from reopening stock. Paused/unreconciled policies have no stock effect.

Fresh evidence: `php artisan test --compact --filter='StockAllocation|CheckoutCommerceRules|Marketplace|Shopee'` passed 187 tests / 1,101 assertions; `git diff --check` passed. The new integration is covered by synthetic SQLite feature tests; the earlier MySQL allocation races do not establish concurrency proof for the whole ingestion transaction. No real Shopee inventory lifecycle or stock push was performed. Provider state semantics, reconciliation/activation UX, ingestion-level MySQL concurrency, exception presentation and actual Sandbox acceptance remain release gates.

### Follow-up: ingestion-level MySQL competition

`php tests/manual/stock-allocation-mysql-race.php order-compete` passed: two real processes, two synthetic stores mapping one shared SKU, two persisted order snapshots, one reserved result and one insufficient-stock exception, one allocation/event and available stock three from ten. `order-duplicate` passed: two processes ingest the same store/order and produce one order, one allocation/event and available stock three. Both disposable schemas were removed; the business database was untouched.

An additional failing-then-passing regression verifies that adding a line after inventory allocation cannot silently allocate more stock. The immutable line-set check now blocks both additions and removals. Targeted `MarketplaceInventoryIngestion|StockAllocation|CheckoutCommerceRules` suite passed 34 tests / 132 assertions and `git diff --check` passed. These results improve local concurrency evidence, not provider/live acceptance. Activation, historical reconciliation, inventory UI integration and provider lifecycle verification remain open.

### Inventory exception read contract

Added protected `GET /admin/api/marketplace/inventory/exceptions` under the existing Marketplace read gate. Filters: `storeId`, `error`, `q` (external order reference), `page`, `per_page` (1–100, default 25). Stable sorting uses last check time then identity. Response is paginated and explicitly projects order reference, platform/store, current connection/platform states, error code and checked version/time; no encrypted payload or recipient data is selected. Reads do not retry or modify allocations.

`MarketplaceInventoryIngestionTest`: 10 tests / 44 assertions passed, including unauthenticated/non-admin denial, pagination, store/error/search filters, invalid parameter rejection, private-payload exclusion and unchanged stock. The existing customer/web-session middleware intentionally rejects non-admin web sessions with 401. Inventory & WMS exception UI remains unbound pending approved source; this API is not a UI closure claim.

### Inventory matrix read contract

Added protected `GET /admin/api/marketplace/inventory` with `storeId`, `q`, `page`, `per_page` (1–100, default 25). Reads JW variants, legacy unallocated `available_stock`, tracked `marketplace_reserved_stock` and an array of all mapped store relationships, including multiple stores of the same platform and disconnected historical links. Store filtering selects relevant JW SKUs but does not hide their other store relationships. Reservation totals remain SKU-global.

`physical_stock` and `safety_stock` are intentionally null and `reconciliation_required` true until their authoritative configuration/reconciliation implementation exists. `stock_push_enabled` remains false. These are not completed inventory settings. No currency/warehouse/stock figures are fabricated from source demos. The page-sized query aggregates allocations and mappings only for returned variants.

Fresh regression: 191 tests / 1,149 assertions passed for `StockAllocation|CheckoutCommerceRules|Marketplace|Shopee`; `git diff --check` passed. Includes 305-SKU pagination (100 per page, five results on page four), two Shopee stores for one SKU, disconnected-link preservation and unchanged inventory on reads. No browser or actual provider acceptance claimed; no deployment or business-database migration performed.

### Official order-detail documentation review

Read in authenticated Chrome on 2026-09-07: https://open.shopee.com/documents/v2/v2.order.get_order_detail?module=94&type=1 . The response documents local/platform fulfillment, advance fulfillment, component mappings and item-level active/cancelled/returned quantities. The inventory implementation now explicitly requests `fulfillment_flag` and refuses automatic JW allocation for missing/non-local fulfillment, advance fulfillment, component bundles or inconsistent active/cancelled/returned quantities. These become `unsupported_fulfillment` or `unsupported_order_composition` exceptions rather than silently consuming warehouse stock. Manual resolution/presentation is still pending; this does not implement partial cancellation or returns.

Source also distinguishes `line_item_id` and package item locations; full order-state semantics and package/warehouse handling still need verification. Reading this endpoint alone does not establish all status transitions. Fresh test-first regression passed 193 tests / 1,162 assertions for the Marketplace/Shopee/Checkout/allocation filter; `git diff --check` passed. No platform writes or deployment occurred.

### Thirty-day order fetch windows

Official Chrome verification of https://open.shopee.com/documents/v2/v2.order.get_order_list?module=94&type=1 confirms a maximum 15-day interval and create_time/update_time query modes. Existing code conservatively retains at-most-14-day provider calls. The protected fetch endpoint now accepts up to 30 days, stores the original request bounds, and the queue job advances through windows only after exhausting the current cursor. Adjacent windows overlap a boundary second deliberately; persisted order identity/version handles duplicates. Existing runs without original bounds remain single-window compatible.

Test proves a 30-day request continues across three windows, honors an intermediate pagination cursor, accumulates counts and reports success only after the final window. Provider responses are mocked for orchestration tests, not claimed as actual API acceptance. Fresh regression passed 194 tests / 1,174 assertions; diff check passed. Existing UI still requests seven days and automatic incremental scheduling is not implemented by this checkpoint. No business migration, cron execution or deployment occurred.

### Thirty-day merchant action follow-up

OperationalOrders now submits exactly 30 days for one explicitly selected eligible store; the existing button/tooltip say 30 days without DOM/class changes. Test-first component check observed the old seven-day request fail, then passed after binding correction. Four OperationalOrders/read-task component tests passed. Vite build passed (existing large-chunk warning; local main asset `main-C2hKKs9t.js`). Authenticated Chrome at `http://127.0.0.1:8500/admin/marketplace/oms` displayed the 30-day button after data loading, no fetchable local stores and the correctly disabled action; screenshot emitted in conversation. This is real local rendering but not a real 30-day provider fetch. No fake connected store was created. Automatic incremental scheduling and remote acceptance remain pending; no deployment occurred.

### Automatic incremental order scheduling — local implementation

`MarketplaceAutomaticOrders` is invoked by the existing `marketplace:cron` command before consuming a queued task. New `MARKETPLACE_AUTOMATIC_ORDERS_ENABLED` defaults false and no environment setting was enabled. When enabled, eligible connected Shopee stores in the configured environment receive an initial 30-day update-time pull. Automatic-origin runs provide a separate durable progress boundary from manual queries. Successful runs permit subsequent incrementals with a five-minute overlap; failed automatic runs require retry and are not skipped. Existing active manual/automatic work prevents a duplicate run. Large catch-up periods are bounded to 30-day requests and the existing job splits provider windows further.

New migration adds run origin and an index; no business DB migrations were run. Tests cover initial range, duplicate prevention, failed-run halt, successful resume, poll interval, disconnect and default disabled behavior. Fresh Marketplace/Shopee/Checkout/allocation regression passed 196 tests / 1,187 assertions; diff check passed. Cron process invocation on Hostinger, automatic real-provider runs, concurrent scheduler acceptance and per-store merchant control remain unverified. Local tests do not establish that the host cron is configured or running.
