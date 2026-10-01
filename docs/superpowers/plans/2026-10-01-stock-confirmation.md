# Stock confirmation recovery: approved localhost plan

Baseline: main `495cd7b8cd7740cfd0522643dc79aede86d71b4d`; no tracked dirty files. Preserve existing untracked samples, preview build, screenshots and tmp evidence. User approved four bounded improvements on 2026-10-01; no commit/push/deploy.

1. Add failing tests for same-snapshot status/quantity/error, task-scoped manual checks, idempotent replay/concurrency and stale identity rejection. Observe failures before implementation.
2. Extend existing receipt JSON with an explicit allowlist of accepted-write/readback diagnostic fields. Preserve errors in existing attempts; clear current errors after exact confirmation. No schema migration.
3. Reuse UncertainStoreStockReconciler's store lock, task/version/listing/facility identity and observe-only publisher. Add permission-checked operation/task confirmation action, immutable request identity, cooldown and attempt audit using existing storage. Never run transfer continuation or publish from this action.
4. Project latest Shared confirmation tasks with quantities, status, error, timestamp/count from the same overview snapshot, independent of the latest-100 history window. Bind cards and completion view to these projections; use Confirming stock versus failed/exhausted states and an explicit Check confirmation action. Refresh after reconciliation. The additional approved chevron change is click/Enter/Space only, with hover/focus closed and internal check actions preserving expansion.
5. Verify SQLite-memory/array cache-session/fake queue/stray-HTTP isolation. Run backend and mocked UI tests, baseline-difference type check, build and intercepted localhost browser acceptance. Preserve Shopee/Lazada/Independent behavior.
6. Record contract, phase acceptance, exact hashes, limitations and screenshots. Upload screenshots to Library with confirmed IDs; verify localhost entry. Keep source/build changes local only.
