# Inventory stock push blocker audit — 2026-09-27

## Incident and evidence

Production SKU `0126206` (variant 114), Lazada test store 4: read-only inspection of `LazadaTestFacilityAcceptance::inspect(4,114)` returned `eligible=false`, with `open_test_store_order` as its only blocker. All eight platform facilities were uncounted. No provider write was performed during this investigation.

Allocate Stock calls BatchFirstCountReview, which calls FacilityStockReview for every destination. Both review and confirmation invoke LazadaTestFacilityAcceptance. That service searches platform orders independently of the JW balance. Matching open order lines therefore blocked a brand-new balance and sent the UI into Stock Take Review. The earlier store-level publisher-readiness fix did not cover this facility path.

## Nine business conditions addressed in this phase

| Condition | Entry points and change |
| --- | --- |
| Matching open platform orders | LazadaTestFacilityAcceptance: `open_test_store_order` is advisory, not an eligibility veto. |
| Unreadable historical platform orders | `unreadable_test_store_order` is advisory. |
| Existing order obligations | `active_stock_obligation` is advisory. |
| Existing legacy inventory | `legacy_stock_present` is advisory; removed duplicate legacy store/warehouse checks in FacilityStockReview and FacilityTransferReview. Existing records are not deleted or migrated by this change. |
| Existing store-level order activation route | Removed prerequisite to reconcile the route before facility count/intake/audit/transfer. |
| Unfinished facility sync | `unfinished_stock_sync` is advisory. |
| Physical count below recorded reservations | CountMutation and FacilityStockReview accept a nonnegative physical count; StoreSkuSyncReadiness no longer vetoes publishing. Reserved quantity remains recorded and calculated available stock is clamped to zero. |
| Previous delivery uncertain | Direct store resync permits a deliberate resend. A new store mutation supersedes old uncertain delivery, and its worker does not wait for uncertain tasks. Exact-facility resend already supports this. |
| Previous transfer delivery incomplete | Direct store resync no longer demands resolving the old source/destination sync first. It sends the latest store balance without repeating the inventory transfer. |

Diagnostics remain available through the acceptance response's `warnings`. They do not force an order-review step. No extra quantity/reason push form is added.

## Conditions still present (not represented as removed)

This audit covers the inventory page's batch first count, per-store/facility mutation, transfer, direct resync, outbox and worker paths. It is not a claim that every application guard has been removed.

* Valid identity and input: permissions; existing store/SKU; unambiguous listing mapping; exact active warehouse binding where applicable; supported provider; valid integer quantities; allocation totals and transfer source capacity; stock already counted for intake/resend versus first count; stale review/version checks.
* Explicit operating controls: independent/shared mode compatibility, rebaseline pause, store pause, master/platform sync switches, configured canary allowlist, facility rollout switches and designated test-seller identity. These can still prevent sending if configured off. They are not caused by historical orders.
* Concurrency: an actively processing same-store/SKU write still serializes delivery; direct resync reports a conflict while that request runs. An uncertain completed request is no longer treated as an active request.
* Listing participation: inactive/draft/archived/deleted listing status still participates in StoreSkuSyncReadiness checks.
* Provider prerequisites/results: credentials, connection, provider rejection, rate limiting and unsuccessful readback can still yield an actual sync error. No simulated success is introduced.
* Outside ordinary push: ownership/mode transition readiness still checks reservations and legacy inventory; StoreSyncReadinessReader uses per-SKU readiness for its actionable `ready` result. Historical takeover requirements can still appear as setup diagnostics.
* The legacy task `/retry` endpoint performs readback when the existing task is uncertain. Manual resend uses the store/facility resync endpoint, which creates a new delivery of current stock.

## Verification

Canonical development root: `C:\xampp\htdocs\jwstudio-ecommerce`. SQLite `:memory:` tests, with no cached production configuration.

* 69 tests / 356 assertions: acceptance, facility stock actions, facility review and transfer, store review, stock recovery, batch first count, stock take recovery.
* 20 tests / 116 assertions: store sync worker, direct delivery and transfer pipeline.
* Batch regression includes two open same-SKU order lines across two locations before first count; confirmation creates exact-location jobs and mocked provider delivery confirms both.
* New mutation regression preserves a previous uncertain task as superseded and delivers the latest computed quantity. Direct manual resend and incomplete transfer recovery are also covered.

Provider transport is mocked in tests. Production diagnosis was read-only. This phase has not yet been synchronized or verified as a live release.
