# Campaigns, Attribution and Analytics — Functional Spec v1

Date: 2026-10-28
Status: design scope recorded; UI sample and implementation not accepted.
Owner: JW Marketing Studio. Canonical development: C:/xampp/htdocs/jwstudio-ecommerce.

## 1. Purpose and approved direction

Merchants across industries organize marketing activity in Campaigns, prepare content with existing Posts/Calendar, and connect measurable website activity to verified paid orders. Avoid per-product logic. User-facing UI/copy is English. Prefer the supplied CRM UI over inventing another design system.

Current verified baseline: Campaigns and Analytics are preview routes in routes/marketing.php; Posts, Calendar and Channels already exist. This specification does not claim those previews implement campaign attribution. No application code, provider request, publishing, commit, push or deployment is authorized by this document update.

Reference: C:/Users/User/Downloads/crm.zip and https://crm.qairahijab.com.my/marketing/dashboard. Read-only review covered Dashboard, Report Overview, Revenue & Conversion and Campaign Review; inspected CampaignAttributionService.php, track_storefront.php, track_conversion.php and ecommerce-conversion-tracking.md. Displayed revenue was not independently reconciled against payments.

## 2. Product architecture and phase boundaries

Use one Campaign entity and channel-specific adapters, not a second campaign system per channel. Campaign is an organizing/reporting unit, not a new bulk-send shortcut.

Current phase:
- Campaign CRUD, existing social Posts association, independent destination tracking links.
- First-party website arrival and checkout attribution; verified payment and refund reconciliation.
- Analytics Overview, Campaign Performance and Revenue & Conversion.
- Fake-response fixtures, local browser acceptance and a UI sample before formal integration.

Later phases explicitly reserved:
- Email, SMS and WhatsApp campaign delivery, templates, audiences, consent/suppression, provider receipts and channel costs.
- Social platform impressions/views/likes/comments when separately authorized and implemented.
- Meta/TikTok event forwarding, paid ad buying, external-store connectors and cross-device identity resolution.

Do not expose future send buttons as functional. A future adapter may reuse campaign, content/creative references, schedule, task/receipt and analytics contracts, but must supply channel-specific readiness and authorization.

## 3. UI source and navigation

Preserve Marketing Studio sidebar, existing routes, accounts and Hub callback. Use the CRM Bootstrap patterns, actual typography, spacing, card styling, filters, charts, tables and empty states as visual reference. Do not copy unrelated branding, customer data, database settings or credentials.

| Surface | CRM pattern | JW behavior |
| --- | --- | --- |
| Overview | Date range, summary cards, sales trend, campaign ranking | Activity overview and measured conversion summary; useful main CTA |
| Campaigns | Campaign list/status and review | Create/edit campaign, link Posts, inspect results |
| Campaign detail | Delivery summary and report links | Tabs: Overview, Content, Performance; per-account publishing remains in existing Posts |
| Analytics Overview | Report Overview | Clear separation of social performance and first-party website conversions |
| Campaign Performance | Campaign Performance | Filter by campaign, post, platform/account and date; drill into source records |
| Revenue & Conversion | Revenue & Conversion | Revenue/orders trend, campaign contribution, attributable order history and CSV |

No extra Reports sidebar is needed: these are tabs within Analytics. Campaign detail opens the same filtered Analytics, not a different calculation.

Primary CTAs: Create campaign, Add posts, Create post, View performance, View order, Open landing page. Avoid developer text, tracking-code forms and manual recovery workflows in the primary UI. Explain tracking only where it affects a decision. Existing campaign assignment is optional for standalone Posts.

## 4. Campaign functional contract

Fields: immutable ID, owner/merchant scope, internal name, objective, optional description, start/end dates, timezone, default destination URL and timestamps/version. Objectives reuse existing supported marketing purposes. No performance promises.

Lifecycle: Draft -> Active -> Completed; Archived is recoverable organization state. Campaign status must not overwrite individual post/destination publishing status. Active does not mean every post published. End date does not cancel scheduled content silently. Explicit changes/cancellation use existing confirmation workflow.

A logical Post belongs to zero or one Campaign in v1; each destination keeps its own account, platform, content version, publishing receipt and tracking link. Future channel messages use the same campaign ID without becoming fake social Posts.

Published links and order attribution keep immutable source snapshots. Moving a draft is allowed; moving published content must not rewrite historical attribution. Archived campaigns retain history. Campaign deletion with historical events/orders is not hard deletion. Campaign creation/association never sends content.

## 5. Destination and CTA contract

Each trackable destination owns a distinct opaque, unguessable link ID bound server-side to merchant, campaign, post/message, destination/account, content version, CTA placement and an allowlisted destination URL. No email, customer ID or secret in public URL. Changing a destination creates a new version; old shared links retain their original meaning.

Validate http(s) destinations, prevent open redirects, reject credential-bearing URLs and unsafe schemes. Same-store destinations are supported for order tracking in v1. External destinations may be opened but must show conversion tracking unavailable; never imply an uninstrumented website is measured.

Preserve intended URL query and fragment. Generate standard UTM values alongside internal IDs where useful; a visitor cannot supply trusted campaign/order attribution simply by editing query values. URL cleanup occurs only after safe capture, preserves other query/fragment state and is not a secrecy guarantee.

Platform adapter declares supported link placements. A CTA copy label is not a promise of a native clickable button. Instagram/TikTok shared bio links are attributed only to the shared entry unless a distinct post-specific destination is actually used. Never invent post-level precision. Preview/review states the real link placement and any missing profile-link prerequisite. Do not modify profile links silently.

## 6. Event flow and identity

1. A redirect records a raw link request. Link preview bots are not people.
2. An instrumented storefront arrival records a qualified session/landing event when collection is permitted. Preserve arrival separately from raw requests and deduplicate refreshes for the chosen session policy.
3. Capture attribution in a first-party signed/server-managed context, bound to the checkout/cart/order server-side before leaving for payment. Do not depend solely on localStorage or a success-page callback.
4. begin_checkout is emitted for an actual checkout start, not every rendering/refresh; define and test its idempotency key.
5. A verified successful payment transition writes a purchase event/attribution snapshot using authoritative order/payment amounts and currency. Reconcile asynchronous callbacks and authorized bank-proof review. Pending/failed/COD-unpaid orders are not paid conversions.
6. Refund/partial refund records adjust the refund metric idempotently without deleting the original purchase history. Fulfilment cancellation alone must not erase an already collected payment.

Anonymous visitors and guest checkout are valid. Do not require the purchaser to match an email recipient. No fingerprinting. Do not claim cross-device or view-through attribution. Unattributed orders remain unattributed; do not assign them to a campaign to improve results.

## 7. Attribution and metric definitions — proposed v1 defaults

Store first and last eligible tracked touches; primary report uses last eligible campaign-link click within 7 days. Direct return visits do not overwrite a valid tracked source. A new eligible campaign click replaces last touch. Order checkout captures the source snapshot; repeated callback/late arrival must not reassign it. Window eligibility is evaluated at order placement, while paid conversion date is payment confirmation date. Late payment retains the order's eligible snapshot.

A qualified arrival must preserve the original click timestamp; refreshing a marked URL must not renew its window. Multiple tabs must not overwrite an existing order snapshot. An event table maintains source evidence and policy version. Proposed defaults are design recommendations, not previously accepted runtime behavior.

| Metric | Definition |
| --- | --- |
| Link requests | Raw tracked redirect requests; includes bot/preview activity, never labelled people |
| Website sessions | Qualified attributed storefront sessions; not proven unique humans |
| Checkout starts | Deduplicated tracked checkout attempts |
| Paid orders | Distinct attributable orders with verified successful payment |
| Attributed revenue | Captured paid amount, grouped by currency; exclude failed/unpaid amounts |
| Refunds / Net attributed revenue | Confirmed attributable refunds; revenue minus refunds, explicit reporting basis |
| Session-to-purchase rate | Converted sessions / eligible website sessions for the stated cohort/window |
| Checkout-to-purchase rate | Converted checkouts / eligible checkout starts for the stated cohort/window |
| Average paid order value | Attributed paid revenue / paid orders, same currency and cohort |

Dashboard activity trends use event date and merchant timezone. A conversion funnel/rate uses a defined entry cohort and window; never divide unrelated period purchases by current-period sessions. Show incomplete observation windows. Counts at different stages need not be a strict funnel when links are shared or tracking is unavailable.

Platform-reported engagement metrics have their own source, as-of time and definitions. Missing permission, not synced and unavailable are distinct from measured zero. Do not total incompatible view metrics into a supposedly comparable reach number. No ROAS/ROI without a valid cost basis. Multi-currency totals stay separate unless a separately specified exchange-rate policy is implemented.

## 8. Reporting and access

Filters: date range, campaign, channel/platform, account and post; timezone and attribution policy visible compactly. Summary, chart, table and CSV must use identical filters and money definitions. Order list links to the protected existing order page, not a public order URL. Respect existing employee order visibility; analytics access must not grant customer data access implicitly. Mask/omit PII when unnecessary.

Campaign editor permissions do not grant publishing or spending. Separate view analytics, manage campaigns, publish, cost approval and order access. Every read/write/export/link/event lookup is merchant scoped. One installation's shared store catalog and authorized employees remain the current ownership model; this phase does not introduce a new SaaS tenant migration.

Collection respects the site's consent/tracking policy; define when collection/storage may occur before implementation, without bypassing rejection. No PII in URLs, events exported to social platforms, or analytics payloads. Provisional retention: raw anonymous events 90 days; aggregate reports 13 months; order evidence follows the store's order retention policy. Retention and consent integration are implementation gates to map against existing settings, not claims that those controls already exist.

Tracking failure must never break checkout or payment confirmation. Persist/reconcile pending analytics work idempotently; unavailable attribution is visible rather than blocking commerce. Background work fits existing Laravel queue/scheduler/shared-host operation. No dependency on Remotion/GPU/render workers.

## 9. Future Email / SMS / WhatsApp boundaries

Shared: campaign/creative references, merchant ownership, schedule/timezone, per-destination task, unique link, receipt, event source and report drilldown.

Distinct: Email recipient consent/unsubscribe/bounce/suppression; SMS consent, phone validation, encoding/segment cost; WhatsApp approved templates, opt-in, message window/category/provider pricing. Provider-accepted, delivered, opened, read and clicked are different states; unavailable receipts remain unavailable. Never treat social OAuth as messaging consent. Later multi-channel orders still receive one primary attribution, while assisted touches may be shown separately without duplicating revenue.

No existing CRM database/users/audiences/secrets are migrated in this phase. Reuse UI and design concepts; port logic through Laravel services and existing order/payment contracts after review.

## 10. Implementation sequence and release gates

A. UI sample with safe fixtures: Campaign list/detail/create, tracking destination section, Analytics tabs, drilldown, empty/unavailable/failed states. Clearly label illustrative analytics; no real send/charge.
B. Campaign persistence and existing Post association; protected permissions and historical snapshots.
C. Links and first-party arrival/checkout capture; consent and attribution test matrix.
D. Payment/refund reconciliation and real-data reports/CSV.
E. Fake-response suite then normal localhost browser acceptance; controlled test payments only.
F. Synchronize only after separate user approval, exact-SHA release and HTTPS smoke verification. No real social posting or third-party event forwarding implied by acceptance.

See docs/acceptance/2026-10-28-campaign-attribution-analytics-scope.md for stable acceptance IDs. All implementation gates remain open.

## 11. Approved scope extension — Request a quote (2026-10-29)

User explicitly adds quote-request attribution for quote-mode products/services. The same tracking/consent policy applies, without requiring a cart purchase.

Separate outcomes: qualified enquiry submission, quotation sent, quotation accepted, quotation order created, verified payment. A button click/form view/failed validation is not a lead. Deduplicate by persisted ProductEnquiry ID and submission request_key; do not merge distinct requests based only on email. Save first/last source on enquiry creation. Later quotation acceptance and order creation inherit this immutable enquiry source even after the original 7-day click window; the 7-day gate is evaluated when the enquiry was created. No recipient email requirement. If an order is already attributed, do not overwrite it or count its revenue twice.

Lead stage data uses existing ProductEnquiryEvent, EnquiryQuotation/Version, QuotationAcceptance and Order relations. Report quote requests and paid conversions separately, optionally linking the funnel Enquiry -> Quotation -> Acceptance -> Paid order. Quoted values are pipeline value, never collected revenue; lost/closed enquiry is not a refund. Do not show pipeline money until currency/value contracts exist.

Preserve manual fulfilment and no-stock-reservation quotation rules. No new public access to enquiry/customer information; use existing protected receipts/admin rights. Missing/declined consent is unattributed. Repeat form submit, quote revision, acceptance or payment callback must not duplicate lead/payment counts. Tracking failure never blocks valid enquiry submission.

## 12. Approved canonical event catalog (2026-10-29)

User approves the full funnel event catalog. This is functional scope, not a claim of completed implementation.

| Canonical event | Authoritative trigger | Role / deduplication |
| --- | --- | --- |
| campaign_link_click | Valid tracked redirect request | Raw acquisition event; distinguish previews/bots from qualified arrivals; not a unique-human count |
| page_view | Actual eligible storefront page navigation after analytics consent | Browsing event; navigation ID dedupe for transport retries, genuine reloads may be additional views; do not equate views with sessions |
| view_item | Actual rendered product/service detail with canonical catalog ID after consent | Browsing event; reuse navigation ID + item ID, exclude hidden/prefetched items |
| add_to_cart | Server confirms a successful cart quantity addition | Intent event; no event on button click, invalid stock/product, or request replay; stable mutation ID, quantity/currency from server |
| begin_checkout | Actual checkout initiation | Intent event; stable checkout attempt ID, refresh does not create another start |
| request_quote | ProductEnquiry successfully persisted | Primary lead conversion, unique enquiry ID; failed forms/button views/replayed request_key excluded |
| quotation_sent | Existing immutable quotation revision successfully marked sent | Sales stage, revision ID + sent event; show both revision events and distinct enquiry counts explicitly |
| quotation_accepted | Existing QuotationAcceptance successfully persisted | Acceptance conversion, unique acceptance ID; repeat acceptance does not duplicate |
| purchase | Verified successful payment for an attributable order | Primary revenue conversion; one primary attribution per order, frozen source and payment evidence |
| refund | Confirmed completed full/partial OrderRefund | Revenue adjustment; unique refund ID, original purchase retained |

Campaign chooses primary conversion objective: lead/request_quote for enquiries, purchase for sales, quotation_accepted where appropriate. All eligible supporting events remain available. Never total all event types into one ambiguous conversion count or duplicate revenue across milestones.

Buy-online funnel: view_item -> add_to_cart -> begin_checkout -> purchase.
Quote funnel: view_item -> request_quote -> quotation_sent -> quotation_accepted -> purchase.
Display-only catalog entries support browsing only; do not invent cart/quote actions. Valid later quote payment inherits original enquiry attribution as defined in section 11.

Aggregate counts use the same merchant, date/timezone, cohort, source and consent policy. Unknown source remains unattributed. No optional visitor events before consent; server order/enquiry records remain operational irrespective of analytics choice. Capture does not send events to Meta/TikTok/Google unless a future integration is explicitly authorized.

Current implementation mapping: raw request/qualified arrival/checkout event scaffolding, enquiry source snapshot, quotation-order source inheritance, and payment/refund evidence reconciliation exist locally with focused tests. Canonical page_view/view_item/add_to_cart collection and explicit quotation_sent/quotation_accepted analytics events are still pending. Existing internal kinds request/arrival/checkout need an explicit mapping/version, not an implicit rename of historical rows. Approved report UI parity and full browser acceptance remain open.
