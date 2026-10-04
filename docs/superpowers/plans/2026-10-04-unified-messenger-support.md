# Unified Messenger Support Implementation Plan

> Execute inline with tests before code. User approved the in-chat design: shared AI and handoff flow, Pending only open, Human waiting/human mode, first staff reply takes over.

**Goal:** Reuse Storefront knowledge, intent detection and support state transitions for Messenger.
**Architecture:** Persist authenticated inbound events before enqueueing database jobs. Jobs call FaqAutoReplyService; MessengerReplyService adapts generated text to Meta with existing Page/PSID, window and dedup guards. AI generation never runs inside webhook transactions.
**Tech Stack:** Laravel, MySQL, database queue, existing React Inbox.

- [x] Prove ordinary inbound starts open; duplicate webhook creates one AI job; per-Page separation remains.
- [x] Prove common explicit/semantic human request transitions to waiting_human and notifies once; other escalation suggestions remain opt-in.
- [x] Adapt existing send transport for generated AI text with durable one-attempt reservation, suppress stale or taken-over generation, never auto-resend unknown delivery.
- [x] Permit first staff send to take over atomically, retaining permission/archive/window guards.
- [x] Make Pending/Human mutually exclusive and allow shared Hand Back to AI.
- [x] Run scoped backend/frontend regressions, clean release build and authenticated localhost browser proof. Preserve unrelated files. No push/deploy until separately approved.

Operational acceptance: database worker required; configured support AI must be enabled. No live customer messages during local QA. Real per-Page AI roundtrip remains a release acceptance gate. Product/order actions use trusted server links, never provider identities as storefront customer authorization.
