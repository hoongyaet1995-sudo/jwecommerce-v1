# Manual refund browser acceptance - 2026-09-05

Scope: localhost UI clicks, controlled test order, existing manual refund flow. No real payment, provider refund API, or carrier booking. No release in this turn.

## Evidence

- Order `JW-20260905132638-2583`; test customer `rma-manual-20260905@example.test`.
- Nondefault SKU `SKU-SOLIS-WAL-XL`, quantity 1, MYR 275 merchandise + 16.50 tax = 291.50.
- Admin Orders stayed open during checkout; automatic insertion observed without refresh. Screenshot 05 was captured again after test fulfillment, so it is not independent proof of insertion timing.
- Simulated COD settlement/manual delivery explicitly labelled DEMO in audit notes. Automatic notification audit entries were created; no assertion of external mailbox delivery.
- Customer requested refund; staff approved, received 1 restockable item, recorded DEMO external reference; customer resolved state verified after reload.
- DB read-only check: refund amount 27500, status completed; payment partially_refunded; RMA resolved_refund; events requested/approved/received/resolved_refund.
- Screenshots `output/playwright/refund-manual/01-...15-...png`; PDF `output/pdf/JW-Ecommerce-Manual-Refund-User-Manual-zh-CN.pdf`.

## Findings - not fixed in this audit

1. Admin renders partially_refunded as Pending despite correct backend payment status. Visible employee-facing defect.
2. ReturnRequestService calculates unit_price * requested quantity; no actual external refund amount field or tax/discount allocation. Test records 275 instead of order total 291.50. Do not describe as full order refund.
3. Receive transition stores condition only; no inventory movement in the service or ReturnRequest model. Restockable is not evidence of stock replenishment.
4. OrderDataService paymentGatewayLabel lacks cod, falling back to Credit Card.
5. Checkout quote refresh fallback selector targets Coupon Apply rather than final submit; observed label change and mobile overflow.

PDF is a demonstration acceptance edition, not an approved production financial SOP. Actual gateway refund, rejection, replacement, evidence access, concurrency and multicurrency refund were not browser-tested in this round. P1-016 and manual-refund closure must not be upgraded to fully accepted based solely on the happy path.
