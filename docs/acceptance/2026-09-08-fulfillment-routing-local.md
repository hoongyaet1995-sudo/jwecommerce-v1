# Fulfillment routing integration

Database readback confirmed local website route id1 -> warehouse2, is_enabled0, version0; audit actor2 at 2026-09-08 02:51:45. Configuration remains operating_mode null/accounting_enabled0.

PhysicalAllocationFlow + InventoryRoutingService: 6 passed / 31 assertions. Added independent-warehouse test with Shopee and Lazada fixture orders sharing one physical balance: first reserves5 of ATP8, second request4 rejects, second request3 succeeds, dispatching both leaves physical2/safety2/ATP0. This proves local service behavior only, not provider callback or real concurrent database execution.

## Browser checkpoint

Mounted ZIP35 StoreWarehouseBindingTab and asynchronous modal at `/admin/marketplace/inventory-preview-33?view=routing`. Paginated real warehouse, route and limited store-identity reads feed the screen; no mocked stores. Current browser listed JW Webstore only. Saved its initially disabled route to the existing inactive `QA-BROWSER-0907` warehouse, then reloaded and observed the persisted mapping. This is local QA configuration; save preserves disabled state, does not activate accounting, and is not provider verification. Added explicit empty warehouse choice to prevent native-select visual default from disagreeing with empty state; disabled platform-location fields for webstore because backend intentionally ignores them. Build main-DAu-EWQn.js passed. Requested viewport matrix and backend audit readback remain pending.

InventoryRoutingService and protected GET/PUT `/admin/api/inventory/fulfillment-routes` persist warehouse ownership by `website` or `marketplace:<store id>`. Only existing Shopee/Lazada identities are allowed. Enabled routes require active warehouses; expected versions prevent stale updates. Every change records server actor and before/after snapshots in inventory_route_changes.

ZIP35 platform location name and fulfillment priority now persist alongside location ID. Priority is stored configuration only: the current one-route-per-channel model does not implement automatic fallback or split routing. No claim of provider location verification is made.

Migrations 000005 and 000006 applied locally. InventoryRoutingServiceTest: 2 passed, 13 assertions (session protection, unsupported actor injection, inactive activation rejection, stale versions and audit ownership).

ZIP35 StoreFulfillmentBindingModal extracted into marketplace34 preview components, preserving presentation and changing save to awaited async with pending protection. Removed client-created lastSyncAt: saving a route is not a provider synchronization. Component not yet mounted; loader, API adapter and source table host remain next. Need verify optional metadata roundtrip and mounted browser before claiming closure. No business route enabled, no platform write, no deployment.
