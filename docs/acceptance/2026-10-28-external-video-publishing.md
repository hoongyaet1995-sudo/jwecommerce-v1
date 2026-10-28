# YouTube / TikTok publishing — phase checkpoint
User approved private YouTube / TikTok self-only first tests, existing15second video, no regeneration; then approved Ecommerce+Hub synchronization. Actual expanded OAuth consent and TikTok publishing legal acknowledgement require human action at time.

## Boundaries
Identity-only Connect unchanged. Explicit owner-only Enable publishing authorization requests identity+youtube.upload or identity+video.publish through signed purpose bound to Hub transaction. No new callbacks, app IDs, redirect domains or global default scope expansion. Existingencrypted refresh/access records retained on denial. TikTok Live app already has Content Posting API, DirectPost enabled andvideo.publish/video.upload listed; onlyvideo.publish requested here. Audit/public reach not implied.

Existing MP4 library upload: owner-scoped immutable hash, private storage, local duration verification, up to64MB/10minutes, no render/generation. YouTube explicit title/visibility/kids/altered-media choices; TikTok livecreatorinfo, no-default visibility, interactions off and disabled constraints, commercial labels, music/policy acknowledgement. Private visibility mismatch is not success. No mixed unsupported image/video routes.

Per-destination claim before init; encrypted resumable/upload URL and known task IDs persisted before bytes. YouTube status queries known session after uncertainty; TikTok status queries publish_id. No new upload/session/init on uncertain retry; byte upload once. URLs allowlisted, original source hashes rechecked. Owner-only live action. No automatic scheduled dispatch. Existing Meta image publisher unchanged.

## Evidence before release
Ecommerce focused suite41tests248assertions passed, then additional uncertainty/URL checks8videoTests37assertions passed. Final Hub relevant OAuth suite19tests178assertions passed. Final Ecommerce suite44tests263assertions passed. Normal localUI existing15second portraitMP4 uploaded and parsed, Video format selected, draft31a6c358-dcbe-484e-9020-5bca9824712e saved without provider calls. Current source syntaxes and Blade cache passed.
A broad extra Hub Marketplace OAuthRoutingTest run had5 fixture failures at absent client JSON(id null), existing Social client API requires enabled_channels, while the legacy test sends enabledMarketplace; unrelated marketplace files untouched; not reported as a passing broader suite. Do not blindly rerun or modify that unrelated subsystem. Relevant social OAuth suite passes separately.

Real scopes, private upload IDs, finalvisibility, same-SHA and callback preservation evidence to append after deployment/normal consent. Production publishing not yet claimed. No paid generation calls and no original media replacement.

Primary files/record: docs/superpowers/specs/2026-10-28-external-video-publishing.md; docs/superpowers/plans/2026-10-28-external-video-publishing.md; work/video-quality-review/merchant-readiness-20261027/ledger.md.
