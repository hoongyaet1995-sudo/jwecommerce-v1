# Support AI business outcomes - local acceptance 2026-10-07

Base SHA: 7166fbb8bb47865bde638d3d0707382ee7e6ee08. No Phase 2 sync approval. Unrelated dirty files preserved.

## Delivered
Per-tool outcomes isolate failures and distinguish empty results from verification requirements. Saved partial-answer and clarification flags are exposed in Knowledge Scope. Personality provides multilingual business-query failure text. Mandatory identity and authorized read-only tools remain enforced. Bounded staff evidence includes tool outcomes, source IDs, decision, settings version and handoff reason; public chat API excludes it. Private order content never enters model or trace. Different queries may reuse an enabled tool, with max3 planner calls and duplicate-call deduplication.

## Validation
Backend278tests1736assertions passed29.80s. UI27tests passed3.46s. Admin build passed11.86s. Actual model mixed product/shipping/returns, unknown product + known shipping, controlled product outage, and mixed greeting/two policies passed across recorded runs. Local browser saved/refreshed/restored settings and expanded actual AI Reply Evidence. Screenshot output/playwright/support-ai-business-evidence-local.png. Synthetic QA writes rolled back except one labelled localhost QA conversation. No external customer messages sent.

## Limits
Tool completion means records retrieved, not full intent coverage. Keyword product search can retrieve generic related products; conservative actual P20 answer did not invent QUASAR999 availability. IDs do not prove sentence-level grounding. Cross-platform live acceptance pending. No embeddings, attachment analysis, pacing or Blast Campaign added. Issue ledger retained in 2026-10-07-home-ai-quality.md.

## Release gate
User sync approval pending. Include additive migration 2026_10_07_120000_add_support_reply_evidence.php and apply it in approved production root before exercising replies, with exact SHA/HTTPS verification. Scope phase source/tests/docs and manifest assets only; exclude local synthetic data/harnesses and unrelated work.
