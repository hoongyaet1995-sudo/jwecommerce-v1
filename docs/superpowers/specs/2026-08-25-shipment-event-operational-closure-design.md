# Shipment and Event Operational Closure Design

## Status

Proposed and approved in principle on 2026-08-25. This document is an implementation contract, not a claim that carrier APIs are already live.

## Boundary decisions

| Boundary | Decision |
| --- | --- |
| Convergence mode | `OPERATIONAL_CLOSURE` |
| First delivery slice | Real manual fulfillment, shipment records and auditable tracking events. |
| Provider scope | Prepare an adapter boundary for EasyParcel; do not call its API, store credentials, or present it as connected in the first slice. |
| Runtime truth | MySQL orders, shipments and shipment events. `orders.shipping_snapshot` remains the immutable checkout-rate fact. |
| Current real bridge | Existing protected order tracking endpoint writes carrier, tracking number and an order-status history event. |
| UI authority | Existing Google AI Studio Shipping Dashboard, Order detail and Account tracking structures only. No visual redesign or new information hierarchy. |
| Shipment cardinality | One active outbound shipment per order in P0.2; schema supports later parcel splitting without changing the order snapshot. |
| Historical semantics | Shipment/event records are append-only operational evidence. Editing a rate or a carrier configuration must never rewrite a shipped order. |
| External APIs | EasyParcel remains `DORMANT` until credentials, sandbox/production mode, provider tests and webhook verification have been separately accepted. |

## Problem and current state

Checkout shipping configuration is already operational: active rate rules are saved in MySQL, address eligibility is checked server-side, and the selected rate is snapshotted on the order. The Admin Shipping dashboard is different: courier configurations, parcels, waybills, routing rules, simulated webhooks, pickup requests and billing reconciliations currently initialise from React fixtures and persist in browser localStorage.

The application already supports a small real manual-dispatch path from Admin Orders: a processing order can receive a carrier and tracking number; Laravel writes the order tracking fields and an `OrderStatusHistory` record. This slice makes that path the canonical shipment model and makes the Shipping dashboard consume it instead of its fixture parcels.

## Architecture

### Canonical entities

`shipments`

- `id`, `order_id`, `sequence`, `source` (`manual` initially; `easyparcel` later), `carrier_code` nullable, `carrier_name`, `service_code` nullable, `service_name` nullable.
- `tracking_number` nullable/unique when present, `status`, `shipping_cost`, `currency`, `label_path` nullable, `provider_reference` nullable.
- `dispatched_at`, `delivered_at`, `cancelled_at`, `created_by`, timestamps.

`shipment_events`

- `id`, `shipment_id`, canonical event `status`, `occurred_at`, `location` nullable, `description`, `source` (`manual`, `provider`, `webhook`), `external_event_id` nullable, `created_by` nullable, timestamps.
- A provider event ID is unique within a shipment so future webhooks are idempotent.

The migration backfills neither synthetic fixtures nor invented historical events. Existing orders with tracking data remain readable through a compatibility projection; when an operator performs a later action, the system may create the corresponding manual shipment transactionally.

### Services and APIs

`ShipmentService` owns transactions, eligibility, status transitions and the projection back to the existing order fields:

1. Only an authorized operator can create/update a shipment.
2. Manual dispatch requires a processing order, a non-empty carrier and a non-empty tracking number.
3. Creating/dispatching a shipment appends an event and updates the existing `orders` tracking projection for Account and legacy order consumers.
4. Delivery, exception and cancellation transitions append events; no event row is overwritten.
5. Duplicate tracking numbers and invalid lifecycle transitions return stable Laravel validation errors.

Protected `/admin/api/shipping/*` endpoints expose a shipment read model for the existing Shipping dashboard and mutation endpoints for manual dispatch/status updates. The existing `/admin/api/orders/{order}/tracking` route becomes a compatibility caller of the same service, preventing two lifecycle implementations.

### Existing UI binding

- **Fulfillment & Waybills:** receive real shipments joined to actual orders; no fixture parcels or randomly generated tracking numbers.
- **Tracking & Telemetry:** shows persisted manual events. Its existing “simulator” control is removed from operational use or clearly routed to a real manual event action only when the accepted source already supports that wording.
- **Overview:** counts are calculated from persisted shipments only. Provider/SLA/wallet claims remain hidden or `DORMANT` until a provider exists; they must not report fictional active APIs.
- **Account / order tracking:** consumes the order compatibility projection first; it can later use detailed shipment events without rewriting the approved customer DOM.
- **Thermal label:** not enabled as a carrier label in P0.2. If retained for internal packing, it is labeled/documented as an internal packing slip and has no fabricated waybill/barcode claim.

## Authorization and audit

Introduce explicit permissions before exposing Shipping mutations:

| Permission | Purpose |
| --- | --- |
| `shipping.view` | Read shipments and their non-sensitive events. |
| `shipping.dispatch` | Create a manual shipment and add carrier/tracking data. |
| `shipping.update_status` | Add manual lifecycle events, including delivery exceptions. |
| `shipping.configure` | Later manage courier connections and rate configurations. |
| `shipping.provider_webhooks` | System-only route capability, never a browser role. |

Every mutation records actor, previous/new status and human note. Provider raw payloads are a later P1 concern; they are not invented during manual fulfillment.

## Mapping contract

| Feature ID | Producer -> consumer | Truth owner | Status after P0.2 | Acceptance evidence |
| --- | --- | --- | --- | --- |
| `shipping.rate.quote` | Admin rates -> Checkout -> order snapshot | MySQL `store_settings` + `orders` | `CLOSED` already | Existing checkout/rate tests. |
| `shipping.manual.dispatch` | Admin Shipping/Orders -> ShipmentService -> Account tracking | MySQL `shipments`, events and order projection | `BACKEND_CONTRACT_GAP` | Cross-session DB/browser dispatch proof. |
| `shipping.lifecycle.audit` | Authorized admin -> event append -> tracking timeline | MySQL `shipment_events` | `BACKEND_CONTRACT_GAP` | Append-only and permission tests. |
| `shipping.dashboard.readmodel` | Shipment query -> existing dashboard cards/tables | MySQL shipments/orders | `BACKEND_CONTRACT_GAP` | Refresh/re-login shows no fixture parcels. |
| `shipping.awb.provider` | EasyParcel -> label/tracking | Provider adapter + MySQL | `DORMANT` | Separate provider acceptance. |
| `shipping.webhooks` | Provider callback -> event append | MySQL events | `DORMANT` | Signed/idempotent callback tests. |
| `shipping.pickups`, `shipping.reconciliation`, `shipping.smart-routing` | Provider/ops workflow -> dashboards | Provider data + MySQL | `MOCK_ONLY` | Explicitly excluded from P0.2. |

## EasyParcel follow-up boundary (P1)

After P0.2 browser acceptance, implement one `CarrierAdapter` vertical slice:

1. Encrypted EasyParcel credential storage outside React/localStorage, with explicit sandbox/production mode and secret redaction.
2. Backend-only health check and quote/booking request; no client-side credential or faux ping.
3. Provider booking returns a real reference and label; update the same shipment record.
4. Verified, idempotent webhook events append to `shipment_events`.
5. Browser acceptance proves one actual sandbox/production test shipment end-to-end before enabling other courier cards.

No real credential is needed to implement P0.2. Credentials will be requested only immediately before the approved P1 connection test, and will never be placed in chat, source code, compiled Admin assets or logs.

## Acceptance plan

1. Admin creates a real checkout order, marks it processing, dispatches it manually and refreshes/relogs in.
2. A shipment and an initial tracking event exist in MySQL; carrier/tracking projection remains visible in existing Admin Orders and customer Account.
3. Authorized staff can append valid events; unauthorized users receive 403; invalid transitions and duplicate tracking numbers receive stable validation errors.
4. Delivery and exception events remain historically visible after rate edits and after a separate browser session.
5. Shipping dashboard contains only real shipments/orders in its operational views; fixture couriers, random AWBs, simulated webhooks, wallet and reconciliation claims remain excluded/dormant.
6. Run targeted Feature tests and Chrome browser proof for Admin -> MySQL -> Account tracking.

## Explicit exclusions

- EasyParcel API calls, pickup booking, label purchase/download, real carrier rates, webhook processing, wallet top-up, COD remittance, billing reconciliation, automated smart routing, multi-parcel splitting and courier performance analytics.
- Any new L2/L3 visual surface. If the existing accepted source lacks a needed validation, empty or provider setup state, that is a source/UI gap and requires a Google AI Studio export before visible implementation.
