# Order / shipment status closure
Mode: OPERATIONAL_CLOSURE. Local only; no publishing, booking, mail, commit or deployment.
Plan: docs/superpowers/plans/2026-11-02-order-shipment-status-closure.md
Baseline: primary canonical checkout has unrelated dirty changes; preserved. Historical test failure accounting remains in 2026-11-01-admin-commerce-integration-readiness.md.

## Mapping
- Settings -> commerce status API -> MySQL commerce_status_definitions -> order selector / settings actual counts: pending verification.
- Order command -> existing CheckoutService transaction + history -> MySQL -> shipment preparation: pending verification.
- Shipment facts -> existing ShipmentService / provider -> shipments -> Track deliveries: unchanged transitions, pending regression.
- Queue -> Order.status processing + no active shipment -> shared preparation flow: pending verification.
- Payment is financial evidence, not another manually configured lifecycle.

## Issue ledger
Initial implementation checks: no historical run accounting inferred. Timings/tokens unavailable unless output measured.

- First CommerceStatusClosureTest run: 3 passed / 1 failed; new fixture omitted required shipments.carrier_name. Unexpected fixture issue count 1. Fixed by supplying carrier_name. Next: rerun targeted test and frontend build. No application retry or provider request.

- Targeted PHP rerun after fixture fix: 4 passed, 16 assertions, 7.00s. Fixture issue resolved.
- Frontend build: passed 14.23s; existing missing sample font/image and bundle size warnings remain.
- Local migration applied only new commerce status table, 90.23ms; no historical order changes.
- Browser normal settings page: real counts 15 pending / 1 paid / 7 processing projection / 0 hold / 6 completed / 3 cancelled, adjacent Shipment Status present.
- Frontend command found shipment completion/preparation checks: 5 passed. An ancillary edit command used wrong cwd and made no change; corrected canonical path before next validation.

- Regression: 14 PHP checks passed / 146 assertions. Frontend 15 passed / 6 failed in one issue: notification boolean broadened to true for unowned status names. Unexpected issue count 1. Restored exact-code notification ownership; backend still validates explicit lifecycle code. Rerun justified by code fix.

- Next run: focused status/queue/action policy tests + OrderAdmin and shipment UI tests, after exact-code notification fix, explicit no-payment-bypass guard, queue helper split and server-only registry save. No unchanged rerun.

- Browser integration found a distinct adapter omission: shipmentListParcel allowlist dropped new orderStatus/paymentStatus/statusName fields, resulting in empty preparation list despite eligible API rows. Issue count 1. Added field handoff and API-response-to-queue regression. Not marked closed before browser recheck.
- Latest frontend build 16.55s passed. Automatic notification permission/dedup/no-op API regressions: 4 passed / 17 assertions.

- Adapter regression resolved: 34 frontend checks passed (3 queue/adapter handoff + 16 shipment integration + 15 order integration). Normal browser preparation now shows exactly 2 processing/unbooked order rows; no dispatched/cancelled history rows.
- Final consistency changes: preparation hub count uses same queue selector; payment result shown independently of COD terms; shipment status mutation options read the same server registry names. Existing allowed transition codes remain unchanged.

- Shipment status modal integration test initially could not load: testing-library is not installed. Environment/test harness issue count 1; replaced test harness with the repository's existing React act/createRoot pattern, no dependency install.

## Local acceptance result (2026-11-02)
- HEAD f1922359, work remains uncommitted in canonical checkout; unrelated dirty work retained. No push/deploy.
- [x] Registry MySQL persistence and owner-only editing. Canonical status codes/effects are protected; arbitrary code/inventory-effect fields rejected.
- [x] Order API command validates actual transition, payment evidence, and pause/resume stage; existing CheckoutService and notification ownership retained.
- [x] Order dropdown/badges and shipment list/update options read registry labels. Legacy stored order states are projected without rewriting historical rows.
- [x] Processing-only preparation queue; no active manual/EasyParcel duplicate row. All historical shipments remain in deliveries.
- [x] Payment shows actual payment result; COD remains separate delivery terms, never proof of payment.
- [x] Focused backend checks: 15 tests/154 assertions plus 4 existing permission/notification/no-op checks/17 assertions. Relevant frontend unique checks: 40 (15 OrderAdmin, 16 shipment adapter, 3 queue/handoff, 4 preparation, 1 completion, 1 status registry modal).
- [x] Build succeeded, last measured 12.79s. Existing sample font/image references and bundle size warnings unchanged.
- [x] Normal browser: settings actual counts; same-value shipment name save and refresh; Prepare shows 2 eligible processing/recovery orders; Track retains dispatched/cancelled/delivered history; light/dark checked. No order/payment/booking/notification mutation during browser checks.
- Evidence: tmp/status-closure-settings-light.png, tmp/status-closure-shipment-settings.png, tmp/status-closure-prepare-light.png, tmp/status-closure-deliveries-dark.png.
- Scope limit: no real courier booking/provider transition tested; existing broader return/refund failures recorded in 2026-11-01-admin-commerce-integration-readiness.md remain outside this phase. Mobile browser layout not separately verified.
- No elapsed total or token accounting measured; unavailable. Test-run durations above are tool output measurements.
