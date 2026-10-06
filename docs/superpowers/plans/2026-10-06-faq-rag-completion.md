# FAQ RAG completion implementation plan

> For agentic workers: use superpowers:executing-plans for inline execution. No deployment or automatic publication of store policies. Existing canonical checkout is required by user.

Approved design: docs/superpowers/specs/2026-10-06-faq-rag-completion-design.md. Baseline27f495b7c1c5e62653b4125d31a34100271725a4. Preserve dirty inventory/checkout/assets and UI. User requested start after design approval; this plan awaits execution selection under skill gate.

## Evidence and content blocker
Read-only local and production audit2026-10-06: local FAQ enabled semantic6/6 active with keywords, no exact duplicate questions; shipping2, returns1, sizing1, payment1, others1. Production FAQ enabled semantic0 records. Category headings are not policy facts. No local FAQ copying or automatic policy invention. Audit outputs tmp/faq-rag-audit-local.json and tmp/faq-rag-audit-live.json. Production business-policy answer acceptance requires owner-approved actual policy content. Product/discount/order sources are independent of FAQ count.

## Task1: evaluation baseline and content coverage
- [x] Read current configurations and compare aggregate counts without customer transcripts/secrets.
- [ ] Add synthetic approved-policy fixture and tests/Feature/FaqRetrievalModesTest.php, covering exact/lexical, English/Chinese/Malay paraphrases, inactive exclusion, shipping exception siblings, irrelevant query, budget overflow and injection.
- [ ] Add docs/acceptance/2026-10-06-faq-rag-completion.md with persistent root issue ledger and retrieval evaluation table. Count initial intentional RED separately. Record missing production policy content as content gate, not code failure.
- [ ] Create owner-fillable policy coverage checklist (delivery coverage/cost/time, return eligibility/time/exclusions, payment, warranty, cancellation, care/contact). Unknown facts remain missing; production FAQ stays unchanged.

## Task2: mode-aware bounded retrieval
Files: new app/Services/Support/FaqQueryExpansion.php; modify FaqKnowledgeSearch.php; modify tests/Feature/FaqRetrievalModesTest.php and UnifiedKnowledgeTest.php.
- [ ] Intentional RED tests for distinct mode semantics and malformed model output fallback.
- [ ] Keep default/strict lexical compatibility. semantic performs max3 short multilingual normalized query expansion via existing AiConnectionService, bounded JSON validation, combines deterministic scores over active records; no generated policy text is accepted.
- [ ] deep adds max-one bounded candidate ranking call using existing IDs only (max10 candidates) before policy-sibling completion. Ranking failures retain lexical/expanded results. Enforce candidate and context byte limits, no silent policy truncation.
- [ ] Use explicit per-request expansion/ranking call ceilings and classify provider fallback in internal logs without raw transcripts; preserve existing daily budget, document latency/cost tradeoff. No external vector service.
- [ ] GREEN unit/integration tests proving inactive records, invalid IDs, empty expansion and provider outage behavior.

## Task3: role-labelled safe dialogue
Files: new app/Services/Support/SupportDialogueContext.php; modify FaqAutoReplyService.php and UnifiedKnowledgeService.php; tests/Feature/SupportChatApiTest.php, SupportDocumentMessagingTest.php.
- [ ] Intentional RED for prior assistant clarification plus customer short followup, bounded role history, private-number redaction and PDF-caption exclusion.
- [ ] Build last6 permitted role-labelled text turns capped by bytes/characters; assistant history is contextual and cannot be business evidence. Exclude document-bearing turns and any unsupported/failed-media placeholder. Preserve current string history interface for existing consumers by explicit role prefixes; latest_question remains authoritative.
- [ ] Permit one targeted clarification with validated structured response only when ambiguity is established; no source-less business claims. Keep ordinary no-evidence fallback distinct. Do not broaden order access, execute actions or override human ownership.
- [ ] Handle greetings/thanks through small multilingual deterministic response set without retrieval; do not classify mixed substantive questions as greetings.
- [ ] GREEN pause/concurrency/handoff/media/order verification regressions.

## Task4: evidence and local acceptance
- [ ] Run focused FAQ/unified/support chat/document/order tests once after changes; broad tests only if new failures justify.
- [ ] Run synthetic model-backed evaluation with existing configured connection if available, without private customer transcripts. Mocked tests and actual model quality reported separately. If provider auth/connection unavailable pause those checks immediately.
- [ ] Authenticated localhost knowledge settings and real synthetic chat acceptance; preserve approved layout, no unsolicited copy/theme changes.
- [ ] Review full diff, compare before/after labelled cases, record remaining content/provider gates. Do not claim complete RAG from mocks or empty production knowledge.
- [ ] Report locally verified scope; ask separate synchronization approval before commit/push/Hostinger deploy.

Execution recommendation: inline in this chat; no subagents needed. Commits deferred to synchronization approval per project contract. Elapsed total/token cost unavailable until measured. Existing attachment interpretation remains off; Blast excluded.
