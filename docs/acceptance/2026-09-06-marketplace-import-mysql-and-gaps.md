# Import acceptance and remaining contract gaps

## MySQL execution

`C:/xampp/php/php.exe tests/manual/marketplace-mysql-constraints.php` now includes the four MarketplaceImportTest cases alongside mapping/order constraints. Result: **7 passed, 85 assertions**, exit 0. The runner verified the disposable MySQL schema and removed it; application records were unchanged.

Import evidence covers admin-only access, rejecting client-supplied stock/status/actor fields, server-owned audit actor, draft creation, storefront PDP returning 404 for the draft, zero stock despite channel stock 500, persisted import identity on retries even after unlinking, blocking existing SKUs, and whole-product rollback when a later variant has invalid price/currency/title/SKU.

These are synthetic fixtures using real MySQL and application services, not actual channel-product import acceptance. The read-only release does not enable import.

## Source-grounded gaps (not yet complete)

- `MarketplaceImportService::importProduct` now creates product title/base price, safe description, brand and variant title/SKU/price; it does not currently map JW categories, option definitions or structured color/size/material selections.
- Product gallery observations are now imported into existing `product_images` rows (see below). Variant-specific image associations are still absent. Shopee catalog extraction does not yet preserve all metadata needed for a faithful complete JW product draft.
- Missing channel SKU codes are explicitly rejected rather than invented. A controlled review/edit path is needed before importing such products; the current rejection is safe but does not solve the merchant workflow.
- Existing mapped variants block importing their entire parent product. Adding only genuinely missing variants to an already linked JW product remains a separate unresolved workflow.
- No real-platform import, import browser acceptance, or outbound listing publication has occurred in this phase.

Next implementation should extend the provider observation contract and reuse JW's existing product/media/option structures, with a source-faithful preview for any merchant review controls. Keep drafts unpublished, stock zero, and mappings transactional; do not use missing metadata as grounds to invent categories or attributes. Image handling must follow the existing media security rules rather than blindly fetching arbitrary external URLs.

## Main-image import added locally

Existing `productImage` observations now create deduplicated `product_images` records in the same import transaction. URL validation reuses `MediaUrlPolicy`, with an additional 255-byte limit matching the current image-path column. Unsafe or malformed nonempty URLs reject the import before product creation; absent images remain absent, never fabricated. No server-side image fetch or filesystem copy occurs. The merchant's draft remains unpublished with zero stock.

Tests were observed failing before implementation (missing image row; unsafe URL incorrectly returning 200), then passed. Retry does not duplicate image records. Public URL validation is not proof that a remote image remains available; durable media ownership and complete gallery/variant images remain follow-up work.

## Gallery extension

Catalog observations now retain `image.image_url_list` as `productImages` alongside the legacy main-image field. Import validates that it is a list and validates every address before writing, preserves first-observed order, and deduplicates repeated images across variants. Legacy observations containing only `productImage` remain importable. Invalid later gallery entries reject the transaction rather than silently losing images.

The new regression tests were observed failing before the change (missing catalog field, empty imported gallery, invalid gallery returning success). Catalog/import tests then passed: 13 tests, 139 assertions. This extends product-level galleries only; it does not assign variant-specific images or prove remote image availability. No provider calls or production imports were used for this test.

## Brand and category identity extension

Catalog observations preserve official `brand.original_brand_name` and `category_id` as `brand` and `externalCategoryId`. Import validates and stores the brand in JW's existing field, while leaving JW `category_id` unset: a platform ID is not a JW foreign key. Unknown/absent brand stays null. Malformed or overlength brand rejects the complete import.

Regression tests first failed for missing observed brand, null imported brand and invalid metadata returning 200. Catalog/description/import tests now pass: 16 tests, 173 assertions. Category selection/mapping and source-faithful browser acceptance remain unresolved; this does not claim full import completion.
