# Inventory ownership decision before physical baseline persistence

Verified current owner: product_variants.stock is one shared unallocated quantity per JW SKU; InventoryMutationService serializes Catalog, Checkout, Returns and Marketplace changes. No separate physical balances per warehouse or safety-stock owner were found in the current inventory migrations. ZIP33 has one warehouse annotation per SKU, but also channel warehouse destinations and per-store safety percentages. Those source fields do not define their accounting meaning.

Recommended R1 contract for user review:

1. One JW stock pool per master SKU, shared across stores and the website. Warehouse/bin on a SKU is an annotation; provider location is a separately reviewed push destination. Do not claim independent physical warehouse balances or transfers.
2. SKU safety quantity is deducted once when determining shared ATP. A store's safety percentage reduces that store's advertised quantity only: floor(shared ATP * (100 - percent) / 100). It does not reserve units or deduct JW stock again. Showing the same shared ATP in several stores is not a physical allocation guarantee; asynchronous overselling risk remains.
3. Physical count is explicitly entered, with actor from the authenticated session. Current availability and platform snapshots do not initialize physical stock. Existing pending website and marketplace obligations must be reconciled before deriving the baseline.
4. Historical open orders require explicit reserve/already-accounted/exception disposition. No blanket timestamp exclusion. Activation and remote write opt-in remain separate.
5. If merchants need independent balances in several physical warehouses, implement warehouse-SKU balances, source allocation and transfers first; do not emulate them using per-store percentages or a single stock field.

This document proposes business semantics. It is not approval of the model, physical adjustments, platform writes, route cutover or deployment. Await the stock-pool/warehouse and safety-percentage decision before implementing physical baseline persistence.

## User decision — 2026-09-07

User explicitly answered A to shared-SKU versus independent-warehouse accounting. Approved: one shared JW pool per SKU for website and stores; warehouse/bin is a location annotation. Independent warehouse balances and transfers are not part of this selected model. This resolves the stock-pool ownership blocker. It does not authorize remote stock overwrites, deployment or formal route cutover. Per-store percentage semantics remain the proposed advertising-buffer rule above, not a second deduction from shared stock.

Implementation sequence under this decision:
1. Build a server-side obligation preview covering website pending_payment/paid/processing order items plus actual reserved Marketplace allocations. Keep unsupported/historical orders visible for disposition; never sum provider snapshots as physical stock.
2. Establish reviewed physical/safety baseline with authenticated actor, explicit count, revision and idempotent operation identity; compute shared availability only after obligations are reconciled. Preserve current legacy availability until reviewed.
3. Integrate physical effects with checkout reservation, shipment consumption, cancellation and received returns. Source inspection confirms CheckoutService currently deducts at checkout and restores at cancellation, including an allowed shipped -> cancelled transition; physical accounting must distinguish goods already shipped rather than blindly treating that restoration as physical receipt.
4. Wire ZIP33 review UI to persisted preview/confirmation, then implement store opt-in/outbox with all remote writes disabled until separately authorized.

Next tests must cover shared-pool cross-store reservations, pending website obligations, stale review, shipment/cancellation/return provenance, and no duplicate safety deduction. The existing availability ledger alone is not a completed physical ledger.

## Superseding direction — selectable inventory modes

User subsequently requested both shared and independent inventory as a merchant-selectable option. This supersedes an implementation limited to shared mode; the earlier A answer must not be treated as permanent shared-only scope. Independent may mean channel/store allocation quotas or separate physical warehouse balances. A clarification is pending, including whether both axes are required. These are different owners: stock pools and physical locations cannot be conflated, and quotas must not duplicate physical units.

Retain the mode-neutral order-obligation read endpoint, tested locally with 13 assertions, but do not persist a shared-only physical baseline schema while this distinction is unresolved. Mode switching requires a reviewed conversion of existing reservations, totals and in-flight synchronization, not a cosmetic boolean. No defaults may rewrite current stock. ZIP33 does not yet supply this newly requested mode-selection/conversion interaction; keep source-fidelity boundary for any new control.

## Resolved clarification and ZIP34 implementation boundary

User clarified independent inventory means physical warehouses, approved the design prompt, and supplied ZIP34. This resolves the preceding pending clarification. Shared mode owns one balance per SKU; independent mode owns one balance per physical warehouse and SKU. Multiple stores bound to one warehouse reuse that balance. Store identity is never another physical stock owner.

Initial storage uses inventory_warehouses, inventory_pools and inventory_balances. It creates no default warehouse, active mode, pool or baseline. Nullable physical, reserved and safety values represent unestablished quantities. A count and reconciled obligation coverage are both required for a numeric ATP read; shortfall remains explicit. Legacy product_variants.stock and its writers remain authoritative until the separately reviewed takeover is implemented.

Still required: authenticated warehouse management, active-mode configuration with revision, baseline review/confirmation with session actor and immutable application ledger, reservation provenance and existing-order dispositions, transactional transfers, reviewed mode conversion, store routing, controlled outbox and ZIP34 source-faithful integration. Storage and read projection alone do not implement or authorize stock takeover. ZIP34 localStorage callbacks must not become production write handlers.
