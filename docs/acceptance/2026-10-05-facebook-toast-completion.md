# Facebook transient notices — 2026-10-05

Baseline HEAD: 14c2d9a3090debb0aa16849eccf8fbf85458b4eb. Unrelated inventory, assets and documentation work preserved.
Scope: all 12 mapped OAuth notices and load/select/check/disconnect/delete request failures use existing Toast. Remove transient inline duplicates. Persistent connection status and diagnostics remain in connection details.

Verification ledger:
- Frontend build passed; existing large chunk advisory. Browser warning and provider_error Toast verified; callback query consumed; refresh does not repeat callback Toast. Screenshot: output/playwright/facebook-hub-warning-toast.png.
- 2026-10-05 16:37:53 +08:00: focused test preparation used wrong relative output paths; new file absent, only two existing tests ran. Corrected absolute paths. Preparation failure count 1, resolved.
- 2026-10-05 16:38:27 +08:00: focused test run failed because jsdom lacks HTMLDialogElement.close, one environment root cause affecting 13 cases. Unexpected failure count 1. Added native dialog test shim; no production code workaround.
- Follow-up focused test: all mapped callback Toasts plus repeated operation errors and existing account tests. Result recorded below.

Current changes local only; synchronization pending user approval per AGENTS.md. Elapsed time/token usage unavailable.
`nFollow-up: 15 focused tests passed at 16:38:59. Added modal Toast regression: Toast follows native dialog open/close so it is visible above the backdrop and returns to the page after closing. Final run: 16 tests passed; frontend rebuild passed (large-chunk advisory only).
`nRelease approved by user. Isolated HEAD build with only the two Toast source files overlaid passed. Production baseline matched local/GitHub 14c2d9a; clean tracked server state. Backup: /home/u762348670/backups/facebook-toast-20261005-1641/before.tar.gz. No migration or environment change.
