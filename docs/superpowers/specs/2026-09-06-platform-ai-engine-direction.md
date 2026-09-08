# Platform AI Engine — recorded product direction

Status: future direction requested by the user; not implementation or release approval.

## Confirmed direction

JW Ecommerce will have a shared platform AI Engine supporting OpenAI, Google Gemini and Anthropic Claude. Marketplace AI Suggestions is one consumer of that engine, not a separate Marketplace-only provider integration. Other modules may use the shared engine under separately approved scopes.

Preserve the supplied Marketplace AI recommendation presentation and its intended single/batch review entry points during ZIP integration. Do not represent demo recommendations as real model results. Provider credentials belong on the server, never in client bundles or model prompts.

## ZIP 29 evidence

Archive: jw-ecommerce-dashboard (29).zip
SHA256: 8730B48C0CD6CA3BF74A3FD04E222C2749502B98306822B93C48B1DCE4E81BDD

- src/components/marketplace/MarketplaceMappingPage.tsx: AI Suggestions filter, suggestion count, Accept AI Suggestions, per-variant suggested SKU and match score/reason.
- src/components/marketplace/ProductMappingModal.tsx: AI Match Recommendation and suggested variant selection.
- src/context/AdminContext.tsx: batchAcceptSuggestedMappings consumes existing suggestedMatch objects.
- src/data/marketplaceMockData.ts: pre-seeded suggestedMatch objects with confidenceScore values. These are demonstration data, not evidence of LLM inference or calibrated accuracy.

This inspection did not establish a working Marketplace model invocation pipeline. No ZIP 29 production cutover, model request or provider setup is performed by this note.

## Recommended future boundaries — for design review

- Use a provider-independent task contract with provider adapters, permission checks, timeouts, cost limits and sanitized audit records.
- For SKU matching, deterministic identifiers and variant compatibility should narrow candidates first; model recommendations must reference existing candidate IDs and explain their basis.
- A model recommendation never overrides per-store one-to-one validation, enabled-variant checks or transaction/concurrency safeguards. Cross-store reuse remains allowed.
- Single or batch acceptance requires deliberate staff confirmation, current-state revalidation and per-item results. No autonomous stock deduction, publication or mapping replacement.
- Treat remote titles, descriptions and images as untrusted data, not instructions. Minimize data sent to providers; never include seller credentials or unrelated customer data.
- Do not advertise an arbitrary model score as a measured accuracy percentage. Define scoring and evaluation before enabling production scores.
- Until the engine is available, use truthful unavailable/empty states; no fabricated suggestions or fake success.

The immediate OAuth/catalog/manual-mapping phase remains unchanged. Model selection, fallback behavior, settings UI, budgets and other AI tasks need their own approved design before implementation.
