# Posts queue and Calendar local acceptance

## Baseline and scope
Canonical source C:/xampp/htdocs/jwstudio-ecommerce, HEAD f5be1dec77488a89f3bf90f5a2b61cd74a048600. Preserve unrelated dirty work. Approved UI publishing-flow-preview-20261028. No remote commit, push, deployment, provider authorization, paid API or real post in this phase. Existing account/callback/receipt records preserved.

## Implemented
- Opt-in MARKETING_POST_QUEUE_ENABLED and MARKETING_POST_SCHEDULING_ENABLED (default false). Dedicated marketing-posts database connection/queue, retry_after900, one-attempt job, timeout600. Queue insert and dispatch approval transaction share default database; sync queue rejected and transaction rolls back.
- New dispatch table records approved revision, content/options, account versions and source hash. Only explicit new approval creates a dispatch; historical Scheduled rows never auto-adopted. Historical UI requires reconfirmation.
- Web acceptance performs no provider upload. Immediate returns Queued; future stores UTC instant and display timezone. Worker uses saved approval without creating another user approval. Actual publish/reconciliation remains in the existing adapters.
- Version checks invalidate cancelled/edited schedules. Execution locks prevent cancel/edit after worker begins. Source/account/actor changes stop before provider. Successful account outcomes preserved across remaining-target confirmation.
- Compare-and-claim dispatch execution, one-attempt provider transport, known-ID result polling only. Unknown initial submission without known task stops; stale executing workers become query-only, never return to initial upload. Bounded reconciliation needs attention when confirmation remains unavailable. Failure after recorded success cannot downgrade it.
- Commands marketing:publish-due and marketing:process-posts, minute scheduler entries behind flags. Finite worker drains only marketing-posts, not rendering/support/marketplace. No production scheduler was changed or validated yet.
- Approved English upload/progress/schedule/Calendar/attention flow integrated in existing shell. XHR displays real upload percent (99 until server confirmation), only upload-dependent actions disabled. Unload warning only during transfer. Polling saved state is GET-only under queued mode; browser departure does not drive processing. Settings/support details do not use Queue/Worker/Cron/callback jargon in user-facing guidance.

## Verification
- Focused queue + images + videos + Posts + Channels regressions:65 tests /370 assertions pass,11.19 seconds.16 queue tests include actual database queue consumer with fake provider, duplicate execution/sweep, future due/cancel/edit invalidation, UTC instant, account/source change, revoked owner/staff, configuration rollback, partial success, known-photo reconciliation, stale worker and success-before-worker-exit.
- Http::preventStrayRequests in provider tests. No cloud submission. JavaScript syntax, PHP lint, scoped diff whitespace and Blade compile pass.
- Node exercised production fromLocal: Kuala Lumpur10:00 maps02:00UTC; New York DST gap and ambiguous time rejected.
- Only190000 migration applied locally. Local opt-in flags enabled in untracked .env. CLI due sweep returned0; finite publishing worker exited with empty dedicated queue.
- Normal localhost browser: real existing15s/1,500,352-byte MP4 upload showed99% and locked Review/Save; completion restored controls. Saved draft28b2fae6-adea-4f51-9a40-35a50574b34d with retained copy/source, reloaded successfully. Real review shows Schedule for later, timezone and UTC summary; absent destinations correctly disable confirm. Browser error log empty.
- Local Channels directory has no connected accounts. Connected-account lifecycle is fake-provider acceptance, not live visual proof. Production cron/hosting limits and real private scheduled posting remain release gates. UI prototypes are not evidence of actual provider posting.

## Issue ledger
- Intentional initial red: missing outbox and synchronous web result; recorded separately.
- One unexpected regression run after enabling local env flags: legacy adapter tests inherited queued behavior (15 test cases, one root cause). Fixtures now explicitly select legacy mode, queued tests explicitly select queued mode; resolved.
- Encoding issue: default Windows file decoding plus UTF-8 writes produced mojibake. One whole-file repair failed on mixed encoding; targeted known sequences fixed, browser and JS syntax rechecked. Root count2, resolved.
- No unchanged blind reruns. Historical total costs/time unavailable. Token usage unavailable.

## Release prerequisites (not performed)
Synchronize only work/video-quality-review/post-queue-release-files.json after approval; migrate only new table; verify exact SHA and hosting PHP limits/CLI worker capability; enable provider/queue/scheduling flags deliberately; verify minute cron executes scheduler. Keep initial queue empty until a specific private test is approved. Verify intended local/UTC time, one provider submission and receipt. TikTok photo domain verification is separate and remains required.
