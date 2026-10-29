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
