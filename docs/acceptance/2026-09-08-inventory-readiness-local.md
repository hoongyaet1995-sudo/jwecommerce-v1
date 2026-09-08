# Inventory readiness diagnostic

Protected GET `/admin/api/inventory/readiness` reports current mode/version, selected-pool balances requiring count/obligation reconciliation, unowned open reservations, in-transit transfers, and enabled routes to inactive warehouses. No configuration mutation or activation occurs.

This is an incomplete diagnostic input to mode conversion, not the activation gate. `activation_ready` and `coverage_verified` remain false until complete SKU coverage, website historical orders, per-store order cutover coverage, legacy available-stock alignment and conversion transaction logic are implemented. An empty blockers list must not authorize activation. ModeSwitchModal has not been mounted.

InventoryReadinessTest passed 1 test / 6 assertions: unauthorized read rejected, empty configuration explicitly not ready. Continue implementing reconciliation and conversion; do not claim mode switching closed from this read endpoint.
