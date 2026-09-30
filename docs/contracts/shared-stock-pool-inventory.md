# Shared Stock Pool Inventory Contract

Status: approved scope, 2026-09-30. This contract records the user's decisions for phased implementation. It supersedes older automatic-takeover design notes for Shared Stock Pool only. Independent Stock mode retains its current behavior unless a later approved phase changes it.

## Core invariant

Shared Stock Pool has one JW Current Stock ledger per SKU. Marketplace stores do not acquire separate JW stock ledgers. A SKU must complete First Count before its JW stock may be changed by marketplace order webhooks or pushed to providers. The mode-reset workflow clears the prior stock state and requires a new First Count; it is a separate, already delivered phase.

## Store Inventory Management: current phase

The tab is visible only in Shared Stock Pool mode. JW Ecom Storefront is always local and managed. Each OAuth-connected Shopee/Lazada store has its own `inventory_store_accounts.shared_pool_takeover_enabled` flag, keyed by `marketplace_store_id`. The `Takeover` and `Pause Takeover` actions set or clear **only that flag**. They must be idempotent, admin-authorized, and persisted. The UI reads the persisted flag; it must not infer management from a hardcoded value or `management_status`.

Clicking either action must not read or write provider stock, enqueue a push, alter Current Stock, First Count, reservations, mappings, warehouses, historical ledgers, or order state. The old `management_status` is part of the Independent workflow and is not the Shared takeover choice.

When a later JW Current Stock mutation generates provider sync intents, the store id is checked against this flag. Only mapped, connected, takeover-enabled stores may be considered for marketplace push. The worker checks the flag again immediately before publishing so a paused store does not receive a queued future write. Provider warehouse selection, API batching, delivery confirmation, retries, and exact stock-update triggers belong to the later port-specific phases; this flag alone does not claim any of them work.

## Manage Stock phase and remaining phases

| Phase | Boundary for its own chat or agent |
| --- | --- |
| Manage Stock | The approved compact modal uses one JW Current Stock balance per SKU, a guarded First Count, and a read-only JW movement ledger. Actual Intake and Audit Adjustment are visible as later-phase placeholders; Stock Transfer is unavailable. Independent Mode retains its existing modal. |
| Actual Intake | Define Shared Pool receiving and First Count semantics separately; no store-specific ledger mutation. |
| Audit Count Adjustment | Define one Shared Pool correction and later per-store push intents, without reusing Independent per-location accounting. |
| Stock Transfer | Do not expose this operation in Shared Stock Pool mode. Preserve Independent transfers. |

Order-webhook deductions/increases remain blocked for an uncounted SKU after mode reset. Each future phase must prove this gate, the shared-ledger invariant, per-store takeover gating, and no regression in Independent mode before claiming completion.

## Cross-chat development protocol

Each chat or agent starts by reading `AGENTS.md` and this contract, then works on exactly one phase from the table. It should record its concrete API/data-flow plan and acceptance evidence before editing. Changes to these invariants require an explicit user decision and an update to this contract; do not silently reinterpret them from an older spec or a context summary. Keep phases separate for review, tests, and local browser acceptance. Follow the repo's delivery order: localhost first, then user-approved GitHub `main` sync and deployment of that exact SHA to Hostinger.

For handoff, report the branch/SHA, files changed, migrations, tests, browser evidence, unfinished provider assumptions, and any contract decision that needs user approval. A subsequent chat should use this file as the stable source of scope rather than relying on a previous chat's remaining context window.
