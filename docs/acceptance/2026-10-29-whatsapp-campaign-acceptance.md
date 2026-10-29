# WhatsApp Campaign — Phased Acceptance Checklist

Date 2026-10-29. Status: SPEC WRITTEN / CLICKABLE UI SAMPLE READY FOR REVIEW. No functional phase accepted from screenshots alone.
Spec: docs/superpowers/plans/2026-10-29-whatsapp-campaign-functional-spec.md

Evidence gate: user UI -> protected Laravel service -> DB -> background/provider boundary -> persisted result -> permission/failure checks -> tests -> normal page acceptance. Initial fake transport before any explicitly approved real authorization/send. No production credential/recipient migration from crm.zip.

## P0: Spec and UI sample (current scope)
- [x] WA-00 Identify JW App and existing shared Hub WhatsApp callback/subscribed fields read-only; no changes. External onboarding readiness remains unverified.
- [x] WA-01 Functional boundaries written; no manual merchant API IDs/tokens; account connection distinct from marketing readiness.
- [x] WA-02 Existing Channels card + WhatsApp list/detail; no account details/count on landing cards, no automatic OAuth.
- [ ] WA-03 Simulated connection success/cancel/reconnect; explicit preview label, no fake credential login.
- [x] WA-04 Account-scoped template list, preview, draft editor, pending/rejected state and mapping.
- [x] WA-05 Audience eligibility/exclusions, campaign draft/review, cost and timezone confirmation.
- [ ] WA-06 Simulated schedule/results/errors; one primary CTA/state; switches, compact buttons, desktop/narrow no overflow.
- [x] WA-07 User approves sample before production code integration.

## P1: Authorization and account readiness
- [ ] WA-10 Audit JW App external-business onboarding/permissions/Embedded Signup and existing Hub implementation; list precise setup gap before any security-sensitive change.
- [ ] WA-11 Fake OAuth valid/cancel/error/replay/expired state, asset mismatch and foreign install; no secret in client or logs.
- [ ] WA-12 Real approved own-test-account OAuth returns validated WABA/number, encrypted token; customer never types IDs. Preserve other accounts/callbacks.
- [ ] WA-13 Reconnect same identity no duplicates, multiple numbers independent, revoked/expired/blocked account paused. Support-connected asset reuse purpose/permissions checked.
- [ ] WA-14 Signature-verified multi-install webhook routing and provider replay isolation verified. No outbound send required for connection acceptance.

## P2: Templates and audiences
- [ ] WA-20 Template sync preserves WABA/language/revision and provider state; unknown state cannot send.
- [ ] WA-21 Draft/preview/explicit submit status transitions, rejected reason, compatible media/variable/CTA checks; no auto-submit from Save.
- [ ] WA-22 Real template submission only on explicit authorized scope; previous approved versions preserved.
- [ ] WA-23 Eligible subscribed audience, phone normalization/dedupe, invalid/missing variable exclusions; no opt-in inferred from commerce or support.
- [ ] WA-24 Opt-out suppression/source/time, cross-campaign prevention, privacy/export permissions and repeated opt-out tested.

## P3: Campaign, price and queue (fake first)
- [ ] WA-30 Shared Campaign ownership, immutable account/template/recipient/content/link/budget snapshot and stale-version rejection.
- [ ] WA-31 Unknown/changed fee blocks sending; consent/account/template/price/permission preflight states and human CTAs tested.
- [ ] WA-32 Schedule timezone/DST, cancel unsent, save locks and automatic progress; no recipient growth after approval.
- [ ] WA-33 Two worker claims/repeated approval/restart -> at most one accepted send; unknown result stays paused, no blind retry/fee addition.
- [ ] WA-34 Provider rejected/accepted/timeout and billing boundaries use fakes; delivered recipients excluded from explicit reviewed retry.

## P4: Limited real send and shared analytics
- [ ] WA-40 Before real send list exact test recipients, content, account/template, time, price source and cap for user approval.
- [ ] WA-41 Real controlled send with one receipt per recipient; duplicates/out-of-order callbacks converge; no unauthorized recipient contacted.
- [ ] WA-42 Sent/delivered/read/failed counts agree; read subset, unknown unavailable, recipient privacy enforced.
- [ ] WA-43 Actual tracked CTA -> consent-qualified site -> enquiry or pending checkout -> isolated verified payment/refund -> same shared Campaign report; no assumed identity from forwarding.
- [ ] WA-44 Existing support inbox/routing, social Channels/Posts/Calendar, old Campaigns and payment paths regressions pass.

## P5: Release
- [ ] WA-50 Current-phase checklist updated; source scope isolated from dirty primary. Explicit sync approval, same SHA local/GitHub/Hostinger, HTTPS checks.
- [ ] WA-51 Queue/cron/runtime, rate/cost source and account readiness verified without exposing credentials; rollback plan preserves receipts and avoids duplicate sends.

Current blockers are provider/readiness to verify, not a request for customer secrets. Token/elapsed usage unavailable unless measured. No paid API or new runtime installation permitted by the sample request.


## P0 local evidence — 2026-10-29
- Isolated sample: http://127.0.0.1:8524/whatsapp-campaign-sample-20261029/#channels . Uses existing approved Channels shell and shared Bootstrap/CRM assets. All data is fictional and memory-only; reload resets it.
- Source checks: `node --check work/video-quality-review/ab-comparison/whatsapp-campaign-sample-20261029/sample.js` exit 0 after final edits. No provider requests, credentials, app routes or DB changes introduced.
- Chrome normal-page observations: fifth platform entry, business-number list, simulated Connect dialog (no login fields), Continue preview adds sample number; template status list and desktop screenshot; unavailable-price switch disables confirmation; confirmation dialog shows recipients/time/cap; simulated sending automatically updates to Completed with issues; desktop screenshots show inherited UI.
- WA-03 partial: success path observed; cancellation/reconnect UI exists but all branches not browser-verified. WA-06 partial: automatic state update observed; narrow layout and remaining form branches still need review. User approval WA-07 remains pending. No backend/Meta integration acceptance claimed.
- Issue ledger: schedule result initially rendered completed despite schedule confirmation; fixed to render Scheduled when scheduled flag is set. Browser used old cached script until explicit reload; refreshed script verified new failed/unknown separation and auto update. One stale accessibility-node click was rejected after DOM replacement; refreshed tree then successful click. These are preview/browser handling issues, not provider failures; no blind reruns. Measured elapsed and token usage: unavailable.
- Future stages stay unchecked. Real onboarding still requires verified JW App eligibility/permissions and protected Hub implementation; UI approval does not prove external customer onboarding or sending readiness.


## Approved navigation revision — 2026-10-29
Templates is a shared Campaigns submenu, not an account detail tab. Saved Templates first, with channel filter. Create Template opens Email / SMS / WhatsApp / Post cards. Post reuses social content authoring; saving a reusable template remains distinct from scheduling/publishing. WhatsApp templates retain WABA/language/status ownership despite the shared library location. Connection return shows saved business/number and readiness checks, never asks the merchant to reselect Meta assets. Sample only; Email/SMS transports and formal Posts migration are not implemented. Browser acceptance of this revision pending.

Revision browser evidence: shared Templates list visible with six sample rows and channel/status columns; Create Template modal visually checked with Email/SMS/WhatsApp/Post cards; Post opens template content editor. Node syntax check passed. Remaining editors/return-result branch and narrow viewport still need review. No real data or authorization changed.

## Sample polish — 2026-10-29
Campaigns parent now toggles its submenu without navigation or editor reset; aria-expanded and chevron update together. Shared WhatsApp preview now uses store-name chat header, incoming white bubble, tail, timestamp and attached CTA on chat background. Browser observed collapse with unchanged template URL and desktop screenshot. JavaScript syntax check exit 0. Real OAuth remains unconnected. No unexpected code-test failures; elapsed/token accounting unavailable.

## P1 implementation checkpoint
User approved polished sample and staged integration. WA-07 approved. Baseline HEAD f55b906a7a36fef746893896e55dad8c60b2b5aa; canonical tree contains extensive unrelated changes, preserved. Scope: Channels WhatsApp identity/readiness and protected Hub handoff only, no sends/subscription activation. Initial TDD red declared for MarketingWhatsAppChannelsTest missing routes. Command: php artisan test --filter=MarketingWhatsAppChannelsTest; purpose entry and access boundary. Elapsed/tokens unavailable.

## P1 checkpoint — local Channels foundation (not full P1 closure)
- Implemented formal fifth Channels entry and WhatsApp number list/detail using existing encrypted ManagedSocialAccount identities, no credential copying/CRM import. Existing numbers visible, no browse-time provider probe.
- Added protected Marketing authorize/select/cancel endpoints. Reuses existing Hub launch and existing /admin/social/whatsapp/hub-return callback; Marketing intent returns to Marketing. One-time pending grant and asset/token verification reused; no direct Meta fallback.
- Saving Marketing connection does not activate subscriptions or enqueue/send. Optional preserveInbound mode retains existing support subscription on reconnect; default legacy behavior remains unchanged. Marketing disconnect/check mutations intentionally not exposed yet.
- Fake acceptance: initial declared red was expected missing-route 404. After changes MarketingWhatsAppChannelsTest passed 3 / 21 assertions, including signed mocked launch/redeem/select, encrypted persistence, no subscription activation, replay rejection. Combined existing Channels/WhatsApp/Hub suites passed 30 / 200 before final preserve-subscription test. After adding that test focused suites passed 8 / 37. All HTTP fake/preventStrayRequests; Queue fake. Node syntax check passed. Unexpected failure count 0; intentional red excluded. Elapsed/token usage unavailable.
- Normal localhost Chrome: /admin/marketing/channels/whatsapp loaded authenticated, showed two existing numbers and Manage detail with Number connected / Campaign sending not enabled. No Connect/check/send pressed. Local connect is disabled by existing Hub registered-domain mismatch; stop real OAuth, do not weaken allowlist or change production config.
- Remaining P1: live external onboarding eligibility; full security negative matrix and routing receipt regression; explicit live authorization on approved matching domain. WA-10..14 remain incomplete until these gates pass.
- Templates library/navigation/editor, audiences, cost approval, worker, sending and delivery/conversion remain later phases; sample approval does not claim those are integrated. No commit/push/deploy or real messages/API costs in this checkpoint.

## Local unified Templates integration run
Approved complete local integration before sync. Implementation plan: docs/superpowers/plans/2026-10-29-whatsapp-local-integration.md. Initial TDD red declared: MarketingContentTemplatesTest, new routes not yet present. Existing dirty work preserved. No live send/paid API/sync.

Templates test ledger: initial expected route red observed; first implementation run had one unexpected assertion mismatch (unavailable account returned 404 instead of form error 422); account lookup changed to generic 422, next run 4 tests/12 assertions passed. Cumulative unresolved count 0, historical unexpected 1. New provider/Posts/page branches added before next run. No unchanged blind retries.

Provider test expansion: one fixture setup failure group (required permissions/tasks omitted) corrected; next run exposed a separate schema assumption: ManagedSocialAccount IDs are integers, not MarketingChannelConnection UUIDs. Corrected template account column/validation and added non-destructive local type alignment migration. These are distinct root causes, one failed run each; no escalation threshold reached.

Checkpoint: templates test expansion passed 11 tests / 51 assertions after fixing Query Builder firstWhere misuse and replacing a stale Http fake between provider phases. Both distinct issues failed one run each, resolved. A new JS preparation form had an escaped-newline syntax error; node check diagnosed, corrected, then passed. No affected issue has three failures. No provider HTTP escaped the fake test boundary.

Broad regression diagnosis: existing video template entry already owns GET /admin/marketing/templates; reusing it hid its route name and broke existing navigation boot. One broad failed run plus one focused diagnostic confirmed RouteNotFoundException marketing.templates.entry (same root cause, cumulative 2). Shared messaging library moved to /admin/marketing/campaigns/templates; old video route preserved. API /templates/data remains non-conflicting. Separate staff bootstrap 503 is the known existing Admin build-assets gate, not changed here. Next run verifies route correction.

## Local integration acceptance checkpoint — ready for bounded OAuth sync review
Local migrations applied: 060000 content templates, 060100 account-ID alignment, 061000 WhatsApp preparation/subscription storage. No old records removed; no credentials or callback changes.

Implemented and observed:
- Channels fifth entry, preserved two existing numbers; shared expandable Campaigns > Templates navigation.
- Saved library search/channel filter, four-type chooser, stored draft/version, owned account/WABA association, example preview with actual ShopBrand name. No inherited sample data or fixed quote.
- Real browser created `local_acceptance_collection` WhatsApp draft, saved, refreshed and retained number/body/examples. Created `Local acceptance · Social post`, then Use in post opened existing Posts editor with same text, status Draft and no accounts. No Review/Publish clicked.
- Browser created one WhatsApp message draft attached to existing `Local browser acceptance · quote · 2026-10-29` Campaign, reviewed actual 0 eligible / 0 excluded contacts and missing-template/consent/price blockers; sending disabled.
- Meta template submit/status adapter tested using fake HTTP only; default submission flag false. Existing support subscription retained on Marketing reconnect.

Verification commands/results (unique suite matrix, not sum of repeated runs):
- MarketingContentTemplatesTest latest: 12 passed / 56 assertions (output/whatsapp-template-final-tests.txt).
- MarketingChannelsTest + MarketingCampaignTest + MarketingWhatsAppChannelsTest: 22 passed / 138 assertions (part of output/whatsapp-integration-regression.txt; that file additionally contains prior 11 templates tests).
- MarketingPostsTest + WhatsAppAccountTest + SocialHubOAuthTest: 25 passed / 152 assertions (output/whatsapp-legacy-regression.txt).
- Unique matrix total: 59 passed / 346 assertions. PHP lint on new controllers/services/routes and node syntax on changed scripts passed. Scoped git diff --check passed.
- Broader StaffMarketingPermissionTest bootstrap surface returned 503; not counted as passing and not repaired in this WhatsApp scope. Its permission/security assertions otherwise passed in that broader attempt. Full Admin asset readiness is an outstanding environment gate, not claimed closed.
- One browser observation timed out during Posts navigation; tab inventory then showed the completed destination and fresh AX confirmed original Posts draft. No duplicate action or retry submission occurred.
- Account ID selected value initially lost due numeric/string DOM comparison; normalized comparison and normal browser refresh confirmed retained selection. New JS newline parse error corrected before browser execution. Distinct issues recorded above, none reach unresolved 3-failure threshold.

Outstanding/live gates: matching-domain real OAuth on ecom after explicit sync approval; signed Hub/prod app eligibility; actual Meta template creation/status round-trip when separately approved; real marketing opt-in capture, rates/budget approval, queue/receipts/send and conversion tests remain unchecked. Local preview prices/results are not ported into production. No actual sends, paid AI, commit/push/deploy. Elapsed/token usage unavailable.

## CRM editor fidelity extension
User explicitly requests direct reuse of supplied CRM WhatsApp create/edit editor. Read-only live source inspected, no CRM form submitted. Scope: source DOM/CSS/JS, JW branding, business number selector; separate internal/provider names, Marketing/Utility, text header, numbered variables/examples, URL/phone/quick-reply buttons (source supported limit 3), approval panel. Initial TDD red declared for new category/header/buttons contract. No actual Meta submission or release.

CRM reuse test checkpoint: new contract tests passed 2/10. Full template suite initially hit two existing submission fixtures ending in a bare variable; fixtures updated with fixed suffix to satisfy newly adopted submit-only CRM validation, next run 14/66 passed. Browser source editor shows Utility, separate names and Add Variable creating {{1}} plus example. Found source JS dynamic URL field visibility did not update while typing; fixed visibility on existing example input, preserved structure. Added accessible labels/icon attributes. One screenshot observation timeout; no external submission. Two locator naming misses resolved using observed IDs/AX, not action retries.

CRM fidelity closure (local scope):
- Read-only CRM live form inspected. Source form DOM/classes, CSS and input interactions copied from supplied archive with source hashes. No CRM writes.
- Browser created `CRM acceptance order update`, Utility / en_US / text Header `Order update`, BODY variable {{1}} with example AB123, dynamic website URL + example, phone and Quick Reply buttons. Save Draft returned edit version; settings restored. Final Approval panel shows Draft / Not submitted; no Submit to Meta pressed. Store name is sender preview.
- The duplicate source icon glyphs were hidden from accessibility, variable/button labels associated with inputs, and dynamic URL example visibility fixed. Source form remains recognizable; integration-specific number selector/JW branding/preserve-version actions are intentional.
- Final MarketingContentTemplatesTest: 15 passed / 70 assertions (output/whatsapp-crm-final-tests.txt), including full payload mapping, supported bounds and rejection of unapproved Campaign template use. JS syntax checks passed for templates.js and both copied/adapted editor scripts. New expanding-name migration applied locally.
- Submitted/approved live Meta state and actual template approval remain pending OAuth/explicit real submission phase. Permanent deletion not ported. Source screenshot observation initially timed out once; later normal screenshot/AX successfully captured source-style button rows and approval card.

## Template list refinement
User approved removing campaign-draft panel from Templates (preserve records and move access to Campaign content), CRM-style Edit/Duplicate/Archive menu. Initial TDD red declared for versioned archive/restore preserving Meta state; no permanent delete or provider action.

Template list actions acceptance:
- Templates campaign-draft panel removed; corresponding persisted drafts returned in owned Campaign boot and rendered in Campaign Content > WhatsApp messages. No draft rows deleted.
- CRM-style three-dot actions tested in Chrome: menu displays Edit Template / Duplicate Template / Archive Template without clipping. Duplicate created independent acceptance copy cc16a127-3e67-497c-8051-62f2ccc0d974, with original body/category/buttons retained and provider approval reset to Draft. Only that new copy archived; original three active templates retained. Archived filter displays copy as Archived; Restore available.
- Initial intentional missing-route TDD red observed. After implementation archive test passed 1/9, then MarketingContentTemplatesTest + MarketingCampaignTest passed 22/111 (output/whatsapp-template-actions-tests.txt). Covers ownership, version conflict, provider-state retention, active/unapproved duplicate, no HTTP/send/queue. No unexpected failures in this refinement.
- Local archive column migration 063000 applied. Scoped diff check and JS syntax checks passed. No Meta calls, messages, commit/push/deploy. Elapsed/token accounting unavailable.

## Approved synchronization checkpoint
- User approved GitHub and Hostinger synchronization followed by real WhatsApp OAuth acceptance on 2026-10-29.
- Scope: Channels connection handoff, shared template library and approved template editor/actions; no message sending or actual template submission.
- Existing Hub callback and connected accounts are retained.
- Release verification: scoped isolated fake-provider tests, syntax checks, exact commit and live HTTPS/OAuth checks.


- Release isolated test checkpoint: first run blocked by missing test APP_KEY (environment issue, one unexpected run). Added ephemeral test-only key; no production credentials copied. Re-run purpose: validate exact isolated release code.

- Second isolated run: 49 test behaviors passed; one OAuth fixture used default localhost against registered 127.0.0.1. Explicitly bound test app.url to fixture domain; application domain protection unchanged. Next run verifies corrected isolated fixture. Cumulative domain-fixture failure count: 1.

- Isolated fixture diagnostic: runtime APP_URL must be present before application bootstrap. Explicit testing APP_URL fixed signed handoff (1 test / 14 assertions). Two failed runs for fixture-domain environment, resolved; no app security change. Final matrix runs with test-only APP_KEY and matching APP_URL.

- Final exact-release matrix: 50 tests / 284 assertions passed, no risky tests; scoped PHP/JS syntax and diff whitespace checks passed. No real provider generation, message sending or template submission.
