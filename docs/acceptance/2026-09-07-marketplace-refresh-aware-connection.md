# Refresh-aware connection metadata

Root cause: connection metadata required an unexpired access token, while ShopeeTokenManager already refreshes expired/near-expiry access tokens during actual operations. The UI blocked those operations before refresh could run.

Local fix: retain connected status when application/environment binding matches and either access exceeds the manager's five-minute margin or a stored refresh token has no known expiry/past expiry. The query selects only a refresh-presence boolean and expiry, never encrypted token contents. Unknown refresh validity is not a guarantee of provider acceptance. Actual requests remain subject to the existing token manager and platform errors.

Missing/expired refresh credentials with unusable access still require reauthorization. Metadata GET does not call Shopee or rotate credentials. No DOM, assets, platform write gates or remote configuration changed.

Evidence: regression failed on connected vs reauthorization_required before implementation; passed afterward. Full Marketplace/Shopee suite 79 tests / 569 assertions; isolated MySQL 16 tests / 229 assertions. MySQL first exposed a test gate default inherited from the manual runner environment; authorization tests now explicitly set gate defaults. Disposable schema cleaned up, application schema untouched.

Pending: user phase synchronization approval, then real Sandbox automatic refresh and catalog/order fetch via official UI. No real provider refresh performed in these tests. Overall goal incomplete.

Additional local failure-path coverage: a simulated provider 503 leaves encrypted access/refresh values and expiry unchanged; explicit subsequent retry rotates both credentials and later access reuses the stored result without a second refresh. This verifies the existing transaction behavior, not real Shopee recovery. Full regression after this addition: 80 tests / 580 assertions passed. No additional production-code changes or deployment.
