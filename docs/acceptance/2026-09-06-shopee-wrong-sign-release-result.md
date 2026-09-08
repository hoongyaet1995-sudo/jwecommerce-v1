# Wrong sign patch release

Explicit synchronization approval received. Local main, GitHub main and Hostinger HEAD all verified at `4f7c5c972481375a06ce54ff22cb7023e2cf4f70`.

57 backend tests (344 assertions), 10 frontend tests passed immediately before publication. Only authorization gateway, redirect allowlist, regression tests, matching build and root-cause document included. No environment or database changes in this patch.

Real authenticated HTTPS browser click from `/admin/marketplace/oauth-acceptance` now reaches Shopee's `Login to Authorize Shopee Openplatform APP` page; the previous `error_sign` response no longer occurs. Shopee redirected to HTTP account login; no credentials were entered there. The same official account URL was upgraded to HTTPS and the login form rendered successfully without a certificate bypass.

Evidence: `output/playwright/marketplace-30/oauth-fixed-live-https-login.png`.

Seller login, consent, token exchange and persisted connection remain pending; this release fixes the observed authorization-signature error, not the complete OAuth lifecycle. Marketplace operations remain disabled. Live platform configuration unchanged.
