# Marketplace publishing recovery and real-provider acceptance — 2026-09-27

## Evidence and root causes

Production store 1/product 56 (FIX PLATE PMC / SKU 0126206) has Tasks 27 and 28, both uncertain at create with no external item ID and empty diagnostics. Task 28 references 27. There is no newer task for this pair. The recovery query incorrectly included older uncertain Task 27 and reported it as a newer attempt. Local fix ignores older uncertain attempts without confirmed identity, while preserving genuinely newer attempts, queued/processing writes and confirmed listings. Existing deliberate recovery consent is retained. No historical task rows were rewritten.

With explicit user authorization, a fresh controller/service publication of the same product to Shopee Sandbox store 2 created Task 29. Real add_item response: HTTP 200, error product.error_desc_length_min_limit, message "Product Description is required. Please fill in it.", request ID e3e3e7f35c7816cdd00fca165a51cd00. JW source description is null and the outgoing description was empty. The deployed code mislabeled this explicit rejection uncertain. This reproduces a cause for the same source product; it does not reconstruct the missing historical responses of Tasks 27/28.

Local fix classifies this verified rejection as rejected, preserves bounded provider code/HTTP/request ID, and tells staff to update the JW description. Unknown transport/results remain unknown; diagnostics are now retained and visible instead of discarded. No description was invented.

A real attempt to prepare product 58 for Shopee failed before task creation: "This image is not a supported local catalog asset." Its owned images use sg-test-11.slatic.net. Receipt lookup read local-only bytes before the existing automatic importer could run. The image service also changed product_images.path during preparation, invalidating source revision, and receipt lookup did not use the same normalized bytes as upload.

Local fix adds the observed exact CDN host to the existing bounded public-IP-pinned media importer and introduces Shopee-specific owned-image resolution. Both product and variant paths use consistent normalized bytes for upload/receipt lookup without changing catalog paths. Arbitrary URLs, changed variant paths and cross-product image ownership remain rejected. Lazada image handling is unchanged.

## Real accepted publications using deployed application services

Diagnostics were created locally and piped over SSH to existing authenticated controller/services. No replacement application code or direct task-state repair was applied on production.

| Task | Source | Destination | Result |
| --- | --- | --- | --- |
| 29 | FIX PLATE PMC, product 56 | Shopee Sandbox store 2 | Explicit description rejection; old deployed code stored uncertain; no item ID |
| 30 | Baju Kurung Joshua 2, product 63 | Lazada MY store 3 | succeeded, item 14993467237, mappingComplete=true |
| 31 | Baju Kurung Joshua 2, product 63 | Shopee Sandbox store 2 | parent and two models succeeded, item 846060133, mappingComplete=true |

Task 30 used the existing JW size-chart image 177, visually verified as a measurement chart, and zero initial stock. Task 31 used the verified Baju Kurung category 300510, UNLIST, zero initial stock. No catalog descriptions or inventory balances were changed. Lazada official test store 4 had an expired temporary lease; the authorized test used connected store 3 instead.

The live JW Product & SKU Mapping page was searched for Baju Kurung Joshua 2 and showed both new destination listings, each 2/2 variants mapped (mapping rows 8273–8276), channel stock zero. Existing store 4 listing remained present. This verifies the current complete create → provider readback → mapping → user-facing list path for both providers; it does not yet verify the newly modified recovery/image code on production.

## Local validation and release boundary

69 focused PHP tests, 282 assertions passed with isolated SQLite memory DB. GD was enabled only for the test process to exercise image normalization. Scope includes publishing tasks/recovery, Shopee client, failure details, product/variant image preparation, owned media resolution, receipts, and normalization. Imported-image tests verify source revision remains unchanged. Existing image fixtures were corrected to specify their Sandbox application rather than inherit local environment credentials.

Earlier frontend validation: 14 modal tests passed and release frontend build passed. Earlier expanded publishing tests had six separately reproduced baseline failures outside this repair; full TypeScript checking also has existing unrelated errors. Neither is represented as a clean full-suite result.

Application changes remain local and await synchronization approval. Historical Tasks 27–29 retain their actual saved outcomes. FIX PLATE PMC needs a real JW description before Shopee will accept it. New diagnostics cannot recover old discarded provider responses. Successful probes prove the current complete flow for the two selected destinations, not every product/category or all provider errors.
