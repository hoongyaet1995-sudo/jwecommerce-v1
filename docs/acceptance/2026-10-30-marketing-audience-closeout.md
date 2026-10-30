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
| AUD-R2 | Partial | Import/export retain queue worker execution. Local CSV preview/confirm and private export passed; normal production CSV/import/download not verified in this release. Decide page-driven preparation before removing dedicated Audience worker. Sync itself no longer requires that worker. |
| AUD-R3 | Deferred by user | My Account birthday input/update with validation and explicit customer action. Column and segment projection already connected. |
| AUD-R4 | Not load-tested | Large-directory MySQL scale test, per-batch timings, interruption across multiple batches and concurrent staff. Do not infer thousands-of-rows throughput from small local/live datasets. |
| AUD-R5 | Partial | Automated permission/revocation tests pass; normal production restricted-employee grant/revoke journey remains to be demonstrated with a designated employee. No production permission changes in this release. |
| EMAIL-R1 | Not implemented for bulk | Email currently explicit single-recipient SMTP test. Build immutable segment recipient snapshot, send-time eligibility, working unsubscribe, bounce/complaint suppression, bounded sending/rate limits and delivery records before bulk use. Existing test substitutes unsubscribe with #; do not advertise that as production unsubscribe. |
| WA-R1 | Partial | Existing own-test-number authorization and template preparation. Live template list observed Pending approval 2026-10-30; approval is not inferred. Verify final provider state and account readiness. |
| WA-R2 | Not ready to send | Current preparation deliberately reports no verified price and can_confirm=false. Complete recipient variables, consent/opt-out, cost cap, send snapshot/queue, receipt webhooks and controlled send before broadcasts. |
| SMS-R1 | Templates only | Encoding/segment estimate and saved templates exist; sender/provider, delivery, fees, STOP/unsubscribe and controlled real send are separate work. |
| REPORT-R1 | Bounded evidence | Email test CTA -> enquiry and social website attribution evidenced. Live paid/refunded conversion and every-channel engagement analytics are not claimed complete. |

## Recommended order
1. Finish production Sync acceptance; close this milestone without expanding scope.
2. Unify Import/Export preparation with a portable execution strategy if eliminating per-client Audience Cron is required.
3. Implement Email segment sending with real unsubscribe/suppression and small approved test recipients; then WhatsApp price/template/receipt gates. SMS provider later.

No broadcasts, paid API calls, purchases, permission changes or customer-list imports are authorized by this release request. Prior historical unchecked WA items should be reconciled individually against evidence, not marked all complete from this milestone.

## Live release receipt
- Code release c2eaad934aab53e3b720c24bed75619140a8253c matched canonical localhost, GitHub main and Hostinger HEAD. Hostinger ff-only pull and optimize:clear completed.
- Live authenticated Contacts start -> Segments paused at processed=21 / updated_at 05:10:39 UTC across two read-only DB observations; returning showed Continuing your audience update. Refresh continued same ID, completed at 05:11:51 UTC, 50 source records checked. Contacts remain 27, paid-order contacts 2; sync job count 0.
- New JS and existing Audience JS/CSS HTTPS return 200 and match committed LF Git blobs exactly. Local working copies use CRLF; raw-byte comparison initially differed solely from line endings, then committed-source comparison passed.
- Evidence output/audience-page-sync-live-complete.png. Initial whole-page Chrome screenshot timed out once; bounded 974x668 capture succeeded after final AX state verified. No further sync task or automatic retry was created.
- Production Cron entries preserved because Audience worker still serves import/export; Sync is page-driven and unaffected by worker. Posts/Email schedules, credentials, Hub callback and existing records unchanged.
- This evidence-only update follows code release; final exact SHA checked after documentation deployment. No migration, paid provider request, broadcast or financial transaction.
