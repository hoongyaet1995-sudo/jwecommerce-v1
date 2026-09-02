# Unified Fulfilment Correction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate the approved Google AI Studio fulfilment-recovery and delivery-address correction experience into the operational Laravel Admin while preserving the customer order, inventory, payment, and financial snapshots.

**Architecture:** Treat every dispatch as an immutable shipment attempt. A correction closes the selected non-delivered attempt, records a durable audit snapshot, reopens the same order for a replacement method, and completes only when a new shipment attempt is created. Detail-only edits and delivery-address amendments use separate append-only ledgers. React keeps the selected ZIP DOM/Tailwind and calls protected Laravel APIs; MySQL is the only persistence truth.

**Tech Stack:** PHP 8.x, Laravel, MySQL, React, TypeScript, Tailwind CSS, Vitest/jsdom, PHPUnit/Laravel feature tests.

**Spec:** `docs/superpowers/specs/2026-09-02-unified-fulfilment-correction-design.md`

## Global Constraints

- Develop and accept first in `C:\xampp\htdocs\jwstudio-ecommerce`; do not edit the temporary ZIP extraction or the current Codex worktree.
- Preserve the selected ZIP component DOM, Tailwind classes, copy, responsive behavior, and interaction states. The only source correction allowed without a new visual decision is renaming the mismatched `onOpenAmendAddress` adapter prop to the component's defined `onOpenAmendDeliveryDetails` prop.
- Never import ZIP mock orders, mock shipments, prototype permission toggles, or `localStorage` mutation handlers.
- Never cancel the parent customer order, restock inventory, change payment state, or recalculate order totals during shipment correction.
- Delivered shipments and cancelled parent orders are hard-blocked. Provider cancellation is recorded truthfully but does not remove the staff's authority to continue after the required acknowledgement.
- Automated tests must fake EasyParcel and mail. They must not create real bookings, cancel real consignments, charge a wallet, or send real email.
- Run `C:\xampp\php\php.exe artisan config:clear` before PHP tests so the test environment cannot inherit a cached production-like database connection.
- Do not expose new actions on official Admin routes until the isolated authenticated localhost preview has been visually accepted.
- Do not push or deploy in this plan. After local acceptance, request separate synchronization approval.

## File Map

### New backend files

- `database/migrations/2026_09_02_000100_create_shipment_corrections_table.php`
- `database/migrations/2026_09_02_000200_create_shipment_operational_amendments_table.php`
- `database/migrations/2026_09_02_000300_create_order_address_amendments_table.php`
- `app/Models/ShipmentCorrection.php`
- `app/Models/ShipmentOperationalAmendment.php`
- `app/Models/OrderAddressAmendment.php`
- `app/Services/Shipping/FulfilmentCorrectionService.php`
- `app/Services/Shipping/ShipmentOperationalDetailsService.php`
- `app/Services/Shipping/OrderDeliveryDetailsService.php`
- `app/Http/Requests/Admin/StartShipmentCorrectionRequest.php`
- `app/Http/Requests/Admin/UpdateShipmentOperationalDetailsRequest.php`
- `app/Http/Requests/Admin/UpdateOrderDeliveryDetailsRequest.php`

### Existing backend files to modify

- `app/Models/Shipment.php`
- `app/Models/Order.php`
- `app/Models/OrderAddress.php`
- `app/Services/ShipmentService.php`
- `app/Services/Shipping/EasyParcelFulfillmentService.php`
- `app/Services/Shipping/EasyParcelBookingReconciliationService.php`
- `app/Services/Admin/ShipmentDataService.php`
- `app/Services/Admin/OrderDataService.php`
- `app/Http/Controllers/Admin/ShipmentApiController.php`
- `app/Http/Controllers/Admin/OrderApiController.php`
- `routes/web.php`

### New frontend files

- `resources/admin/src/components/shipping/CorrectFulfilmentModal.tsx`
- `resources/admin/src/components/shipping/CorrectFulfilmentModal.test.tsx`
- `resources/admin/src/components/orders/AmendDeliveryDetailsModal.tsx`
- `resources/admin/src/components/orders/AmendDeliveryDetailsModal.test.tsx`

### Existing frontend files to modify

- `resources/admin/src/types/shipping.ts`
- `resources/admin/src/types/index.ts`
- `resources/admin/src/integration/shipmentData.ts`
- `resources/admin/src/integration/shipmentData.test.tsx`
- `resources/admin/src/integration/orderData.ts`
- `resources/admin/src/integration/orderData.test.ts`
- `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx`
- `resources/admin/src/components/shipping/ShippingDashboard.tsx`
- `resources/admin/src/components/shipping/ShippingDashboard.test.ts`
- `resources/admin/src/components/orders/OrderAdmin.tsx`
- `resources/admin/src/components/orders/OrderAdmin.test.ts`

## Task 1: Persist Immutable Correction and Amendment Ledgers

- [ ] Add failing migration/model tests to `tests/Feature/Admin/ShipmentCorrectionApiTest.php`, `tests/Feature/Admin/ShipmentOperationalDetailsApiTest.php`, and `tests/Feature/Admin/OrderDeliveryDetailsApiTest.php` proving relationships, casts, foreign keys, actor attribution, and the one-open-correction invariant.
- [ ] Run `C:\xampp\php\php.exe artisan config:clear` followed by `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentCorrectionApiTest.php tests/Feature/Admin/ShipmentOperationalDetailsApiTest.php tests/Feature/Admin/OrderDeliveryDetailsApiTest.php` and retain the expected red output.
- [ ] Create `shipment_corrections` with `order_id`, `source_shipment_id`, nullable `replacement_shipment_id`, nullable unique `active_order_id`, `reason_code`, `internal_note`, JSON `original_snapshot`, `external_action_state`, nullable `external_action_note`, `status`, `created_by`, nullable `completed_at`, and timestamps. Use `active_order_id = order_id` only while awaiting replacement; set it to null on completion/closure so MySQL enforces one active correction per order.
- [ ] Create `shipment_operational_amendments` with `shipment_id`, `before_snapshot`, `after_snapshot`, `reason_code`, `internal_note`, `created_by`, and timestamps.
- [ ] Create `order_address_amendments` with `order_id`, `order_address_id`, `before_snapshot`, `after_snapshot`, `reason_code`, `internal_note`, `address_book_sync_requested`, `address_book_sync_result`, `created_by`, and timestamps.
- [ ] Add models, JSON/datetime casts, guarded/fillable declarations, and relations on `Shipment`, `Order`, and `OrderAddress`.
- [ ] Rerun the three targeted feature files and confirm green.
- [ ] Commit only Task 1 files with `git commit -m "feat: add fulfilment correction ledgers"`.

## Task 2: Start a Generic Fulfilment Correction Transaction

- [ ] Extend `tests/Feature/Admin/ShipmentCorrectionApiTest.php` with red cases for all three methods, delivered/cancelled hard blocks, permission denial, duplicate active correction, mandatory reason/note, provider-confirmed and staff-confirmed-external acknowledgements, and preservation of order totals/payment/inventory.
- [ ] Add `StartShipmentCorrectionRequest` validation: allowed reason codes; required internal note; required external-action state; conditional acknowledgement note; reject fields not owned by this operation.
- [ ] Implement `FulfilmentCorrectionService::start(Shipment $shipment, User $actor, array $input): ShipmentCorrection` inside one database transaction using `lockForUpdate()` on order, source shipment, and active correction lookup.
- [ ] Capture the full source attempt snapshot before mutation, append a shipment event, mark only the source attempt cancelled/voided locally, reopen the order to `processing`, and clear order tracking only when it still belongs to that source attempt.
- [ ] Preserve inventory reservations/deductions, payment state, order line items, coupon, tax, shipping fee, currency snapshot, and total fields byte-for-byte.
- [ ] Add `POST /admin/api/shipping/shipments/{shipment}/corrections` to `ShipmentApiController` and `routes/web.php`, protected by the existing shipping dispatch permission and CSRF/session middleware.
- [ ] Return a normalized active-correction payload and safe domain error codes; never surface provider secrets or raw exception messages.
- [ ] Run the targeted test file until green, then commit with `git commit -m "feat: start generic fulfilment corrections"`.

## Task 3: Complete Corrections Through Any Replacement Method

- [ ] Add failing cases to `ShipmentCorrectionApiTest.php` and `EasyParcelFulfillmentApiTest.php` proving that external courier, manual delivery, immediate EasyParcel booking, and reconciled EasyParcel booking each create a new shipment attempt and complete the same active correction.
- [ ] Add `FulfilmentCorrectionService::completeForReplacement(Shipment $replacement): void`; lock the active correction, reject reuse of the source shipment, set `replacement_shipment_id`, `status = completed`, `completed_at`, and `active_order_id = null`, then append completion audit events.
- [ ] Change `ShipmentService::dispatchTeamManaged` so a replacement never overwrites/reuses the cancelled source attempt and its sequence is `max(order shipment sequence) + 1`.
- [ ] Call the generic completion method from team-managed dispatch and from both EasyParcel completion paths in `EasyParcelFulfillmentService` and `EasyParcelBookingReconciliationService` within their existing transactions.
- [ ] Keep `EasyParcelFailedBookingRecoveryService` compatible for legacy recovery rows, but prevent it from being the canonical owner of new generic corrections.
- [ ] Update `ShipmentDataService` to expose active correction metadata and a single pending fulfilment row with `Resume Fulfilment`; do not regenerate or duplicate the source attempt.
- [ ] Verify forward-only attempt history, order tracking ownership, and concurrency/idempotency in targeted tests.
- [ ] Commit with `git commit -m "feat: replace shipment attempts across fulfilment methods"`.

## Task 4: Amend Operational Shipment Details Without Rebooking

- [ ] Write red tests in `tests/Feature/Admin/ShipmentOperationalDetailsApiTest.php` for External Courier fields (`courier`, `service`, `awb`, `tracking_url`) and Manual Delivery fields (`driver_name`, `handoff_reference`), including forbidden EasyParcel provider-owned fields.
- [ ] Validate method-specific fields in `UpdateShipmentOperationalDetailsRequest`, require reason and internal audit note, and reject delivered/cancelled shipment edits.
- [ ] Implement `ShipmentOperationalDetailsService::amend` with row locking, a before snapshot, a narrow allow-list per fulfilment method, shipment event creation, and an append-only amendment record.
- [ ] Add `PATCH /admin/api/shipping/shipments/{shipment}/operational-details` using the shipping update-status permission.
- [ ] Ensure provider-managed EasyParcel AWB/tracking remains read-only; its correction path is void-and-replace rather than local rewriting.
- [ ] Run the targeted feature test and commit with `git commit -m "feat: audit shipment operational amendments"`.

## Task 5: Amend the Active Order Delivery Destination

- [ ] Write red tests in `tests/Feature/Admin/OrderDeliveryDetailsApiTest.php` for complete recipient/address validation, Malaysian phone/postcode/state rules, before/after audit, no-op rejection, permission denial, cancelled/delivered hard blocks, active local shipment snapshot update, and optional customer address-book sync.
- [ ] Implement `UpdateOrderDeliveryDetailsRequest` with normalized first/last name, phone, address lines, postcode, city, state, and country fields plus mandatory reason and note. A non-Malaysian country must not be silently paired with a Malaysian state.
- [ ] Implement `OrderDeliveryDetailsService::amend` transactionally: lock order and shipping address; update only this order's address; update still-active local shipment destination snapshots; create the immutable address amendment; optionally sync the matching saved address only when the staff checkbox is explicit and permitted.
- [ ] Do not alter historical provider booking payloads or pretend an EasyParcel/external courier accepted the new destination. If an active provider booking exists, return a warning requiring void-and-replace before the provider route changes.
- [ ] Add `PATCH /admin/api/orders/{order}/delivery-details` to `OrderApiController` and `routes/web.php` under an order-management permission already accepted by the current Admin order mutation policy.
- [ ] Update `OrderDataService` and `ShipmentDataService` to project address eligibility, current delivery fields, validation metadata, warning state, and last amendment summary.
- [ ] Run the targeted feature test and commit with `git commit -m "feat: amend audited order delivery details"`.

## Task 6: Define React Contracts and API Adapters

- [ ] Add red adapter tests to `resources/admin/src/integration/shipmentData.test.tsx` and `resources/admin/src/integration/orderData.test.ts` for request methods, CSRF headers, payload normalization, 403/409/422 safe errors, active-correction mapping, and refreshed result data.
- [ ] Extend `resources/admin/src/types/shipping.ts` with typed `ShipmentCorrection`, eligibility, external-action state, operational amendment inputs, and replacement-flow results; extend `resources/admin/src/types/index.ts` with delivery-amendment inputs/results.
- [ ] Implement `startShipmentCorrection`, `amendShipmentOperationalDetails`, and `amendOrderDeliveryDetails` adapters using the existing authenticated request helper and response conventions.
- [ ] Do not add browser persistence. After success, reload canonical server data through the existing Admin context/data loaders.
- [ ] Run `npm test -- --run resources/admin/src/integration/shipmentData.test.tsx resources/admin/src/integration/orderData.test.ts` from `resources/admin` and confirm green.
- [ ] Commit with `git commit -m "feat: add fulfilment correction admin adapters"`.

## Task 7: Port the Approved ZIP Modals Into an Isolated Preview

- [ ] Copy only the selected modal DOM/classes/copy from the hashed ZIP source into `CorrectFulfilmentModal.tsx` and `AmendDeliveryDetailsModal.tsx`; record any unavoidable adapter-only differences in the source extraction manifest.
- [ ] Add red component tests for both modes, method-specific inputs, acknowledgement gates, validation/diff display, delivered/cancelled blocks, loading, 403, 409, 422, and generic failure states.
- [ ] Replace ZIP `localStorage` handlers with injected typed callbacks. Remove prototype permission toggles and mock-data imports without changing visible production layout.
- [ ] Use `onOpenAmendDeliveryDetails` consistently to repair the documented source prop mismatch.
- [ ] Mount the components only in an authenticated localhost preview harness or feature-flagged preview path; do not add official table/order triggers yet.
- [ ] Run `npm test -- --run resources/admin/src/components/shipping/CorrectFulfilmentModal.test.tsx resources/admin/src/components/orders/AmendDeliveryDetailsModal.test.tsx`, then `npm run lint` and `npm run build`.
- [ ] Capture desktop and 390x844 screenshots for each modal and compare them with the four source baselines in the extraction manifest, including modal width, scrolling, selected states, footer actions, diff layout, and focus/keyboard behavior.
- [ ] Present the localhost preview evidence and obtain explicit acceptance before Task 8.
- [ ] Commit accepted preview code with `git commit -m "feat: port fulfilment correction modal previews"`.

## Task 8: Cut Over Official Admin Triggers After Preview Acceptance

- [ ] Add red tests to `ShippingDashboard.test.ts`, `OrderAdmin.test.ts`, and the two modal tests proving correct action visibility: all non-delivered shipment methods can correct, delivered is blocked, cancelled order is blocked, active correction shows resume, and address amendment is available from both approved surfaces.
- [ ] Wire `Correct Fulfilment` and `Resume Fulfilment` into `ShippingFulfillmentTab.tsx`/`ShippingDashboard.tsx` using existing data refresh and `ChooseFulfillmentMethodModal` handoff.
- [ ] Wire `Amend Delivery Details` into the Admin Order shipping-address card and the Shipping row quick action, passing the same server-owned order/address object.
- [ ] Preserve the existing `ChooseFulfillmentMethodModal` layout and all unrelated Shipping/Order DOM. Do not replace current EasyParcel quote/booking UI.
- [ ] Ensure a successful void-and-replace closes correction UI, refreshes data, and opens method selection for the same order; failed requests must remain recoverable without optimistic local mutation.
- [ ] Run the three targeted frontend suites, `npm run lint`, and `npm run build`; capture authenticated localhost screenshots and interaction recordings for both official entry points.
- [ ] Commit with `git commit -m "feat: activate fulfilment recovery admin flow"`.

## Task 9: Notification, Security, and End-to-End Local Acceptance

- [ ] Add `tests/Feature/Notifications/ShipmentCorrectionNotificationTest.php` proving: starting/correcting an attempt sends no order-cancelled message; replacement EasyParcel/external shipment emits one deduplicated shipping-details-updated notification after commit; manual delivery without public tracking emits no tracking email; SMTP failure records a failed dispatch without rolling back business state.
- [ ] Extend security assertions to cover CSRF, authentication, shipping/order permissions, safe validation errors, immutable audit actors, and absence of credentials/raw provider payloads in responses.
- [ ] Run targeted backend tests:
  - `C:\xampp\php\php.exe artisan config:clear`
  - `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentCorrectionApiTest.php tests/Feature/Admin/ShipmentOperationalDetailsApiTest.php tests/Feature/Admin/OrderDeliveryDetailsApiTest.php tests/Feature/Admin/EasyParcelFulfillmentApiTest.php tests/Feature/Admin/ShipmentApiTest.php tests/Feature/Notifications/ShipmentCorrectionNotificationTest.php`
- [ ] Run the full backend suite with `C:\xampp\php\php.exe artisan test` and retain totals/output.
- [ ] Run the full Admin checks from `resources/admin`: `npm test -- --run`, `npm run lint`, and `npm run build`.
- [ ] Run `C:\xampp\php\php.exe artisan migrate:status`, `C:\xampp\php\php.exe artisan route:list --path=admin/api`, and `git diff --check`.
- [ ] Perform authenticated localhost browser acceptance without real provider side effects: External -> Manual replacement, Manual -> External replacement, simulated/faked EasyParcel recovery, detail-only correction, destination diff, validation failure, delivered/cancelled blocks, duplicate-submit resistance, page refresh persistence, and audit visibility.
- [ ] Confirm no unrelated storefront/admin visual regression and no writes outside the canonical XAMPP repository.
- [ ] Request review using `superpowers:requesting-code-review`, address verified findings, and rerun affected checks.
- [ ] Commit final local verification fixes with `git commit -m "test: close fulfilment correction workflow"`.
- [ ] Report the locally accepted commit SHA and evidence. Ask separately whether to synchronize that exact commit to GitHub `main` and Hostinger; do not release without that approval.

## Acceptance Matrix

| Scenario | Required result |
| --- | --- |
| EasyParcel -> Manual/External/EasyParcel replacement | old attempt retained and cancelled; provider confirmation or staff acknowledgement audited; new attempt created; order unchanged financially |
| Manual/External -> any replacement | old attempt retained and cancelled; new method selectable immediately; replacement attempt links to correction |
| Correct details only | method-owned editable fields change; before/after audit exists; no new attempt |
| Amend destination before active provider booking | current order/local snapshot updated; before/after diff and actor audited |
| Amend destination with active provider booking | truthful warning; provider destination is not silently claimed updated; void-and-replace remains available |
| Delivered shipment | correction and destination mutation blocked; return/reship workflow referenced |
| Cancelled parent order | correction and destination mutation blocked |
| Duplicate concurrent correction | exactly one open correction, HTTP 409 for the loser |
| Notification success/failure | commit first; sent/failed dispatch truth preserved; no business rollback |
| Refresh/relogin | active correction and all audits persist from MySQL; no localStorage dependency |
