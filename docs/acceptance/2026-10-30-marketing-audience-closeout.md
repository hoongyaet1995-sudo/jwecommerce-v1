# Marketing Studio Audience milestone and remaining gates

Date: 2026-10-30. User approved page-driven Sync release. This is a bounded Audience milestone, not a claim that every Marketing channel can broadcast.

## Accepted / implemented
- Contacts from JW customer/order/enquiry/newsletter/import sources; deduplication, source provenance, channel eligibility and paid-order projection.
- CRM-style Contacts / Segments / Import / Export, full-page presets and editable rule builder; merchant/staff checks.
- Nullable customer birthday storage and derived Birthday Month rule. No birthday invented or marketing permission inferred.
- User-triggered Sync, 100-row checkpoint batches, pause on leaving/hiding, same-task resume on return/reload, interruption CTA and completion refresh. No sync queue dispatch; legacy queued sync job cannot advance it.
- Fake verification: canonical 29 backend tests; isolated exact release 29 tests / 244 assertions plus 4 frontend cases. Normal localhost original-task return/reload and completion verified.

## Release gate
- Baseline 9019bee0532ddea753d12ace0ae121335912a12a matched local, GitHub main and Hostinger before release.
- No migration/build/provider/Hub change. Allowlist source only; unrelated dirty inventory/storefront code and .env excluded.
- Exact released SHA and live receipt are appended after deployment; not inferred from local tests.

## Remaining checklist
| ID | Status | Smallest next acceptance |
| --- | --- | --- |
| AUD-R1 | Verified live | Explicit start, leave/return and refresh completed same task 1a033cce-17ed-44a3-82aa-f4ee331be267; 50 source records checked, 27 contacts before/after, no queued sync job. |
| AUD-R2 | Code released; live transfer acceptance pending | Import/export page checkpoints released in b7616349. Local fake and normal CSV preview/confirm/private download verified. Production transfer execution not repeated in that release. Legacy hosting Audience worker entry remains but cannot advance page-driven tasks; Posts/Email unchanged. |
| AUD-R3 | Deferred by user | My Account birthday input/update with validation and explicit customer action. Column and segment projection already connected. |
| AUD-R4 | Not load-tested | Large-directory MySQL scale test, per-batch timings, interruption across multiple batches and concurrent staff. Do not infer thousands-of-rows throughput from small local/live datasets. |
| AUD-R5 | Partial | Automated permission/revocation tests pass; normal production restricted-employee grant/revoke journey remains to be demonstrated with a designated employee. No production permission changes in this release. |
| EMAIL-R1 | Local bulk/settings complete; real acceptance pending | Segment snapshots, explicit confirmation, 25-recipient worker chunks, limits, records and Email Settings pass 90 tests/631 assertions locally; not released. Real bulk remains disabled. Unsubscribe code released in b7616349, but actual new-email opt-out click remains to verify. Existing local Hostinger SMTP authentication-only check succeeded without submitting mail. Sender-domain authentication, confirmed tariff, small real Segment send and authenticated bounce/complaint intake remain open. Internal fake feedback reducer is not a connected provider. |
| WA-R1 | Partial | Existing own-test-number authorization and template preparation. Live template list observed Pending approval 2026-10-30; approval is not inferred. Verify final provider state and account readiness. |
| WA-R2 | Not ready to send | Current preparation deliberately reports no verified price and can_confirm=false. Complete recipient variables, consent/opt-out, cost cap, send snapshot/queue, receipt webhooks and controlled send before broadcasts. |
| SMS-R1 | Templates only | Encoding/segment estimate and saved templates exist; sender/provider, delivery, fees, STOP/unsubscribe and controlled real send are separate work. |
| REPORT-R1 | Bounded evidence | Email test CTA -> enquiry and social website attribution evidenced. Live paid/refunded conversion and every-channel engagement analytics are not claimed complete. |

## Recommended order
1. Finish Email real readiness: SMTP authentication, sender-domain evidence, verified fee and real feedback capability; synchronize approved local bulk/settings phase before online testing.
2. Controlled real Segment send, actual unsubscribe and second-send exclusion, bounced recipient suppression and records/tracking reconciliation.
3. WhatsApp final approval, fee/consent/send/receipts; SMS verified provider and pricing later.
4. Audience load and real restricted-employee page acceptance; live Import/Export smoke and legacy worker housekeeping. Birthday account input deferred.

No broadcasts, paid API calls, purchases, permission changes or customer-list imports are authorized by this release request. Prior historical unchecked WA items should be reconciled individually against evidence, not marked all complete from this milestone.

## Live release receipt
- Code release c2eaad934aab53e3b720c24bed75619140a8253c matched canonical localhost, GitHub main and Hostinger HEAD. Hostinger ff-only pull and optimize:clear completed.
- Live authenticated Contacts start -> Segments paused at processed=21 / updated_at 05:10:39 UTC across two read-only DB observations; returning showed Continuing your audience update. Refresh continued same ID, completed at 05:11:51 UTC, 50 source records checked. Contacts remain 27, paid-order contacts 2; sync job count 0.
- New JS and existing Audience JS/CSS HTTPS return 200 and match committed LF Git blobs exactly. Local working copies use CRLF; raw-byte comparison initially differed solely from line endings, then committed-source comparison passed.
- Evidence output/audience-page-sync-live-complete.png. Initial whole-page Chrome screenshot timed out once; bounded 974x668 capture succeeded after final AX state verified. No further sync task or automatic retry was created.
- Production Cron entries preserved because Audience worker still serves import/export; Sync is page-driven and unaffected by worker. Posts/Email schedules, credentials, Hub callback and existing records unchanged.
- This evidence-only update follows code release; final exact SHA checked after documentation deployment. No migration, paid provider request, broadcast or financial transaction.

## 2026-10-30 Email Segment follow-up (local, not released)
Segment review/confirmation, encrypted snapshot, 25-recipient worker chunks, price/permission guards, suppression recheck and records now implemented and fake-tested. 84 tests / 585 assertions passed. Normal localhost free review: 1 included/2 excluded, unknown price prevents confirmation, no delivery/job. Real bulk sending disabled; authenticated provider feedback and Email Settings remain open. Store/user data should reuse existing JW sources. See 2026-10-30-email-segment-delivery plan.

## Email Settings local follow-up
Settings entry and CRM-derived single-route sender/SMTP/limits/cost/readiness page implemented. Owner-only private edits, encrypted password preservation, authentication-only gated probe, revision reset and isolated marketing mailer. Quota reservations delay only unsent work. 90 tests/631 assertions passed, normal local synthetic save verified then removed. No actual connection or send, no release. Domain and feedback remain not connected; refer to 2026-10-30-marketing-email-settings plan.

## Checklist reconciliation 2026-10-30
Corrected obsolete AUD-R2 and EMAIL-R1 table rows against b7616349 release receipt and current local Email Settings acceptance. No real-provider status was refreshed by this documentation update. WhatsApp final status must be checked live; last recorded state is Pending approval.


## Email pilot handoff — 2026-10-30

Scope: local single-mailbox Email pilot, application ceiling 1,000 attempts per rolling 24 hours. No expansion, SMTP pool, paid provider, real message, commit, push or deployment.

Implemented and rechecked: frozen Segment membership and eligible contacts; confirmation/version/permission/cost guards; 25-recipient queue chunks; durable delivery records; unsubscribe and future exclusion; unknown-result pause without resubmission; encrypted owner-only SMTP configuration; isolated marketing mailer; rolling mailbox-key quota reservations. This database cannot count other applications using the same mailbox; the provider still enforces its actual allowance.

Verification ledger:
- 2026-10-30 17:14 +08:00: fresh handoff validation, no application-code change since recorded quota acceptance. Command: C:/xampp/php/php.exe artisan test --filter='MarketingEmailBatchTest|MarketingEmailSettingsTest|MarketingEmailDeliveryTest|MarketingEmailUnsubscribeTest'. Result: 30 passed, 182 assertions, 7.16 seconds. Unexpected failures in this handoff: 0. Token usage unavailable.
- node --check for email-campaign-batches.js and email-settings.js passed. Scoped git diff --check passed (line-ending warnings only).
- Earlier normal localhost Settings save and Segment review evidence remains as recorded above; not repeated or presented as fresh browser acceptance in this handoff.

Status: local implementation ready for the next controlled acceptance phase; NOT a complete production Email deliverability closure. Bulk stays disabled until verified sender, known cost and explicit send approval. Provider-authenticated bounce/complaint intake is still absent: internal fake feedback tests do not prove a connected callback. Never label SMTP acceptance as delivered. Real send, unsubscribe from the received email, domain authentication and delivery feedback remain explicit gates. Other channel gates in the table remain unchanged.


## Hostinger bounce intake follow-up — 2026-10-30
EMAIL-R1 update: local authenticated-mailbox adapter, strict standard-DSN parsing, signed original message correlation, exact recipient/merchant match, durable50-UID checkpoint/lease and read-only owner Check Bounces action implemented. New Hostinger submissions route bounces to the SMTP login mailbox. Permanent failures suppress future sending; temporary failures have distinct delayed state and never trigger resends. Old accepted originals remain unchanged.

Fresh regression99 tests/675 assertions passed21.85s, JS/PHP syntax and scoped diff checks passed. Normal authenticated localhost page checked; screenshot output/email-bounce-settings-local.png. Real mailbox gate remains false, no email or mailbox content read. New dependency webklex/php-imap6.2.0, no PHP IMAP extension required. No migration, commit, push or deployment.

Remaining external gates: real small Segment send -> received-email opt-out -> future exclusion; real bounce correlation under Hostinger; sender-domain authentication and confirmed price. Spam complaints and positive inbox delivery confirmation remain NOT connected, not merely untested. Composer reported10 pre-existing advisories in7 packages; isolate and resolve/review before release. Detailed plan: docs/superpowers/plans/2026-10-30-email-bounce-intake.md.
