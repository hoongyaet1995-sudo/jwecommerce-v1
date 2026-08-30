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
- `provider_terminal` automatically opens recovery guidance. A fresh quote/rebook action is offered only when the terminal shipment is actually rebookable (`cancelled` or `booking_failed`).
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
- The Task 2 service and recovery domain were consumed unchanged.
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
