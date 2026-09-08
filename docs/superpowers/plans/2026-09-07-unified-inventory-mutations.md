# Unified inventory mutation implementation plan

Approved by user on 2026-09-07: unify Catalog, Checkout, Returns and Marketplace writes; retain ZIP presentation and keep provider writes disabled.

1. Add stock_version to product_variants and immutable inventory_movements with variant, unique operation key, delta, resulting quantity/version, actor and reason. Implement InventoryMutationService for version-checked absolute adjustments and idempotent delta changes. Test duplicate/conflicting identity, transaction rollback, insufficient quantity, stale absolute writes. Do not infer physical inventory.
2. Route StockAllocationService reserve/release through that service while preserving tombstones and zero-delta consumption. Test reservation lifecycle and both existing MySQL races with the new schema.
3. Route checkout decrement/cancellation and return receipts through stable order-line/return keys, preserving current payment, notification and return workflows. Run checkout/cancellation/returns acceptance suites, including rollback and duplicate events. No real payment or shipping.
4. Catalog reads expose opaque stock_version; existing-variant writes require matching version for quantity changes. Carry version through React data bindings without changing source DOM/classes. Preserve metadata-only changes; reject stale absolute stock instead of overwriting. Add feature and frontend tests.
5. Test competing adjustments/checkout/Marketplace in disposable MySQL. Review all direct application stock writes and initialization paths. Apply additive local migration only after verification; browser-check existing form save/conflict handling. Record evidence before release synchronization. Provider write opt-in remains disabled and baseline/physical ownership is still pending.

Current canonical development root is C:/xampp/htdocs/jwstudio-ecommerce. No worktree-only implementation and no independent deployment. No per-step commits: follow user phase synchronization gates.

## Approved execution and first implementation evidence

User explicitly selected the unified inventory service direction on 2026-09-07. No further approval is needed for the stated local Catalog/Checkout/Returns/Marketplace convergence. Real platform inventory overwrites remain unauthorized.

Implemented InventoryMutationService with per-variant serialization, durable operation identity/hash, stock version, audited deltas and version-checked absolute adjustment. Added additive migration 000008; no historical physical balances are inferred. Marketplace allocation reservation/release now use this writer; consumption preserves existing no-second-decrement behavior.

Test-first failures proved missing writer/ledger and missing allocation integration before implementation. Fresh local suite: 226 tests / 1393 assertions. Disposable MySQL order-compete passed with two processes, one allocation, stock three and one allocation event. These tests do not yet verify catalog conflict UI or checkout/return unified audit; those writers remain pending. Migration has run only in test schemas, not the business database. No deployment or platform write performed.

## Checkout and return integration checkpoint

Checkout now performs audited delta writes under its existing transaction after order/item identities exist. Variant locks are retained during validation; insufficient combined demand still fails atomically in the shared mutation service. Cancellation uses order-item identities; return receipts use the return ID and authenticated actor. Existing cancellation and return state guards remain intact, and no payment/refund/shipping protocol changed.

Regression tests first failed because checkout/cancel and return audit rows were absent, then passed after integration. Expanded Checkout/ReturnRequest/Marketplace/Shopee/Lazada/StockAllocation/InventoryMutation suite: 267 tests / 1637 assertions passed; diff check passed. Local business migration 000008 applied successfully after tests. No platform calls or remote deployment. Existing catalog absolute-stock writes are still pending migration to version checking, so unified inventory ownership is not yet complete.

## Catalog conflict protection checkpoint

Catalog now returns stockVersion and validates existing-variant updates under stable variant row locks. Quantity changes without a version and any supplied stale version are rejected with a field error, rolling back the product update. Accepted quantity adjustments pass through InventoryMutationService with authenticated actor and a catalog reason; metadata writes no longer directly overwrite stock. Existing forms preserve this optional field through their existing object-spread state and JSON submission; only the TypeScript data contract changed, not ZIP DOM/classes.

HTTP test first reproduced the stale form succeeding (200), then passed with 422 after an intervening audited sale and succeeded only with the fresh version. Existing catalog round-trip fixture now carries the server-provided version. Fresh Catalog/Checkout/Returns/Marketplace/Inventory regression: 250 tests / 1651 assertions; diff check passed. Browser conflict acceptance, MySQL absolute-adjustment competition, initialization/audit deletion policy and full remaining direct-write audit remain pending. No deployment.

## MySQL adjustment and browser checkpoint

Extended tests/manual/stock-allocation-mysql-race.php with adjust-compete. Two independent workers contend under an initial variant lock: one audited deduction, one absolute adjustment using version zero. Valid outcomes retain the deduction in either serialization order. Actual run passed with stock 3, version 1 and one stale adjustment rejection. Disposable schema removed.

Local browser fixture: product 51, variant 92, title Inventory Conflict QA 20260907, draft, handle inventory-conflict-qa-628b77f0. This is newly created local synthetic data, not the same-numbered production variant. Opening the editor showed 10; a local audited synthetic deduction changed it to 8/version1. Several save attempts did not produce a captured conflict toast or close the editor. Read-back still shows stock8/version1/one movement. Therefore browser request/feedback acceptance remains UNPROVEN; no success claim. Fixture retained as a draft for reproduction, no production data touched. Next: establish whether save reaches API, then verify the actual error notification and fresh-version save. Existing PHP request tests do not replace this gate.

## Browser conflict and recovery verified

Fresh Chrome acceptance closed the earlier observation gap. Temporary local-only request diagnostics established that the real product PATCH reached Laravel and returned 422. A short bounded DOM observation captured: Product Save Failed / Inventory changed. Reload the product before saving. No frontend defect was established; earlier snapshots missed the transient notification.

Discarded the stale form and reloaded. Browser table showed seven units (after the second synthetic deduction). Fresh-version save emitted Product Published / Saved "Inventory Conflict QA 20260907" with 1 variant SKUs and closed the editor. The generic existing success title does not mean this draft was publicly published. Test fixture product51 was then archived locally, retaining its two audit records; variant92 stock7/version2 verified. All temporary instrumentation removed from controller/provider and both diagnostic files deleted. No production mutation or deployment. Browser conflict and reload/save recovery gate now passed.

## Inventory history deletion guard

Application stock-write audit found no remaining decrement/increment calls outside InventoryMutationService; the remaining stock assignments are initialization/read projections plus Catalog's new-variant attributes. Existing-variant Catalog stock is removed from metadata attributes and handled by the versioned writer. Initial quantity provenance still needs explicit baseline treatment; this is not physical-ledger completion.

Fresh expanded regression before the deletion fix: 314 tests / 1882 assertions. New HTTP regression reproduced 500 when replacing a SKU that has inventory_movements. Catalog now rejects that removal with a field validation message directing the merchant to disable the SKU instead. Transaction rollback preserves the original SKU/stock/audits and removes the attempted replacement. Targeted post-fix CatalogApiTest/InventoryMutation/StockAllocation: 24 tests / 150 assertions; diff check passed. No remote release or platform write.

## Full backend verification checkpoint

Complete php artisan test --compact finished successfully: 981 passed, 1 skipped, 6889 assertions, 146.58 seconds. One environmental skip remains reported; no claim that skipped coverage ran. During that run the legacy product-delete path was separately fixed and verified with the final targeted InventoryMutation/CatalogApi suite: 17 passed / 129 assertions. Browser conflict acceptance from the preceding checkpoint remains applicable; no frontend markup changed.

Legacy ProductController deletion now locks variant rows and rejects products with inventory movements using a validation message to archive instead. HTTP test first reproduced 500 then verified validation and preserved product. New frontend serialization/error regression passed: reviewed stockVersion is included in PATCH and Laravel's conflict message reaches the existing caller's error handling. git diff --check passed. Phase is local only, no GitHub/Hostinger sync in this checkpoint.

## Unified movement read contract

Protected GET /admin/api/inventory/movements requires variantId and supports page/per_page (maximum100). Returns newest-first id, SKU identity, operation key, availability delta/result, version, actor, reason and timestamp. Internal request hashes are excluded. This belongs to general Admin inventory so it does not depend on the Marketplace read flag. It never replays operations or changes stock.

Test-first absent-route failure followed by passing authentication/non-admin denial, SKU isolation, pagination, missing/invalid input, private-field exclusion and unchanged database checks. Targeted InventoryMovement/InventoryMutation/CatalogApi/MarketplaceReconciliation: 22 tests /180 assertions. Diff check passed. UI history presentation is not implemented by this API and no deployment occurred.
