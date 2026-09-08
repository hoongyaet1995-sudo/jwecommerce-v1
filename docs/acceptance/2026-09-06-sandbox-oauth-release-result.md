# Sandbox OAuth release result

User explicitly approved Sandbox OAuth publication. Published commit: `a87b1bce30e25bb42b9f16dfdb22af9cac5ba9c8`.

- Local main, GitHub main and Hostinger HEAD verified identical.
- 56 PHP tests / 343 assertions, 10 frontend tests passed before publication.
- Hostinger pulled via read-only SSH deploy key; Marketplace migrations 000002 through 000010 completed successfully.
- Only allowlisted Sandbox environment settings transferred over SSH stdin. Existing production APP_KEY and unrelated configuration preserved. Private permission-0600 environment backup retained outside public root.
- Acceptance flag and Shopee sandbox enabled; operations disabled. No Marketplace worker started. Live credentials not transferred.
- Readiness reports sandbox configured, callback ready, no missing tables. This is configuration readiness, not provider acceptance.
- HTTPS acceptance route redirects guests to admin login; authenticated admin sees Shopee available and other providers unavailable.
- Authenticated catalog and order API requests returned 503. Evidence: browser console and live screenshot under `output/playwright/marketplace-30/oauth-sandbox-release-live.png`.
- Real browser click proceeded to the official Shopee Sandbox authorization endpoint with Test Partner ID 1232619. Shopee returned HTTP 403, JSON `error_sign` / `Wrong sign.` No seller consent or token exchange succeeded; no connection success claimed.
- Provider response screenshot: `output/playwright/marketplace-30/oauth-sandbox-provider-response.png` (may contain transient authorization URL; keep local, not a public asset).

Next action: verify Test Partner ID/key provenance against the authorized Shopee application and signature implementation. Do not change Live configuration or bypass signature verification. Existing local code uses HMAC-SHA256 with partner ID + API path + timestamp; compare with the read-only Dashboard reference before changing code.

The deployment is complete. Real OAuth and the overall Marketplace goal remain incomplete.
