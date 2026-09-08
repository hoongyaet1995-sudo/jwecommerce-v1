# Physical count review service — local only

Development remains C:/xampp/htdocs/jwstudio-ecommerce on main. User explicitly specified http://127.0.0.1:8500/ and no additional branches/worktrees.

Added persisted physical-count reviews and append-only application movement receipts. Reviews bind balance, actor, proposed count, reason, balance version, legacy availability version and a 15-minute expiry. Confirmation checks current administrator permission, actor ownership, acknowledgment and both versions within a transaction. Retries return the original receipt; a fresh count records a separate version. Unknown initial physical stock has a null delta rather than an invented receipt of goods.

Counts update physical storage only. Reservation coverage is invalidated, legacy availability is untouched, and no provider API is invoked. This is not inventory takeover: HTTP handlers, ZIP34 bindings, warehouse provisioning, reservation reconciliation and active accounting are still pending. Database administrators can edit tables; this is not a claim of cryptographic tamper-proof storage.

Validation: 23 targeted PHP tests / 113 assertions passed (SQLite test database). Tests cover stale balance and legacy versions, actor mismatch, revoked privilege, acknowledgment, expiry, negative counts, explicit zero, retry receipts and recount delta. Local MySQL migration initially rejected two required TIMESTAMP columns due to default handling; changed review expiry/creation and movement creation to explicit DATETIME fields. Local MySQL migration then succeeded. No business count was entered through the service in the development database, and no deployment or remote inventory write occurred.

## HTTP integration checkpoint

Added POST /admin/api/inventory/count-reviews and POST /admin/api/inventory/count-reviews/{review}/confirm under existing auth/admin middleware and web CSRF protection. Review accepts only balance_id, physical_count, reason and optional CSRF form token. Confirm accepts acknowledgment and optional CSRF token; proposed count and actor cannot be overwritten. Both responses are no-store.

Updated targeted suite: 24 tests / 139 assertions. HTTP tests cover guest/nonadmin rejection, forged actor, blank/fractional/negative/overflow count, zero count, acknowledgment, attempted confirmation payload tampering, retry and stale legacy version. Real 127.0.0.1:8500 POST without a session/CSRF token returned 419, demonstrating web CSRF enforcement; this is not authenticated browser acceptance. ZIP34 client binding and balance provisioning are still pending. No application visual structure was changed in this checkpoint.

## ZIP34 component checkpoint

Added countReviewApi transport and optional countApi binding in the isolated ZIP34 InventoryAdjustmentModal. Review and confirmation await server responses, retain errors in the existing header subtitle, and close only after a verified confirmation response. Server actor id and reviewed before/after quantities replace client claims in review; ATP remains pending reconciliation and no automatic push is promised. Employee and warehouse selectors are disabled when bound to this balance-specific API. Delta adjustment remains unavailable pending its own reviewed server operation.

Five frontend tests pass across API contract, SSR and jsdom interaction tests, including a failed confirmation followed by retry. These use simulated transport and are not real browser or provider acceptance. The component is not yet mounted into the preview route; balance provisioning/read mapping and current-user display integration remain required before browser acceptance. On mounting, use a balance-specific component key to isolate pending requests when changing SKU/warehouse.
