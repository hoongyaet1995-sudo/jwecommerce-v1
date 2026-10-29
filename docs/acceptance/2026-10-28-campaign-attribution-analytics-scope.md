# Campaign Attribution & Analytics — Acceptance Scope

Date: 2026-10-28. Status: PARTIALLY IMPLEMENTED / ACCEPTANCE IN PROGRESS.
Spec: docs/superpowers/plans/2026-10-28-campaign-attribution-analytics-functional-spec.md

A checked item requires producer -> protected Laravel service -> MySQL truth -> consumer -> permissions/failures -> automated test -> normal browser evidence, where applicable. UI sample approval is separate from functional acceptance.

## A. UI sample gate

| ID | Status | Pass condition |
| --- | --- | --- |
| CA-UI-01 | [ ] | CRM-derived date controls, cards, charts and tables fit existing English Marketing Studio shell; desktop and narrow viewport screenshots have no overlap/overflow |
| CA-UI-02 | [~] | Campaign list/create/detail and existing Posts/Calendar handoffs are understandable; one primary next CTA per state; no duplicate content editor |
| CA-UI-03 | [~] | Analytics Overview / Campaign Performance / Revenue & Conversion support filtering and drilldown, with safe clearly illustrative fixtures |
| CA-UI-04 | [ ] | Zero, missing permission, not synced, unavailable tracking, empty list and failed states are distinguishable; native CTA/button availability is represented honestly |
| CA-UI-05 | [ ] | Future messaging channels do not offer active sending or imply working integrations; no real client PII from reference CRM in sample |

## B. Operational acceptance

| ID | Status | Test scenario and expected result |
| --- | --- | --- |
| CA-01 | [~] | Create/edit/reload/archive campaign; data persists and state does not send/cancel Posts implicitly |
| CA-02 | [~] | Associate existing/new draft Post, optional standalone Posts remain valid; each destination is separately visible |
| CA-03 | [ ] | Change draft campaign/destination; published source links and historical order snapshots retain original attribution |
| CA-04 | [~] | Unique per-post/account/placement links; tampered/expired-invalid tokens, unsafe redirects and cross-merchant IDs rejected; intended query/fragment retained |
| CA-05 | [~] | Click -> qualified arrival -> checkout -> fake payment yields matching campaign/post/account/order and authoritative amount/currency |
| CA-06 | [~] | Refresh landing/checkout/thank-you, duplicate payment callbacks and concurrent jobs do not duplicate sessions, checkout or paid-order counts |
| CA-07 | [ ] | Close browser before payment callback; background payment still creates one conversion from persisted order attribution |
| CA-08 | [~] | Pending, failed and unpaid orders do not become paid conversions; authorized bank-proof confirmation is supported without counting submission as payment |
| CA-09 | [~] | Full/partial refund and repeated refund callback produce correct separate gross/refund/net values; preserve original paid event |
| CA-10 | [~] | A then B then direct return: first A, last B; primary revenue counted once for B; forwarded/shared links are not presented as original-view proof |
| CA-11 | [~] | Window boundary, expired source, URL refresh, multiple tabs and delayed payment follow specified source-snapshot policy and merchant timezone |
| CA-12 | [~] | Guest purchaser works without recipient-email matching; absent consent/context stays unattributed without blocking checkout |
| CA-13 | [~] | Link preview bot/raw request is not labelled a human/session; staff/test traffic excluded or clearly segmented; collection failure does not break commerce |
| CA-14 | [ ] | Shared bio link reports only supported granularity; external uninstrumented landing pages show conversion unavailable |
| CA-15 | [~] | Summary/chart/table/CSV agree for same filters; period/cohort rates, incomplete window and distinct currencies are explicit |
| CA-16 | [ ] | Analytics viewer cannot edit/send/spend or reveal protected order/customer data; exports and account/campaign access are merchant scoped |
| CA-17 | [ ] | Unknown platform metrics remain unavailable, not zero; source/as-of labels present; social metrics separate from first-party orders |
| CA-18 | [ ] | Consent rejection, retention expiry and deletion policy mapped/tested; no customer email/order details in public tracking URLs |
| CA-19 | [ ] | Existing Posts, Calendar, Channels, video projects and Hub callbacks pass regression; no product-specific branches |
| CA-20 | [ ] | Fake future Email/SMS/WhatsApp adapter can map into shared campaign/event contract; no actual provider integration or charge |

## C. Free test matrix

Use local fake accounts/provider responses and controlled fake payment status transitions. Fixture identities are fictional. Include two campaigns, two posts, two social destinations, guest and signed-in checkout, unpaid/paid/partial-refund/full-refund orders, and separate currencies. No paid API, real mass-send, real charge, existing account disconnect, or production customer-order mutation.

Before claiming functional closure: validate persisted records, automated suite, normal localhost UI and storefront journey. UI screenshots alone are insufficient. Track each issue in one durable phase ledger with timestamp, command, evidence/change, result, cumulative unexpected failures and next action. Escalate 3/6/9 per AGENTS.md; no unchanged blind reruns. Measured elapsed/token usage only, otherwise unavailable.

## D. Later scope (not current acceptance)

Email/SMS/WhatsApp consent, recipient management, templates, provider sending/delivery reports and billing each need a channel-specific spec and acceptance matrix. Paid ad analytics, social conversion API forwarding, external ecommerce connectors and cross-device attribution are deferred.

## Evidence status

Local implementation, isolated tests and normal-browser visitor checkpoints now exist. See the dated execution ledger and readiness checklist. This does not close all operational gates; no deployment or real payment/publication.

## E. Request-a-quote acceptance extension (2026-10-29)

| ID | Status | Expected outcome |
| --- | --- | --- |
| CA-Q01 | [x] | Campaign click -> consent-qualified arrival -> valid persisted enquiry records one lead and correct source; no purchase required |
| CA-Q02 | [~] | Invalid form, form view and repeated request_key do not create additional leads; distinct legitimate enquiries remain distinct |
| CA-Q03 | [ ] | Quote sent/revised/accepted stages use existing records, with no extra lead count and no quoted-value revenue |
| CA-Q04 | [~] | Accepted quotation -> order inherits enquiry source; delayed verified payment counts one paid order, preserving manual fulfilment and no stock effects |
| CA-Q05 | [~] | Refused/missing consent, expired source, cross-user lookup, closed/lost enquiry and duplicate callbacks are safe; original quote workflow remains operational |

## F. Approved complete event-funnel acceptance

| ID | Status | Expected outcome |
| --- | --- | --- |
| CA-E01 | [~] | Consent-qualified page_view and view_item map to actual page/product; prefetch, hidden content and replay are excluded; page views differ from sessions |
| CA-E02 | [~] | add_to_cart emitted only after successful server cart mutation, correct item/quantity, no event for errors/replay |
| CA-E03 | [ ] | Explicit quotation_sent and quotation_accepted analytics stages match existing authoritative revision/acceptance records; retries/revisions do not inflate distinct lead counts |
| CA-E04 | [ ] | Buy-online and quote funnels shown separately with stable denominators/cohort/window; display-only products do not acquire fake conversion actions |
| CA-E05 | [ ] | Campaign primary conversion objective is explicit; lead, acceptance and purchase not summed as one conversion metric; revenue only from verified payments |
| CA-E06 | [~] | Canonical event mapping/version and raw/qualified traffic distinctions survive migration; unknown/disabled data is not fabricated as measured zero |

2026-10-29 localhost browser evidence: see campaign-integration-ledger.md. Draft save/reload/association and empty Analytics verified; partial markers do not close full functional/UI gates. Collection remains off and no real provider call occurred.

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
