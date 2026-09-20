# ZIP 39 Store Inventory Acceptance Evidence

## Implemented boundary

- One ledger owns shared or store-specific balances. Marketplace snapshots remain reference data.
- First count is blank, reviewed, permits explicit zero, and never submits provider stock.
- Intake, correction, order reservation/release/shipment/return, transfer, takeover, pause and mode change are durable audited operations.
- Transfers conserve physical stock. JW ledger mutation occurs once; source and destination provider stages are separate retryable tasks.
- EasyParcel, shipping booking, payment and storefront presentation were not changed.
- TikTok Shop and Shopify remain Coming Soon.

## Source-fidelity browser evidence

| Evidence | Artifact |
| --- | --- |
| Original ZIP desktop Inventory | `output/playwright/inventory39-source-desktop.png` |
| Original ZIP mobile Inventory | `output/playwright/inventory39-source-mobile.png` |
| Converted desktop overview | `output/playwright/inventory39-desktop.png` |
| Converted mobile overview | `output/playwright/inventory39-mobile.png` |
| Converted settings | `output/playwright/inventory39-settings.png` |
| Blank first-count input | `output/playwright/inventory39-first-count.png` |

Browser acceptance against the isolated SQLite preview proved: authenticated page load; real store labels; partial-account count warning; blank count; explicit 5 and 0 counts; reviewed persistence; takeover blocker then successful takeover after mapping; 5 total units conserved across a 1-unit store transfer; activity count growth; and responsive rendering.

## Automated evidence

- Adapter: `4 passed` (`storeInventory.test.ts`).
- Cutover command: `3 passed, 18 assertions` (`StoreInventoryCutoverTest`).
- Preview build: Vite `1680 modules transformed`, production build successful.
- Backend feature evidence is recorded in `.superpowers/sdd/2026-09-20-store-inventory-zip39/progress.md` and must be rerun as a final combined gate before cutover.

## Release gates and truthful limitations

- Official Inventory route is intentionally unchanged until preview approval.
- The cutover command defaults to dry-run, hashes record versions, rejects drift, blocks non-empty legacy warehouse balances, and preserves products, marketplace stores, credentials and history.
- Lazada write code has HTTP-fake contract evidence; a real provider stock write/read-back is not yet recorded for this branch.
- Shopee live stock publishing is deliberately rejected by the publisher boundary until a separately verified adapter exists. It is not represented as operational.
- SQLite proves behavior but not MySQL row-lock concurrency. Dedicated two-connection MySQL evidence remains required before production cutover.
