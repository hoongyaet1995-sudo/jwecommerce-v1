# Central Automation Cron Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task. No subagents requested. Follow repository release approval rules.

**Goal:** Generate review-only AI Drafts on opted-in schedules through a single Social Hub cron.

**Architecture:** Hub launches allowlisted same-host client Artisan runners; clients reserve occurrences and advance durable generation states. Existing signed transport is retained for later remote support.

**Tech Stack:** Laravel, MySQL, existing provider state machine, PHP CLI, fixed argument-array process launcher.

**Spec:** ../specs/2026-11-07-central-automation-cron-design.md

## Global Constraints

- Asia/Kuala_Lumpur schedules; inclusive 30-minute catch-up; no historical backfill on activation/edit.
- Existing Automations paused; no implicit paid execution from pairing; Drafts only.
- Never resend provider outcomes marked requesting/unknown.
- Do not replace unrelated project cron jobs or change source/provider behavior.
- No commit/push/deploy until phase verification and synchronization approval.

## Review Focus

- Concurrent dispatch and lease expiry cannot duplicate paid steps.
- Pause/edit while queued cannot execute stale authorization.
- A stopped Hub cannot trigger unbounded historical work on recovery.
- Missing client directory/credentials cannot block another client.
- Partial generation and saved Drafts survive restart without regeneration.

### Task 1: Client occurrence reservation

**Files:** Create migration for schedule status/activation timestamps and occurrence table; `app/Services/Marketing/MarketingAutomationSchedule.php`; `tests/Feature/MarketingAutomationScheduleTest.php`. Modify Automation model/service validation.

**Interface:** `reserveDue(CarbonImmutable $now): array` returns newly reserved occurrence IDs; database unique key automation_id/scheduled_at_utc. Store settings snapshot, generation UUID, local schedule label and UTC scheduled time.

- [ ] Write frozen-clock tests for Monday/multi-day schedules, Malaysia midnight, inclusive 30 minutes, expired skip, prospective activation/edit, paused state and repeated/concurrent reservation.
- [ ] Declare initial red in phase ledger, run `php artisan test --filter=MarketingAutomationScheduleTest`, verify expected missing behavior.
- [ ] Implement transactional reservation and uniqueness; existing records migrate paused. Use persisted activation/version boundaries rather than a rolling now-only scan.
- [ ] Rerun focused tests; record result and failures under repository budget.

### Task 2: Client dedicated runner

**Files:** Create `app/Services/Marketing/MarketingAutomationRunner.php`, command `marketing:automation-tick`, tests `MarketingAutomationRunnerTest.php`; integrate existing `MarketingPostAutomations` without duplicating provider calls.

**Interface:** `tick(CarbonImmutable $now): array` returns safe counts/IDs/statuses. CLI command exits within configured runtime boundary and touches only the AI Automation lane.

- [ ] Test two completed draft groups across ticks, concurrent claims, pause before paid step, revoked owner/AI connection, multi-image partial completion, provider unknown and restart during local save using fake provider.
- [ ] Verify initial expected red, then implement durable claim, current authorization checks, prepare/step continuation against the same run ID and safe receipts.
- [ ] Verify unknown/requesting steps never call provider again even after lease expiry; confirmed local work only may resume.
- [ ] Run new tests plus existing Automation/FreePost tests. No paid call.

### Task 3: Social Hub dispatcher

**Workspace:** canonical local `C:/xampp/htdocs/jw-social-hub` must have its own instructions/baseline read before edits; current writable scope does not include it. Obtain required filesystem access rather than editing only the server.

**Files proposed in Hub:** `app/Services/Social/AutomationDispatcher.php`, fixed runner registry/config, dispatcher receipts migration, command `social:automation-tick`, feature tests. Confirm existing Hub conventions locally first.

**Interface:** `dispatchDue(CarbonImmutable $now): array`; client runner transport launches only registered canonical roots and fixed `marketing:automation-tick` argument arrays. Initially maximum two runners globally/one per client. Persist per-client dispatch state independently.

- [ ] Test disabled/missing-capability clients skipped, invalid root rejected, two healthy clients independent of a failing client, duplicate ticks, bounded concurrency, no prompt/key in receipts and lease recovery without duplicate child launch.
- [ ] Verify red before implementation; implement registry validation, leases and bounded process supervision. Do not expose arbitrary paths or commands through client input.
- [ ] Test process behavior against dummy local runners; do not invoke production or paid generation.

### Task 4: Management UI and joint acceptance

**Files:** Automation editor/controller/JS and index views; Hub client overview view/controller following existing design. Tests extend Automation page/JS and Hub dispatcher tests.

- [ ] Add Active/Paused and next scheduled time, remove timing-preparation-only copy after real execution exists; preserve manual Generate Post and Recent generations. Require valid day/time and owner permission on activation.
- [ ] Test Run due tasks observes time/uniqueness and does not force generation, paused defaults, changing schedule, status links and manual/scheduled separation.
- [ ] Verify desktop and390px UI, mocked end-to-end Hub→client→Drafts and recovery scenarios. Record commands/results/screenshots in durable ledger; preserve known unrelated failures without blind reruns.
- [ ] Read actual Hostinger panel cron inventory; map existing responsibilities before proposing one new cron entry. Do not remove existing schedules as part of this feature.
- [ ] Present local acceptance and request synchronization/canary authorization. After approval deploy exact accepted commits to both applications, run only reviewed migrations, then verify one explicitly enabled scheduled canary. Keep all other Automations paused.

## Self-review

Tasks cover the approved catch-up, activation, per-client isolation, unique occurrences, no-retry unknown-state rule, worker availability and user-visible history. Remaining operational gate: actual Hostinger panel schedule and process/runtime limits must be measured before enabling; no assertion of current cron coverage. No product implementation or cron activation performed by this plan.
