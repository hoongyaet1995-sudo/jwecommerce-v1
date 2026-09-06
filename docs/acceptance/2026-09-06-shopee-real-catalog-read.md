# Shopee real catalog and order read probe

Read-only probe executed from the deployed Laravel runtime against the authorized MY Sandbox shop 227482706. No token refresh, database writes, queue dispatch or marketplace writes. Access token remained server-side. Probe source lives locally in tmp/probe-sandbox-catalog-orders.php and was piped to PHP over SSH without installing application changes remotely.

## Actual platform results

- NORMAL catalog: 1 item, ID 846055463, status NORMAL, no next page.
- Base information: JW Sandbox PS5, has_model true.
- Model 12983425: White,500GB; model SKU JWSTORE1PS5-500GB; MYR 1400 current price; available stock 100, reserved 0. These are observations, not JW inventory initialization.
- Orders updated in the preceding seven days: successful response, zero orders, more false. This is not a populated order ingestion acceptance.
- UNLIST and BANNED catalogs: HTTP 200, empty error, response keys total_count/has_next_page/next, total_count 0 and has_next_page false. The platform omits item entirely.

## Local compatibility repair

Existing parser incorrectly rejected those explicitly empty responses. Added a narrow normalization only when item is absent, total_count is integer zero and has_next_page is boolean false. Missing count, positive count, more pages and malformed item fields remain errors.

Test first: new empty-catalog test failed against prior parser with RuntimeException. After implementation: 60 Marketplace/Shopee tests passed, 351 assertions. These regression tests use HTTP fixtures based on observed structure; the repair is local and not yet deployed.

## Next acceptance

- Persist real catalog through the approved operational entry and prove repeat fetch does not duplicate records.
- Verify actual SKU mapping in browser and MySQL.
- Create the user-authorized official Sandbox order fixture, then verify populated order ingestion.
- Keep stock/publishing/shipping writes disabled until their own controlled acceptance.
