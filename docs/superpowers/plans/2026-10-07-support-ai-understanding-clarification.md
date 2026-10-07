# Support AI Phase 1 Understanding and Clarification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. No subagents. Steps use checkbox syntax for tracking.

**Goal:** Handle greetings, mixed questions, language changes and contextual clarifications through a bounded understanding stage rather than requiring staff-maintained phrase lists.
**Architecture:** Extract the existing model planner into a validated understanding service returning a structured plan, language and conversational act. Execute the enabled read-only tools through Laravel, then compose either a conversational response or an evidence-grounded business response. Control/identity/action checks remain server-owned.
**Tech Stack:** Laravel/PHP/MySQL, existing AiConnectionService, React Admin. No new dependencies or vector database.
**Spec:** docs/superpowers/specs/2026-10-07-support-ai-professional-baseline.md; phase limited to understanding/clarification. Later retrieval indexing, pacing and actual handover completion are separate phases.

## Global Constraints
- Develop in C:\xampp\htdocs\jwstudio-ecommerce; preserve unrelated dirty changes and the existing uncommitted phrase-chip/toggle UI.
- No commit/push/deploy until a separately approved coherent release.
- No external customer messages in automated verification. Actual provider evaluation uses synthetic/local data and rollback.
- Mandatory server verification, disclosure whitelist, master/channel controls, human takeover, source/action validation, concurrency and idempotency stay authoritative.
- Attachments remain outside autonomous interpretation; Blast Campaign excluded.
- Guidance and business facts stay separate. No claimed action without a persisted executed action.
- Preserve approved UI structures; any hints/labels use existing patterns.

## Review Focus
- Mixed greeting + business questions must not collapse into social-only replies.
- Pure courtesy after an unresolved issue must not silently resolve or close the conversation.
- Language changes must not index missing localized fallback templates or crash private-order response formatting.
- Existing default/alias settings and outage paths must remain compatible without silently requiring staff to maintain lists.
- Invalid/disabled tool selection or prompt injection must fail safely, never widen access.

### Task 1: Safe role-labelled context
Files: app/Services/Support/SupportDialogueContext.php; tests/Feature/SupportDialogueContextTest.php (new).
- [ ] Before execution, read current producer message roles and attachment/action schemas; capture file hashes and branch/HEAD.
- [ ] Add deliberate red tests for a staff clarification followed by a customer fragment; latest order-navigation reply retained as sanitized context; attachment placeholders excluded; total context bounded; PII redacted.
- [ ] Run the isolated tests and record intentional red in the existing quality ledger.
- [ ] Include customer/AI/human_agent text roles. Keep chronological order and server-derived control state separate. Exclude attachments/private order content; do not simply remove all useful public navigation context. Specify bounded message count/text budget based on existing prompt limits.
- [ ] Run isolated tests; records are context only, never authoritative policy facts.

### Task 2: Structured understanding contract
Files: app/Services/Support/SupportUnderstandingService.php (new); app/Services/Support/UnifiedKnowledgeService.php; tests/Feature/SupportUnderstandingTest.php (new); tests/Feature/UnifiedKnowledgeTest.php.
- [ ] Write intentional red tests for pure unlisted greeting, mixed product+delivery question, courtesy+unresolved intent, latest-language request, prior staff product clarification, missing product/destination, unknown/disabled tools and malformed output.
- [ ] Service takes latest question, sanitized history, enabled tool descriptions and relevant staff settings. Returns tools (0-3), escalation enum, clarification enum, conversational act (greeting/thanks/none), validated language tag and needs_business_answer boolean. Tool queries remain bounded. No free-form business answer in this plan.
- [ ] Use the existing model planning call instead of adding another unconditional call. Raise its output budget only if measured JSON completeness requires it.
- [ ] Require the new contract from the actual understanding service. Keep a compatibility adapter for legacy tests/consumers only where explicitly documented; do not silently treat missing business intent as social-only.
- [ ] Model instructions cover multilingual/mixed intent and context. Optional aliases are hints, never the primary classifier or a requirement for coverage.
- [ ] Move tool execution into a method consuming the validated plan, preserving current tool/result limits and verification. Exact voucher lookup may accelerate execution after understanding, never truncate other questions.
- [ ] Unit/integration tests prove no unavailable tool executes and no raw query becomes SQL/URL/provider credentials.

### Task 3: Response and language integration
Files: app/Services/Support/FaqAutoReplyService.php; app/Services/Support/SupportResponseComposer.php (new if needed to keep orchestration readable); tests/Feature/SupportChatApiTest.php; tests/Feature/UnifiedKnowledgeTest.php; tests/Feature/OrderKnowledgeTest.php.
- [ ] Deliberate red tests: unlisted multilingual greetings answered; greeting+business uses sources; mixed questions yield supported partial answer plus one clarification; customer rejects prior answer; public tracking question vs private order lookup; request for human in disabled knowledge scope; provider outage; human takeover during generation; duplicate reply prevention.
- [ ] Replace linguistic greeting/thanks/negotiation/tracking early-return branches with understanding-driven selection. Keep safety rules enforced through tool scopes, composer restrictions and action guards. Do not remove unauthorized-discount refusal capability.
- [ ] Pure conversational replies may be generated without factual sources under a restricted instruction (no business claims/actions). Mixed messages require facts for all business claims. Greetings must not use a brand template containing unverified shipping or price statements as official evidence.
- [ ] Localized fixed fallback templates stay en/ms/zh; for other detected languages use a documented safe fallback. For successful generative answers, validated language tags guide model output. Server-private order templates remain localized by supported fallback language; expand translations only with explicit complete coverage.
- [ ] Clarification output is one useful question: resolve product from history first; do not ask for destination when it is already known. Clarification text must not request secrets. Supported facts remain visible when another part needs clarification.
- [ ] Preserve the final transaction ownership/control-version check, source ID/action validation and stored idempotency.
- [ ] Run all affected chat/FAQ/knowledge/policy/order/provider-job regression tests once after repairs.

### Task 4: Staff UI compatibility and acceptance
Files: resources/admin/src/components/ai-auto-reply/AIPersonalityTab.tsx; AIKnowledgeScopeTab.tsx; relevant UI tests; docs/acceptance/2026-10-07-home-ai-quality.md; docs/acceptance/2026-10-07-home-ai-cross-platform-messages.md.
- [ ] Label existing aliases as optional advanced hints; explain that common language/intent is automatically understood. Retain persisted values and API schema compatibility. Do not require new vocabulary from staff.
- [ ] Existing brand voice, primary language and scope controls remain consumed by runtime. No new unsupported switches.
- [ ] Focused UI tests + build; local authenticated screenshot confirms light/dark readability and save/reload. Restore temporary changes.
- [ ] Real-provider synthetic evaluation: minimum 16 baseline cases plus paraphrases outside aliases. Record actual route/tools, sources, reply, clarification and rubric result without tokens/secrets/customer data. Failures go into durable issue ledger; no blind retries.
- [ ] Report automated safety gates separately from measured model quality. Model mocks cannot prove language understanding.
- [ ] Supply user cross-platform messages after local gates pass; Instagram/WhatsApp/Facebook acceptance is pending until actual channel results are verified. TikTok remains approval-gated.

## Expected user-visible result
A customer can greet in an unlisted language, mix greetings with several business questions and continue earlier discussion without maintaining a trigger list. Staff configure the service's behavior and approved sources. Slow reply pacing, streaming, vector indexing and autonomous attachment analysis are not included in this phase.

## Execution handoff
Recommended method: execute inline in this checkout, task-by-task, preserving the existing UI phase. Implementation authorized by the user; see local verification status below.


## Local execution status — 2026-10-07
Tasks 1–3 implemented; context tests live in SupportChatApiTest.php rather than a separate context test file. Task 4 optional-hint labels and API compatibility implemented. Backend regression: 251 passed / 1538 assertions / 30.51s. Frontend: 8 passed; build passed / 12.16s. Local authenticated knowledge page renders the optional hint and saved switches after reload. Existing chip/toggle save and light/dark acceptance belong to the preceding UI phase; no settings changes were needed for these explanatory labels.
Actual-provider synthetic evaluation: 18 baseline scenarios, including out-of-list French greeting, mixed Chinese product/delivery, requested Malay response, unresolved assistance, identity refusal, discount refusal and staff/product-context follow-up. Initial failures and repairs remain in the quality ledger. Final staff-context answer identified BILLY height 202 cm; language-switch follow-up retained 14-day conditions in Malay. These are scenario results, not universal accuracy or live cross-platform acceptance. Evidence records replies/actions and rubric checks; detailed chosen tool/source traces are not all exported in the JSONL harness.
No legacy planner-contract adapter: tests migrated to the strict new contract. Exact voucher pre-planner shortcut removed. Mandatory escalation safety branches retained. Existing planner output cap is 350 tokens; no measured latency comparison or aggregate token total available. The numbered checklist above remains the original plan; where exact test/file breakdown differs, this execution status is authoritative.
Fixed fallback/clarification/private-order templates support en/ms/zh; other languages use English fallback on these paths. Successful generated replies accept validated language tags. No vector indexing, pacing or autonomous attachment interpretation in this phase. Actual Facebook/Instagram/WhatsApp manual acceptance and release pending; TikTok approval-gated. No commit, push or deployment performed.
