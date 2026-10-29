# Campaign Integration Readiness Audit

Date: 2026-10-29 (Asia/Kuala_Lumpur)
Baseline HEAD: 87a20a88d9d22a978dd830cf3316e51a016d363b
Scope: read-only source review and implementation preparation. No application code changed, no provider calls/tests run, no commit/push/deployment. Primary working tree contains unrelated changes, preserved. Readiness is source-grounded, not runtime acceptance.

## Approved references

Functional spec: docs/superpowers/plans/2026-10-28-campaign-attribution-analytics-functional-spec.md
Acceptance: docs/acceptance/2026-10-28-campaign-attribution-analytics-scope.md
Approved sample: work/video-quality-review/ab-comparison/campaign-analytics-sample-20261028/
2026-10-29 correction uses Bootstrap nav-tabs/nav-link and removes decorative paper plane. Carry the corrected sample, not the earlier screenshot, into integration.

## Reuse and gaps

| Boundary | Current source evidence | Integration decision |
| --- | --- | --- |
| Campaign / Analytics | routes/marketing.php delegates to MarketingNavigationController preview | Add protected persisted modules; preserve formal shell and existing URLs |
| Posts | MarketingPosts::write owner/merchant check, row lock, version conflicts, immutable published outcomes; listing private to user | Keep current ownership; add campaign_id on normalized parent Post, preserve destination snapshots and approvals; never introduce arbitrary document keys that write() drops |
| Publishing | Existing queue and per-destination receipts | Link substitution must precede versioned approval/snapshot; never mutate approved queued/published content at worker runtime |
| Channels | merchant-scoped connection IDs | Reuse IDs; no callback/grant/profile modifications |
| Permissions | MarketingPermission currently only view/edit/approve_cost | Add narrow manage_campaigns/view_analytics grants with explicit upgrade mapping; existing general view must not accidentally expose revenue; no implicit order/publishing access |
| Checkout | CheckoutController::store -> CheckoutService::checkout creates pending order; guest email supported | Persist server-side source to order in checkout transaction, before external redirect; begin_checkout separate from paid/order-created event |
| Payment webhook | PaymentWebhookService verifies provider callbacks and writes payment/order paid plus notifications | Reuse verified confirmation evidence, use durable outbox + order deduplication; do not depend only on CheckoutService::transition because webhook writes directly |
| Manual proof | PaymentProofReviewService uses CheckoutService::transition for verified bank proofs | Hook successful review/transition, never proof upload |
| Other paid transitions | CheckoutService::transition and Admin/OrderOperationsService::updateStatus | Enumerate authorized manual/offline paths; mark evidence origin; do not count simulated/sandbox traffic in live reports |
| Refund | Admin/OrderOperationsService::refund and Returns/ReturnRequestService::transition resolve_refund create completed OrderRefund | Deduplicate by refund ID; preserve original purchase. Do not perform provider refunds from analytics code |
| Money | Support/StorefrontCurrencyContext explicitly states order arithmetic is base cents; Order stores display currency/exchange_rate; PaymentAttemptService sets amount from order total | Never label order.total cents with order.currency blindly. Add tested money-normalization boundary and snapshot base currency, base minor amount, rate and verified settlement data; unknown mismatch stays excluded/pending reconciliation |
| Consent | Targeted source search found no cookie_consent/analytics_consent/cookieconsent/tracking_consent contract | Treat as a gap, not proof none exists elsewhere. Tracking disabled by default; add explicit consent adapter/UI before visitor collection. Do not silently use localStorage without policy |
| Reports | No new campaign analytics implementation found | Build one query service shared by cards/chart/table/export; permission and merchant scope on every lookup |

## Technical constraints discovered

1. PaymentWebhookService::assertQuotationSettlement currently validates amount/currency only for quoted_manual orders. General-order confirmed status alone does not prove settlement amount/currency consistency. Analytics must not claim full money verification on that basis. Test normal provider payload matching separately and quarantine mismatches; a broader payment repair is not implicitly authorized by this phase.
2. A paid order may advance to processing/shipped/cancelled; order.status alone is not a permanent paid ledger. Use payment evidence and refund rows, not current fulfilment label.
3. Existing posts are user-owned while account connections are store scoped. Campaign selection and analytics must not leak another employee's private content. Store-wide reports require an explicit dedicated grant; protected order drilldown is separately checked.
4. Old post approvals and receipts must not acquire tracking links retroactively. New tracking applies only to new/reapproved unpublished destination versions; old records remain valid and may be unattributed.
5. Independent local customer deployments do not justify a new SaaS tenant structure. Keep current installation merchant boundary plus owner grants; Hub stays an OAuth broker, not the analytics database.

## Proposed implementation defaults

- Last eligible tracked click in 7 days; first-touch evidence also stored. Direct returns do not overwrite; checkout/order freezes source; delayed payments reuse snapshot.
- Visitor tracking disabled until consent integration is accepted. No consent -> no optional visitor tracking/storage, ordinary purchase unaffected and unattributed.
- No automatic historical backfill, source guessing or cross-device fingerprinting.
- Base-money reporting uses explicit base currency and source snapshots. Show display/settlement currency only when validated; keep currencies separate.
- Source events and order evidence are immutable; refund adjustments separate. Analytics processing is recoverable but cannot roll back payment/checkout.
- Email/SMS/WhatsApp remain planned adapters; no recipients, templates, sends or credentials migrate now.

## Current readiness verdict

Campaign CRUD and the first formal UI/data integration are now present locally. Attribution collection still has consent, session and money normalization gates before enablement. No new user choice needed for the preparation stage; findings above are engineering work with safe defaults. Operational/visual acceptance remains open. No release approval implied.

## Implementation checkpoint — 2026-10-29

Preparation is now followed by partial local implementation: campaign CRUD/assignment, consent-qualified event endpoints, quote source inheritance and payment/refund evidence reconciliation; real report API/date filters/CSV and formal UI are present. Canonical page/item/cart and quote stage producers added. This is not full phase acceptance; see 2026-10-29-campaign-integration-ledger.md for tests, known baseline inventory failures and open gates. Local Admin login was completed; first normal browser checks are recorded below.

## Completed checklist and next-stage gates — 2026-10-29

- [x] Persisted Campaign create/update/version checks and owner/merchant boundary.
- [x] Formal Campaign list/create/detail/content UI port and existing Post navigation.
- [x] Normal browser Campaign save/reload and labelled test draft association.
- [x] Explicit campaign-management / analytics permissions added without widening existing staff grants.
- [x] Real Analytics API/date/Campaign filters, single query for CSV and separate currencies; normal browser empty/disabled-state verified.
- [~] Complete acquisition-to-purchase/quote attribution: code and focused tests present, full guest/expiry/session/browser gates open.
- [~] Payment/refund evidence: local bank proof and delayed quote source cases tested; broader provider and currency edge coverage remains.
- [~] Event catalog: page/item/cart/checkout/enquiry/quotation/acceptance producers present; canonical mapping, session semantics and cohort reporting remain open.
- [ ] Data retention/revocation and complete cohort funnel acceptance.
- [ ] Genuine-account tracked post link plus full normal-browser conversion journey.
- [ ] Release approval, GitHub/Hostinger synchronization and exact-SHA HTTPS acceptance.

Collection remains disabled by default. Latest scoped tests 11/62 passed after local browser checks; build passed; two unrelated physical-return assertions reproduced on baseline are recorded, not hidden. No paid API/publication/production changes. See campaign-integration-ledger.md for exact run evidence and remaining issues.

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
