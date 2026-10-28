# TikTok and YouTube connection audit — 2026-10-27

## Scope and evidence
Read-only source/configuration and developer-console audit. No OAuth consent, credential changes, callback changes, uploads, publishing, paid API calls or release actions. Credential presence was checked without recording secret values. No live token validity was tested.

## Current implementation
- Ecommerce SocialHubOAuth currently permits Facebook, Instagram and WhatsApp execution only. TikTok and YouTube Channels pages are planned navigation, not OAuth adapters.
- Shared Hub has reusable signed client/actor/state/transaction/TTL handoff and encrypted storage patterns. Its enabled-channel whitelist includes TikTok but not YouTube; neither platform has a working Hub OAuth provider/route.
- Marketing connection persistence lacks refresh-token and expiry lifecycle fields. Readiness is Meta-oriented and needs provider-specific handling.
- The supplied legacy environment contains TikTok production/sandbox and YouTube/Google credential variables. Presence does not establish validity, correct OAuth product or current console match. No credentials were imported.

## TikTok
Observed actual TikTok for Developers app: JW Studio Dashboard, Production / Live. Login Kit registered redirect points to the legacy www.jwaistudio.online /admin/social/auth/tiktok/callback. Shared Hub redirect is not registered there yet.
Products include Login Kit and Content Posting API; listed scopes include user.info.basic, user.info.profile, user.info.stats, video.list, video.upload and video.publish. Direct Post displays an audit application action/restrictions: unrestricted publishing approval was not established.
A separate TikTok API for Business profile is rejected. This is not the Login Kit app and must not be treated as a Login Kit rejection.
Legacy dashboard has authorization/token/user-info handling worth adapting, but requests video.upload during login. Connection phase should request user.info.basic only.

## YouTube
Google Cloud project JW Studio Dashboard (jw-studio-dashboard) contains a Web OAuth client named JW Studio Web Client. The inspected client only lists localhost:8000 / 127.0.0.1:8000 Google Drive MCP callback addresses. It is not evidence of a configured YouTube callback.
The supplied YOUTUBE credential pair was not matched to that console client. YouTube Data API enablement, consent audience/publishing status and verification remain unconfirmed. A sidebar locator failed during read-only navigation; no settings were changed.
Legacy YouTubeProvider has Google web-server OAuth, channel identity lookup and refresh-token preservation logic. Reuse the bounded logic, not its upload workflow. It currently selects the first channel; a Google login must not be assumed to identify exactly one intended channel.
Connection-only proposed scope: youtube.readonly. Do not request youtube.upload or Analytics scopes until their separate feature phase.

## Recommended next phase
1. Implement provider-specific Hub adapters and signed result exchange; extend YouTube client whitelist and encrypted token/expiry persistence.
2. Add multi-account identity-based upsert; reconnect must preserve other accounts and retain refresh tokens when omitted. Separate connection readiness from publishing readiness.
3. Fake acceptance: success/cancel/denial, invalid or expired state, replay, client/domain/actor mismatch, multiple accounts, reconnect, refresh expiry, redaction and no publishing calls.
4. Before any online changes, present exact new provider callback URLs and configuration delta. Add Hub redirects without removing legacy callbacks or altering existing Meta configuration.
5. After explicit online configuration approval and deployment, test normal-page authorization for one TikTok identity and one YouTube channel, then multiple-account isolation, refresh and cancellation. Publishing remains disabled.

## Acceptance boundary
Four navigation cards already work locally. This audit does not establish real TikTok/YouTube connectivity. No production configuration or application behavior changed. UI remains English and existing accounts/callbacks remain intact.

## Primary references
- https://developers.tiktok.com/doc/login-kit-web/
- https://developers.tiktok.com/doc/scopes-overview/
- https://developers.tiktok.com/doc/content-posting-api-get-started/
- https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps

## Accounting
Elapsed time and token usage: unavailable. No application tests run for this read-only audit. UI locator failures: Google project selector exact name (resolved using observed label), audience sidebar exact-name locator (unresolved; stopped). No blind repeated actions.
