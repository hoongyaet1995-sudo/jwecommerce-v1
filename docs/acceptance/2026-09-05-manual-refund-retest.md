# Manual refund browser retest

Local only, 2026-09-05. No real funds or provider booking. User approved retest and manual update, not release.

- New order created by customer browser checkout: JW-20260905141607-2819, SKU-SOLIS-WAL-XL x1, MYR291.50 including16.50 tax.
- Simulated COD settlement and manual delivery through Admin. DEMO-labelled tracking and notes; notification audit entries may be generated to the test address.
- Customer requested RMA-2026-000003 through Purchase History.
- Staff approved and recorded restockable receipt via UI. Read-only MySQL stock evidence: variant7 before receipt7, after receipt8.
- With reference/note filled but amount blank, confirmation stayed disabled.
- UI input291.50 -> confirmation persisted once; confirmation control removed after resolution.
- Refreshed Admin shows Cash on Delivery / Refunded. Refreshed customer shows Resolved by Refund.
- Read-only ledger: refund29150 completed; payment refunded; RMA resolved_refund; quantity1; audit events requested,approved,received,resolved_refund.
- Screenshots03-16 in output/playwright/refund-manual updated for this order. Product/cart screenshots01-02 are same-SKU reference images retained from the preceding run.
- PDF regenerated and rendered/visually reviewed: output/pdf/JW-Ecommerce-Manual-Refund-User-Manual-zh-CN.pdf (13 pages).

Bounded happy-path amount/stock persistence gaps in 2026-09-05-manual-refund-fixes.md are now browser-proven. This does not close all P0-P4 or assert live production release. Refund concurrency/rejection/FX tests remain automated evidence, not browser evidence. No old refund or stock records were backfilled.
