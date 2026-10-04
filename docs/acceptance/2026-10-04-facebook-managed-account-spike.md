# Facebook managed account migration - first probe

## Approved purpose
JW hosts the ecommerce application and platform-level Meta App. Merchants authorize their own Facebook Pages through OAuth. App credentials do not establish merchant authorization. Do not migrate the old project's personal login accounts, change its webhook, or retire it during this probe.

## Approved ownership decision
User selected the current Ecommerce installation as one merchant; staff share its connections. Reserve an explicit merchant ownership key for later multi-merchant expansion. Never use the authorizing employee as the merchant boundary.

## Minimal first acceptance
1. Authenticated authorized administrator starts Facebook OAuth.
2. Server creates expiring one-use state bound to session, administrator and merchant.
3. Callback validates state before exchanging code; secrets and tokens remain server-side.
4. Fetch authorized Pages and granted permissions, paginate with bounded provider requests.
5. Show Page selection without exposing Page access tokens to the browser.
6. Persist selected Page token encrypted under Ecommerce's own key and ownership; prohibit cross-merchant reads/writes.
7. Reload connection status from persisted records. Report granted permissions separately from messaging readiness.
8. Verify real OAuth and selected Page with the user. Fake HTTP tests prove guards only.

## Explicit exclusions
No automatic Page webhook subscription or old webhook replacement, no customer message sending, no bulk account/history import, no TikTok/Instagram/WhatsApp implementation, and no production deployment in this probe.

## Required provider configuration
Use the supplied verified FACEBOOK_CLIENT_ID and FACEBOOK_CLIENT_SECRET through private server configuration. Define a separate Ecommerce OAuth callback; preserve old redirect/Webhook settings until cutover acceptance. The presence of a secret is not evidence that the new redirect URI is registered or that the selected merchant granted permissions.

## Source mapping
- Dashboard SocialMediaController redirectToPlatform and callback orchestration.
- Dashboard MetaProvider getPages, getGrantedPermissions and access-token proof helpers.
- Dashboard SocialAccount token mutators demonstrate encryption intent; do not copy its plaintext fallback or ciphertext between apps.
- Dashboard SocialInboundToInboxService and MetaMessagingService are later messaging phases, not part of OAuth-only acceptance.

## Current evidence
Local Dashboard code reviewed; user confirms parity with production. Supplied .env inspected without printing secret values. No credentials imported, provider settings changed or application OAuth implementation made yet. Elapsed time and token usage unavailable.

## Meta dashboard read-only inspection - 2026-10-04
- Authenticated Chrome inspection confirms App 1280202060726106 is named JW AI Studio Social Publishing, type Business. Supplied FACEBOOK_CLIENT_ID matches this ID (boolean comparison only; secrets not displayed).
- Facebook Login settings allow Client OAuth and Web OAuth; HTTPS and exact redirect matching are enforced.
- Registered callback URLs are only https://www.jwaistudio.online/admin/social/auth/facebook/callback and https://jwaistudio.online/admin/social/auth/facebook/callback. No Ecommerce callback is registered yet.
- Permissions and Features shows Advanced access granted for pages_messaging, pages_manage_metadata, pages_show_list, pages_read_engagement and business_management.
- public_profile remains Standard access / Verification required. Business Login settings explicitly warn that public_profile needs advanced access. External merchant onboarding readiness requires resolving/verifying this warning.
- Configurations list JW Studio Embedded Signup (1496764641846624) and JW Studio Embed Signup 2 (1273401224216535). Their names/IDs alone do not establish Facebook Page OAuth suitability; contents not inspected.
- Messenger appears under Available products / Set up, but pages_messaging is already Advanced access and active. Do not infer absent messaging capability solely from the product tile.
- No settings saved, permissions changed, secrets revealed, webhook switched or messages sent. OAuth connection and message delivery remain unverified in Ecommerce.
