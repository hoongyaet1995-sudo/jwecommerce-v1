# Marketplace Inventory Sync Operational Closure

Date: 2026-09-21  
Local repository: `C:\xampp\htdocs\jwstudio-ecommerce`  
Verified local implementation SHA: `ad62221`  
Plan: `docs/superpowers/plans/2026-09-21-marketplace-inventory-sync-operational-closure.md`

## Current acceptance state

Tasks 1–8 are locally implemented and verified. The phase is **not operationally closed** yet: GitHub/Hostinger release, live read-only readiness, and approved Shopee/Lazada write-readback-restore canaries remain open under Tasks 9–10.

No production provider write was authorized or performed during local verification.

## Implemented contract

- Stock writes fail closed behind a master switch, one switch per provider, strict canary enforcement, and a persisted `platform:store_id:listing_variant_id` identity.
- Readiness is evaluated for the exact JW SKU and store listing. Unrelated unmapped listings do not block an eligible SKU.
- Marketplace order ingestion starts at an idempotent per-store/per-SKU boundary and checks unresolved historical reservations before stock becomes eligible.
- Shopee writes an absolute model/item quantity for one verified seller location, then confirms it using an independent provider read.
- Lazada writes an absolute ItemId/SkuId quantity for the selected warehouse identity, then confirms it using an independent provider read.
- Ambiguous writes become `uncertain` and are never blindly repeated. Recovery reads provider truth first.
- Queue workers re-lock tasks and balances, reject stale revisions, and re-run readiness before provider transport.
- Workers run only on the `marketplace` connection and queue. Every processing and terminal provider outcome is appended to an immutable attempt-event ledger.
- A store can be partially ready: eligible SKUs continue to sync while blocked SKUs remain excluded with exact reasons.
- The existing single-store intake/adjustment modal keeps JW Storefront and shows only marketplace stores whose exact SKU is externally ready.
- Admin rows display JW ledger stock separately from provider-confirmed, queued, processing, failed, authorization-expired, uncertain, superseded, and unsynchronized states.

## Verification evidence

### Focused Laravel inventory/provider suite

Command:

```powershell
$files = @(Get-ChildItem tests/Feature/StoreInventory*.php | ForEach-Object FullName) + @(
  'tests/Feature/ShopeeStoreInventoryPublisherTest.php',
  'tests/Feature/ShopeeClientTest.php',
  'tests/Feature/LazadaStoreInventoryPublisherTest.php',
  'tests/Feature/LazadaClientTest.php',
  'tests/Feature/MarketplaceInventoryIngestionTest.php',
  'tests/Feature/LazadaInventoryIngestionTest.php',
  'tests/Feature/SandboxInventoryWriteGuardTest.php'
)
C:\xampp\php\php.exe artisan test @files
```

Result: **179 passed, 760 assertions, 1 skipped** across the final broad and focused reruns. Coverage includes explicit Shopee/Lazada HTTP 5xx ambiguity, immutable attempt retention, exact provider identity snapshots, mapping replacement rejection, and recovery that resumes dependent work. The skipped thumbnail-resize test requires PHP GD, which is not enabled in this CLI runtime. No inventory/provider test failed.

### Focused Admin behavior suite

Command:

```powershell
cd resources/admin
npm test -- --run src/previews/inventory39/components/marketplace/inventory/StockIntakeAdjustmentModal.test.tsx src/previews/inventory39/components/marketplace/MarketplaceInventoryAdjustmentTarget.test.ts src/integration/storeInventory.test.ts
```

Result: **11 passed** in the final targeted run. Coverage includes eligible/ineligible dropdown accounts, partial store readiness, persisted readiness/status adaptation, and recovery labels.

### Full Admin Vitest suite

Command: `npm test -- --run` from `resources/admin`.

Result: **627 passed, 3 failed** across 155 files. The three failures are outside the changed Inventory 39 files:

1. `src/integration/marketplaceAttributeImages.test.ts` — fixture response is rejected as an invalid attribute-image response.
2. `src/integration/__tests__/sourceInventory.test.ts` — the stored 123-component manifest differs from 129 source files.
3. `src/previews/marketplace30/components/marketplace/MarketplaceMappingPage.test.tsx` — expected legacy `read-only` title copy differs from the current review copy.

### TypeScript and build

- `npm run lint` remains red on existing cross-module typing debt in AI Auto Reply, shipping, catalog, motion, and older marketplace previews. No diagnostic originated from the Task 7 files changed in commit `79f4ea9`.
- `npm run build` from `resources/admin` passed and produced the Inventory 39 Admin bundle under `public/build-admin`.
- `git diff --check` passed for the final worktree.

## Runtime and browser evidence

- The isolated Laravel server responded to `/admin/marketplace/inventory` with the expected protected `302` redirect to `/admin/login`.
- Authenticated visual browser acceptance could not be completed in this run because the desktop browser surface could not reach the host-local isolated port. No claim of authenticated browser closure is made.
- Admin bundle compilation plus Laravel feature tests cover the implemented behavior, but authenticated localhost acceptance remains a release gate.

## No-write proof

Local configuration at verification time:

| Control | Value |
| --- | --- |
| Master stock-sync switch | `true` |
| Shopee stock-sync switch | `false` |
| Lazada stock-sync switch | `false` |
| Canary enforcement | `true` |
| Canary identities | `0` |

The provider-specific switches and empty canary allowlist made transport impossible. The business database contained **0 sync tasks** and **0 queued/processing/uncertain tasks** after verification. Provider calls in automated tests used Laravel HTTP fakes. Production must be deployed with all three write switches disabled.

## Database and schema impact

A new additive migration creates `inventory_store_sync_attempts` and adds immutable listing/location snapshots to each sync task. The ledger stores append-only worker/admin attempt events with task ID, attempt number, status, actor/source, target quantity, bounded provider receipt, and timestamp. Restrictive foreign keys prevent task or actor deletion from erasing audit identity. The local migration ran successfully as batch 94. Existing inventory balances and operation receipts remain unchanged.

## Environment switches required for release

Deploy with:

```dotenv
MARKETPLACE_STOCK_SYNC_OPERATIONS_ENABLED=false
MARKETPLACE_SHOPEE_STOCK_SYNC_ENABLED=false
MARKETPLACE_LAZADA_STOCK_SYNC_ENABLED=false
MARKETPLACE_STOCK_SYNC_CANARY_ENFORCED=true
MARKETPLACE_STOCK_SYNC_CANARY_IDENTITIES=
```

Enable only one provider and the exact approved canary identity during Task 10. Clear configuration cache after every switch change.

## Rollback

1. Disable the master and both provider stock-sync switches and run `php artisan optimize:clear`.
2. Stop the inventory queue worker if a task is processing; inspect provider truth before retrying any `uncertain` task.
3. Application rollback may revert commits `79f4ea9`, `fb9ae70`, `75fe610`, `6ad1f3a`, `6a2e776`, `249f7e2`, and `3da6e22` in reverse order, then rebuild `public/build-admin`.
4. Keep the attempt ledger during an application rollback for audit continuity. Drop it only if the whole phase is intentionally rolled back and its evidence is no longer required.
5. Do not reverse a committed JW ledger operation to compensate for a provider failure. Use a reviewed inventory correction and retain both audit records.

## Remaining production gates

- Push and deploy the exact accepted SHA with writes disabled.
- Prove localhost, GitHub `main`, and Hostinger use the same SHA.
- Complete authenticated live read-only UI/API checks.
- Record one concrete Shopee Sandbox and one Lazada official-test canary with store/listing/location identity, token health, reservations, before/target/restore quantities, and rollback commands.
- Obtain explicit approval for those exact values before enabling any provider write.
- Complete write, independent readback, restore, second readback, and verify no unresolved queue task remains for both providers.
