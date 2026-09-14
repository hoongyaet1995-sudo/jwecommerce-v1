# Append variant local verification

- Employee-selected product remains the destination for new variant creation.
- Added regression: import first variant as a new master, append second variant to that master, retry without duplicating either product or variant.
- Existing SKU in selected product is reused and mapped without overwriting price or stock.
- Cross-product conflict reports SKU, owner title, owner ID and requested destination ID. UI surfaces controlled 409/422 messages instead of hiding them behind a generic failure; destination dropdown displays IDs to distinguish equal titles.
- No automatic ownership transfer implemented. Import does not silently move an existing variant, erase historical import identity or delete a parent.
- Catalog/import tests: 32 passed, 336 assertions. Connected variant UI: 4 passed. Admin build passed.
- Earlier image, weight and package capture/import changes remain local in the same pending service files. Stock policy remains zero initial catalog stock and no inferred WMS stock.
- No online user SKU was created or relocated during this task. MCC-Black-M remains reserved for user acceptance. No release performed.
