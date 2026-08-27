# Order Tracking Operational Closure Design

## Status

Approved UI source supplied by the user on 2026-08-27. This is the implementation contract for a source-faithful preview and its real Laravel/MySQL adapter; it is not an official-route cutover claim.

## Boundary decisions

| Boundary | Decision |
| --- | --- |
| Convergence mode | `OPERATIONAL_CLOSURE` for the data contract; source-faithful preview precedes public-route cutover. |
| UI authority | ZIP `universal-e-commerce-system-&-schema-suite (12).zip`, selected `OrderTrackingPage.tsx`, L3. Existing Laravel shared nav/footer are compatible shell dependencies. |
| Lookup identity | Exact normalized order number plus exact normalized checkout email. Telephone is delivery data only, never a lookup factor. |
| Verification | A six-digit code is generated server-side, stored only as a hash, expires after 10 minutes, and is single-use. The lookup response is privacy-safe regardless of whether a matching order exists. |
| Guest ownership | Checkout resolves a pre-existing `Customer` by normalized email, otherwise creates a guest Customer. A later registration with the same normalized email attaches the existing guest Customer to the newly created User instead of creating a duplicate Customer. |
| Customer ownership | Logged-in checkout and matching-email guest checkout both store the order under the one Customer record. Account reads Customer-owned orders only. |
| Runtime truth | MySQL `orders`, `order_status_histories`, `shipments`, `shipment_events`, `customers`, `users`, and a new `order_tracking_verifications` table. |
| Status truth | `orders.status` is the current lifecycle authority. Status history and shipment events are append-only evidence; the public view never invents future milestones or delivery dates. |
| External integration | Email dispatch is server-side. Local `MAIL_MAILER=log` is acceptable for automated proof only; customer-operational release requires configured SMTP/transactional-mail delivery and a real recipient test. Carrier APIs remain excluded. |
| Historical semantics | Order totals, items, taxes, delivery address, payment and shipping values remain immutable order snapshots. |
| Official route | Preview at `/order-tracking/preview`; replacing the public `/order-tracking` route requires explicit post-preview acceptance. |

## Source variance and exclusions

The supplied source includes `DEFAULT_ORDER_HISTORY`, demo autofill chips, synthesized order addresses/items/payments/tracking events, and a rule that accepts any six digits except `000000`. These are source mock mechanisms, not approved business facts. They are excluded from the operational adapter while the surrounding source DOM, classes, copy, responsive structure, code-cell behavior, resend countdown and result layout remain the visual source.

## Public flow

1. A shopper submits order number and email. Laravel normalizes both values, applies a lookup throttle, creates a verification attempt only if the exact order/email pair exists, and responds with the same verification-screen transition either way.
2. Laravel emails a six-digit code only for a valid pair. The browser always sees a masked version of the submitted email and never learns whether the pair exists.
3. The shopper submits six digits. Invalid, expired, consumed or mismatched attempts produce the ZIP's generic privacy-safe error. A valid code marks the attempt verified and stores only that verification ID in the session.
4. The result endpoint accepts only that session-scoped verified attempt and returns the immutable order payload. The result has no public direct order-ID endpoint.
5. Admin Order Operations and Shipment Service continue to be the sole writers of order and shipment statuses. A refresh of the verified result reads the current MySQL lifecycle and append-only events.

## Status mapping

| Real status/event | Customer-facing title | Detail rule |
| --- | --- | --- |
| `pending_payment` | Payment Pending | Read current payment state; never call it authorized or paid. |
| `paid` | Payment Confirmed | Only after persisted paid transition/payment state. |
| `processing` | Preparing Your Order | Uses real status-history timestamp/note only. |
| `shipped` / shipment dispatched | Handed to Carrier | Carrier and tracking number appear only when persisted. |
| shipment `in_transit` / `out_for_delivery` | In Transit / Out for Delivery | Uses actual shipment event description, time and location. |
| `delivered` / shipment delivered | Delivered | Uses real delivered event. |
| `cancelled`, failed delivery, returned | Order Update | Uses the actual persisted note; no invented recovery promise. |

## Required browser acceptance

1. Create a real order using checkout and prove its row/history/payment snapshots in MySQL.
2. Request verification for its exact order number and email; show the ZIP verification state without revealing order data first.
3. Obtain the code only through the configured test mail transport, verify it, and prove the result reads the real order.
4. From Admin, transition to processing, dispatch with a real persisted tracking number, append a shipment event, and refresh the verified tracking page to prove the changed lifecycle is visible.
5. Verify a mismatched email/order, expired/incorrect code, unauthenticated result request, and rate-limited request do not disclose private data.
6. Register/login with the checkout email and prove the same Customer's order appears under My Account after a new browser session.

## Explicit exclusions

- Telephone-only lookup, SMS OTP, carrier portals, external carrier tracking, estimated delivery promises, live payment-provider changes, refunds/cancellations requested by guests, and bulk guest order-history search.
- Any demo preset, generated order, default customer address, arbitrary code acceptance, or browser/localStorage ownership.
