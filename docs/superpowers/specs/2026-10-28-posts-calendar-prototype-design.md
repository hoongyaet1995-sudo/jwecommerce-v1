# Posts and Calendar flow prototype — Functional Spec v1

Date: 2026-10-28. Status: disposable local UI prototype requested for review; not an operational publisher. No commits, pushes, deployment, paid API, provider requests or business database writes.

## Goal and selected approach
A merchant can choose an existing connected account, prepare/save a draft, review a schedule, locate it in Calendar and understand a delivery failure with one clear next action.
Reuse JW Marketing Studio's approved Bootstrap/Xenibox shell, sidebar, cards, typography, buttons and green palette. Do not clone Metricool's styling. Considered cloning a broad planner (too much new scope), extending only the current simple table (insufficient failure/timing visibility), and keeping the current table/editor with a shared calendar and review page. The last approach follows the user's existing-UI choice.

## Research used, and limits
Primary product documentation, not an authenticated competitor product audit:
- Metricool calendar: date navigation, timezone, status/network filters, view changes; only unpublished items may move. https://help.metricool.com/how-to-manage-your-calendar-in-metricool-xhlbw
- Metricool scheduling: common content planning with per-network differences and brand timezone. https://help.metricool.com/how-to-schedule-content-from-the-calendar-q8ymv
- Buffer drafts: incomplete drafts are allowed; setting a date on a draft is distinct from approval to schedule. https://support.buffer.com/en-us/articles/saving-and-scheduling-draft-posts-CBLXg1yFXp
Borrow these interaction principles. Our call: one logical Post with per-account variants, rather than separate unrelated drafts per channel. Our first calendar uses explicit Edit schedule confirmation rather than drag-to-reschedule. No best-time suggestions without analytics data.

## Data boundary
Account names/identities are a read-only snapshot of the four live connected accounts verified on 2026-10-28: Facebook JW Studio; Instagram hoongyaet95; TikTok Joshua Wan; YouTube Joshua Wan. No credentials, OAuth state, CSRF tokens, secret keys or provider tokens in the sample.
All posts, permission-error scenarios, schedules and delivery histories are clearly labelled sample data. Only account display names/platforms come from the connection snapshot; sample IDs are local aliases. Local browser storage is prototype persistence, never production business truth. Assets reuse existing local sample images/videos. No live snapshot polling, uploads, LLM, OAuth or publisher calls.
Actual connected state is not publishing readiness. Facebook/Instagram may simulate successful preflight; TikTok/YouTube display Publishing access required and may save drafts but cannot schedule in this version. No implication that general-customer Meta App Review is complete.

## Screens and interactions
| ID | Screen | Functional scope | Primary CTA |
|---|---|---|---|
| P1 | Posts | Status tabs with counts, text search, account filter, empty/no-results, destination names, timezone/date, per-destination outcomes | Create post |
| P2 | Editor | Account picker with search and explicit platform labels; text, optional title, platform preview, optional per-account copy; existing image/video library; Save draft accepts incomplete work | Review schedule |
| P3 | Schedule review | Account + exact variant + media snapshot, local time + IANA timezone + UTC reference; content/readiness validation; explicit future date/time; one confirmation | Confirm schedule |
| P4 | Calendar | Week and list views, previous/next/today, timezone and status/account filters; click item opens same Post; dated drafts labelled Draft, undated drafts excluded | Create post |
| P5 | Post/delivery detail | Target-specific statuses, scheduled revision and concise history; edit schedule, cancel, move to draft confirmation; preserve successful destinations | Action for current problem |
| P6 | Needs attention | Three examples: permission required, provider rejected media, unknown submission outcome. Specific next action; never one generic blind Retry | Review account / Edit post / Check status |

## Validation and state rules
Draft can be incomplete and never publishes. Review blocks no destination, no caption, unsupported type/media combination, missing publishing capability, invalid/past date or invalid timezone conversion. Only Facebook may use text-only Post; Instagram needs an image/video. Video type needs video. No hard-coded industry/product rules. Per-account copy inherits common copy unless explicitly customized.
Schedule changes require another review; cancel requires confirmation and preserves the post. Sample state: Draft -> Scheduled -> Publishing -> Published or Needs attention; Cancelled is retained. No real timers publish due content. Existing seeded histories demonstrate success/partial failure/unknown outcome. Simulated Check status only resolves a known sample reference; it never submits a replacement. If still unknown, say no duplicate was sent and keep Needs attention. Successful targets cannot be resent by fixing a failed target. Publishing/Published items have no Edit schedule or Cancel publishing controls.
Editor changes are held in a local working copy; back navigation offers Keep editing / Discard changes. Explicit Save draft persists local sample only. Repeated scheduling confirmation yields a single record/revision. Changing timezone preserves the selected absolute instant for already scheduled content. New date/time is interpreted in selected IANA timezone; detect invalid/ambiguous local time instead of silently choosing.

## Acceptance scope
| Gate | User-checkable result |
|---|---|
| AC1 Existing UI | Source shell/colors/fonts/spacing retained; English user copy; desktop and narrow-screen no page-level horizontal overflow |
| AC2 Account selection | All four real snapshot names; platform remains visible where names repeat; search and multiple selection; no fabricated second account |
| AC3 Draft | Incomplete draft can save; reload retains it; no schedule inferred; undated draft absent from calendar |
| AC4 Editing | Common and destination copy preview correctly; media picker works; changing preview tab does not drop edits; dirty navigation guarded |
| AC5 Confirmation | Clear destinations, per-account content, date/time/timezone; incomplete/missing-permission/past-time case blocked with a direct corrective CTA |
| AC6 Schedule | One explicit confirmation creates one sample schedule; repeated click does not duplicate; Calendar and Posts agree |
| AC7 Calendar | Week/list, filters, today/previous/next and item detail work; timezone is explicit; reschedule and cancellation update both views |
| AC8 Failure | Permission, rejected-media and unknown-outcome cases distinct; partial-success targets stay protected; no automatic resend or fake whole-post success |
| AC9 Safety | No provider/business API calls, real posts, uploads, fees, credentials, live business writes, commits or deployment |
| AC10 Prototype honesty | Header identifies flow preview; review states no content will publish; simulated delivery history is labelled; no fabricated analytics |

## Excluded
Real publisher/backend/cron, platform permission expansion, AI copy/media generation, billing, analytics/best times, bulk CSV, recurring queues, ads, inbox, team approval chains, new Campaign backend, cross-brand duplication and template marketplace.

## Review sequence
Posts -> Create post -> Select accounts -> Write/add existing media -> Save draft/reopen -> Review schedule -> Confirm -> Calendar -> Edit schedule/cancel -> Needs attention examples. Approving the UI later is a separate decision from allowing any real publication.

## Local preview verification — 28 Oct 2026
Sample: http://127.0.0.1:8524/posts-calendar-sample-20261028/#posts

Observed through normal Chrome UI: all four account names and platform labels; search for Joshua distinguishes TikTok/YouTube; account selection; incomplete draft save and reload retention; future schedule review with local/IANA/UTC summary; past-date disabled confirmation; TikTok publishing-access blocker; explicit sample schedule confirmation; shared Calendar week/list/search; cancellation confirmation and retained Cancelled result; permission-specific action; unknown-status busy then unchanged outcome, without resend; partial-success retained. Desktop screenshots reviewed for Posts, account picker, schedule review, Calendar list. Console error list empty. JS syntax and static no-publisher-call checks passed. This is flow evidence, not provider publishing acceptance.

During local construction, fixed Calendar live search, dated draft retention and partial-result aggregation before review. Remaining acceptance before production integration: narrow-screen browser verification, full editing/back-navigation edge cases, backend authorization/storage/idempotency, actual platform media/permission validation and provider fake-adapter tests. Those production checks are outside this UI sample. No claim that AC1–AC10 are fully production accepted.
