# Marketplace Inventory Sync Operational Closure

**Date:** 2026-09-21

**Status:** Approved design; pending written-spec review

**Scope:** Per-SKU Shopee and Lazada inventory synchronization from the JW inventory ledger

## Problem

JW Ecommerce can commit physical stock changes to its internal store ledgers and enqueue external synchronization tasks, but the current integration cannot yet prove end-to-end Shopee and Lazada inventory updates:

- production marketplace stock synchronization is disabled;
- every connected marketplace store is still in `setup_required`;
- readiness currently blocks an entire store when any active listing is unmapped or historical order is unresolved;
- the operational `StoreInventoryPublisher` only publishes Lazada and explicitly rejects Shopee;
- Lazada write success is accepted from the write response without a provider read-back;
- there is no bounded canary allowlist and restore workflow for the first real stock writes.

The existing store-wide readiness rule conflicts with the accepted operator behavior: staff should be able to update a mapped and counted SKU even when unrelated listings in the same store are not yet mapped.

## Product Decision

Marketplace inventory synchronization is eligible per store SKU, not per store catalog.

A store SKU may receive JW ATP broadcasts only when all of the following are true:

1. The marketplace store is connected and its authorization is valid.
2. The marketplace listing variant has exactly one mapping to a JW product variant.
3. The JW variant has a counted balance in the current inventory mode.
4. The balance has no unresolved reservation or ingestion-boundary conflict for that store SKU.
5. The provider adapter supports a confirmed absolute-quantity write and read-back.
6. The platform stock-write flag is enabled.
7. During canary rollout, the store SKU is explicitly allowlisted.

Unmapped or uncounted listings remain visible as setup work but do not block other eligible store SKUs. JW never imports provider-reported stock as physical inventory.

## Approaches Considered

### Selected: Per-SKU readiness with canary rollout

Each mapping has an independently derived synchronization capability. Store state summarizes its SKU states, while every queued task rechecks the exact mapping, balance revision, authorization, platform capability, and canary policy before sending.

This allows safe incremental adoption, matches the inventory dropdown behavior, and contains provider failures to one SKU.

### Rejected: Whole-store activation

Requiring every active listing, count, and historical order to be resolved before any stock write preserves a simple store status but makes partial catalog adoption impractical. One unrelated listing can block hundreds of valid mappings.

### Rejected: Direct synchronous writes from the modal

Writing to providers during the HTTP request gives immediate feedback but couples internal ledger commits to provider latency and ambiguous failures. It also increases partial-success risk and makes safe retries harder.

## Readiness Model

### Store summary

The store summary remains one of:

- `setup_required`: no eligible mapped SKU exists, authorization is invalid, or the provider adapter is unavailable;
- `partially_ready`: at least one SKU is eligible and at least one SKU still requires setup;
- `active`: all currently mapped, participating SKUs are eligible and external sync is enabled;
- `paused`: external writes are administratively paused while inventory ownership and balances remain intact.

The summary is informational. The task worker always applies the per-SKU gate.

### Per-SKU state

Each store SKU exposes one of:

- `not_mapped`;
- `count_required`;
- `reservation_conflict`;
- `authorization_expired`;
- `provider_unsupported`;
- `sync_disabled`;
- `canary_not_allowlisted`;
- `ready`;
- `queued`;
- `processing`;
- `confirmed`;
- `failed`;
- `uncertain`.

The Actual Stock Intake and Stock Audit dropdown includes only the website account and marketplace store SKUs whose effective state permits a real external sync. An ineligible marketplace selection must never silently perform a local-only update while implying that the provider will change.

## Historical Order Boundary

Historical marketplace orders must not block unrelated SKUs or require destructive cleanup.

For each connected store, JW records an explicit ingestion boundary timestamp when inventory synchronization is activated for the first eligible SKU. Orders created before the boundary remain historical reference data unless they were explicitly reconciled into JW reservations. Orders received after the boundary must be routed through the inventory reservation ledger.

Activation is blocked only when an open historical order contains the exact mapped variant being activated and JW cannot determine whether its quantity is already represented in the current physical balance. The operator receives a concrete reconciliation action for those order lines.

The boundary operation is idempotent and audited. It does not alter marketplace orders, provider stock, or JW physical stock.

## Provider Publishing Contract

`StoreInventoryPublisher::publish()` remains the queue worker boundary. Provider-specific publishers implement the same contract:

```php
publish(object $store, object $listing, int $quantity, string $eventKey): array
```

The returned receipt contains the provider, requested quantity, observed quantity, provider request identifier when safe, event fingerprint, confirmation timestamp, and confirmation method.

### Shopee

The Shopee publisher uses `/api/v2/product/update_stock` with the mapped `item_id`, `model_id`, and the verified seller `location_id`. It sends an absolute seller-stock quantity and requires exactly one matching success row.

After the write, it reads the exact item/model seller-stock location. The task becomes `confirmed` only when the observed quantity equals the authoritative JW ATP. A connection failure or non-conclusive response after a send produces `uncertain`, never an automatic blind retry.

Sandbox and production credentials remain separated by the persisted store environment and credential source. A sandbox store can never use production credentials.

### Lazada

The Lazada publisher continues using `/product/stock/sellable/update` with the mapped ItemId, SkuId, and warehouse code when required. A successful write response is followed by a read of the exact product SKU and warehouse sellable quantity.

The task becomes `confirmed` only after the read-back matches JW ATP. Rate limits may use bounded backoff. An ambiguous send or mismatched read-back produces `uncertain` and requires reconciliation before retry.

## Queue, Idempotency, and Concurrency

- Internal ledger mutation commits before provider dispatch.
- Each task stores the balance revision and desired ATP.
- Before sending, the worker recomputes authoritative ATP and supersedes stale tasks.
- Only one task per store SKU may be `processing`.
- A repeated event key cannot produce a second provider write for an already confirmed quantity and balance revision.
- A newer balance revision supersedes older queued work.
- `uncertain` tasks require provider read reconciliation before retry.
- The existing Hostinger `marketplace:cron` worker remains the only production consumer; no duplicate cron or supervisor is added.

## Configuration and Canary Controls

Use separate server-side controls:

- master marketplace stock-write switch;
- Shopee stock-write switch;
- Lazada stock-write switch;
- canary enforcement switch;
- allowlisted store/listing variant identities.

Configuration contains identifiers only, never tokens or secrets. The production master switch remains disabled until local and sandbox verification passes.

Canary eligibility is checked in both readiness output and immediately before provider dispatch. Removing an item from the allowlist prevents new sends without modifying the ledger.

## Canary Acceptance Protocol

The first real write for each platform uses one user-approved test listing with no active customer reservation.

1. Verify store identity, environment, authorization, mapping, provider location/warehouse identity, current JW ATP, and current provider quantity.
2. Record a redacted acceptance snapshot.
3. Choose a bounded target quantity that cannot oversell and differs from the provider quantity.
4. Add only that store SKU to the canary allowlist.
5. Commit one JW inventory adjustment through the normal reviewed ledger flow.
6. Observe the queue task through `queued`, `processing`, and `confirmed`.
7. Read the provider quantity independently and prove it equals JW ATP.
8. Restore the original JW quantity through the same reviewed flow.
9. Prove the provider returned to the original quantity.
10. Remove the temporary canary allowlist entry or retain it only by explicit operational decision.

If the first write is `uncertain`, stop all writes for that platform, reconcile by read, and do not restore until the actual provider quantity is known.

## UI Behavior

The existing Google AI Studio inventory design remains unchanged except for truthful status and eligibility behavior.

- Actual Stock Intake and Stock Audit retain the current single-store dropdown.
- The dropdown shows the JW storefront and only the current SKU's eligible marketplace accounts.
- Store and SKU readiness details explain why an account is hidden or blocked.
- Activity History separates internal ledger success from provider broadcast status.
- `confirmed` means provider read-back matched the requested ATP.
- `queued`, `processing`, `failed`, `auth_expired`, and `uncertain` remain visible and actionable.

No multi-store batch-entry modal from ZIP 42 is introduced.

## Security and Data Integrity

- Tokens remain encrypted server-side and are never returned to the browser or acceptance evidence.
- Provider payloads use persisted mapping identities, never browser-supplied external IDs.
- Only authorized administrators may create inventory reviews, confirm them, manage sync state, or change canary controls.
- Provider errors are sanitized before persistence and display.
- JW physical stock remains the source of truth; provider snapshots never mutate it.
- Every ledger mutation and provider attempt retains actor/system identity, store, SKU, before/after quantities, balance revision, event key, and outcome.

## Implementation Phases

### Phase 1: Per-SKU readiness

Replace store-wide unmapped-listing and count blockers with per-SKU eligibility. Add the ingestion-boundary model and exact-order-line conflict detection. Preserve store summaries for the UI.

### Phase 2: Provider adapters and confirmation

Implement the Shopee operational publisher, add Lazada read-back confirmation, and standardize provider receipts and uncertain outcomes.

### Phase 3: Canary controls and recovery

Add platform switches, canary policy, readiness exposure, reconciliation commands, and safe retry behavior.

### Phase 4: Local and sandbox acceptance

Run focused feature tests, queue/idempotency/concurrency tests, provider fake-contract tests, Admin build/tests, and authenticated browser checks without production writes.

### Phase 5: Production canaries

Deploy the exact accepted commit, keep master writes disabled, prepare one Shopee Sandbox listing and one Lazada official-test listing, then obtain final approval for the concrete quantities before executing and restoring each canary.

## Acceptance Criteria

1. A mapped and counted store SKU can become `ready` while unrelated listings remain unmapped.
2. An unmapped or uncounted SKU cannot appear as an externally synchronized target.
3. Historical orders block only affected store SKU activation when reservation ownership is ambiguous.
4. Shopee and Lazada both support absolute-quantity writes through the queue publisher boundary.
5. Neither platform reports `confirmed` without an exact provider read-back match.
6. Ambiguous outcomes become `uncertain` and cannot be blindly retried.
7. Platform switches and canary allowlists are enforced at readiness time and send time.
8. Internal ledger commits remain durable when a provider write fails.
9. Queue processing is idempotent and prevents concurrent writes for the same store SKU.
10. The Admin UI distinguishes ledger completion from provider broadcast state.
11. Local, GitHub, and Hostinger run the same accepted SHA before a real canary.
12. One bounded Shopee Sandbox canary and one bounded Lazada official-test canary complete write, independent read-back, restore, and second read-back evidence.

## Out of Scope

- Importing marketplace quantities into JW physical stock.
- Automatically mapping listings without operator review.
- Price, title, media, promotion, or product publishing changes.
- Multi-store atomic provider writes; each provider call remains independently recoverable.
- TikTok Shop, Shopify, EasyParcel, payment, or storefront behavior.
- Enabling all production listings immediately after canary success.
