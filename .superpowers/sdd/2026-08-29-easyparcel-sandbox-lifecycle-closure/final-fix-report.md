# EasyParcel Sandbox Lifecycle Closure — Consolidated Final Fix Report

**Date:** 2026-08-29
**Canonical working copy:** `C:\xampp\htdocs\jwstudio-ecommerce`
**Branch:** `main`
**Application commit:** `2560d19bc1de19389f221f8aa29bf9344b1cb9f6` (`fix: close EasyParcel reconciliation lifecycle gaps`)
**Outcome:** DONE — all final-review findings 1–10 are addressed by application, test, migration, and runbook changes.

## Finding disposition

### 1. Uncertain booking, AWB-null booking, and early unmatched webhook recovery — ADDRESSED

- Added durable reconciliation ownership in `easyparcel_reconciliation_tasks`. Booking task identity is keyed by the persisted application reference, with quote, shipment, or receipt ownership stored as foreign keys.
- An uncertain submit is never resubmitted. `EasyParcelBookingReconciliationService` obtains an OAuth-authenticated provider session, performs a bounded Shipment Listing request, authenticates each candidate through Shipment Details, and accepts only the exact persisted application reference.
- Materialization locks the order and quote, verifies the provider/session snapshot and booking claim, rejects competing shipments, and creates at most one EasyParcel shipment.
- Recovery supports claims created before this fix: when `selected_service_code` is absent, the authenticated Shipment Details service id must resolve to an exact service in the persisted quote snapshot.
- A successful booking with a null AWB atomically creates a Shipment Details reconciliation task. The five-minute sweeper also discovers pre-existing non-terminal EasyParcel shipments with no AWB.
- Standard unmatched receipts receive durable tasks. An early receipt with a provider shipment number can authenticate Details, resolve the application reference, materialize the shipment, attach the receipt, and continue reconciliation without calling submit-order.
- Added an authorized Shipping Dispatch endpoint: `POST /admin/api/shipping/orders/{order}/easyparcel/reconciliation`. It only requeues durable reconciliation ownership and never submits a booking.
- Added a one-time sanitized warning after the configurable AWB age threshold (`EASYPARCEL_AWB_ALERT_AFTER_MINUTES`, default 60).
- Corrected the acceptance runbook with the recovery, worker, scheduler, retry, alert, and no-resubmit procedure.

### 2. Receipt/outbox publication and worker ownership — ADDRESSED

- Receipt classification and reconciliation-task creation now commit in the same database transaction.
- Queue publication is handled by a durable publisher. A dispatch exception leaves the task durably `queued` with `queue_publish_failed`; a stale-queued sweeper republishes it.
- Workers acquire a database state/UUID lease inside a locked transaction before any provider call. A second worker cannot overlap an active lease.
- Retry state, bounded backoff, terminal exhaustion, publication attempts, processing attempts, lease expiry, completion, and sanitized error codes are database-backed.
- Legacy queued `ReconcileEasyParcelWebhook` jobs now enter the same task/lease processor instead of bypassing ownership.
- Tests cover dispatch failure and stale republish, an expired crashed lease, and an overlapping worker attempting to enter during the provider call.

### 3. Delivered plus Returned batch equivalence — ADDRESSED

- Lifecycle projection now honors the first terminal transition in ingestion order. Once Delivered is terminal, a later Returned hint cannot regress it.
- A shipment already terminal Returned still accepts only a verified Delivered event whose provider occurrence time is later than the return.
- The same Delivered-then-Returned events now produce Delivered whether received in one provider batch or two calls, including identical order history and delivered timestamp projection.

### 4. Provider-session refresh for quote and booking — ADDRESSED

- Quote and booking obtain `EasyParcelProviderSession` before entering their locked application transactions.
- Quote context and quote persistence both assert the same current provider/credential snapshot.
- Booking claim persists the selected service and asserts the current session before committing the claim; finalization asserts it again before committing provider results.
- Expiring-token quote and booking tests prove refresh HTTP executes at transaction level zero and that subsequent provider requests use the rotated access token.

### 5. Stale Shipment Details overwriting a replaced AWB — ADDRESSED

- Shipment Details now snapshots both provider shipment number and current AWB before the provider request.
- The locked write requires the provider reference and AWB to remain exactly equal to that snapshot, then asserts the provider session before commit.
- The overlap regression replaces the AWB while the response is in flight and proves no stale provider fields are persisted.

### 6. Manual Admin events mutating EasyParcel truth — ADDRESSED

- `ShipmentService::appendManualEvent` now rejects every non-manual shipment before transition logic.
- The sanitized shipment read model includes `source`; pending order projections identify themselves as `pending`.
- The existing Admin manual-event UI filters its target selector and quick actions to `source=manual`. No DOM/class/layout redesign was made.
- Backend authorization/source-boundary and frontend target-filter tests cover the restriction.

### 7. Webhook source and identifier correlation — ADDRESSED

- Correlation queries only `source=easyparcel` shipments.
- When both shipment number and AWB are supplied, both must resolve and must identify the same EasyParcel shipment. Otherwise the receipt remains unmatched and only durable provider-backed reconciliation may resolve it.
- Tests cover a manual shipment collision and two identifiers that point to different EasyParcel shipments.

### 8. Real two-process provider-session refresh contention — ADDRESSED

- Added a real two-process MySQL/database-cache contention test.
- Both PHP processes reach the refresh boundary together. The distributed cache lock permits exactly one fake token exchange; both processes receive the same rotated access token and the database records one rotation.

### 9. Three-decimal package snapshot prefills — ADDRESSED

- Prefilled weight and dimensions are normalized to an accepted two-decimal representation before display/submission.
- Missing values remain blank; no dimensions are invented.
- The focused test covers `1.234`, `28.346`, `18.111`, and `8.999` becoming `1.23`, `28.35`, `18.11`, and `9`.

### 10. Idle quote expiry — ADDRESSED

- The booking modal schedules a quote-expiry timer and recomputes time when it fires.
- Confirm also performs an immediate `Date.now()` expiry check so a stale render cannot submit.
- The focused fake-timer UI test proves an open idle modal changes to the expired state without another user action.

## Design and safety decisions

- OAuth-authenticated EasyParcel Listing, Details, and Tracking responses are the only EasyParcel truth. Webhooks remain hints and cannot project lifecycle state directly.
- Recovery contains no submit-order call. The original application reference and booking claim are immutable recovery ownership.
- Receipt plus outbox creation is atomic; publication is recoverable rather than falsely treated as atomic with an external queue.
- Network calls remain outside locked database transactions. Only short claim/finalization/projection transactions use row locks.
- Reconciliation discovery and publishing are bounded to 100 rows per sweep pass. Worker retries are bounded to 12 attempts with a maximum fifteen-minute delay.
- Logs and Admin responses retain only safe local ids, state, counts, exception class, and allow-listed error codes. No raw webhook body, OAuth credential, address, customer PII, label URL, or provider secret was added to the reconciliation table or API.
- Google AI Studio presentation structure and Tailwind classes were preserved. Frontend changes are state, filtering, timing, and data-binding logic only.
- No Developer Hub setting, Live carrier, push, deployment, or development-data mutation was performed.

## Migration

Added `database/migrations/2026_08_29_000100_create_easyparcel_reconciliation_tasks_table.php`.

The additive table contains:

- provider/environment and unique task key;
- type plus nullable quote/shipment/webhook-receipt foreign-key ownership;
- persisted application reference;
- pending/queued/processing/retrying/resolved/failed state data;
- availability/publication timestamps and attempt counts;
- UUID processing lease and lease expiry;
- sanitized last-error code, completion time, and one-time AWB alert time;
- due/publication indexes.

Rollback drops only this child table. MySQL round-trip tests explicitly suspend and restore the child migration before exercising older parent-table rollback tests, preserving real foreign-key ordering. The current development database reports this migration as **Pending**; it was exercised against `jw_ecommerce_testing` only and must be applied in the normal accepted release workflow.

## Exact files changed in application commit `2560d19`

- `.env.example`
- `app/Http/Controllers/Admin/ShipmentApiController.php`
- `app/Jobs/ReconcileEasyParcelTask.php`
- `app/Jobs/ReconcileEasyParcelWebhook.php`
- `app/Jobs/SweepEasyParcelReconciliation.php`
- `app/Models/EasyParcelReconciliationTask.php`
- `app/Services/Admin/ShipmentDataService.php`
- `app/Services/ShipmentService.php`
- `app/Services/Shipping/EasyParcelBookingReconciliationService.php`
- `app/Services/Shipping/EasyParcelFulfillmentService.php`
- `app/Services/Shipping/EasyParcelReconciliationPendingException.php`
- `app/Services/Shipping/EasyParcelReconciliationPublisher.php`
- `app/Services/Shipping/EasyParcelReconciliationSweeper.php`
- `app/Services/Shipping/EasyParcelReconciliationTaskProcessor.php`
- `app/Services/Shipping/EasyParcelReconciliationTaskService.php`
- `app/Services/Shipping/EasyParcelShipmentDetailsService.php`
- `app/Services/Shipping/EasyParcelWebhookCorrelationService.php`
- `app/Services/Shipping/EasyParcelWebhookReceiptService.php`
- `app/Services/Shipping/EasyParcelWebhookReconciliationService.php`
- `config/services.php`
- `database/migrations/2026_08_29_000100_create_easyparcel_reconciliation_tasks_table.php`
- `docs/operations/easyparcel-sandbox-acceptance.md`
- `public/build-admin/assets/main-C8oOHxl9.js` (replaces `main-SjYnhSJt.js`)
- `public/build-admin/manifest.json`
- `resources/admin/src/components/shipping/EasyParcelBookingModal.test.ts`
- `resources/admin/src/components/shipping/EasyParcelBookingModal.tsx`
- `resources/admin/src/components/shipping/ShippingDashboard.test.ts`
- `resources/admin/src/components/shipping/TrackingAndWebhooksTab.tsx`
- `resources/admin/src/components/shipping/easyParcelPackageConfirmation.test.ts`
- `resources/admin/src/components/shipping/easyParcelPackageConfirmation.ts`
- `resources/admin/src/integration/shipmentData.ts`
- `resources/admin/src/types/shipping.ts`
- `routes/console.php`
- `routes/web.php`
- `tests/Feature/Admin/EasyParcelFulfillmentApiTest.php`
- `tests/Feature/Admin/EasyParcelFulfillmentMigrationTest.php`
- `tests/Feature/Admin/EasyParcelPlatformOauthTest.php`
- `tests/Feature/Admin/EasyParcelProviderMigrationTest.php`
- `tests/Feature/Admin/ShipmentApiTest.php`
- `tests/Feature/EasyParcelReconciliationRecoveryTest.php`
- `tests/Feature/EasyParcelTrackingLifecycleTest.php`
- `tests/Feature/EasyParcelWebhookIngressTest.php`
- `tests/Feature/EasyParcelWebhookReconciliationTest.php`

User-owned dirty `AGENTS.md` was preserved byte-for-byte from this task's starting state and was never staged or committed.

## Test-first evidence and final verification

Initial focused tests failed for the intended reasons before implementation: no durable task table/services, batch projection returned `returned`, stale Details overwrote the replacement AWB, the manual endpoint returned 201, webhook source/dual-id correlation matched incorrectly, expiring quote/booking sessions returned 422, three-decimal prefills remained invalid, and idle expiry did not render.

An intermediate integrated run exposed MySQL foreign-key dependency failures when older parent migrations were rolled back while the new child table existed. The migration tests were corrected to roll the additive child down first and restore it last. No constraint was removed or weakened.

Final commands and outcomes:

```text
vendor\bin\pint --dirty
PASS

C:\xampp\php\php.exe artisan config:clear
Configuration cache cleared successfully.

C:\xampp\php\php.exe artisan test \
  tests/Feature/Admin/EasyParcelPlatformOauthTest.php \
  tests/Feature/Admin/EasyParcelProviderApiTest.php \
  tests/Feature/Admin/EasyParcelFulfillmentApiTest.php \
  tests/Feature/Admin/EasyParcelFulfillmentMigrationTest.php \
  tests/Feature/Admin/EasyParcelProviderMigrationTest.php \
  tests/Feature/EasyParcelTrackingLifecycleTest.php \
  tests/Feature/EasyParcelWebhookIngressTest.php \
  tests/Feature/EasyParcelWebhookReconciliationTest.php \
  tests/Feature/EasyParcelReconciliationRecoveryTest.php \
  tests/Feature/Admin/ShipmentApiTest.php
PASS — 109 tests, 719 assertions, 48.57 seconds.

cd resources/admin
npm test -- --run
PASS — 40 files, 215 tests.

npm run lint
PASS — TypeScript noEmit.

npm run build
PASS — 2,399 modules transformed; production assets generated in public/build-admin.

C:\xampp\php\php.exe artisan schedule:list
PASS — SweepEasyParcelReconciliation every five minutes; SyncEasyParcelTracking every fifteen minutes.

C:\xampp\php\php.exe artisan route:list --name=admin.api.shipping.easyparcel.reconciliation.store
PASS — one authorized POST route registered.

C:\xampp\php\php.exe artisan migrate:status | Select-String '2026_08_29_000100'
EXPECTED — new migration Pending in the development database; MySQL migration tests passed in jw_ecommerce_testing.

git diff --check
PASS.
```

The broad Laravel suite was intentionally not rerun because the final-review instruction requested the bounded EasyParcel gate and said not to rerun the broad suite unless diagnosing a regression.

## Concerns and operational follow-through

1. Apply the pending additive migration through the normal accepted localhost/release process before running workers against this code.
2. Reconciliation requires the Laravel scheduler and database queue worker to remain operational. A stopped worker is recoverable through the persisted outbox, but no provider progress occurs until processing resumes.
3. Vite completed successfully but retained its existing large-chunk warning (`main` is above 500 kB). No bundle-splitting redesign was included in this closure wave.
4. External Sandbox API/Developer Hub acceptance and all Live-carrier proof remain unexecuted and require separate authorization. This report is code and local-fixture closure evidence only.
5. No push or Hostinger deployment was performed, as explicitly prohibited for this wave.

## Self-review

- Re-read the final diff for provider authority, idempotency, lock duration, lease ownership, safe logging, source boundaries, and accidental presentation changes.
- Confirmed the only remaining pre-existing worktree change after the application commit is `AGENTS.md`.
- Confirmed no recovery path references `EasyParcelFulfillmentService::BOOKING_URL` or calls submit-order; focused assertions explicitly reject such a call.
- Confirmed quote/booking refresh tests and the two-process contention test use fake provider responses and the isolated MySQL test database, not real Sandbox or Live credentials.
- Confirmed the generated Admin build corresponds to the tested source and manifest.
- Confirmed all ten review findings have focused regression coverage and are represented in the final targeted gate.
