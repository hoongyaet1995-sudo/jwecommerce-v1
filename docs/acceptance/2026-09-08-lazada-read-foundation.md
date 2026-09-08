# Lazada seller read foundation

LazadaClient now supports an explicit GET allowlist for /orders/get, /order/get, /order/items/get, /products/get and /product/item/get against Malaysia's HTTPS REST endpoint. It signs with the server application secret, blocks authentication parameter overrides and write paths, does not follow redirects, sanitizes transport/provider errors and requires code0 plus data before returning. Existing token exchange/refresh behavior is unchanged.

Official references consulted:
- https://open.lazada.com/apps/doc/doc?docId=120984&nodeId=30764 (order and fulfillment API paths)
- https://open.lazada.com/apps/doc/doc?docId=108139&lang=en_US&nodeId=10397 (API mapping)
- https://open.lazada.com/apps/doc/api?path=%2Fmedia%2Fvideo%2Fblock%2Fcreate (common regional endpoints and authentication parameters)

LazadaReadClientTest: 2 passed / 7 assertions, mocked HTTP only. No real provider request was sent. Next: integrate LazadaTokenManager, bounded order pagination/details, encrypted snapshots and item-based lifecycle normalization. Endpoint-specific parameters and payload schema must be verified against official API documentation before implementing pagination or stock effects. Read client availability does not mean a Lazada store is connected or operational.
