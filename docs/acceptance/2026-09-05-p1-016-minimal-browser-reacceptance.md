# P1-016 minimal browser re-acceptance — 2026-09-05

## Scope and safety

- Mode: `AUDIT_ONLY`.
- Environment: local Laravel runtime at `http://127.0.0.1:8500`.
- Baseline source commit: `0cf752ab6492d7a2643e32382b4901c482f317b7`.
- No payment gateway, payment-proof approval, courier booking, fulfilment mutation, or production action was triggered.

## Evidence collected

### Guest checkout and order truth — pass

- A guest selected the non-default Solis XL SKU `SKU-SOLIS-WAL-XL` (RM 275.00); the PDP, header count, cart drawer, checkout, success page, and persisted order item agreed on the selected SKU and quantity.
- Active coupon `MERDEKA20` reduced the order by RM 20.00. The shopper-facing total was RM 270.30, comprising RM 275.00 subtotal, RM 0.00 shipping, and RM 15.30 tax.
- A controlled COD order `JW-20260905094937-2161` was created. Database reconciliation confirmed currency `MYR`, `pending_payment`, and the same cent values (subtotal `27500`, discount `2000`, shipping `0`, tax `1530`, total `27030`).
- The freshly opened Admin Orders page showed that order at the top of the list. This proves cross-surface persistence; it does not independently re-prove the existing-page polling interval.

### Visible acceptance blocker — fail

The currency display renderer is inconsistent with the stored order currency:

- Admin Orders showed the new MYR order as `$270.30`.
- The signed-in customer's Purchase History showed records as `$412.34 MYR`, with line items such as `$389.00 MYR`.
- In the same customer order detail, the payment-proof panel correctly showed `RM 412.34 MYR`, proving the data and current storefront currency context are not the cause.

Read-only source tracing confirms hard-coded dollar formatting in both surfaces:

- `resources/admin/src/components/orders/OrderAdmin.tsx` renders order totals with a literal `$`.
- `resources/views/storefront/account.blade.php` renders order-history totals, line items, and invoice values with a literal `$`.

This is a customer- and employee-visible pricing defect. P1-016 remains partial until both use the existing MYR/currency formatter and a short browser rerun proves the corrected output.

### Minor visual regression — non-blocking for the money fix

The Checkout console reported unresolved Lucide names `building-2` and `banknote`; these can cause the Bank In and COD visual icons to be absent. The checkout flow itself completed successfully.

## Evidence artifacts

- `output/playwright/p1-016-2026-09-05/guest-success-mobile.png`
- `output/playwright/p1-016-2026-09-05/account-history-mobile-currency-mismatch.png`

## Smallest closure recommendation

1. Replace hard-coded currency literals in the Admin Orders and storefront Account history/invoice renderers with the canonical currency formatter.
2. Map the two unavailable Lucide icon names to installed icon names.
3. Re-run one controlled guest COD checkout while Admin Orders is already open, then confirm the poller inserts the order, Admin displays `RM`, and signed-in mobile Purchase History displays `RM` consistently.
