# RMA Operational Closure Mapping

## Boundary decisions

| Boundary | Decision |
| --- | --- |
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Runtime truth | MySQL return requests, evidence and audit events |
| Admin ownership | Employee-managed lifecycle after customer submission |
| External integration | Refund ledger and replacement order are simulated-but-operational; no provider refund or courier booking claim |
| UI authority | Approved Google AI Studio Storefront `(15)` and Admin `(24)` selected regions |
| Lifecycle | Realtime current state plus append-only events |
| Release surface | Official localhost routes; production cutover requires later sync approval |

| Feature | Producer -> service -> truth -> consumer | Eligibility | Status before implementation | Acceptance evidence |
| --- | --- | --- | --- | --- |
| Customer request | Purchase History -> ReturnRequestService -> `return_requests`/evidence/events -> both surfaces | owned delivered item, within 30 days, remaining quantity | `CLOSED` | `ReturnRequestOperationalClosureTest` owner/eligibility/quantity/file/persistence assertions |
| Staff review | Admin drawer -> ReturnRequestService -> request/event -> Storefront status | requested only | `CLOSED` | protected Admin route plus transactional transition test |
| Warehouse receipt | Admin drawer -> ReturnRequestService -> request/event -> Storefront status | approved only | `CLOSED` | received quantity, condition and note persistence test |
| Refund resolution | Admin drawer -> ReturnRequestService + refund ledger -> refund/request/event -> both surfaces | received refund request | `CLOSED` | completed `order_refunds` assertion and DTO response |
| Replacement resolution | Admin drawer -> ReturnRequestService -> new order/request/event -> both surfaces | received replacement request, same SKU snapshot | `CLOSED` | replacement order and same-SKU item database assertions |
| Evidence storage | owner submission -> private local disk metadata | request ownership | `SYSTEM_MANAGED` | MIME/size/count validation and private-disk persistence test; evidence viewing is intentionally not exposed in this first simple workbench |

## Local evidence

- PHP: 26 tests, 217 assertions passed across RMA closure, account adapter and Admin order regressions.
- Admin: TypeScript lint and Vite production build passed.
- Storefront: Vite production build passed; Node 20.17 emitted the existing Vite version warning.
- Database: migration `2026_09_03_130000_create_return_request_tables` ran locally.
- Routes: six customer/Admin RMA mutation routes registered.
- Release: localhost only; GitHub and Hostinger remain unsynchronized pending explicit release approval.

Explicit exclusions: staff-created requests, provider refunds, courier booking, automatic email/WhatsApp claims, return-label generation, configurable RMA policy UI.
