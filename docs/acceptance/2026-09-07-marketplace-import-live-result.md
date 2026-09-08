# Real Sandbox product import

- Released commit 67c9233d88312ab150eb6cfbb11ef4a7ac10ff8c matches local, GitHub main and Hostinger.
- Enabled only MARKETPLACE_IMPORT_OPERATIONS_ENABLED in local and Sandbox host .env. General provider operations remain false. No credentials printed or committed.
- Actual official Mapping button clicked for store 1 / product 846055463 (JW Sandbox PS5).
- Database confirms JW product 51, status draft; variant 92, SKU JWSTORE1PS5-500GB, stock 0; one mapping created. Product count increased from 10 to 11 as expected.
- Existing variants' stock fingerprint remains e9648ce1062bbbb306ddafaf397f9625515c20275450b7044e5e0edef3e55e11. No platform stock or publishing write.
- Screenshot output/playwright/marketplace-30/import-real-result-20260907.png.
- Read-only verification script initially used the DTO alias jw_variant_id instead of database product_variant_id; corrected query and reran. This was a diagnostic query error, not import failure.
- Remaining: real duplicate import retry and refreshed mapped UI/PDP draft visibility checks, single-variant import, larger batch handling, publish/fulfillment/inventory and further platforms. Overall goal incomplete.
