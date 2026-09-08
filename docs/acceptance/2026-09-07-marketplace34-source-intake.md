# ZIP34 Inventory source intake — 2026-09-07

Archive SHA256: D378C168025B512AC10EFF36FB821C2E0BD47843DDE5E3E74F7D437AD395A347.
Private source: C:/Users/User/Downloads/jw-ecommerce-dashboard-34-source.
New selected inventory source supersedes ZIP33 for subsequent conversion only; existing formal routes and ZIP33 local read preview are unchanged. User screenshots establish new multiwarehouse presentation, not backend acceptance.

## Confirmed source findings

- InventoryAdjustmentModal reads currentWarehouseBalance.reserved, but WarehouseStockBalance defines reservedStock. This explains the screenshot Projected ATP: NaN. Negative physical validation alone does not reject NaN. Unknown audit still defaults to 50; actor remains client-selectable.
- ModeSwitchModal offers primary_all/split_even and immediately calls onConfirmSwitch then closes. AdminContext setInventoryModeWithAudit's even-split branch initializes per-warehouse reserved quantities to zero, then aggregates them. No order-level reservation allocation review is performed there. Mode-change logs in client state/localStorage are not immutable server audit records.
- InterWarehouseTransfer defines only draft/in_transit/completed/cancelled. Partial receipt, actual received quantity and discrepancy workflow are missing from this contract. updateTransferStatus deducts source physical quantity and credits destination on completion; source physical is not reduced at dispatch in that shown handler. Cancellation releases reserved quantities. This does not establish the requested in-transit custody accounting, state-transition validation or idempotent settlement.
- Current context uses Math.max clamps and writes localStorage. These handlers must not be transplanted into operational Laravel mutations.

## Integration disposition

Reuse page structures, expanded warehouse rows, warehouse forms and routing visuals where applicable. Repair calculation bindings without redesign. Bind authenticated actor server-side. Keep platform locations distinct from facility labels. Implement server-owned pools, warehouse balances, reservations, transfers and reviewed mode conversion before enabling writes. Do not silently simplify away partial receipt/discrepancy or reservation conversion requirements.

No archive code was run in this checkpoint. No official page was replaced, stock changed or production deployed. Evidence is user screenshot plus current extracted source inspection, not real provider acceptance.

Local isolated InventoryAdjustmentModal copied to marketplace34 preview components. Regression against the unmodified component reproduced NaN; corrected reservedStock field and canonical binLocation binding. Numeric safety rejects missing/noninteger/negative operands before review or confirmation and renders Not verified for invalid ATP. One isolated render regression passed. No route mounts this component yet; unknown audit default50 and actor binding remain separate unfinished work. Original archive/extraction unchanged, JSX structure/classes preserved, no real stock mutation.
