# Audience Functional Spec — Phase 1

Date: 2026-10-29
Status: Scope approved; locally integrated and verified 2026-10-30, ready for user review. No bulk sending authorized.
Baseline: e13cd2326425cb5b7662cf378228521d97d58be6. Canonical development root only; unrelated dirty work preserved.

## Purpose and approved approach
Provide one merchant-owned audience for Email, SMS and WhatsApp campaigns. Reuse the supplied CRM Contacts, Segments, Import and Export layout, styles and interaction patterns with JW branding and English copy. Adapt its data access to Laravel/MySQL and existing JW customer identity. Do not copy CRM customer records, credentials or company branding. Do not create an independent competing customer master.

A direct CRM database transplant would duplicate identities and bypass JW permissions. Selected approach: preserve presentation and portable rules, replace persistence and authorization with JW services. This requires explicit identity, subscription and snapshot contracts but keeps customer/order/enquiry flows intact.

## Source audit
- crm.zip: crm/contacts.php, segments.php, audience_import.php, audience_export.php; AudienceIdentityService, AudienceSyncService, CampaignAudienceService available.
- CRM sync includes registered contacts, order activity, guests and newsletter sources; sender review includes suppression checks. Their existence does not prove JW compatibility.
- JW Customer exposes email, phone, tags, user and orders; Customers/CustomerContactResolver and ContactValueNormalizer already exist.
- JW ProductEnquiry exists; do not infer a verified customer relationship from names alone.
- marketing_whatsapp_subscriptions already stores merchant-scoped phone hashes, encrypted phones, status and consent/revocation timestamps; retain and reconcile this truth.
- Customer model alone does not expose channel consent. Audit all actual newsletter/subscription producers before migration; absence from the model is not proof that no producer exists.

## Navigation and merchant experience
Audience expandable sidebar: Contacts / Segments / Import / Export. Existing Campaigns, Templates, Posts and Channels remain available.
Contacts: CRM KPI cards, paginated table, search and source/subscription/order filters, last successful sync and Sync Audience. Contact detail shows linked sources, channel eligibility, verified order totals and enquiry history with authorized links.
Segments: name, rule builder, preview count and eligible counts by channel; save dynamic rules, edit, duplicate and archive. Unsupported rules must fail validation rather than silently broaden the audience.
Import: upload CSV, map columns, preview validation and duplicate decisions, confirm, then background progress and downloadable row errors.
Export: choose current filters or segment, select permitted columns, prepare in background, then authenticated download.
Saving or synchronizing an audience never sends a message.

## Identity and data integrity
One store directory shared with authorized employees. Never reinterpret customers.user_id as Marketing employee ownership. Resolve tenant/store scope through existing shop contracts.
Reuse existing customer resolver; keep source IDs and provenance. Normalize email and phone using existing rules. Never match only by display name. Do not assume a country code or remove email plus suffixes. Conflicting email/phone matches enter review; no destructive automatic merge.
Guest orders, enquiries and imports may create audience identities linked to source records without fabricating storefront accounts. Source removals must not delete commerce records or revoke historical evidence.
Synchronize incrementally in bounded batches, with a durable job state and a single active sync per store. Repeated sync is idempotent; persist only the successful checkpoint and expose partial failures.

## Channel eligibility
Store channel-specific unknown / subscribed / unsubscribed status and provenance, effective time, and evidence reference; suppression is separately reasoned and auditable.
Purchase, enquiry, account registration and CSV presence do not imply marketing permission. Import defaults to unknown. Existing evidenced consent may be migrated without inventing consent dates.
Email unsubscribe and WhatsApp opt-out remain channel-specific; explicit global suppression overrides all channels. Reimport and sync never clear suppression or resubscribe. A legitimate new opt-in requires a supported evidence-producing action.
Website analytics consent is separate from receiving marketing messages.

## Segments and campaign handoff
First rules: source, tags, channel subscription, has verified paid order, paid-order count, net paid value per currency, last paid date, enquiry presence/status/date. Support a bounded AND/OR group contract with strict operator and value allowlists; no arbitrary SQL.
Preview reports matched contacts, eligible recipients and excluded counts with reasons. Counts are not authorization to send.
At later campaign confirmation freeze content/version, segment rules/version, eligible destination IDs, consent basis and suppression evaluation. Recheck revocation/suppression immediately before dispatch. One channel per campaign remains enforced.
This phase delivers preview/handoff contracts, not production bulk dispatch, billing or automatic scheduling.

## Import and export boundaries
CSV first version; reject unsupported file types and enforce configurable byte/row limits in implementation. Preview must state limits before upload. Do not load entire files or catalogs in browser memory.
Map names, email, phone and supported tags. Dry-run reports invalid rows, duplicates, conflicting identities and safe updates. Confirm exact batch; replays cannot duplicate records. Partial errors remain traceable by row without dumping PII into logs.
Export uses the same scope and filters as the list, audited actor/time/count, authenticated expiring download, formula-injection-safe CSV cells and restricted storage. Deletion/retention policy must be explicit before release; do not publish export files under public/.

## Reporting semantics
Registered, guest and enquiry-only contacts must remain distinguishable. Total contacts is deduplicated identities; subscribers is channel-specific; With orders must have a documented paid-order definition. Suppressed counts are distinct contacts, not repeated reasons.
Only verified paid transactions count toward paid value. Refunds reduce net value. Keep currencies separate and use existing minor-unit conventions; do not total mixed currencies. Avoid N+1 queries; indexed aggregate projections must reconcile to source records.

## Permissions and execution
Separate view audience, manage contacts/segments/import, export personal data, and existing campaign send permissions. Enforce server-side scope on lists, detail, jobs, previews and downloads. A direct ID URL cannot bypass access.
Queued imports/exports/syncs use dedicated bounded jobs and shared-host scheduler-compatible processing. User UI shows progress and actionable errors; provider/SQL details remain in protected logs.

## Delivery order and exclusions
A: source manifest + producer audit + schema/permission mapping. B: Contacts and channel states. C: Segments and preview. D: CSV import/export. E: fake acceptance and normal localhost UI acceptance; update ledger and request explicit sync approval.
No real blasts, paid APIs, provider key changes, Hub callback changes, production data import, commit, push or deployment in this stage.
Preserve Email/SMS templates and the completed one-recipient Email conversion evidence. Existing stock-fixture failure in purchase conversion test remains an independent issue (historical unexpected failure count 2); no blind reruns or unrelated inventory repairs.

Implementation defaults and local evidence: see docs/acceptance/2026-10-29-audience-acceptance.md. Campaign drafts can now select a scoped segment and preview per-channel counts; dispatch remains disabled. Export snapshots freeze membership, not source values, and are operational contact downloads rather than financial point-in-time reports.
