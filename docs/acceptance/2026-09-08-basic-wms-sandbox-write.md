# Basic WMS bounded sandbox write evidence

Scope: designated Lazada borrowed seller, local XAMPP; no production stock broadcasting or inventory takeover. Shopee test stock transport remains outstanding.

## Implemented boundary

MARKETPLACE_SANDBOX_STOCK_WRITES_ENABLED defaults false. SandboxInventoryWriteGuard checks Basic enablement, authenticated administrator, designated store ID, persisted test provenance, provider environment/application/test credential lease, test SKU, test warehouse, store route, exact mapping, physical count, safety stock and configuration. Shared/independent pools are checked against current configuration. Target quantity comes from Basic balance-owned available stock; unallocated order demand remains excluded.

Separate sandbox_inventory_attempts migration000019 applied locally. Staging stores immutable snapshot, actor and reason. Dispatch claims an attempt once, records original provider-location quantity before sending, verifies seller identity again, sends through the existing Lazada transport without changing the production broadcast flag, and independently reads the exact SKU/location quantity. Only matching readback confirms. Unknown outcomes remain uncertain and cannot be blindly resent. Provider exception text is not persisted or returned. Authenticated stage/dispatch/inspect endpoints and the existing ZIP36 single-store button are connected. Broadcast-all remains disabled for Basic test operations.

## Actual provider and browser evidence

- First real attempt6866eb81-0f26-4a80-adf7-0dbb3f0bc96a: store3, borrowed seller300163725140, listing83, item14971376157, SKU128558080469, locationMY4NA1T7CK-WH-10007, balance10/version5, routeversion0, configurationversion4. Before82, requested19, independently observed19; confirmed2026-09-08 12:42:47UTC. Original82 is retained for any later explicitly reviewed restoration; it has not been restored.
- Chrome on main-CvuwJ5tx.js: existing single-store button displayed Sandbox Test Only / Test Push19. Clicking created attempt0ad4577c-12ce-4a38-a83b-5471bb71e610. Before19, observed19, confirmed12:50:01UTC. Browser displayed Pushed19 units confirmed (19340ms),20:50:01 local. The second test proves the UI-to-service-to-provider-to-receipt path, not a further quantity change.
- DB readback: physical21, safety2, balanceversion5, legacy stock0, accountingfalse, production_broadcastfalse. No WMS or legacy stock changed as a side effect of these provider writes.
- Test write flag was enabled only for this browser test and is returned to false afterward. Designated local store3 remains configured. No secrets printed, committed or sent to the frontend.

## Verification and remaining scope

Sandbox guard/attempt/API tests7/34 assertions passed, including foreign actor, client quantity rejection, explicit flag, designated store, lease expiry, readback confirmation, retry behavior and sanitized uncertainty. Existing Lazada client/production stock-task suites also passed after transport extraction. TypeScript passed; frontend adapter tests2 passed; preview build passed. A final one-line receipt display correction follows the browser build and must be included in the next batch build.

Remaining B6: Shopee sandbox write/readback, durable test-attempt discovery in the existing ledger/read model, and deployment-environment test credential renewal. Remaining B7: shared/initial-configuration/new-mapping browser coverage and release-route integration. Do not claim Phase1 released. No Git commit, push or deployment in this batch.
