# Support AI V2 — delivery boundary

Status: scope approved by user; written design awaiting review. No implementation or release claimed.

## Purpose and baseline

Improve customer understanding and continuity without reopening the completed text V1 indefinitely. Development starts in this repository; synchronization requires a separately accepted local phase and user release approval.

Baseline observed on 2026-10-07: branch `codex/social-crm-identity`, HEAD `ee27c7abcdb10533cd1027b947763f460a5d9812`. Existing unrelated Checkout, marketplace tests, documentation and generated asset changes must be preserved. V1 acceptance remains closed; V2 adds separate evidence.

## Four bounded deliveries

| ID | Delivery | Completion boundary |
| --- | --- | --- |
| V2-A | Image understanding, PDF analysis, WhatsApp voice transcription | Private inbound attachments produce attributed analysis, then use the existing grounded text answer pipeline; limits, failure paths and takeover cancellation are verified. |
| V2-B | Customer memory | Confirmed customer association only; language, explicitly stated preferences and open issues with source and expiry; staff can inspect, correct and delete. Memory never grants access to orders. |
| V2-C | Reply rhythm | Configurable brief pacing and semantic segmentation, bounded message count; staff takeover cancels unsent segments; unsupported channel typing indicators are omitted. |
| V2-D | Knowledge improvement and quality | Resolved conversations can suggest attributed FAQ drafts; employees approve publication. Existing Admin gains a compact quality view and FAQ review queue, rather than a separate application. |

Implementation order is A → B → C → D. Each delivery receives focused local acceptance before the next delivery expands behavior. The final V2 acceptance repeats cross-channel text, attachment, privacy and takeover scenarios.

## Explicit exclusions

- Refunds, order edits and other autonomous business writes remain human operations.
- No campaign scheduling, audience dispatch or bulk sends. Prepare only a documented interface for platforms, tags, consent and channel capability; platform presence is not marketing consent.
- No automatic FAQ publication, unrestricted permanent memory or large analytics/BI application.
- No mandatory vector database. Introduce one only through a separately evidenced retrieval shortfall.
- TikTok and Instagram live acceptance depend on actual permissions; implementation or fake-provider tests do not establish provider approval.

## Settings and UI ownership

Reuse the approved Admin theme and existing pages. Knowledge owns attachment capability switches; guardrails owns bounded limits and retention; personality owns reply pacing. Customer context owns memory visibility. FAQ owns draft review. Quality receives a small navigator entry using existing patterns. Mandatory tenant isolation, verified order access and human takeover protections cannot be disabled by employees.

Ordinary employees should not maintain language greeting lists or technical prompts. Models interpret natural language; administrators configure business knowledge, enabled capabilities and bounded policy choices.

## V2 closure

All four deliveries must have producer → service → database → consumer → permission/failure → tests → browser evidence. Actual-model tests use synthetic fixtures. Storefront, Facebook and WhatsApp require real channel acceptance; blocked providers are explicitly marked rather than reported passed. Release is the exact accepted commit on localhost, GitHub and Hostinger with live HTTPS verification. Once that record is complete, V2 closes; new abilities become a new scope.
