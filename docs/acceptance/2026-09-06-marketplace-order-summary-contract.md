# OMS server summary contract

- Added protected, read-gated `GET /admin/api/marketplace/orders/summary` and the frontend operations adapter.
- Counts persisted orders by raw platform status across the complete selected store/platform/order-reference search scope, excluding pagination and selected status. Does not infer AWB, packing, shipment, stock reservations or normalized lifecycle states.
- Empty status counts serialize as an object; unknown provider states remain visible in the contract. No raw payload or recipient data returned. Response is no-store.
- Test first failed for missing route. `MarketplaceOrderTest`: 6 passed, 77 assertions, including protection, search, platform isolation and status counts independent of page/status filter. Frontend type check executed.
- OMS source still needs runtime binding, server pagination and truthful status labels; no claim of official OMS completion and no deployment.
