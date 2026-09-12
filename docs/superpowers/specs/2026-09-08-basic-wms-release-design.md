# Marketplace Inventory Console + Basic WMS

Status: chat design and written specification approved by user on 2026-09-08.

## Outcome and release boundary

Publish Basic WMS to https://ecom.jwaistudio.online as an online development and acceptance release. This is not production inventory takeover. The complete Marketplace/WMS objective remains; advanced capabilities follow in Phases 2 and 3.

Develop only in C:/xampp/htdocs/jwstudio-ecommerce, on the existing branch, using http://127.0.0.1:8500. ZIP36 supersedes earlier ZIP visual sources. Preserve its layouts, classes, assets and interactions; adapt data and action bindings. Do not create another application or temporary inventory ledger.

## Phase 1 capabilities

- Display JW legacy inventory, Shopee/Lazada catalog snapshots and WMS physical balances as distinct sources.
- Reuse product/SKU mapping and explicit import with same-store uniqueness and cross-store reuse.
- Choose shared pool or independent physical warehouses during initial configuration.
- Manage physical warehouses and store-to-warehouse bindings without activating order routing.
- Record product inbound, outbound and manual adjustments in the existing WMS balance and movement tables.
- Show authenticated actor, server timestamp, reason, before/after quantities and balance version.
- Show Basic WMS available balance and safety buffer, with explicit exclusion of orders not taken over.
- Read official sandbox data and permit bounded test stock writes only for eligible designated test stores.
- Show actionable missing-count, invalid mapping, expired credential, insufficient quantity and failed-operation states.

## Independent operation

Add a Basic WMS enablement flag separate from inventory accounting, automatic orders and stock broadcasting. Every new Basic WMS mutation must enforce its server-side gate. Existing staging paths must not accidentally become unrestricted public mutation routes.

Basic WMS operations update existing physical balances and append movements. They must not update product_variants.stock, create or alter legacy order reservations, enable accounting, or enqueue marketplace stock broadcasts. Existing storefront inventory behavior continues unchanged.

Initial mode selection is allowed without requiring all existing catalog SKUs to have counted balances. Unknown inventory remains unknown. It must not infer physical quantities from legacy stock or platform snapshots. If existing staged balances conflict with the selected mode, return a conflict identifying the required reconciliation; do not move or discard balances silently. Once configured, switching modes is deferred to Phase 2.

Existing reviewed count/delta transactions, actor checks, version checks and idempotent confirmation should be reused. Inbound and outbound use signed deltas with an explicit operation reason. An uncounted balance requires an initial physical count before delta operations. Outbound quantities exceeding physical stock are rejected, never clamped. Retrying a confirmed operation returns its original receipt.

## Quantity semantics

Legacy JW availability and platform catalog quantities are display-only sources for this release. WMS physical quantities are explicitly counted/manual-ledger quantities.

Basic WMS available quantity is max(0, physical - WMS reserved - safety). Because order takeover is disabled, it must state that existing/unallocated order demand is excluded. Do not label unknown reservations as verified zero or mark order reconciliation complete merely to display an ATP number. Expose a separate Basic WMS projection if the current verified physical projection requires advanced obligation reconciliation. Display deficits separately. This projection cannot authorize production stock writes.

## Test isolation and platform access

Persist clear test classification for test stores, warehouses, SKUs and orders, and expose it in existing UI patterns. Test quantities must remain distinguishable from operating stock throughout reads and audit records. Do not infer eligibility solely from a name prefix or from the website hostname.

Platform stock writes default off. A test write requires an explicit test-operation flag, a designated test store, server-side verified provider/store identity and matching SKU mapping. Fail closed for unknown identity, expired credentials or non-test stores. Store credentials encrypted server side. Record attempted target, quantity and provider outcome without tokens or secrets. Provider rejection or an uncertain result must never be presented as success or blindly retried.

## Deferred capabilities

Phase 2: existing-balance mode conversion, order reservation/automatic deduction, history takeover, store takeover activation, inter-warehouse transfers, multi-stage reconciliation and automatic return receipts.

Phase 3: complete customer OAuth acceptance, production merchant writes and full fulfillment/logistics/return lifecycle. Existing implementations and prior evidence remain reusable; their existence does not enable them in Basic WMS. Advanced entry points require server-side disablement, not only hidden buttons.

## Verification and release

One coherent local verification batch covers initial mode selection, warehouse and binding persistence, mapped SKU opening count, inbound, outbound, adjustment, ledger/actor/time checks and source-separated balances. Confirm replay safety, stale-review rejection and negative-stock rejection. Verify legacy stock and order allocations are unchanged by Basic WMS actions; advanced mutation routes and non-test platform writes remain disabled.

Reuse prior tests where behavior is unchanged. Add focused tests for the new boundaries rather than repeating every old test manually. Perform one authenticated browser acceptance of the batch against localhost and then one online acceptance after release.

At release readiness, report the batch and obtain the project-required synchronization approval. Commit locally verified code, push main, deploy that exact commit to /apps/jwecommerce, and verify matching SHAs and HTTPS behavior. Do not deploy uncommitted code or commit .env. Phase 1 acceptance does not mark the overall Marketplace goal complete.

## Current code findings

config/marketplace.php already separates accounting, read, import, stock sync and fulfillment flags. PhysicalCountReviewService updates legacy availability only through activeProjection when accounting_enabled is true. PhysicalBalanceReader currently requires obligations_reconciled_at for verified ATP, so Basic WMS needs explicit projection semantics instead of fabricating reconciliation. InventoryModeReviewService currently requires every enabled SKU to have reviewed source balances; this advanced conversion prerequisite must not govern initial Basic WMS setup.
