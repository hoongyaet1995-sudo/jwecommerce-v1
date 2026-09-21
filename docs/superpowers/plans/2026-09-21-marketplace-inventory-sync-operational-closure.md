# Marketplace Inventory Sync Operational Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close Shopee and Lazada stock synchronization per mapped SKU with authoritative JW ATP, provider read-back confirmation, bounded canary controls, and reversible production acceptance.

**Architecture:** Keep internal inventory commits atomic and independent from provider calls. Derive readiness for each mapped store SKU, dispatch provider-specific absolute-quantity writes through the existing queue boundary, and mark a task confirmed only after the exact provider SKU/location reads back the authoritative JW ATP. Enforce platform switches and canary allowlists both before enqueue and before send.

**Tech Stack:** Laravel 12, PHP 8.x, MySQL, database queue, Hostinger cron, React 19, TypeScript, Vitest, PHPUnit, Shopee Open Platform API, Lazada Open Platform API

**Spec:** `docs/superpowers/specs/2026-09-21-marketplace-inventory-sync-closure-design.md`

## Global Constraints

- Development truth is `C:\xampp\htdocs\jwstudio-ecommerce`.
- Preserve the current Google AI Studio Inventory UI and single-store intake/adjustment modal.
- JW inventory balances are authoritative; provider quantities are observations only.
- Never import, sum, or copy provider stock into JW physical stock.
- Internal ledger commits survive provider failures.
- No task is `confirmed` without an exact provider read-back match.
- An `uncertain` send cannot be blindly retried.
- Tokens, secrets, signed URLs, and raw provider bodies never enter browser responses, logs, test artifacts, or Git.
- Do not stage unrelated dirty storefront, checkout, payment, or shipping files.
- Keep the production master stock-write switch disabled until the canary preparation task is complete.
- Do not perform a real provider write until a concrete SKU, before quantity, target quantity, and restore quantity have been presented for final approval.
- Release order is localhost verification, GitHub `main`, exact-SHA Hostinger deployment, live read-only verification, then approved canary execution.

## Review Focus

- A mapped and counted SKU remains eligible when hundreds of unrelated listings are unmapped; pin this in Task 2.
- A historical open order blocks only the mapped variant whose reservation ownership is ambiguous; pin this in Task 3.
- A provider accepts a write but the connection drops before response; the task becomes `uncertain` and reconciliation reads before any retry; pin this in Tasks 4–6.
- A queued task passes readiness, then its mapping, balance revision, authorization, or canary entry changes before dispatch; the worker must stop or supersede it; pin this in Tasks 1 and 6.
- Two workers or repeated event keys target the same store SKU; at most one provider send may occur for the same balance revision and quantity; pin this in Task 6.

## File Structure and Interfaces

Create or modify these focused units:

- `config/marketplace.php` and `.env.example` — platform write switches and canary policy configuration.
- `app/Services/Inventory/StoreManagement/StoreSkuSyncReadiness.php` — calculate one store SKU's effective eligibility and blockers.
- `app/Services/Inventory/StoreManagement/StoreSyncReadinessReader.php` — summarize per-SKU states without whole-catalog blocking.
- `app/Services/Inventory/StoreManagement/InventoryIngestionBoundary.php` — create and inspect idempotent order-ingestion boundaries.
- `app/Services/Inventory/StoreManagement/StockSyncPolicy.php` — enforce master/platform/canary gates from persisted identities.
- `app/Services/Inventory/StoreManagement/ShopeeStoreInventoryPublisher.php` — Shopee absolute write and read-back.
- `app/Services/Inventory/StoreManagement/LazadaStoreInventoryPublisher.php` — Lazada absolute write and read-back.
- `app/Services/Inventory/StoreManagement/MarketplaceStoreInventoryPublisher.php` — route supported platforms to their publisher.
- `app/Services/Marketplace/ShopeeClient.php` — reviewed absolute stock transport and exact location read.
- `app/Services/Marketplace/LazadaClient.php` — exact SKU/warehouse sellable read after write.
- `app/Jobs/ProcessStoreInventorySync.php` — recheck policy, stale state, and reconciliation outcomes at dispatch.
- `app/Services/Inventory/StoreManagement/OverviewReader.php` — expose store summary and per-SKU truthful sync state.
- `resources/admin/src/integration/storeInventory.ts` — adapt readiness and sync outcomes.
- Existing Inventory 39 components — display blocker/status information without redesigning the modal.
- Focused feature/unit/Vitest tests named below.

### Task 1: Add server-side platform and canary policy

**Files:**
- Modify: `config/marketplace.php`
- Modify: `.env.example`
- Create: `app/Services/Inventory/StoreManagement/StockSyncPolicy.php`
- Create: `tests/Feature/StoreInventoryStockSyncPolicyTest.php`

**Contract:** `StockSyncPolicy::review(object $store, object $listing): array` returns `allowed`, `code`, and a safe next action. It evaluates persisted store/listing identities and never trusts browser-provided external IDs.

- [ ] Write failing tests for master disabled, Shopee disabled, Lazada disabled, canary absent, canary match, disconnected store, and unsupported platform.
- [ ] Run `C:\xampp\php\php.exe artisan test tests/Feature/StoreInventoryStockSyncPolicyTest.php` and verify failures are due to the missing policy.
- [ ] Add `MARKETPLACE_SHOPEE_STOCK_SYNC_ENABLED=false`, `MARKETPLACE_LAZADA_STOCK_SYNC_ENABLED=false`, `MARKETPLACE_STOCK_SYNC_CANARY_ENFORCED=true`, and a comma-separated server-side canary identity list to configuration.
- [ ] Implement strict identity parsing as `platform:store_id:listing_variant_id`; reject malformed, duplicate, or cross-platform entries.
- [ ] Make the master `MARKETPLACE_STOCK_SYNC_OPERATIONS_ENABLED` flag mandatory in addition to the platform flag.
- [ ] Ensure configuration output and exceptions never expose credentials.
- [ ] Run the focused test and verify green.
- [ ] Commit only the policy/config/test files with `feat(inventory): add stock sync canary policy`.

### Task 2: Replace whole-store readiness with per-SKU eligibility

**Files:**
- Create: `app/Services/Inventory/StoreManagement/StoreSkuSyncReadiness.php`
- Modify: `app/Services/Inventory/StoreManagement/ReadinessReader.php`
- Modify: `app/Services/Inventory/StoreManagement/StoreSyncReadinessReader.php`
- Modify: `app/Services/Inventory/StoreManagement/OverviewReader.php`
- Modify: `app/Services/Inventory/StoreManagement/ModeCatalogReader.php`
- Modify: `tests/Feature/StoreInventorySyncStateTest.php`
- Create: `tests/Feature/StoreInventorySkuReadinessTest.php`

**Contract:** one mapping returns exactly one effective state and blocker list. Store summaries aggregate states as `setup_required`, `partially_ready`, `active`, or `paused` but do not replace the per-SKU gate.

- [ ] Write a failing test where one mapped/counted variant is ready while 709 unrelated listings are unmapped.
- [ ] Write failing cases for missing mapping, duplicate mapping, uncounted balance, reservation conflict, expired authorization, disabled platform, and canary exclusion.
- [ ] Run the new readiness test and confirm red.
- [ ] Implement `StoreSkuSyncReadiness` using the current inventory mode, exact store mapping, counted balance, reservations, connection state, provider capability, and `StockSyncPolicy`.
- [ ] Remove `unmapped_listings` as a whole-store hard blocker; expose its count as setup information.
- [ ] Keep inactive, archived, deleted, and deactivated listings outside participating-SKU totals.
- [ ] Return safe blocker codes and direct actions without provider secrets or raw data.
- [ ] Update store summaries and overview rows to include per-SKU effective state.
- [ ] Run the new test plus `StoreInventorySyncStateTest.php`, `StoreInventoryReadTest.php`, and `StoreInventoryTakeoverTest.php`; update legacy assertions only where the approved semantics changed.
- [ ] Commit with `feat(inventory): evaluate marketplace sync per sku`.

### Task 3: Create an exact historical-order ingestion boundary

**Files:**
- Create: `app/Services/Inventory/StoreManagement/InventoryIngestionBoundary.php`
- Modify: `app/Services/Inventory/StoreManagement/StoreSyncStateMutation.php`
- Modify: `app/Services/Inventory/StoreManagement/ReadinessReader.php`
- Modify: `app/Services/Marketplace/MarketplaceOrderInventoryService.php`
- Modify: `tests/Feature/StoreInventoryOrderLifecycleTest.php`
- Create: `tests/Feature/StoreInventoryIngestionBoundaryTest.php`

**Contract:** activation records one idempotent `orders_created_after` boundary per store variant. Historical orders block only when an unresolved open line maps to that exact variant and its reservation ownership is ambiguous.

- [ ] Write failing tests for an unrelated historical order, an affected mapped line, repeated boundary creation, a post-boundary order, and a pre-boundary order explicitly reconciled into reservations.
- [ ] Run the focused test and verify red.
- [ ] Reuse `inventory_order_activations`; do not add another boundary table unless existing uniqueness cannot represent store variant scope.
- [ ] Implement a transactionally idempotent boundary creator that does not mutate orders, provider stock, or physical balances.
- [ ] Change readiness to query exact mapped order lines rather than every open order in the store.
- [ ] Ensure post-boundary order ingestion reserves once and terminal events release or consume once.
- [ ] Run the new test plus `StoreInventoryOrderLifecycleTest.php`, `MarketplaceInventoryIngestionTest.php`, and `LazadaInventoryIngestionTest.php`.
- [ ] Commit with `feat(inventory): scope order boundary by store sku`.

### Task 4: Implement Shopee operational stock publishing and read-back

**Files:**
- Modify: `app/Services/Marketplace/ShopeeClient.php`
- Create: `app/Services/Inventory/StoreManagement/ShopeeStoreInventoryPublisher.php`
- Create: `tests/Feature/ShopeeStoreInventoryPublisherTest.php`
- Modify: `tests/Feature/SandboxInventoryWriteGuardTest.php`

**Contract:** the publisher sends an absolute quantity for the mapped item/model and verified seller location, then reads the exact location. It returns a receipt only when observed quantity equals requested ATP.

- [ ] Write failing HTTP-fake tests for model and non-model items, correct location selection, wrong location, provider partial success, expired auth, rejected write, connection loss before send, connection loss after possible send, and mismatched read-back.
- [ ] Run `ShopeeStoreInventoryPublisherTest.php` and confirm red.
- [ ] Extract reusable exact item/model/location lookup from the existing sandbox guard without weakening its identity checks.
- [ ] Add an operational absolute-quantity method gated through `StockSyncPolicy`; keep sandbox and live credential sources isolated.
- [ ] Require one matching Shopee success row and perform an independent item/model/location read after the write.
- [ ] Convert expired authorization to `StoreInventoryAuthorizationExpired` and ambiguous send/read outcomes to `StoreInventoryResultUncertain`.
- [ ] Return only safe receipt fields: provider, requested quantity, observed quantity, event fingerprint, safe request identifier, and confirmation timestamp.
- [ ] Run the focused test and existing Shopee sandbox/client/catalog tests.
- [ ] Commit with `feat(inventory): publish and confirm shopee stock`.

### Task 5: Add Lazada exact read-back confirmation

**Files:**
- Modify: `app/Services/Marketplace/LazadaClient.php`
- Create: `app/Services/Inventory/StoreManagement/LazadaStoreInventoryPublisher.php`
- Create: `tests/Feature/LazadaStoreInventoryPublisherTest.php`
- Modify: `tests/Feature/LazadaClientTest.php`

**Contract:** write the exact ItemId/SkuId/warehouse sellable quantity, then independently read that same identity. A successful write response without matching read-back is not confirmation.

- [ ] Write failing HTTP-fake tests for single warehouse, explicit warehouse, absent warehouse identity, rate limiting, auth expiry, ambiguous write, mismatched read-back, and exact match.
- [ ] Run the focused test and confirm red.
- [ ] Add a bounded Lazada read method that returns one exact SKU/warehouse sellable quantity and rejects ambiguous or incomplete provider payloads.
- [ ] Move operational Lazada publishing into its focused publisher and enforce `StockSyncPolicy` immediately before transport.
- [ ] Apply bounded retry only to documented read/rate-limit operations; never automatically repeat an uncertain stock write.
- [ ] Standardize the safe receipt shape with Shopee.
- [ ] Run the focused test, `LazadaClientTest.php`, and existing Lazada stock-contract tests.
- [ ] Commit with `feat(inventory): confirm lazada stock by readback`.

### Task 6: Route providers and harden queue reconciliation

**Files:**
- Modify: `app/Services/Inventory/StoreManagement/MarketplaceStoreInventoryPublisher.php`
- Modify: `app/Jobs/ProcessStoreInventorySync.php`
- Modify: `app/Services/Inventory/StoreManagement/SyncOutbox.php`
- Modify: `app/Providers/AppServiceProvider.php` only if bindings change
- Modify: `tests/Feature/StoreInventorySyncTest.php`
- Create: `tests/Feature/StoreInventoryProviderRoutingTest.php`

**Contract:** the router supports only verified Shopee and Lazada publishers. The worker locks and rechecks the exact balance, mapping, authorization, policy, and canary state before sending.

- [ ] Write failing tests for Shopee routing, Lazada routing, unsupported platform, stale balance revision, mapping changed after enqueue, canary removed after enqueue, duplicate event key, concurrent processing, and uncertain reconciliation.
- [ ] Run the focused tests and verify red.
- [ ] Route by persisted store platform to the focused publisher; remove the current explicit Shopee rejection.
- [ ] Recompute ATP and per-SKU readiness inside the worker transaction immediately before setting `processing`.
- [ ] Supersede stale tasks and fail closed when mapping, authorization, provider capability, or policy changed.
- [ ] Add a reconciliation path that reads provider state for `uncertain` tasks before allowing an operator-reviewed retry.
- [ ] Preserve confirmed-task idempotency and one-processing-task-per-store-SKU behavior.
- [ ] Run `StoreInventorySyncTest.php`, `StoreInventoryProviderRoutingTest.php`, transfer/review tests, and a disposable-MySQL concurrency probe. Report concurrency incomplete if only SQLite is available.
- [ ] Commit with `feat(inventory): harden provider stock sync queue`.

### Task 7: Expose truthful readiness and recovery in the existing Admin UI

**Files:**
- Modify: `app/Http/Controllers/Admin/StoreInventoryReadController.php`
- Modify: `app/Http/Controllers/Admin/StoreInventorySyncController.php`
- Modify: `routes/web.php` only if a reconciliation endpoint is needed
- Modify: `resources/admin/src/integration/storeInventory.ts`
- Modify: `resources/admin/src/previews/inventory39/components/marketplace/MarketplaceInventoryPage.tsx`
- Modify: `resources/admin/src/previews/inventory39/components/marketplace/inventory/StockIntakeAdjustmentModal.tsx`
- Modify: `resources/admin/src/previews/inventory39/components/marketplace/inventory/ActivityHistoryPanel.tsx` if that is the current status surface
- Modify: adjacent Vitest tests

**Contract:** the dropdown contains the JW storefront and only marketplace accounts whose current SKU is externally eligible. Activity History distinguishes ledger success from queued, processing, confirmed, failed, auth-expired, and uncertain provider states.

- [ ] Write failing API and component tests for partially-ready stores, hidden ineligible SKU accounts, visible eligible accounts, provider failure after ledger success, uncertain reconciliation, and no false `Synced` label before read-back.
- [ ] Run focused PHPUnit and Vitest tests and confirm red.
- [ ] Adapt per-SKU readiness without changing the accepted modal DOM or introducing ZIP 42 multi-store inputs.
- [ ] Replace any optimistic `Synced` label with the persisted provider task outcome.
- [ ] Show safe blocker action text and an operator-reviewed reconciliation/retry control for `uncertain` tasks.
- [ ] Ensure refresh/reload restores persisted status and cannot dispatch a write.
- [ ] Run the component tests, `npm run build`, and authenticated localhost browser acceptance.
- [ ] Commit source and generated `public/build-admin` assets with `fix(inventory): expose confirmed marketplace stock state`.

### Task 8: Verify the coherent local phase and prepare release evidence

**Files:**
- Create: `docs/acceptance/2026-09-21-marketplace-inventory-sync-closure.md`
- Update: `docs/acceptance/jw-ecommerce-p1-p4-master-checklist.md` only if the active marketplace row is explicitly brought into scope

- [ ] Run all focused inventory, provider, ingestion, queue, authorization, and UI tests from Tasks 1–7.
- [ ] Run the broader relevant feature group and full Admin Vitest suite; list every unrelated baseline failure instead of hiding it.
- [ ] Run `npm run lint`; compare failures with the pre-change baseline and prove no new error originates from changed files.
- [ ] Run `npm run build` and `git diff --check` on scoped files.
- [ ] Perform authenticated localhost browser acceptance for eligible and ineligible SKUs, ledger success/provider failure separation, and persisted recovery state.
- [ ] Inspect logs and database rows to prove no provider HTTP write occurred during local verification.
- [ ] Record test commands, results, screenshots, schema changes, environment switches, rollback procedure, and remaining production gates in the acceptance document.
- [ ] Request a code review using `superpowers:requesting-code-review`; address findings before release.
- [ ] Commit the coherent verified phase without staging unrelated dirty files.

### Task 9: Release with writes disabled and prepare concrete canaries

**Files:**
- Production environment configuration only after exact-SHA deployment
- Update: `docs/acceptance/2026-09-21-marketplace-inventory-sync-closure.md`

- [ ] Push the accepted local commit to GitHub `main`.
- [ ] Deploy that exact SHA to `/home/u762348670/apps/jwecommerce` using the Hostinger deployment skill.
- [ ] Run additive migrations and `php artisan optimize:clear`; verify localhost, GitHub, and Hostinger SHAs match.
- [ ] Keep master, Shopee, and Lazada operational write switches disabled.
- [ ] Verify the existing hPanel `marketplace:cron` remains the only worker and can drain a harmless non-write queue task.
- [ ] Perform authenticated live read-only checks for per-SKU readiness, dropdown eligibility, history state, assets, and API authorization.
- [ ] Select one Shopee Sandbox mapped listing and one Lazada official-test mapped listing with no reservations and stable provider read access.
- [ ] Record each store/listing identity, JW balance, provider before quantity, proposed target, restore quantity, mapping, location/warehouse, token health, and rollback command in the acceptance document.
- [ ] Present the two concrete canary records for final approval before changing any write switch or quantity.

### Task 10: Execute, confirm, and restore the approved canaries

**Files:**
- Update: `docs/acceptance/2026-09-21-marketplace-inventory-sync-closure.md`

- [ ] Enable the master flag, only the current platform flag, canary enforcement, and only the approved store/listing identity.
- [ ] Clear configuration cache and re-run read-only readiness; stop if any identity, balance, reservation, mapping, or provider quantity changed.
- [ ] Submit the approved JW inventory adjustment through the normal reviewed Admin flow.
- [ ] Observe the exact queue task without manually duplicating the worker.
- [ ] Require `confirmed` with matching requested and observed quantities.
- [ ] Independently read the provider quantity and record redacted evidence.
- [ ] Submit the approved restore adjustment through the same reviewed flow.
- [ ] Require a second confirmed task and independent provider read matching the original quantity.
- [ ] Disable the platform write flag before moving to the other platform.
- [ ] Repeat for the second platform only after the first platform has restored cleanly.
- [ ] Disable all write flags, remove temporary canary entries, clear configuration cache, and verify no queued/processing/uncertain task remains.
- [ ] If any task becomes `uncertain`, immediately disable that platform, read the provider state, and stop; do not send the restore until actual quantity is known.
- [ ] Finalize the acceptance record with before/target/restored values, task IDs, timestamps, exact deployed SHA, and any residual limitations.

## Completion Gate

The phase is closed only when both platforms complete the approved write, provider read-back, restore, and second read-back cycle on the exact deployed SHA; all internal ledger operations remain auditable; no unresolved queue task remains; and the acceptance document contains redacted evidence for every link. Code completion or local tests alone do not close the phase.
