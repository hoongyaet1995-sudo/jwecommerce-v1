# MySQL order concurrency acceptance

Command: `C:/xampp/php/php.exe tests/manual/marketplace-order-mysql-race.php`

Result: PASS on local MySQL, using a randomly named disposable schema and copying table DDL only, never business rows. Two PHP worker processes competed while the parent held the store lock, then both executed MarketplaceOrderSnapshotService::saveShopee for the same external order identity.

Verified:

- Both workers returned the same persisted order ID.
- Exactly one marketplace_orders row exists.
- A newer SHIPPED snapshot remains after replaying the older READY_TO_SHIP snapshot.
- Private fixture content is not stored as plaintext in encrypted_payload.
- Test schema was removed; configured application database was unchanged.

This is real MySQL concurrency with synthetic input, not real provider ingestion or inventory reservation evidence. The test deliberately has no provider calls and no stock tables. Same-store mapping concurrency remains a separate acceptance item.

Pending publication approval: local read-only gate, catalog compatibility repair, order detail and preview bundle have not been pushed/deployed. Production stays at c8cc6262 with operational reads disabled until the next release is approved.
