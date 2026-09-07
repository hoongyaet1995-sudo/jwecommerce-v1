# ZIP 32 reviewed import — local checkpoint

Status: local implementation; no official UI cutover or deployment approval recorded for ZIP 32.

## Implemented contract

- Existing `/admin/api/marketplace/listings/{listing}/import-product` accepts optional `existingLinks`, keyed by saved listing variant ID, with numeric `productId` and `variantId` values.
- Requests without that field retain the previous whole-product import path.
- Reviewed imports lock the store, validate source membership, and atomically link existing variants and create missing variants in a new draft with zero physical stock.
- All-existing reviews create no empty parent product. Existing price and stock are unchanged.
- Same-store duplicate mapping or an invalid parent rolls back mappings, audit events, draft creation and import receipts together.
- Durable reviewed-import receipts return the prior result on retry and do not restore subsequently removed mappings.
- ZIP 32 local preview passes reviewed selections to the API; completion counts distinguish drafts, created variants and existing links.

## Evidence

- `MarketplaceImportTest`: 23 tests, 268 assertions passed after all-existing and duplicate-mapping coverage.
- Prior full Marketplace regression: 88 tests, 755 assertions passed before that additional test.
- ZIP 32 and related integration frontend tests: 20 passed.
- Targeted TypeScript checks of both connected modal wrappers passed after shared response type correction.
- Admin Vite build passed with existing large-chunk warning.
- Reviewed-import receipt migration applied to local MySQL only.

These are local test/build results, not a claim of a real Shopee round trip. Browser acceptance of mixed imports and MySQL concurrent reviewed-import races remain outstanding.

## Publishing boundary and next integration

The extracted `PublishProductModal` remains isolated preview code. Inspection confirms timer-driven success, random job/item IDs, assumed Shopee/Lazada availability and hardcoded category/brand/package values. Do not expose it as an operational publishing entry point.

Before operational wiring:

1. Replace simulation with a backend task contract, stable request identity and persisted queued/processing/succeeded/rejected/uncertain results.
2. Supply provider capabilities and actual category, attributes, brands and logistics metadata; form completeness must not be described as platform verification.
3. Reconcile an uncertain request before any resubmission. No timer or missing response may establish a mapping.
4. Map only actual returned item/model identities, with opt-in stock writes and explicit destination validation.
5. Preserve source DOM and classes while adapting data and behavior. Keep private source preview available for visual comparison.

CMS motion is excluded from this Marketplace phase. Cron testing remains deferred. GitHub/Hostinger synchronization requires the phase approval gate.

## Official publishing protocol checkpoint

Read in authenticated Chrome on 2026-09-07: https://open.shopee.com/documents/v2/v2.product.add_item?module=89&type=1 . Direct web extraction returned 403; the browser rendered the request table successfully.

Verified differences from the ZIP wizard:

- Package dimensions use integer centimetres; weight uses kilograms.
- Creation requires platform image IDs, not JW image URLs.
- Logistics selections carry channel IDs and conditional size/custom-fee fields.
- Category attributes use IDs and values; custom values have their own rules.
- Brand is an ID/name object, not just a free-text brand field.
- The documented condition values are NEW/USED; the wizard's REFURBISHED value must not be sent unchanged.
- Item status supports UNLIST/NORMAL; this differs from a local JW Draft status.

This is a partial request-contract inspection, not the completed payload validator. Stock fields, response handling, media upload and variation initialization still require their own official-document verification before implementation. No platform write was performed.

### Follow-up: stock and variation initialization

Read `add_item` remaining stock/response tables and https://open.shopee.com/documents/v2/v2.product.init_tier_variation?module=89&type=1 in authenticated Chrome on 2026-09-07.

- Creation returns `response.item_id`; this proves only the parent creation stage, not completion of a multi-variant listing.
- Stock uses `seller_stock` entries with integer stock and optional warehouse location. Variation documentation directs warehouse identification to `v2.shop.get_warehouse_detail`; do not invent a location.
- Variation initialization must wait at least five seconds after parent creation, supports at most two tiers and documents at most 50 models per request. Model SKUs are limited to 100 characters.
- The current request example uses `standardise_tier_variation`, model `tier_index`, price and seller stock; returned models include their own IDs and tier indices.
- GTIN requirements depend on `get_item_limit`. Do not blanket-fill `00` where a real GTIN is required.

Implementation consequence: persist parent identity and stage before delayed variation work. Parent-created/variant-failed is not whole-task success and must never repeat `add_item`. Model identity reconciliation remains outstanding.

## Local publishing task checkpoint

- Added request-identity persistence, parent item checkpoint, compare-and-set transitions, disconnected-store claim protection and interrupted-task classification as uncertain. These do not yet constitute a publishing executor.
- Added the protected read-only `/admin/api/marketplace/publishing-tasks/{task}` endpoint. It returns only task identity, state, external item identity and timestamps, not submitted payloads.
- ZIP 32 `PublishProductModal` now accepts a saved `taskId` for read-only monitoring. Poll Status queries that same task, never the simulation submission callback. Closing stops polling; unknown results do not imply rejection or automatic retry.
- Saved-task mode does not claim listing visibility, mapped variant counts or enabled inventory synchronization from a parent item ID. The no-task isolated preview still retains source simulation and is not an operational publishing entry point.
- Shopee image upload and parent creation transport have signature, malformed response and timeout tests. These use simulated HTTP; neither is a real platform write acceptance result. Media upload documentation was read at `https://open.shopee.com/documents/v2/v2.media_space.upload_image?module=91&type=1`.
- Current local regression run: `artisan test --compact --filter=Marketplace` — 100 tests, 817 assertions passed. Targeted frontend monitor/contract tests — 14 passed; targeted publishing-modal TypeScript check passed.

Still required: provider payload validation, real metadata binding, submission endpoint, executor and variation stages, uncertain-result reconciliation, end-to-end browser/MySQL concurrency acceptance, and authorized Sandbox publishing. No GitHub/Hostinger release has occurred for this phase. Cron acceptance remains deferred.

### Attribute contract verified in official browser documentation

Read `https://open.shopee.com/documents/v2/v2.product.get_attribute_tree?module=89&type=1` on 2026-09-07.

- Required child attributes depend on the selected parent value (`child_attribute_list`), not just on the category's top-level mandatory flags.
- Input types distinguish single dropdown, single combo, free text, multi dropdown and multi combo (1 through 5). Validation types distinguish unvalidated, integer, string, float and date (0 through 4).
- Quantitative format uses allowed units. Dates distinguish day/month/year from month/year. `max_value_count` limits selections.
- Crucially, `support_search_value=true` requires the separate `v2.product.search_attribute_value_list` API for default values. An empty in-tree value list cannot be interpreted as permission for arbitrary input.

Next implementation dependency: inspect and implement searchable attribute values before declaring the category form or submission validator complete. Preserve conditional metadata, reject missing/unsupported validation information rather than silently dropping mandatory fields. No live write was made during this inspection.

### Real Sandbox metadata checkpoint (2026-09-07 06:18 UTC)

The approved read-only probe returned HTTP 200 for categories, attributes, brands and logistics. This was streamed to the existing remote runtime, not a deployment of the new application services.

- Single-choice attribute metadata omits `max_value_count`; multi-choice metadata supplied 5. Local validation was corrected and regression-tested. The search flag lives under `attribute_info.support_search_value`.
- Sandbox channels 21012, 21013 and 24000 are enabled and use SIZE_INPUT. Channel 24000 has maximum weight 20 and per-axis dimensions of 30 cm; it cannot be treated as equivalent to the other two channels' limits.
- Actual `channel_relation_rules` is an object with related-enabled/disabled/dependent channel lists, despite the documentation table labeling the field object[]. Do not iterate it as a list of rule objects.
- Official get_channel_list documentation also exposes compulsory-channel, masked-channel, seller-covered-fee and pause-related constraints. Weight limits of zero/null mean no limit. Volume documentation does not clearly specify units; do not invent a conversion.

This proves read-only metadata availability, not successful publishing, shipment eligibility or AWB completion. Logistics preflight and remaining full-listing validation are still pending.

### Product media ownership inspection

Catalog media is stored in `product_images.path`, with variant images also stored separately. `CatalogApiController` permits managed `/storage/cms/media/...` assets, public HTTPS references accepted by `MediaUrlPolicy`, and existing bundled `/images/catalog/...` files.

`MediaUrlPolicy` validates URL syntax and literal public IPs; it does not resolve and pin DNS for server-side downloading. It is not sufficient as the sole SSRF defense for a publishing image downloader. Do not simply pass an accepted catalog URL to `Http::get()`.

Publishing media work must resolve product-owned image records, read managed/bundled assets with bounded file size, and use a DNS/IP-validated, pinned, redirect-controlled download boundary for remote images. Validate actual bytes before Shopee upload and preserve uploaded image identity for task recovery. WebP/GIF catalog support also needs explicit conversion/handling because Shopee upload currently accepts JPEG/PNG. No arbitrary remote URL-fetch route or server downloader exists in this phase yet.
