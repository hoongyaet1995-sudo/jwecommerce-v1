# Support AI V2-A Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Analyze private inbound images, PDFs and WhatsApp voice safely through the existing grounded support pipeline.

**Architecture:** Separate private attachment transport, attributed extraction and customer answering. An idempotent queued analysis record feeds a single existing support turn; final send rechecks ownership, current source and human control.

**Tech Stack:** Laravel, PHP, MySQL, existing support queue, React Admin and existing AI provider connections.

**Spec:** `docs/superpowers/specs/2026-10-07-support-ai-v2-attachments-design.md`

## Global Constraints

- Preserve approved UI patterns, V1 text behavior and unrelated dirty files. No release in this plan without synchronization approval.
- Image/PDF/audio analysis defaults off; require master and channel AI switches.
- Transport limits remain 5 MB images, 10 MB PDFs, 16 MB / five minutes audio. Analysis defaults: 10 PDF pages, 120 seconds audio, 30-day derived retention (7–90 selectable).
- No identity verification from attachments, business writes, outbound AI audio, campaign dispatch or automatic FAQ publication.
- Provider compatibility must be verified explicitly; a text connection does not establish media support.

## Review Focus

- Multiple inbound attachments must yield one coherent answer rather than a reply per download.
- Takeover during a provider call must prevent the eventual send.
- Caption and extracted instructions must remain untrusted customer material.
- Timeout with ambiguous provider completion must not cause uncontrolled repeat billing.
- A ready download with failed analysis must remain viewable and distinguish the two failures.

## Task 1: Configuration and scoped analysis lifecycle

**Files:** create `app/Models/SupportAttachmentAnalysis.php`, `app/Services/Support/SupportAttachmentAnalysisPolicy.php`, `database/migrations/2026_10_07_180000_create_support_attachment_analyses_table.php`; modify `app/Services/SupportFaqService.php`; test `tests/Feature/SupportAttachmentAnalysisTest.php`.

**Interfaces:** policy `settings(array $settings): array`, `eligible(SupportAttachment $attachment, array $settings): bool`; model belongs to attachment and source message. Table has merchant key, source hash, settings version, attempt key (unique), status, encrypted result, provider/model, sanitized failure code and started/completed/expires timestamps.

- [ ] Add failing tests for defaults off, saved settings round-trip, limits, encrypted result, unique attempt and tenant isolation; record the intentional initial red in the phase ledger.
- [ ] Implement policy bounds: PDF pages 1–20, audio seconds 15–300, retention days 7–90. Preserve transport limits separately.
- [ ] Run `php artisan test --filter=SupportAttachmentAnalysisTest`; require all tests pass before the next task.

## Task 2: Provider capability adapters and bounded extraction

**Files:** create `app/Services/AI/SupportMediaClient.php`, `app/Services/Support/SupportAttachmentAnalyzer.php`; modify the verified supported clients under `app/Services/AI`, `AiConnectionService.php` and `ProviderTransport.php` only as required; test `tests/Feature/SupportMediaProviderTest.php`.

**Interfaces:** `SupportMediaClient::capabilities(string $model): array`, `analyze(string $key, ?string $baseUrl, string $model, string $kind, string $mime, string $bytes): array`; analyzer `analyze(SupportAttachment $attachment, array $settings): array` returning observations/extracted text/transcript, limitations and provenance.

- [ ] Inspect active connection provider/model without printing credentials. Verify media request schemas against official provider documentation; record supported and unsupported combinations before adapter code.
- [ ] Add contract tests for image, text/scanned PDF, transcription, unsupported model, malformed response, prompt-injection content and timeout. Compatible custom endpoints remain unsupported until independently verified.
- [ ] Implement supported adapters using server-side private bytes and bounded output. Enforce PDF page limit before analysis with the existing qpdf runtime; never silently analyze only the first pages as a full document.
- [ ] Enforce audio duration before provider calls. Never substitute a guessed transcript or numerical confidence.
- [ ] Run `php artisan test --filter=SupportMediaProviderTest`; require contract tests pass. Actual-provider synthetic tests are separately recorded and do not follow from mocked success.

## Task 3: Queue orchestration and single-turn answering

**Files:** create `app/Jobs/AnalyzeSupportAttachments.php`, `app/Services/Support/SupportAttachmentAnalysisService.php`; modify `DownloadSupportImage.php`, `SupportAttachmentService.php`, `FaqAutoReplyService.php`, `SupportDialogueContext.php`, `app/Http/Controllers/Storefront/SupportChatController.php`, `MessengerInboundService.php`, `WhatsAppInboundService.php`, and existing generated-reply transports as necessary; test `tests/Feature/SupportAttachmentAnalysisFlowTest.php`.

**Interfaces:** service `schedule(SupportMessage $source): void`, `context(SupportMessage $source): ?array`; job constructor `__construct(public int $sourceId)` with one billable attempt per claimed analysis key. Schedule only after transaction commit and validated downloads.

- [ ] Add tests for duplicate webhook/jobs, multiple attachments arriving separately, caption plus media, older source, staff outbound file, disabled channel and configuration changes.
- [ ] Wait until all source attachments have a terminal transport/analysis state before one consolidated answer; use existing source-based reply ID and channel dispatch.
- [ ] Pass extraction as attributed untrusted context into V1; preserve caption and existing factual source checks. Replace document skip only for eligible analyzed turns.
- [ ] Test takeover during analysis and between answer generation/send. Final send checks status/control version and latest source; stale jobs keep staff results but send nothing.
- [ ] Test download failure versus analysis failure and ambiguous timeout; emit one configured safe fallback only while AI still owns the conversation.
- [ ] Run `php artisan test --filter=SupportAttachmentAnalysisFlowTest` and existing image/document/WhatsApp/understanding tests; record every unexpected failure by root cause.

## Task 4: Staff visibility, settings and retention

**Files:** modify `SupportAttachmentController.php`, `SupportAttachmentService.php`, `routes/web.php`, `resources/admin/src/components/ai-auto-reply/AIKnowledgeScopeTab.tsx`, `AIGuardrailsTab.tsx`, existing support attachment renderer and types; create `app/Console/Commands/PruneSupportAttachmentAnalyses.php`; register scheduling through the repository's existing console scheduler entry.

- [ ] Add authorization tests for analysis reads/retry; denied merchant/session access returns no result. Explicit retry is rate limited and audited; no file URL or secret is returned.
- [ ] Add independent capability toggles and readiness using existing settings patterns. Add numeric analysis limits/retention in guardrails; preserve mandatory safety controls.
- [ ] Add staff analysis status/transcript/observations and sanitized errors adjacent to existing media without restructuring support layout.
- [ ] Test pruning expired derived results preserves original files and messages; test save/refresh retains valid settings.
- [ ] Run focused Vitest tests, `npm run lint` and `npm run build` in `resources/admin`; Laravel focused tests must pass. Verify authenticated localhost light/dark rendering and settings persistence.

## Task 5: Acceptance and phase closure

**Evidence:** `docs/acceptance/2026-10-07-support-ai-v2-attachments-local.md` is the durable issue/test ledger. Record commands, purpose, changes, result, cumulative unexpected failure counts and next step. Measured time only; unavailable token usage stays unavailable.

- [ ] Use synthetic image, text/scanned PDF and English/Malay/Chinese/noise audio with the configured real provider. Record unsupported combinations as gaps, not passes.
- [ ] Exercise prepared Storefront, Facebook and WhatsApp end to end with actual media, staff takeover and private-order requests. Unsupported inbound kinds remain explicitly unavailable; TikTok/Instagram approval is not inferred.
- [ ] Run bounded text V1 regression plus tenant, idempotency and takeover safety tests. No unrelated test repairs or blind reruns.
- [ ] Self-review against all spec requirements, document remaining environment/provider blockers, and present local evidence for user acceptance.
- [ ] Ask synchronization approval only once the coherent local phase is ready; release exact accepted SHA under the delivery contract.

## Plan review

### Local execution checkpoint

Implementation and focused local evidence are recorded in the phase ledger. Final related regression: 147 tests / 980 assertions; build passed. Real synthetic image/text PDF answers and Malay voice confirmation-follow-up passed. Browser settings persistence and private analysis rendering verified. Checkbox items combining several scenarios remain unchecked until every listed scenario has evidence; do not infer full acceptance from implementation.

Outstanding before phase closure: actual upload/channel ingress and social transport acceptance; noise beyond silence; complete adversarial response/timeout and configuration-change cases. V2-B/C/D remain subsequent phases. No synchronization authorized for V2-A yet.

Coverage includes capability settings, provider limits, encrypted provenance, multi-file sequencing, grounded responses, tenant access, staff takeover, retry policy, retention and real-channel acceptance. V2-B/C/D are intentionally outside this implementation plan. Recommended execution: native in this session, preserving the canonical local checkout and unrelated work. No implementation or tests claimed completed by writing this plan.
