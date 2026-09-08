# Official OMS local binding

The official local OMS route now bypasses the legacy mock-shaped provider and mounts `OperationalOrders`. It queries the real paginated order and status-summary endpoints, discards stale responses, and lazily requests the existing protected order detail/recipient contract on explicit inspection.

ZIP30 table cell classes/layout are reused in OrderSummaryRows. Missing buyer/item/tracking facts are not fabricated; items are disclosed as loaded on inspection. The existing OrderInspectionDrawer renders detail. Simulator and unsupported fulfillment remain unavailable. Raw platform statuses drive operational tabs.

Verification: three component tests passed, TypeScript and build passed (existing large-chunk warning). Real authenticated local browser loaded `/admin/marketplace/oms`, no alerts, no simulator, truthful empty state. Screenshot `output/playwright/marketplace-30/oms-official-local.png`.

Remaining: nonempty browser inspection/stale detail tests, per-store filter and fetch/progress UI, real Sandbox order acceptance after approved release. Current local DB has no store/order data. This is not remote order-flow completion and no deployment was performed. Final header/search-copy adjustments require the next build before browser recheck.

## Detail identity and store filtering

- Added a component integration test with two orders and out-of-order detail responses. Selecting B after A retains B when A completes; closing removes recipient text.
- Added per-store selection using the existing ZIP Mapping select styling. Changing store resets pagination and closes the previous order. Both list and summary requests receive storeId.
- Test first failed for absent selector, then passed with storeId=7/page=1 assertion. TypeScript passes. These are component/API mock tests, not a real Marketplace order interaction. Nonempty browser and fetch progress remain.

## Nonempty browser and read-task workflow

- Browser-only order fixture verified list amount/reference, detail line and recipient rendering, disabled AWB, and recipient removal on close. Desktop/list and 390px drawer screenshots are `oms-nonempty-fixture.png` and `oms-detail-mobile-fixture.png`. All interception removed afterward; no DB writes.
- Added shared read-task lifecycle hook: duplicate-submission guard, backend run polling, refresh only on succeeded, status-query failure does not requeue. Added OMS last-seven-days fetch scoped to an explicitly selected connected/capable store, plus explicit failed-task retry.
- Three related frontend tests pass, including queued-versus-succeeded lifecycle and double-click protection. TypeScript passed before the final retry-button addition; next verification must include that addition and a fresh build/browser run. No worker or real task was started here.
