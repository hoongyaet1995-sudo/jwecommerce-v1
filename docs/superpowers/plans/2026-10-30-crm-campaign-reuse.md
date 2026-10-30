# CRM campaign source reuse and bounded integration

User approved reuse of supplied crm.zip rather than rebuilding existing flows. Source is plain PHP/PDO; JW uses Laravel/MySQL and existing store, audience and tracking contracts. Preserve source UI/English copy, port business rules, replace only persistence/auth/transport binding. No CRM credentials, customer rows or provider settings imported.

## Verified source reuse map
| Area | Source | Reuse / missing boundary |
| --- | --- | --- |
| Email segment snapshot/review | CampaignAudienceService, CampaignRepository, campaign_review.php | Reuse recipient preview/exclusions and frozen audience. Bind to JW segment version and suppression. Bulk dispatch not already implemented in JW. |
| Email per-recipient send/status | CampaignWorkerService, EmailTransportService | Reuse sender/render/history and list-unsubscribe headers; preserve JW unknown-result stop instead of CRM automatic retry. |
| Email unsubscribe | CampaignUnsubscribeService, campaign_unsubscribe.php | Reuse signed recipient-bound URL, store suppression, repeat-safe count and cancellation of pending recipients. GET should only show confirmation to avoid link-scanner opt-out; signed POST applies it. |
| Bounce/complaint | EmailTransportService has SMTP bounce sender only | Full receiving webhook/parser not found in ZIP application code; SMTP send exception is not a delivered/bounced receipt. Need actual sender/provider feedback capability before claiming this complete. |
| WhatsApp | WhatsAppTemplateService, WhatsAppCampaignService/AudienceService/WorkerService/WebhookService and review UI | Reuse Meta payload/variables, webhook signature, dedupe, delivery/read and STOP. Adapt shared Hub/account scoping. Audit found no campaign monetary budget/cap implementation; approval and fee sources remain external prerequisites. |
| SMS | SmsProviderSettingsService, OneWaySmsService, SmsCampaignService/AudienceService/WorkerService | Reuse actual OneWaySMS connection, balance/send/status flow and credit estimates, not arbitrary providers. JW-owned credentials and known tariffs required for real send. |
| Audience | AudienceIdentity/SyncService, SegmentRuleService and supplied UI | Already substantially ported. CRM cron must not replace approved JW page-driven processing. Large real directory performance and restricted-employee browser acceptance are tests, not features proved by copied code. |

## Source gaps deliberately not copied
- CampaignRepository recoverStaleRecipients resets interrupted processing recipients to pending (lines 909-925); transport exception retry in CampaignWorkerService may resubmit unknown accepted messages. JW keeps unknown outcomes paused until reconciled.
- campaign_unsubscribe.php calls unsubscribe on GET and POST (lines 5-20). JW uses same source result layout but GET is read-only confirmation; POST requires valid recipient/store-bound signature.
- WhatsApp status handler must retain account/install scope and handle out-of-order callbacks against JW receipts; provider message ID alone is insufficient for shared Hub routing.
- UI/source code cannot make Meta approve templates or determine a current provider tariff. No provider approval/receipt or load test is claimed from import.

## Current executable slice: source-based Email unsubscribe
1. TDD: generated links, GET no write, signed POST repeat-safe suppression, queued cancellation, expired/tampered/foreign-store rejection; no transport calls.
2. Add JW EmailUnsubscribe service, narrow signed public routes/controller and CRM-derived English result view. Existing Audience subscription/audit tables are truth; accepted delivery results retained.
3. Replace # placeholder only for newly prepared test emails; ensure every new email has a real opt-out footer and List-Unsubscribe headers. Worker rechecks suppression, does not expand sending permission or enable any feature flag.
4. Run focused Email + Audience fake suites, normal localhost synthetic preference page and update checklist. Preserve current unsynced Import/Export changes. No send, paid API, commit, push or deployment.

Remaining subsequent slices: segment bulk preparation/send records, provider-specific bounce intake, WhatsApp price/receipt integration and SMS setup; reuse mapped source and existing approved UI. These are not marked complete by the unsubscribe slice.
