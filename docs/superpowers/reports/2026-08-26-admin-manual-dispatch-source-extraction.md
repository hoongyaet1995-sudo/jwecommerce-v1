# Admin manual-dispatch source extraction manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (12).zip` (private local source).
- ZIP SHA-256: `933CDB4FC22E8C53D0E47A824A27C0EBDA5A92AFD73032E4D08B646966214775`.
- Target Laravel project and route: `C:\xampp\htdocs\jwstudio-ecommerce`, protected Admin `/admin/orders`.
- Requested region/function: the conditional manual-fulfillment subsection inside the existing **Update Order Status & Dispatch Customer Notification** card.
- Explicitly excluded: carrier-provider API setup, AWB purchase, pickup booking, webhooks, wallet, routing, billing, and customer-account redesign.
- Acceptance intent: Admin -> Laravel -> MySQL shipment/event -> refreshed Admin order detail.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/orders/OrderAdmin.tsx` | `26E8202623D1DEC714532B713CBEB5BAAD98232B09E47E233AE919551B7AC4D5` | Source DOM, input validation, dispatched summary | `resources/admin/src/components/orders/OrderAdmin.tsx` | selected |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| `useAdmin().updateOrderTracking` | Existing authenticated adapter already delegates to the canonical Laravel shipment service. | existing-compatible | retained; handler awaits persisted response rather than mutating local mock state |
| `Order`, `FulfillmentStatus`, `Truck`, `CheckCircle2` | Existing component view model and source icons. | direct | retained |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| Processing order | Existing order detail modal | Carrier and tracking controls appear beneath the status row. | available adapter |
| Empty submit | `Record dispatch` | Existing inline red validation for both required fields. | local UI validation |
| Valid submit | `Record dispatch` | Existing Dispatched summary replaces inputs only after Laravel returns an updated order. | available adapter |
| Refresh | Reopen same order | Carrier, tracking, Shipped status and audit history are returned from MySQL. | available adapter |

## Approval record

- Initial source boundary approved by user on 2026-08-26.
- Official Admin route cutover approved by prior user authorization for P0.2 manual fulfillment.
