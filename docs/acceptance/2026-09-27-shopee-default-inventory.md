# Shopee inventory location fallback

Release approved by the user. Live Shopee writes have not been exercised.

The requested independent-stock flow is store -> location (Default Inventory when no usable provider location is returned) -> JW ledger -> Manage Stock -> intake, audit adjustment, or transfer. Uncounted inventory continues through the existing First Count entry.

Previously FacilityDiscovery short-circuited Shopee with complete_location_api_unverified without calling the provider. It now reads stock location identities through the store-specific Shopee application. Valid locations are registered with independent JW warehouse owners. Empty results, invalid location data, authorization errors, and provider failures resolve to the existing no_warehouse store account. A failed discovery keeps default_inventory_fallback and no verified_at timestamp; verified status here means the local inventory route is usable, not that Shopee confirmed inventory or warehouse completeness. Existing balances are not rewritten by discovery.

Both inventory location read endpoints resolve previously pending Shopee scans. The existing discovery sweep performs subsequent refreshes. Lazada discovery remains unchanged. Shopee default inventory uses direct:<store-id> UI targets and the existing store-level actions, not a fabricated provider location ID. If live seller_stock has exactly one row without a location, its update omits location_id; a real location remains exact, and ambiguous multiple seller rows are not combined. Provider readback must still match the requested quantity.

Validation:
- 94 PHP tests / 398 assertions passed using isolated SQLite :memory:, covering provider discovery errors and valid locations, count/intake/correction, default-store transfer, facility operations, and provider publishers.
- 22 frontend tests passed for the drawer and store inventory integration.
- Admin Vite build passed (existing large-chunk warning); git diff --check passed.
- Local browser fixture using the actual drawer component exercised store -> Default Inventory -> ledger -> all three action callbacks, with exact direct:3 targeting. This is UI fixture evidence, not authenticated full-stack or live provider acceptance.
- Temporary browser fixture files and development servers were removed after verification.

Release preparation must preserve the canonical workspace's pre-existing dirty changes and merge only this phase onto the current released main; do not deploy the stale canonical HEAD or replace newer released inventory behavior. Release preparation on top of 8ed3719 repeated all 94 PHP tests (398 assertions), 22 frontend tests, and the admin build successfully. The released push-panel removal and ledger-review copy are preserved. No live provider write was performed.
