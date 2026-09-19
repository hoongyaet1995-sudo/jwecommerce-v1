# Catalog Download ZIP 38 extraction manifest

## Request boundary

- ZIP archive: `C:\Users\User\Downloads\jw-ecommerce-dashboard (38).zip`
- ZIP SHA-256: `2DD039D2C66A725F2E02E2849AE4141A5846EC5403895D2193F79E5E872EC884`
- Laravel acceptance route: `/admin/marketplace/mapping`
- Selected boundary: first-time Fetch Store Products confirmation; server-backed catalog progress drawer; floating task indicator; pause/resume/recovery presentation.
- Excluded: `CatalogDownloadComponentInventory.tsx`, the `UI States Spec` trigger, and the Drawer `Interactive State Tester` simulation controls.
- Acceptance intent: source-faithful Fetch confirmation and background task UI on the authenticated operational route.

## Source baseline

- Startup: `npm install && npm run dev`
- Source build: passed on 2026-09-19. Vite noted Node 20.17 is below its recommended 20.19, but emitted a successful build.
- Source interaction observed: Fetch Store Products opens the selected-store confirmation before task submission; the supplied source showed its test-store fixture and a persisted completed task indicator.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/marketplace/catalog/FetchStoreProductsConfirmModal.tsx` | `0218ABADEE9BCC039D5B0AB4B6F3AA265B9098C275B1A7BBF903691579F29B34` | confirmation dialog | `resources/admin/src/previews/catalog-download38/FetchStoreProductsConfirmModal38.tsx` | selected |
| `src/components/marketplace/catalog/CatalogDownloadDrawer.tsx` | `073C8E9B45087FC0EA3C4C27B394C20AB0CD1B191C237C1597E48AFB725B7EA5` | public progress/recovery drawer | `resources/admin/src/previews/catalog-download38/CatalogDownloadDrawer38.tsx` | selected, simulation bar removed |
| `src/components/marketplace/catalog/PersistentCatalogTaskIndicator.tsx` | `93C9FFB45A478AA8E8CEC7A87D7F7BC2A0E31156144E449611265D45029A82F1` | persistent task notice | `CatalogDownload38Overlay.tsx` | selected behaviour |
| `src/components/marketplace/MarketplaceLogo.tsx` | source dependency | platform identity | existing Marketplace 30 logo | existing-compatible |
| `src/components/marketplace/catalog/CatalogDownloadComponentInventory.tsx` | excluded | developer state inspector | none | excluded |

## Data adapters

| Data | Classification | Existing source |
| --- | --- | --- |
| Connected store identity and capability | available adapter | `/admin/api/marketplace/connections` |
| Catalog task status and progress | available adapter | catalog-run endpoints already used by `useMarketplaceReadTask` |
| Start / retry | available adapter | existing catalog fetch / retry endpoints |
| Confirmation preference | local preference | browser localStorage per store |
| Images captured, total pages, unmapped total | mock-retained as zero until API exposes them | no backend change in this frontend-only phase |

## Evidence matrix

| Requirement | Current evidence | Result |
| --- | --- | --- |
| ZIP identity and component selection | hashes above | recorded |
| Source build | private ZIP build completed | passed |
| Confirmation dialog unit test | `FetchStoreProductsConfirmModal38.test.tsx` | passed |
| Admin build | `npm run build` in `resources/admin` | passed |
| Local browser preview | Laravel at the isolated preview URL renders the Lazada Official Test store and its pulled catalog; the expired local test-token lease correctly leaves Fetch disabled | passed with token-gate limitation |
| Explicit-start guard | `CatalogDownload38Overlay.test.tsx` verifies Fetch opens the confirmation first and does not create a task until `Start Background Download` is clicked | passed |
| Official route integrated | yes | Fetch confirmation and progress drawer replace the legacy task modal |

## Local verification boundary

- The local official-test token lease expired at `2026-09-19 04:34:39 UTC`. The existing connection API therefore returns the store as requiring reauthorization and the mapped Fetch button remains disabled. This is the intended credential safety rule; no token, task, store, catalog row, or provider data was changed during this conversion.
- The React adapter was verified with an equivalent connected test-store fixture for the explicit-confirmation path. A real download must be initiated by the acceptance user after supplying a fresh token through the existing test-token flow.
