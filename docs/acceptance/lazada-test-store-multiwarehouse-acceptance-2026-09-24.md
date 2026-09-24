# Lazada test-store isolated multiwarehouse acceptance

Status: implementation and local automated verification in progress; live four-action acceptance remains open.

## Boundary

- Designated seller: Lazada test account, external Shop ID `300163725140`.
- Eligible SKU: any one-to-one mapped SKU in that seller with no counted legacy owner, active stock obligation, matching open order, or alternate store mapping. Selection is dynamic; no SKU is hard-coded.
- Facility: complete verified Lazada warehouse scan, active registered provider code and distinct active JW physical owner.
- Development-stage First Count may use an explicit synthetic test quantity; no physical goods are required. Provider stock is a reference, never silently copied into the count input.
- Production sellers, Shopee stores, global rebaseline and old balances are outside this acceptance branch.

## Local evidence

- The acceptance policy, First Count, Intake, Audit, Transfer, sync worker and recovery use server-side eligibility. A pending or confirmed stock task cannot be resent directly.
- Focused and related feature suite: 190 passed, 1 skipped, 897 assertions on isolated SQLite. Admin main build and Inventory48 preview build succeeded; Inventory48 action-state UI tests: 4 passed.
- Read-only local audit found test store ID 3 with 8 mapped variants and 76 nonterminal orders. The policy resolves open orders per mapped listing, so unrelated orders do not block every candidate. Local candidates without legacy balance or matching open orders include `JW-SBX-LZ-MOUSE-001`, `14980668266-1788252266835-2`, `SST-LOW`, `JW-QA-LZ-ZERO-WH-0913` and `JW-LZ-DUAL-APP-20260919`; current mapping, physical stock and order state must be rechecked at live acceptance time.
- Local warehouse refresh returned `authorization_expired`: zero verified facilities. No local stock mutation or provider write was made. The browser-visible production page still needs the new release and a fresh production-side warehouse scan.

## Release and live gates still open

1. Commit only coherent marketplace/facility/UI dependencies from the dirty canonical checkout; verify a clean release commit and merge/push to GitHub `main`.
2. Deploy the exact SHA to `/apps/jwecommerce`, run migrations and confirm live SHA and authenticated Inventory page rendering.
3. Refresh the production test-store authorization and complete eight-warehouse discovery; bind each provider facility to a distinct JW physical warehouse. Do not infer no-warehouse from a failed scan.
4. Enable only the scoped test-acceptance, discovery/binding and stock-sync switches. Keep the stock-sync canary enforced and allowlist only the selected test listing identity.
5. Select any currently eligible mapped test SKU and enter explicit synthetic First Count quantities for two chosen warehouses. Record four review receipts, JW before/after balances, exact warehouse-code readback and provider-observed quantity.
6. Confirm production sellers and their stock tasks remain unchanged. Update the master checklist only after live evidence exists.
