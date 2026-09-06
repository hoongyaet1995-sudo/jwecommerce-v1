# Catalog connection environment race

Local regression acceptance, 2026-09-06.

The catalog service checked connected status and shop identity before committing a page, but unlike the order service did not recheck the store environment. A controlled HTTP fixture changes the store from sandbox to live while item details are in flight. Before the fix the real job incorrectly succeeded and advanced offset 50 to 51. After the fix it fails the page, preserves offset 50 and prior counters, and writes no catalog rows.

The fix adds the environment comparison under the existing store transaction lock; no UI, provider configuration, or production data changed.

Verification: `C:/xampp/php/php.exe artisan test --filter='Marketplace|Shopee'` — 65 passed, 404 assertions, exit 0. The regression was observed failing before the production change. HTTP responses are simulated for this race; this is not evidence of a live platform environment switch or completed production ingestion.

Publication remains pending the next phase synchronization approval.
