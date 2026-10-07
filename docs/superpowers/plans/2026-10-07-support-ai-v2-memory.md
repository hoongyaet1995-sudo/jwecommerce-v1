# Support AI V2-B Customer Memory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Give recognized customers bounded conversation continuity with inspectable, correctable and deletable memories.
**Architecture:** Typed encrypted MySQL records with immutable identity scope and message provenance. A policy resolves eligible identity before extraction or consumption; the existing grounded reply pipeline consumes bounded historical context.
**Tech Stack:** Laravel/PHP/MySQL, existing AI connection, React Admin.
**Spec:** docs/superpowers/specs/2026-10-07-support-ai-v2-memory-design.md

## Global Constraints
- Local first; baseline5aa7bce; preserve unrelated dirty files. No commit/push/deploy until phase acceptance and synchronization approval.
- Defaultoff, retention30days allowed7–90, at most5 active records and2000 context characters.
- Anonymous storefront stays within current conversation. Memory never grants order access.
- Only language, explicit product preferences and unresolved customer requests; no private order/identity/payment facts or attachments.
- Preserve existing UI patterns and human takeover rules.

## Review Focus
- CRM merge after capture must not broaden visibility without trusted current evidence (Task1).
- Delayed extraction after staff deletion must not recreate deleted source (Task2).
- Preference correction arriving while extraction runs must supersede older data (Task2).
- Archive/resolve must suppress unresolved requests without deleting valid preferences (Task3).
- Model text containing instructions or sensitive details must be rejected as memory, not executed (Task2).

### Task1: Identity policy, persistence and settings
Files: create app/Models/SupportCustomerMemory.php, app/Services/Support/SupportCustomerMemoryPolicy.php, database/migrations/2026_10_07_190000_create_support_customer_memories_table.php; modify app/Services/SupportFaqService.php; test tests/Feature/SupportCustomerMemoryPolicyTest.php.
Interfaces: policy scope(SupportConversation): ?array returns merchant_key, identity_hash, customer_id and trusted cross-platform eligibility; model encrypted content with kind, source_message_id, source_conversation_id, expires_at, revoked_at and staff attribution.
- [ ] Write initial failing tests for defaultoff, rangevalidation, merchant/anonymous isolation, conflictingidentity denial and postcapturemerge restrictions.
- [ ] Record intentional red and run php artisan test --filter=SupportCustomerMemoryPolicyTest; expected missingfeature failures.
- [ ] Implement schema/model/policy/settings; immutable scope and deleted-source tombstones remain enforceable through expiry. Do not treat bare customer_id as identity proof.
- [ ] Run same test after concrete changes; expectedallpass.

### Task2: Controlled extraction and lifecycle
Files: create app/Services/Support/SupportCustomerMemoryService.php, app/Services/Support/SupportCustomerMemoryExtractor.php, app/Jobs/ExtractSupportCustomerMemory.php; test tests/Feature/SupportCustomerMemoryExtractionTest.php.
Interfaces: extractor extract(string $customerText): array of kind/text/sourceQuote candidates; service capture(SupportConversation, SupportMessage): void, eligible(SupportConversation): array, context(SupportConversation): array. Enqueue once per eligible customer text message, not provider webhook repeats or attachment contents.
- [ ] Write tests for explicitlanguage/preference, unresolvedrequest, sensitive/instruction rejection, sourcequote validity, duplicatejobs, staffdelete/races, latercorrection and malformed/providerfailure.
- [ ] Declare initial red, run php artisan test --filter=SupportCustomerMemoryExtractionTest.
- [ ] Implement bounded structured extraction through existing AI connection; validate against source and allowlisted kinds. Failclosed without interrupting chat. Queue idempotency and conditionalwrites preserve newer staff edits; no blind retries or historical backfill.
- [ ] Run focusedtest untilpass with issueledger tracking.

### Task3: Reply consumption and authorized staff operations
Files: modify app/Services/Support/SupportDialogueContext.php, app/Services/Support/FaqAutoReplyService.php and existing inboundtext entry points; create app/Http/Controllers/Admin/SupportCustomerMemoryController.php; modify routes/web.php and app/Http/Controllers/Admin/SupportChatController.php; test tests/Feature/SupportCustomerMemoryFlowTest.php.
Interfaces: GET/PATCH/DELETE /admin/api/support/conversations/{conversation}/memories/{memory?}; PATCH accepts bounded text; DELETE revokes and suppresses source. Reuse existing admin and conversation access checks. Memory response excludes hidden hashes.
- [ ] Write tests for returnvisit consumption, trustedcrossplatform and conflictrejection, staffauthorization/edit/delete, expiry/disable, resolution, historicalprompt injection and unchangedorderverification.
- [ ] Declare initial red and run php artisan test --filter=SupportCustomerMemoryFlowTest.
- [ ] Implement eligible bounded context labeled customer history; live tools remain authoritative. Capture asynchronously independent of replies. Resolve/archive invalidates unresolvedrequest records. Audit staff changes without logging plaintext sensitivecontent.
- [ ] Run focusedtest and existing SupportFaqTest regression; expectedallpass.

### Task4: Existing Admin controls and local acceptance
Files: create resources/admin/src/components/support/CustomerMemoryCard.tsx; modify CustomerContextPanel.tsx, integration/supportChat.ts, existing Knowledge/Guardrails components, settings types/defaults; create CustomerMemoryCard.test.tsx.
- [ ] Add componenttests for display/edit/deleteconfirmation, deniedoperation and refreshstate; verify switches/retention savecontract.
- [ ] Add compact existing-theme CustomerMemorycard with source/expiry, edit/delete confirmations; defaultoff switch in Knowledge and retention in Guardrails. No inboxredesign.
- [ ] Run focusedVitest, relevantLaraveltests and npm run build; compare TypeScript diagnostics against baseline rather than claiming existing global failures clean.
- [ ] Apply migration locally; verify authenticatedbrowser setting save/reload and synthetic customer memory edit/delete/returnvisit. Restoredefaultoff and syntheticfixtures only.
- [ ] Run syntheticactualmodel extraction with explicit positive/negative statements; no realcustomerhistory upload. Record screenshots/provider evidence and pendingliveplatform acceptance.

### Task5: Expiry cleanup and phase closure
Files: create app/Console/Commands/PruneSupportCustomerMemories.php; modify routes/console.php; create tests/Feature/SupportCustomerMemoryRetentionTest.php; maintain docs/acceptance/2026-10-07-support-ai-v2-memory-local.md.
- [ ] Test expiredpayload pruning and deleted-source suppression lifetime; implement daily command without removing original conversations/files.
- [ ] Verify current scope, databaseconsumer, authorization/failure paths and browser evidence; selfreview identity/race boundaries. Record each run before execution and cumulative issuecounts3/6/9.
- [ ] Report completed versuspending acceptance and request synchronization only after local checks. No automatic release; no fullV2 completionclaim.

## Self review
Every design section maps to a task. All five reviewfocus cases have explicit testowners. No crossplatform consumer may bypass scope(); extraction/consumption share eligible(). Existing orderverification remains unchanged. Execution recommended native in current session because tasks share policy/service interfaces and the approved environment is the existing canonical checkout.

## Execution checkpoint 2026-10-07
Tasks1–5 implemented locally. Extraction/retention/race tests consolidated in SupportCustomerMemoryFlowTest; settings persistence additionally tested in SupportFaqTest. Local checks and actual synthetic provider evidence recorded in docs/acceptance/2026-10-07-support-ai-v2-memory-local.md. Release and actual channel acceptance remain separate pending gates. No automatic commit/push/deploy.
