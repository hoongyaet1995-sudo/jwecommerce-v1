# Shopee product metadata source verification

Read on 2026-09-06 through the authenticated official Chrome documentation, without API writes or console setting changes.

Primary source: https://open.shopee.com/documents/v2/v2.product.get_item_base_info?module=89&type=1

## Confirmed fields and integration consequences

- `description_type=normal` uses `description`. For extended descriptions, the ordinary description is empty; content comes from `description_info.extended_description.field_list`.
- Extended fields carry `field_type` of text or image. Text uses `text`; images use `image_info.image_url`. The importer must preserve their sequence and must not silently discard image blocks or fall back to an empty ordinary description.
- `image.image_url_list` is the product image URL list. This confirms the gallery observation source used in the local implementation.
- `brand.original_brand_name` is the brand name. `category_id` is a Shopee identity, not a JW category identity; do not assign it directly to JW's category foreign key.
- When a product has models, price information must be read from `get_model_list`, not assumed present at product level.
- Product-level weight uses KG, dimensions use CM. Where model-level package values are configured, the product-level fields can represent maximum weight/largest-volume compatibility values. Do not copy these values to every variant as if they were exact.

## JW source findings and next work

`CatalogApiController` stores `descriptionHtml` in `Product.description`; no reusable HTML sanitation step was found in that save path during this bounded inspection. Marketplace text must therefore be escaped into owned markup, not trusted as HTML. Extended image URLs must use the existing media policy before inclusion. Preserve source values in observations; validate the complete generated description against JW/database limits and reject unsupported or malformed structures with a review error, not invented or truncated content.

JW Dashboard's `ProductVariationSyncService::mapFromShopeeTierVariation` supplies a useful read-only precedent for resolving model tier indexes into named options, but the exact `get_model_list` contract and JW option schema still require inspection before implementation.

The inspection itself made no description/brand/option code changes. Secondary search results were not used as authority for the mapping decisions above.

## Subsequent local description implementation

Catalog observations now retain description type, normal text and extended fields. `MarketplaceProductDescription` converts text into escaped paragraphs with line breaks and extended image fields into media-policy-validated image elements, preserving order. Unknown types, malformed blocks, unsafe URLs and generated content above 50,000 bytes produce a review error rather than truncation. No arbitrary source HTML is trusted and no image is fetched server-side.

Import validates every variant observation before writing the parent draft description, within its existing transaction. Invalid later metadata rejects the whole import. Existing imports are still returned without overwriting subsequent merchant edits.

TDD evidence: formatter tests first failed for the missing implementation; integration tests failed for missing observed fields, null imported description and invalid metadata returning success. After implementation the Marketplace/Shopee suite passed: **75 tests, 473 assertions**. Real-provider description ingestion and rendered-browser acceptance remain unverified. Brand/options and controlled publication remain incomplete.
