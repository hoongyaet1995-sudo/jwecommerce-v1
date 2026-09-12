# ZIP36 inventory extraction manifest

## Reconciliation adapter update, 2026-09-08

### Real localhost browser evidence

Chrome at `/admin/marketplace/inventory-preview-33?view=zip36` loaded 62 database SKUs. Created `QA-ZIP36-CYB` / `LOCAL QA ZIP36 Cyberjaya` using the ZIP36 warehouse form; list increased from one to two facilities. Named test SKU `JW-SBX-LZ-SHIRT-001` (variant 102) was explicitly staged into warehouse 9, balance 10/pool 10 using the local-only provisioning script. Initial quantities remained null.

The browser physical-count review and explicit confirmation recorded 20 **test fixture** units with actor 2. Database readback showed physical 20, version 1, reserved/safety/ATP null. Browser ZIP36 Reconcile then reviewed safety 2 and committed receipt `45ce4240-d5d9-4668-90e5-eaa5bfa0a958`. Independent database projection confirmed physical 20, reserved 0, safety 2, ATP 18, verified true, version 2. No platform stock broadcast was performed; global operating mode remains unconfigured. This proves the local fixture count/reconciliation path, not active channel order accounting or real physical stock verification.

Follow-up fixes: receipt actor now comes from the persisted audit's user; retry timestamp comes from the original audit rather than request time; warehouse/product labels are attached from the reviewed UI identity. These display fixes have backend/adapter coverage but require rebuilt-browser verification. Last combined frontend run: 10 tests passed across six files. Remaining major integration work includes routing, transfers, operating-mode activation/conversion, channel accounting activation and store broadcast readback; final OAuth/provider evidence remains separate.

The selected reconciliation component and shared types are present under `resources/admin/src/previews/marketplace36`. The only component behavior change so far preserves numeric version zero with `??` instead of `||`; DOM and classes remain unchanged. The source baseline remains private.

`loadReconciliation` now composes the complete paginated reservation read, real order identity grouping, physical-count prerequisite, unowned-reservation block, pinned balance/variant versions, CSRF-protected POST and validated receipt. Reservation pages reject version drift, duplicate IDs and quantity-total mismatch. The server enriches reservation rows with minimal website/marketplace order identity, excluding customer details and encrypted provider payloads.

Current evidence: adapter composition test passed with controlled HTTP responses; interaction regression verifies version zero and retained form on rejection; backend `InventoryReconciliationTest` passed 4 tests / 33 assertions. These are local automated tests, not live browser reconciliation acceptance.

Remaining host work: mount the ZIP36 matrix and its selected dependencies, bind actual warehouse identity and store impacts, expose load failures via the existing toast, supply authenticated operator, and verify the end-to-end browser flow. The existing ZIP33 matrix has no warehouse expansion/reconciliation action; adding only the modal does not complete the ZIP36 host. No official-route cutover or deployment has occurred.

Source: user-supplied `C:/Users/User/Downloads/jw-ecommerce-dashboard (36).zip`, SHA256 `5435213c00e6b9e8bba4e8536d0677d577597061b2eb449c910b0da6df5b5c42`.

Boundary: Marketplace Inventory & WMS page and its mode, warehouse, binding, transfer, stock count, reconciliation, broadcast, mapping and exception dialogs. Exclude storefront, payments, CRM and unrelated marketplace page designs. Preserve source DOM, classes, copy and responsive states. User supplied ZIP36 as the latest approved design; no official-route cutover approval is recorded.

Fifteen selected component files were extracted byte-for-byte to `C:/Users/User/.codex/private-sources/jw-ecommerce-zip36-inventory`. Exact per-file hashes and sizes are in `zip36-inventory-source-manifest.json`. Raw source remains outside repository/deployment output.

Direct dependency review found shared `SubNavHeader`, `types` and `AdminContext`, plus React and lucide-react. Context and type dependencies require bounded extraction/adaptation; do not replace the whole application provider. Selected components import no other local component families in the inspected import statements.

Data boundaries:

Implemented `resources/admin/src/integration/warehouseReconciliation.ts` as the bounded callback adapter: pins reviewed SKU/warehouse/versions, omits front-end operator identity, preserves the operation key after response loss, rejects changed retry payloads, validates the receipt and maps it to ZIP36 fields. Vitest passed its lost-response/retry test and TypeScript checking passed. It is not yet mounted into a ZIP36 host; the HTTP function is deliberately supplied by that host.

Warehouse reconciliation callback contract confirmed: ZIP36 sends masterSku, warehouseId, newSafetyStock, reason, expectedVersion and operatorName. Existing POST /admin/api/inventory/reconciliations accepts balance_id, balance_version, stock_version, safety_stock, operation_key and reason only; operator identity/reservations are server-derived. Adapter must resolve the exact SKU+warehouse balance, retain both versions from the reviewed read, retain one operation key across retries, and map the server receipt into ReconcileInventoryReceipt. Do not silently refresh versions at submission or send operatorName as authority. Shared SubNavHeader, types/index and index.css were additionally copied into the private source directory for dependency inspection; transitive type imports remain to be resolved.
- Available adapters: warehouse CRUD, balance provisioning, physical count review/confirm, safety/reservation reconciliation, store routing, transfers, catalog SKU association, order exceptions, audited stock tasks.
- Backend gaps: operating-mode conversion and global activation, reviewed store order-accounting activation, exact ZIP36 callback request/result adaptation, enabled stock broadcast state/readback projection.
- Source local state is baseline material, never evidence of server persistence. Any retained source mock flow must remain explicitly labelled pending real logic.

Baseline status: original desktop/mobile rendering, source build command, converted visual comparison and latest component host are still pending. Existing `/admin/marketplace/inventory-preview-33` is not a ZIP36 baseline. Use localhost:8500 and the existing branch; no new server or worktree is authorized. Determine the isolated source-baseline mount before integration, keeping official routes unchanged.

Source baseline follow-up: a private Vite harness now builds the untouched WarehouseInventoryReconcileModal plus ZIP36 index.css into locally ignored public/build-zip36-source. Browser URL: http://127.0.0.1:8500/build-zip36-source/index.html. It uses explicitly labelled mock balances and an always-blocked submission callback, no real API writes. Vite build passed; browser verified Step 1 and Proceed to Review -> Step 2. This is only a targeted component baseline, not the full module or desktop/mobile screenshot parity acceptance. Source itself can display a ledger reserved quantity while its supplied allocated-order list is empty; real adapters must provide consistent ledger/coverage data. Build from the private source directory with the existing resources/admin/node_modules/.bin/vite.cmd build; no extra server was started. Compiled baseline is excluded locally from Git and is not release content.
