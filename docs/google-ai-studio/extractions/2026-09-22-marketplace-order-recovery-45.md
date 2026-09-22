# Source Extraction Manifest — Marketplace Order Recovery 45

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (45).zip`
- ZIP SHA-256: `3E4DF48ACD149CCE0B32931378144BD2A519697A209DE29730C8E0054198D603`
- Target: `C:\xampp\htdocs\jwstudio-ecommerce`, `/admin/marketplace/oms`
- Requested region: Fetch Marketplace Orders entry point, store selection, execution status and recovery actions.
- Explicitly excluded: deliverable presets, reviewer view toggle, mock data, simulated timers, Aurora stores, invented account tiers, TikTok/Shopify demo rows, raw provider diagnostics and unsupported result breakdowns.
- Acceptance intent: operational Shopee/Lazada order recovery using Laravel/MySQL store and order-run truth.

## Source baseline

- Visual baseline: user-supplied desktop screenshots of the ZIP recovery modal.
- Known source-only controls: five deliverable presets and selection/execution reviewer toggle.
- Known inert/mock behavior: local timers manufacture success, failure and counters.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/marketplace/orders/FetchMarketplaceOrdersModal.tsx` | `0BDEC04FFB040E1B93F0265BFC4A5EF85D8B4F235DA6EE4AD93CBE93FA05C4FD` | modal structure and states | `resources/admin/src/marketplace/orders/FetchMarketplaceOrdersModal.tsx` | selected, demo controls removed |
| `src/components/marketplace/MarketplaceOMSPage.tsx` | `4285AE1CE533ED5223C371BBB60CED643022736E3A58411A4E7290E1D6638320` | entry point and persistent feedback | existing OMS component | selected region only |
| `src/components/marketplace/orders/orderRecoveryTypes.ts` | `4F5A9198C67E7770FBD13427402846325A813FE360299F62D053A327DE9EAD34` | mock scenarios | none | excluded |

## Dependency map

| Dependency | Why | Decision |
| --- | --- | --- |
| Existing `MarketplaceLogo` | provider identity | existing-compatible |
| Existing connections API | real store identity and capabilities | available adapter |
| Existing fetch/run/retry APIs | start, refresh and recover order pulls | available adapter |
| Latest run per store | restore status after closing/reloading | added read-only adapter |
| New/updated/unchanged breakdown | source displayed invented figures | excluded; backend does not own these counters |

## Preserved interactions

| Flow/state | Trigger | Visible behavior | Data classification |
| --- | --- | --- | --- |
| Store selection | Fetch Marketplace Orders | eligible real stores and date range | available adapter |
| Start recovery | Start Order Recovery | one run per selected store | available adapter |
| Waiting | queued/running run | amber status and Refresh Status | available adapter |
| Success | succeeded run | green Done with pages/orders processed | available adapter |
| Failure | failed run | employee-safe reason and Retry Fetch | available adapter |
| Reconnect | disconnected supported store | existing OAuth recovery | available adapter |

## Approval record

- Source boundary and noise removal: explicitly requested by the user on 2026-09-22.
- Official route cutover: the user supplied the approved redesign for the existing OMS entry point; only the selected region was integrated.
