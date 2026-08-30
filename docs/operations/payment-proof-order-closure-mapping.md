# Payment Proof and Order Source Closure Mapping

## Boundary decisions

| Boundary | Decision |
| --- | --- |
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Scope | P0 manual Bank In / DuitNow proof review, Admin Order source details, and read-only shipment-origin display |
| Runtime truth | MySQL `orders`, `order_payments`, `order_status_histories`, `shipments`, and a new append-only payment-proof review record |
| Admin ownership | Authorized payment reviewers verify or reject proof; shipping data is system-managed after booking |
| External integration | No real payment charge or EasyParcel quote/booking is triggered by this change |
| UI authority | ZIP 17, `OrderAdmin.tsx` and `OrderPaymentProofPreviewModal.tsx`, L2; Laravel may replace mock bindings only |
| Lifecycle | Order payment and shipping snapshots are immutable historical facts; review records append only |
| Delete semantics | Proof files and review records are retained; no Admin delete action is introduced |
| Release surface | localhost first; the official production route remains unchanged until local acceptance and explicit sync approval |

## Capability map

| Feature ID | Current source | MySQL truth | Admin/system producer | Service/API contract | Operational consumer | Empty/default | Closure status | Acceptance evidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| payment-proof.secure-storage | public disk path and upload timestamp | private proof object and immutable metadata on `order_payments` | checkout upload | protected proof stream and download routes | authorized Admin viewer | no receipt uploaded | `BACKEND_CONTRACT_GAP` | upload, private existence, authorized stream, denied stream tests |
| payment-proof.review | browser/local mock in ZIP | append-only review rows plus proof status | Admin Verify/Reject | protected review endpoint and transactional service | payment status and order transition | submitted remains pending payment | `BACKEND_CONTRACT_GAP` | approve, reject, bypass denial, refresh persistence tests |
| payment-proof.audit | missing in Laravel | review/view/download audit rows | protected endpoints | safe Admin order resource | internal audit trail | empty audit list | `BACKEND_CONTRACT_GAP` | resource serialization and actor/timestamp tests |
| order.payment-source | incomplete and mislabelled fallback | `order_payments` historical fields | Checkout payment attempt | Admin order resource | Payment Summary | neutral unavailable values | `BACKEND_CONTRACT_GAP` | bank-transfer API JSON contract test |
| order.shipping-source | hidden checkout snapshot and shipment data | `orders.shipping_snapshot`, `shipments` | checkout and shipment booking | Admin order resource | Shipping & Fulfilment Source | not initiated before booking | `BACKEND_CONTRACT_GAP` | unbooked and EasyParcel-booked API JSON tests |

## Explicit exclusions

- No real payment transaction.
- No real EasyParcel quote, booking, cancellation, or webhook call.
- No customer re-upload screen, email/SMS change, or production deployment in this batch.
