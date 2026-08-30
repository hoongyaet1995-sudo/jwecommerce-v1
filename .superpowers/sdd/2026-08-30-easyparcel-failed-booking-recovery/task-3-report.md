# Task 3 report — protected recovery API and existing-modal rebook UX

## Status

Complete in the canonical development environment `C:\xampp\htdocs\jwstudio-ecommerce`. No push, deployment, production mutation, or `AGENTS.md` change was made.

## Focused TDD evidence

### RED

- PHP endpoint tests: `ShipmentApiTest` produced 3 focused failures because `POST /admin/api/shipping/shipments/{shipment}/easyparcel/recovery` returned `404` for the dispatch, view-only, and bounded-reason cases. The existing 17 tests stayed green.
- Admin adapter: `shipmentData.test.tsx` failed with `confirmEasyParcelProviderFailure is not a function`.
- Rendered recovery behavior: `ShippingDashboard.test.ts` failed because the exact code-7 provider diagnostic and recovery dialog were absent after row refresh.
- Existing booking-modal ambiguity: the new assertion failed until the existing EasyParcel booking modal rendered the unconfirmed-cancellation warning.
- Global refresh: a focused test failed because a `provider_terminal` result from the global `Get Latest Status` action did not open recovery guidance.
- Concurrency guard: a focused adapter test failed because no guard prevented rebooking while Task 2's durable cancellation state remained `cancelling/claimed`.
- The plan's npm command uses repository-relative test paths after `npm --prefix resources/admin` changes Vitest's working directory; that exact command returned `No test files found`. Meaningful RED/GREEN runs therefore used package-relative `src/...` paths.

### GREEN

- `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php tests/Feature/EasyParcelFailedBookingRecoveryTest.php`
  - PASS: 30 tests, 178 assertions.
- `npm --prefix resources/admin test -- --run src/integration/shipmentData.test.tsx`
  - PASS: 9 tests.
- `npm --prefix resources/admin test -- --run src/components/shipping/ShippingDashboard.test.ts`
  - PASS: 19 tests.
- `npm --prefix resources/admin test -- --run src/components/shipping/EasyParcelBookingModal.test.ts`
  - PASS: 3 tests.
- `npm --prefix resources/admin run lint`
  - PASS: `tsc --noEmit`.
- `C:\xampp\php\php.exe vendor\bin\pint --dirty` and `git diff --check`
  - PASS after formatting; no whitespace errors.

## Exact implemented UI behavior

- Row and global `Get Latest Status` actions retain and display the exact safe provider status description/code, AWB availability, and check timestamp from Task 1 rather than replacing them with a generic success message.
- The global action shows one exact diagnostic toast per successfully checked shipment and opens the existing-language recovery guidance for the first actionable terminal/review result.
- `provider_terminal` automatically opens recovery guidance only when the server has returned a durable `awaiting_replacement` recovery and the refreshed shipment read model contains its matching pending replacement row. A provider-terminal status alone never releases rebooking.
- `needs_review` opens the same source-language modal, explains the API/portal mismatch, and requires an employee reason of 3–500 characters before `Confirm Provider Failure & Rebook` is enabled.
- Confirmation posts only `{reason}` to the protected recovery endpoint, refreshes the shipment list, shows confirmed/unconfirmed/in-progress cancellation evidence, and opens the existing `EasyParcelBookingModal` for `replacementOrderId` only after the Task 2 audit reaches `awaiting_replacement` with a terminal old shipment.
- An unconfirmed cancellation remains visibly attached to the replacement parcel and is rendered inside the existing booking modal; ambiguity is not hidden.
- Endpoint failures remain in the recovery modal. A still-claimed cancellation is shown as processing and cannot release a replacement quote.
- `booking_failed`, `cancelled`, and `on_hold` retain their canonical read-model statuses and are counted/rendered in both existing Exceptions surfaces.
- Terminal failed/cancelled history remains visible, while a fresh pending order projection is restored for the same processing order so the existing quote/service-selection flow can operate with a new quote.

## Files

- `routes/web.php`
- `app/Http/Controllers/Admin/ShipmentApiController.php`
- `app/Services/Admin/ShipmentDataService.php`
- `resources/admin/src/types/shipping.ts`
- `resources/admin/src/integration/shipmentData.ts`
- `resources/admin/src/integration/shipmentData.test.tsx`
- `resources/admin/src/context/AdminContext.tsx`
- `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx`
- `resources/admin/src/components/shipping/ShippingDashboard.tsx`
- `resources/admin/src/components/shipping/ShippingDashboard.test.ts`
- `resources/admin/src/components/shipping/ShippingOverviewTab.tsx`
- `resources/admin/src/components/shipping/EasyParcelBookingModal.tsx`
- `tests/Feature/Admin/ShipmentApiTest.php`
- `.superpowers/sdd/2026-08-30-easyparcel-failed-booking-recovery/task-3-report.md`

## Self-review

- RBAC uses only the existing `shipping.dispatch` permission for confirmation; a `shipping.view`-only employee receives `403` with no audit or provider request.
- The endpoint returns only safe recovery identifiers/states/messages; it does not expose credentials, addresses, raw provider responses, or provider prose beyond Task 2's sanitized cancellation evidence.
- The Task 2 recovery domain remains authoritative. Its service now also records server-verified provider code-0 cancellation evidence idempotently, without issuing a redundant cancellation request, and upgrades an earlier unconfirmed audit when later provider evidence confirms cancellation.
- Reason validation exists at both the HTTP boundary and Task 2 domain boundary.
- The shipment list no longer collapses `booking_failed`, `cancelled`, or `on_hold` back to `pending` merely because no AWB exists.
- Existing modal/card/button Tailwind language was reused. No new design system, page layout, or shipping information hierarchy was introduced.
- `AGENTS.md` was already modified before this task and is intentionally excluded from staging.

## Commit

- Message: `feat: add EasyParcel recovery and rebook workflow`
- The SHA is the commit containing this report and is returned to the orchestrating task after commit creation.

## Concerns / deferred acceptance

- This task includes feature, adapter, rendered-component, type, and formatting verification. Authenticated real-browser acceptance, tracked Admin production build output, GitHub push, and Hostinger deployment remain Task 5/release work and were not performed here.
- When a global refresh returns several actionable shipments, the UI opens the provider-terminal result first (otherwise the first needs-review result); every successful provider check still receives its own exact diagnostic toast.

## Reviewer fix round 1/5

### Focused RED evidence

- `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php --filter="provider_cancelled_refresh|provider_on_hold_refresh"`
  - RED: 2 failures; provider code 0 and code 8 both returned internal `pending` rather than `cancelled` and `on_hold`.
- `npm test -- --run src/integration/shipmentData.test.tsx`
  - RED: the mutation-plus-reload helpers were absent, recovery readiness/package data were not preserved, reload failure replaced successful mutations, and the exact diagnostic omitted `checkedAt`.
- `npm test -- --run src/components/shipping/ShippingDashboard.test.ts`
  - RED: audited code-0 recovery guidance and the fresh backend replacement handoff were absent. The prior provider-terminal branch synthesized a pending parcel from stale shipment data.
- `C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelFailedBookingRecoveryTest.php --filter=cancel_error_is_audited`
  - RED: later server-verified code-0 evidence retrieved the existing recovery but left its cancellation state as `error` instead of upgrading the same audit to `success` and projecting the shipment `cancelled`.
- The first affected-suite run exposed the code-0/code-8 tracking semantics gap: the tracking lifecycle suite rejected canonical `cancelled` and `on_hold` events. The focused exception test then failed until those statuses were retained as exception events and rendered as `Delivery Exception` without order cancellation, refund, or restock side effects.

### GREEN evidence

- `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php tests/Feature/EasyParcelFailedBookingRecoveryTest.php tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/EasyParcelReconciliationRecoveryTest.php tests/Feature/EasyParcelWebhookReconciliationTest.php tests/Feature/EasyParcelWebhookIngressTest.php`
  - PASS: 85 tests, 568 assertions.
- `npm test -- --run src/integration/shipmentData.test.tsx src/components/shipping/ShippingDashboard.test.ts src/components/shipping/EasyParcelBookingModal.test.ts`
  - PASS: 36 tests across 3 files.
- `npm run lint`
  - PASS: `tsc --noEmit`.
- `C:\xampp\php\php.exe vendor\bin\pint --dirty` and `git diff --check`
  - PASS after formatting; no whitespace errors.
- Additional read-model assertion after formatting:
  - `EasyParcelFailedBookingRecoveryTest::test_cancel_error_is_audited_but_employee_confirmation_still_releases_rebooking` PASS: 1 test, 19 assertions.

### Exact corrected behavior

- Provider code 8 is persisted and displayed as `on_hold`, returns `recoveryDisposition=none`, creates no recovery, and exposes neither manual confirmation nor replacement booking.
- Provider code 0 is accepted only from the authenticated server-side Details response. It creates or retrieves one audited recovery, moves it to `awaiting_replacement`, records confirmed cancellation without another provider cancel request, projects the old shipment `cancelled`, and returns safe recovery readiness.
- Recovery readiness and confirmed/unconfirmed cancellation evidence are attached to both the old shipment and the pending replacement read-model row, then preserved by the TypeScript adapter across reload/reopen.
- Row and global status checks return the provider result even when the subsequent shipment-list reload fails. A separate warning tells staff to refresh; a successful mutation is never relabeled as failed.
- Manual confirmation has the same mutation/reload separation. Unconfirmed provider cancellation remains visible in the existing booking modal through the persisted recovery snapshot.
- Replacement handoff uses only the deterministically matched refreshed pending row (`pending-order-{replacementOrderId}` plus matching recovery ID/readiness). It never constructs a replacement from the stale failed row.
- Recovery guidance is cleared before opening the existing booking modal. The guidance overlay now uses the existing EasyParcel booking overlay/shell classes, backdrop, scrolling, radius, shadow, and animation language.
- Every row/global exact diagnostic includes the server `checkedAt` value, including non-actionable code-8 checks.

### Files added to the fix scope

- `app/Domain/Orders/OrderTrackingPayloadFactory.php`
- `app/Models/Order.php`
- `app/Services/ShipmentService.php`
- `app/Services/Shipping/EasyParcelFailedBookingRecoveryService.php`
- `app/Services/Shipping/EasyParcelShipmentDetailsService.php`
- `tests/Feature/EasyParcelFailedBookingRecoveryTest.php`
- `tests/Feature/EasyParcelTrackingLifecycleTest.php`

### Round-one self-review and concerns

- The protected endpoint still uses the existing dispatch permission and ignores client status assertions; provider-terminal recovery is derived from the authenticated provider response.
- Existing Google AI Studio cards, buttons, and the EasyParcel modal shell remain intact; no new component system or shipping-page layout was introduced.
- Cancellation ambiguity is deliberately durable and visible. An awaiting replacement with an unconfirmed cancellation is still rebookable only because the audited manual-confirmation policy explicitly permits it.
- Authenticated real-browser acceptance, tracked Admin build output, push, and deployment remain outside Task 3 and were not performed.

### Fix commits

- Code fix commit: `b4254f1` (`fix: harden EasyParcel recovery handoff`).
- Report-only evidence commit: the follow-up commit containing this exact SHA and verification record.
