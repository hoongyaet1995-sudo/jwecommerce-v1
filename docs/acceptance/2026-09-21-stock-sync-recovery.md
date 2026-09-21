# Stock Sync Recovery — local implementation and acceptance

Date: 2026-09-21. Base HEAD: `b0a50e49aa0529b5487469e513f67409c0bb1ecd`, uncommitted local work.
Mode: REAL_DATA_BINDING. Operational provider closure remains pending.

## Boundary

Implement backend details, read-only provider check, and direct stock recovery for one store/SKU pair. Bind ZIP 43's Stock Sync Details modal in a local-only preview. Reuse the existing storefront ledger and inventory history. Do not activate/relist products, replace the official inventory route, deploy, or replace existing intake/transfer workflows.

Preview: `http://127.0.0.1:8500/admin/marketplace/inventory-preview-43` (authenticated, local environment only).

## Contracts

Base: `/admin/api/inventory/store-management/stores/{store}/skus/{variant}/sync`

| Action | Contract | Ownership and safeguards |
| --- | --- | --- |
| Details | GET base | MySQL ledger, current mapping, readiness and persisted observations; no provider call or balance creation |
| Check Status | POST base + `/check` | Provider observation only; persists readback and reconciles matching uncertain tasks; never enqueues or sends stock |
| Resync Stock | POST base + `/resync`, `{operation_key: UUID}` | Reads latest counted ATP on server; direct provider call; absolute stock only; ignores no client quantity (unsupported fields rejected) |
| Audit | Existing history endpoint | `platform_sync` operation plus immutable attempt records; records operator, target pair and quantity; `ledger_changed=false` |

All endpoints require an administrator with inventory.edit (or super-admin). Write/check endpoints are throttled. Responses are no-store and expose business summaries rather than raw exceptions or credentials. No new frontend credential handling.

Recovery uses a per-pair lock, database transaction/version checks and a persisted idempotency key. Existing processing/uncertain updates block a second send. Outstanding transfers cannot be bypassed. A stock version change during send produces a superseded result rather than a current confirmation. Only balance revision changes are labeled Replaced by Newer Stock. Authorization, first count, mapping, pause and configured write gates are respected.

Direct resync does not depend on the worker. Existing automatic dispatch and legacy retry behavior remain unchanged. After an ambiguous send, bounded retries are reads only; a mismatch stays uncertain. A database error after provider success leaves the request in flight rather than marking it retryable. Long-running or interrupted processing tasks may require operational reconciliation; this phase does not add automatic resend or watchdog behavior.

## Source extraction manifest

- Private archive: `C:/Users/User/Downloads/jw-ecommerce-dashboard (43).zip`
- ZIP SHA256: `C93772BBDC220BC7C3E6C8E608AE01AF76402F8D99C664B3CCDC848516A9640C`
- Private extracted source: `C:/Users/User/.codex/private/jw-inventory43`
- Selected source: `src/components/marketplace/inventory/StockSyncDetailsModal.tsx`
- Original modal SHA256: `6D7AAEA0732A5A04AFC8271838CF6E1154AEE2623C93A354E1B6212CFCFBE1A3`
- Selected type dependency: `stockSyncTypes.ts`; existing compatible inventory types and MarketplaceLogo reused.
- Imported presentation: `resources/admin/src/previews/inventory43/StockSyncDetailsModal.tsx`.
- Existing inventory page has an opt-in `recoveryPreview` prop; default false preserves official-route behavior.
- Excluded: Sync States (14 Frames), entire ZIP app/shell, source mock inventories, full transfer-page redesign, old duplicated settings CTAs.
- Source modal baseline run with Vite at port 8543, real React rendering and confirmation-button interaction.
- Removed simulated timer success. Source handlers now call the real contract; success requires returned/readback state.
- Small existing-pattern adaptations: Check Status also available before Resync, unavailable actions disabled, unknown quantities and authorization messages reflect actual data. No new layout/classes were invented for the modal.
- Initial-count success rows in the preview can open the same recovery adapter.

## Capability mapping and evidence

| Capability | Producer → owner → consumer | Evidence | Remaining gate |
| --- | --- | --- | --- |
| Pair details | Store chip → StockSyncRecovery → MySQL → ZIP modal | Feature tests; authenticated local browser | Provider evidence and preview acceptance |
| Readback | Check Status → provider observe → inventory_sync_observations → modal | No-write test; uncertain match/mismatch and identity-change tests | Actual connected provider readback in this environment |
| Direct resync | Confirm → readiness/version/idempotency → publisher → readback → audit | Direct-send, duplicate, uncertain, permissions, flags and version-change tests | Real provider write/readback acceptance |
| Recovery navigation | Reconnect → existing auth page | Browser reached `/admin/marketplace/auth` | None for navigation |
| History | Direct attempt → inventory_store_operations + attempts → existing history | History API assertion | Browser verification with actual completed recovery |

Local-only additive migration applied: `2026_09_21_200000_create_inventory_sync_observations.php`. No stock count, allocation or provider stock was changed for browser acceptance.

Authenticated local data differs from production: 74 SKUs were awaiting first count, and the inspected Lazada official-test authorization was expired. This is an observed readiness blocker, not a successful provider sync test. No expired authorization was renewed and no arbitrary count was entered.

### Browser and visual evidence

- Source desktop/mobile: `output/playwright/stock43-source-desktop.png`, `stock43-source-mobile.png` (1440×1000 / 390×844).
- Converted component harness: `stock43-converted-desktop.png`, `stock43-converted-mobile.png` (same fixture; UI-only callback fixtures, not provider proof).
- Authenticated MySQL-backed preview: `stock43-local-details-desktop.png`, `stock43-local-details-mobile.png`.
- Unknown stock is displayed as Unknown; no Resync action is offered for expired authorization. Reconnect Store navigation verified.
- Original source baseline had only a missing favicon request, no component runtime failure.

### Reproduction

Backend tests (isolated SQLite, never migrate:fresh on application MySQL):

```powershell
$env:APP_CONFIG_CACHE='bootstrap/cache/testing-no-config.php'
C:\xampp\php\php.exe artisan test --compact --filter='StockSyncRecoveryTest|StoreInventorySyncTest|StoreSkuSyncReadiness|StockSyncPolicy'
```

Frontend from `resources/admin`:

```powershell
npx vitest run src/previews/inventory43/StockSyncDetailsModal.test.tsx src/previews/inventory39 src/integration/storeInventory.test.ts
npx vite build --config vite.inventory43.config.ts
```

Recorded passes: backend 26 tests / 151 assertions; frontend 29 tests; isolated production build passed. Existing repository-wide TypeScript errors were not repaired; the filtered typecheck reported no inventory43/StockSync error. A full-project clean typecheck is not claimed.

## Release gates

Not deployed, not pushed, not committed. Official-route cutover still requires preview acceptance under the source-fidelity contract. A real eligible counted SKU and valid store connection are needed for provider acceptance. Preserve this limitation rather than calling simulated publisher tests an operational closure.

The pre-existing P3-001 master checklist baseline is not marked closed by this work.

## Approved official cutover

User explicitly approved production release after preview on 2026-09-21 and reported renewed Lazada test access. `Inventory39Runtime` now enables the recovery integration on the official inventory route. Official localhost route and modal verified before commit; `stock43-official-local.png` records the browser check.

Release regression: 27 backend tests / 153 assertions, 29 frontend tests and complete Admin build pass. Added coverage that a valid renewed official-test lease is not rejected due to an old Seller OAuth expiry field (the real token manager uses the lease for these credentials).

Release includes only stock recovery source, tests, additive observations migration, isolated preview source and rebuilt Admin assets. Unrelated dirty checkout/storefront files are excluded. Production migration must use the exact new migration path, not all pending migrations.
