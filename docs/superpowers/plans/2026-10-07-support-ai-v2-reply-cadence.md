# Support AI V2 Reply Cadence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans for native implementation, or superpowers:subagent-driven-development if the user selects delegation. Steps use checkbox syntax for tracking.

**Goal:** Collect rapid customer inputs, deliver one grounded answer in at most three ordered segments, and cancel unsent segments when control or input changes.

**Architecture:** Persist a shared reply batch in MySQL and dispatch delayed support-queue jobs. Generate once through FaqAutoReplyService, then publish segments through existing provider transports; every publish revalidates source, settings and employee control. Storefront uses the same queue only when cadence is enabled.

**Tech Stack:** Laravel, PHP, MySQL, existing support queue, React Admin and existing storefront polling.

**Spec:** `docs/superpowers/specs/2026-10-07-support-ai-v2-reply-cadence-design.md`, approved by user after functional specification and acceptance discussion.

## Global Constraints

- Canonical local workspace first; preserve unrelated dirty changes and the pending Facebook memory repair.
- Default off. Collection window 2 seconds (0–3); interval 1 second (0–3); max segments 3 (1–3); hard collection bound 5 seconds.
- No random waits, automatic business mutations, Blast Campaign, additional budget controls or unsupported typing claims.
- Preserve complete conditions, links, references and text; maximum three segments; safety/verification/handoff responses remain single messages without ornamental pacing.
- No external provider call inside a DB transaction. Ambiguous send outcomes stop the batch for staff review; never blindly replay.
- Local commits/push/deploy are deferred until the project's synchronization approval, overriding generic per-task commit steps.

## Review Focus

- Out-of-order inbound jobs must not recreate obsolete replies or reset the five-second collection deadline.
- Takeover/new input between generation and transport reservation must cancel the remaining batch.
- Worker failure after provider submission must not replay an uncertain segment.
- Segmentation must not truncate conditions, lose actions, or split a URL.
- Off/default settings must preserve synchronous storefront and existing social behavior.

## Task 1: Validated settings and durable batch boundaries

**Files:** Create `app/Services/Support/SupportReplyCadencePolicy.php`, `app/Models/SupportReplyBatch.php`, `database/migrations/2026_10_07_220000_create_support_reply_batches_table.php`; modify `app/Services/SupportFaqService.php`; create `tests/Feature/SupportReplyCadenceTest.php`.

**Interfaces:** `SupportReplyCadencePolicy::settings(array $settings): array` returns enabled, collectionSeconds, intervalSeconds, maxSegments. Batch stores conversation, first/latest source IDs, language, settings/control snapshots, collection deadline, status, encrypted segment payload, next index and failure reason. Conversation/status indexes and deterministic segment UUIDs enforce bounded lookup and identity.

- [ ] Write settings roundtrip/range rejection/default-off tests and migration/model tests for encrypted segment data and independent conversation batches.
- [ ] Record initial intentional red and run `php artisan test --filter=SupportReplyCadence`.
- [ ] Implement policy, migration and model; integrate settings read/save under `replyCadence`, preserving existing optimistic version checks.
- [ ] Re-run focused tests; record result and issue counts in `docs/acceptance/2026-10-07-support-ai-v2-reply-cadence-local.md`.

## Task 2: Input collection and one grounded generation

**Files:** Create `app/Services/Support/SupportReplyCadenceService.php`, `app/Jobs/ProcessSupportReplyBatch.php`; modify `app/Services/Support/FaqAutoReplyService.php`, `app/Jobs/ProcessMessengerSupportReply.php`, `app/Jobs/ProcessWhatsAppSupportReply.php`, `app/Jobs/ReplyToAnalyzedSupportAttachment.php`, `app/Http/Controllers/Storefront/SupportChatController.php`; extend `tests/Feature/SupportReplyCadenceTest.php`.

**Interfaces:** `enqueue(SupportMessage $source, ?string $language=null): bool` reports whether cadence owns processing. `process(string $batchId): void` advances collection/generation. `prepare(string $batchId, SupportConversation $conversation, SupportMessage $source, string $answer, array $actions, array $trace, array $settings): void` stores validated output without publishing a full draft message.

- [ ] Write tests: three rapid messages produce one current request containing all three; sustained arrivals preserve first-source +5s deadline; stale jobs do not resurrect cancelled batches; disabled policy preserves existing paths.
- [ ] Run intentional red before implementing collection. Lock conversation/batch state briefly, use delayed queue jobs rather than sleeps, and check current source/control/settings before generation.
- [ ] Add optional internal batch ID to FaqAutoReplyService. Use combined bounded latest request while retaining source IDs for trace. When enabled, persist prepared answer through the shared service instead of exposing a complete draft bubble.
- [ ] Integrate social job entry points, synchronous storefront route and analyzed-attachment reply entry point. Inspect actual channel aliases; do not assume all storefront conversations use one channel value.
- [ ] Verify focused collection tests and existing SupportChatApi/attachment regressions.

## Task 3: Lossless segmentation and cancellable delivery

**Files:** Create `app/Services/Support/SupportReplySegmenter.php`, `app/Jobs/PublishSupportReplySegment.php`; extend cadence service and tests; modify existing social transports only if existing guards require an explicit batch boundary.

**Interfaces:** `SupportReplySegmenter::segments(string $answer, array $candidateSegments, int $maximum, int $channelLimit): array` accepts only lossless, ordered, complete candidate groups, otherwise uses a safe single-message fallback or reports an oversized-answer failure. `publish(string $batchId): void` publishes the next eligible segment. Suggested segmentation is returned in the same generation response, never a second generation call.

- [ ] Write tests for short single answers, three complete paragraphs, max-one behavior, unchanged text, URLs and condition groups, final-segment actions, provider length limits and oversized safe failure.
- [ ] Write tests for takeover, staff reply, archive/resolve, master/channel pause, settings version change and new input between sends. Assert unsent segments have no published message records.
- [ ] Write duplicate/reordered job and ambiguous-timeout tests. Assert each confirmed segment is submitted once and subsequent segments stop on failed/unknown outcomes.
- [ ] Run declared initial reds; implement durable segment identifiers, per-batch execution lock and per-send eligibility checks. Create each visible message only at publication. Carry evidence/source attribution across segments and attach actions to final segment.
- [ ] Reuse MessengerReplyService/WhatsAppReplyService sendGenerated, including existing one-attempt reservations. Record uncertain status for staff and do not proceed to the next segment until the prior one is confirmed sent/delivered/read.
- [ ] Run focused tests plus social transport safety suites. Add crash-resumption fixtures at draft creation, transport reservation and acknowledgement boundaries.

## Task 4: Employee controls and operational visibility

**Files:** Create `resources/admin/src/components/ai-auto-reply/AIReplyCadenceControls.tsx` and its focused test; modify `AIPersonalityTab.tsx`, `resources/admin/src/types/aiAutoReply.ts`, `resources/admin/src/data/defaultAIAutoReply.ts`, the existing config normalizer, and `resources/admin/src/context/AdminContext.tsx`. Extend existing staff reply-evidence presentation if needed for batch cancellation/failure status.

**Interfaces:** Config `replyCadence` uses the same four validated fields as policy. Existing save configuration request includes the object; reload normalizes server settings. Reuse approved icon/header/divider, toggle, slider and settings save patterns.

- [ ] Write controls tests for bounds, enablement, displayed defaults and serialization/normalization roundtrip.
- [ ] Implement controls in Personality & Tone without restructuring existing sections. Make delayed batch status and failed/uncertain sends visible through existing evidence/error patterns, not a new dashboard.
- [ ] Verify focused Vitest, TypeScript/build and authenticated localhost save/refresh in light and dark modes.

## Task 5: Local closure, release and real-channel acceptance

**Files:** Maintain `docs/acceptance/2026-10-07-support-ai-v2-reply-cadence-local.md`; add release/real-channel evidence only after synchronization authorization.

- [ ] Run one coherent backend suite covering cadence, understanding/memory, storefront, attachment handling and provider delivery guards; record commands, timing and failures before/after each run.
- [ ] Perform localhost browser tests for collection, short/long replies, customer interruption and takeover cancellation. Capture readable evidence of settings and actual bubbles. Confirm enabled queue worker operational behavior, not only queue fakes.
- [ ] Review scoped diff for sensitive payloads, unintended UI changes, duplicate sends, missing channel aliases and preserved default-off behavior.
- [ ] Report local verified scope and ask synchronization approval. Release accepted exact source only; verify local/GitHub/Hostinger SHA and HTTPS behavior.
- [ ] Reaccept the pending Facebook memory declaration/recall fixes and then cadence through authorized real Facebook messages. Close those exact issues only after evidence passes.
- [ ] Record WhatsApp Windows App acceptance as pending human test while native control is unavailable. Do not use WhatsApp Web. Instagram/TikTok remain explicitly qualified by real provider readiness/acceptance.
- [ ] Close this bounded phase only against the approved functional acceptance matrix; do not declare untested platforms passed.

## Self-review

Settings, input collection, generation, publication, cancellation, failure visibility and all approved acceptance scenarios have an owner above. No dependency introduces a new infrastructure service. Persistence uses sensitive-text encryption; provider calls remain outside transactions. The pending Facebook repair is preserved and its real-channel closure is separated from mock tests. Native execution is recommended because all tasks share the batch lifecycle and provider guards; execution selection/review remains required by the writing-plans handoff.
