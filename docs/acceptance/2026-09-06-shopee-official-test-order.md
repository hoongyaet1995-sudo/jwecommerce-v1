# Official Sandbox order creation and real API read

Date: 2026-09-06, approximately 09:03 UTC.

Using the user's prior explicit approval for Sandbox-only test data, created exactly one order through Shopee Console > Test Order > Create Test Order. No Live purchase/payment or shipment arranged.

- Sandbox shop: 227482706 (Local - MY).
- Selected product: JW Sandbox PS5, item 846055463, quantity 1.
- Only available shipping option: 24000 Instant (Sandbox selection, not a shipment booking).
- Official console result: order `260906B667WJ5E`, READY_TO_SHIP, updated 06-09-2026 17:03:31.
- Screenshot emitted in the conversation; console result tab retained.

Readonly API probe through deployed ShopeeClient confirmed:

- get_order_list returned count 1, more false.
- get_order_detail returned the same order ID, READY_TO_SHIP, MYR 1407.31, update_time 1788685411.
- Item 846055463, model 12983425, model SKU JWSTORE1PS5-500GB, quantity 1.
- Platform available stock changed from 100 to 99 after this test order. No JW inventory initialization or deduction was executed.

Probe printed only allowlisted order/product metadata. Tokens and recipient information were not output. The probe does not persist catalog/orders, refresh tokens, enqueue work or issue remote writes.

## Remaining acceptance

This proves a populated real Sandbox order can be read by JW's deployed client. It does not prove database ingestion, idempotent UI refresh, mapping, inventory reservation, fulfillment or AWB. Those gates remain open. Empty-catalog compatibility patch remains local, explaining UNLIST/BANNED parser failures still seen in the deployed probe.

Official workflow reference read in Chrome: https://open.shopee.com/developer-guide/644 (Sandbox Testing V2). Do not create another order just because a later read is delayed; reconcile this known order ID first.
