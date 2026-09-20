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

## Final integration hardening (2026-09-20)

- All reviewed count, intake and correction mutations now create durable sync stages; order lifecycle, takeover and mode conversion also enqueue ATP publication work.
- A sync task already in processing cannot be published by a second worker. Interrupted processing is converted to an explicit reconciliation-required state before retry.
- ZIP39 intake and audit correction use the selected store balance and normalize the UI adjustment label to the backend correction contract.
- The cutover provisions the JW Storefront account as managed and creates unknown first-count balances; the UI now renders takeover state from backend truth.
- Takeover readiness checks connection, complete/unambiguous mappings, provider snapshots, historical open orders and counted balances. Mode readiness checks pending sync, reservations, unknown balances, safety buffers and unfinished transfers.
- Transfer receipts retain provider stages and are reconstructed from persisted operation history after reload.
- Verification: 72 backend tests / 360 assertions passed; targeted store-inventory adapter 4/4 passed; production Vite build succeeded. The repository-wide frontend run has four pre-existing unrelated failures recorded separately and is not used as closure evidence for this module.


- Shared Stock mutations now fan out one durable task per managed mapped marketplace store; takeover resolves the shared pool and publishes the selected store's initial ATP.
- Store/SKU tasks supersede older queued work, serialize behind in-flight work, and mark a completed write superseded when the ledger advanced during provider I/O.
- Mode readiness now scopes balances to the current mode; a Shared to Independent to Shared round trip is covered.
- Overview exposes current generic sync task IDs/statuses; operation history hydrates current task stages rather than saved creation-state only.
- Takeover confirmation now requires a server review token covering store, configuration, mappings, snapshots and balance versions, and writes an audited takeover operation.
- Latest verification: 36 StoreInventory tests / 216 assertions; targeted adapter 4/4; Vite build succeeded.


- Official localhost /admin/marketplace/inventory now lazy-loads the ZIP39 Inventory runtime; the isolated preview route remains available for comparison.
- Outbox work dispatches after commit, dependent transfer stages re-dispatch after source confirmation, Shared conversion publishes its counted shared balance, Shared takeover provisions its account, and takeover tasks are linked to the audited operation.
- ZIP39 preserves generic sync task IDs and exposes a visible review/retry panel for failed, authorization-expired, uncertain and interrupted writes.

