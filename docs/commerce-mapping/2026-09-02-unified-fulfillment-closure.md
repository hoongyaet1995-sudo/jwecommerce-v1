# Unified Fulfilment operational closure map

| UI action | Laravel/API owner | MySQL truth | Audit/result |
| --- | --- | --- | --- |
| EasyParcel Compare Rates | `ShipmentApiController::quoteEasyParcel` / existing fulfilment service | `shipment_quotes` | Real provider quote, expiry and service capabilities. |
| EasyParcel Confirm Booking | `ShipmentApiController::bookEasyParcel` / existing fulfilment service | `shipments`, quote/provider snapshots, events | Provider-managed shipment and official tracking/label state. |
| Record External Courier | `ShipmentApiController::dispatch` → `ShipmentService::dispatchTeamManaged` | `shipments` with `fulfillment_method=external_courier` | External AWB, safe tracking URL and required immutable dispatch note. |
| Record Manual / Own Delivery | Same protected dispatch boundary | `shipments` with `fulfillment_method=manual_delivery` | Server-generated internal tracking reference, driver/handoff metadata and immutable dispatch note. |
| Update Team-managed Status | `ShipmentApiController::appendEvent` → `ShipmentService::appendManualEvent` | `shipment_events`, projected shipment/order state | Mandatory note; allowed transition graph; EasyParcel rows rejected. |
| Inspect Tracking | `ShipmentDataService` safe DTO | shipment plus immutable events | Fulfilment method, management mode, courier/driver/handoff, safe external link and dispatch audit. |

Security and integrity rules:

- All writes require existing Shipping permissions and CSRF-protected admin routes.
- Shipment creation and order projection run inside a database transaction with row locking.
- Tracking URLs accept only HTTP/HTTPS and are re-filtered before returning to the UI.
- EasyParcel remains provider-managed; team members cannot fabricate provider lifecycle events.
- Shipment status and order status remain separate; delivery projects the order to delivered, while a later return is retained as shipment/audit truth.
