# WhatsApp Cloud API via Social Hub — design

Date: 2026-10-06 (Asia/Kuala_Lumpur)
Status: user approved design and inline plan execution; local implementation verified, release/cutover and real provider acceptance pending.

## Purpose and approved boundary
JW Ecommerce replaces the legacy JW Studio WhatsApp customer support. User explicitly permits interruption of legacy receipt during cutover. Client-facing management follows Facebook/Instagram patterns. Social Hub permits new OAuth and relays verified provider events; Ecommerce owns encrypted tokens, subscriptions and full customer support. Pause affects new OAuth only, never existing messaging feature control.

## Current evidence
2026-10-06 implementation update: reference token is valid and belongs to App 1280202060726106 with WhatsApp messaging/management scopes. The production phone belongs to the configured WABA and reports CONNECTED/CLOUD_API. It is imported only into local encrypted storage. Meta contains Embedded Signup configuration 1496764641846624; the supplied environment had no usable configuration ID. Native SDK launch, state binding, signed redemption/selection and verified phone routing are implemented and tested with fixtures. Actual HTTPS signup remains a live acceptance gate. No production callback, subscription, registration PIN or billing configuration was changed.

New phone numbers must complete Meta registration; a pending/unregistered phone cannot become Ready merely because the WABA is subscribed. Existing registered numbers are not re-registered by this implementation.

The following paragraph records the initial discovery before verification:
Legacy www.jwaistudio.online/admin/social/accounts shows merchant-managed JW Studio sender Ready to use. Its /admin/whatsapp is a module launcher. Local legacy code calls Meta Graph phone-number/messages endpoints and has onboarding, template and inbound support. Supplied reference .env has production WABA, phone number ID, token and verification token, but no Embedded Signup config ID or redirect URI. These values have not been imported or printed. Live token validity, owning Meta App and supported signup configuration need read-only verification.

Existing Ecom ManagedSocialAccount encrypts access_token. MessengerInboundService supports only Facebook/Instagram event structures; it must not parse WhatsApp as a Page event. Hub Instagram webhook verifies raw-body HMAC and signed tenant relay and rejects ambiguous routing. Reuse protocol and support facilities, with a dedicated WhatsApp adapter.

## Approach comparison
1. Recommended: migrate existing sender into Ecom encrypted storage for first real receipt/reply acceptance, then enable native Meta Embedded Signup through Hub for customer-owned WABAs. Existing sender import is a developer bootstrap, not claimed as new OAuth.
2. Use a fresh test sender for all initial acceptance: avoids changing old sender, but delays replacement and user already authorizes cutover.
3. Keep old application as message relay: introduces permanent dependency on the system being replaced; rejected.

## Accounts and authorization
Protected /admin/social/whatsapp management page uses existing account table, tabs, dialogs and Toast patterns with WhatsApp green/icon. Data from authenticated backend, no fabricated readiness. Super-admin may authorize/disconnect; staff can view and reply according to existing support permissions. Check Hub whatsapp capability before allowing new signup; deny paused/unregistered client.

Bootstrap existing WABA/phone identity and token only from approved server configuration, with explicit account binding, encrypted credentials and unique tenant/platform/phone association. No secrets in JS/API/logs. Bootstrap cannot bypass future customer's OAuth permission gating.

Native customer signup requires verified Meta Embedded Signup configuration and permissions. Hub creates expiring, single-use state bound to client, actor and installation fingerprint. Only registered return URLs allowed. Ecom launch -> Hub -> native Meta signup -> Hub server code exchange and authorized asset discovery -> one-time signed redemption by Ecom -> select owned phone(s). Selected account registered in Hub routing; selected token encrypted in client. Missing native signup configuration shows setup required, no generic Facebook Page flow substituted.

## Model and provider adapters
Account identity: platform=whatsapp, provider_account_id=phone_number_id, WABA ID as explicit metadata/column, app ID, display number/name, grant and health states. Preserve existing Facebook/Instagram identity semantics. Add dedicated WhatsAppAccountService, WhatsAppInboundService and WhatsAppReplyService; share support conversation/message/notification facilities, not Page token APIs. Migrations add only missing fields/indexes based on schema inspection.

## Webhook and relay
Planned public Hub endpoint: /webhooks/whatsapp/messages. GET challenge uses configured verification token. POST validates X-Hub-Signature-256 over original body using actual owning app secret before parsing whatsapp_business_account payload. Route each change by value.metadata.phone_number_id and authorized WABA/client mapping; no guessing or cross-tenant fallback. Ambiguous route fails closed.

Reuse signed Hub -> client protocol with replay protection, body limits and no redirects. Expose a client receiver for WhatsApp envelope. Provider retries and client retries deduplicate by tenant/phone/provider message ID. Unknown account/status does not create conversations or fabricate acceptance. Relay outage returns retryable failure. OAuth pause does not block known-account webhook relay.

## Support workflow and outbound
Incoming supported text creates one message/conversation, reopens appropriate archived state, marks unread and emits one bell alert with WhatsApp icon. UI sender identity distinguishes customer phone/authorized business number. Unsupported media gets explicit unsupported-message behavior, not silent fake text.

AI/human uses existing planner and takeover protections; enqueue after durable inbound commit. Staff/AI sends via phone_number_id/messages with client encrypted token. Enforce actual current WhatsApp customer service policy from provider docs; outside allowed free-form window show template required. Initial acceptance is text only; template authoring/catalog/broadcast excluded.

Store outbound provider message ID; accepted is not delivered. Process sent/delivered/read/failed callbacks idempotently without regressing terminal status from stale events. Unknown send outcome must be reconciled before any resend. No unbounded retries or duplicate AI replies. Disconnect revokes local use/routing; preserve conversation history.

## Cutover and release
1. Local source and schema first; tests/build/browser and read-only provider account checks.
2. Complete native configuration discovery and existing sender bootstrap; preparation must not be called OAuth success.
3. Ask explicit synchronization approval, then GitHub/Hostinger exact SHA for Hub and Ecom.
4. Register Hub route for sender before provider webhook switch. Use actual owning Meta App. Retain old endpoint/config as rollback evidence; do not log tokens.
5. Switch provider callback to Hub under user's confirmed cutover authorization; browser policy may require action-time confirmation for security-sensitive callback save.
6. Real customer inbound -> Hub -> Ecom stored support message + bell -> human reply -> native WhatsApp recipient + status evidence. Verify AI separately; pause test confirms only new OAuth blocked.

## Validation gates
Tests: non-admin signup denied; Hub denied unavailable paused capability; expired/tampered/replayed states; tenant identity isolation; encrypted token serialization; bad provider signature; unknown/ambiguous phone mapping; relay failure; repeated message/status idempotency; AI/human collision; free-form policy boundary; token expiry; failed/accepted/delivered distinctions. Keep existing Facebook/Instagram tests passing. Local UI: light/dark management, Toast, selection and callback notices; real provider test required after release.

## Discovery blockers and non-goals
Provider product page currently loads shell but not detailed configuration in connected browser. Read-only discovery must confirm app ownership, WABA/phone ID, current token validity/scopes, native signup config and webhook settings before implementation contracts are finalized. No replacement of official consent with simulated consent. No phone migration, billing setup, broadcasts, catalog or template builder in this phase. No reuse of hardcoded legacy Graph v19.0 without current verification.
