# Import enablement checkpoint

Goal: wire ZIP 30 import controls to the existing atomic JW draft import service without enabling provider writes.

Current local implementation:
- Added MARKETPLACE_IMPORT_OPERATIONS_ENABLED (default false), middleware import mode, and localCapabilities.importProducts in connections metadata.
- Existing broad operations gate remains compatible; import-only gate does not grant catalog/order fetch or provider writes.
- Red-green tests cover metadata and guarded import: draft creation, zero stock, reads still disabled. Full Marketplace/Shopee regression 81 tests / 591 assertions passed.

Not yet released or enabled in any .env. Next: source-faithful product-level and batch import adapters, awaited failures and duplicate-click handling; do not route single-variant import to the whole-product endpoint under a misleading label. Existing whole-product service rejects partial mapped products and supports idempotent retries. Then local browser/MySQL acceptance, coherent release under user's ongoing authorization, and real Sandbox import/mapping verification.

Frontend adapter preparation: importCatalogProducts resolves selected source products to saved listing IDs, deduplicates selected products, waits for each request and returns per-product success/failure without exposing backend exception content. Missing selections and malformed success responses fail closed. Two adapter tests passed after an observed red run; TypeScript passed. Not yet wired to UI: capability binding, in-flight guard and source button feedback remain. This helper is not evidence of operational browser import.

Subsequent local acceptance: product and batch source buttons now bind to the real adapter, capability-controlled, with an in-flight guard and partial-result selection retention. Single-variant buttons remain disabled because the endpoint imports the whole product. Importing state uses the existing button without layout/style changes. Frontend targeted tests 4 passed, TypeScript and production build passed (main-bwsdEhN6.js; existing chunk warning). Isolated MySQL 17 tests / 240 assertions passed. Browser local intercept test verified disabled pending button, 409 feedback, successful retry and list reload; exactly two intercepted submissions, no real imports. Desktop pending and mobile success screenshots captured. UI fixture routes removed afterward. Real Sandbox import and large-batch rate handling remain unverified.
