# Shopee Sandbox authorization Wrong sign — confirmed gateway mismatch

## Evidence and exclusions

Read-only JW Dashboard history was inspected: `c91c52b` restored raw Partner Key for authorization; `97ce37d` removed shpk hex decoding for API signing; `fc300c6` changed API hosts. Dashboard currently uses Live Partner ID 2033707. Its working Live configuration is not evidence that the same host accepts this Sandbox application.

The current Shopee console Test Partner ID 1232619 and Test Key were checked against local and Hostinger configuration without printing keys: fingerprints match, length 64, shpk prefix retained. No credentials were changed. Provider Date headers differ from local clock by 0–1 seconds.

Real Sandbox HTTP probes, no seller login/consent, no token exchange:

- Existing authorization URL: `partner.test-stable.shopeemobile.com/api/v2/shop/auth_partner` => HTTP 403, `error_sign`.
- Adding the Dashboard legacy redirect token => same error.
- Historical shpk hex decoding => same error; not adopted.
- Identical raw-key signature, Partner ID and redirect at `openplatform.sandbox.test-stable.shopee.sg/api/v2/shop/auth_partner` => HTTP 302 to `open.sandbox.test-stable.shopee.com`.

After the fix, a fresh probe of the actual ShopeeClient output again returned 302 with no error; the old host control again returned 403. This isolates the authorization gateway as the cause for this application. It does not establish successful seller authorization or correctness of downstream token/catalog/order API hosts.

## Local change

- Sandbox authorization alone uses the verified Open Platform gateway.
- Frontend redirect allowlist follows that exact hostname and path; lookalike hosts remain rejected.
- Live authorization remains `partner.shopeemobile.com`.
- Partner Key remains raw; other API routing, callback binding and OAuth state validation are unchanged.
- No UI layout, Dashboard configuration, Live credentials or inventory changed.

Regression workflow: PHP and frontend hostname tests failed first, then passed after the change. Full Marketplace/Shopee suite: 57 tests / 344 assertions. Frontend operational/modal suite: 10 tests passed.

Pending: explicit patch synchronization approval, followed by real HTTPS browser seller authorization. This is a local fix with real authorization-endpoint evidence, not a full OAuth closure claim.
