# Posts / Calendar local prototype plan
Spec: docs/superpowers/specs/2026-10-28-posts-calendar-prototype-design.md
The user explicitly requested a disposable flow sample; this is an isolated UI spike, not production implementation.

1. Reuse approved static shell and bundled local Bootstrap/icons/fonts under work/video-quality-review/ab-comparison. Create posts-calendar-sample-20261028/index.html, sample.css, sample.js only.
2. Use sanitized four-account snapshot, existing sample media and local-only state. No fetch/API/publisher.
3. Build Posts/editor/account+asset dialogs/review/Calendar/delivery details with scoped current UI patterns.
4. Verify normal browser happy path, permission/past-time blockers, save/reload, calendar consistency, cancel and failure flow; inspect wide/narrow screenshots. Fix concrete findings once and record evidence in sample verification.md and existing issue ledger.
5. Open local sample for user review, link functional spec, state limitations. No commit/push/deployment.


## Next integration phase — ready for scope approval
Preserve the approved Bootstrap UI and English copy. Replace sample storage with Laravel/MySQL Posts, per-account destination variants and delivery records. Read actual connected accounts through existing ownership/employee permissions. Posts and Calendar query the same authorized source. Persist UTC schedule plus IANA timezone, explicit approved content revision, cancellation and history. Add media readiness and platform capability checks; do not infer publishing rights from connection alone.

First acceptance uses fake Facebook/Instagram publishing adapters with external dispatch disabled: draft reload, unauthorized account rejection, explicit schedule, revision edit/cancel, queue claim idempotency, independent target outcomes, partial-success preservation and unknown-submission no-resend. TikTok/YouTube can prepare drafts; scheduling remains blocked until their specific publishing permissions/adapters are independently verified. Reuse existing Hub callback unchanged. Campaign linking optional; no new Campaign management scope. Do not publish, commit, push or deploy without separate authorization.

## 2026-10-28 control refinement
Root cause: navigation-preview .empty-box i applies decorative 32px/display:block/margin-bottom18px to the CTA arrow. Added scoped CTA overrides (40px actual height, 14px arrow, zero bottom margin). Shared controls.css changes native checkbox visuals to 38x22 toggles, preserving checked/name/value/required/disabled and all handlers. Loaded last in both Marketing layout shells and the standalone preview. Browser verified compact empty-state button and Customize text toggle reveals the existing destination textarea. No business logic changed. Full inventory of production screens remains an integration regression gate.
