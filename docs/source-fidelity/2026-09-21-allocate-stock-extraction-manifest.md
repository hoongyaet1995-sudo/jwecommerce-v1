# Allocate Stock source-fidelity manifest

- Source package: `C:/Users/User/Downloads/jw-ecommerce-dashboard (41).zip`
- SHA-256: `83224F7AD3DEEDFC8E45458DE79FD8E6FE27C2EB1DBC9DAAC690ACB8714380A4`
- Selected source component: `src/components/marketplace/inventory/InitialStockCountModal.tsx`
- Integrated route: `/admin/marketplace/inventory`
- Runtime component: `resources/admin/src/previews/inventory39/components/marketplace/inventory/IndependentStockAllocationModal.tsx`

## Preserved surface

The Independent Stock version preserves the supplied three-step Count & Allocate, Review, and Success flow, neutral dark visual hierarchy, conservation summary, store allocation rows, unmapped-store state, and separation between the JW ledger result and platform broadcast status.

## Deliberately excluded source-only controls

The `Demo Scenarios` switcher and its simulated balanced, unmapped, mismatch, reserved-conflict, and timeout fixtures are presentation aids from the source export. They are not included in the application runtime. Each equivalent state is driven by Laravel/MySQL data and provider sync-task status.

## Laravel adapters

- Inventory overview supplies real SKU, store, mapping, balance, reservation, and connection state.
- Allocation review validates the complete eligible account set and strict quantity conservation.
- Allocation confirmation commits all store balances atomically and records an audit receipt.
- Marketplace ATP broadcasts are queued after the ledger commit; retry uses the existing sync-task endpoint and never reapplies the ledger allocation.
