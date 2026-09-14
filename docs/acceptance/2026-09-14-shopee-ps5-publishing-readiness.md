# PS5 Pro Shopee publishing preparation

Baseline release: 1e6d32d63730e479dcdd769a39548bb9cae0b73b. This publishing change is local and awaits synchronization approval.

## Implemented locally

- Publishing source includes image_url and option_image so image changes invalidate a saved source revision.
- First-option image plan uses the editor's option_image first, with image_url fallback. Different images within one first-option group are rejected instead of silently choosing one.
- Owned local variant images are uploaded through the Shopee client after destination and provider identity checks. Confirmed receipts bind store identity/application and actual bytes, deduplicate identical assets, and are checked again before dispatch.
- Product images remain on the existing owned-image path. No arbitrary remote image fetching is added.
- HTML descriptions are converted to readable plain text for the existing normal-description request.
- Migration: 2026_09_14_000003_create_shopee_variant_image_receipts.php (applied locally only).
- No UI changes or Admin asset rebuild required.

## Verification

`C:/xampp/php/php.exe artisan test --compact --filter='Shopee|MarketplacePublishing|BasicPublishingDestination'`

99 tests passed, 393 assertions. Includes variant image upload/binding, unchanged-image reuse, changed-byte rejection, image selection precedence, option conflicts and normal-description conversion. Transport tests use fake provider responses; no real product was created.

## User-approved online test data

The user selected editing images personally and completed it. Live readback: product #45 PS5 Pro has one managed JPG main image; all 16 option_image fields reference that new managed JPG. Legacy image_url values differ; the new option_image precedence is important.

Shopee shop 227906696 was verified through get_shop_info as MY (store #2). The authenticated user's account was used to mark this store and all 16 PS5 Pro variants as test data.

User authorized missing weight to use 1000g, and missing L/W/H to use 1cm each. Empty packaging fields were filled locally and online. Existing 350g values were preserved. Online readback: all 16 variants are test-classified, weight 350g, dimensions 1 x 1 x 1cm. These are Sandbox values, not verified physical PS5 measurements.

## Release / remaining activation

Stage only MarketplaceLocalImage, MarketplacePublishingSource, ShopeePublishingImages, ShopeePublishingMasterData, new ShopeeVariantImagePlan/ShopeeVariantImages, new receipt migration, related tests and this document. Leave unrelated WMS work out.

After approved same-SHA deployment, run only the new receipt migration and clear caches. Confirm no queued/processing publishing tasks before enabling the non-secret environment-specific settings:

```
MARKETPLACE_SHOPEE_TEST_STORE_ID=2
MARKETPLACE_PUBLISH_OPERATIONS_ENABLED=true
```

Keep Basic WMS enabled and stock-write/advanced controls unchanged. No secrets belong in source control. Local store IDs differ: do not reuse online #2 in local configuration without checking its identity.

Then verify BasicPublishingDestination for product #45/store #2 and prepare images (image upload only, not add_item). The user will select the correct Shopee leaf category, attributes, brand and logistics in the publishing wizard and submit the listing themselves. Do not create a product task on their behalf. Full provider product/variant acceptance remains unproven until that user submission succeeds.
