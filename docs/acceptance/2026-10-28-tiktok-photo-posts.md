# TikTok image Posts and destination format guidance

## Scope
Local development only. Preserve the production draft 53bde49c-39ca-4096-9ae2-7680ff52b2dc, connected accounts, Hub callbacks and previous delivery receipts. No publishing, provider OAuth, paid API, commit, push or deployment in this phase.

## Contract
- Shared implemented-format registry: Facebook/Instagram image Post; TikTok image Post and video; YouTube video. This is current application capability, not the full platform capability list. Legacy text-only schedule storage remains compatible and paused.
- Editor shows each selected destination's supported formats before review. Labels distinguish Image Post and Video while retaining stored Post/Reel values and old drafts.
- TikTok PHOTO uses /v2/post/publish/content/init/, DIRECT_POST, PULL_FROM_URL, one existing catalog image prepared by the established JPEG delivery path. Originals retained. Multi-photo carousel is not part of this patch.
- Fresh creator privacy/comment constraints, explicit privacy, required terms, commercial disclosure and AI label apply. Photo-specific recommended music is optional/off by default. Duet/Stitch and video duration do not appear for photos.
- MARKETING_TIKTOK_PHOTO_VERIFIED_PREFIX must explicitly name a verified HTTPS media domain or URL prefix ending in /. It must cover the actual signed /marketing/post-delivery/ URL. Empty/mismatched configuration fails before any provider request. No domain is assumed verified. Portal verification remains a deployment prerequisite.
- Persist publish_id before polling; known task refresh only, no new init. Unknown submission without a task cannot be resubmitted. Successful destinations remain immutable.

## Evidence
- Intentional initial red: photo submission rejected422 by old video-only rule.
- Latest focused regressions:49 tests,289 assertions,8.14 seconds; Http::preventStrayRequests. Includes photo payload, privacy, unverified/wrong domain, consent, disabled comments, no duplicate init, unknown submission protection, shared format boot and YouTube image rejection. Meta and existing external video regressions pass.
- node --check public/marketing-studio/posts.js passed. Scoped git diff --check passed.
- Normal localhost browser: saved existing image draft opens unchanged; updated Image Post/Video labels and pre-selection guidance render. Local DB has no connected social accounts, so TikTok-specific branch verified by fake responses rather than claiming real-account browser acceptance.
- Production not changed; actual TikTok image publishing remains pending verified media domain, explicit synchronization approval and authorized real posting test.

## Test issue ledger
- Initial red is expected and excluded from unexpected counts.
- One implementation syntax error in photo payload (missing closing bracket), corrected and linted.
- Domain fixture/root mismatch:2 failed runs, resolved by explicitly setting fixture URL root/scheme; no production bypass. A trailing slash mismatch was corrected while tracing it.
- One compatibility regression run: existing Facebook text-only saved schedule blocked by overly broad media rule; restricted validation to actual media or non-Facebook destinations. All related cases green.
- No unchanged blind reruns. Token usage unavailable.

Official reference:https://developers.tiktok.com/docs/en/content-posting-api-reference-photo-post

## Illustrated format cards
Replaced editor select with Image Post and Video / Reel illustrated buttons using existing Bootstrap icons and Studio colors. Shared capability registry disables unsupported formats for selected destinations; current media retained when switching, with immediate mismatch guidance. Legacy Post/Reel payload retained. Normal localhost browser checked selection both ways, existing image/copy retained, no save or publish. Node syntax check passed; no deployment.
