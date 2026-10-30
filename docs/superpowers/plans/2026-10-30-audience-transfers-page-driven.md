# Page-driven Audience Import / Export implementation plan

**Goal:** Extend the approved Sync interaction and portable execution to CSV Import and Export without Cron. User explicitly requested the same behavior; no new UI design round or sending capability.
**Architecture:** Existing task/row/private-part tables and 100-row transactions remain. All task execution requires an authorized checkpoint continuation. Import still stops at preview until explicit confirmation. A small transfer page controller drives bounded requests, restores same tasks and offers Continue after network interruption.
**Tech stack:** Laravel, MySQL, existing Blade/Bootstrap patterns, vanilla JS.

- [x] TDD: import/export no queued jobs; worker cannot advance; preview/confirm/apply checkpoints; duplicate stale requests; frozen export membership and private output; expiry/permission guards.
- [x] Backend: remove worker dispatch from start/confirm, generalize current continue route and checkpoint status, preserve sync contract; remove automatic Audience processing schedule only. Keep unrelated workers and file retention schedules.
- [x] UI: existing import mapping, preview and export options retained; shared transfer progress in approved inline panel; disable duplicate submissions while active; pause/hide, return/reload discovery, failure and download actions.
- [x] Fake JS tests: interrupted continuation does not start new task, hidden page stops, preview never auto-confirms, final download state and lock release.
- [x] Normal localhost: synthetic CSV preview -> explicit confirm -> completed; leave/return and export -> private CSV. Update acceptance/closeout. No real customer lists, sends, payment, commit, push or deployment.

Baseline 9b6a63ebf218b042f860a9773f95f37b74997363; canonical files scoped clean, unrelated dirty inventory/storefront work retained. Existing Audience issues resolved; new issue ledger below persists test failures. Initial red explicitly intentional. Token/total elapsed unavailable.

Local browser accepted with synthetic data; no release performed. Final checks recorded in Audience acceptance ledger. Online removal of the legacy dedicated Audience cron waits for explicit sync approval.
