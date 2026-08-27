# Order Tracking P0 operational-closure mapping

## Boundary decisions

| Boundary | Decision |
|---|---|
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Runtime truth | MySQL orders, customers, users, status histories, shipments, shipment events and verification-attempt records. |
| Admin ownership | Order and shipment lifecycle remains operator-managed through existing protected Admin APIs. Guest verification is system-managed. |
| External integration | Email verification is server-side; live public closure requires a configured transactional mailer. Carrier integration stays simulated-but-operational/manual. |
| UI authority | Approved ZIP (12) `OrderTrackingPage.tsx`, L3. |
| Lifecycle | Order and shipment facts are immutable/auditable; verification attempts expire and are single-use. |
| Release surface | Isolated `/order-tracking/preview` before public `/order-tracking` replacement. |

## Capability mapping

| Feature ID | Business purpose | Owner | Truth | Producer | Consumer | Closure status | Acceptance evidence |
|---|---|---|---|---|---|---|---|
| `tracking.lookup.privacy` | Start verification without disclosing whether an order or account exists. | System-managed | verification attempts + rate limiter | `OrderTrackingService` | source lookup/verify state | `BACKEND_CONTRACT_GAP` | exact/mismatched lookup responses are visually indistinguishable; throttle test. |
| `tracking.code.verify` | Restrict an order payload to an email-verified visitor. | System-managed | hashed, expiring, single-use code | service + mail notification | source six-cell state | `BACKEND_CONTRACT_GAP` | request, bad/expired/used, success and session authorization tests. |
| `tracking.result.realtime` | Display real current order/shipment status after verification. | System-managed | orders/history/shipments/events | existing Admin operations and Shipment Service | source result timeline/details | `BACKEND_CONTRACT_GAP` | Admin transition -> refresh -> customer result proof. |
| `customer.guest.reconcile` | Preserve a single Customer owner when checkout email belongs to a later/current account. | System-managed | customers/users/orders | CheckoutService + CustomerAuth | My Account | `BACKEND_CONTRACT_GAP` | guest checkout, registration and fresh-login tests prove one customer/order relationship. |
| `tracking.source.fidelity` | Preserve approved tracking page layout and interactions. | Presentation-only | ZIP source | Blade/JS adapter | preview route | `STOREFRONT_UI_GAP` until browser comparison | desktop/mobile source vs preview screenshots. |
| `tracking.email.delivery` | Deliver verification code to the checkout email. | System-managed external adapter | configured mail transport/log delivery | Laravel Mail | shopper inbox | `BACKEND_CONTRACT_GAP` | Mail fake tests now; real mailbox proof needed before operational public closure. |

## Trace

```text
Guest lookup form
-> throttled OrderTrackingController
-> OrderTrackingService validates exact order/email and persists hashed attempt
-> configured Mail transport sends code without exposing lookup outcome
-> session-scoped verification capability
-> OrderTrackingReadModel loads order/history/shipment events
-> source-faithful verified result

Admin Orders / Shipment events
-> OrderOperationsService / ShipmentService
-> MySQL orders + histories + shipments + events
-> same read model on refreshed verified tracking page
```

## Blocking conditions

- Current `.env` uses `MAIL_MAILER=log`; this is sufficient for automated contract tests but not proof that a guest shopper receives a code.
- Official route cutover is unapproved until preview browser evidence and a configured mail-delivery acceptance are complete.
