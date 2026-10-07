# Support AI V2 reply cadence — proposed bounded delivery

Status: design for review; implementation and release not yet accepted.

## Purpose and scope

Improve readability and avoid replying to incomplete bursts of customer input. Accuracy, identity verification, grounding, and immediate employee control remain stronger requirements than human-like timing. This phase introduces input coalescing, natural paragraph delivery and bounded spacing, not invented employee identity, fake actions, random long waits or automatic business writes. Blast Campaign remains excluded.

## Current implementation and architectural impact

Facebook/Instagram and WhatsApp have separate queued reply jobs; generated replies share FaqAutoReplyService and source-based UUID idempotency. Storefront currently generates its text reply synchronously in SupportChatController. Consequently a single policy/service with durable reply batches is preferable to adding sleeps to each provider job. Existing provider delivery guards must remain active for every segment.

Alternatives: synchronous sleeps are simple but occupy PHP workers and cannot reliably cancel between sends. Independent provider-specific timing duplicates rules and drifts across channels. Recommended: a durable shared batch with delayed queue dispatch and per-segment guards. MySQL and the existing support queue suffice; no new infrastructure is required.

## Employee controls

Reuse existing card/header/toggle patterns in AI Personality & Tone, under Reply rhythm. Default off, preserving current delivery. Settings: enabled; input collection window (default 2 seconds, range 0–3); segment interval (default 1 second, range 0–3); maximum segments (default 3, range 1–3). Saving follows existing authenticated settings API/version contract. No employee phrase lists. No additional per-platform budgets in this phase.

## Runtime behavior

1. Collect contiguous unsent customer text messages after the last handled turn during the short window. Bound total wait to 5 seconds from the first input so continuous typing cannot starve a response. Use all collected messages as the current request, preserving distinct questions and existing coverage checks.
2. Generate one grounded answer. Segment only after grounding and coverage validation. Prefer paragraph boundaries; short answers remain one message. Keep pricing/conditions together, preserve URLs, lists and references, and do not truncate or discard text. At most 3 segments; excess paragraphs remain together. Provider-specific length ceilings remain enforced.
3. Publish each segment through the existing transport and a durable batch position. Segments share trace/source attribution; actions appear on the relevant final segment. The UI must not display a segment as sent before its actual publish step.
4. Before generating and before each send, check latest input, current settings version, master/channel switches, conversation state and employee control version. Employee reply/takeover, resolve/archive, or AI disable cancels pending segments. A new customer message cancels remaining segments and forms a new request, with delivered fragments recorded as already sent.
5. Unsupported typing indicators stay unavailable. Storefront may show an honest pending-reply indicator while an active request is processing; no suggestion that a human employee is typing.

## Reliability and permissions

Durable batch/segment identifiers and conditional state transitions prevent queue redelivery from duplicating confirmed segments or sending later segments out of order. Do not hold a database transaction while calling provider APIs. An ambiguous provider timeout is marked uncertain for staff review rather than automatically replaying a potentially delivered message. Retry behavior must preserve existing transport safety. Never regenerate facts separately for each segment.

Private order answers retain server-only authorization. Attachments, remembered preferences and prior AI prose do not become official store facts. Safety/verification and handoff responses remain single messages and bypass ornamental pacing.

## Acceptance and stopping boundary

- Two rapid messages yield one complete response covering both; a sustained burst waits no longer than the bound.
- A short answer stays one bubble. A longer grounded answer uses 2–3 meaningful segments without losing conditions or actions.
- Takeover, staff reply, resolve/archive, master/channel pause and new input during spacing cancel subsequent segments.
- Duplicate/reordered jobs do not duplicate confirmed sends or reverse order; provider failure/uncertainty remains visible.
- Enabled/disabled settings save and refresh; default-off delivery matches existing behavior.
- Storefront and authorized real Facebook acceptance recorded; WhatsApp desktop requires human testing while native app control is unavailable. Instagram uses transport regression coverage until a real-channel acceptance is available. TikTok is not declared supported without its provider connection.

Once these gates pass, stop this phase. Random delays, simulated typing on unsupported platforms, proactive campaigns and business mutations are not required for closure.
