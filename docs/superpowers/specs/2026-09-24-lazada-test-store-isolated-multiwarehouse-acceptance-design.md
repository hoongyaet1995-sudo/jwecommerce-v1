# Isolated Lazada test-store multiwarehouse acceptance

**Status:** Written specification approved by the merchant on 2026-09-24. This is a limited acceptance path, not the all-store production rebaseline or a new stock mode.

## Purpose and scope

Let the merchant exercise First Count, Inbound Intake, Stock Audit Adjustment and Stock Transfer on the real Inventory & WMS page, with official Lazada test-store stock writes and exact-warehouse readback. The only eligible seller is the connected Lazada account with `account_mode=test` and external shop ID `300163725140`. The seller is an official test account even though Lazada stores its API environment as `live`; `environment=live` alone never authorizes this path. No production seller, Shopee seller, JW website stock or unrelated SKU is included.

Prefer the TOMS product shown by the merchant if a variant meets all gates. Otherwise choose a different mapped test SKU with no legacy physical balance, reservations or open order obligations. Never relabel or clear an existing legacy balance to make a SKU eligible. This acceptance does not activate or alter `inventory_configuration.active_rebaseline_epoch_id`, freeze checkout globally, or run the all-store zero-and-cutover sequence.

## Identity and eligibility

The server, not the browser, resolves the exact store, mapped listing variant and provider facility. Before showing a write action and again inside the reviewed transaction it verifies:

1. The connected seller is the designated official Lazada test account; its current authorization belongs to that seller and test application.
2. A complete, current Lazada warehouse scan is `verified`; each selected `warehouse_code` is active, belongs to this store and is bound to one active JW physical facility. An API failure or incomplete scan remains `verification_pending` and blocks writes.
3. The JW variant has exactly one mapped listing in this seller. No counted legacy warehouse/store balance, legacy order activation, active reservation, nonterminal order obligation or unfinished inventory task can compete for this SKU's physical units. Other stores and SKUs may have legacy accounts and remain untouched.
4. The chosen facility/SKU balance is either uncounted for First Count or counted for subsequent operations. A transfer requires two distinct verified and counted facilities for the same SKU, each bound to a distinct JW physical owner. Its amount cannot exceed source ATP.

Eligibility is evaluated per `store + variant + facility`; a badge or frontend allowlist alone is not an authorization. A failed gate returns a specific reason and no ledger or provider mutation. The test-specific enablement is disabled by default in configuration and requires the exact approved test-store identity; it cannot silently broaden to a production seller.

## Four action flows

- **First Count:** Staff selects one facility and enters its real physical quantity. A reviewed, idempotent confirmation sets that facility's previously unknown balance once. Other facility balances and old accounting rows stay unchanged. The button disappears for the counted facility and is still available for another eligible uncounted facility.
- **Inbound Intake:** On a counted facility, staff enters a positive received quantity, reason and operator. The review shows the before/after physical and ATP values. Confirm adds units once to that facility.
- **Stock Audit Adjustment:** On a counted facility, staff enters the newly observed physical quantity. The review shows the signed difference and refuses a result below active reservations. Confirm replaces that facility's physical number once.
- **Stock Transfer:** Staff chooses distinct source and destination facilities and a positive quantity. Both must be counted. A single transaction debits source and credits destination, checks source ATP and both balance versions, and records one receipt. It does not move reservations. Mixed direct-account and facility routes remain unavailable in this isolated path.

Every confirmed ledger mutation records actor, reason, operation key, before/after quantities, facility IDs and version. A repeated confirm returns its original receipt; a changed balance, mapping, topology or store authorization invalidates a pending review. No provider retry repeats the JW ledger mutation.

## Provider publication and failure

After a ledger confirmation, create one exact-location stock task for each affected Lazada test-store destination: one for count/intake/audit, two for a transfer. Derive desired provider quantity from the facility's current physical minus reserved and safety stock. Require a server-resolved listing and `warehouse_code`; never send a shopwide or aggregate quantity. A successful state requires a readback of the same warehouse code and exact quantity. An accepted API request alone remains pending.

For timeout, expired authorization, rejected warehouse, mismatch or uncertain write, keep the JW ledger receipt and mark provider sync `awaiting_confirmation`, `failed` or `uncertain` with a Check Status action. Resync is allowed only after reconciliation of an uncertain attempt and rechecking the latest balance version. The other seller warehouses and all production sellers remain untouched. The UI states separately what was saved locally and what Lazada confirmed.

## UI and acceptance

Preserve the approved Google AI Studio Inventory & WMS layout. In Manage Stock, show only actions valid for the selected facility: First Count before count; Intake and Audit after count; Transfer when two counted facilities and positive source ATP exist. The First Count form labels the entered value as the selected facility's count, not the master SKU total. Show mapping, warehouse-verification and old-ledger blockers in English with the corresponding recovery link. Remove developer scenarios from the operational page.

Use an isolated SQLite test database to cover eligibility, no/single/multi topology, old-stock and order blockers, each of the four ledger actions, idempotency, version conflicts, provider task targeting, successful exact readback, timeout/uncertain recovery and noninterference with a production store. Build the Admin UI and perform authenticated localhost browser acceptance. On the approved production URL, use only the designated test account and selected test SKU, recording the exact commit SHA, before/after JW balances, Lazada warehouse code and readback receipts. Do not mark the full multiwarehouse production rollout closed from this isolated test. The all-store rebaseline remains a separate pending phase, and Shopee warehouse API whitelist access remains an external dependency.
