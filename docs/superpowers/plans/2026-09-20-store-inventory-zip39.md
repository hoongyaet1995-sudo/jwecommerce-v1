# Store Inventory ZIP 39 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking. Execution method: Native, preserving the user's earlier choice; no parallel edits to inventory ownership services.

**Goal:** Deliver ZIP 39 Inventory backed by one authoritative shared/store-account ledger, reviewed employee operations and recoverable real channel synchronization.

**Architecture:** Extend existing inventory pools/balances instead of introducing a second ledger. Introduce store-oriented review and operation contracts while retaining reusable allocation/history primitives. Mount the source-faithful UI in an isolated preview before authorized official cutover.

**Tech Stack:** Laravel/PHP, MySQL, React/TypeScript, Tailwind, existing Laravel queue and provider clients.

**Spec:** `docs/superpowers/specs/2026-09-20-store-inventory-zip39-design.md` (user approved).

## Global Constraints

- Work first in `C:/xampp/htdocs/jwstudio-ecommerce`; no application changes only on Hostinger.
- Preserve ZIP 39 selected DOM/classes/assets/responsive composition.
- One global mode applies to every store. No mixed shared/independent groups.
- Unknown physical remains null/Not Counted; no provider-derived initialization.
- Refund alone does not restock; stock transfer does not represent freight movement.
- No EasyParcel, payment behavior or unrelated storefront visual changes.
- Keep the ZIP private. Preserve existing dirty allocation/checkout/payment work.
- No migrate:fresh against business databases. Verify actual test connection before migration-backed tests.
- Keep official route unchanged until preview acceptance. Commit/push/deploy only after explicit phase synchronization approval, overriding generic per-task commit guidance.

## Review Focus

1. Two stores on the same platform must remain distinct; test account identity in task 1.
2. A browser repeats Confirm after losing its response; test saved receipt and mismatched payload in task 2.
3. Shipment arrives before an older unpaid event; test cumulative terminal behavior in task 4.
4. Source platform times out after accepting a stock reduction; test read-back and destination hold in task 5.
5. Admin switches mode while an order arrives; test serialized confirm and durable ingestion in task 6.

## Shared interfaces

New PHP service namespace: `App/Services/Inventory/StoreManagement`.

```php
// DTO array contracts used in all tasks; quantities nullable only for unknown balances.
// Balance: id, variant_id, account_key, physical, reserved, available, counted, version
// Review: id, kind, actor_id, config_version, balance_versions, before, after,
//         blockers, expires_at, status
// Receipt: operation_id, review_id, ledger_status, balances, external_stages
// Error: code, message, field, variant_id, store_id, next_action
AccountResolver::balance(int $variantId, string $channelKey): array;
ReviewService::create(User $actor, string $kind, array $input): array;
ReviewService::confirm(User $actor, string $reviewId, string $operationKey): array;
OverviewReader::read(array $filters): array;
SyncOutbox::enqueue(int $variantId, array $channelKeys, string $operationId): array;
```

Resolve `website` internally; marketplace account identity uses internal store ID and cannot be supplied as an arbitrary external shop ID. Services derive actor, quantities and target store permissions on the server.

## Task 1 — Store accounts and safe test harness

**Create:** `database/migrations/2026_09_20_000001_add_store_inventory_ownership.php`, `app/Services/Inventory/StoreManagement/AccountResolver.php`, `tests/Feature/StoreInventoryAccountsTest.php`, `tests/Support/StoreInventoryFixture.php`.
**Inspect/modify:** `tests/TestCase.php`, existing isolated migration guard, `inventory_pools`, `inventory_configuration` readers. Do not rewrite historical migrations.
**Produces:** AccountResolver contract and fixture `StoreInventoryFixture::sku(array $balances)` returning variant/store/account identifiers.

- [ ] Record git status/diff of existing dirty inventory services before changes. Inspect resolved testing connection with config-cache absent; require SQLite memory or a dedicated `_test` MySQL database and test-only credentials before destructive test lifecycle hooks.
- [ ] Add tests for two Shopee stores, stable JW storefront account, duplicate provisioning, unknown new SKU, explicit-zero newly joined store, and no imported provider stock. Run and observe missing-store-account failures.
- [ ] Add nullable marketplace-store FK to pools, preserve unique pool key and balance-owner key, create review/operation/outbox/account-management storage with unique idempotency/event keys and foreign keys. Introduce `independent_stores` while retaining legacy modes for read-only migration evidence.

```php
$first = $fixture->store('shopee', 'A');
$second = $fixture->store('shopee', 'B');
$this->assertNotSame(
    $resolver->balance($variant->id, 'marketplace:'.$first->id)['id'],
    $resolver->balance($variant->id, 'marketplace:'.$second->id)['id']
);
// Shared mode must resolve both channel keys to the same shared balance.
```

- [ ] Test account/channel validation and database uniqueness under duplicate requests. Run `C:/xampp/php/php.exe artisan test --filter=StoreInventoryAccountsTest` only after the connection guard passes.

## Task 2 — Reviewed count, intake and correction API

**Create:** `app/Services/Inventory/StoreManagement/ReviewService.php`, `app/Services/Inventory/StoreManagement/CountMutation.php`, `app/Http/Controllers/Admin/StoreInventoryReviewController.php`, `tests/Feature/StoreInventoryReviewTest.php`.
**Modify:** `routes/web.php`; reuse `PhysicalCountReviewService` locking/receipt patterns, not warehouse projection assumptions.
**Consumes/produces:** AccountResolver; ReviewService and Receipt contracts.

- [ ] Write endpoint tests for blank count, zero count, shortage below reserved, expired review, unauthorized actor, changed balance version, repeated confirmation, and same operation key with different input. Run red.
- [ ] Implement create-review and confirm endpoints under `/admin/api/inventory/store-management/reviews`. Store immutable review payload and ten-minute expiry. Confirm locks configuration then variant/balances in deterministic order; update ledger, movement and operation receipt in one transaction.

```php
// Count 12 with reservation 3 -> available 9; repeat confirmation changes nothing.
$review = $service->create($actor, 'count', ['balance_id'=>$id,'physical_count'=>12,'reason'=>'Shelf count']);
$receipt = $service->confirm($actor, $review['id'], $key);
$this->assertSame($receipt, $service->confirm($actor, $review['id'], $key));
$this->assertDatabaseHas('inventory_balances', ['id'=>$id,'physical_stock'=>12]);
```

- [ ] Enforce inventory-edit versus administrator-only operation permissions; return structured 403/409/422 errors. Run `artisan test --filter=StoreInventoryReviewTest` with the guarded test connection.

## Task 3 — Overview and audit contracts

**Create:** `app/Services/Inventory/StoreManagement/OverviewReader.php`, `app/Http/Controllers/Admin/StoreInventoryReadController.php`, `tests/Feature/StoreInventoryReadTest.php`.
**Modify:** `routes/web.php`; use existing marketplace catalog/store reads and stock movement sources.
**Produces:** GET overview/stores/history and GET operations/{id} from the approved spec.

- [ ] Write read tests for consistent filtered totals, unknown versus zero, two same-platform stores, shared totals counted once, independent sum, pagination and secret redaction. Run red.
- [ ] Query balances and grouped reservation aggregates without N+1 lookups. Return ledger amounts separately from timestamped provider observations and external task statuses. History uses operation references and sanitized details.

```php
// Shared physical 20/reserved 4 exposed in three stores still totals 20/4/16.
$this->assertSame(['physical'=>20,'reserved'=>4,'available'=>16], $result['totals']['known']);
$this->assertSame(1, $result['totals']['unknown_accounts']);
```

- [ ] Run `artisan test --filter=StoreInventoryReadTest`; inspect query count with multi-store/multi-SKU fixtures.

## Task 4 — Order and storefront inventory ownership

**Modify carefully:** `app/Services/Inventory/StockAllocationService.php`, `PhysicalAllocationService.php`, `OrderAllocationService.php`, `PhysicalReturnService.php`; inventory availability consumers in checkout/cart only where needed.
**Create:** `app/Services/Inventory/StoreManagement/OrderInventoryRouter.php`, `tests/Feature/StoreInventoryOrderLifecycleTest.php`.
**Interface:** `OrderInventoryRouter::apply(string $channelKey, string $orderLineKey, string $eventKey, string $event, int $cumulativeQuantity): array`; resolve mappings/owner server-side.

- [ ] Trace existing order producer paths and identify exactly one allocation call per lifecycle transition; reconcile with pre-existing dirty work before editing.
- [ ] Add failing tests for unpaid reserve, cancellation, partial shipment/cancellation/return, refund without restock, repeat/out-of-order events, insufficient remote stock exception and JW checkout rejection.
- [ ] Route active accounts through AccountResolver. Deduplicate source event and processed line quantities. Consume physical and release reservation atomically. Return restock requires reviewed inspection and is bounded by shipped minus already returned quantity.

```php
// Start 10; reserve 4; ship 2; cancel remaining 2 => physical 8/reserved 0.
// A delayed unpaid event cannot reserve those four units again.
$this->assertSame([8,0], [$balance['physical'],$balance['reserved']]);
// Refund leaves 8; one inspected return changes physical to 9 exactly once.
```

- [ ] Derive active JW storefront availability from the ledger; avoid legacy stock double subtraction. Run new lifecycle tests plus `StockAllocationTest` and `PhysicalAllocationFlowTest`, preserving legacy compatibility until cutover.

## Task 5 — Atomic store transfers and durable platform stages

**Create:** `app/Services/Inventory/StoreManagement/TransferMutation.php`, `SyncOutbox.php`, `app/Jobs/ProcessStoreInventorySync.php`, `app/Http/Controllers/Admin/StoreInventorySyncController.php`, `tests/Feature/StoreInventoryTransferTest.php`, `StoreInventorySyncTest.php`.
**Reuse/adapt:** `app/Services/Marketplace/MarketplaceStockSyncTaskService.php`, `MarketplaceStockSyncDispatchService.php`, existing Shopee/Lazada clients after checking current official provider contracts during implementation.
**Interface:** SyncOutbox contract; `TransferMutation::apply(array $review): array` called only inside reviewed confirm transaction.

- [ ] Test invalid same-account transfer, unknown destination, reserved source quantity, duplicate confirmation, rollback and concurrent orders before implementation.
- [ ] Implement subtraction/addition and durable outbox in one transaction. Persist source/destination task dependencies; website stage is local, unmanaged store stage is not-applicable.

```php
// A physical=10/reserved=3; B physical=4/reserved=1; transfer 5:
$this->assertSame([5,3,9,1], [$a['physical'],$a['reserved'],$b['physical'],$b['reserved']]);
// Transfer 8 must fail; total physical remains 14 after every retry.
```

- [ ] Add tests for response-lost timeout, auth expiry, worker restart, stale queued version and newer order arriving before dispatch. Source uncertain must keep destination increase waiting.
- [ ] Serialize external writes by store/listing, recompute target from latest committed balance, supersede stale tasks, store sanitized provider receipt and read-back state. Targeted retries never repeat ledger mutations.
- [ ] Expose Sync All, operation detail and stage retry using persisted tasks. Run transfer/sync tests with HTTP fakes, then independently verify each supported provider on the authorized test store; never label fake HTTP evidence as provider acceptance.

## Task 6 — Admin takeover, pause and global mode conversion

**Create:** `app/Services/Inventory/StoreManagement/TakeoverMutation.php`, `ModeMutation.php`, `ReadinessReader.php`, `tests/Feature/StoreInventoryTakeoverTest.php`, `StoreInventoryModeTest.php`.
**Modify:** ReviewService dispatch by kind, order ingestion activation routing, configuration readers.
**Interface:** each mutation implements `review(array $input, User $actor): array` and `apply(array $review): array`; readiness returns concrete blocker identities and next_action.

- [ ] Add failing tests for expired connections, unmapped listings, unknown balances, open historic orders, duplicate provider quantities, unauthorized settings, and pause preserving reservations.
- [ ] Implement takeover quantity preview and activation ingestion boundary; reconcile open commitments, retain newer events and recheck versions on confirmation. Pause stops outgoing publishing, not observation/accounting of obligations while a store can still sell.
- [ ] Add global conversion tests for physical conservation, incomplete distribution, active reservations, unsafe safety buffers, pending transfers/sync, and concurrent ingestion.

```php
// Physical=20/reserved=0 -> website=8/storeA=7/storeB=5, never allocate available fallback.
$this->assertSame(20, array_sum($distribution));
// If a reservation appears after review, confirm returns 409 with actionable blocker.
// Durable order input is processed under the winning configuration version.
```

- [ ] Implement admin-only versioned conversion with configuration lock and no provider calls inside DB transaction; enqueue final provider updates after atomic ledger conversion. Run takeover/mode tests and multi-connection MySQL concurrency tests.

## Task 7 — ZIP 39 isolated UI and real adapters

**Create:** `resources/admin/src/previews/inventory39/` containing only selected source components/dependencies; `resources/admin/src/integration/storeInventory.ts`, `storeInventory.test.ts`; preview entry/build config and authenticated Blade host following existing preview conventions.
**Modify:** `routes/web.php` for `/admin/marketplace/inventory-preview-39`; mapping document with per-file hashes/evidence. Do not switch official route.
**Consumes:** tasks 2–6 contracts; existing import product-ID handoff.

- [ ] Extract ZIP privately, inventory transitive dependencies and record hashes. Run original source and capture desktop/mobile overview, transfers, history, settings, takeover, first count and review.
- [ ] Port selected DOM/classes exactly. Keep a narrow context adapter; do not import the entire fixture Admin provider.
- [ ] Write UI contract tests for API-backed metrics, null amounts, asynchronous task state, structured errors, permissions, count queue continue/close and no provider prefill.

```ts
expect(firstCountInput.value).toBe('');
expect(confirmRequest).not.toHaveProperty('platformStock');
expect(retryRequest).toEqual({syncTaskId: failedExternalStage.id});
// Closing the first-count modal stops progression and preserves product filtering.
```

- [ ] Implement request adapters, loading/error states and reviewed events at component boundaries. Correct approved Inventory label; remove timer-derived completion and fixture arithmetic in connected preview. Render unsupported providers as Coming Soon.
- [ ] Run `npm --prefix resources/admin test -- --run src/integration/storeInventory.test.ts src/previews/inventory39`; run selected preview production build and browser interactions. Capture converted screenshots at matching source sizes; report any source surface incapable of rendering required recovery state before altering visual structure.

## Task 8 — Targeted migration and end-to-end acceptance

**Create:** `app/Console/Commands/InventoryStoreCutover.php`, `tests/Feature/StoreInventoryCutoverTest.php`, `docs/acceptance/zip39-inventory-evidence.md`.
**Modify only after preview approval:** `resources/admin/src/marketplace/MarketplaceWorkspace.tsx`, import navigation bridge, Admin inventory labels and relevant acceptance checklist entries.
**Command:** `inventory:store-cutover --dry-run`; applying requires a saved manifest hash and explicit environment, never broad truncation.

- [ ] Test dry-run manifest, ambiguous real-order references, changed manifest rejection, partial-failure rollback and preservation of products/stores/credentials.
- [ ] Produce record-level migration inventory and restorable snapshot. Migrate or retire only identified test warehouse balances; retain old history where feasible. Disable obsolete warehouse writers under new mode. Assert no second ledger remains active.

```php
// Dry run cannot mutate data. Changed record versions invalidate approved manifest.
$this->artisan('inventory:store-cutover --dry-run')->assertSuccessful();
$this->assertDatabaseCount('products', $originalProductCount);
```

- [ ] Run dedicated MySQL tests with two real connections for race behavior; never infer row-lock correctness from SQLite.
- [ ] Verify full import -> filtered inventory -> first count -> review -> persistence -> order -> transfer/sync/history flows on localhost. Record test-store provider write/read-back separately, without inventing physical inventory values.
- [ ] Present preview and evidence for official cutover approval. Wire official route only after approval, rebuild Admin, then rerun route/browser regression.
- [ ] At coherent phase end report exact scope, tests, evidence gaps and request synchronization under AGENTS.md. After approval commit an explicit path allowlist, push main, deploy same SHA with hostinger-laravel-deploy and verify authenticated live behavior.

## Plan self-review

Coverage: source fidelity task 7; accounts/unknown totals tasks 1/3; reviews/import flow tasks 2/7; orders/returns task 4; transfers/provider retries task 5; settings/global mode task 6; reset/rollout/acceptance task 8. Five review-focus cases each have a named owning test. No implementation completion or provider-readiness claim is implied by this plan.
