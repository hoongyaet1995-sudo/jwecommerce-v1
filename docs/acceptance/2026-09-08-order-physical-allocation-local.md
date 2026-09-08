# Order physical allocation integration checkpoint

Local development only; full Marketplace and WMS goal remains active.

Checkout now reserves stock through StockAllocationService. Physical ownership is recorded when accounting is enabled: shared pool or enabled website/Marketplace store warehouse route. Consumption decreases physical and reserved quantities; release restores availability without decreasing physical stock. Return receiving records the original dispatched allocation and warehouse, rejects quantities beyond dispatched coverage, and runs inside the existing return transaction.

Verification: Return, PhysicalAllocationFlow, StockAllocation and Checkout selection passed 116 tests / 741 assertions. A subsequently added authenticated return API integration test passed 1 test / 8 assertions, proving original balance restoration and repeated receipt rejection. These are automated local tests, not real provider evidence.

Local migrations 2026_09_08_000002 and 000003 applied successfully. Accounting defaults disabled; this checkpoint does not activate inventory ownership or push platform stock.

Next integration gaps: ShipmentService manual dispatch and EasyParcel handoff projection still bypass CheckoutService allocation consumption and must converge on a shared order allocation operation. Activation/reconciliation, routing controls, aggregate availability during transfers, provider broadcasts and full real account acceptance remain outstanding. ZIP modal scrolling changes remain assigned to Google AI Studio.

No commit, push or deployment performed. Continue on current main checkout at http://127.0.0.1:8500/.

## Reservation release after reconciled shortages

PhysicalAllocationService now returns the actual ATP difference for terminal transitions; StockAllocationService uses that difference when an allocation owns a physical balance. A cancellation after a reconciled stock shortage therefore restores only the newly available quantity, not the full reserved quantity. Bound balances with unverified obligations reject completion instead of calculating from unknown values. Unbound legacy allocations retain their existing disabled-accounting behavior.

Regression selection PhysicalAllocationFlow, StockAllocation, InventoryTransferFlow and ReturnRequestOperational: 26 passed, 177 assertions. New shortage case: physical 4 / reserved 5 / safety 2 / ATP 0, release 5 -> ATP 2, repeated release unchanged. Return receiving still needs analogous ATP-difference handling for destination safety/obligation shortages; full closure remains unproven.

## Return ATP follow-through

PhysicalReturnService now returns and records the actual available delta in inventory_return_movements. ReturnRequestService locks the variant before the allocation/balance and applies that delta inside its existing return transaction. Historical movements without a recorded delta cannot be silently replayed. Bound returns require verified obligations; unbound legacy returns retain the accounting-disabled behavior.

ReturnRequestOperational, PhysicalAllocationFlow and InventoryTransferFlow selection: 20 passed / 161 assertions. The authenticated manual-dispatch/return chain is exercised with normal stock and with physical 1 / safety 2 / ATP 0: receiving one unit restores physical to 2 while availability remains zero; a repeat receipt is rejected. This supersedes the preceding return ATP gap, but activation, routing and real provider closure remain outstanding.
