# Hostinger Email Bounce Intake Implementation Plan

**Goal:** Read standard delivery-status notifications from the configured Hostinger mailbox without resending or modifying mail.
**Architecture:** Webklex pure-PHP IMAP adapter, read-only bounded mailbox scan with durable UID checkpoint; MIME DSN parser; authenticated opaque per-delivery token and exact recipient correlation; existing feedback/suppression service. Complaints remain not connected. No deployment or real mailbox access in this phase.
**Approved design:** User approved adding the receiving dependency and implementing Hostinger IMAP bounce intake. Existing SMTP identity is reused, secrets remain encrypted. Only owner can explicitly check; no cron added. Host fixed to imap.hostinger.com:993 TLS with certificate validation. Real reads gated off by default.

- [x] Add Webklex dependency without updating unrelated packages or running project scripts.
- [x] TDD: fixture DSNs, negative correlation/security cases, duplicate/temporary/permanent behavior; owner and disabled gate; transport fake with checkpoint, bounded calls and safe errors.
- [x] Add signed message identifier to newly submitted emails. Do not change original stored HTML/media or old versions. Old uncorrelated mail remains unchanged.
- [x] Add DSN parser and matching service, mailbox adapter and checkpoint storage. No mailbox mutations; locks and duplicate protection; safe terminal status and suppression.
- [x] Add owner-only Check Bounces action to existing Email Settings, honest separate delivery/complaint status, no new design.
- [x] Run focused regression tests, JS/lint and local render checks; update acceptance record. No real message, real mailbox access, commit, push or deployment.

## Ledger
Initial intentional TDD red will demonstrate missing bounce intake behavior. Unexpected failures tracked here. Token usage unavailable. Before each test run record purpose and changed evidence; no blind reruns.

Pre-run: intentional initial TDD red, new MarketingEmailBounceTest proves missing classes/endpoints. Dependency added webklex/php-imap 6.2.0 only; no other package versions changed. Composer audit reports 10 advisories in 7 pre-existing packages; no new Webklex advisory reported. Security upgrades require a separate scoped phase before production release.

Initial TDD red: 5 missing-feature failures expected. Implemented parser, signed Message-ID, pure PHP readonly adapter, StoreSetting mailbox checkpoint/lease and owner route. First green run follows; fake adapters only, real gate remains false.

First green: 5 tests /36 assertions, 4.13s passed. Next intentional red: missing envelope return-path linkage in Mailable. Protocol fake and hard/soft ordering checks added alongside it. No real network.

Envelope red observed null Return-Path as expected. Added Hostinger SMTP login as return address for new sends; signed Message-ID stored before submission. Existing accepted snapshots and original HTML retained. UI now uses existing cards/button patterns with owner-only explicit checks; claims neither delivered nor complaint connected. Full focused suite next.

38 tests/231 assertions passed (7.20s). Review found temporary DSN was labelled Bounced by the existing reducer. Add intentional red for a distinct delayed state; no suppression/retry. Added MIME and concurrent/version guards, then validate new state.

Delayed-state red observed as expected. Reducer and batch UI now distinguish Delivery delayed; late temporary reports cannot downgrade permanent bounce. Next run expands to Email/Audience/Campaign/Template compatibility; all transports fake.

## Completion evidence (local implementation only)
- Latest regression: 99 tests / 675 assertions passed, 21.85 seconds. Purpose: Email/Audience/Campaign/Template compatibility after delayed-state and MIME/concurrency guards. Command: C:/xampp/php/php.exe artisan test --filter='MarketingEmailBounceTest|MarketingEmailBatchTest|MarketingEmailSettingsTest|MarketingEmailDeliveryTest|MarketingAudience|MarketingCampaign|MarketingContentTemplate'. No unexpected failures; intentional TDD reds recorded above.
- JS syntax (email-settings.js and email-campaign-batches.js), PHP syntax for the three intake services, and scoped diff whitespace checks passed. No frontend build needed for served plain JS/Blade.
- Authenticated normal localhost Email Settings refreshed and inspected: English Bounce Checks section, disabled Check Bounces while live gate is false, distinct Spam complaints/Inbox delivery confirmation Not connected. Screenshot: output/email-bounce-settings-local.png. No form submitted and no real mailbox read.
- No migration needed: encrypted existing SMTP settings and StoreSetting checkpoint/lease are reused. Only new Webklex 6.2.0 package added to lockfile; existing package versions unchanged.

## Operational boundaries and next live gate
- Enable MARKETING_EMAIL_BOUNCE_ENABLED only for explicitly approved real acceptance after release. Save the owner SMTP identity; IMAP uses the same Hostinger login over certificate-verified TLS993. No credentials returned to browser; connection errors are redacted.
- Owner clicks Check Bounces. Each request examines INBOX read-only and scans at most 50 UID positions with an additional time budget. Headers are limited64KiB and report bodies1MiB. Larger messages are skipped visibly. Continue Bounce Check resumes the durable cursor; no cron or new hosted worker added.
- No Seen/Deleted/Move/Expunge operations. Ordinary mail bodies are not fetched; only multipart/report bodies are parsed. Raw mailbox content is not persisted or returned to UI. Signed original Message-ID plus exact recipient and merchant are mandatory. Never guess from subject or address alone.
- Only newly submitted mail carries the saved signed identifier and Hostinger return mailbox. Historical uncorrelated mail, unsupported DSNs and reports outside INBOX are not claimed covered. Sender/provider rewriting of Message-ID/Return-Path must be checked in real acceptance; absence of a bounce never means delivered.
- Hard bounce suppresses future campaigns and blocks unsent queued deliveries. Soft delays are recorded as Delivery delayed without resending or suppression; late soft/delivered events cannot downgrade a hard bounce. Duplicate events do not duplicate suppression/records.
- Real provider complaints remain unavailable. This feature does not connect spam complaints or positive delivery confirmations. An authenticated provider adapter is still required for those capabilities.
- Composer audit found10 advisories affecting7 existing packages; new Webklex package has no reported advisory in that result. A separate security upgrade/review is required before production release; do not call the dependency tree clean.
- No real mail sent/read, paid API, commit, push, deploy, production credential or Hub callback change. Token usage unavailable.
