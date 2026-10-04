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

Implementation approved by user: OAuth -> Page selection -> encrypted installation-scoped persistence -> live connection state. Initial intentional TDD red: FacebookManagedAccountTest, new migration/routes absent. No provider actions or production mutation. Command php artisan test --filter FacebookManagedAccountTest.

Environment issue E1 unexpected count1: prior managed worktree archive traversed vendor/node_modules junction targets; autoload absent. Pause product tests; restore canonical dependencies from lockfiles. No source/db deletion. Initial TDD red not yet achieved (environment failure).

E1 fixed: composer install and npm ci succeeded from locks, vendor restored and Admin377 packages installed; no manifest updates. Intentional TDD red now reattempted after concrete environment restoration, migration/routes still absent.

Fixture issue F1 unexpected count1: helper callback collided with PHPUnit final callback method. Renamed completeOAuth; initial product TDD red pending. Retry after concrete fixture fix.

Initial TDD red achieved: migration absent,4 failures expected. Implemented state binding/replay expiry, encrypted pending token and persisted accounts, live Page ID check, install owner isolation and safe errors. Green verification FacebookManagedAccountTest follows; Http fake only, not real OAuth acceptance.

Green4/34 passed2.57s. Added actor/config switch, cancellation, denied scope, revoked token vs network uncertainty regressions; verification php artisan test --filter FacebookManagedAccountTest. UI wired to native dialog with page selection and live status. No provider credentials sent or settings changed.

Fixture issue F2 unexpected count1: repeated Http::fake registers cumulative stubs; earlier success stub shadowed revoked/denied replacements. Reset HTTP Factory before changing responses, retain preventStrayRequests. Retry with concrete fixture correction.

Facebook7/57 passed2.76s, Admin build passed14.05s (combined local inventory remains excluded from later release). Local selected two Facebook private keys installed; local migration applied only managed_social_accounts. Public HTTP localhost intentionally not a valid external OAuth redirect. UI badge edit shell parser failed before writes (F3 count1); corrected with apply_patch. Live provider writes still unperformed.

Combined support/Facebook57 tests405 assertions passed6.84s. Read-only localhost connection UI renders honest no-account/unconfigured state. Meta user reports confirmation completed; fresh page still Standard Access, opening flow reveals saved Data handling draft with Hostinger processor, controller JW Studio/Malaysia, No authority disclosures, None listed processes; Submit remains pending. No declaration submitted by agent. Need exact confirmation before Submit, plus scoped deployment/new OAuth redirect authorization before real provider acceptance.

User approved exact Meta declarations, Facebook-only GitHub/Hostinger release, provided private credentials and new Ecommerce redirect registration. Meta Submit requests password reauthentication; user asked to complete privately. No credential guessed. Isolated release build14.65s; independent dependencies copied, no junction. Real OAuth closure pending.

## 2026-10-04 production checkpoint
- User completed Meta password reauthentication; public_profile Advanced Access granted observed in Chrome.
- Approved Ecommerce callback saved and observed after Meta page reload; both legacy callback entries retained.
- Local HEAD, GitHub main, Hostinger HEAD verified equal: 7cd61cee6e28f8e8484cccd2917ea56cd9a06e7e.
- Authenticated production channel modal verified: Facebook Pages section present; server reports credentials/HTTPS callback setup required.
- Credential transfer command rejected by automatic policy before execution. No secret transferred. Reason detail unavailable beyond blocked by policy. Do not retry through alternate transport.
- User asked to install selected provider settings directly in production .env; real OAuth/Page persistence acceptance remains pending. After confirmation clear config and proceed with real consent flow.
- Elapsed time and token usage: unavailable.


## Real provider acceptance completed 2026-10-04
- User confirmed production provider .env configured. optimize:clear succeeded.
- Authenticated live UI now offers Continue with Facebook. Meta showed existing JW App authorization; continued with previous settings (no new permission grant).
- Callback returned selectable JW Studio Page 1045679445296291. Selected Page and real Meta check displayed Connected.
- Reload triggered another provider check; connection persisted and last verified advanced to 2026-10-04T04:18:30+00:00.
- Read-only production DB check confirmed state connected, encrypted_at_rest=true, decrypts_successfully=true. No raw token emitted.
- Screenshot output/playwright/facebook-production-connected.png.
- Minimum OAuth -> Page selection -> encrypted persistence -> fresh live status gate passed for existing-authorized Joshua/JW Studio account. First-time external customer grant remains separate acceptance; messaging/webhooks not enabled by this phase.


## Facebook detail-view UI checkpoint
- Scope: Facebook connection region moved to second modal view with Back to channels; callback opens detail directly. API unchanged. Local only pending release approval.
- Build command npm run build at resources/admin. UI changed SupportOverviewHub.tsx. Ledger write initially used wrong cwd (unexpected failure 1), corrected absolute path without product changes.


- Build passed in measured 13.49s; existing chunk-size warning only.
- Authenticated localhost browser passed: first view has four channels only; Facebook click opens Facebook detail without platform grid; Back to channels restores four cards; close/reopen works. Light/dark screenshots verified. Local shows no saved Page; production connected Page is not copied to local.
- Screenshots output/playwright/facebook-detail-local-light.png and facebook-detail-local-dark.png. Product change is local only; await phase synchronization approval.


## Dedicated Facebook management page correction
- User clarified dedicated URL, not a modal detail. Route /admin/social/facebook uses existing authenticated Admin shell. OAuth results now return here; Facebook card navigates here, channel chooser retained.
- Existing connection region reused in FacebookAccountPage.tsx; sidebar/header route mapping added. API and token storage unchanged.
- Before validation: php artisan test --compact --filter=FacebookManagedAccountTest and npm run build. Redirect assertions updated for dedicated route. Unexpected failures this product issue 0.


- Tests passed 7 / 57 assertions. Build passed 14.29s. Browser verified navigation URL and direct reload. Dark screenshot identified missing inherited text color after section extraction; added explicit existing dark/light surface colors before next build (new visual finding, no blind rerun).


- Final build passed 13.93s after dark text fix. Browser showed correct dedicated URL, Admin shell, Facebook Pages and authorization action; dark heading/surface colors verified screenshot.
- During follow-up capture tab changed to /admin, causing region locator timeout twice (unexpected browser issue count 2; no product test failure). No repeated build/test; direct target navigation provided final visual proof. Origin of external navigation not determined. Screenshots facebook-management-page-dark.png and facebook-management-page-light.png.
- New phase remains local, not committed/pushed/deployed. Time/token accounting unavailable except measured build/test durations above.


## Approved dedicated-page release
- User approved synchronization. Clean release copy build passed 13.48s; canonical Facebook tests passed 7 / 57 in 2.65s. Diff check found one blank line whitespace issue, removed; no behavior change.
- Pre-release heads equal 7cd61cee6e28f8e8484cccd2917ea56cd9a06e7e. Only dedicated Facebook page, route navigation, redirect tests and generated admin assets included.


## Dedicated Facebook page release verified
- Released SHA 47bb4dd49b8a75ef5165d0aba18cd08edcea5c3d verified equal local HEAD, GitHub main, Hostinger HEAD.
- Hostinger fast-forward and optimize:clear succeeded. Admin manifest SHA256 equal eeeebabab412a0433b59196be05aa5f02d63614605a6bb9ffd649bc12bf704b1.
- Authenticated live HTTPS /admin/social/facebook loaded full Admin shell, JW Studio 1045679445296291 Connected with fresh provider verification. Back to Customer Support and Facebook card roundtrip passed.
- Screenshot output/playwright/facebook-management-live-release.png. Messaging remains not enabled.
- Other dirty changes preserved; this verification addendum remains local after released checkpoint.


## Approved SleekFlow-style accounts UI (local)
- 2026-10-04: user approved JW light/dark sample. Bounded presentation change, existing OAuth/API/storage preserved; only real account rows, no sample records. Manage expands provider details; connection guide describes actual flow.
- Baseline HEAD 47bb4dd49b8a75ef5165d0aba18cd08edcea5c3d; unrelated dirty changes preserved. Patch application rejected duplicate file operations once before mutation; replaced via literal file write. Unexpected tooling issue count 1, resolved.
- Validation planned: admin build, Facebook feature tests, authenticated localhost visual/interaction checks. No release approval for this phase. Elapsed/token usage unavailable.

- Validation completed: npm run build passed (9.93s, existing chunk warning); FacebookManagedAccountTest passed 7 tests / 57 assertions (2.00s). Authenticated localhost showed real empty account state, Connect Facebook action, correct guide tab content, and light/dark surfaces. Screenshot output/playwright/facebook-accounts-table-dark.jpg. Local has no saved account, so populated rows and Manage interaction remain browser-unverified; no provider reauthorization performed for this visual change.
- Existing OAuth endpoint and select handler retained; sample accounts not persisted or rendered. Source and generated admin bundle are local only, not synchronized. Unexpected product verification failures 0.

## Approved Facebook accounts table release
- User approved GitHub/Hostinger synchronization. Preflight local HEAD / GitHub main / Hostinger HEAD all 47bb4dd49b8a75ef5165d0aba18cd08edcea5c3d. Production pre-existing untracked config backups/assets preserved. Clean archive built with only accepted FacebookAccountPage.tsx and phase record added; OAuth callback route remains /admin/social/facebook.
- Canonical local feature verification already passed 7 / 57; clean release build validation recorded before commit. Scope excludes all unrelated inventory/test/preview edits.
- Clean release build passed 10.06s (existing chunk-size warning). Cleanup command rejected by automatic review; no deletion executed. Copied clean build, stage only tracked changes and clean-build filenames; other generated files excluded.

## Facebook accounts table release verified
- Released bac411fdd66a3ee8b2dcaa85fd894f5cc3380f49; local HEAD / GitHub main / Hostinger HEAD equal. Hostinger fast-forward and optimize:clear succeeded.
- Authenticated HTTPS new table displays real JW Studio Page 1045679445296291 Connected, provider check at 13:41:51 MYT. Manage expanded real granted permissions and review authorization link. Screenshot output/playwright/facebook-accounts-live-release.jpg. OAuth callback remains dedicated management URL; new OAuth grant not repeated this release.
- Phase released. Unrelated dirty files preserved; this post-release evidence addendum remains local. Time/token accounting unavailable except measured build/test timings above.

## New-account OAuth read-only diagnosis
- 2026-10-04: user authorized checking App access levels and new-account authorization outcome. Meta App 1280202060726106 UI confirmed Advanced access granted for public_profile, pages_show_list, business_management, pages_messaging, pages_manage_metadata, pages_read_engagement.
- Read-only scoped recent encrypted pending sessions: failed authorization granted only pages_show_list/public_profile with zero usable Pages; comparison successful session had business_management and other grants, two usable Pages. No token/secret printed or saved in diagnostics.
- Direct server-side read-only Meta /me/accounts replay of failed authorization: HTTP 200, no error code, raw data count 0, no pagination. Therefore not a local filtering issue or single-account limit. Missing business_management request is a verified integration gap; specific Page/Business Portfolio asset grant and user role remain unverified, so causation not closed. No code or provider setting changes made.

## Business Page discovery permission repair (local)
- User authorized completing missing permission request. Bounded fix: request pages_show_list + business_management with auth_type=rerequest; distinguish empty discovery with missing Business grant from genuinely inaccessible assets. Existing successful Page connections preserved.
- Intentional initial TDD red: FacebookManagedAccountTest new scope/rerequest and empty-list diagnostic tests, before service/controller change. Unexpected failure count 0. Baseline HEAD bac411f; unrelated work preserved.
- Intentional initial red confirmed both missing scope and generic empty-list handling (2 failed, 4 assertions, 1.69s). Service/controller and callback notices now changed; run full FacebookManagedAccountTest and admin build for regression verification.
- Regression verification passed 9 tests / 66 assertions (1.79s), admin build passed (10.59s, existing chunk-size warning). Authenticated localhost presentation of business_permission_required notice confirmed via query-state URL, not a real grant; screenshot output/playwright/facebook-business-permission-notice.jpg. Real new-account OAuth retest remains pending deployment and customer consent. Source remains local; no synchronization performed.

## Approved business discovery permission release
- User approved synchronization. Baseline local/GitHub/Hostinger bac411fdd66a3ee8b2dcaa85fd894f5cc3380f49 equal. Release scope only OAuth request scopes/rerequest, empty-list diagnosis, callback notice, tests and clean generated admin assets. Previous clean archive contains accepted Facebook table source; updated only integration notice before rebuild.
- Pre-release validation: previous local feature tests 9 / 66 and build passed; clean archive build scheduled now. Unrelated dirty changes excluded.
- Clean archive build passed; stage exact accepted source, tracked asset removals, clean-build asset filenames and phase ledger. No credentials/provider settings changed.
