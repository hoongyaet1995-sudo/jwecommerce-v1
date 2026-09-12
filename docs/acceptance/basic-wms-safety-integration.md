# Basic safety stock integration

Implemented an independent BasicWmsSafetyService and authenticated POST /admin/api/inventory/basic/safety. Request fields: balance_id, balance_version, safety_stock, reason, operation_key (UUID). The server supplies actor and timestamp. A transaction locks configuration, variant and balance; rejects stale versions, unknown physical counts, disabled Basic WMS and active accounting; updates only safety stock/version and appends an idempotent basic_wms_safety_events receipt.

Local migration 2026_09_08_000018 applied. BasicWmsSafetyTest passes 2 tests / 13 assertions, including API session gate, forbidden actor override, replay, unchanged legacy stock/reservation coverage, and available recalculation.

Remaining integration: ConnectedReconciliationModal currently always loads advanced loadReconciliation. Basic mode must use the independent safety endpoint and label its receipt as safety configuration, not WMS order reconciliation. The ZIP36 modal's existing fields and two-step flow can be reused; no new modal design is required. Advanced loadReconciliation must keep its unowned reservation blocker; Basic safety must exclude those reservations explicitly rather than marking them reconciled.

## Browser verification completed for independent warehouse safety

The Basic preview now calls the separate safety endpoint from the existing two-step ZIP36 modal. On main-BUqpt487.js, Chrome set safety stock to 2 for variant 103 / balance 14. Receipt 5cc934eb-9f1e-497e-83f7-2eeeb55e8739 showed BASIC_SAFETY_CONFIGURED, employee 2, 2026-09-08 20:07:47 local time, physical 12, reserved 0, safety 2 and available 10. Warehouse subtable refreshed to the same values. The local readback script confirmed legacy stock 17, physical 12, safety 2 and balance version 2.

Remaining: shared-pool safety UI path, broader negative/gate tests and aggregate release acceptance. Small display corrections after the browser check retain unknown previous safety and remove fabricated Zone A-01 fallback; they require rebuilding. The independent Basic route does not run advanced order reconciliation.
