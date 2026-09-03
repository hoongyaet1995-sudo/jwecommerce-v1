# Shipment Action Capability Design

## Scope

Converge Fulfillment & Waybills row actions without changing the approved Google AI Studio DOM, Tailwind classes, icons, or menu layout.

## Canonical state

The shipment API owns four orthogonal values:

- `lifecycleStage`: `pending_fulfilment`, `ready_to_ship`, `in_transit`, `delivered`, or `action_required`.
- `labelState`: `not_required`, `pending`, `available`, or `failed`.
- `recoveryState`: `none`, `replacement_required`, `rebooking_ready`, or `review_required`.
- `capabilities`: explicit booleans consumed by the admin UI.

## Rules

- A first-attempt pending order has `fulfil=true`; it has no correction, tracking, or print actions.
- A pending replacement is `action_required`, has `resume=true`, and must not count under Pending AWB.
- Correct Fulfilment is available only for an existing, non-terminal shipment before physical handoff (`ready_to_ship`).
- Direct address amendment is available before initial fulfilment. For a ready provider booking it is routed through replacement rather than silently mutating the provider shipment.
- Tracking timeline and copy tracking require actual shipment history/tracking data.
- Provider refresh is available only for non-terminal EasyParcel shipments.
- Print is available only when a label exists; a manual shipment has `labelState=not_required`.
- Delivered rows never show AWB Pending and have no operational primary action.
- Exception actions are subtype-specific. Resume exists only when correction or recovery is explicitly ready.

## UI contract

Keep the existing single-line primary-action plus `...` menu structure. Change only conditional rendering and status/tab copy. Rename the exception tab to `Action Required`; keep current styling.

## Safety

No real provider booking, cancellation, status update, payment, or shipment mutation is part of this change.
