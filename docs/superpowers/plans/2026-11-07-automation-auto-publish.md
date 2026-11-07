# Automation Automatic Publishing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Start generation at the scheduled time and submit completed posts to selected accounts when Automatic Publish Status is enabled.

**Architecture:** Persist explicit publishing preferences on Automation settings. At scheduled generation completion, atomically submit generated platform drafts through MarketingPostQueue and record durable dispatch identifiers per occurrence. Manual generation never invokes this handoff.

**Tech Stack:** Laravel, Blade, existing marketing JavaScript, database queue, PHPUnit.

**Spec:** docs/superpowers/specs/2026-11-07-automation-auto-publish-design.md

## Global Constraints

- Default off; existing automations remain draft-only.
- Reuse platform permissions, consent, media support and connection-version checks.
- No real publish calls or paid generation in verification.
- No commit, push or deployment until separate synchronization approval.
- Preserve unrelated working-tree changes.

## Review Focus

- Repeated ticks and crash recovery never repeat publishing handoff.
- Pausing or editing during generation prevents stale publishing approval.
- Changed, expired or disconnected accounts preserve drafts and require attention.
- TikTok consent and privacy options must be explicit, never invented.
- Manual Generate Post remains draft-only even when automatic publishing is enabled.

### Task 1: Persist explicit publishing approval

Files: MarketingPostAutomations.php, automation editor/controller, public/marketing-studio/automation.js; new focused PHPUnit settings tests.

- [x] Add failing tests for default off, explicit account requirements, foreign/unavailable accounts, platform mismatch and required platform options.
- [x] Add boolean setting, destination IDs, connection-version snapshots and explicit per-account options using existing publishing validation contracts.
- [x] Render status and account controls using existing UI components; preserve saved values on reload.
- [x] Run focused settings tests and JavaScript checks.

### Task 2: Durable scheduled handoff

Files: MarketingAutomationRunner.php; new MarketingAutomationPublisher.php; additive occurrence publication migration; focused runner/publisher tests.

Interface: publishCompletedOccurrence(occurrence ID) returns persisted publication state and dispatch IDs; consumes completed generation post_ids and snapshotted approval.

- [x] Add failing mock tests for off/manual, success, duplicate tick, stale approval, disconnected account and queue failure.
- [x] Lock occurrence and Automation, recheck ownership/version/enabled settings and account versions, then submit platform-matching drafts using MarketingPostQueue::submit(..., true).
- [x] Record dispatch IDs in the same database transaction as submission; preserve drafts and record attention when validation fails.
- [x] Call handoff only from successful scheduled occurrence completion. Keep generation and publication statuses separate.
- [x] Run publisher and existing schedule/queue regression tests sequentially.

### Task 3: Acceptance and handoff

- [x] Show publication state in generation history; remove inaccurate unconditional draft-only messages where automatic publishing applies.
- [ ] Desktop/narrow-screen visual acceptance BLOCKED by earlier CUA URL-confidence policy stop; no new connection evidence and no workaround. UI DOM/interaction checks pass, but do not substitute for rendered acceptance.
- [x] Run relevant PHP syntax, Blade and JavaScript validation, and focused PHPUnit aggregate.
- [x] Record commands/results in docs/acceptance/2026-11-07-central-automation-cron.md; request synchronization approval only after local acceptance.
