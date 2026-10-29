## Current milestone status — 2026-10-29

This summary supersedes historical checkbox snapshots below; historical test/release records are retained.

| Deliverable | Current state | Evidence / remaining boundary |
| --- | --- | --- |
| Campaign, Posts association and immutable tracked links | Verified implementation; prior release online | Real Facebook test post -> website -> enquiry -> quotation acceptance -> unpaid order |
| Purchase/payment/refund attribution and boundary safeguards | Isolated acceptance passed; released | 85 tests / 564 assertions; no real financial transfer required or performed |
| Three-tab conversion reports, unified icon events and record drilldown | Local accepted implementation; NOT deployed | 36 tests / 281 assertions, real local enquiry read and isolated paid/refund UI |
| Tracking preference and visitor retention | Local implementation and tests; normal PDP preference entry verified | Choice expiry 180d (session-scoped); events 90d, raw redirects 7d; local prune dry-run only (0 events / 0 retirements) |
| Financial and enquiry evidence | Preserved | Retention removes expired visitor events and retires correlation keys; does not delete orders, enquiries, payment/refund/source facts |
| Next release | Requires separate explicit sync approval | Local reports + privacy changes have no new commit/push/deployment |

Current operational boundaries: collection stays disabled locally. Consent rejection stops new optional visitor attribution and clears current context; existing commerce facts stay available. The Privacy choices dialog opens only on user request; first-time enabled collection uses a nonblocking banner, not a forced modal. No global default-consent change has been approved. Eligibility of a default-on analytics mode depends on deployment purpose/jurisdiction and is not declared here.

Deferred, not blockers to Email/WhatsApp design: cohort conversion rates, cross-device matching, external-site tracking, shared-bio post-level inference, platform engagement metrics and advanced ROI. Future channel adapter/recipient/template/send acceptance belongs to the next phase; it is not marked complete.

Outstanding environment observation: actual local homepage returns 503 / Storefront page assets unavailable; normal product page is 200 and privacy controls are present. Do not equate this unrelated homepage asset issue with privacy functionality passing there. It remains a separate storefront acceptance gap; no unrelated build/source changes made.

Next phase after this scoped closeout: audit crm.zip for reusable Email/WhatsApp campaign, audience/consent, templates, worker/delivery and reporting boundaries. No recipients, credentials or sending are imported automatically.

---

# Campaigns, Attribution & Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Do not delegate unless separately authorized. Steps use checkbox syntax for tracking.

**Goal:** Integrate the approved CRM-style Campaign and Analytics UI with first-party tracked website/checkout activity and verified order evidence.
**Architecture:** Reuse existing Posts/Calendar/Channels, Laravel/MySQL payment and refund truth. New scoped campaign/link/event/order-snapshot services feed one reporting query layer. A durable reconciliation path isolates analytics failures from commerce.
**Tech Stack:** Laravel, PHP, MySQL, Blade, existing Bootstrap/JS; no new rendering tools.
**Spec:** docs/superpowers/plans/2026-10-28-campaign-attribution-analytics-functional-spec.md
**Audit:** docs/acceptance/2026-10-29-campaign-integration-readiness.md

## Global constraints

- Develop first in C:/xampp/htdocs/jwstudio-ecommerce; preserve unrelated dirty work.
- Use corrected approved sample UI and English copy; remove paper-plane graphic, use Bootstrap tabs.
- No real publishing, paid API, charge, credential/callback changes, commit/push/deploy without separate authorization.
- No single-product branches. Existing projects/posts/media/receipts retained.
- 7-day last eligible click, first-touch evidence retained, order snapshot frozen, no automatic historical attribution.
- Default tracking off until consent/money gates pass; future Email/SMS/WhatsApp planned only.
- Each test run recorded with change/evidence, result and cumulative unexpected issue count; 3/6/9 escalation per AGENTS. No blind reruns.

## Review focus

- Two employees sharing store accounts must not gain access to private Posts/orders.
- A fresh payment callback after browser closure or a repeated callback must yield exactly one paid-order conversion.
- Base cents, display currency, settlement amount and quotation money must never be confused.
- Rejected consent, bot requests and shared bio links must not become fabricated human/post-level conversions.
- Updated campaigns, content, destination URLs and refund states must not rewrite prior attribution evidence.

## Planned file responsibilities (verify exact existing signatures before editing)

New models/migrations: MarketingCampaign, MarketingTrackingLink, MarketingAnalyticsEvent, MarketingOrderAttribution, MarketingAnalyticsOutbox. Add nullable campaign_id to MarketingPost without rewriting legacy documents. Keep unique keys on merchant/order primary attribution, merchant/event deduplication, merchant/refund event; indexes on merchant/date/campaign/post/destination. Money uses integer minor units, not floats. Attribution snapshot includes policy version and observed click timestamp.

New services under app/Services/Marketing/: MarketingCampaigns (CRUD/ownership/association), MarketingTrackingLinks (immutable link versions/allowlisted destination), MarketingAttribution (consent-qualified touch/session/order capture), MarketingOrderEvidence (payment/refund normalization), MarketingAnalytics (shared filtered queries), MarketingAnalyticsOutbox (durable reconciliation). These names are proposed contracts, not existing files.

Controllers: Admin/MarketingCampaignController, Admin/MarketingAnalyticsController, public MarketingTrackingController. Views and static assets port approved sample into marketing/campaigns and marketing/analytics, reuse shell. New consent adapter configuration/UI must be scoped to optional analytics and not replace checkout/session security. Add domain permissions and existing employee grant UI support.

### Task 1: Campaign persistence and approved UI

- [ ] Add failing Campaign feature tests: owner/store access, grant isolation, CRUD/version conflict, archive history, no send side effect, standalone legacy Posts.
- [x] Add migration/models/services/controller routes and permission mapping. Preserve existing staff grants; only explicit new grants enable new sensitive capabilities.
- [x] Port list/create/detail/content tabs from corrected sample. Replace mock data and duplicate editors with normal existing Post links.
- [ ] Persist optional campaign association through MarketingPosts normalization and approval snapshot. Prevent historical link reassignment and incompatible queued mutation.
- [ ] Verify CA-01..03,16,19; normal local browser create/reload/search/associate and view-only denial. No publishing.

### Task 2: Consent-qualified destination links and checkout snapshot

- [ ] Add failing tests for redirect tamper/open redirect, merchant scope, query/fragment preservation, rejected consent, bot/raw distinction, guests, refresh deduplication, seven-day boundaries and two-tab source isolation.
- [~] Implement first-party consent adapter and tracking opt-in surface using existing UI patterns. Defaults off. Keep operational routing possible without optional analytics identifiers.
- [~] Generate link versions only for supported placements and approved destination content. Bind them before publication approval; never substitute worker-side after consent snapshot.
- [~] Persist signed server-managed context and immutable order source in CheckoutService transaction via explicit validated context, not arbitrary request payload.
- [~] Add deduplicated begin_checkout at actual checkout initiation using a durable checkout attempt ID. Inventory/payment behavior stays unchanged.
- [ ] Verify CA-04,06,10..14,18 in tests and normal local storefront browser. Fake fixtures only.

### Task 3: Authoritative paid/refund evidence and durable reconciliation

- [ ] Add failing tests for verified webhook, proof approval, authorized offline transition, simulated/sandbox exclusion, duplicate/delayed callback, fulfilled paid order, both refund paths and repeated partial refunds.
- [ ] Implement money mapper with base minor units/base currency, historical rate and provider evidence. Verify hosted gateway payloads and quotation exception; unverified amount/currency remains excluded with reconciliation reason.
- [~] Add durable analytics outbox evidence atomically with relevant commerce transitions; worker failure cannot undo valid checkout/payment. Retry only idempotent analytics work, not provider money requests.
- [~] Reconcile PaymentWebhookService direct paid update, CheckoutService paid transition, manual proof, Admin refund and ReturnRequestService refund. One order primary conversion; each refund applied once.
- [ ] Verify CA-05..12,15; no real charge/refund and no unrelated stock mutation. Exercise existing checkout/payment/proof/returns regression suites once changed.

### Task 4: Real-data Analytics and exports

- [ ] Add failing aggregate/filter tests covering period timezone, cohort denominator, incomplete windows, partial/full refund, multiple currencies, no permissions and missing platform data.
- [~] Implement shared query/filter DTO for Overview, Campaign Performance, Revenue & Conversion and CSV. Aggregate by indexed dimensions; paginate detail rows and stream bounded export with CSV injection protection.
- [~] Port approved CRM cards/chart/tables to those data contracts. Display policy/as-of/unavailable states. Restrict order drilldown separately and avoid customer PII in general Analytics.
- [x] Bind filtered report navigation from Campaign details. No new report calculation per page. Future channel identifier accepted by contract without active sender.
- [ ] Verify CA-15..20, UI desktop/narrow screenshots and existing Posts/Calendar/Channels navigation.

### Task 5: Phase acceptance and handoff

- [ ] Run focused new tests and relevant existing regression suites with providers faked/stray HTTP blocked. Document expected TDD reds separately from unexpected failures.
- [ ] Normal local browser: create Campaign -> associate draft -> distinct link -> consent-qualified landing -> checkout -> fake verified payment -> report -> refund -> report adjustment. Refresh/repeat to prove no duplicates.
- [ ] Prove tracking-disabled journey still checks out normally; prove no old orders/posts changed. Compare screenshots with corrected sample, including Bootstrap tabs.
- [ ] Update stable acceptance IDs with evidence; report remaining provider-dependent limitations. Do not equate sample approval with functional closure.
- [ ] Ask for synchronization only after coherent local phase accepted; exact-SHA local/GitHub/Hostinger + HTTPS checks on authorized release.

## Not implemented / not measured

Implementation is in progress. Consult the dated checkpoint and ledger for measured tests/browser evidence; token usage is unavailable. Completed code is not full phase acceptance. UI sample has illustrative chart shapes and in-memory state, which must not be copied as analytics calculations or persistence.

## Approved event-catalog addition / next execution order (2026-10-29)

- [~] Map canonical event catalog from spec section 12 to existing local kinds without rewriting history; add semantic producer tests first.
- [~] Add consent-qualified page_view/view_item and authoritative cart mutation events; inspect actual cart APIs before selecting integration points.
- [~] Add quotation sent/accepted analytics producer events referencing existing revisions/acceptances, not public page views.
- [~] Add objective selection and separate buy-online/quote report funnels using actual events and cohort definitions; preserve approved UI.
- [ ] Complete fake-response/simulated payment regression, then normal local storefront and Admin acceptance for both paths; no real charges or sends.
- [x] Resume browser gate after normal localhost Admin login. No release until separately approved.

## Progress checkpoint — 2026-10-29

Legend: [x] named deliverable completed with recorded evidence; [~] partial/code present but not fully accepted; [ ] open. Task headers are not marked complete until all acceptance gates pass.

Completed evidence: Campaign model/migration/routes/services, new explicit campaign/analytics grants and staff UI labels, corrected CRM-style formal list/create/detail/content screens, real database-backed Analytics and CSV query, page/item/cart/quote-stage producer scaffolding, enquiry-to-order source inheritance and local payment/refund evidence reconciliation. Local MySQL migrations completed without deleting original records. Browser created/reloaded test campaign 01a185a2-ebbf-7250-939f-ece189d2e729 and associated labelled local draft 858f21b2-150a-4688-a5ea-6cd1d60741b1. Formal Analytics Campaign/date filters and disabled/empty income states observed.

Test evidence: focused 30 tests/290 assertions and latest 11/62 passed; earlier broad run 93 of 95 tests passed, two physical-return inventory assertions also fail on untouched release baseline. Admin build passed. These are distinct runs, not additive coverage totals.

Next: guest redirect/consent/expiry/refusal/page/quote source tests and privacy/session correctness, followed by controlled local collection UI proof. Real social account/link preparation happy path needs a genuine connected local account or later separately approved online verification. Never create a fictitious connected social account to bypass that requirement. No release yet.

## Next-stage attribution boundary checkpoint

- [x] Automated guest-route checks cover explicit consent, revocation, invalid/expired signed source, redirect host/query/fragment preservation and duplicate arrival delivery.
- [x] A new visit begins after 30 minutes of inactivity; browsing does not renew the original seven-day click attribution window. Report visit totals deduplicate session keys across links.
- [x] Known crawler/link-preview user agents do not create qualified arrivals or page events; arrival response reports the actual collection result. This is a heuristic exclusion, not proof that every bot can be identified.
- [~] Full normal-browser landing-to-checkout/quote and verified conversion acceptance remains open. Local collection stays disabled; previously verified Admin empty states are not conversion evidence.

Latest focused verification: MarketingAttributionJourneyTest, MarketingAttributionTest, MarketingQuoteAttributionTest and MarketingAnalyticsTest passed: 11 tests / 86 assertions, 4.517 seconds. No provider requests, paid API, publication, commit, push or deployment. Next gate: controlled guest storefront acceptance and remaining privacy/payment/cohort cases; genuine local-account link preparation remains separate.

## Visitor purchase / request-quote acceptance — 2026-10-29

- [x] Isolated route-level simulation: signed landing -> consent -> checkout -> pending order -> fake verified bank evidence -> one paid conversion; repeated reconciliation does not duplicate. Quote receipt/repeated request key and refusal still permit legitimate submissions without false sales. Providers blocked, mail faked.
- [x] Normal local browser: localhost guest followed labelled acceptance link, accepted optional analytics, viewed existing buy product, added one item, completed normal checkout with bank transfer selected but no transfer/proof. Order 32 (JW-20261029035919-5573) remains pending_payment; attribution remains pending and revenue/paid orders remain zero.
- [x] Normal local browser: second labelled link -> existing quote product -> request form -> receipt ENQ-20261029-WPRTKUP7ME, enquiry 2c4be940-9a4c-48ac-b51f-75128238faa0; refresh persisted receipt and source. No quote sent or real contact. Admin shows one quote request and zero paid orders. Same guest session across both campaigns counts one overall session, each campaign has its own qualified arrival.
- [x] MySQL interrupted-DDL repair: restored missing event dedupe unique index; six duplicate arrivals preserved as duplicate_legacy with original identity/canonical reference, not erased. Fresh-schema and interrupted-schema regression both covered.
- [~] Link fixtures were deliberately prepared for the local acceptance consumer, not obtained from a real social account/post publication. Genuine-account preparation gate remains open. Browser did not simulate a paid production order; paid/refund evidence remains isolated-test proof.

Final regression: 30 tests / 328 assertions passed in 8.951s. Prior run's disabled-state test inherited temporary local enablement; fixed explicit test config, recorded in ledger. APP_URL and optional tracking/mail settings restored; collection disabled again. Existing data retained, no provider call, publication, real payment, commit/push/deployment. Readbacks: output/campaign-conversion/browser-readback.json and final-check.json. Remaining scope: consent retention/revocation UI, multiple-tab/cohort/report/provider edge coverage and genuine account-link acceptance.

## Saved-channel tracking preparation — 2026-10-29

- [x] Local implementation uses saved MarketingChannelConnection identities, same-business ownership, current identity/app/expiry state and supported Facebook/YouTube destination. Preparing a draft link does not request extra publishing scopes, refresh OAuth or call providers. Instagram/TikTok explicitly remain unavailable for post-level website links.
- [x] Draft-only mutation requires both Campaign-management and Post-edit permission and expected Post version; rejects foreign, unselected, disconnected/expired and unsupported accounts. Selected-account copy receives its own link; shared copy and other accounts remain unchanged. Length overflow fails atomically.
- [x] Repeated preparation reuses the saved link without another row/version increment. Changed campaign landing creates a new immutable link snapshot while preserving the old row; stale requests and locked/Scheduled posts cannot mutate preparation.
- [x] Posts draft save preserves server-owned campaign link/context metadata, ignoring client-forged metadata. Existing account tokens/connections remain untouched.
- [x] Isolated integration: fake Hub select receipt -> stored channel -> fake identity check -> real Posts draft route -> Campaign assignment -> link -> Posts readback. No production OAuth receipt or connected-account fixture inserted into local MySQL.
- [x] Browser fake responses use formal Blade/JS/CSS: Facebook prepare, reuse, separate YouTube link and unsupported Instagram notice; compact existing Bootstrap buttons, Review in Posts CTA, visible in-dialog failure message and restored button. Fixture is explicitly labelled Local UI test / Fake responses; not production-account evidence. Fixture cross-origin icon font rendering is not formal-page fidelity evidence.

Verification: 60 tests / 416 assertions passed in 10.855s across preparation, Campaign, Posts, Channels, external channels and prior conversion/attribution/schema cases. PHP and JavaScript syntax checks and scoped diff whitespace passed. Subsequent JS-only failure feedback/button restoration verified in browser. All initial TDD reds and fixture/UI diagnostics are recorded in the durable ledger; no blind reruns.

Remaining gate: normal page with a genuinely connected local account, or a separately approved deployed environment, to prepare an actual account link without publishing. No live OAuth, callback change, paid API, real payment, publication, commit, push or deployment. Optional visitor collection remains disabled. This closes local fake-response preparation scope only; full Campaign/Analytics operational acceptance remains partial.

## Real Facebook publication and website conversion — 2026-10-29

User explicitly approved synchronization, live test-account publication, and conversion acceptance. Scoped code release 6a7f48961cac5bf6f9451429a9d80e31cf08e67d was verified at canonical localhost HEAD, GitHub main and Hostinger; additive five Marketing migrations succeeded, live campaigns.js returned HTTP 200 and formal pages loaded. Production MARKETING_ATTRIBUTION_ENABLED enabled per approval; local collection stays disabled. Existing accounts and Hub callbacks retained.

- [x] Normal production UI: Campaign 01a185f0-732f-7121-aaab-79fbc207180d (TEST · Facebook website conversion · 29 Oct), quote destination, selected real connected Facebook JW Studio account 01a17d42-0f60-706d-904a-652a2a8bf025. Draft bb13c068-2293-454d-bf73-a3c9b6080479 uses existing container image and English TEST-only copy. Website links prepared de99e501-9195-4e62-9642-50944ebe596d into account-specific text. No manual API bypass for preparing/publishing.
- [x] One approved immediate publication accepted by normal Posts page and completed by background worker, no duplicate submission. Public post: https://www.facebook.com/61578497026372/posts/122132479754949900/ . Old posts untouched. Earlier unsaved editor tab was closed before saving; replacement draft was saved before further steps, not a duplicate publication.
- [x] Anonymous in-app browser opened the actual public post, dismissed Facebook login solicitation, clicked its outbound CTA, landed on production PDP with signed jw_ref, consented to optional analytics, and submitted the normal Request quote form. Enquiry 8e2477d8-286e-43ad-9008-edf15cf7c515 / ENQ-20261029-4LTZLFI8H6. Persisted source joins the exact Campaign, link, post and real Facebook destination. One qualified session, page/item view and quote request; raw crawler/redirect requests remain separate from human counts.
- [x] Normal enquiry Admin prepared clearly labelled non-commercial RM1 test quotation QT-20261029-JUD4EGUE (no goods/payment/fulfilment), published its immutable version to that enquiry only; UI confirms no email is sent. Original guest opened/accepted it and created unpaid manual-fulfilment order 26 / JW-Q-20261029-LCLVZWWQFT. Order inherited the enquiry source under enquiry-source-v1, total 100 minor MYR, state pending; no automatic stock reservation.
- [x] Refresh retained receipt/order; live Analytics confirmed one quote request, one quotation sent, one accepted, zero paid orders. No bank transfer, receipt upload, true payment or simulated paid mutation was made on production. Paid amounts correctly remain excluded.
- [~] Direct-buy online path unavailable because production active catalog contains zero buy-mode products. Existing purchase modes were not altered. Paid purchase/refund correctness remains isolated-test proof, not a real online payment claim. Generic local direct-buy route already tested; full paid production gate is not marked complete.

Evidence: output/campaign-release/live-facebook-post.png, live-enquiry-receipt.png, live-conversion-stages.png, live-quote-readback.json, live-order-readback.json. Screenshots inspected; original test records remain. Core local suite 60 tests/416 assertions; scoped release build 13.29s with known asset/chunk advisories. No paid generation API or Sol. UI issue observed outside attribution scope: account tab label includes [object Object]; it did not change recipient/content or block the test and remains a follow-up.

Current verdict: real-account link preparation, Facebook publication/click, consent-qualified request_quote and enquiry-to-unpaid-order source inheritance are verified. Full Campaign/Analytics phase remains partial for other acceptance gates, direct-buy production prerequisites and live paid-payment evidence. Do not represent pending test order as revenue.


## Local closure of priority 1 and 2 � 2026-10-29 (not released)

Scope approved: direct-buy/payment/refund correctness and attribution boundaries. Existing UI, original products, connected accounts, callback configuration and live pending quote order are preserved.

- [x] Generic normal checkout creates pending attribution. Private fake proof upload alone remains unpaid; unauthorized staff review is denied; authorized normal review creates auditable verified evidence and one paid conversion after reconciliation.
- [x] Isolated Stripe signed delayed webhook works after browser session removal and ten days delay; replay and repeated reconciliation do not duplicate purchase. Stripe/PayPal/Billplz normalized evidence shapes are checked. Wrong provider/environment, invalid amount/currency, unverified evidence and sandbox payments are excluded. Bank evidence requires recorded verification time.
- [x] Completed partial/full refunds adjust refunded amount once; pending refunds do not reduce revenue. Original paid-order count and gross amount remain historical facts; net equals gross less confirmed refunds. A cancelled fully refunded order has zero net, not a second negative purchase.
- [x] Same-second A -> B and reverse arrival/replayed old tabs select by signed server click sequence. First and last touch remain ordered. Tracking POST session writes are serialized. Direct returns preserve eligible source; checkout freezes source and existing order cannot be reassigned by later clicks.
- [x] Exactly seven days is eligible; beyond seven days remains unattributed without blocking checkout. Staff/admin activity cannot convert prior guest context. Late payment uses frozen order source and matching receipt time, not an unrelated receipt.

Code changed: MarketingTrackingController, MarketingAttribution, MarketingOrderEvidence and three optional tracking routes. No payment-gateway charge/refund operations, inventory logic or UI redesign added.

Verification: 85 tests / 564 assertions passed in 9.320s (focused Campaign, tracking, Posts, Channels, purchase/quote, Analytics and payment-proof routes). Earlier initial TDD reds are recorded in the durable ledger; no unexpected failures in this checkpoint. All payment proof/provider evidence is isolated test data. No real payment, new publication, paid API, commit, push or deployment. This checkpoint is automated local integration evidence, not a fresh normal-browser or live paid-payment acceptance claim.

Owner-report next milestone: clearly show website visits -> quote requests / checkout -> paid orders, attributed gross revenue, confirmed refunds and net revenue; breakdown by Campaign/post/account and date/currency, with source drill-down and permission checks. Current report is activity-based, not a cohort conversion-rate claim. Revenue CSV, all-platform engagement metrics and owner-friendly funnel presentation are not declared complete here. Refunds shown are confirmed refunds to date on orders paid in the selected period. No currencies may be summed together without an explicit conversion policy.

Production remains at the prior accepted release; the real RM1 quote order stays pending with zero paid revenue. Production direct-buy acceptance remains prerequisite-blocked by the absence of buy-mode active products; no catalog mode was changed. Next step is this local phase's explicit sync approval, followed by online read-only checks and the owner-report milestone.


## Approved conversion report UI binding — local, 2026-10-29

- [x] Three existing Analytics tabs use approved report sample; icon above each metric, unified Conversion Events, English customer-facing labels. Dedicated static CSS/JS; no Admin build or migration required. Campaign editor remains on existing logic; report CSS loaded only for Analytics.
- [x] Existing summary, payment evidence and per-currency amount rows drive real cards. No fabricated conversion rate, ROI or cross-currency sum. Exports append separate currency/minor-unit gross/refund/net section with date/timezone/policy.
- [x] Read-only paginated details and record routes enforce Analytics permission plus owner/merchant Campaign scope. Anonymous activity grouped by link, no customer email, phone, session key or access token returned. Enquiry and order source reference real link/post/account, unavailable accounts explicitly labelled. Quotation-stage records can open the linked enquiry.
- [x] Normal authenticated localhost: preserved existing 1 enquiry, 0 paid orders; enquiry ENQ-20261029-WPRTKUP7ME opened with actual product, original source and permitted existing Admin enquiry link. Performance opened source post list, enquiry and zero-paid results. Revenue correctly has no paid orders. Local collection remains disabled; no runtime mutation.
- [x] Isolated fake transport uses unchanged formal assets and explicit sample label: paid/refund amounts and fully refunded record net zero verified in normal browser. Error fixture renders clear message and Try again CTA. Fixture font asset cross-origin limitation is not formal-font evidence; formal page icons inspected separately.
- [x] Final focused integration regression: 36 tests / 281 assertions PASS in 5.206s (report details/privacy/pagination/staff, analytics, campaigns, tracking preparation, attribution/payment boundaries, purchase/quote). PHP/JS syntax and scoped whitespace checks passed. Later frontend-only stale record filter and empty-refund wording fixed; syntax rechecked.

Not claimed: real local/online paid transaction, all-platform engagement analytics, cohort conversion rates, production deployment. Real paid/refunded values were isolated tests/fixtures, not changes to live orders. No paid API, publication, Sol, commit, push or deploy. Unrelated dirty work retained. Existing online release remains f01c7756. Phase ready for user local review and separate sync approval.


## Local closeout acceptance — completed 2026-10-29, pending sync approval
Normal PDP browser verified: Save ON -> refresh -> ON; change OFF -> Cancel -> remains ON; Save OFF -> refresh -> OFF. Dialog now centered and opens only from Privacy choices, not on page load. Saved choice is session-scoped (180-day maximum validity does not override normal session expiry). Local global collection remains paused, so enabled-collection stopping/expiry is isolated route-test evidence, not a live collected visit claim. Product variant query remains intact after saving; clean() removes only jw_ref from the current URL. Existing trade records preserved.
Final focused privacy + report contracts: 9 tests / 83 assertions passed in 2.845s; broader privacy/attribution/payment/report regression previously 34 / 281 passed in 5.045s. JS/PHP syntax and scoped whitespace passed. Retention command on existing local DB used --dry-run only: events 0, retired 0; no actual cleanup performed on existing data. No default-on consent change, provider request, publication, financial transfer, commit, push or deployment.
Pending release scope: three-tab reports, protected drilldown, CSV currency breakdown, optional tracking preference/expiry and bounded daily visitor retention. No schema migration, new runtime tool or frontend build required. Exclude .env, generated sample/fixture pages, homepage/storefront build changes and all unrelated dirty work. See 2026-10-29-marketing-reports-privacy-release-scope.md.
Next milestone after separately approved sync/HTTPS checks: Email/WhatsApp CRM audit and functional spec/sample; no send activation or credential/recipient import implied. Homepage asset 503 remains a separate storefront issue, not marked fixed.


## Approved release and live verification — 2026-10-29
User approved synchronization. Scoped 24-file code release 2e1dcd9f4b9d11673ce6878b930d32509ee05713 pushed to GitHub main and deployed to Hostinger by ff-only pull and optimize:clear. All 23 source hashes matched the accepted manifest before copying; .env, generated fixtures, inventory/theme and unrelated dirty source excluded. No migration or frontend build.
Normal authenticated live Analytics renders three tabs and unified icon Conversion Events: existing qualified session 1, enquiry 1, quotation sent 1, accepted 1 and paid orders 0; no paid revenue. Live enquiry ENQ-20261029-4LTZLFI8H6 drill-down resolves original Facebook JW Studio campaign/post source, product and QT-20261029-JUD4EGUE, with existing Admin enquiry link. No record edits. Live product page retains variant=160, has Privacy choices dialog and previous choice; opened/read then Cancel only, no preference change. Same existing account connections and Hub callback source untouched.
Live report JS/CSS and storefront HTTPS return 200; unauthenticated Analytics remains login-protected 302. Online cleanup was --dry-run only (events 0 / retired 0, event days 90 / request days 7); no manual deletion. Daily retention command registered, not manually executed. No publication, financial action or paid API. Production collection setting preserved; local remains disabled. Prior local homepage asset 503 is separate; production homepage returns 200.
This evidence-only update follows the code release; final local/GitHub/Hostinger SHA parity is checked after deploying this record. All prior pending-sync statements describe earlier checkpoints and are superseded by this release section. Next work is Email/WhatsApp CRM audit/spec; no automatic recipient/credential import or sends authorized.
