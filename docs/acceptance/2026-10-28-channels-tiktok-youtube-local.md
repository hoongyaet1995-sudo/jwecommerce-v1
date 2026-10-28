# TikTok / YouTube Channels local acceptance — 2026-10-28

## Result
Local implementation and fake acceptance complete. Real provider authorization is NOT yet accepted. Production Hub, callbacks, accounts and environment configuration were not changed. No OAuth consent, publication, upload, paid API, commit, push or deployment.

## Delivered
- Independent connection-only Hub adapters: TikTok user.info.basic and YouTube youtube.readonly. Fixed provider endpoints and shared Hub callbacks; no publishing methods.
- Signed start / launch / callback / redeem / select flow. Actor, merchant, installation, domain, provider-app, transaction, state, browser session, expiry, and one-time handoff binding.
- YouTube channel identities are offered explicitly; the first returned channel is not silently chosen. Discovery beyond one 50-channel page fails closed rather than returning a truncated choice.
- Separate accounts upsert by merchant + platform + provider identity. Reconnect preserves other accounts and an existing same-app refresh token when the provider omits it.
- Encrypted refresh-token storage plus access/refresh expiry. Explicit Check connection refreshes near-expiry tokens once; no scheduled polling or automatic retries. Rotated credentials survive a subsequent identity probe failure. Raw provider errors are sanitized.
- Disconnect clears stored access/refresh credentials and expiry while retaining the record. Existing Meta paths and timeouts remain unchanged.
- Existing English Channels UI and CSS retained. Four-card overview still hides account names/counts; detail pages provide the existing search/Manage controls. No provider calls on GET.

## Verification
- Ecommerce focused Channels/external/transport/hosting: 31 tests / 224 assertions, 9.16 seconds, passed.
- Additional legacy FacebookManagedAccount + hosting: 18 tests / 117 assertions, 7.63 seconds, passed (hosting overlaps the focused run; do not sum as distinct tests).
- Navigation suite earlier: 3 tests included in a 23-test / 227-assertion Channels run, passed.
- Hub external provider + relay + existing Facebook/Instagram relay: 17 tests / 168 assertions, 2.50 seconds, passed.
- Node Channels interactions: 6 tests, passed; no fetch during navigation/search/Manage.
- Intentional initial red: absent external routes and provider classes. No unexpected test failures.
- Independent review found the old outer timeout shorter than a refresh + probe. External provider operations now each allow 10 seconds, caller allows 35 seconds for check / 20 seconds for other external actions; Meta remains 15 seconds. Transport fake asserts timeout and expected-app binding. Independent follow-up found no remaining blocking issue. Actual slow-network behavior was not exercised.
- Normal authenticated localhost Chrome: four entries, TikTok/YouTube detail pages, English setup-required state, preserved layout, no browser errors. No Connect/Check/Disconnect submitted. Local new provider client IDs remain unset, so Connect correctly stays disabled.
- Applied only additive local migration 2026_10_28_000001_add_marketing_channel_token_lifecycle. No accounts imported or seeded in the normal local database.

## Exact online configuration delta — approval pending
### Shared Hub
Deploy the new adapters/routes/config and allow youtube in client channel validation. Set private TIKTOK_CLIENT_ID / TIKTOK_CLIENT_SECRET and YOUTUBE_CLIENT_ID / YOUTUBE_CLIENT_SECRET only on Hub after matching the correct platform clients. Do not overwrite Meta settings or the paired installation secret.
For the existing Ecommerce client, add tiktok / youtube to enabled_channels only if absent; preserve all existing allowed channels, domain and keys. This is not applied yet. Existing Hub UI does not yet offer a YouTube checkbox; use a separately reviewed, explicit configuration update, not client recreation.

### Platform consoles
Add these exact redirect URIs, preserving old redirect entries:
- TikTok: https://social.jwaistudio.online/oauth/tiktok/callback
- Google YouTube Web OAuth client: https://social.jwaistudio.online/oauth/youtube/callback
Confirm YouTube Data API v3 enabled, consent audience/test-user readiness and the credential/client match. Current audit has not established these prerequisites. TikTok Live Login Kit must not be confused with its separate rejected API for Business profile; Direct Post approval is outside this phase.

### Ecommerce
Deploy code + additive token-lifecycle migration. Configure only public TIKTOK_CLIENT_ID and YOUTUBE_CLIENT_ID matching Hub; provider client secrets are not needed in customer installations. Keep existing Social Hub URL, client/key, registered domain, Meta configuration and callbacks unchanged.

## Next acceptance gate
After explicit synchronization/configuration approval, deploy exact approved revisions of both applications, add redirects, then user-authorized normal-page connection for one identity/channel each. Verify saved identity, second account, reconnect isolation, cancellation, explicit check/refresh and expiry notices. Do not claim real connectivity from fake tests or credential presence. Publishing remains disabled.

## Accounting and limits
Elapsed time and tokens for entire phase: unavailable. Measured test durations above only. This phase did not expand publishing, scheduling or analytics. Provider outages after a successful refresh but before any response reaches Hub/client can still require reauthorization; no blind retry is implemented. Preserve original source and unrelated dirty trees in both repositories.

References: https://developers.tiktok.com/docs/en/login-kit-manage-user-access-tokens and https://developers.google.com/youtube/data/guides/auth/server-side-web-apps
