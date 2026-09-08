# Store Mapping Rule Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enforce the approved per-store one-to-one mapping rule without preventing cross-store reuse. This is the first implementation checkpoint, not completion of the full Marketplace goal.

**Architecture:** Retain the existing mapping API and audit model. Serialize mapping changes on the store row before reading conflicts, and reject a second listing targeting the same JW variant in that store. No UI changes or remote writes.

**Tech Stack:** Laravel, MySQL runtime, SQLite isolated feature tests.

**Spec:** docs/superpowers/specs/2026-09-06-marketplace-oauth-catalog-design.md

## Global constraints

- Develop in the canonical XAMPP directory on codex/marketplace-oauth-catalog; preserve unrelated local files.
- Every external variant has one target; each JW variant has one target per store; cross-store reuse remains allowed.
- Mapping never changes inventory. No automatic reassignment, deletion, publishing or production deployment.

## Task 1 — API invariant

Files: tests/Feature/MarketplaceFoundationTest.php; app/Services/Marketplace/MarketplaceFoundationService.php.

- [ ] Change the existing mapping test to expect `assertStatus(409)` when a second listing in the same store targets the same JW variant; verify the first mapping and audit remain intact.
- [ ] Add a second store/listing and expect `assertOk()` for reuse. After unlinking the original, expect the formerly conflicting listing to map successfully.
- [ ] Run `C:/xampp/php/php.exe artisan test --filter=MarketplaceFoundationTest` and observe the duplicate assertion fail against current code.
- [ ] In `map(int $listingId, ?int $variantId, ?int $productId, int $actorId): void`, resolve listing store, lock store before listing/variant, query existing mapping joined to listing store, and `abort_if($conflict,409,'This JW SKU is already mapped in this store. Unlink the existing mapping first.')`.
- [ ] Run the same tests, verify no stock changes and audit count, and inspect the diff.

## Follow-on checkpoints retained in the full objective

Database uniqueness migration with pre-existing-conflict detection and MySQL concurrency verification; ZIP 30 source preview and approved cutover; real Shopee/Lazada OAuth; paginated catalog ingestion; real seller acceptance; import/publication; order and inventory synchronization; separately scoped remaining providers. Do not mark the full goal complete at Task 1.

## Execution checkpoint

2026-09-06: API invariant implemented locally. Red run reproduced HTTP 200 instead of required 409 for same-store duplicate. Green run: MarketplaceFoundationTest 5 passed / 34 assertions. Cross-store reuse, idempotent remapping, unlink/relink, audit counts and unchanged stock are covered. Store-row locking is implemented, but isolated SQLite tests do not establish concurrent MySQL behavior. Database uniqueness migration and real-MySQL concurrency verification remain outstanding.

Commit/push/deployment wait for explicit phase synchronization approval.
