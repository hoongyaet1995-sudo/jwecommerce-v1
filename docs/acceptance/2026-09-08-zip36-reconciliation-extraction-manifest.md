# ZIP36 reconciliation extraction manifest

Source archive: `C:\Users\User\Downloads\jw-ecommerce-dashboard (36).zip`.

Source hash (SHA-256): `5435213C00E6B9E8BBA4E8536D0677D577597061B2EB449C910B0DA6DF5B5C42`.

Private untouched extraction: `C:\Users\User\Downloads\jw-ecommerce-dashboard-36-source`.

Selected source regions are `WarehouseInventoryReconcileModal.tsx`,
`ModeSwitchModal.tsx`, and `ModeAndWarehouseConfigTab.tsx`. The supplied
modal keeps the ZIP35 viewport containment, internal scroll body, and pinned
header and footer treatment.

## Server contract now available

`POST /admin/api/inventory/reconciliations` accepts only the locked balance
identity, balance and legacy-stock versions, safety-stock units, operation key,
and reason. The authenticated administrator is taken from the session; client
operator fields and client reservation values are rejected. The server derives
the reservation total, records an immutable before/after audit record, and
returns the WMS receipt values. Reusing the same operation key is idempotent.

`GET /admin/api/inventory/balances/{balance}/obligations` remains the source
for the fixed balance context, derived reservations, unowned reservations,
and allocation list used before opening the modal.

## Deliberately unmounted source region

ZIP36's architecture-switch UI requires an atomic server migration contract:
target-mode readiness, in-transit-transfer and unallocated-order blockers,
explicit facility distribution, stock-conservation validation, and audited
configuration-version updates. That contract is not yet implemented. The UI
must stay in server-readiness state until the corresponding mutation service
exists; a successful-looking front-end mode switch would corrupt ownership
semantics.

## Verification

`C:\xampp\php\php.exe artisan test --compact --filter=InventoryReconciliationTest`
passed: 3 tests, 30 assertions.

`vite build --config resources/admin/vite.marketplace33.config.ts` passed.

The repository-wide TypeScript invocation currently reports pre-existing ZIP33
and ZIP34 missing type-module imports and an unrelated marketplace32 fixture
shape mismatch. The selected Marketplace Vite build is the applicable compile
check and completed successfully.
