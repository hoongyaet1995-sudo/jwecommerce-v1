# Shared support recommendation local acceptance

Scope: existing support understanding -> read-only retrieval -> grounded answer -> trace. No product/brand/theme special branches, UI edits, provider configuration changes, data writes, paid calls or release.

Implemented:
- Optional validated literal required/excluded attribute values in existing understanding tool steps. Customer context can resolve references; unknown compatibility remains unknown and requires one essential clarification.
- Shared measurement normalization, short size terms, SQL constraint prefilter before bounded candidates, exact postfilter against public titles/specifications. Original named target protection remains. Follow-up retains constraints. Ambiguous alternative/negative measurements are not automatically positive filters.
- Concise customer prose instructions separate from complete JSON envelope: answer budget 1200-2400, understanding budget 1000. Saved existing prose setting retained. More output allowance can increase actual paid usage when later authorized; no usage incurred in acceptance.
- JSON/schema diagnostic codes; provider incomplete, output limit and refusal detection; safe message/conversation correlation and budget in existing trace/log. No raw prompt/provider bodies/secrets recorded. No new automatic generation retry.

Verification:
- Final main suite: 122 tests / 682 assertions passed, 12.57 seconds. `output/support-contract-tests.txt`. HTTP external model boundaries mocked; no network model generation.
- Tests cover capacities, inch/ml dimensions, apparel XL vs XXL, required/excluded attributes, >50 candidates, active/draft boundaries, multilingual fake understanding, follow-up preservation, existing permissions/private-data/coverage gates, invalid JSON and provider truncation.
- Final routing-only suite after multi-question measurement isolation: `output/support-contract-routing-final.txt`.
- Localhost homepage HTTP 200. Browser inventory available but claiming local tab returned `browser.user is unavailable`; normal-page interactive verification remains pending, not accepted by HTTP alone.
- Real LLM extraction quality, recommendation quality, full variant-specific attribute filtering, arbitrary currency/budget filtering and a broader industry evaluation set are not proven by this phase. Constraints currently match product titles/PDP specifications; do not infer variants/compatibility from absent fields.

Issue ledger (historical individual run timestamps unavailable; recorded 2026-10-31 13:51 +08; token accounting unavailable):
1. Initial new contract tests: intentional TDD red (missing classes), then passed after implementation.
2. Candidate-window and incomplete-provider tests: intentional red, then passed after SQL prefilter/provider guard.
3. ProductKnowledge legacy price tests: unexpected failure count 1; fixture omitted current purchase_mode/price_display columns. Added explicit fixture defaults; passed without changing commerce rules.
4. Existing mocks expected former budget/empty scope shape: unexpected runs 2 for initial routing expectations, then one run for separate answer-setting/follow-up expectation. Updated budget expectations and preserved empty-scope compatibility. Resolved; no unchanged reruns.
5. Alternative measurement and follow-up validator tests: intentional red, then passed after parser guard and validated requirements forwarding.

Release: LOCAL ONLY. Preserve existing unrelated dirty work, original catalogue, images, settings and production. Do not mark production fixed or real-model acceptance complete.

## Approved synchronization

User approved synchronization on 2026-10-31. Release scope is the support contract files and tests only; existing privacy/UI and unrelated dirty work excluded. No migrations, builds or environment edits needed. Paid real-model and interactive browser acceptance remain pending; deployment does not close those gates.
