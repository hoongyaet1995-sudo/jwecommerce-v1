# Shared Stock Pool Inventory Contract

Status: approved scope, 2026-09-30. This contract records the user's decisions for phased implementation. It supersedes older automatic-takeover design notes for Shared Stock Pool only. Independent Stock mode retains its current behavior unless a later approved phase changes it.

## Core invariant

Shared Stock Pool has one JW Current Stock ledger per SKU. Marketplace stores do not acquire separate JW stock ledgers. A SKU must complete First Count before its JW stock may be changed by marketplace order webhooks or pushed to providers. The mode-reset workflow clears the prior stock state and requires a new First Count; it is a separate, already delivered phase.

## Store Inventory Management: current phase

The tab is visible only in Shared Stock Pool mode. JW Ecom Storefront is always local and managed. Each OAuth-connected Shopee/Lazada store has its own `inventory_store_accounts.shared_pool_takeover_enabled` flag, keyed by `marketplace_store_id`. The `Takeover` and `Pause Takeover` actions set or clear **only that flag**. They must be idempotent, admin-authorized, and persisted. The UI reads the persisted flag; it must not infer management from a hardcoded value or `management_status`.

Clicking either action must not read or write provider stock, enqueue a push, alter Current Stock, First Count, reservations, mappings, warehouses, historical ledgers, or order state. The old `management_status` is part of the Independent workflow and is not the Shared takeover choice.

For a verified Lazada warehouse store, the approved Store Inventory Management UI shows active warehouses directly below that store. Each warehouse has its own persisted `marketplace_store_facilities.shared_pool_takeover_enabled` choice. Its Takeover/Pause action changes only this flag, never provider or JW stock. Pausing store takeover retains the warehouse choices but makes them ineffective until store takeover resumes. Unverified or inactive warehouses cannot be taken over.

When a JW Current Stock mutation generates provider sync intents, the store id is checked against this flag. Only mapped, connected, takeover-enabled stores may be considered for marketplace push. The worker checks the flag again immediately before publishing so a paused store does not receive a queued future write. For a verified Lazada warehouse store, only active warehouses with their own Shared Pool takeover flag are destinations. The same JW Current Stock total is pushed independently to **each** enabled warehouse; it is not divided among them. A warehouse disabled before its task runs must not receive that queued write. Every destination needs its own delivery and read-back status. API batching, retries, and other port-specific flows remain separate phases.

## Common Shared Pool stock-update boundary

First Count, Actual Inbound Intake, Audit Stock Adjustment, and marketplace order changes received through either webhooks or manual pulls must use the same outgoing stock-sync boundary whenever they change a counted SKU's JW Current Stock. Each operation first records its one authoritative JW ledger mutation, then creates durable sync intents for eligible destinations. The sync boundary applies the mapping, connection, store takeover, and (for verified Lazada warehouse stores) individual active-warehouse takeover checks described above. Pausing a store or warehouse affects outgoing provider stock sync; it does not prevent an eligible marketplace order from deducting or restoring the counted JW stock balance.

Webhook delivery, manual order pull, and subsequent order-status refresh must share idempotent order-line effects: replaying the same event must not change the JW ledger twice or create duplicate stock effects. An uncounted SKU cannot receive a marketplace-order stock mutation or an outgoing provider push. Provider delivery or confirmation failure must retain a visible pending/failed sync state without reversing or duplicating the committed JW ledger mutation. This boundary also governs future stock-update ports.
For the approved Actual Intake and Audit form, the review records the operator's reason and optional reference, and confirmation rechecks the balance version before saving. A changed balance requires a fresh review. Repeating a confirmation with the same operation key returns the same receipt without a second JW movement or provider sync.

## Manage Stock phase and remaining phases

| Phase | Boundary for its own chat or agent |
| --- | --- |
| Manage Stock | The approved compact modal uses one JW Current Stock balance per SKU, a guarded First Count, and a read-only JW movement ledger. Actual Intake and Audit Adjustment open the approved Independent-style form adapted for Shared Pool; Stock Transfer is unavailable. Independent Mode retains its existing modal. |
| Actual Intake | Approved and released at `df409dacc088cdc169d5769d5ac478e952feb2c1`; supervision evidence and the user-approved baseline exceptions are in the Intake/Audit acceptance record. After First Count, enter a positive received quantity. Review and confirm one additive JW Current Stock movement, then use the common takeover-gated sync outbox; no store-specific ledger mutation. |
| Audit Count Adjustment | Approved and released at `df409dacc088cdc169d5769d5ac478e952feb2c1`; supervision evidence and the user-approved baseline exceptions are in the Intake/Audit acceptance record. After First Count, enter the verified absolute SKU total, including zero. The server computes the delta from the locked JW balance; an unchanged total creates no movement. Review and confirm through the same outbox as intake. |
| Stock Transfer | Do not expose this operation in Shared Stock Pool mode. Preserve Independent transfers. |

Order-webhook deductions/increases remain blocked for an uncounted SKU after mode reset. Each future phase must prove this gate, the shared-ledger invariant, per-store takeover gating, and no regression in Independent mode before claiming completion.

## Cross-chat development protocol

Released Intake/Audit evidence: `docs/acceptance/2026-09-30-shared-pool-intake-audit-supervision.md`. Both phases were approved together, repaired, verified and released at `df409dacc088cdc169d5769d5ac478e952feb2c1`, with explicitly approved pre-existing test/type exceptions. Shared Stock UI acceptance and release evidence: `docs/acceptance/2026-09-30-shared-stock-ui-local.md`.

Each chat or agent starts by reading `AGENTS.md` and this contract, then works on exactly one phase from the table. It should record its concrete API/data-flow plan and acceptance evidence before editing. Changes to these invariants require an explicit user decision and an update to this contract; do not silently reinterpret them from an older spec or a context summary. Keep phases separate for review, tests, and local browser acceptance. Follow the repo's delivery order: localhost first, then user-approved GitHub `main` sync and deployment of that exact SHA to Hostinger.

For handoff, report the branch/SHA, files changed, migrations, tests, browser evidence, unfinished provider assumptions, and any contract decision that needs user approval. A subsequent chat should use this file as the stable source of scope rather than relying on a previous chat's remaining context window.

## Shared Stock inventory overview UI: released phase

The user selected the first visual direction and approved implantation in the existing localhost Admin inventory page. On 2026-10-01 the user explicitly approved synchronizing this accepted phase to GitHub main and deploying it to Hostinger. The development baseline is `df409dacc088cdc169d5769d5ac478e952feb2c1`. The accepted application was released as `7be2a57e797ad8904cbaeac3b1cd71d0fd60168d`; verified SHA/HTTPS/bundle results are in the phase acceptance record.

Show one Shared Stock total per SKU. Unknown stock displays `Awaiting first count`, with `First Count` opening the existing unified Manage Stock flow. A counted SKU, including zero, uses `Manage Stock`. Mapped marketplace cards are read-only; clicking the store name must not open the old stock operation modal. Warehouse details support hover, keyboard focus and a small expansion control. Each warehouse shows its own saved readback, takeover/sync state and the same shared target; never sum warehouse observations or substitute an Independent ledger/store-wide snapshot. Missing or mismatched receipts remain `Unknown`. Distinguish a verified store-level topology from undiscovered/failed warehouse discovery. Preserve real error information. JW Storefront displays the local `Shared ledger` / `JW Ledger` semantics.

Use the existing charcoal/purple theme, components, icons, true product images and English admin copy. No First Count mutation/outbound, takeover, backend inventory contract or Independent behavior changes are included. The existing topology GET can perform Shopee discovery; isolated UI verification intercepts it as well as all stock APIs. Real provider stock testing was separately permitted by the user but is not required or claimed for this UI phase. This exact accepted UI phase is approved for synchronization and deployment; further feature or invariant changes still require a user decision. Release completion and verification are recorded in the phase acceptance record.
