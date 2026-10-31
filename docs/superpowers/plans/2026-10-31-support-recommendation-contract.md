# Shared support recommendation implementation plan

Goal: Improve generic specification extraction, catalogue filtering, clarification and generation diagnostics without changing themes, UI, provider configuration or store data.

Architecture: Extend existing understanding tool steps with optional literal attribute constraints; enforce them in ProductKnowledgeService before recommendation limits. Keep unknown compatibility unknown. Separate concise prose instructions from the structured answer token budget. Preserve existing policy, private-data and source gates.

- [x] Add cross-industry fake tests: spaced measurements, exact sizes, excluded attributes, malformed output and provider truncation.
- [x] Normalize measurements and apply explicit attribute constraints against public catalogue identity/specifications, never arbitrary descriptions as instructions.
- [x] Carry constraints through existing tool routing and follow-up; forbid relaxing an original constraint.
- [x] Budget complete structured output independently of prose, validate payloads and record safe diagnostic codes without raw customer/provider content.
- [ ] Run existing support regression tests and normal localhost read-only page checks; no paid model calls or deployment.

Acceptance: no per-product/brand/theme branches; missing evidence cannot become a compatibility claim; failure must distinguish malformed output from provider interruption. Actual LLM extraction quality remains pending explicitly authorized real-model evaluation.

Baseline: canonical HEAD 7c63f9770365da4597d991735093ed1a4476eb64. Existing large dirty worktree preserved. Target support files were clean before this phase. UI privacy work and all unrelated changes excluded.

Issue ledger: initial test red will be intentional; unexpected failures recorded separately. Elapsed/token usage unavailable unless measured.
