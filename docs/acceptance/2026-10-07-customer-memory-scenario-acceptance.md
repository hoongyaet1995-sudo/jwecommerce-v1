# Customer memory scenario acceptance

Date: 2026-10-07. Released source: 2e987e4. No application code or production settings changed during this acceptance.

## Fresh verified results

- Backend: `php artisan test --filter='SupportCustomerMemory|SupportFaqTest|CustomerContactResolver'` — 91 passed, 566 assertions, 12.02 seconds. Covers same-identity cross-conversation retrieval, foreign-identity isolation, trusted cross-identity gates, expiry/disablement, encryption, staff edit/delete, tombstone replay suppression, resolution boundary, asynchronous ingress and order safety regressions.
- Frontend: CustomerMemoryCard/AICustomerMemoryControls Vitest — 2 passed, 2.35 seconds. These are focused component checks, not full channel browser acceptance.
- Actual configured LLM: four fresh synthetic Chinese extraction calls. Explicit language and furniture preference returned exact source quotes; unsafe password/order-verification override returned no items. Friend's price question yielded only open_issue, not preference. Raw output and reviewed results: `output/support-memory-acceptance-refresh.json`.
- Oracle correction: temporary question initially required zero items and failed once. That requirement was broader than the scenario's no-inferred-preference requirement. Original provider result retained; reviewed classification passed, without unchanged provider retries. Shell ledger interpolation failed once before backend execution and was corrected. No application defect proven by either diagnostic.
- Read-only production probe: memory enabled true, retention 30 days, both tables exist, memoryRows 0, support-ai database queue configured.
- Authenticated production Support page: Customer details panel exposes Customer Memory; current anonymous Guest displays No eligible memory. Screenshot `output/customer-memory-acceptance-entry-live.png`.

## Where to inspect

Staff: `/admin/support` -> select conversation -> Customer details -> Customer Memory. Eligible records show kind, text, expiry, source conversation and Edit/Delete controls. Settings are Knowledge (enablement) and Guardrails (retention); settings pages are not the memory record list.

Developers: this report, the linked JSON and local checkpoint. There is no dedicated acceptance-results dashboard in the current product.

## Remaining live acceptance

No new real Facebook/WhatsApp/customer messages were sent in this run. Therefore real provider ingress/extraction, real channel return-visit and actual trusted cross-platform reuse remain unverified. Local API/LLM tests do not close those gates. Production has no memory records yet; historical chats are not backfilled. Anonymous storefront guests are intentionally not eligible for long-term customer memory. Full V2 closure is not claimed.

## Subsequent authorized Facebook live acceptance (22:00 MYT)

User explicitly authorized using their Facebook and WhatsApp accounts. Facebook JW Studio conversation `18ee6372-ef61-4a8e-a3c0-93c61869e0d0` moved waiting_human -> human_mode -> open through confirmed Admin actions. Sent explicit Chinese language + light oak furniture preference from user's Facebook Messenger UI.

- Ingress and memory persistence PASS: extraction status done; production now has two memory records. Admin card shows language "以后请用中文回复我" and preference "我喜欢浅色橡木家具", source conversation and expiry 2026-11-06.
- Response acknowledgement FAIL: customer received AI-unavailable fallback. Production warning identifies ValidationException. Focused read-only understanding reproduction returned `conversation: Inconsistent social-only plan.` Root cause: current plan allows greeting/thanks/none but rejects non-business none/false, so preference-only requests have no valid social intent representation. Count: original live failure + diagnostic reproduction = 2 for this root issue. Do not rerun unchanged.
- Recall answer FAIL: sent "我之前说喜欢什么颜色和材质的家具？". Customer received FAQ-no-confirmed-answer fallback despite saved preference. Retrieval/answer routing does not currently close explicit memory-recall questions. Distinct root issue, live failure count 1. No application fix or deployment in this acceptance turn.
- Separate tiny provider diagnostic with 10-token output returned generic unavailable; not proof of invalid credentials/model because actual extraction completed. Do not classify this as an authentication blocker. Diagnostic extraction-table query initially used nonexistent id, corrected to created_at; count 1 resolved. Admin role selector mismatch count1 resolved using fresh UI state. Remote rg unavailable, used grep. These tooling issues are separate from application failures.
- Evidence: `output/customer-memory-facebook-live-failure.png`. Staff can inspect saved memory and AI Reply Evidence in Customer details. Initial zero-row probe above predates this live test.
- WhatsApp desktop remains untested: native app control is disabled in current tools. User instructed not to use WhatsApp Web; opened web login tab was closed, no WhatsApp message sent. Real cross-platform and new-conversation reuse remain pending. Facebook same-conversation recall is not cross-conversation proof.

Gate: do not advance/declare customer memory closed until preference acknowledgement and recall routing are repaired and real channel checks finish. Existing historical local test evidence remains valid but does not cover these live response intents.
\n## Repair checkpoint\nIntentional initial TDD red: php artisan test --filter=test_preference_and_memory_recall_have_valid_non_business_intents. Purpose: prove missing validated preference/recall contract before implementation. Existing unexpected counts remain acknowledgement 2, recall 1. Elapsed and tokens unavailable.
Initial red confirmed (invalid conversation enum, 1.78s). Implemented explicit personal intents, eligible-only recall prompt and no asynchronous save promise. Pre-run: php artisan test --filter="SupportUnderstanding|SupportCustomerMemory|SupportChatApi"; purpose intent/recall isolation and existing business gates. Unexpected historical counts preserved.
Result: 132 passed, 1 failed, 12.52s. Distinct fixture issue count 1: lightweight parent schema omitted social AI version columns; production code reached correct recalled reply but insert failed in SQLite. Added those columns to this social reply test only. Pre-run same command with concrete fixture correction.
Pre-run: php tmp/memory-intent-repair-check.php. Fresh configured LLM synthetic check of original Chinese declaration/recall and mixed private-order bypass. No real channel sends; output/memory-intent-repair-check.json.
Corrected fixture run: 133 passed / 834 assertions / 11.87s. Actual configured LLM: 3/3 passed original declaration, original recall, mixed private order remains order.lookup. Added explicit acknowledgement publication and recall/private-tool rejection checks. Pre-run focused suite plus FAQ/resolver regression: php artisan test --filter="SupportUnderstanding|SupportCustomerMemory|SupportChatApi|SupportFaqTest|CustomerContactResolver".
Final local result: 160 passed / 981 assertions / 13.39s; PHP syntax and scoped diff whitespace passed. Configured real LLM synthetic intent checks 3/3 passed. Local authenticated Support browser inspected; unchanged rendering available, but repaired real Facebook send/response has NOT been reaccepted. No commit, push or deployment this turn. Local repair ready for synchronization approval; Facebook issues remain open pending real provider response. Reply cadence design prepared at docs/superpowers/specs/2026-10-07-support-ai-v2-reply-cadence-design.md; no cadence implementation yet. Existing issue counts remain recorded, not silently reset.
