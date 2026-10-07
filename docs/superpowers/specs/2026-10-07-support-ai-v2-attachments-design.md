# V2-A — private attachment understanding

Status: written design for review, not implemented. Parent: `2026-10-07-support-ai-v2-design.md`.

## Observed gap and chosen approach

Existing `SupportAttachmentService` validates and stores private images, PDFs and WhatsApp audio. `DownloadSupportImage` downloads provider media asynchronously but does not start analysis after completion. `FaqAutoReplyService` explicitly skips document messages. `SupportDialogueContext` excludes attachments. `AiConnectionService` exposes text generation; media capabilities must be added explicitly and cannot be inferred from a working text connection.

Use an asynchronous analysis service and provider capability adapters, then feed attributed results into the existing V1 retrieval/tool/answer flow. Do not send files directly from the browser to an AI provider. Keeping extraction separate from answering allows staff visibility, deduplication and safe cancellation. A synchronous webhook approach would hold provider requests open and cause duplicate or timeout behavior; it is rejected.

## Capability and limits

- Image: identify visible objects, damage and readable text. Match possible products against the live catalogue; never claim visual similarity proves an exact SKU or warranty eligibility.
- PDF: extract and answer about text; scanned pages require a verified vision-capable adapter. Unsupported scans receive an explicit fallback, never an empty successful result. Both text and scanned fixtures are required before claiming complete PDF acceptance.
- Voice: WhatsApp inbound audio becomes a transcript, then a normal text answer. No AI audio replies in this delivery. Ambiguous speech prompts a short confirmation; do not invent missing words or rely on fabricated confidence percentages.
- Preserve transport limits already implemented: images 5 MB, PDFs 10 MB, audio 16 MB / five minutes. Analysis defaults: PDF at most 10 pages; audio at most 120 seconds. Larger files remain accessible to staff but are not automatically analyzed. Admin may adjust analysis limits within server-supported bounds; cannot weaken transport validation.
- Derived analysis retention defaults to 30 days, selectable 7–90 days. Retention deletes derived analysis only; it does not silently change original attachment retention.
- Separate image/PDF/audio switches default off. Analysis also requires global AI, channel AI, ready media and verified provider capability. Unsupported provider capabilities are visibly unavailable, rather than silently routed to arbitrary models.

Exact adapter/model selection is an implementation prerequisite: verify official provider capabilities and configured connection without exposing credentials. A provider lacking the required feature does not block ordinary text support.

## Data contract

Add a scoped analysis record associated with attachment, source message and merchant. Store kind, status, source SHA-256, settings version, provider/model identifier, timestamps, sanitized failure code and encrypted structured result. Result includes transcript/extracted text or visual observations, limitations and provenance. Do not store secrets, signed download URLs, unrestricted reasoning or verified-order facts in the result.

Statuses: `pending`, `processing`, `ready`, `failed`, `unsupported`, `expired`. A unique attachment/hash/configuration key prevents duplicate provider calls. Intentional reanalysis creates a versioned attempt; it is rate limited. Existing attachment status remains the transport status, independently visible from analysis status.

## Producer → service → database → consumer

1. Customer message binds a validated attachment through the existing ownership rules. Staff outbound files do not trigger customer-intent analysis.
2. Local-ready media schedules analysis after transaction commit. Provider-downloaded media schedules only after validated private storage becomes ready.
3. Analysis job checks merchant, conversation, source, switches, supported model and limits, claims the deduplicated attempt and reads the private file server-side.
4. Adapter returns a bounded structured result. Service validates its schema and stores encrypted results with source provenance. Download failure and analysis failure remain separate.
5. The answer pipeline receives caption plus attributed analysis as one customer turn, not two independent requests. Attachment text is untrusted source material, never a system instruction. Existing FAQ/catalogue/order tools remain the source of business facts.
6. Before sending, recheck source relevance, conversation status, settings version and staff takeover. A newer customer message invalidates a stale automatic response; retain useful analysis for staff, and use it only in the current turn's context when applicable.
7. Send through the existing channel delivery service. Idempotency covers the answer as well as analysis. Staff UI shows analysis state, transcript/observations and sanitized failures alongside existing attachment controls.

## Privacy and safety invariants

- File access and analysis access require existing conversation/merchant authorization; guest access remains scoped to the owning session.
- A receipt, order screenshot, phone number spoken in audio or name in a PDF never verifies customer identity. Existing order login/email verification stays mandatory.
- Instructions inside files cannot change tools, disclose other customers or override employee settings. Business claims still require retrieval/tool evidence.
- No background analysis of old conversations on deployment. New enabled inbound messages only; explicit staff reanalysis is scoped and audited.
- Human takeover disables automatic answers immediately, including jobs already processing. Queue cancellation alone is insufficient: final send must recheck state.
- Provider timeouts are not blindly retried when billing/completion is ambiguous. Record failure and permit bounded explicit retry; deterministic pre-provider failures can be retried after their cause is resolved.
- Analysis failures preserve the attachment and conversation. Customer receives one concise configured fallback where AI is still active; staff sees actionable sanitized status. No repeated error message loops.

## Admin changes

Reuse `AIKnowledgeScopeTab` for independent capability toggles and provider readiness; reuse `AIGuardrailsTab` for analysis limits/retention. Keep mandatory safety boundaries server-enforced. Reuse existing attachment rendering/customer context for status and extracted content; no replacement of the approved support layout. Use existing settings validation/versioning and save/refresh behavior.

No mandatory phrase lists. Employee settings describe allowed capability and business policy, while the LLM interprets language and customer intent.

## Acceptance gate

- Image: clear product, damaged item, unreadable screenshot and embedded malicious instructions.
- PDF: text, scanned, password-protected, malformed, over-page-limit and customer-private information.
- Voice: English/Malay/Chinese, silence/noise, ambiguous content and over-analysis-limit.
- Pipeline: delayed download, duplicate webhook/job, caption with attachment, newer message during analysis, takeover while processing, disabled channel and changed settings.
- Isolation: different merchant/session, expired result, staff outbound attachment and receipt used to request private order facts.
- Verification: focused Laravel/provider-contract tests, relevant Admin tests/build, authenticated localhost save/refresh and rendering, actual-model synthetic fixtures, then prepared Storefront/Facebook/WhatsApp channel acceptance.
- No claim of TikTok/Instagram live success without permissions and actual message evidence.

Record each test command/purpose/change and result in the phase ledger. Preserve cumulative unexpected failure accounting; pause known credential/environment blockers rather than blind retries. No deployment until the coherent local phase is accepted and synchronization is approved.

## Design self-review

Checked against approved V2 scope: no business write, automatic publication, Campaign dispatch or vector database introduced. Existing source presentation preserved. Transport and analysis states separated; data ownership, takeover races and duplicate replies have explicit gates. Remaining implementation prerequisite is provider capability verification, not assumed compatibility. This document does not claim an implementation or passing acceptance.
