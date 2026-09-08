# Single variant import: local backend checkpoint

Not released or browser-accepted. Source UI remains unchanged and disabled.

## Contract

POST `/admin/api/marketplace/listings/{listing}/import-variant` accepts either `productId`, an explicitly selected existing JW parent, or JSON `newProduct: true` for a new draft. Both together, false, omitted destination and unknown fields are rejected. Optional `sku` accepts an explicitly reviewed nonempty string up to 255 characters; omitted SKU uses the saved channel code. Admin authentication, import operation gate and rate limiting apply. Actor, price and stock cannot be supplied by the request. Response contains numeric `variantId` and `productId`.

Reviewed SKU is trimmed, checked against existing JW codes and never written back to the channel snapshot. Retried imports return the retained identity and do not rename an already imported SKU. Conflict rolls back a new draft; changing the reviewed code can then append the variant to an existing parent without changing its stock, title or base price. Latest focused import/gates/metadata/client run: 52 tests, 373 assertions passed. This is backend test evidence, not ZIP 32 browser acceptance.

New draft mode imports only the selected variant, preserves saved title/description/brand/safe gallery, uses the selected variant price as base price, and keeps stock zero. Invalid variant data rolls back the new parent. Repeated requests return the retained variant identity, not another draft. This does not import siblings or publish to Storefront.

Service creates only the selected saved external variant, with zero stock, and maps it transactionally. Existing parent content/pricing/publication and sibling stock remain unchanged; has_variants becomes true if needed. A retained variant import identity prevents retries from creating duplicates or restoring employee-unlinked relationships. A retry selecting a different parent is rejected. Existing whole-product parent identity cannot be contradicted.

Whole-product import now rejects previous single-variant import identities even after unlink and local SKU rename, preventing duplicate master creation.

## Verification

- Local application DB checkpoint: the single-variant identity migration was Pending, then applied explicitly using `artisan migrate --path=database/migrations/2026_09_07_000001_create_marketplace_variant_imports.php --force`; status now `[55] Ran`. This adds only the identity table and constraints. No Hostinger migration was executed. Local connected-store/catalog absence still prevents real-source browser import acceptance.

- Full Marketplace/Shopee suite after new-draft endpoint: 88 passed, 669 assertions.
- New-draft endpoint test first failed with 422 for a valid request; then passed with explicit destination handling, invalid-source rollback and retry assertions.
- Disposable MySQL feature suite before latest whole-import guard: 21 passed, 288 assertions.
- `tests/manual/marketplace-import-mysql-race.php`: two independent processes contend on the same store lock, return the same variant identity, produce exactly one variant/import/mapping/audit record and zero stock. Disposable schema removed, application data unchanged.
- Race initially exposed a repeatable-read stale snapshot and unique violation. Source lookup moved before transaction and retained-identity read made locking/current; same race then passed. No generic exception swallowed or retry used to conceal failure.
- Gate tests now explicitly configure all release switches and cover both import endpoints rather than inheriting local .env values.

## Remaining before operational closure

Reviewed destination UI and browser acceptance, broader competing-destination/cross-store SKU races and friendly unique-conflict handling, real Sandbox source acceptance, release migration and same-commit verification. The two-process race also passed in `new-parent` mode: exactly one draft parent, one variant, one retained identity, one mapping and one audit event; base price 1234 and stock zero. Both modes use disposable local MySQL schemas, not platform calls. No claim that this backend checkpoint completes Marketplace or publishing.
