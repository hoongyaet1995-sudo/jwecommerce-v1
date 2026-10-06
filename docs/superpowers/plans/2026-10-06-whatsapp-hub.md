# WhatsApp Hub Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task in this chat. Steps use checkbox syntax for tracking. No delegation is selected.

**Goal:** Replace legacy WhatsApp support with real Cloud API text messaging through Social Hub and Ecommerce, with native customer authorization.

**Architecture:** Hub verifies and routes WhatsApp events and gates new signup. Dedicated client adapters bind encrypted credentials and reuse support conversation, alerts and AI/human facilities. Existing sender bootstrap precedes native customer signup; neither is simulated acceptance.

**Tech Stack:** Laravel, PHP, MySQL, existing React/Tailwind Admin, Meta Graph Cloud API.

**Spec:** C:/xampp/htdocs/jwstudio-ecommerce/docs/superpowers/specs/2026-10-06-whatsapp-hub-design.md

## Current checkpoint
Local code is implemented and locally verified. Full live signup, production relay and real WhatsApp delivery remain Task 6 release gates. Unchecked test checklist lines contain broader provider/browser evidence requirements and are not represented as fully accepted by fixtures. See docs/acceptance/2026-10-06-whatsapp-local-checkpoint.md.

## Global Constraints
- Develop application code first in C:/xampp/htdocs/jwstudio-ecommerce; Hub counterpart in C:/xampp/htdocs/jw-social-hub. Preserve dirty source and unrelated generated assets.
- Hub pause affects new OAuth only; no messaging feature control.
- Secrets remain encrypted server-side and never printed, committed or sent to the browser.
- Existing sender bootstrap is not OAuth success. No production readiness from fixtures.
- Preserve existing UI patterns; no redesign.
- Native WhatsApp policy/version/permissions must be verified before provider contract implementation.
- No commit/push/deploy before explicit synchronization approval; repository delivery contract overrides skill frequent-commit defaults.
- Keep one issue ledger in docs/acceptance/2026-10-06-whatsapp-implementation.md, including timestamps, commands, changed evidence and cumulative failures; 3/6/9 escalation and immediate auth blocker pause apply.

## Review Focus
- Wrong owning Meta App: reject rather than using Facebook/Instagram app secret by assumption; Task 1/3 tests.
- Same phone claimed by multiple clients: fail closed and do not deliver to either tenant; Task 3 tests.
- Status delivered precedes sent callback: no state regression; Task 4 tests.
- Staff takeover during AI generation: suppress stale AI send, not merely change UI badge; Task 4 tests.
- Network timeout after provider acceptance: no blind duplicate retry; Task 4 tests.

## Task 1: Account ownership and encrypted bootstrap
Files: config/services.php; app/Models/ManagedSocialAccount.php; new app/Services/Social/WhatsAppAccountService.php; new app/Console/Commands/WhatsAppImportLegacy.php; migration 2026_10_06_110000_add_whatsapp_account_metadata.php only if schema requires it; tests/Feature/WhatsAppAccountTest.php.
Interface: WhatsAppAccountService::list(): array; ::importLegacy(array $serverValues, User $actor): ManagedSocialAccount; ::check(ManagedSocialAccount $account): array. No token in summaries. Console command whatsapp:import-legacy reads approved server config, never command-line secret arguments; idempotent identity binding.
- [x] Inspect legacy sender and provider ownership using read-only native UI/API; inspect actual managed account/support schemas and existing test helpers. Record only non-secret evidence. Confirm supported Graph version; stop provider-dependent actions on token/permission blocker.
- [x] Write failing tests: encrypted credential at rest, summary omits secrets, duplicate import idempotent, conflicting merchant binding rejected, missing owning app or mismatched phone/WABA cannot be Ready.
- [x] Run intentional red: php artisan test --filter=WhatsAppAccountTest.
- [x] Implement account adapter/config metadata and command; do not reuse legacy fallback across tenants.
- [x] Rerun focused test, record result and missing real-provider gates.

## Task 2: Management and native signup
Client files: new Admin/WhatsAppAccountController.php; resources/admin/src/components/support/WhatsAppAccountPage.tsx; integration/whatsappAccounts.ts; App.tsx; types/index.ts; integration/adminRoutes.ts; AdminHeader.tsx; AdminSidebar.tsx; SupportOverviewHub.tsx; SocialHubOAuth.php; routes/web.php. Hub: new WhatsAppOAuthController.php and Services/Social/WhatsAppOAuthProvider.php; routes/web.php; provider config; existing transaction/routing tables extended only when native signup schema requires it.
Tests: WhatsAppAccountPage.test.tsx, tests/Feature/WhatsAppOAuthTest.php in both apps.
- [ ] Verify actual Meta Embedded Signup configuration, authorized scopes, session completion/code handoff and permitted return origin. Record distinct bootstrap readiness and native signup availability. Do not implement invented Graph OAuth URLs.
- [ ] Write failing tests for manager-only signup, denied/paused Hub, actor/client/installation-bound one-time state, expired/replayed/tampered redemption, approved phone selection and cross-tenant rejection.
- [ ] Run intentional reds in both apps; implement minimal native signup state/code exchange and signed redemption matching verified provider contract. Hub transaction token encrypted and promptly purged after redemption.
- [x] Reuse Facebook/Instagram management layout with WhatsApp colors/icon, existing Toast for all callback/failure notices, native signup unavailable state, real accounts/check/disconnect/delete. Bootstrap appears as migrated existing sender, not user OAuth.
- [ ] Tests: route deep link, callback Toast, disabled unauthorized setup, account selection and staff read-only behavior. Run focused tests/build; localhost browser light/dark.

## Task 3: Signed Hub receipt and client ingestion
Hub files: new WhatsAppWebhookController.php; Services/Social/SocialDelivery.php::relayWhatsApp(object $client,array $payload); routes/web.php; config/services.php. Client: SocialHubReceiverController.php::whatsapp(Request); new Services/Social/WhatsAppInboundService.php::receive(array $payload): int; signed receiver route. Receipt migration only if current receipt table cannot safely scope phone/platform IDs.
Tests: hub tests/Feature/WhatsAppWebhookTest.php; client tests/Feature/WhatsAppInboundTest.php and SocialHubConnectivityTest.php.
- [ ] Write red tests: bad/missing raw-body signature, payload limit, mismatched WABA/phone, unknown account, ambiguous routing, mixed-tenant batched entries, invalid relay signature/replay and downstream outage.
- [x] Implement exact /webhooks/whatsapp/messages GET/POST on Hub; map by metadata.phone_number_id and authorized client asset. Relay /api/social/hub/whatsapp with signed envelope; reuse authenticated receipt protocol and OAuth-independent messaging delivery.
- [x] Implement dedicated WhatsApp text parser, transactional account lock and durable tenant/phone/message dedupe. Create support message/unread/alert once and dispatch AI after persistence. No Page event parser reuse.
- [ ] Test duplicated/reordered webhook, malformed text, empty/unsupported media, disconnected account and paused-OAuth existing account. Fixtures assert persisted counts and routing; do not call them live acceptance.

## Task 4: Staff/AI send and delivery status
Files: new WhatsAppReplyService.php; ProcessWhatsAppSupportReply.php; existing support send dispatcher and AI/human guards; SupportMessageAlertService.php; support channel type/icon mapping; WhatsApp status receiver integration; support delivery metadata migration only when needed. Tests/Feature/WhatsAppReplyTest.php, WhatsAppSupportTest.php; frontend channel tests.
- [ ] Write red tests for token expiry, service-window boundary from verified provider policy, template-required error, takeover collision, timeout unknown outcome, provider error and malformed acceptance.
- [x] Implement phone_number_id/messages text sends with encrypted client token. Store provider ID and accepted state, not delivered.
- [x] Process sent/delivered/read/failed callbacks idempotently, bounded error-safe logging and no stale regression. Unknown send outcome remains uncertain; reconcile rather than blind resend.
- [x] Reuse support AI planner and human takeover, WhatsApp channel icon in list/header/bell and deep links/read behavior matching Storefront. No broad support refactor.
- [x] Verify database producer/service/consumer permissions/failures; preserve Facebook/Instagram tests.

## Task 5: Local closure and review
- [x] Run affected backend and frontend suites once code gates pass, then production build. New failure requires ledger entry/change before rerun.
- [ ] Authenticated localhost verify management and callback Toast, mocked replay count and failed/delivered distinction, bell/unread/human takeover. Do not label local fixtures as provider success.
- [ ] Review scope against approved spec and check no unrelated source/assets or secrets in diff. Maintain clean accepted release artifact separate from legacy dirty generated files.
- [ ] Report locally completed scope and ask synchronization approval for both repositories. No deployment under design approval alone.

## Task 6: Approved release, cutover and real acceptance
- [ ] After sync approval, commit/push accepted source and deploy exact SHA of each app; migrations and live HTTPS verified. Record both SHAs.
- [ ] Import existing sender into encrypted production account and register Hub tenant mapping before callback switch. Verify configuration readiness; no old phone re-registration/billing action.
- [ ] Preserve old callback/token configuration for rollback, without printing it. Existing user permits old project receipt interruption. Ask action-time confirmation only if browser security-sensitive save requires it.
- [ ] Set actual owning Meta App WhatsApp callback to https://social.jwaistudio.online/webhooks/whatsapp/messages and verify subscription field.
- [ ] User-authorized test recipient sends real message; prove Hub signed relay, Ecom stored conversation, notification, human reply, provider status and native WhatsApp delivery. Authorize test messages before sending through UI/API; no unsolicited recipient.
- [ ] Verify AI separately and pause-new-OAuth behavior with existing messaging retained. Record rollback if failed real safety gate; no false release-complete claim.

## Execution method
Recommended: inline execution by the current agent with superpowers:executing-plans, task by task. User reviews this plan before product code begins. Provider blockers pause only affected work; independent local implementation/tests may continue.
