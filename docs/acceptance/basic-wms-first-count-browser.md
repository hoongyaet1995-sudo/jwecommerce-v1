# First physical count browser evidence

2026-09-08, authenticated Chrome on local ZIP36 preview, bundle main-CU0F3sZy.js.

Fixture created by tests/manual/basic-wms-first-count-fixture.php: draft test product, variant 103, SKU JW-QA-BASIC-FIRST-COUNT-0908, explicit test classification, legacy stock 17, no physical balances.

1. Opening the existing stock audit modal offered active warehouse QA-ZIP36-CYB. Database still had no physical balance after opening.
2. Entered count 12 with reason BASIC-WMS-20260908 first count 12 test units and proceeded to review. Database contained balance 14 with physical null, safety null, version 0; no physical quantity was seeded.
3. Server review displayed physical before unknown, after 12, Basic available unknown because safety was unset, and employee 2.
4. Acknowledged and confirmed. Database showed physical 12, safety null, version 1 and legacy stock still 17. Browser matrix refreshed to 12 verified physical units.

Observed remaining issue: available cell was blank for counted physical stock with unknown safety. Corrected display fallback to Unknown and stopped treating null availability as out-of-stock; rebuild/visual check remains. Safety configuration and full release acceptance remain open.
