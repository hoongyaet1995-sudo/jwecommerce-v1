# WhatsApp Campaign & Templates — Functional Spec v1

Date: 2026-10-29, Asia/Kuala_Lumpur. Stage: approved UI integrated into local Channels, shared Templates and Campaign draft preparation. Real OAuth/Meta submission/sending remain gated.

## 1. Product outcome and boundaries
Merchants connect their own WhatsApp business assets through JW Studio, prepare approved-template campaigns for eligible audiences, review scope/cost/time once, then follow delivery and website conversion in the existing Campaign/Analytics workspace. No customer finds or pastes Meta App ID, WABA ID, Phone Number ID or access token.

JW Studio App 1280202060726106 is the platform app. Do not import Webspert/CRM credentials, recipient PII or client identities. Each customer retains its business account, sending number, templates and billing relationship. One installation is one merchant; preserve current private Campaign ownership and explicit staff permission model. No new SaaS tenancy or all-customer shared sending number.

The current authorization intent is to design this workflow and a sample only. Live OAuth grants, credentials, number registration/migration, App Review submissions, webhook changes, real template submissions, tests to real recipients and production releases require their own concrete acceptance authorization.

## 2. Evidence and reuse (not implementation claims)
- Read-only Meta UI: JW AI Studio Social Publishing App contains WhatsApp; callback currently https://social.jwaistudio.online/webhooks/whatsapp/messages; messages and message_template_status_update subscriptions shown at v25.0. No settings changed. These observations do not prove external-business onboarding readiness.
- JW source: SocialHubOAuth / SocialHubInstallation and WhatsAppAccountService, WhatsAppSubscriptionService, WhatsAppInboundService, WhatsAppReplyService, WhatsAppStatusService. Reuse candidates; inspect actual tenant routing, signature, replay, permissions and marketing opt-in before enabling campaigns.
- CRM zip: WhatsAppSettingsService/CloudService; CampaignService draft/payload mapping; CampaignAudienceService review/schedule/unschedule; CampaignWorkerService processDueRecipients; TemplateService validation/payload; WebhookService challenge/callback processing. These are workflow references, not permission to port credentials, schemas or run legacy code.
- Existing Marketing: Campaign IDs, actor ownership, approval/version checks, queued work, analytics filters and monetary/refund rules remain shared. Website analytics consent and WhatsApp marketing opt-in are independent.

## 3. Navigation and entry points
Channels retains current four cards; add WhatsApp with same Bootstrap card, icon, concise description and Manage accounts. No account names/count summaries on the landing cards. No OAuth side effect just by opening Channels.

Channels -> WhatsApp accounts -> Connect WhatsApp -> Meta Embedded Signup -> return to account list/readiness. Multiple WABA/number identities are distinct selectable destinations; same identity reconnect updates authorization, never duplicates account or replaces another account's token.

Account connection details exclude template editing; templates are managed under Campaigns > Templates. Default UI shows business name, display number, readiness and next required action. IDs and redacted diagnostics live in restricted technical details. No editable App ID/Secret/Token form for merchants.

Campaigns retains a single Campaign container. Add content route chooses Social post or WhatsApp message. Existing Posts stays the social delivery editor and can reuse Post templates; WhatsApp gets channel-specific content setup, not a new parallel Campaign system. Audience and templates use channel subviews; no mandatory new top-level sidebar modules in v1. Account detail links to its templates; campaign editor links to recipient review.

## 4. Embedded Signup contract
The platform owns App configuration and private secret on the server/Hub. Merchant logs in to Meta, selects/creates permitted business assets and chooses/verifies a business phone number where required. System receives authorization code and asset hints, exchanges code on the server, queries and validates asset ownership/permissions before saving encrypted credentials. Never trust browser-supplied IDs alone.

Bind one-time, short-lived state to actor + merchant + provider + fixed allowlisted installation return URL. Exact state lifetime and OAuth API version are verified against existing Hub implementation during engineering audit. Validate response origin/session, prevent replay and open redirects. Credentials stay server-side; no secret in URL, sample, log, localStorage, source control or browser response.

Shared Hub callback stays intact. WhatsApp incoming/status/template webhook delivery routes to the installation owning the validated WABA/number. OAuth completion and webhook URLs are separate contracts; do not repurpose a social OAuth callback as a message endpoint.

Customer may need OTP, business information, payment setup or eligibility review. External customer onboarding needs JW App/permission/Tech Provider or supported partner-route readiness verified with Meta. Do not promise all accounts can immediately onboard. Existing Business App numbers need an explicitly supported coexistence/migration path; never deregister or replace them automatically.

Connection states: Not connected -> Connecting -> Authorizing -> Checking account -> Connected; separate Needs action / Reconnect required / Failed. Cancel retains all prior connections. An existing support account can be reused only after actor rights, App association, WABA/number and marketing capability checks; support messaging is not evidence of marketing opt-in.

## 5. Account readiness
Connected means identity/credential verification, not campaign delivery approval. Expose a derived Ready to prepare / Needs action state from authorization, number registration, account health, approved template availability, billing/rate availability and routing readiness. Show one relevant CTA: Connect, Reconnect, Review setup or Prepare campaign. Disconnected/expired/blocked account cannot be scheduled. Reconnection does not re-send old messages.

## 6. Message templates
Templates are scoped to WABA, name, language and revision/provider identity, not globally interchangeable between accounts. Choose account before selecting a template.

V1 includes list/search/filter, WhatsApp-style preview, header/body/footer/button fields, numbered variables and example values, language/category where supported, draft save, explicit submit for approval, sync/recheck status and status-specific CTA. Supported components and limits must come from the verified provider capability catalog; no arbitrary local template treated as approved.

States: Draft / Pending approval / Approved / Rejected / Paused / Disabled / Sync unavailable. Unknown provider status fails closed for sending, not shown as Approved. Rejection displays actionable reason; edits create a new draft/revision and never mutate a previously approved campaign snapshot. Ready-to-use filters expose only approved compatible templates.

UI previews use safe example values. Campaign variable mapping validates required recipient/product/link fields, media type and approved button URL constraints before approval. Dynamic tracked links must fit the provider-approved URL template; otherwise block that CTA route and offer a compatible template. AI-assisted writing is outside v1, not a reason to call a paid API.

## 7. Audience, consent and exclusions
Reuse existing customer/contact catalog where suitable; audit CRM audience workflow before schema decision. Separate channel consent source/time/purpose and revocation evidence. A purchase, enquiry, support chat or website analytics acceptance is not automatically a WhatsApp marketing subscription.

Select a saved segment or explicitly eligible contacts. Review normalized phone numbers, per-merchant deduplication, subscribed status, missing required variables, blocked/invalid destinations and frequency constraints. Explain exclusion counts; never silently send to all CRM contacts. No real contacts appear in the sample.

Freeze recipient membership and rendered message inputs on approval. Worker rechecks suppression/opt-out/account/template immediately before submission; newly opted-out recipients skipped. Contacts added to a segment later do not join an already approved campaign.

Opt-out is processed idempotently via supported webhook/user action and checked across all future campaigns for that merchant. Keep suppression separate from customer deletion. Do not infer all incoming messages mean opt-in; exact keywords/locale and evidence contract are verified during implementation.

## 8. Campaign creation, approval and schedule
Flow: choose account -> approved template -> map content/variables/website CTA -> audience review -> review time/timezone and cost -> explicit confirm -> queue -> results. Draft may be incomplete. Saving/previewing does not send, submit templates or reserve paid capacity.

Approval snapshot includes merchant/actor, account asset, template revision/language, rendered content/media, recipients, tracked-link version, timezone/UTC due time, provider route/version, verified rate source/time, estimated maximum and currency. Stale version conflicts reload for review. Invalid/missing fields, unknown price or unavailable account block the final send CTA with one actionable next step.

Price is not inferred from CRM old rates or a universal fixed number. Verify current provider billing model, category, recipient country, currency and eligibility at implementation. Sample prices are explicitly illustrative. Unknown cost stops sending; budget increase or changed route asks for a new explicit approval. No automatic charge/retry after uncertain submission.

Schedule states: Draft -> Scheduled -> Preparing -> Sending -> Completed / Completed with issues / Needs attention; Cancel scheduled stops unsent work, does not recall accepted messages. Progress updates in background. Review allowed while sending; saves affecting approved batch locked. Edits to unsent approved content require versioned new review. Local/UTC timezone and DST tested; no hidden automatic recipients or send-time substitution after approval.

## 9. Worker, webhook and recovery
Laravel queue uses per-recipient/per-approved-revision idempotency key, transactional claim/lock and immutable provider message ID. Repeat clicks, refresh or scheduler restarts cannot create another submission. Known accepted message IDs are queried/reconciled for free; response-unknown sends are paused for investigation, never automatically resent.

Webhook signature verification, expected App/asset routing, idempotency, out-of-order delivery and duplicate payloads tested. Preserve provider response/error code in restricted audit, show concise human wording and useful CTA. Failed not accepted messages may be retried only through an explicit reviewed action; already delivered recipients excluded from retry batch. New costs confirmed before execution.

Keep customer-facing status names clear; no raw queue class/SQL/provider secret. No installed queue runtime or server tool changes during design/sample.

## 10. Reporting and conversion
Shared existing Analytics: distinguish recipients, accepted submissions, delivered, read, failed, link clicks, consent-qualified website sessions, enquiries, paid orders and net attributed revenue. Read is a subset of delivered, not another successful recipient. Missing receipt/read metrics are unavailable, not zero. Denominators and as-of time visible.

Use opaque campaign/message/recipient tracking references where needed; never put phone/email in public URL. Do not claim a forwarded click proves original recipient identity or match checkout email to infer conversion. Existing seven-day last-eligible-click source snapshot, enquiry inheritance, payment verification, refund and separate-currency policy remain authoritative. Read/delivery never count as website visits or purchases.

A recipient status report is private to permitted campaign staff; general Analytics excludes contact PII. Existing WhatsApp replies link to the support inbox where authorized; no duplicate support conversation or second independent webhook registration.

## 11. Permissions and security
Separate connection management, audience access, template management, campaign preparation, final sending/budget approval and analytics grants. Existing marketing.view never implies bulk-send/spend. Owner defaults explicit; existing employees receive no automatic new send permission. Support and Marketing authorization stay purpose-specific.

Scope every read/write/export/media/webhook by installation/merchant, permitted account and Campaign ownership. Encrypt credentials, redact logs and rotate without replacing unrelated accounts. Audit template submission/approval, audience exclusions, scheduling, cancellation, sends/retries and opt-outs. Retention of business evidence and recipient suppression needs its own policy; do not reuse website-event cleanup on message/consent records.

## 12. First implementation boundary / exclusions
V1: WhatsApp Cloud API path, existing Hub, multiple numbers, approved-template campaigns, audience eligibility, review/schedule/queue/receipts, shared website conversion. Email/SMS sending, ads, calling, group messaging, automated journeys, AI generation, phone migration, global shared JW sending identity and automatic fee-increasing retries are excluded.

UI sample contains fictional data, simulated connection and queue outcomes. No real Meta login page is impersonated, no passwords/OTPs collected. No API call, mass send, template submission or paid estimate request.


## Approved navigation revision — 2026-10-29
Templates is a shared Campaigns submenu, not an account detail tab. Saved Templates first, with channel filter. Create Template opens Email / SMS / WhatsApp / Post cards. Post reuses social content authoring; saving a reusable template remains distinct from scheduling/publishing. WhatsApp templates retain WABA/language/status ownership despite the shared library location. Connection return shows saved business/number and readiness checks, never asks the merchant to reselect Meta assets. Sample only; Email/SMS transports and formal Posts migration are not implemented. Browser acceptance of this revision pending.

## Engineering checkpoint
2026-10-29: Channels entry, encrypted existing-number readout and protected Marketing Hub handoff/select foundation integrated locally. Fake receipt path verified. Local live OAuth remains blocked by registered-domain mismatch; no callback or production configuration changed. Shared template library and sending phases remain unimplemented. Latest navigation revision supersedes the original account template-tab placement.

## Local integration outcome — 2026-10-29
- Shared library URL /admin/marketing/campaigns/templates preserves existing video entry /admin/marketing/templates. Shared Campaigns navigation expands/collapses on Channels, Templates, Posts and Campaigns.
- Four types are stored, private and versioned. Email/SMS are reusable plain-text drafts, not delivery integrations. Post creates a new Draft in existing Posts with no destinations or publishing. WhatsApp uses stored business-number/WABA association, message/examples/footer/static HTTPS CTA and verified template states.
- Explicit Meta submit adapter is default-off; fake tests verify pending/unknown results, no automatic retry and remote revision match before Approved. Status checks are explicit, not browse-time probes. No live Meta template creation was performed.
- Campaign WhatsApp preparation stores messages in the shared Campaign, maps variables and reviews actual recorded subscription counts. Missing approved template/consent/verified price prevents sending. No synthetic prices, recipients or delivery metrics appear in formal pages.
- Delivery workers, consent-capture UI, current verified rate catalog, real receipt/webhook report completion remain later sending gates. No real send is authorized by local UI approval or OAuth connection alone.

## CRM editor source reuse — approved extension 2026-10-29
The supplied CRM WhatsApp editor is now the create/edit source of truth for its form sections, source classes/CSS and variable/button interaction, wrapped in the existing JW Studio shell. Source hashes/limited adaptations are recorded in output/whatsapp-crm-source-manifest.json. Credentials/backend application files were not ported.
- Separate JW internal name and permanent Meta identifier; source-supported Marketing / Utility categories; six source locale options plus legacy en compatibility.
- No/Text Header, numbered header/body variables and individual review examples; footer; up to three source-supported URL, international phone and quick-reply buttons. Dynamic URL uses only terminal {{1}} with a realistic example. These are this editor's supported subset, not all Meta capabilities.
- Laravel validates/saves every field and builds HEADER/BODY/FOOTER/BUTTONS provider payload with selected category/provider name. Template names/Meta names support 150 characters; existing data column expands only.
- Submit remains explicit/default-off pending live OAuth verification. A successful submission is Pending approval until explicit provider state/content checks match this exact revision. Unknown result cannot be resubmitted automatically. Submitted content is locked and retained; create another draft for changes. No permanent-delete action ported.
- Campaign preparation now requires Approved plus provider ID server-side and in the UI. Utility is marked unsuitable for promotional Campaigns; transaction-specific use is separate.
- JW additions: owned business-number selection, actual store sender name, accessible field labels; source dynamic-URL example visibility repaired. No real CRM/Meta submission or customer send in this local phase.

## Template list actions — 2026-10-29
Templates is a reusable-content library only. Campaign message drafts belong under their Campaign > Content > WhatsApp messages, with existing draft records retained. No draft panel in Templates.
Per-row CRM-style three-dot menu: Edit, Duplicate, Archive. Archive is a local timestamp, separate from Meta approval state, provider identity and stored content. Archived records are hidden from the default Active list, visible through Archived templates and recoverable with Restore. No Meta deletion or external action. Owner/version checks cover archive/restore/duplicate. New template use is blocked while archived. Duplicate creates an active unsubmitted Draft with a distinct provider name; source version remains intact.

## 2026-10-30 first delivery slice
Current local code now includes Review/confirm/page-continuation, encrypted batch/recipient snapshots, verified account-specific rate guards, one-attempt send reservation, Campaign status changes, existing verified webhook receipt reconciliation and inbound STOP suppression. See 2026-10-30-whatsapp-blast-implementation.md and dated acceptance appendix. Original historical draft-only statements describe prior state and are superseded only for this local slice, not production/provider readiness.
Immediate sends require page-open progression, not an unattended schedule. Body variables and fixed text header/buttons only initially; unsupported dynamic mappings block rather than sending incomplete payloads. No static approved URL rewriting or claimed per-recipient conversion tracking. Live API flag disabled, rate config empty; each real test needs concrete recipient/template/cap approval. Existing Hub callback and JW App remain unchanged.
