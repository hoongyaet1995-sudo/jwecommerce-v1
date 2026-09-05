# P1-016 Browser Acceptance — Live Development Evidence

Date: 2026-09-03
Target: `https://ecom.jwaistudio.online` (development-stage site, explicitly authorized for controlled acceptance)
Source commit expected on live: `6b52ab4cca017dc46f576c6e5ba16bf80a6d9049`

## Status

**FAILED — P1-016 remains `[~]`.** The guest desktop and mobile evidence proves several operational paths, but it also exposes cross-surface source-of-truth failures. No application code was changed during this acceptance run.

## Controlled test data and safety boundary

- Product: `Solis Modular Desk Shelf System`, 1 × `SKU-SOLIS-WAL-STD`.
- Valid existing coupon: `WELCOME10`.
- Guest test order: `JW-20260903022736-7626`, created using the Bank / DuitNow pending-verification path.
- Test email: `p1-acceptance-20260903@example.test`; test-only address.
- No payment was made, no payment proof uploaded, and no courier, pickup, or fulfilment action was triggered.
- An Admin attempt to create `P1TEST20` was made through the visible Promotions form. The UI left the form open and did not render the campaign afterward; it is evidence of a non-persisting/no-feedback Admin producer, not a valid campaign fixture.

## What passed

| Journey | Evidence |
| --- | --- |
| Guest cart persistence | Adding Solis from PDP initially left the header count stale, but `/cart` subsequently showed one persisted Solis line at RM 220.00. |
| Valid coupon calculation | `WELCOME10` applied in Cart and Checkout: subtotal RM 220.00, discount RM 22.00, GST RM 11.88, total RM 209.88. |
| Server-side delivery recalculation | After test address entry, Ninjavan FREE and Express RM 8.00 options appeared; the selected free rate was reflected in the order snapshot. |
| Pending-payment authorization | Checkout created `JW-20260903022736-7626` with payment method `bank transfer`, status `pending payment`, and total RM 209.88. The confirmation accurately stated that settlement is still pending. |
| Failure validation | Blank guest order-tracking submission rendered required-field messages without exposing order data. |
| Responsive spot check | At 390 × 844, Contact, coupon input, and the authorize button were visible; no browser console error was observed in the tested checkout interaction. |
| Local regression coverage | `CustomerCouponClosureTest`, `OriginalAccountWorkspacePreviewTest`, and `CheckoutCommerceRulesTest`: **36 passed, 223 assertions**. |

## Failures blocking P1-016

| Severity | Finding | Browser evidence / impact |
| --- | --- | --- |
| P1 | Admin Promotions is not a reliable producer | Deploying a controlled `P1TEST20` rule did not close the dialog, show feedback, or add a campaign card. The live customer flow can use the pre-existing `WELCOME10`, but Admin-to-storefront campaign closure is not proven. |
| P1 | Admin Orders is a stale live stream | `JW-20260903022736-7626` was absent from an already-open Orders page, but appeared after a manual browser refresh. The order is persisted; the operational defect is that the Admin client only fetched the list at page load. |
| P1 | Product/variant snapshot disagreement | Cart main line showed `SKU-SOLIS-WAL-STD`; the simultaneous cart drawer showed `SKU-SOLIS-WAL-S` for the same product. Checkout and confirmation used `SKU-SOLIS-WAL-STD`. |
| P1 | Public catalog contains test/unrelated inventory | `/shop` exposed `Testing 001`, `PS5 Pro`, and `Razer Bluetooth Mouse RZ-250`. `PS5 Pro` also advertises `SAVE -261%`, a mathematically invalid public promotion. |
| P1 | PDP add-to-cart feedback is stale | Immediately after PDP add, the button disabled but the header remained `Cart (0)` until navigating to Cart. |

## Remaining matrix gate

Signed-in customer acceptance passed after the explicitly authorized storefront login: the account dashboard loaded the owner-scoped profile, six persisted order-history entries, address count, and three MySQL-backed available coupons (`MERDEKA26`, `ATELIER50`, `WELCOME10`). Guest order tracking’s successful email-verification path is not run because it would send an external email; only its safe validation branch is proven.

## Local repair verification (not yet deployed)

The canonical XAMPP working tree now contains a bounded Admin Orders poller. It uses the existing `/admin/api/orders` MySQL read endpoint only while the current Admin route is Orders and the browser document is visible. The cadence is 15 seconds; no order, payment, shipment, or notification mutation occurs.

- The new regression test first failed with one initial `fetchOrders` request (the pre-fix behavior), then passed after the poller was added; it verifies the next visible Orders interval replaces the list with an incoming order.
- `npm run lint` completed without TypeScript errors.
- Focused Admin tests: **12 passed** (`AdminContext.orderMutations` and `orderData`).
- `npm run build` completed and generated the Admin production bundle.
- Browser smoke: authenticated Chrome on `http://127.0.0.1:8500/admin/orders` rendered the compiled Orders page and its MySQL-backed list without a UI structure change.

## Next repair order

1. Deploy and browser-verify the bounded Orders polling repair: visible Orders page refreshes `/admin/api/orders` every 15 seconds without a full reload.
2. Trace the Admin Promotions producer used by the deployed React Admin host; the controlled `P1TEST20` creation attempt did not render a persisted campaign or an error.
3. Remove/disable development catalog records and correct the negative discount calculation.
4. Make cart drawer and cart page consume one immutable variant snapshot; update the post-add header state.
5. Re-run the full guest and signed-in desktop/mobile matrix after fixes, then update this report and only then consider P1-016 `[x]`.
