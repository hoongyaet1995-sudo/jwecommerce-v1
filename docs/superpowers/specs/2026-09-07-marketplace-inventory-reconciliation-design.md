# Inventory reconciliation execution design — 2026-09-07

## Verified starting point

Read-only production database check after release 81e0da9: store 1 is connected Shopee; one mapping links listing variant 1 to JW variant 92. No marketplace_inventory_policies, marketplace_order_inventory or Marketplace stock allocations exist. This is not evidence of zero physical stock. Order run 5 succeeded across the requested 30 days but inventory processing was intentionally disabled.

Source: MarketplaceOrderInventoryService requires both the global gate and enabled/reconciled store policy. MarketplaceInventoryController returns unknown physical/safety counts. There is no activation API. ZIP32 has no inspected multi-store reconciliation interaction; use the existing AI Studio completion brief for that source gap.

## Recommended implementation

1. Build a read-only store reconciliation preview service first. It must not invoke allocation services, update policies or decrypt payloads into the response. Return an evidence version, last successful order window, connection identity, mapped JW quantities, outstanding order demand, missing mappings and unsupported fulfillment/composition exceptions. Report incomplete coverage explicitly.
2. Persist a merchant-reviewed baseline with employee ID, reason, physical count, safety buffer, historical-order disposition and platform warehouse identity. Lock affected JW variants in stable order and recheck the evidence version before saving. Never infer physical stock from platform quantity or legacy availability. Baseline changes must use one canonical inventory service shared with checkout.
3. Separate accounting activation from remote stock-push opt-in. Activating accounting must atomically establish a cutoff and disposition of existing outstanding orders. A timestamp alone cannot safely exclude unpaid orders created before activation that later become payable, or hold old READY_TO_SHIP orders without reconciliation. Existing historical_order exceptions require explicit review/resolution; do not bypass them by backdating the cutoff.
4. Persist an outbox in the same transaction as availability changes. Coalesce by store/item/model/location, revalidate mapping and policy at dispatch, and compute latest available quantity. Keep global and store write gates default disabled.
5. Validate each Shopee model/location outcome against the submitted quantity. Persist confirmed, rejected and uncertain results separately. Timeouts or incomplete responses require a read-back before any retry; absence of an HTTP error is insufficient.

## Alternatives and impact

Directly enabling existing policies is quick but leaves physical/safety ownership and historical orders unresolved; reject this path. Automatically adopting Shopee stock would overwrite JW truth and mix platform reservations; reject it. A reviewed baseline plus durable outbox adds schema and workflow work but supports shared stock across stores and auditable recovery. Backend work must preserve the current ZIP DOM/classes; missing merchant controls require the supplied AI Studio source completion.

## Evidence gates

- Read preview: no writes, no secrets/recipient data, auth/store isolation, all relevant outstanding orders included, bounded queries/pagination with explicit incompleteness.
- Baseline: stale review rejection, negative availability rejection, outstanding demand disposition, audit identity, concurrent checkout/marketplace serialization on MySQL.
- Activation: historical unpaid transitions and READY_TO_SHIP demand explicitly handled; no duplicate allocation; per-store pause.
- Outbox: transaction rollback and crash recovery; mapping/policy changes; partial/uncertain remote outcomes; stock read-back.
- Actual sandbox: separately authorized quantity change, before/after provider read and JW audit; no production overwrite as test.

This is a proposed implementation sequence, not delivered reconciliation or authorization to change stock. Safe read-only preview implementation can proceed under existing scope; baseline ownership decisions and missing UI source remain explicit review points.

## Local implementation checkpoint

Added protected GET /admin/api/marketplace/inventory/reconciliation requiring explicit storeId. It returns a paginated persisted non-terminal order review list, sanitized store identity and latest successful fetch bounds. Old unpaid orders and unknown statuses remain visible; no timestamp filter silently drops them. coverage_verified and activation_ready remain false because persisted fetch history does not prove all outstanding orders were retrieved.

This is the order-review foundation only: demand calculation, mapping evaluation, evidence-version locking, baseline persistence and activation are not implemented by this endpoint. No UI source was changed, provider called, stock allocated or policy enabled.

Test-first evidence: absent route failed with 404; final protected endpoint checks authentication, non-admin rejection, store isolation, unknown/historical states, pagination, parameter bounds, missing store, disabled read gate, payload/recipient exclusion and unchanged allocation/policy counts. Fresh regression: 220 tests / 1363 assertions passed; git diff --check passed. Code remains local and uncommitted; no release in this checkpoint.

## Demand and mapping checkpoint

Local preview now classifies supported persisted Shopee lines as outstanding, conditional or shipped_review; malformed, unsupported fulfillment/composition and duplicate lines return sanitized exceptions with no misleading quantity plan. Payload decoding remains server-side and only projected demand leaves the service. This reuses the current conservative ingestion boundary, not proof of complete Shopee status semantics.

Mapping resolution checks both listing store and mapping store, restricted to item identities on the current page. Tests prove another store's matching external identifiers are not reused and explicit same-store mapping resolves without allocations or stock changes. Separate failure injection advances the snapshot between page read and annotation; the service returns snapshot_changed with no lines instead of combining old state and new payload.

Fresh local regression: 223 tests / 1380 assertions passed; diff check passed. No provider calls, UI changes, inventory activation or deployment. Still outstanding: full review evidence version across mapping/stock changes, baseline ownership and historical disposition, durable opt-in push/outbox and real sandbox inventory verification. Page demand is not a complete-store aggregate and activation_ready remains false.

## Canonical inventory ownership finding

Source inspection on 2026-09-07 confirms four current stock writers:
- CatalogApiController::replaceProductRelations writes submitted inventoryQuantity as an absolute product_variants.stock value, without a reviewed inventory version.
- CheckoutService::checkout locks variant rows and decrements availability before creating order lines; transition cancellation restores quantities directly.
- ReturnRequestService::transition restores restockable received quantity directly under the return record lock.
- StockAllocationService reserves/releases Marketplace quantities under variant locks and records allocation events; consumption does not decrement again.

Consequently, adding a Marketplace-only physical ledger would leave existing absolute catalog edits and legacy checkout/return writers outside it. A stale product-edit submission can overwrite availability changed since the form loaded. This is a source-confirmed write-path gap; a browser/concurrency reproduction has not yet been run.

Recommended convergence requires a shared inventory mutation service with versioned absolute adjustments, idempotent order/return event identities, stable variant locking, actor/reason audit and atomic stock/outbox updates. Preserve legacy quantities until an explicitly reviewed physical baseline exists. Do not infer historical checkout allocations from current stock, and preserve existing cancellation/return semantics through regression tests. Product form data binding can carry an opaque version without changing ZIP DOM/classes.

An explicit direction question has been sent because this extends implementation across existing Catalog, Checkout and Returns owners. Until answered, continue read-only reconciliation work; do not silently replace those business paths or enable inventory accounting.

## Latest UI direction

User initially selected reuse of ZIP32 controls, then immediately corrected: let Google AI Studio design it. The latest instruction governs: do not implement new reconciliation/activation UI until the new design source is supplied. Existing brief: docs/google-ai-studio/prompts/2026-09-07-marketplace-inventory-multistore.md. Backend work remains authorized.

Internal MarketplaceReconciliationRevision now fingerprints connected-store metadata, mapped availability/version/enabled state, mapping identities, persisted order versions/statuses and policy under store/variant locks. Tests verify stability and invalidation after shared-stock mutation and mapping removal. Five reconciliation tests /47 assertions passed; diff check passed. No confirmation endpoint or UI uses this helper yet; it is not evidence of complete stale-confirmation protection or platform order coverage. Scale testing and lock-duration review remain needed before exposing full-store fingerprinting to interactive requests.

## Revision lifecycle correction

Test-first verification reproduced unchanged review fingerprints when a reserved allocation was consumed without a second availability deduction. Revision input now includes allocation identity/quantity/status and order-inventory terminal/error state, plus external shop identity. Variant lock coverage includes SKUs referenced by this store's historical allocations even if their current mapping was removed. Targeted reconciliation/allocation/mutation tests: 15 passed /93 assertions; diff check passed. This remains an internal fingerprint helper, not an activated confirmation endpoint; no UI or provider writes.

## Transactional revision guard

Added internal withCurrent(storeId, revision, save) wrapping version recheck and database save in one outer transaction. Locks acquired by the nested fingerprint read remain held through the save. Invalid/stale revisions fail before callback execution; callback failure rolls back changes. Caller must still validate admin authorization, order coverage and activation eligibility. The callback is for transactional database work only; no remote writes or notifications belong inside a retryable transaction.

Test-first missing-method failure then targeted reconciliation/mutation/movement suite: 10 passed /87 assertions. Tests cover stale disconnect rejection without callback, failed callback rollback and current revision result. This does not create a merchant confirmation endpoint or activate stores. No remote calls, UI changes or deployment.
