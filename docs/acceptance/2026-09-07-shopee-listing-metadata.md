# Shopee listing metadata foundation

Local only; not deployed and not a publishing completion claim.

## Official contract inspected

On 2026-09-07, inspected the authenticated official documentation in Chrome:
https://open.shopee.com/documents/v2/v2.product.get_category?module=89&type=1

The web reader returned HTTP 403; browser access succeeded. The endpoint is GET `/api/v2/product/get_category`, shop-bound HMAC-SHA256 authentication, optional language (`en` supported for MY). Response `response.category_list` contains category_id, parent_category_id, original_category_name, display_category_name and has_children. The documented Sandbox host matches the existing Sandbox V2 client.

## Local implementation and evidence

- ShopeeClient::categoryTree reads English category data with the existing signed shop transport. No writes or fallback categories.
- Rejects missing list and malformed required category fields rather than reporting empty success.
- Test-first: two missing-method failures, then passing request/signature/hierarchy and malformed-response tests.
- Full Marketplace/Shopee suite: 90 passed, 676 assertions; git diff --check clean.
- These are HTTP-fixture contract tests, not a real Sandbox metadata response. No claim of real platform verification yet.

## Next boundaries

Protected GET `/admin/api/marketplace/stores/{store}/listing-categories` now uses the saved store identity and ShopeeTokenManager. It requires admin access and the read-operation gate, rejects disconnected/wrong-platform/wrong-environment stores, throttles requests, returns only category fields with no-store, and reports sanitized 502 errors rather than fake success. Three new endpoint tests passed (18 assertions), after first failing on the missing route.

## Real Sandbox protocol evidence

2026-09-07T04:11:39Z: `tests/manual/shopee-sandbox-category-probe.php`, developed locally and streamed to PHP over SSH without installing remote source, queried only approved Sandbox store 1 / shop 227482706. The existing encrypted token manager provided server-only credentials. Remote commit was still 67c9233d88312ab150eb6cfbb11ef4a7ac10ff8c.

- HTTP 200, 2799 categories, 2273 leaves, zero malformed rows.
- Output contained only counts, status and timestamp; no tokens or signatures.
- No catalog import, publishing, shipping or inventory write. Token manager may perform normal credential refresh if needed.
- This is a real protocol/credential probe, NOT proof that the new local admin endpoint is deployed or browser-accepted.
- Latest local regression suite before this probe: 93 passed, 694 assertions.

## Attribute contract and real probe

Authenticated Chrome inspection of https://open.shopee.com/documents/v2/v2.product.get_attribute_tree?module=89&type=1 showed a documentation discrepancy: request table uses category_id_list (up to 20), while the example uses category_ids and an internal UAT host. The implementation uses the documented parameter table and the previously verified official Sandbox host, not that example host.

2026-09-07T04:13:36Z: approved Sandbox product 846055463's saved category 106511 returned HTTP 200 and 13 attributes using category_id_list. Returned top-level attribute keys: attribute_id, mandatory, name, attribute_value_list, attribute_info, multi_lang. No product or stock mutation.

Local ShopeeClient::attributeTree now reads one explicitly selected category, verifies matching response category and complete tree, rejects category warnings, and preserves conditional children and input/unit metadata. Two test-first failures preceded implementation.

Protected GET `/admin/api/marketplace/stores/{store}/listing-attributes?categoryId=...` now exposes this read through the encrypted token manager. Category ID must be explicitly supplied and positive; admin/read gate, store connection/environment and rate limiting apply. Errors are sanitized. Endpoint tests first failed on the absent route, then passed; metadata suite is 5 tests / 34 assertions. Detailed nested-schema validation and browser acceptance still pending.

## Brand contract and real probe

Official authenticated documentation inspected: https://open.shopee.com/documents/v2/v2.product.get_brand_list?module=89&type=1

Brand lookup is leaf-category scoped, status 1 is normal, maximum page size 100, and next_offset is the continuation cursor. is_mandatory and input_type are part of the response and must reach the listing form/validator.

Local ShopeeClient::brandPage implements the signed read with status 1 and English, preserves pagination and requirements, and rejects malformed responses or nonadvancing cursors. Two tests failed before implementation and passed afterward. Full suite: 99 passed, 721 assertions.

Real Sandbox protocol probe at 2026-09-07T04:18:05Z: category 106511 returned HTTP 200, 20 brands, has_next_page false, next_offset 0, is_mandatory true, input_type DROP_DOWN. No write calls. This proves the fixture contract against one real category, not all-category completeness or deployed endpoint acceptance.

Protected GET `/admin/api/marketplace/stores/{store}/listing-brands?categoryId=...&offset=...` now preserves the brand page and requirements for the selected store. Positive category and nonnegative offset are validated; admin/read gate, connection check, throttling, response field allowlist and sanitized failures apply. Endpoint tests first failed on missing route, then passed. Full local regression: 101 passed, 735 assertions.

Logistics requirements, source-faithful listing UI, local/browser endpoint acceptance, controlled creation/reconciliation and release remain pending. No add_item or inventory write was sent.
# Local logistics endpoint checkpoint

- Added protected GET `/admin/api/marketplace/stores/{store}/listing-logistics`, using the persisted store identity and encrypted token manager. Requires administrator, read gate, connected Shopee store and matching environment; rate limited.
- Keeps disabled channels explicit and returns allowlisted channel metadata. Provider failures are sanitized and responses are not cached. Does not publish, ship or modify inventory.
- TDD: two new endpoint tests first failed with 404; after implementation, metadata/client suite passed 32 tests / 119 assertions.
- Tests cover access rejection, disabled read gate, missing/disconnected/wrong-platform/wrong-environment stores, GET using saved shop identity, retained disabled flag and limits, stripped unknown top-level fields and sanitized provider errors.
- This endpoint is local only, not deployed or wired into ZIP 32 yet. HTTP fixture tests do not constitute real endpoint acceptance. Nested logistics constraint validation and publishing-time validation remain pending.
