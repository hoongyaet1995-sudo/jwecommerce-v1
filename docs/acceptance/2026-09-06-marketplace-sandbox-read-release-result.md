# Shopee Sandbox read release acceptance

Date: 2026-09-06. Approved scope: GitHub/Hostinger phase synchronization and Sandbox catalog/order reads only.

## Release

- Local, GitHub main and Hostinger were verified at `ddbd88f126d28a3fcf66573c289e86689baed615`.
- Production root: `/home/u762348670/apps/jwecommerce`.
- Hostinger read operations enabled; general operations remain disabled.
- Only connected store: Shopee Sandbox `227482706`, database ID 1.
- No official UI cutover, publication, import, inventory push, shipment or new test-order creation.

## Real HTTPS and database evidence

Using an authenticated Playwright administrator session on the production HTTPS origin:

1. Catalog and order fetch POST endpoints returned 202; database queue contained only the two expected read job types.
2. Two bounded `marketplace:cron` executions processed the jobs successfully.
3. Catalog run 1 and order run 1 returned HTTP 200 and `succeeded`.
4. Catalog contains one product/model: `JW Sandbox PS5`, item `846055463`, model `12983425`, SKU `JWSTORE1PS5-500GB`, MYR 1400, observed platform stock 99.
5. Order list and detail return actual test order `260906B667WJ5E`, `READY_TO_SHIP`, MYR 1407.31; one matching external model, quantity 1. The order line truthfully remains unmapped. Fulfillment and airway-bill capabilities are false. Default detail contains no recipient fields.
6. Repeated both fetches. Runs 2 also succeeded, retaining the same listing/order IDs and exactly one row in each table. No duplicate creation.
7. After both rounds the queue is empty. JW product count remains 10. Sorted variant ID/stock SHA-256 before and after is unchanged: `e9648ce1062bbbb306ddafaf397f9625515c20275450b7044e5e0edef3e55e11`.

This is genuine provider HTTP -> queue -> persisted MySQL -> authenticated HTTPS API evidence, not intercepted browser fixtures.

## Remaining gates

- Official merchant UI binding/cutover and actual rendered order-drawer acceptance are not established by these API checks.
- The worker was invoked manually and bounded; unattended cron has not been installed or accepted.
- SKU mapping/import, publication, inventory and fulfillment still require their own real acceptance gates.
- Other platforms are not accepted by this Shopee result. Overall Marketplace goal remains active.
- This post-release evidence document is local pending the next coherent synchronization; the released application SHA above is unchanged.
