# Marketplace MySQL constraint acceptance — 2026-09-06

## Evidence

Command: `C:/xampp/php/php.exe tests/manual/marketplace-mysql-constraints.php`

Result: **3 tests passed, 29 assertions**, exit code 0. The runner explicitly verified that the mapping and order tables existed in its isolated MySQL schema, excluding an accidental SQLite-only run.

- Same-store reuse of a JW variant is rejected; cross-store reuse is allowed without changing stock.
- The database itself rejects a second mapping for the same store and JW variant.
- Duplicate and older order snapshots do not duplicate or regress the order.

The disposable schema was removed after execution. The application schema and its records were unchanged. No marketplace API calls were made by this test.

## Boundaries

These mapping checks exercise database constraints and sequential API operations; they do not prove concurrent mapping requests. The separate order race runner covers concurrent order snapshots, not mapping races.

## Additional mapping concurrency acceptance

Command: `C:/xampp/php/php.exe tests/manual/marketplace-mapping-mysql-race.php`

The dedicated runner starts two independent PHP processes calling the actual `MarketplaceFoundationService::map` against different listings in the same store and the same JW variant. Both workers are released behind a held store-row lock in a disposable MySQL database.

Verified outcomes: exactly one successful mapping and one HTTP 409 service exception; exactly one mapping and one audit event. Mapping that same JW variant in a different store then succeeds, with two total mappings and two audit events. Stock remains 7. This is service-level concurrency evidence, not browser-concurrency or real-platform mapping evidence.

Only table structures are copied from the local application database. Test rows are synthetic; the runner removes its validated random schema after completion. No platform requests or application data mutations occur.

This is local acceptance only. It does not establish production catalog/order ingestion, official UI cutover, fulfillment, or inventory synchronization. The next read-only release still requires phase synchronization approval; publishing and inventory writes remain disabled.
