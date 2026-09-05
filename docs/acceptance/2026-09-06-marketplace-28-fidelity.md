# Marketplace ZIP 28 — isolated preview and gap assessment

## Status and approval

- Mode: FIDELITY_PREVIEW, mock retained — pending real logic. Not operational closure.
- User approved local isolated execution in this task on 2026-09-06.
- Preview: http://127.0.0.1:8529/marketplace28.html
- Restart: `npm --prefix resources/admin exec -- vite --config vite.marketplace28.config.ts` (or run `npx vite --config vite.marketplace28.config.ts` in resources/admin).
- Official route replaced: **no**. GitHub/Hostinger synchronization: **not performed**.
- Preview is a dedicated Vite entry in the Laravel repository, not an authenticated Laravel-hosted page. Official sidebar/header integration is deferred to cutover. The selected Marketplace workspace is shown without global Admin chrome, with an external simulation notice.

## Extraction manifest

- Archive: `C:/Users/User/Downloads/jw-ecommerce-dashboard (28).zip`.
- SHA256: `28EE2CB5CA5A8C4618F97E2501E3FA722742EBBAEAF9EC2C045086600EDD158A`.
- Private original baseline: `C:/Users/User/AppData/Local/Temp/jw-marketplace28-source-20260906`, served on loopback port 8528.
- Selected: MarketplaceMappingPage, ProductMappingModal, PublishProductModal, ChannelMappingModal, MarketplaceHub, MarketplaceAuthPage, MarketplaceOMSPage, MarketplaceInventoryPage, MarketplaceSyncPage.
- Required UI dependencies: ShippingLabelModal, SubNavHeader, ToastContainer, original stylesheet.
- Required data dependencies: selected source Marketplace/catalog fixture declarations and transitive types. No real-data adapter is used.
- State dependency: only referenced source provider declarations were extracted into the private preview provider. Ambient localStorage writes are replaced by inert preview storage; state lives in React memory and resets on reload.
- All unrelated application components and the full original AdminContext were excluded. Raw ZIP/extracted source remains outside the repository.
- Component hashes: `2026-09-06-marketplace-28-source-hashes.json` alongside this report.
- AST statement comparison excluding imports: all 12 extracted UI components match original source statements. This proves no component DOM/class/interaction edits, not full-screen pixel equality.

## Verification evidence

| Check | Evidence | Result |
| --- | --- | --- |
| Original source startup / TypeScript | Original ZIP Vite app on 8528; `npm run lint` | Passed; favicon.ico 404 remains in original |
| Preview TypeScript | `npm --prefix resources/admin run lint` | Passed |
| Frontend regression + isolation suite | `npm --prefix resources/admin run test -- --run` | 56 files, 353 tests passed |
| Preview-only build | `npx vite build --config vite.marketplace28.config.ts` in resources/admin | Passed into tmp/marketplace28-build; 515 kB JS chunk warning |
| Navigation | Connect Store, OMS, Inventory, Logs, Mapping, Hub | All opened without page errors |
| Mapping | Confirm Variant Mapping | Completed in preview memory |
| Import / Publish | Import Product; Publish & Auto-Link | Simulation success feedback observed; no mutation requests, no localStorage keys |
| Large catalog | Browser-only injected 350 products / 1,050 variants | Flat view renders 15 rows; page 1 and page 2 ranges correct |
| Mobile | Mapping and publication screenshots at 390x844 | Publication viewport and document both 390px wide; not a full mobile interaction sign-off |
| Source/preview visuals | Desktop/modal/mobile screenshots below | Region presentation retained; original Admin chrome intentionally not mounted |

The first suite run failed because the new Node test runner test was collected by Vitest. Converted its runner import to Vitest and reran successfully; no existing business tests were weakened.

Screenshots under `output/playwright/marketplace-28/`:

- source-mapping-desktop.png, source-mapping-mobile.png, source-matching-desktop.png
- preview-mapping-desktop.png, preview-mapping-mobile.png, preview-matching-desktop.png
- preview-publish-desktop.png, preview-publish-mobile.png, preview-hub-desktop.png
- preview-350-products.png

Original and preview modal screenshots currently show different selected products, so they are structural references, not pixel-diff acceptance. Same-state pixel comparison, source publication baseline, full phone interactions and all batch edge cases remain open. Do not claim 100% visual acceptance.

## Source UI/interaction gaps (not silently corrected)

1. **Action Required excludes conflicts.** The default `unmapped` filter selects only unmapped variants; conflict/invalid variants are not included. A separate conflict branch exists, but it is not the default combined task queue.
2. **Flat view has no selection checkboxes.** The 350-product browser test found no checkbox in the compact view. Bulk selection is therefore not available there as described; the attempted checkbox check timed out. Grouped-view batch scope needs separate acceptance.
3. **Sync All Channels is inert in Hub.** Source Hub reads `triggerChannelSyncAll` and `isSyncingMarketplace`, neither provided by the source provider. Preview explicitly retains undefined values, without inventing a successful sync.
4. **Publishing lacks a real pending/rejected/partial-failure flow.** Simulated publish immediately creates a listing and success log. Existing-listing warning permits another listing. Operational retry/deduplication needs a separate contract.
5. **Unlisted products are product-level, not variant-completeness based.** Any mapped variant excludes that whole JW product; missing variants of a partially listed product can be overlooked. Platform-only selection is not applied to this computation when store selection is All.
6. **Demo/developer surfaces remain source-only.** Reset demo data and raw log inspection are retained in isolated source preview for fidelity, not approved as ordinary employee functionality for cutover. Existing official employee UI was not changed.

## Backend contract gaps and unsafe mock assumptions

- Real provider authorization, catalog pagination and refresh are absent from this preview.
- Mappings require durable store-scoped external product/variant identifiers and JW variant ownership. Equal SKU text is not required for manual mapping.
- Import sets zero/negative external stock to **50**, invents compare-at price, cost, barcode, weight, category and warehouse, marks products active and enables stock sync. These source demo defaults must never be translated into real catalog/warehouse writes.
- Import uses title equality to choose an existing master product and appends variants, without safe repeat-import deduplication.
- Batch import loops can produce timestamp ID collisions and stale-state conflicts; backend idempotency/transactions are required.
- Publish/Import success does not prove a remote operation; fake IDs and simulated timing must be replaced only during separately approved operational work.
- Remote inventory must not automatically become physical warehouse stock. Mapping must not automatically enable price/stock writes.

## Recommendation and remaining gate

Keep this as a reviewable source preview. Before official cutover, resolve the source's combined task queue, compact-view bulk controls, clear pending/failure states, and employee/developer separation; keep the rest of the approved design. Define real Import/Publish contracts separately before any provider writes. Do not import the whole ZIP over the current Admin.

No fixes to these source behaviors, real API integrations, production routes or deployed assets were made in this phase.
