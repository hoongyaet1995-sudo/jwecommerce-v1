# Marketplace Publishing Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Preserve the user's earlier Native execution preference.

**Goal:** Give staff a persistent publishing-task entry, actionable failure explanations, safe recovery, and an explicitly acknowledged new attempt when the prior result is uncertain.

**Architecture:** Extend the existing publishing pipeline with a server-owned review policy, safe diagnostics and a one-attempt recovery authorization. Preserve old task outcomes and existing mappings. Reuse the supplied UI components and move long-running result checks into persisted bounded batches.

**Tech Stack:** Laravel, PHP, MySQL, React/TypeScript Admin, Vitest, PHPUnit, existing scheduler/queue infrastructure.

**Spec:** `docs/superpowers/specs/2026-09-20-marketplace-publishing-recovery-design.md` (user approved).

## Global Constraints

- Development truth: `C:\xampp\htdocs\jwstudio-ecommerce`.
- Marketplace 物流仅使用各平台物流；EasyParcel 不参与。
- 保留历史任务，记录操作责任，不删除旧任务或把未知结果伪装成失败。
- TikTok、Shopify 在其真实发布适配器具备能力前不显示可执行重推按钮。
- 已有 variant mapping 不自动转移到强制新建的商品。
- 一次 override 只对这次审查有效，不永久解锁。
- Keep Google AI Studio component structure/classes; no developer state-preview tools in production.
- Read diagnostics through an allowlist; never expose tokens, raw provider bodies or signed request URLs.
- No real product create or forced retry during agent verification; user performs final publication acceptance.
- Do not stage unrelated checkout/storefront changes. Group a coherent locally verified phase before approved release.

## Review Focus

1. Two staff members authorize against the same old task: only one new attempt may consume that blocker snapshot (Task 3).
2. Reload or lost stage response after acknowledgment: restore the new request identity, never silently dispatch or return to the old task (Task 5).
3. Provider parent exists but size chart failed: catalog match must not report the whole operation complete (Task 4).
4. Old tasks contain no diagnosis: show missing historical evidence rather than guessing a token/brand error (Tasks 1, 6).
5. Catalog pagination fails, changes, or exceeds a bound: report incomplete verification, never infer absence from a partial scan (Task 4).

## File structure and interfaces

Create under `app/Services/Marketplace/`:

- `PublishingTaskReview.php`: `read(int $taskId, int $actorId): array` and `forProduct(int $storeId, int $productId, int $actorId): array`; read-only authorized task details and available actions.
- `PublishingFailureDetails.php`: `fromThrowable(Throwable $error, string $stage): array`; deterministic safe code/message/next-action classifier.
- `PublishingRecoveryAuthorizations.php`: `issue(int $taskId, int $actorId, array $confirmation): array` and `consume(string $authorizationId, int $actorId, int $storeId, int $productId, Closure $create): int`; one-use audited recovery.
- `PublishingResultChecks.php`: `start(int $taskId, int $actorId): int`, `advance(int $checkId): void`, `read(int $checkId, int $actorId): array`; readback and checkpoint policy.

Create backend tests in `tests/Feature/MarketplacePublishingRecoveryTest.php`, `MarketplacePublishingDiagnosticsTest.php`, `MarketplacePublishingResultCheckTest.php`, plus `tests/Unit/PublishingFailureDetailsTest.php`. Add a test helper under `tests/Support/PublishingRecoveryFixture.php` for saved source/store/token fixtures.

Create frontend `resources/admin/src/marketplace/PublishingTaskHistory.tsx`, `PublishingTaskRecovery.tsx`, and `PublishingRetryConfirmation.tsx`, each with an adjacent test. Extend existing modal rather than redesigning it.

### Task 1: Persist diagnostics and expose task review/history

**Files:** new `database/migrations/2026_09_20_000100_add_marketplace_publishing_recovery.php`; `PublishingTaskReview.php`; modify `MarketplacePublishingController.php`, `PublishingStoreStatus.php`, `routes/web.php`; recovery feature tests.

**Contract:** keep `products: Record<string,string>` backward-compatible; add `tasksByProduct` with task ID/status. Add paginated `GET /admin/api/marketplace/publishing-tasks?storeId=&productId=&status=&page=`. Existing GET detail adds `stage`, `failure`, `creationCertainty`, `blockers`, `actions`, `previousTaskId`, `nextTaskIds`, and `mappingState`.

- [ ] Add nullable task columns: stage, error_code, safe_error_message, safe_diagnostics JSON, failed_at, previous_task_id (restrict delete), recovery_authorization_id (unique nullable). Add per-task optimistic revision for mutating diagnostics/transitions.
- [ ] Establish a test harness that asserts SQLite before migrations. Use a nonexistent test APP_CONFIG_CACHE path, forced testing/SQLite environment and `Http::preventStrayRequests()`; never run RefreshDatabase before verifying resolved DB driver.
- [ ] Write and run failing tests for legacy task 5 shape, latest task selection, stable pagination, no credentials in output, unauthorized access and GET without writes. Example assertion:

```php
$this->actingAs($actor)->getJson("/admin/api/marketplace/publishing-tasks/{$id}")
    ->assertOk()->assertJsonPath('creationCertainty', 'unknown')
    ->assertJsonPath('failure.code', 'historical_reason_unavailable');
Http::assertNothingSent();
```

- [ ] Implement authorized reads using existing admin rules; all list/detail/action endpoints must use the same scope. Calculate actions from persisted status plus local capability/connection checks; GET does not refresh tokens or call provider.
- [ ] Keep history visible even when currently blocked or disconnected. Review distinguishes confirmed parent from complete listing and mapping conflict from pending readback.
- [ ] Run `C:\xampp\php\php.exe vendor/bin/phpunit tests/Feature/MarketplacePublishingRecoveryTest.php` under the isolated harness; expect no failures.

### Task 2: Record the actual failing stage and actionable reason

**Files:** `PublishingFailureDetails.php`; modify `MarketplacePublishingDispatchService.php`, `PublishingTasks.php`, `MarketplacePublishingController.php`, `LazadaClient.php`, `ShopeeClient.php` as needed for typed safe diagnostics; diagnostic tests.

**Contract:** failure `{code,message,stage,field?,sku?,nextAction,diagnostics}`; stage is one of review, credentials, schema, images, warehouses, create, variants, size_chart, persist_result, mapping. Raw exception strings never become public by default.

- [ ] Write red tests for expired credentials, changed source, invalid attribute, provider rejection, transport uncertainty, failure after parent creation and redaction. Unit example:

```php
$details = PublishingFailureDetails::fromThrowable(new RuntimeException('token=secret'), 'create');
$this->assertStringNotContainsString('secret', json_encode($details));
$this->assertSame('create', $details['stage']);
```

- [ ] Record stage before each operation. Persist approved diagnostics in the same outcome transition, with compare-and-set so late workers cannot overwrite a newer state.
- [ ] Preserve known field/SKU errors from validated internal exceptions; unknown errors give task reference and admin/check-connection actions without guessing brand or title corrections.
- [ ] Capture pre-task failures as structured response fields displayed persistently in the wizard. Do not create fake publishing tasks for failed form validation.
- [ ] Record mapping readback failures without downgrading confirmed creation. Interrupted processing becomes uncertain with an explicit interruption reason.
- [ ] Run diagnostics tests and existing `MarketplacePublishingTaskTest.php`; verify API writes are never automatically retried after uncertain create.

### Task 3: One-attempt recovery authorization and new-task lineage

**Files:** `PublishingRecoveryAuthorizations.php`; migration from Task 1; modify `PublishingTasks.php`, `MarketplacePublishingController.php`, `PublishingStoreStatus.php`, `routes/web.php`; recovery feature tests.

**Contract:** POST `/publishing-tasks/{task}/recovery-authorizations` accepts `reason`, `checkedMarketplace`, `acceptDuplicateRisk`, `confirmationVersion`, `expectedRevision`. Returns opaque authorization ID and expiry. Stage accepts optional `recovery_authorization_id` and passes it through both platform adapters. Normal corrected retry also links previous_task_id.

- [ ] Create authorization table with actor/store/product/previous task, blocker snapshot and hash, confirmation audit, expires_at (30 minutes), consumed_task_id. Record the exact acknowledged old task set including existing mapping identities.
- [ ] Write red tests: missing checkboxes; wrong actor/store/product; expired authorization; processing task; unsupported platform; changed blockers; two distinct authorizations from same revision; repeated identical request key.

```php
// Both authorizations were issued against the same old blocker set.
$first = $this->stageWithAuthorization($authA, $requestKeyA);
$this->stageWithAuthorization($authB, $requestKeyB)->assertConflict();
$this->stageWithAuthorization($authA, $requestKeyA)->assertJsonPath('id', $first->json('id'));
```

- [ ] Lock store first, authorization second, matching existing lock order. Recompute blocker snapshot immediately before insert; exclude no task merely because the browser supplied an ID. Insert new task and consume authorization in one transaction. Network-lost repeats return the saved task only after actor/payload/source identity verification.
- [ ] A new retry chain head becomes current; older unknown attempts remain recorded but can only be bypassed through the exact audited authorization. Other live attempts, permissions and eligibility remain blockers.
- [ ] Preserve existing mappings. Override permits a new create, never a mapping overwrite; mapping conflicts appear in task review.
- [ ] Verify genuine concurrent requests using a dedicated disposable MySQL test database, not business DB. If that environment is unavailable, report concurrency verification incomplete rather than claiming SQLite proves row locking.

### Task 4: Resumable provider result checks and mapping recovery

**Files:** `PublishingResultChecks.php`; new `app/Jobs/CheckMarketplacePublishingResult.php`; extend migration with result check cursor/status/heartbeat/matches; modify `LazadaPublishingReconciliation.php`, `PublishedCatalogMapping.php`, controller and existing scheduler runner; result check feature tests.

**Contract:** POST `/publishing-tasks/{task}/result-checks` returns `{checkId}`; GET `/publishing-result-checks/{check}` returns saved progress; POST `/{check}/resume` resumes incomplete readback. Only one active check per task. Mapping recovery uses verified known item identity and existing catalog pullItem.

- [ ] Inspect actual catalog runner/queue connection before binding jobs. Use asynchronous persisted scheduling; sync queue must never perform a full scan in an HTTP response.
- [ ] Write red fixtures for known Lazada parent, full SKU match, ambiguous duplicate match, partial variants, size-chart pending, expired token, failed page, changed catalog total, mapping conflict and worker restart.

```php
// One completed page is not evidence that a SKU is absent.
$checks->advance($checkId);
$this->assertDatabaseHas('marketplace_publishing_tasks', ['id'=>$taskId,'status'=>'uncertain']);
Http::assertNotSent(fn ($request) => str_contains($request->url(), '/product/create'));
```

- [ ] Prefer known ID. For unknown IDs use only verified supported provider queries; Lazada fallback processes one bounded page or detail slice per job and checkpoints after saved results. Lease/CAS prevents stale worker checkpoints.
- [ ] Incomplete/unstable scans retain unknown certainty and provide manual check plus risk acknowledgment path. Do not change to rejected based only on cached absence. Completed stable readback can record no match, with timestamp and scope, without claiming absolute absence under eventual consistency.
- [ ] A full SKU match confirms parent/variants only. Keep size-chart stage unresolved unless independently verified; show Seller Center correction when no reliable API repair exists. Never call create from readback or replay unknown non-idempotent writes.
- [ ] Shopee known parent readback uses existing item/model read adapters. Unknown parent without supported reliable search gets explicit Seller Center SKU instructions and acknowledged retry. No fake recovery support for TikTok/Shopify.
- [ ] Re-read a confirmed listing to repair missing mappings; stop polling on stable conflicts and offer the existing manual mapping workflow.
- [ ] Run result-check, existing Lazada reconciliation, publishing task and mapping tests in isolation.

### Task 5: Browser recovery contracts and new request identity

**Files:** modify `marketplaceOperations.ts`, `marketplaceData.ts`, `publishingReviewSession.ts`, `submitLazadaReview.ts`, `submitShopeeReview.ts`, `usePublishingStoreStatus.ts`, `useMarketplacePublishingTask.ts`; adjacent integration tests.

**Contract:** extend PublishingTask with Task 1 fields. Recovery context `{authorizationId,previousTaskId}` is part of reviewed submission identity. Persist server task reference independent of browser request data.

- [ ] Write red tests for lost stage response, reload after acknowledgment, old localStorage uncertain key, invalid localStorage JSON and status-query failure.

```ts
expect(await publishLazadaWithRecovery(actorId, reviewedWithAuthorization)).toBe(newTaskId);
expect(stagePayload.recovery_authorization_id).toBe(authorizationId);
// Opening history and reading status must never dispatch.
expect(dispatchCalls).toHaveLength(0);
```

- [ ] Namespace new submission identity by authorization; do not erase original request identity. Recover only that attempt on response loss and reject mismatched product/store receipts.
- [ ] Separate failed GET from uncertain create. Keep task link across reauthentication and provide explicit Retry status action.
- [ ] Refresh store/task summaries after recovery transitions; prevent late responses from a previous store replacing current state.
- [ ] Run Vitest integration tests for both submit adapters, review session, marketplace operations and task hooks.

### Task 6: Staff task entry, persistent explanations and retry confirmation

**Files:** three new frontend components listed above; modify `PublishProductModal.tsx` under previews/marketplace32, `OperationalMapping.tsx`, and its mapping-page action props; component tests.

- [ ] Read `google-ai-studio-laravel-fidelity` before UI work; identify source confirmation, error, progress, history/list and navigation patterns in the supplied ZIP and existing converted components. Record source paths before editing.
- [ ] Write red interaction tests: clicking Review existing task opens task 5; it calls no dispatch; missing reason remains honest; disconnected store action navigates correctly; risk acknowledgment opens a fresh three-step review; closing does not create anything.

```tsx
await userClick('View publishing task');
expect(screenText()).toContain('Task #5');
expect(screenText()).toContain('Historical failure details were not saved');
expect(createCalls).toHaveLength(0);
```

- [ ] Reuse existing async status cards inside PublishingTaskRecovery; render only server-approved actions. Remove generic brand-change suggestions from live task failures.
- [ ] Add task history/filter entry to Mapping; paginate server-side. Provide task deep link so another login/device can reopen the same review.
- [ ] Retry confirmation includes reviewed store/product, possible existing ID, required risk checks and reason. Successful acknowledgment enters editable review, not create. Revalidate current schema and retain only valid values.
- [ ] Display field/source/image errors in the appropriate step; task-stage errors stay in task details. Show next destination in button text.
- [ ] Add loading/error/retry states for status and result checks; close and reopen retain server progress. Use actionable mapping-conflict terminal state rather than an endless spinner.
- [ ] Run component/hook regression tests, including focus/keyboard close, narrow screen and store-change races.

### Task 7: Verify, review and release the coherent phase

**Files:** create `docs/fidelity/2026-09-20-marketplace-publishing-recovery-evidence.md`; generated `public/build-admin` assets only after source checks.

- [ ] Run scoped backend and frontend tests; record red/green evidence. Run Admin full tests and `npm run lint`, capturing complete exit status/logs, and classify preexisting failures by baseline comparison. Do not claim lint passes from an unfinished process.
- [ ] Run `npm run build` in resources/admin. Verify generated asset changes correspond only to this phase.
- [ ] Browser acceptance on localhost using isolated/test fixtures: task 5 shape, rejected retry, token failure, partial parent, duplicate-risk dialog, interrupted check and cross-session reopen. Final create remains mocked.
- [ ] Review permission, race and sensitive-output boundaries; map every spec acceptance point to evidence. Review UI against ZIP, not an invented design.
- [ ] Summarize source, migrations and evidence before deployment approval. Preserve prior approval only where clearly applicable; no production task reset or real publishing is bundled with code release.
- [ ] After release authorization: commit only scoped files, push main, deploy same SHA and additive migrations with the Hostinger deploy skill. Ensure result-check scheduler works. Verify live authenticated task view, asset HTTP 200, three matching SHAs and unchanged original task 5 outcome until user chooses an action.

## Self-review

- All 11 spec acceptance requirements map to Tasks 1–7.
- Recovery does not introduce a new blanket Super Admin-only restriction; existing publishing permission governs the action.
- Known-parent confirmation, size-chart completion and mapping completion are distinct.
- Original uncertain task is retained, and authorization applies to exactly one fresh reviewed attempt.
- No implementation or production mutation has been performed while preparing this plan.
