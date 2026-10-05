# Developer-only Social Hub installation

Customer Support Overview installation entry removed. All four installation endpoints require a super-admin whose numeric user ID appears in server configuration SOCIAL_HUB_DEVELOPER_USER_IDS (comma-separated). Default is empty and fails closed; client customer roles cannot grant themselves this permission. Developer provisions this once during deployment. OAuth capability checks and existing installation data remain unchanged. Production has not been synchronized for this phase.

Verification: SocialHubOAuthTest 7 passed / 62 assertions. First test run had 1 unexpected fixture failure because rendering the 403 error page requires store_settings; added that fixture table, then passed. Issue resolved; cumulative failure retained at 1. Admin build passed. No new database migration needed.
