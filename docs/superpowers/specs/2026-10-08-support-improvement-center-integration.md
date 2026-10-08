# Support Improvement Center — approved UI integration scope

Date: 2026-10-08
Status: English prototype approved. Functional scope presented for review before implementation.

## Purpose
Help staff turn recurring unanswered customer questions into approved FAQ answers. Published FAQs are retrieved by the existing AI knowledge service; this does not train or fine-tune the model.

## UI
Preserve the approved English prototype: overview, Support Performance, Answer Review, source conversation, editable question/answer/category, policy confirmation and publish/dismiss dialogs. Replace sample data with persisted records. Use existing Admin permissions and theme. Empty states must show actual absence of records, not demo metrics.

## Bounded workflow
1. Collect new persisted unanswered/partially answered customer text questions from the existing shared support reply evidence. Exclude greetings, empty/attachment-only messages, and provider/generation/delivery failures from missing-knowledge suggestions. Do not scan historical conversations or generate drafts automatically.
2. Staff can open the original authorized conversation and explicitly request a suggested answer after the conversation has been resolved. If no usable staff answer exists, leave the answer empty for staff to write rather than invent policy.
3. Store question, proposed answer, category, source references, timestamps and review status separately from FAQ. Redact private customer/order details before generating or storing draft content. Conversation text is evidence, not trusted system instructions or policy.
4. Staff edit and save a draft, dismiss it, or explicitly confirm policy correctness and publish. Publication must use existing FAQ validation and permissions. A repeated publish request must return the same published FAQ rather than create another.
5. Only published and enabled FAQs enter existing retrieval. Drafts and dismissed suggestions never enter retrieval. No promise of exact wording, instant model training or perfect semantic similarity.
6. Show real bounded-period/channel counts and linked examples. Separate missing knowledge from incomplete replies/service or delivery failures. Do not present an accuracy or satisfaction score.

## Acceptance and stopping point
- A new unanswered text question appears with correct channel and an authorized source conversation.
- A service failure is classified separately and does not silently create a FAQ.
- An edited draft survives refresh; dismissing it prevents its use by AI.
- Publishing requires explicit confirmation and existing publish rights; duplicate publication creates exactly one FAQ.
- Published enabled answer is retrieved for a matching/similar test question; the same question cannot retrieve the unpublished draft.
- Private details do not enter draft text and unauthorized users cannot read source conversations or publish.
- Real counts match stored evidence for the selected date range/channel; empty states are truthful.
- English light/dark UI and the source/edit/publish flow pass localhost browser acceptance. Existing FAQ/support flows still pass relevant regression tests.

Stop after this closed loop. No historical backfill, automatic publication, automatic policy invention, broad analytics, model training, new vector database, campaign dispatch, refunds or order edits. Sync/deployment requires the normal separate release authorization.

## Scope reconciliation
This document refines the existing V2-D quality boundary and approved prototype. Collection means an observed unanswered-question queue. Draft generation remains an explicit employee action from a resolved source conversation. This avoids per-message LLM expense and does not imply autonomous knowledge publication.
