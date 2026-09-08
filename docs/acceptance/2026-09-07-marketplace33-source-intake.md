# ZIP 33 Inventory & WMS source intake

## Boundary

Archive: C:/Users/User/Downloads/jw-ecommerce-dashboard (33).zip
SHA256: D93B99AB3C0BCEB0BB51BB38656919CDF84782F316AAB641F5FEBBA5DA6ED5F7
Private extracted source: C:/Users/User/Downloads/jw-ecommerce-dashboard-33-source
Canonical Laravel project: C:/xampp/htdocs/jwstudio-ecommerce
Selected official destination: /admin/marketplace/inventory; no cutover performed.
Only inventory page, its four new dialogs/drawers, required types and narrow context adapters are selected. Other ZIP modules are excluded. Source export documentation is not authority to execute real inventory writes.

## Browser baseline

Started unmodified source using existing local Vite dependencies on http://127.0.0.1:8533. The supplied app uses internal navigation: navigate Marketplace OMS > Marketplace Inventory & WMS. A direct URL alone initially renders Dashboard.
Chrome verified matrix with eight demo SKUs, multiple store associations, unknown physical stock / Pending Count, and physical count input -> review -> disabled confirmation pending acknowledgment. Review screenshot emitted in task. Desktop viewport screenshot 1536x674; mobile and converted baseline not yet verified.

Initial employee dropdown briefly showed the first staff member Airene; after initialization, review correctly showed Alexander. Still, staff is client-selectable and callbacks submit a staff display name. Production audit actor must come from authenticated server identity, not this input. Unknown physical count is prefilled with 50; it must not become an inferred physical baseline.

## Contract mapping

| Flow | Existing backend | Remaining integration |
| --- | --- | --- |
| Multi-store SKU read | GET marketplace/inventory, actual store mappings | Adapt source fields; unknown physical and safety values remain unknown; no demo warehouses |
| Order exceptions | GET marketplace/inventory/exceptions | Project to drawer fields without recipient payloads |
| Stock ledger | GET inventory/movements | Wire SKU identity and paginated ledger |
| Count/delta two-step review | Unified InventoryMutationService | Physical baseline, reserved/safety ownership, authenticated review and conflict API remain gaps |
| Store activation | Read-only reconciliation endpoint and internal revision | Persist reviewed historical-order disposition, warehouse mapping, policy and cutoff atomically |
| Broadcast | No operational stock push API | Durable outbox, opt-in, platform validation, read-back and confirmed/failed/uncertain outcomes |

Source AdminContext broadcastStockToStore sets confirmed and fixed latency 145ms, updates localStorage and adds success logs without an actual platform call. Keep mock behavior only in isolated source preview; never copy it into production. Source warehouse and staff fixtures are not real runtime identities. Two modal/drawer source components also conditionally return before hooks; inspect reopen lifecycle before integration, without altering DOM/classes.

## Selected files

| Source path | SHA256 |
| --- | --- |
| src/components/marketplace/MarketplaceInventoryPage.tsx | 0A3CAA55858784C6CEBDBC59E18A294A6F0D1757521629E3E1302819B53B3DF9 |
| src/components/marketplace/InventoryAdjustmentModal.tsx | E55AAD276201D071C8CCD4F9EA3144D23641690C208F7E711EA284C6960D4688 |
| src/components/marketplace/InventoryStoreBroadcastModal.tsx | 6B507D3A2EADD3A9BE4FF86E347CD3A4B5C5E696BA0B68B24C54B498F6FC4601 |
| src/components/marketplace/StoreActivationsDrawer.tsx | 7B4E27262C34A2D3C0B4BD98D6567FDA835243CE4C6CF50C03B4E26B699DA396 |
| src/components/marketplace/InventoryOrderExceptionsDrawer.tsx | 40AF2431F2FEBC59F4BC6A7908EBA612A2AD287B9A383C02941021C058913ECA |

## Isolated Laravel read preview checkpoint

Local route /admin/marketplace/inventory-preview-33 uses separate build-marketplace33-preview assets and is admin-protected/local-only. Seven Marketplace component files retain exact ZIP33 source hashes. A narrow context reads the existing protected inventory endpoint; no source AdminContext or mock fixtures were copied. No write requests are implemented. Existing formal page is unchanged.

Chrome loaded 58 actual local SKU rows and searched SKU-SOLIS-WAL-STD, displaying RM 220.00 after correcting cents-to-major-unit projection. Rows retain Awaiting Audit and Pending Count. Console errors: none captured. Vite build passed. Inventory ingestion regression: 14 tests /84 assertions. Preview auth/environment guard: 1 test /3 assertions.

Limits: first 100 records only, explicitly labeled page 1; activation, exceptions and ledger adapters are not loaded and empty UI is not evidence those datasets are empty. Source metric captions and warehouse summary are still demo copy, explicitly disclaimed by the separate preview banner. Safety/physical/ATP are not fabricated as zero. No complete visual/mobile parity or production readiness claim. Next work must supply truthful aggregate/loading/unknown states and remaining read contracts before formal cutover. Build command: bundled node resources/admin/node_modules/vite/bin/vite.js build --config resources/admin/vite.marketplace33.config.ts (run from appropriate Vite root).

### Complete bounded inventory read

The ZIP33 preview adapter now follows same-origin numbered pages and publishes rows only after all pages succeed. It rejects changed totals/page counts, duplicate IDs, incomplete counts and HTTP failures rather than presenting a partial catalog as complete. A 10,000-row /100-page ceiling fails explicitly; it does not silently truncate. This is preview-only client collection, not the final scalable server-filtered matrix and not an atomic reconciliation snapshot. Four frontend tests passed; isolated Vite build passed. Source components remain unchanged.

### Real available-stock ledger adapter

ZIP33 selected audit drawer now loads the chosen variant's protected movement endpoint on demand; close/switch aborts pending requests. Only behavior bindings/imports changed in MarketplaceInventoryPage; its JSX structure/classes remain source-preserved. Other selected components unchanged. Available movement rows explicitly label their meaning, leave physical result null and display Actor not recorded for null actors rather than inventing employee identity. Bounded pagination rejects duplicate/wrong-SKU/incomplete histories.

Six frontend adapter tests and isolated build passed. Chrome authenticated local preview searched INV-QA and opened the ledger for the preexisting synthetic local conflict fixture. Actual persisted movements -1 (available 7/version 2) and -2 (available 8/version 1) appeared. No new stock mutation was made. Console errors captured: none. This proves local read binding, not a physical audit or Marketplace provider write. Empty/loading/error drawer presentation still needs completion before formal cutover.

### Ledger state presentation

Existing drawer subtitle now binds loading, loaded-count, empty and failure status; no wrapper/class or new control was introduced. Chrome verified loading followed by explicit no-recorded-movements text for SKU-SOLIS-WAL-STD. The message does not equate absent movements with a verified physical count. Isolated build passed. Failure text is implemented but not browser-injected/verified in this checkpoint.

### Exception read adapter

Isolated context now reads every bounded page of the protected exception endpoint; errors remain errors instead of empty success. Historical exceptions display Requires reconciliation, never automatic Excluded from WMS. Unsupported error codes retain their actual code instead of being mislabeled Stock Depleted. Quantity and ATP remain unknown because the current order-level API does not supply line quantities. Timestamp explicitly means Checked, not order creation time. Original JSX containers/classes remain intact; text/data bindings changed.

Nine frontend adapter tests and isolated build passed. This checkpoint has not yet browser-verified nonempty exception rendering. No synthetic production exception, allocation or order state was created. An empty recorded exception set does not prove coverage when inventory accounting is disabled.

### Exception drawer acceptance

Authenticated Chrome verified actual empty-result load, historical filter, close and reopen with no captured console errors. No nonempty local business exceptions existed in this read, so no live nonempty evidence is claimed. Two isolated server-rendered React tests passed: historical review remains unresolved with unknown quantity/ATP and no Excluded from WMS badge; a 503 message is not rendered as empty success. No business records were inserted for this check.

### Truthful read-preview summary binding

Replaced source fixture warehouse/overselling/synchronization captions in their existing text nodes. Physical/ATP/low-stock summary displays Pending Count when unread or any SKU remains unverified; unknown row safety displays Not verified. Reserved card explicitly names recorded Marketplace reservations and does not claim complete OMS coverage. No DOM structure or CSS class changes. Separate inventoryLoaded state prevents the initial empty array from being treated as a successfully read zero count. Isolated build passed; mobile layout effects of longer text remain to verify.

### Mobile viewport check

Chrome viewport override 390x844: real preview loaded 58 SKUs, document scrollWidth 380 within viewport width390. The 837.25px SKU table remains inside its scroll container; no document-wide horizontal overflow was measured. Exception drawer opened and closed; measured bounds x0/y0/width380/height844. Viewport override was reset after verification. Screenshot output was visibly downscaled by the browser capture surface, so this is geometry/interaction evidence rather than complete pixel-level mobile parity acceptance. No UI edits in this checkpoint.

### Connection badge adapter

Preview reads protected /admin/api/marketplace/connections and uses its server-computed effective status (including reauthorization_required), filtered to Shopee/Lazada. Badge denominator is actual returned store count instead of fixed four platforms. Pending/error state is labeled Loading/Unavailable rather than zero connected stores. This is connection metadata only, not inventory activation. Isolated build passed; enabled-store browser evidence remains pending in this local environment.

### Coherent read-phase regression

Fresh combined verification after ZIP33 inventory, movement, exception and connection adapters: PHP Marketplace|Shopee|InventoryMutation|InventoryMovement|StockAllocation passed 198 tests /1271 assertions (14.18s). ZIP33 frontend suite passed 11 tests across four files. git diff --check passed. These checks do not prove physical baseline, activation, remote writes or formal-route parity. Source preview files and separate artifacts remain local; no commit/push/deploy was performed. Physical ownership decision remains pending in docs/superpowers/specs/2026-09-07-inventory-ownership-decision.md.
