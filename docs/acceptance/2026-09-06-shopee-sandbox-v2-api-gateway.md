# Sandbox V2 API gateway follow-up

Official source read in authenticated Chrome: https://open.shopee.com/developer-guide/644 (Sandbox Testing V2, last updated 2025-09-16), reached through the user's official developer guide.

The document identifies `openplatform.sandbox.test-stable.shopee.sg` as the Sandbox V2 API domain for products, orders, logistics and shop calls. The current test account is explicitly under Test Account-Sandbox v2. Previous Dashboard Live API host fixes must not be generalized to this Sandbox V2 deployment.

Local follow-up switches the ShopeeClient shared sandbox API host to that domain; authorization now reuses the shared host. Production remains `partner.shopeemobile.com`. No UI, keys, database changes, automatic catalog/order tasks or stock writes.

Test-first evidence: the sandbox token exchange host assertion failed before the change, then the full Marketplace/Shopee suite passed: 58 tests, 345 assertions. Provider responses in these tests are fixtures; real token exchange awaits consent and deployment.

User approved Confirm Authorization for MY Sandbox shop 227482706 and subsequently approved synchronization of this Sandbox V2 API patch. If the OAuth state expires while awaiting release, initiate a fresh state through the normal protected entry; never extend or reuse an expired state or manually transfer a code between deployments.

The user-approved consent scope displayed by Shopee includes product, order, payment, marketing and customer-service information for this Sandbox account. Do not expand to Live or another shop.
