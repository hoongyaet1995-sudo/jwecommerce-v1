# Marketplace AI suggestions through the shared platform engine

Status: proposed architecture for user review; implementation and real inference are not claimed. This does not replace the overall Marketplace goal or its Shopee-first sequencing.

## Current evidence and problem

- The approved MVP requires LLM mapping suggestions and explicit employee confirmation.
- `docs/superpowers/specs/2026-09-06-platform-ai-engine-direction.md` requires a shared engine with OpenAI, Gemini and Claude, and leaves configuration/budget decisions for design approval.
- There is no existing Laravel AI provider/task pipeline in the inspected application/configuration code.
- ZIP 32 `ConfirmSuggestionsModal` renders the required review journey, but calls the demo context synchronously and increments its success count without a persisted result.
- `OperationalMapping` intentionally disables suggestions. Its runtime still uses the accepted ZIP 30 page shell with selected ZIP 32 import components.
- `MarketplaceFoundationService::map` already serializes writes per store, verifies enabled variant ownership, prevents same-store duplicate mappings and records actor events. It is the canonical mapping owner to retain.
- User re-supplied the current ZIP on this task. Direct archive verification: `jw-ecommerce-dashboard (32).zip`, SHA256 `9285db80782f060dcf20f854ebaceeb44c96acb49642a8000f33d31d181be6d2`. `ConfirmSuggestionsModal.tsx` matches the extracted ZIP 32 file after line-ending normalization. The archived inventory component still uses platform-keyed `channelMappings`; the archive does not provide the required multi-store reconciliation/activation flow. A scan of the TypeScript/JSON source found no shared engine settings or Marketplace model invocation. Provider mentions in support/demo material do not establish such a capability.

Mode: OPERATIONAL_CLOSURE. Current engine/suggestions status: BACKEND_CONTRACT_GAP. Model inference, reviewed acceptance and real browser persistence must all be proved separately.

## Options and recommendation

1. Recommended: shared server-side engine, persisted suggestion jobs, and a Marketplace consumer with explicit reviewed acceptance. This matches the existing product direction and permits provider changes without duplicating mapping rules. It requires additive persistence and queue orchestration.
2. Marketplace-only provider calls are initially shorter, but duplicate future provider configuration/audit work and conflict with the shared-engine direction. Do not select this by default.
3. Deterministic SKU similarity alone is useful candidate retrieval, but does not satisfy the required LLM capability. Retain it only as input narrowing, not as a replacement or simulated AI result.

## Engine contract

- Implement `App/Services/Ai` with one structured-task interface and adapters for OpenAI, Gemini and Anthropic. Marketplace is a consumer, not the owner of provider credentials.
- Server configuration explicitly selects provider/model. Never silently choose a model, fall back to another provider, or send the same data to several providers. No automatic fallback is the initial policy.
- Credentials stay in server environment configuration; omit them from public DTOs, prompts, exception chains and audit payloads. Do not add credential inputs to an unrelated UI.
- Feature defaults disabled. Real inference requires configured credentials/model and a configured positive daily token allowance. Daily accounting is shared across workers, not an in-memory counter.
- Each task reserves its maximum token allowance before dispatch. Settle using reported input/output usage; when request outcome/usage is uncertain, retain the reservation conservatively. A timeout must not trigger an automatic repeat charge.
- Persist task identity, actor, consumer, provider/model, input hash, prompt-contract version, status, timestamps and usage. Store only validated structured results and sanitized error codes; no raw provider payload dumps.
- States: queued, processing, succeeded, failed, uncertain. A submitted request is not a successful recommendation. An expired worker lease becomes uncertain, not automatically reissued.
- Proposed initial bounds: at most 10 selected listings per task, at most 20 candidate variants per listing, 1,000 output tokens per listing. Provider/model input limits must be validated before dispatch. These are configurable limits, not claims of matching quality.

## Marketplace candidate and suggestion ownership

- An authenticated employee initiates a task for one explicitly selected connected Shopee store. The request contains saved listing IDs only. Lazada can reuse the consumer after its provider read acceptance; TikTok/Shopify are not enabled by this phase.
- Laravel loads current saved titles, SKU/variant attributes and enabled JW candidates. Do not send orders, customer details, tokens, images or unrelated catalog data to the model.
- Candidate retrieval uses exact identifiers first, then bounded text/attribute matching. Exclude variants already mapped elsewhere in the same store. Permit cross-store reuse.
- Duplicate/ambiguous identifiers do not imply automatic identity. The model may return no match and must choose only from the candidate IDs supplied.
- Treat every external title and attribute as untrusted data. The model has no tools and no authority to change mapping, inventory, prices, listings or fulfillment.
- Validate the structured response: task/listing identity, allowed candidate membership, bounded explanation, and score range. Reject unknown IDs, malformed output and unexpected fields. A provider refusal is a failure/no recommendation, never a success-shaped fake result.
- Persist suggestions with source listing and candidate fingerprints, target product/variant, engine task ID and review status. Scores are model estimates, not measured accuracy. The existing percentage presentation needs truthful copy ownership confirmed before enabling it.
- Catalog refresh, variant edits/disablement, conflicting mappings or source changes invalidate stale suggestions. The server checks current truth again on every acceptance even if the UI still shows an earlier result.

## Acceptance and concurrency

- Add a protected acceptance endpoint that accepts suggestion IDs and explicit confirmation, not arbitrary client-supplied model output.
- Lock in canonical store -> listing -> variant order, verify suggestion fingerprints/current unmapped state, and call `MarketplaceFoundationService` in the same transaction. Never overwrite an intervening manual mapping.
- A repeated acceptance of the same unchanged result is idempotent. Conflicting stale results return conflict and do not replace existing mappings.
- Batch acceptance returns per-item saved/already-saved/conflict/failed results. Count only confirmed persisted outcomes. Record the authenticated actor; do not accept an operator name from the browser.
- Cross-store reuse remains valid; same-store one-to-one uniqueness remains authoritative under concurrent manual mapping, import and AI acceptance.
- No stock or product publication changes are performed by suggestion generation or acceptance.

## UI source boundary

- Reuse ZIP 32 `ConfirmSuggestionsModal` DOM/classes and existing single-variant recommendation presentation. Inject asynchronous behavior and render server results using approved existing status/toast patterns.
- Do not wire the ZIP 30 synchronous batch handler to real suggestions: it currently reports loop counts as success. Route the action through the ZIP 32 reviewed journey.
- The current UI has acceptance controls but no inspected generation/budget/provider-settings journey. Before adding controls, inspect the full ZIP 32 archive for an exact matching pattern. A small generation action may reuse an existing action pattern only if it qualifies as L1; a new AI settings screen is a source gap and requires approved source.
- Preserve disabled/empty behavior until backend, configured model and real inference acceptance are ready. The official page must not show fixture recommendations.

## Required evidence and release boundaries

1. Provider adapter contract tests for valid results, refusals, invalid structure, timeout, redaction and usage accounting. These are mocked-provider evidence only.
2. Actual MySQL concurrency for task deduplication/budget reservation and AI-vs-manual same-store mapping; cross-store reuse and replay tests.
3. Tests proving staleness checks for source/title/SKU/variant changes and no inventory mutation.
4. Component tests proving the review modal waits for server results, reports partial failure accurately and never invokes demo persistence.
5. Real configured-provider inference using approved catalog data, persisted task/result identity, and no fake success. Each adapter requires its own real-provider acceptance before being advertised as available.
6. Authenticated browser: request -> status -> review -> selected confirmation -> refresh/re-login persistence, plus failure/stale-result handling and desktop/mobile source comparison.
7. No GitHub/Hostinger synchronization until phase approval. No change to JW Dashboard. Scheduler/automatic execution acceptance remains a final goal gate.

## Decisions requested

Approve this shared-engine design with explicit provider/model selection, no automatic provider fallback and required token budget before real inference. Provider credentials/model/budget can be supplied through a secure configuration flow after local implementation; do not paste secrets into the review response.

Inventory activation remains independently blocked on the approved multi-store reconciliation source; this AI work does not redefine or remove that requirement. Lazada application/callback ownership remains a separate pending decision.
