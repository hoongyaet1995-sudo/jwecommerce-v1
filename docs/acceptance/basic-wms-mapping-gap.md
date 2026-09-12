# Basic WMS mapping integration gap

Verified 2026-09-08 in the current local worktree.

- Chrome confirmed both single-store and all-store broadcast buttons disabled when no eligible store exists (bundle main-BKlouqvg.js).
- Inventory API now returns the persisted catalog seller SKU as external_sku, and the preview binds it instead of an empty string. MarketplaceInventoryIngestionTest targeted read test passed with 24 assertions. This last SKU binding needs a rebuilt browser check.
- ZIP36 ChannelMappingModal still defaults to TikTok, invents MP-ITEM identifiers, selects by seller SKU instead of unique listing/store identity and calls a nonpersistent linkChannelSku callback. Do not count this as completed multi-store mapping.
- Canonical backend already exposes PUT /admin/api/marketplace/listings/{listing}/mapping and GET mapping-context. Next integration must select the actual catalog listing identity, preserve store scope, wait for a server receipt, handle conflicts and refresh mappings. No generated identifiers or client-only synced state may be accepted.

## Integration progress

The ZIP36 modal now loads paginated stored Shopee/Lazada catalog rows, includes each store name, selects by unique listing ID, and saves through the canonical mapping endpoint using the preview's session CSRF meta token. Manual fabricated identifiers and simulated sync writes are disabled. Saving waits for the server response; errors retain the modal.

Chrome on main-CX38Kq1B.js loaded the real stored Lazada directory, selected PLAIN-T-Red-M and successfully re-saved its existing mapping. Read-only database verification (`tests/manual/basic-wms-mapping-readback.php`) confirmed listing 83 / store 3 / external item 14971376157 / external variant 128558080469 / JW variant 102 / actor 2. This is an existing-mapping save, not evidence of new-store onboarding. WMS remained physical 21 and Basic Available 19. Mapping constraint tests passed: 4 tests, 9 assertions. The source platform dot now derives its linked state from catalog mapping data; that last display adjustment needs rebuilding.

Remaining: first-time mapping browser scenario, stale-selection conflict protection, unknown catalog price handling, catalogue refresh/navigation wiring, and new balance initialization flow.

## First-time mapping — 2026-09-08 21:50 MYT

Authenticated local ZIP36 browser selected Lazada catalog SKU10015 from borrowed test store3 and saved it to classified JW test variant103 (`JW-QA-BASIC-FIRST-COUNT-0908`). Before saving, listing70 had no mapping; after saving, MySQL recorded listing70 / external item14971011493 / external variant128508288530 / JW variant103 / actor2. Sibling listing69 remained unmapped. This is a local test association for acceptance, not an assertion that the generic JW fixture represents operating dog-toy stock.

Readback `tests/manual/basic-wms-new-mapping-readback.php` proved legacy17 and balance14 physical12/safety2/version2 unchanged. Browser refreshed to show the Lazada association while physical12/available10 remained. No price or platform quantity write occurred. This closes first-time mapping browser persistence; earlier open wording for that scenario is superseded. Existing `saveInventoryMapping` sends expectedVariantId to the canonical endpoint for stale-selection protection; existing server conflict tests remain relevant.
