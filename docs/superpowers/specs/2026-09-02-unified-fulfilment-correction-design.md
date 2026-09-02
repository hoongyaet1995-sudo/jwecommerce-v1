# Unified Fulfilment Correction Design

Date: 2026-09-02

Status: Approved design draft awaiting user review
Convergence mode: `OPERATIONAL_CLOSURE`

## Purpose

Replace the EasyParcel-only `Rebook` recovery with one auditable correction flow that can replace an incorrect EasyParcel, External Courier, or Manual Delivery shipment attempt without cancelling the customer order.

The flow preserves the existing Shipping & Delivery Management page and the accepted `ChooseFulfillmentMethodModal`. It adds only the missing correction and order-delivery-amendment surfaces.

## Boundary decisions

| Boundary | Confirmed value |
|---|---|
| Customer order | Never cancel the customer order merely because staff chose the wrong fulfilment method. |
| Inventory and payment | Keep inventory reserved and payment/order totals unchanged during shipment correction. |
| Provider cancellation | Staff may verify or cancel in the official provider dashboard. An unconfirmed API cancellation does not block correction. |
| External-risk ownership | JW records the staff attestation and audit trail; it does not prevent staff from proceeding because of possible duplicate provider charges or movement. |
| Delivered boundary | A delivered shipment cannot change fulfilment method. Use a separate Return, Replacement, or Reshipment workflow. |
| Notification | Do not send an Order Cancelled email. Notify only after the replacement shipment exists, according to the rules below. |
| Sender information | EasyParcel Sender Profile is global provider configuration and affects only future quotes/bookings. |
| Receiver information | Amend the current order's `order_addresses` snapshot. Customer Address Book synchronization is optional and off by default. |
| UI scope | L2 compound UI: one correction modal, one order delivery-details amendment modal, and small table action states. Existing adjacent UI remains unchanged. |
| Runtime truth | Laravel transactions and MySQL own corrections, amendments, permissions, concurrency, and replacement links. React owns only temporary form state. |
| Release | Localhost acceptance first; GitHub `main` and Hostinger only after explicit release approval. |

## Current-system evidence

- `ShippingFulfillmentTab.tsx` exposes `Rebook` only for an EasyParcel shipment with an EasyParcel recovery and opens `EasyParcelBookingModal` directly.
- `EasyParcelFailedBookingRecoveryService` only completes recovery when the replacement shipment is also EasyParcel.
- Cancelling a Manual shipment changes only the shipment lifecycle; it does not safely reopen the order for another fulfilment method.
- `EasyParcelBookingGuard` treats any Manual shipment as a competing shipment during EasyParcel finalization.
- Cancelling the customer Order would restock inventory, cancel pending payment, move the order to a terminal state, and may create a customer cancellation notification. It is therefore explicitly outside this flow.
- EasyParcel Sender Profile already has an approved edit surface in Courier Integrations.
- Admin Orders displays the order shipping address, but there is no order-scoped audited delivery-amendment flow.

## Human-error disposition matrix

| Scenario | Disposition | Operational result |
|---|---|---|
| Wrong method selected but not submitted | No persisted correction | Return to method selection. |
| Wrong EasyParcel service before booking | No persisted correction | Select another quote. |
| Wrong package dimensions before booking | No persisted correction | Correct package values and re-quote. |
| EasyParcel booked instead of Manual or External | Replace attempt | Record staff provider-dashboard confirmation, cancel old attempt locally, reopen fulfilment, choose any method. |
| Manual selected instead of EasyParcel or External | Replace attempt | Cancel Manual attempt, reopen fulfilment, choose any method. |
| External Courier selected instead of EasyParcel or Manual | Replace attempt | Record external cancellation confirmation, cancel old attempt, reopen fulfilment. |
| Wrong EasyParcel courier/service after booking | Replace attempt | Treat as a new fulfilment attempt. |
| Wrong external courier partner | Replace attempt | Cancel the old attempt and create a replacement. |
| Wrong AWB/tracking number only | Metadata correction | Amend operational details with before/after audit; do not replace method. |
| Wrong tracking URL only | Metadata correction | Amend with audit. |
| Wrong Manual driver or handoff reference | Metadata correction | Amend with audit. |
| Wrong receiver details before booking | Delivery amendment | Amend current order snapshot, then fulfil. |
| Wrong receiver details after booking | Replace plus amendment | Correct old attempt first, amend order delivery details, then fulfil from fresh data. |
| Wrong sender information before booking | Provider configuration | Edit EasyParcel Sender Profile and request a fresh quote. |
| Wrong sender information after booking | Replace plus provider configuration | Correct old attempt, edit Sender Profile, request a fresh quote. |
| Duplicate click | System defense | Idempotency prevents duplicate shipment creation. |
| Wrong non-delivered shipment status | Separate status correction | Do not replace method unless the method itself is wrong. |
| Provider cancellation unconfirmed | Staff-attested replacement | Staff may proceed after confirming external handling and entering an audit note. |
| Returned shipment and active order | Replace attempt | Reopen fulfilment. |
| Delivered shipment | Hard block | Use Return, Replacement, or Reshipment. |
| Cancelled customer order | Hard block | Use a separate Order restoration process. |
| Lost, damaged, or insurance claim | Separate incident | Do not misclassify as staff method-selection error. |
| Split shipment or partial reshipment | Future scope | Not added by this correction feature. |

## Domain model

### Shipment correction

Create a `shipment_corrections` table and model with:

- `id`
- `order_id`
- `source_shipment_id`
- `replacement_shipment_id`, nullable
- `reason_code`
- `internal_note`
- `original_snapshot` JSON containing method, source, status, carrier, tracking, provider reference, cost, and timestamps
- `external_action_state`: `provider_confirmed`, `staff_confirmed_external`, or `not_required`
- `external_action_note`, nullable
- `status`: `awaiting_replacement`, `completed`, or `closed_without_replacement`
- `created_by`
- `completed_at`, nullable
- timestamps

Only one unresolved correction may exist for an order. Enforce this in the transaction under an order lock and with a database-safe uniqueness strategy appropriate to MySQL.

Shipment attempts remain immutable historical identities. Correction never deletes or overwrites an old attempt.

### Delivery-details amendment

Create an order-scoped amendment audit with:

- order and order-address identity
- before and after snapshots
- reason and internal note
- optional Customer Address Book synchronization result
- actor and timestamp

The current `order_addresses` shipping snapshot becomes the new operational value. Historical shipment/quote snapshots remain unchanged.

### Operational metadata amendment

Tracking number, tracking URL, external courier, driver, and handoff-reference corrections require structured before/after audit evidence. They do not reopen fulfilment unless the dispatch method or provider booking must be replaced.

## Transaction and lifecycle rules

### Start correction

Under a database transaction and `lockForUpdate` on the order, source shipment, and unresolved correction set:

1. Require Shipping Dispatch permission.
2. Reject a delivered shipment or cancelled customer order.
3. Reject a second unresolved correction for the same order.
4. Validate the reason, note, and method-specific staff confirmation.
5. Capture the immutable original shipment snapshot.
6. Append a cancellation/correction shipment event.
7. Mark the old shipment `cancelled`; retain AWB, carrier, tracking, cost, and provider references.
8. Restore the order to `processing`.
9. Clear `tracking_carrier`, `tracking_number`, and `shipped_at` only when they belong to the source shipment.
10. Create the correction as `awaiting_replacement`.

Do not change inventory, payments, discounts, taxes, customer-order totals, or order items. Do not create an Order Cancelled notification.

### Resume fulfilment

An unresolved correction projects one real Pending Fulfilment row. Closing the modal does not lose recovery state. `Resume Fulfilment` opens the existing `ChooseFulfillmentMethodModal` with current order/package/address data.

Fresh EasyParcel quotes are mandatory. Frozen quotes from the cancelled attempt cannot be reused.

### Complete correction

Every successful EasyParcel, External Courier, or Manual Delivery creation calls the generic completion service inside the shipment-creation transaction:

1. Lock the unresolved correction.
2. Require the new shipment to belong to the same order and have a greater sequence.
3. Set `replacement_shipment_id` and `completed_at`.
4. Mark the correction `completed`.
5. Preserve the old shipment and audit history.

If replacement creation fails, the correction remains `awaiting_replacement`; retry does not repeat the old-attempt cancellation.

### Notification rules

- Correction start: no customer notification.
- Replacement with a new public tracking number: send one `Shipping details updated` notification.
- Manual Delivery without public tracking: default no notification; allow staff to opt in with customer-visible copy.
- Notification failure does not roll back shipment state; use the existing failed dispatch audit and manual resend controls.

## Sender and receiver ownership

### Sender Profile

Use the existing path:

`Shipping & Logistics -> Courier Integrations -> EasyParcel -> Manage Connection -> Sender Profile`

Changes affect only future quotes and bookings. A correction caused by sender error must update the profile before requesting the replacement quote.

### Order delivery details

Add:

`Admin Orders -> Order Detail -> Shipping Address -> Amend Delivery Details`

Fields: recipient first/last name, mobile, address lines, postcode, city, state, and country.

- Before shipment: amend directly with audit.
- Active non-delivered shipment: require correction first.
- Delivered shipment or cancelled order: reject.
- Default: update current order only.
- Optional, unchecked: also save to Customer Address Book.

## Protected API contracts

Proposed endpoints:

- `POST /admin/api/shipping/shipments/{shipment}/corrections`
- `GET /admin/api/shipping/orders/{order}/active-correction`
- `POST /admin/api/shipping/corrections/{correction}/resume`
- `PATCH /admin/api/orders/{order}/delivery-details`
- `PATCH /admin/api/shipping/shipments/{shipment}/operational-details`

Laravel owns authorization, validation, locks, transitions, immutable snapshots, idempotency, stable error messages, and audit actors. React must not infer eligibility or complete corrections locally.

The existing EasyParcel recovery remains as provider-specific reconciliation evidence. Generic replacement eligibility and completion move to a provider-neutral correction service.

## UI source boundary

### Existing UI to preserve

- Shipping & Delivery Management page shell, tabs, filters, table, columns, responsive behavior, colors, spacing, typography, and icons
- `ChooseFulfillmentMethodModal`
- `EasyParcelBookingModal`
- External Courier and Manual Delivery forms
- `UpdateShipmentStatusModal`
- `ParcelTrackingModal`

### New or changed UI

1. Replace eligible `Rebook` actions with `Correct Fulfilment`.
2. Add `Resume Fulfilment` for an unresolved correction.
3. Add `CorrectFulfilmentModal`.
4. Add `AmendDeliveryDetailsModal` in Admin Order detail and as a guided correction step.
5. Add small status badges for `Awaiting replacement` and `Correction completed` using existing patterns.

`CorrectFulfilmentModal` must show current attempt identity and frozen facts, reason selection, method-specific staff confirmation, internal audit note, customer notification consequence, hard-block states, loading/failure states, and the primary `Void Attempt & Choose New Method` action.

## Capability mapping

| Feature ID | Owner | MySQL truth | Operational consumer | UI level | Current status | Closure evidence |
|---|---|---|---|---|---|---|
| shipping.correct_method | Operator-managed | `shipment_corrections`, shipments, orders | Replacement fulfilment eligibility | L2 | `ADMIN_UI_GAP` + `BACKEND_CONTRACT_GAP` | Permission, transaction, concurrency, refresh persistence, all-method replacement, browser proof |
| shipping.resume_correction | System-managed plus operator action | unresolved correction | Pending Fulfilment row | L1 within approved pattern | `BACKEND_CONTRACT_GAP` | Refresh/re-login/cross-session persistence and idempotent resume |
| shipping.amend_delivery_details | Operator-managed | order address plus amendment audit | Fresh quote/manual dispatch | L2 | `ADMIN_UI_GAP` + `BACKEND_CONTRACT_GAP` | Validation, authorization, before/after audit, new quote payload |
| shipping.amend_operational_details | Operator-managed | shipment plus structured audit | Tracking display and operations | L2 | `ADMIN_UI_GAP` + `BACKEND_CONTRACT_GAP` | Safe URL/AWB validation, immutable audit, refreshed UI |
| shipping.provider_cancellation_evidence | Internal-only | EasyParcel recovery plus correction attestation | Recovery diagnostics | Existing UI plus L1 state | `REAL_DATA_BINDING` | Provider truth remains distinct from staff attestation |
| shipping.replacement_notification | System-managed | notification dispatch audit | Customer email | L0 | `BACKEND_CONTRACT_GAP` | Correct template, dedupe, failure audit, manual resend |

No capability is `CLOSED` until Laravel/MySQL, authenticated browser, and downstream operational evidence are complete.

## Google AI Studio prompt

Use the latest accepted `jw-ecommerce-dashboard (19).zip` as the absolute visual baseline.

> Extend only the existing Shipping & Delivery Management fulfilment-recovery UI and the existing Admin Order Detail shipping-address region. Preserve every adjacent DOM structure, Tailwind class, color, spacing, typography, icon style, table layout, tab/filter behavior, dark/light theme, and desktop/mobile responsive pattern from the supplied ZIP. Do not redesign the page shell, table, filters, existing Choose Fulfilment Method modal, EasyParcel quote/booking modal, External Courier form, Manual Delivery form, Update Shipment Status modal, or Parcel Tracking modal.
>
> Add a source-faithful `CorrectFulfilmentModal` opened from a new `Correct Fulfilment` row action for any non-delivered EasyParcel, External Courier, or Manual Delivery shipment attempt. This is a presentation prototype only; use local mock state to demonstrate the required views, but do not invent backend rules.
>
> The modal must include:
> - current order number and shipment-attempt identity;
> - current fulfilment method badge: Provider managed or Team managed;
> - courier/service, AWB or handoff reference, shipment status, provider cost, and created timestamp;
> - a reason selector with Wrong fulfilment method, Wrong EasyParcel courier/service, Wrong external courier, Incorrect package information, Incorrect receiver details, Duplicate/accidental dispatch, Customer requested delivery change, and Other operational error;
> - method-specific acknowledgement: EasyParcel official dashboard handled, external courier contacted/AWB handled, or Manual Delivery handoff reviewed;
> - mandatory Internal Audit Note;
> - a clear impact summary: customer Order stays active, inventory/payment/totals remain unchanged, old shipment history is retained, and no Order Cancelled email is sent;
> - primary action `Void Attempt & Choose New Method`;
> - cancel/close action;
> - `delivered` hard-block state directing staff to Return / Replacement / Reshipment;
> - customer Order cancelled hard-block state;
> - provider cancellation unconfirmed state that still permits staff to proceed after acknowledgement;
> - loading, validation, permission denied, mutation failure, awaiting replacement, resume, and replacement-created success states.
>
> After the correction success state, visually hand off to the existing `ChooseFulfillmentMethodModal` without redesigning it. Add a `Resume Fulfilment` action and `Awaiting replacement` badge for a correction that was saved but whose replacement method has not yet been selected.
>
> Add a source-faithful `AmendDeliveryDetailsModal` opened from `Admin Orders -> Order Detail -> Shipping Address -> Amend Delivery Details`, and also reachable from Correct Fulfilment when the reason is Incorrect receiver details or Customer requested delivery change. Include recipient first name, last name, mobile, address line 1, address line 2, postcode, city, state, and country; mandatory amendment reason and internal note; a before-versus-after summary; and an unchecked `Also save to customer address book` option. Include validation, loading, permission, active-shipment guidance, delivered/cancelled blocking, and saved-success states.
>
> For tracking/AWB/driver-only errors, show a `Correct Details` branch that explains a full method replacement is unnecessary and displays the appropriate source-faithful operational-details form with before/after review and audit note.
>
> Demonstrate desktop and 390px mobile layouts. Keep modal content scrollable with headers/actions usable at small heights. Use the ZIP's exact button, badge, card, alert, form, overlay, transition, and icon language. Do not introduce a new visual system or third-party component library.
>
> Return a complete updated ZIP containing the full project source, not screenshots only. Identify the exact new/changed component files in the response. Keep all unrelated accepted regions byte-for-byte unchanged where practical.

## Acceptance evidence required after implementation

- TDD proof for each Laravel transition, authorization denial, validation failure, concurrency collision, and idempotent retry
- MySQL persistence across refresh, re-login, and another browser session
- Old shipment, original snapshot, events, and provider references remain queryable
- Order inventory, payment, and totals remain unchanged during correction
- All three replacement methods complete the same generic correction
- Fresh EasyParcel quote uses updated sender/receiver/package data
- Delivered and cancelled-order blocks are enforced server-side
- Notification dedupe, failure audit, and manual resend remain operational
- Authenticated desktop and 390px mobile comparisons against the accepted Google AI Studio source
- No real provider booking or cancellation during automated tests

## Explicit exclusions

- Cancelling or restoring the customer order
- Return, replacement, reshipment, loss, damage, or insurance-claim workflows
- Split or partial shipment fulfilment
- Multiple warehouses/sender profiles
- Provider refund guarantees or duplicate-charge prevention
- Automatic proof that staff completed a provider-dashboard action
- Any Shipping page redesign outside the named source boundary
