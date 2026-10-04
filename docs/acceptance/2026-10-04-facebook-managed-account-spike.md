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

## Business discovery permission release verified
- Released fbcb43bde2ef23e59bea46b2adc8bdd8c8b28e6c; localhost/GitHub main/Hostinger HEAD equal, pull fast-forward and optimize:clear succeeded.
- Read-only production authorizeUrl verification: scope pages_show_list,business_management; auth_type rerequest; callback https://ecom.jwaistudio.online/admin/social/facebook/callback. Authenticated live HTTPS new management page loaded and existing JW Studio remains Connected, checked 14:04:06 MYT. Screenshot output/playwright/facebook-business-access-release.jpg.
- Real new-account consent/retest awaits user; not claimed successful Page discovery. Clean build 9.87s; unrelated work preserved. Post-release evidence remains local.

## OAuth Page picker modal (local)
- User requested and approved continuation: replace inline pending Page picker with single-selection card dialog, one Page per connection notice, light/dark. Native dialog provides focus trap and Escape; close/reopen supported, duplicate submissions blocked, provider failures retain selection. No backend or release change. Baseline HEAD fbcb43b; unrelated inventory changes preserved.
- Validation planned: admin build and localhost browser fixture for two pending Pages, selection/POST/error/success and themes. Unexpected failure count 0; elapsed/token usage unavailable.
- Fixture load failure 1: missing Laravel Admin bootstrap in standalone fixture; added test bootstrap. Product build passed 12.84s. Next browser check uses corrected fixture; product failure count 0, fixture issue cumulative 1.
- Distinct fixture issue: unrelated Admin settings fetch received invalid empty success shape. Restrict successful fake responses to Facebook endpoints and return 404 elsewhere. Count 1 for this distinct fixture issue; no product error.
- Browser fixture verification passed: automatic modal, initial disabled confirm, switching cards leaves only second selected, simulated 422 retains selection/error, cancel/reopen, both themes visually inspected, simulated success closes modal and renders only selected Page. Two fixture setup issues resolved with evidence; no real provider grant performed. Local compiled implementation ready; production untouched. Screenshot filenames light/dark initially inverted due persisted theme (corrected).

## Connection management closure (local)
- Live read-only diagnosis: JW Studio HTTP400/code100 explicitly requires pages_read_engagement, saved grants lack it; Elmina HTTP200 matching Page ID. No secrets output. Request extra read scope, map permission failures safely, display actual attempt time; disconnected connections retain metadata with token cleared, explicit Delete only after Disconnect, merchant/super-admin guards. No global Meta revoke.
- Intentional initial TDD red planned now: scope, safe permission reason/time, ownership/role, disconnect token cleanup and delete guard. Unexpected count 0. Prior modal phase preserved; production untouched.
- Intentional red confirmed 3 missing behaviors, 8 tests passed / 74 assertions, 2.33s. Implemented minimal service error mapping/read scope/check timestamps and guarded per-Page check/disconnect/delete routes. Regression planned after new code, unexpected count 0.
- UI implemented Actions menu, per-Page check, review OAuth, Disconnect/Delete confirmation, failure reasons. Check updates only returned row; disconnect/delete consume server result. Build scheduled for new UI. Existing modal preserved.
- Regression found one introduced edit issue: broad replacement accidentally referenced account in authorizeUrl. Unexpected issue count 1 (8 failed tests share this root cause), 13.63s. Corrected exact authorize state field; rerun justified by source correction.
- Regression passed 11 tests / 88 assertions, 2.05s; build passed 13.30s (existing chunk warning). UI fixture adapted for safe disconnected/delete simulation. Next browser check verifies menu and confirmations; no real account removed.
- Browser fixture verified Actions/per-Page check timestamp, permission reason, Disconnect confirmation and disconnected state, Delete confirmation and cancel. Formal authenticated localhost page loads compiled UI. Review identified check/disconnect race; conditional update prevents in-flight provider result reactivating disconnected or newly reauthorized token. New concurrency regression scheduled; no blind rerun.
- Final verification: 12 tests / 93 assertions passed 2.28s including in-flight check vs Disconnect. Authenticated formal localhost empty account state/Connect/check button verified; real saved-account browser interactions exercised only in isolated fixture, backend persistence/guards via SQLite tests. Real MySQL connected-account and Meta regrant remain acceptance gaps, not claimed closed. Live token permissions unchanged. No GitHub/Hostinger sync authorized for this phase. Time/token usage unavailable beyond measured tests/build.

## Approved connection management release
- User approved sync of Page modal plus check/permission/disconnect/delete management. Local/GitHub baseline fbcb43b; clean HEAD archive plus two approved frontend sources built separately; unrelated inventory/storefront assets excluded. Actual Hostinger root verified before deployment. Prior 12/93 tests and local browser evidence apply. Clean build scheduled; no credentials or real provider grant changed.
- Clean archive build passed 12.94s (existing bundle-size warning). Stage only approved Facebook source/routes/tests/ledger and exact clean bundle. Unrelated inventory/storefront files remain excluded.

## Connection management release verified
- Released 47956dd90c8266931a003f5008c8817c15c3b687; local HEAD / GitHub main / Hostinger equal. Fast-forward pull and optimize:clear succeeded. Authenticated HTTPS shows Elmina Connected and JW Studio Permission required with actionable reason; Actions includes details/check/review/disconnect. Live per-Page check updated JW Studio timestamp to 15:51:27 MYT without page reload. Screenshot output/playwright/facebook-management-47956dd-live.jpg.
- Real Disconnect/Delete were not performed on customer accounts. New scope requires user Meta consent; OAuth modal remains pending real callback reacceptance. Unrelated work preserved. Post-release evidence addendum local only.

## Three-dot Actions menu (local)
- User requested screenshot-style vertical menu. Replaced text trigger with ellipsis, native auto popover floats above table clipping boundary without row growth; viewport-aware placement, outside click/Escape dismissal, keyboard menu navigation, light/dark. Existing actions/API guards preserved. Baseline 47956dd; no release approval for this phase.
- Validation planned build plus local rendered fixture menu, layout/no row growth, outside/Escape dismissal and confirmation. Unexpected failures 0; elapsed/token unavailable.
- Rendered localhost fixture passed: ellipsis opens four vertical items, row height identical before/after (115.975px), menu escapes table clipping, outside click and Escape close, focus returns to trigger, light/dark screenshots saved, Disconnect still opens existing confirmation. Product API unchanged. Final clean build result recorded separately; phase local only.
- Three-dot phase build passed 11.48s, user approved synchronization. Release scope one frontend component, ledger and clean build. Preflight SHA equality checked; unrelated changes excluded.
- Approved clean release build passed 11.37s (existing chunk warning). Stage exact clean outputs plus component and ledger; no backend/config/provider changes.
