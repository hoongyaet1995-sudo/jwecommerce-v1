# Support Improvement Center Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Replace the approved English prototype with a persisted question-to-reviewed-FAQ workflow.

**Architecture:** Read the existing support_messages.ai_trace evidence through a bounded service. Keep drafts in a separate MySQL table and publish through SupportFaqService::save under a transaction. Reuse the approved React presentation inside the existing Admin shell.

**Tech Stack:** Laravel/PHP, MySQL, React/Tailwind, PHPUnit, existing Admin build.

**Spec:** docs/superpowers/specs/2026-10-08-support-improvement-center-integration.md

## Global Constraints
- All product copy is English; retain approved prototype layout and classes.
- No historical backfill, automatic generation/publication, broad analytics, refunds or order edits.
- Draft publication uses existing Super Admin FAQ rights. This application uses one store per deployment; do not invent tenant IDs.
- Drafts remain outside retrieval until explicitly published and enabled.
- Preserve dirty unrelated work; localhost only until separately approved sync.
- Observe the project's persistent test-failure ledger. Record measured time; token usage unavailable unless measured.

## Review Focus
- Concurrent FAQ edits and duplicate clicks must preserve unrelated settings and publish one FAQ.
- A missing original conversation must not cause an unauthorized source read.
- Prompt injection or private order/contact text must not become authoritative policy.
- Provider failures, incomplete coverage and absent knowledge must remain distinct.
- A customer question without a staff answer must produce an empty editable draft, not an invented answer.

## Task 1: Bounded evidence and collection
**Files:** Create app/Services/Support/SupportImprovementService.php; app/Http/Controllers/Admin/SupportImprovementController.php; database/migrations/2026_10_08_120000_create_support_faq_drafts_table.php; app/Models/SupportFaqDraft.php; tests/Feature/SupportImprovementTest.php. Modify routes/web.php.
**Interfaces:** overview(Request filters) returns counts and linked examples for 7/30 days and an allowed channel; GET /admin/api/support/improvement. Collection starts at persisted feature activation time, never backfills. New customer text questions are derived from reply trace and their source messages, not keyword guesses.
- [x] Write tests for no_match/partial versus generation_unavailable/coverage_incomplete/delivery failures, date/channel bounds, greetings excluded, and truthful zero counts.
- [x] Record intentional TDD red, then run C:\xampp\php\php.exe artisan test --filter=SupportImprovementTest.
- [x] Implement bounded persisted evidence queries; retain authorized conversation references, without duplicating private transcript text in the question list.
- [x] Run focused tests and record results in docs/acceptance/2026-10-08-support-improvement-center-local.md.

## Task 2: Draft review and safe publication
**Files:** Extend Task 1 service/controller/tests; use existing app/Services/SupportFaqService.php and app/Services/Support/UnifiedKnowledgeService.php redaction without replacing existing retrieval.
**Interfaces:** GET /admin/api/support/improvement/drafts; POST /admin/api/support/improvement/drafts with sourceMessageId; PATCH /admin/api/support/improvement/drafts/{draft} with question, answer, category, version; POST /admin/api/support/improvement/drafts/{draft}/publish with version and policyConfirmed=true; POST .../{draft}/dismiss with version. All use existing Admin middleware and explicit Super Admin publication checks.
**Data:** draft stores source conversation/customer-message references, sanitized question/answer, category, review version/status, creator/reviewer, published FAQ id and timestamps; unique source message prevents duplicate suggestions.
- [x] Test resolved-source requirement, invalid source, no-staff-answer empty draft, private text redaction, draft exclusion from retrieval, permission rejection, edit conflict and repeated publish.
- [x] Run declared red; implement sanitized explicit suggestion from resolved staff response. Do not incur per-message LLM calls. Staff must review it; never claim the source proves policy.
- [x] Implement publish by locking draft and support_faq setting, merging one deterministic FAQ id into current items, validating through SupportFaqService::save. Preserve categories/settings/version, and return the same FAQ on duplicate publication. Record reviewer/time.
- [x] Run focused tests and a retrieval test proving only the published enabled FAQ is returned.

## Task 3: Approved English UI with real data
**Files:** Create resources/admin/src/components/ai-auto-reply/SupportImprovementCenter.tsx and resources/admin/src/integration/supportImprovement.ts; modify resources/admin/src/App.tsx, resources/admin/src/context/AdminContext.tsx, resources/admin/src/components/ai-auto-reply/AIAutoReplyNavigator.tsx and routes/web.php.
**Interfaces:** official entry /admin/ai-auto-reply/improvement; React API adapter consumes Task 1/2 endpoints, existing CSRF/session handling. Link source to /admin/support?conversation=<authorized id>.
- [x] Add meaningful UI tests for empty/error states, saved edits after reload, publish confirmation, conflict refresh and channel/date filters.
- [x] Transfer approved prototype presentation into Admin shell; replace fixtures/counters/reset-preview banner with actual response/loading/empty states. Preserve overview, performance and review views.
- [x] Show Generate draft only for eligible resolved sources; allow writing an empty answer. Published/dismissed statuses and counts must refresh from server.
- [x] Run focused frontend tests and the existing Admin build; record output.

## Task 4: Local closure
**Files:** docs/acceptance/2026-10-08-support-improvement-center-local.md.
- [x] Run scoped regression tests for FAQ settings and shared Support reply paths.
- [x] Apply only the additive local migration, then test authenticated Admin light/dark views, original conversation link, draft edit/save/refresh, dismiss and confirmed publish with disposable test records.
- [x] Verify an unpublished draft is absent from retrieval and a published enabled answer is available for a matching/similar question. Compare displayed counts against persisted evidence.
- [x] Record actual tests, screenshot paths, permissions/failure evidence, limitations and unresolved prerequisites. Do not call a local build a production release.
- [x] Stop when the approved acceptance criteria pass; report coherent scope and ask separately about synchronization. Commit/push/deploy only with release approval.

## Self-review
All spec requirements map to Tasks 1–4. Task 2 covers privacy, idempotency and source integrity; Task 1 separates technical failures from missing knowledge. No sample metrics or extra subsystem is included. Outstanding cadence release prerequisites remain a separately tracked item, not implicitly repaired or deployed by this plan.

## Recovery baseline
HEAD c56c7854da45fc52c6194a387d09a5242ed5c108. Existing unrelated dirty changes include CheckoutService, FaqAutoReplyService, UnifiedKnowledgeService, tests and generated assets; preserve them. This planning pass changes documentation only. Exact code/file hashes and active issue counts must be recorded at implementation start.
