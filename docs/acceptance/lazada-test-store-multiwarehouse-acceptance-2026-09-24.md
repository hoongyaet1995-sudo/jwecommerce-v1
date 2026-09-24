# Lazada test-store isolated multiwarehouse acceptance

Status: Lazada test-store four-action acceptance completed on 2026-09-24 with synthetic quantities. General multiwarehouse rollout remains gated per seller and provider.

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
- Old stock sync regression run before multiwarehouse acceptance: 86 passed, 449 assertions. Facility-focused suite: 190 passed, 1 skipped, 897 assertions.
- No local marketplace stock write was made. The live test seller authorization was renewed through the existing test access flow and its eight warehouses were scanned and bound to distinct JW physical facilities.

## Release and live evidence

1. Application release `ee436b0f0321c89513b78553072506feade816a3` was verified on the canonical local checkout, GitHub `main` and Hostinger. Production migrations and authenticated Inventory page load passed.
2. Only `MARKETPLACE_MULTIWAREHOUSE_TEST_ACCEPTANCE_ENABLED` was enabled in production. The existing global stock sync canary remained unchanged (`false`); changing it to a test-only allowlist would have interrupted the established store sync path. The server-side test acceptance policy separately restricts the new facility route to the designated Lazada test seller, eligible SKU and exact facility.
3. Lazada test SKU `1000469489` (JW variant 121) passed live eligibility. Synthetic First Count set `MY4NA1T7CK-WH-10003` to 3 and `MY4NA1T7CK-WH-10004` to 2; both exact-location provider writes were confirmed by readback.
4. Intake added 2 to warehouse `…10003` (3 → 5), and Audit Adjustment set it to 4 (5 → 4). Both writes were confirmed by exact-location Lazada readback.
5. Transfer moved 1 from `…10003` to `…10004` atomically in JW (4/2 → 3/3). The source push confirmed immediately. The destination push initially failed without changing the observed provider stock (2); the existing Check Status → Resync Stock recovery created one new task, which confirmed provider stock 3 at the exact destination. The two final provider quantities and two JW facility balances are 3/3.
6. The authenticated live Inventory page showed SKU total 6, both counted facilities at 3, the selected counted facility with Intake/Audit/Transfer and `synced` status, and an uncounted test facility with First Count only. No production seller facility stock was changed by this acceptance.

## Remaining general-rollout limits

- Shopee warehouse discovery remains pending the provider whitelist/permission response. A failed location read stays verification-pending; it is never treated as no-warehouse.
- Legacy stocked or order-obligated SKUs remain blocked from this isolated test route until the approved inventory rebaseline and obligation reconciliation are completed.
- Synthetic counts are development data, not a physical stock attestation. Before a real operational launch, count each active facility with real stock and reconcile provider stock/order obligations.
