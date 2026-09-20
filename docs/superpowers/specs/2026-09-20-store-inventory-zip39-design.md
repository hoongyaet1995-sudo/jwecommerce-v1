# Store-based Inventory — ZIP 39 implementation specification

Status: proposed written specification for user review. Business scope and source mapping approved in conversation; implementation and operational acceptance not yet complete.

## Purpose and boundary

Replace the warehouse-oriented employee experience with Inventory Overview, Stock Transfers and Activity History, plus administrator settings. Use ZIP 39 as the visual authority; see `docs/acceptance/zip39-inventory-mapping.md` for archive identity, selected components and observed gaps. No employee warehouse creation, routing, location or freight-transfer concepts remain in this surface.

One global mode applies to JW Storefront and every connected store: shared or independent. Concrete stores, not platforms, own independent accounts. TikTok and Shopify remain Coming Soon. EasyParcel, payment behavior and storefront presentation are outside scope; storefront stock availability and order inventory consumption are in scope.

## Architecture decision

Use the existing transactional inventory foundation with explicit store-account ownership. Extend inventory pools with a store kind and stable account key (`website`, `marketplace:<internal store id>`); retain the single shared pool. One balance per pool and JW variant is enforced by database uniqueness. Warehouse pools remain legacy history and cannot receive new operations after cutover.

Alternatives rejected: hiding warehouse controls preserves incorrect routing semantics; creating an unrelated parallel ledger introduces two sources of stock truth. Reuse review, allocation and movement primitives after adapting ownership, rather than reusing warehouse dispatch/receive transitions.

Configuration contains global mode, version and transition state. Store management contains store identity, takeover state and an order-ingestion boundary. Order reservations retain their originating store even in shared mode. Provider observations and sync task results are separate from ledger balances.

## Quantity semantics

- Physical is counted, ledger-controlled stock; reserved is outstanding owned order quantity; available is physical minus reserved.
- Unknown physical remains null/Not Counted. Never coerce it to zero in summaries or distribution.
- New imported SKU accounts require first count. A newly joined independent store receives an explicitly initialized zero allocation for already tracked SKUs, with an initialization event. This does not clear remote stock.
- In independent mode, physical represents the stock allocated to that store; employees must not count the same goods in multiple accounts.
- Existing safety buffers are not silently retained as a hidden subtraction. Migration readiness blocks nonzero legacy buffers until they are explicitly reconciled. The new displayed formula contains no hidden buffer.
- First count and correction below reserved quantity are rejected with the related reservation references and a corrective action. Real deficits remain visible as exceptions; do not cancel customer obligations to make totals appear valid.
- Summary totals use one consistent filtered balance scope. Shared quantities count once. Report unknown account/SKU counts separately from known subtotals.

## Reviewed mutations

Each review records actor, reason, operation type, normalized input, current configuration/balance versions, before/after quantities, and a ten-minute expiry. Confirmation checks authorization, actor, expiry and versions again inside a transaction. An idempotency key is unique per actor and operation; repeats with identical input return the saved receipt, different input conflicts.

First count uses an absolute quantity; intake uses a positive increment; correction uses an absolute recount. All quantities are whole units within database limits. The authenticated server supplies the employee identity. No provider snapshot initializes or pre-fills a count.

Import-to-inventory retains the existing JW product-ID handoff. Filter matching SKUs, open first-count flow after data/configuration readiness, then advance only on a confirmed receipt. Independent mode requires account selection. Closing stops the automatic queue while keeping the product filter; manual count actions remain available. All-existing links navigate to their linked products where identities are known; no false claim that only new products can need counting.

## Order lifecycle and ownership

Persist and deduplicate provider events before applying stock effects. JW orders reserve on valid order creation, including unpaid orders; marketplace orders reserve on recognized effective creation state. Cancellation or confirmed expiry releases remaining reservation. Shipment atomically consumes physical and settles the matching reservation. Refund alone does nothing to stock. Actual received, inspected and resellable returns create a reviewed restock event bounded by shipped and previously returned quantities.

Support partial cancellation, shipment and return per order line. Deduplicate by source/store/order/line/event identity and cumulative processed quantities. Out-of-order terminal events cannot revive a cancelled or shipped reservation. Do not release marketplace stock merely because a local timer elapsed; establish provider cancellation/expiry. Unmapped/insufficient-stock events create actionable exceptions, not silent inventory changes. JW checkout rejects insufficient availability before accepting its order.

Keep one authoritative reservation path per activated account/SKU. Existing legacy product inventory must not be independently decremented in addition to the new balance. Adapt existing allocation entry points and derive JW storefront availability from the active account. Review the existing uncommitted allocation/checkout work before edits; preserve unrelated changes.

## Stock transfers

Independent mode only. Review selects one SKU, distinct source/destination store accounts, quantity and reason. Lock accounts in a stable order, require known balances and sufficient unreserved source quantity, then subtract/add once in the same transaction. Total physical and total reserved remain unchanged.

Write the transfer receipt, audit event and platform update outbox atomically. Expose ledger result, source update and destination update separately. JW storefront updates locally and has no external API step. Unmanaged stores show external update as not applicable rather than success.

For managed remote sources, confirm source reduction before releasing destination increase; unresolved or uncertain source outcomes keep destination increase waiting. Recompute current target quantities when dispatching rather than sending obsolete reviewed stock. Persist dependency/retry state across worker restarts. Retry an external stage never replays the transfer. Do not automatically reverse the ledger after a platform failure because subsequent orders may have consumed stock.

## Provider synchronization

Stock mutations generate durable outbox work after the database transaction. Serialize writes by concrete store/listing; supersede obsolete unsent quantities and enforce monotonic ledger versions. Provider adapter validates SKU mapping, credentials, capabilities and quantity limits. Persist provider request reference, sanitized response and read-back time.

States include queued, running, succeeded, failed, auth-expired, uncertain, superseded and not-managed. Timeouts with ambiguous outcomes require read-back before unsafe retries or dependent increases. No timer or successful HTTP transport alone proves accepted provider stock. Every managed platform in the release requires actual test-store evidence; unsupported providers are disabled with a reason.

Shared stock is coordinated asynchronously across marketplaces. A shared ledger does not create atomic cross-platform checkout or guarantee elimination of overselling during network latency. Record excess remote orders as shortages requiring action; show incomplete synchronization honestly. No unapproved per-platform quota model is introduced.

## Store takeover and pause

Connection alone creates no takeover or writes. Administrator review loads actual store identity, SKU mappings, JW counted balances, provider observations and current unresolved orders. Block expired connections, ambiguous mappings, unknown balances and unresolved historical commitments. Preview remote before/after values, including deliberate zero updates.

Reconcile open orders through a captured ingestion boundary and process later events idempotently before activation; do not reserve historical fulfilled orders. Recheck pending events and balance versions at confirm. Activate ownership and enqueue initial quantities transactionally. Distinguish pending initial synchronization from fully synchronized. All unmapped store listings remain explicit blockers for store-wide takeover.

Pause requires an administrator review. Never discard existing reservations or reconciliation duties. A pause stops new automatic stock publishing; existing managed order obligations continue to completion. New observed orders must remain accounted for while the store can still sell. Label paused publishing/unmanaged coverage honestly rather than claiming complete channel consistency.

## Global mode conversion

Administrator only. Review blocks outstanding owned reservations/open commitments, unresolved orders, unfinished transfers, running/uncertain stock writes, unknown active SKU balances and unresolved discrepancies. Provide actionable SKU/store references. Pending ingest events also invalidate readiness.

Shared to independent distributes each SKU's physical total explicitly across concrete store accounts; confirmation requires full allocation and exact conservation. Independent to shared sums JW account physical totals after an explicit duplicate-count acknowledgement, never provider quantities.

Confirm locks configuration, validates the same ledger versions and readiness, then converts accounts atomically and increments the configuration version. Other writers must honor this transition lock. Queue new platform quantities and keep remote conversion synchronization status visible. New order events are durably retained during the transaction and routed under the resulting mode, never dropped. Switching mode is not complete remotely while provider updates remain pending.

## Proposed API surface

New store-oriented endpoints use `/admin/api/inventory/store-management` to avoid confusing legacy warehouse consumers.

| Endpoint | Contract |
| --- | --- |
| GET overview | Paginated products/accounts, mode/version, consistent totals, unknown counts, observations and sync status |
| GET stores | Real JW/marketplace identities, connection, takeover and capability state |
| POST reviews | Type=count/intake/correction/transfer/mode/takeover/pause; authoritative reviewed diff and blockers |
| POST reviews/{id}/confirm | Acknowledgement and idempotency key; saved mutation receipt or stale/conflict response |
| GET operations/{id} | Ledger receipt and separate external stage status |
| POST sync | Eligible store/SKU scopes and idempotency key; queued task identities, never simulated success |
| POST sync/{id}/retry | Retry/reconcile eligible external task without ledger repetition |
| GET history | Paginated event filters and redacted details |

Use existing auth/permission conventions: explicit inventory view/edit permissions, with administrator-only mode/takeover/pause. Match UI capability flags to server enforcement. Responses use stable reason codes, field/SKU/store identity and next-action targets: 403 forbidden, 409 stale/conflict, 422 invalid input; dependency failure never masquerades as validation success. JSON event details exclude tokens, credentials and unnecessary personal data.

## Source fidelity and integration

Preserve ZIP 39 selected DOM/classes/assets/responsive composition. Adapt value/event boundaries only. Correct requested Inventory label and replace fixture-derived arithmetic, local mutations and timer-driven receipts with actual contracts. Preserve source prototype behavior only on clearly isolated mock previews. Capture source desktop/mobile render and interactions before conversion.

An isolated authenticated preview precedes official route replacement. Render readiness, permission and failure states using existing source components. Unavailable capabilities remain explicitly unavailable. Source mismatch or missing recovery surface is reported before inventing visual structures.

## Development data migration and release

Inventory reset permission applies only to identified development warehouse test records. Before reset, enumerate IDs, relationships, obligations and affected balances, save a restorable snapshot and refuse any ambiguous business-order references. Never delete catalog products, provider credentials, marketplace connections or unrelated orders. No migrate:fresh. Deactivate old warehouse writers at cutover; retaining read-only historical records is preferable to broad table destruction.

Develop in the canonical XAMPP checkout. Keep raw ZIP private. Do not commit unrelated dirty work. GitHub/Hostinger synchronization and official route cutover follow explicit phase approval and local validation; previous release approval does not automatically authorize this new release.

## Acceptance gates

1. Source/preview desktop and mobile visual evidence for all selected pages/dialogs.
2. Import handoff, first count, input/review/confirm, queue advance and cancellation work with real persisted balances.
3. Zero versus unknown and totals pass with mixed accounts; no provider initialization.
4. Concurrent reservations/transfers, partial order events, duplicates, stale reviews and rollback conserve stock in isolated MySQL testing.
5. Failed/uncertain provider updates and worker restart recover without duplicate ledger effects; stale tasks cannot overwrite newer quantities.
6. Mode conversion conserves physical stock and rejects blockers or concurrent changes.
7. Takeover previews actual quantities, reconciles open orders and enforces admin permissions.
8. JW storefront availability and real Shopee/Lazada test-store write/read-back evidence are verified separately. No claim of cross-platform atomicity.
9. No EasyParcel booking, payment or courier changes, and no unrelated workspace edits included in release.
10. Update applicable existing master acceptance IDs with producer/service/MySQL/consumer/failure/test/browser evidence; no closure based only on code or prototype screenshots.

Self-review: no unresolved design placeholders; unknown/zero and physical/available semantics separated; pause preserves obligations; source transfers cannot be mistaken for warehouse transport. Actual provider behavior and visual parity remain evidence gates, not assumed capabilities.
