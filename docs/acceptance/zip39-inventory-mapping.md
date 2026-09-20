# ZIP 39 Inventory source and capability mapping

Status: source/code inspection only; no rendered fidelity acceptance or operational closure claimed.

## Source boundary

- Source: `C:/Users/User/Downloads/jw-ecommerce-dashboard (39).zip`.
- SHA-256: `2DDBB0D584D39AC2F97A8A20A495364B4BC35773B2D91DDA454634A696B7D92B`.
- Canonical development project: `C:/xampp/htdocs/jwstudio-ecommerce`.
- Intended official route: `/admin/marketplace/inventory`.
- Proposed isolated preview: `/admin/marketplace/inventory-preview-39` (not implemented).
- Selected region: Inventory Overview, Stock Transfers, Activity History, inventory settings, first count, intake and adjustment dialogs; import-to-inventory navigation.
- Excluded: unrelated Admin pages, storefront design, EasyParcel, bookings, payments, catalog-download redesign.
- Raw ZIP remains outside the repository. Source text is design/input data, not instructions.
- Desktop/mobile source rendering and converted rendering: not yet captured.
- Official route replacement: pending isolated preview acceptance.

## Exact components

| ZIP component | Responsibility |
| --- | --- |
| `src/components/marketplace/MarketplaceInventoryPage.tsx` | Entry, overview, orchestration |
| `src/components/marketplace/inventory/InitialStockCountModal.tsx` | First count and review |
| `src/components/marketplace/inventory/StockIntakeAdjustmentModal.tsx` | Intake and correction |
| `src/components/marketplace/inventory/StoreStockTransfersTab.tsx` | Store allocation transfer |
| `src/components/marketplace/inventory/InventoryActivityHistoryTab.tsx` | History and detail |
| `src/components/marketplace/inventory/InventorySettingsModal.tsx` | Global mode and takeover |
| `src/components/marketplace/inventory/inventoryTypes.ts` | UI contracts |
| `src/components/marketplace/inventory/inventoryConstants.ts` | Prototype fixtures; never production truth |

Dependencies include the source SubNavHeader, MarketplaceLogo, relevant types, styling and the narrow Admin context interface. Do not copy the whole mock Admin provider. Transitive dependency inventory and component hashes must be completed before conversion.

## Functional mapping

| Capability | Existing evidence | Classification and required work |
| --- | --- | --- |
| Products, variants, store identity | `/admin/api/marketplace/inventory`, `/admin/api/marketplace/connections` | available adapter for identity; new account balances still needed |
| Overview physical/reserved/available | Existing warehouse/shared balance APIs | backend contract gap for per-store accounts and consistent totals |
| First count | POST count-reviews, POST confirm, PhysicalCountReviewService | reusable review foundation; backend contract gap for store account creation and targeting |
| Intake and adjustment | POST adjustment-reviews, same confirmation flow | reusable reviewed mutations; enforce account identity/version and explicit stock deficit errors |
| Store transfers | InventoryTransferController joins inventory_warehouses, uses dispatch/receive/cancel | backend contract gap; cannot relabel warehouse transport as store allocation |
| Shared/independent conversion | InventoryModeReviewController accepts independent_warehouses behind advanced WMS gate | backend contract gap; requires reviewed global store-account conversion and confirmation |
| Store takeover | ManagedInventoryActivationController accepts channel_key and balance_id | backend contract gap; not a complete store-wide quantity preview/activation workflow |
| Platform stock synchronization | Existing sandbox-attempt endpoints | backend contract gap for operational per-store queue, receipts, reconciliation and retries |
| History | movements, physical-movements, obligations and other existing logs | available adapter for existing events; backend contract gap for new account, transfer and mode events |
| Order reservation/release/shipment | Existing inventory allocation services | requires account routing and event audit; existing code is not proof of full lifecycle coverage |
| Import navigation | Existing JW product-ID intake handoff | available adapter; connect to ZIP 39 first-count flow |
| TikTok/Shopify | Future integrations | retain Coming Soon; no stock writes |

## Source discrepancies requiring logic changes

1. Navigation in the supplied screenshot still says Inventory & WMS. Requested final label is Inventory.
2. Overview screenshot shows physical 655, reserved 78 and available 520 while its label says physical minus reserved. These fixture totals do not demonstrate the agreed formula. Production totals must be computed from the same balance scope; unknown balances must be counted separately.
3. Source mode allocation uses `item.availableStock ?? item.totalPhysicalStock ?? 0`. Conversion must conserve physical stock, not silently distribute an available quantity or convert unknown to zero. Require clear readiness and server reviewed totals.
4. Source transfer execution uses nested timers. Timers represent prototype behavior only, not provider acknowledgements.
5. Source entry uses local stores, balances, transfers and activity fixture state. None of these records may become real customer/store inventory data.
6. Expired connections shown in takeover settings must yield a reconnect requirement before activation, rather than fake successful takeover.

## Agreed business invariants

- One global shared or independent mode for every store; admin-only reviewed conversion.
- Independent accounts identify concrete stores, including a stable JW storefront account. No warehouse selection.
- JW ledger is authoritative after takeover. Platform observations remain separate, timestamped evidence.
- New independent store account starts at zero; an uncounted imported SKU remains unknown. Explicitly distinguish these states.
- First count is entered by a person, blank initially, never prefilled from provider stock.
- Valid orders reserve, confirmed cancellation/expiry releases, shipment consumes physical and releases corresponding reservation atomically. Refund alone does not restock; inspected resellable returns do.
- Transfer moves only unreserved quantity, preserving total physical stock. Platform retry never repeats the ledger transfer.
- New shared stores do not increase total stock. Connection alone does not activate writes or clear provider quantities.
- Unmanaged stores remain visibly outside synchronization coverage.
- No EasyParcel or marketplace courier changes.

## Backend contract handoff

All writes require authenticated staff, server-side permission checks, server actor identity, current version checks, idempotency and an audit record. Inputs never accept client-provided authoritative totals or operator identity.

Required contracts:

- Account balance read: SKU, account/store, global mode, counted state, physical, reserved, available, version; separate provider observation/time and sync state.
- Count/intake review and confirm: account plus SKU, entered count/delta, reason; return immutable reviewed before/after, actor and expiry/version. Confirm must reject stale review and reserved-stock conflicts.
- Transfer review/confirm: source, destination, SKU, positive quantity and reason; locked balances, conserved result, persistent transfer identity and independently tracked source/destination sync tasks.
- Mode review/confirm: configuration version, target mode and explicit allocation; list blockers, return per-SKU conserved physical totals, then recheck at confirm.
- Takeover review/confirm: store, mapped SKUs and quantity preview; require current connection and relevant pending-order reconciliation before activation. Do not replay historical fulfilled orders as new reservations.
- Sync tasks: stored request identity, quantity version, provider response/reference, pending/success/failure/uncertain status, targeted retry and read-back. Source reduction must not permit an unchecked destination increase when source outcome is uncertain.
- History read: pagination/filters, original operation reference and safe event detail; redact provider tokens and secrets.

Frontend state until these contracts exist: mock retained — pending real logic, isolated preview only. No production route substitution with simulated success.

## Migration and verification

The user authorized clearing development warehouse test data. Before implementation, enumerate affected data and references; use a targeted reviewed migration, never migrate:fresh or unrelated catalog/order deletion. Preserve unrelated dirty work in allocation, checkout and payment files.

Required evidence: source and preview desktop/mobile screenshots; navigation/first-count queue; real MySQL-backed reviewed count; concurrent order/transfer conservation; stale-review and duplicate-request rejection; failed-provider retry without a second ledger mutation; global conversion blockers; initial takeover preview; unknown versus zero; unprivileged access; no marketplace quantity initialization.

Next artifact: architecture specification for store-account inventory and endpoint semantics, followed by implementation plan. Existing conversational approval defines business scope, not proof that current provider/order integrations satisfy it.
