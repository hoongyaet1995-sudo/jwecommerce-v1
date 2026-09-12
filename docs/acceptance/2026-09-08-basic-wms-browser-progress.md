# Basic WMS local browser evidence

Observed in authenticated Chrome at http://127.0.0.1:8500/admin/marketplace/inventory-preview-33?view=zip36 on 2026-09-08.

- Basic operations enabled; configuration reads Independent Warehouses and 2 warehouses.
- 62 JW SKUs loaded; recorded physical total 30; 60 unverified SKUs.
- Aggregate ATP displays Unknown rather than a fabricated zero.
- Local initial configuration receipt 08548a2e-1ddb-44f2-904d-084280eb6bb6, version 4, actor 2 was created through the service, not browser submission. Script compared balances, legacy stock/version and allocations before/after with no changes.
- Runtime readback: basic_wms true, warehouse transfers false, stock_sync false, accounting_enabled 0.

## Browser manual operations, 11:20–11:22 UTC

Chrome loaded bundle main-BWMFjVtr.js. For explicitly classified test SKU JW-SBX-LZ-SHIRT-001, warehouse QA-ZIP36-CYB, the normal adjustment form, server review and employee acknowledgment completed:

- Inbound +5: physical 20 to 25, Basic available 18 to 23; movement 4, actor 2, balance version 3.
- Outbound -3: physical 25 to 22, Basic available 23 to 20; movement 5, actor 2, balance version 4.
- Both operations refreshed the SKU matrix automatically after confirmation.
- Read-only database verification through `tests/manual/basic-wms-browser-readback.php` confirmed both reasons, timestamps, actor, quantities and ledger rows. Accounting and stock sync remain disabled.
- Found input-stage Current quantity incorrectly dependent on a not-yet-created review; corrected it to use the selected balance. Advanced ATP wording and Basic projection in the operation modal remain to be integrated.

This is partial authenticated browser and database evidence, not complete acceptance. Count/adjustment/overdraw/retry, controlled sandbox writes, remaining source placeholder removal, full Basic WMS gate coverage and online release remain outstanding. Initial configuration was service-driven, not browser-driven.

## Manual adjustment and Basic semantics, 11:28 UTC

On bundle main-Cka0Ap64.js, Chrome verified warehouse selector Basic Available 20 and current physical 22. Entering -23 displayed resulting -1 and disabled Proceed to Review. Replacing it with -1 produced server review physical 21, owned reserved 0, safety 2, Basic Available 19, explicitly excluding unallocated orders. Confirmation produced movement 6 (actor 2, balance version 5, server timestamp 2026-09-08 11:28:55) and refreshed the matrix to physical 21 / Basic Available 19. Database readback confirmed the exact adjustment reason and quantities; accounting and stock sync remain false.

The earlier outstanding adjustment/overdraw items now have browser evidence. Retry is covered by automated API and interaction tests, not a new browser retry. Current automated checks: PhysicalCountApiTest 5 tests / 80 assertions; marketplace34 frontend 14 tests; TypeScript and preview build passed. Basic review data is now server supplied and is separate from advanced ATP. This does not close the remaining Basic release scope or authorize online deployment.

## Ledger drawer and setup gates, 11:33 UTC

The original ZIP36 audit drawer now reads the paginated physical-movements endpoint instead of an empty frontend history array. Chrome on main-D2Ahka0u.js displayed all four test SKU entries: initial unknown-to-20, inbound 20-to-25, outbound 25-to-22, adjustment 22-to-21, with reasons, employee 2, warehouse 9 and correctly localized UTC timestamps. Pagination and mixed-SKU rejection tests passed. TypeScript and build passed.

Basic WMS disabled now blocks warehouse create/update, store binding writes and balance provisioning before payload processing while retaining read access. BasicWmsTest, InventoryWarehouseApiTest, InventoryBalanceProvisionTest and InventoryRoutingServiceTest passed together: 12 tests, 95 assertions. Other advanced boundary and release requirements remain open.

## Inventory source separation

Authenticated Chrome on main-DMsEFy0J.js showed test SKU legacy JW stock 0, WMS physical 21, safety 2, Basic Available 19, and Lazada catalog snapshot 546 read at 17:15:46 local time. The snapshot is a previously persisted read, not a new provider call in this check. Source values remain separate; Last Confirmed Pushed is None and per-store Push Stock is disabled. Fixed the all-store button and handler to require at least one eligible store after observing its incorrect enabled state. The follow-up button correction requires browser verification.
