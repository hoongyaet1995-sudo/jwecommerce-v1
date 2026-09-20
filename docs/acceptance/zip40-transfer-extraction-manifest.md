# ZIP 40 Store Stock Transfer Extraction Manifest

- Private source: `C:/Users/User/Downloads/jw-ecommerce-dashboard (40).zip`
- ZIP SHA-256: `44B64EFC7C59A48B980CDAC163BF8E5ABFDE08F16AAE1B7C5C034BD768C12F15`
- Private extraction root: `C:/Users/User/AppData/Local/Temp/jw-ecommerce-dashboard-40-source-20260920-b`
- Selected source component: `src/components/marketplace/inventory/StoreStockTransfersTab.tsx`
- Selected source SHA-256: `C65894C9FEE49D292DFD0A2605AFB656A075A8E9CF65C4DE6BEB75AAA7D9C0E0`
- Integrated component: `resources/admin/src/previews/inventory39/components/marketplace/inventory/StoreStockTransfersTab.tsx`
- Integrated SHA-256: `5AB9812DEE3C94759D6135272529D93EB1DF9018B0D5C29349B24910942F2C17`
- Official route: `/admin/marketplace/inventory`, Stock Transfers tab in Independent Stock mode.

The raw ZIP and complete extracted project remain outside the repository. Only the selected transfer surface is integrated.

## Source fidelity boundary

The ZIP 40 transfer panel DOM, Tailwind classes, responsive full-width layout, combobox, catalog modal, filters, pagination, store balance cards, quantity controls, history table and execution dialog are retained.

Four prototype-only behaviors were replaced at the logic boundary:

1. Hard-coded source and destination store IDs now resolve from connected runtime stores.
2. Nested timers no longer simulate channel synchronization. The dialog uses the persisted Laravel response.
3. Failed create requests retain the real error message and are never presented as a completed ledger transfer.
4. Source and destination platform failures expose separate retry actions, and neither retry repeats the JW ledger transfer.

No EasyParcel, fulfillment routing, physical warehouse management, storefront, payment or unrelated Admin UI was copied or changed.

## Functional mapping

| ZIP 40 interaction | Operational mapping |
| --- | --- |
| Instant SKU/product/barcode/variant combobox | Runtime `MarketplaceInventoryItem[]` supplied by `Inventory39Provider` |
| Full catalog modal and pagination | Client view over the already loaded inventory read model; eight rows per page |
| Store stock cards | Runtime per-SKU `StoreStockAccountBalance` records |
| Source/destination selectors | Connected, non-coming-soon `InventoryStoreChannel` records |
| Transfer quantity and conservation preview | Source ATP validation in UI; Laravel review/confirm remains authoritative |
| Execute transfer | Existing `executeStoreTransfer` adapter and reviewed transfer endpoint |
| Three-stage result | Persisted JW ledger and independent source/destination sync statuses |
| Retry one failed side | Existing targeted `retrySync` adapter; no second ledger mutation |
| Transfer history | Server transfer records passed to the component |

The overview reader batch-loads sync states in one query and groups them by SKU/store. This removes the previous per-balance sync-state query pattern before rendering a large catalog.

## Verification

- Component interaction test opens the catalog, filters by product metadata and selects the matching SKU.
- Source contract test rejects prototype timers and hard-coded store IDs.
- Vite production build succeeds.
- `StoreInventoryReadTest`, `StoreInventoryTransferTest` and `StoreInventorySyncTest`: 12 tests, 77 assertions passed, including a regression check that 12 catalog balances use one sync-task query.
- Whole-admin `tsc --noEmit` remains red because of pre-existing type errors in unrelated AI Auto Reply, legacy preview and other Admin modules; the production Vite build compiles the integrated route successfully.
