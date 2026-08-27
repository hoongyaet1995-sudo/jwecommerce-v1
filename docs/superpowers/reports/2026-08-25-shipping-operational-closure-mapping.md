# Shipping P0 operational-closure mapping

## Boundary decisions

| Boundary | Decision |
|---|---|
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Phase/scope | P0 shipping-rates persistence, address-aware checkout quote, selected-rate validation, and immutable order snapshot. Excludes third-party carrier booking/tracking APIs. |
| Runtime truth | MySQL `store_settings.shipping_rates`; order-level immutable delivery fields on `orders`. |
| Admin ownership | Operator-managed shipping rates in the existing Admin Shipping surface. |
| External integration | Simulated-but-operational local delivery configuration; no carrier API in this batch. |
| UI authority | L0 logic only. The existing Google AI Studio checkout delivery matrix and Admin Shipping UI are preserved unchanged. |
| Lifecycle | Rates are realtime until checkout authorization; the selected rate identity, label, calculated charge and rule snapshot are immutable on the order. |
| Delete semantics | Rate removal prevents future selection only; historical order snapshots remain intact. |
| Release surface | Official Admin `/admin/shipping` and Storefront `/checkout`. |
| Cutover approval | User approved implementation on 2026-08-25; acceptance requires Admin -> MySQL -> Checkout -> Order browser proof. |

## Capability mapping

| Feature ID | Business purpose | Business owner | Current source | MySQL truth | Admin/system producer | Service/API contract | Storefront/operational consumer | Eligibility | Empty/default behavior | Time semantics | UI source and level | External dependency | Closure status | Blocking reason | Acceptance evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| shipping.rates.persist | Let operators save shipping rules without unrelated gateway credentials blocking the change. | Operator-managed | MySQL API. | `store_settings.shipping_rates` | Existing Admin Shipping actions. | Protected `PUT /admin/api/commerce-settings/shipping`. | Admin refresh and checkout quote. | Admin role; up to 100 rates. | Preserve the existing rate list; no active eligible rate means no checkout delivery option. | Realtime. | Existing Admin UI, L0. | None. | CLOSED | None. | Auth and credential-isolation feature tests; authenticated Chrome Admin Shipping surface loaded. |
| shipping.quote.address | Show only active shipping rates that actually serve the shopper's address and calculate their own price. | System-managed from operator rules. | Laravel/MySQL quote. | `store_settings.shipping_rates` plus current cart and address fields. | Existing checkout address input / saved-address picker. | Public session-bound checkout quote endpoint and `ShippingConfigurationService`. | Existing checkout Delivery Method & Logistics Matrix and summary. | Active rate whose zone matches normalized country/state. | Existing delivery matrix shows only eligible choices; a prefilled complete address triggers an initial live quote, and each eligible option resolves by its own exact rate ID. Before an address can qualify a zone-specific rate, Cart and Checkout state `Shipping calculated at checkout` rather than a legacy FREE claim; an explicitly saved empty rate set blocks order; an uninitialized installation receives a documented system-default Standard Delivery. | Realtime before authorization. | Existing Google AI Studio checkout DOM, L0 data binding. | None. | CLOSED | None. | Zone, inactive-rate, exact-rate pricing, initial-quote, and no-address/no-false-FREE tests; browser Checkout re-quote after a Selangor address rendered `General Regional Shipping Rate` at RM 8.00 and independent FREE Standard Delivery. |
| shipping.order.snapshot | Prevent later rate edits from rewriting already-authorized delivery terms. | System-managed at checkout authorization. | Laravel transaction and MySQL snapshot. | `orders.shipping_method`, `shipping_label`, `shipping_amount`, `shipping_snapshot`. | `CheckoutService` transaction. | Server-side re-resolution validates the submitted rate ID against the submitted address before creating the order. | Order, fulfillment and success/account consumers. | Submitted rate must still be active and address-eligible inside the transaction. | Invalid/stale rate returns the existing checkout validation flow; no order is created. | Immutable historical snapshot. | No visible new UI, L0. | None. | CLOSED | None. | Feature test proves selected regional rate, persisted snapshot, rejection of a mismatched rate, and stability after later rate edit. |
| shipping.manual_dispatch | Record a real warehouse hand-off without claiming a connected carrier API. | Authorized warehouse staff. | Laravel/MySQL. | `shipments`, `shipment_events`, and the compatibility projection on `orders`. | Approved Google AI Studio OrderAdmin dispatch subsection plus protected Admin shipment API; legacy tracking API delegates to the same service. | `POST /admin/api/shipping/orders/{order}/dispatch`; requires `processing`, unique tracking number, and `shipping.dispatch`. | Admin order-detail summary, Admin Shipping read model, and existing customer order tracking fields. | Super admin or explicitly granted shipping staff. | No provider-generated labels, pickup, wallet, or courier rate facts are rendered. | Shipment and initial `shipped` event are transactional; prior checkout delivery snapshot remains immutable. | ZIP 12 `OrderAdmin.tsx` subsection, source-faithfully bound to the existing Laravel tracking adapter. | None in P0.2. | CLOSED | None. Provider-operated courier capabilities remain dormant. | Chrome on 2026-08-26: Processing order `JW-20260815110325-3676` rejected empty fields inline, then persisted `Manual Warehouse` / `JW-ADMIN-DISPATCH-20260826-001`; refresh retained Shipped summary and audit event. Targeted Feature suite: 9/9, 44 assertions. |
| shipping.event_audit | Keep each warehouse milestone append-only and prevent lifecycle rewinds. | Authorized warehouse staff. | Laravel/MySQL. | `shipment_events` linked to `shipments`. | Protected shipment event API. | `POST /admin/api/shipping/shipments/{shipment}/events`; permits only forward lifecycle transitions. | Admin Shipment Event Audit Trail; delivery also projects to order status. | `shipping.update_status`; status must follow dispatched -> in transit -> out for delivery -> delivered, with permitted exception branches. | No fabricated webhook payloads or provider verification claims. | Immutable event history; later events never replace initial dispatch. | Existing tracking/audit DOM, L0 binding. | EasyParcel webhooks dormant. | CLOSED | None for event auditing. | Chrome recorded `in_transit`; MySQL holds two ordered events for shipment 1; backward transitions have a 422 test. |

## Trace

```text
Admin Shipping action
-> protected Laravel shipping-settings endpoint
-> MySQL store_settings.shipping_rates
-> ShippingConfigurationService exact-rate view model
-> Cart non-binding free-shipping progress hint from active rate thresholds
-> Checkout delivery matrix / pricing summary
-> CheckoutService re-validates and snapshots the selected rate
-> orders immutable delivery fields and shipping_snapshot
```

## Closure evidence

- Closed: `shipping.rates.persist`, `shipping.quote.address`, `shipping.order.snapshot`.
- Accepted system-managed default: only an installation with no `shipping_rates` document receives the legacy Standard Delivery fallback; a saved empty document intentionally provides no eligible rate. A zone-specific saved rate with no customer address is deliberately not estimated from legacy settings.
- No new L2/L3 UI gap: all required customer states use the approved existing delivery/address matrix.
- P0 exclusions: carrier-label purchase, live courier tracking, external carrier rate APIs, and shipping analytics.
- Evidence: targeted feature suite, local migration `2026_08_25_000200_add_shipping_snapshot_to_orders`, authenticated Chrome Admin Shipping and Checkout checks on 2026-08-25.
- P0.2 status: canonical manual dispatch, role enforcement, durable event audit, source-faithful Admin Order dispatch controls, and Admin shipment read-model are live. EasyParcel credentials, live rates, label purchase, pickup booking, webhooks, smart routing, wallet and courier-billing reconciliation are dormant and their old local-state actions now report unavailable rather than mutate browser data.
