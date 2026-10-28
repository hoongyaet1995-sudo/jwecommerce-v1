# Posts queue execution implementation plan

Approved UI: publishing-flow-preview-20261028. Work locally in canonical source; no real posting/API or deployment.

Architecture: dedicated database queue/outbox on same MySQL connection as approved post revision, atomic enqueue. Opt-in flags default off. Only new explicitly confirmed revisions execute, never adopt historical Scheduled rows. Jobs have one attempt and use existing idempotent per-destination adapters. Unknown submission is query-only, never automatically restarted. Separate due dispatcher and finite queue worker compatible with cron; no rendering dependency.

- [x] Add dispatch table/model, atomic submission, immutable approval fingerprint and stale-version/account/media guards; write fake tests first.
- [x] Add one-attempt worker, due sweep, known-result reconciliation and timeout quarantine. Preserve completed destination records and prevent cancel/edit races.
- [x] Route immediate/future approvals to queue behind flags; lock accepted/processing drafts while leaving browsing available. Existing adapters remain behind current provider gates.
- [x] Integrate approved English progress/confirmation/Calendar/attention copy and actual upload progress. Poll read-only saved state. No developer controls.
- [x] Run focused fake regressions, migrate only new table locally, normal browser acceptance. Record limitations and release scope; do not commit/push/deploy.

Acceptance: web returns queued without provider upload, worker sends once, due clock/duplicate sweep safe, cancelled/edited approval never executes, actor/scope/account/media changed stops, partial success retained, uncertain delivery never starts another request, queues isolated from sync/paid rendering. Old states/versions remain available. Token usage unavailable unless measured.

Local gate completed; connected-account paths fake-provider verified. Production activation and private scheduled posting intentionally pending.
