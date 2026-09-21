# Catalog stock editing and First Count ownership

Status: Approved conversational direction; pending written-spec review.

## Purpose and approved behavior

Keep stock management simple for merchants who only use JW Storefront. Saving a new SKU automatically creates its inventory identity and opening ledger balance. Product Variant stock remains editable until that SKU successfully completes First Count. Thereafter the field is read-only and **Edit in Inventory Management** opens that exact SKU's stock adjustment workflow.

One ledger underlies both screens. Automatic initialization is not a physical verification. Mapping, inventory identity, physical verification, and external synchronization eligibility remain distinct.

## Current implementation evidence

- CatalogApiController accepts inventoryQuantity, exposes product_variants.stock, uses stock_version for existing edits, and conditionally rejects nonzero opening quantities.
- StoreManagement readers use inventory_balances and counted_at to determine counted/readiness state.
- CountMutation currently sets counted_at on its generic apply path; automatic catalog initialization must not accidentally execute that verification behavior.
- AccountResolver and ModeMutation can also populate counted_at. Therefore a non-null balance timestamp alone cannot reliably establish whether an employee completed First Count.
- Existing local changes in order allocation, physical allocation, stock allocation, checkout and storefront files are outside this document's change ownership. Implementation must reconcile their contracts without overwriting or committing unrelated work.

## Lifecycle and quantity meaning

1. New SKU: save the entered non-negative integer, including explicit zero, as the opening physical quantity. Create one inventory identity and its active default balance transactionally. In Independent mode this is JW Storefront; in Shared mode this is the shared pool used by JW Storefront. Do not create another physical copy for each connected store.
2. Before First Count: Product Variant Stock Qty represents the same default physical balance. Edits set its absolute physical quantity and record the resulting delta with actor, source, before/after and reason `Catalog stock update`. Unchanged saves create no stock movement.
3. Initial creation records `Initial catalog stock`. Initialization permits ordinary JW Storefront selling with reservations against this balance, without requiring Marketplace onboarding or First Count. Availability is calculated from physical minus reserved minus applicable safety stock.
4. Successful First Count/verified Allocate Stock atomically records verification and permanently disables catalog quantity writes for that SKU. Failed review/submission does not lock it. Counting zero also locks it. Later zero balance, mapping removal or stock mode changes do not unlock it.
5. After First Count: Product Variant Stock Qty displays total physical quantity across the active ledger scope. Independent store balances are summed once; inactive historical pools and external snapshots are excluded. Provide a concise total-physical label so this is not mistaken for JW Storefront ATP.
6. Product metadata remains editable after verification. A metadata-only save never rewrites inventory, even when its loaded stock snapshot is stale.

## Verification and readiness

Persist a durable SKU-level first-physical-verification marker separate from opening ledger initialization and operational balance provisioning. Set it only on successful explicit physical verification, in the same transaction as the ledger mutation and audit.

The Inventory UI must display initialized but unverified quantities truthfully: show the recorded quantity plus `Not physically verified`, rather than pretending the ledger has no quantity. First Count remains available. Ordinary storefront-only users are not forced through this flow; Marketplace synchronization and stock-mode migration retain their applicable verification readiness checks.

For existing records, backfill verification from trustworthy physical-count/allocation audit evidence. Do not infer verification merely from nonzero stock or automatically generated counted_at. Ambiguous legacy states require a read-only reconciliation report before migration; do not silently overwrite balances, mark them verified, or unlock records already under verified inventory management.

## Write safety and compatibility

Route catalog quantities through one inventory application service, using database transactions and locks in a consistent order. Lock the SKU ownership marker and relevant balance; enforce version checks, actor permissions, nonnegative integer bounds and physical quantity >= reserved quantity. A concurrent First Count must prevent a stale product editor from changing stock.

Reject explicit post-verification quantity changes server-side with a field error and the inventory-management destination. The frontend omits locked quantity mutations. Bulk Set Stock applies only to editable variants; locked variants are visibly excluded.

First Count records an absolute observed total, replacing/reconciling the opening balance rather than adding that quantity again. Existing reservations survive and allocation remains conserved. Repeated submit/retry cannot duplicate opening movements or physical quantities.

product_variants.stock must cease being an independent authority. Its existing storefront/order compatibility semantics must be mapped before implementation; do not blindly copy physical totals into a field that existing order code treats as available stock. Catalog API physical quantity is read from the ledger, and any compatibility projection is updated through the same coordinated mutation boundary. Order reservation, cancellation, consumption and return paths must remain consistent before enabling this lifecycle.

## Navigation and UI

Preserve the existing Product Variant layout and styling except the requested stock-control change. Before verification retain numeric editing. After verification use read-only quantity and the exact CTA **Edit in Inventory Management**.

The CTA uses a stable variant identity, loads fresh data, filters to that SKU and opens its existing Stock Adjustment interface. Shared mode targets the shared pool; Independent mode explicitly selects a store account and cannot adjust an ambiguous aggregate. Permission failures or missing/archived targets show an actionable message. Unsaved product metadata uses the existing unsaved-change guard.

Marketplace connection/mapping does not itself change catalog edit ownership. Unmapped stores receive no allocation or stock broadcast. Catalog initialization must not automatically enable external pushes or mark sync successful.

## Acceptance criteria

- New SKU with 20 units creates one identity, one active opening balance and audit; product and Inventory show the same physical quantity.
- Changing 20 to 15 before First Count records a -5 adjustment; refreshing both views shows 15.
- Saving metadata alone does not change stock or create inventory movements.
- A storefront-only merchant can sell, reserve and cancel against initialized stock without First Count.
- First Count of 18 replaces 15 with 18, locks catalog editing and records the adjustment once; a zero count also locks.
- First Count failure does not lock; repeated successful submission does not double stock.
- A stale or forged catalog request cannot change stock after verification; concurrent order reservations prevent an invalid reduction.
- Mixed verified/unverified variants have correct per-row and bulk-edit behavior.
- The CTA opens the exact SKU adjustment flow, including on refresh and in Independent mode.
- Shared and Independent mode totals exclude duplicate/inactive pools; switching modes does not reset catalog edit ownership.
- Unmapped Marketplace stores do not block basic JW Storefront setup and receive no broadcasts.
- Existing verified SKU quantities, allocations and reservation history survive migration; ambiguous legacy data is reported before correction.

## Delivery boundary

This document approves no production data correction. Implementation begins only after written-spec review and an implementation plan. Develop and verify in C:\xampp\htdocs\jwstudio-ecommerce, including isolated database tests and authenticated localhost browser checks. Release only the coherent scoped changes through the project's approved GitHub/Hostinger sequence.
