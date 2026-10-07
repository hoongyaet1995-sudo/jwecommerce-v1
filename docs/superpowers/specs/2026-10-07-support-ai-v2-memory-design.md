# Support AI V2-B — customer memory design

Status: approved functional scope; written design prepared for user review. No implementation or release claimed.

## Purpose and baseline
Reduce repeated questions when a recognized customer returns, while keeping identity checks and current business records authoritative. Local first: C:\xampp\htdocs\jwstudio-ecommerce. Baseline branch codex/social-crm-identity, HEAD 5aa7bceaf6a18c46f7aa43e3ac4877c508fde6e0. Preserve unrelated checkout, marketplace, documentation and historical generated asset changes.

## Existing flow and root gap
SupportConversation links customer_id and customer_user_id. CustomerContactResolver maintains scoped CustomerChannelIdentity hashes and trusted contact matching, including matching_conflict metadata and provisional merges. SupportDialogueContext currently reads only recent messages and bounded same-conversation attachment observations. CRM association alone is not order verification and is insufficient proof for unrestricted cross-platform memory.

## Chosen approach
Store bounded attributed memory in MySQL, with encrypted content, expiry, provenance and explicit identity scope. Avoid feeding whole historical transcripts into each reply. No vector database or new provider dependency. The existing AI client may extract candidate memories from customer text using structured output; server validation determines admissibility. No employee-maintained greeting phrase lists.

## Content boundary
Allowed: explicitly requested preferred language; explicitly stated product/style preferences; short unresolved-issue summaries. Do not infer sensitive traits, emotions, identity or purchasing power. Exclude addresses, email/telephone values, credentials, payment data, private order facts, attachments and internal staff notes. Summaries describe customer statements and requests, never proof of payment, entitlement or completed action. Use at most five active items and a bounded context length per reply.

## Identity and isolation
Scope every read/write to merchant and authenticated platform identity or signed-in storefront customer. Same scoped provider identity can reuse its memory without claiming verified legal identity. Anonymous storefront guests stay within their current conversation. Cross-platform reuse requires a trusted CRM association with no matching conflict and compatible identity evidence; a nickname, avatar or customer-typed contact value does not qualify. Recheck current association at consumption; a later merge does not automatically broaden existing memory visibility. Ambiguous or contradictory identity blocks reuse. Order access continues through the existing verification gate.

## Lifecycle and settings
Knowledge Scope owns one Customer Memory switch, default off. Guardrails owns retention (default30days, allowed7–90). Enablement applies to subsequent eligible messages, no bulk historical scan. Expired/deleted/disabled records are excluded immediately. Automatic updates preserve source message/conversation, identity scope and last-observed time. Explicit correction supersedes the former preference. Resolve/archive clears eligibility of unresolved-issue memory; preferences can remain until expiry. Staff deletion prevents the same source message from recreating the item. Extraction failure leaves chat delivery intact; no uncontrolled repeated provider calls.

## Employee controls and UI
Reuse Customer details card patterns: a compact Customer Memory card shows type, text, source and expiry, with edit/delete controls and confirmation. Knowledge and Guardrails reuse current switch/limit styles. Existing Admin authorization and conversation access gates apply. Staff edits are attributed and audited; no plaintext memory in routine logs. This phase does not redesign the inbox or add a separate application.

## AI consumption
Inject only eligible bounded items as customer-provided historical context, never instructions. Current user statements supersede historical preferences. FAQ/catalogue/stock/policies are retrieved live. Memory cannot authorize tools, reveal private orders, promise refunds or change an order. Human takeover continues to block automatic replies; memory extraction must not cause a reply on its own.

## Alternatives
Whole-history replay is simpler initially but increases exposure, context size and stale-information risk. Free-form customer profiles are flexible but difficult to validate or delete predictably. Bounded typed memories provide explicit ownership, expiry and reviewability within Laravel/MySQL; the tradeoff is intentionally limited coverage.

## Acceptance boundary
Verify enabled/disabled behavior, recognized return visits, anonymous isolation, cross-platform trusted association and conflict rejection, merchant isolation, expiry, staff correction/deletion, deleted-source suppression, resolution, extraction failure, prompt injection and unchanged order verification. Meaningful automated tests plus local browser save/reload and Customer details operations are required. Actual model acceptance uses synthetic customer statements. No real-customer bulk processing. Record commands/results and cumulative issues in one phase ledger. Synchronization requires separate user approval after local acceptance.

## Exclusions
No permanent unrestricted history, sensitive-personal-data inference, order writes/refunds, Blast Campaign, reply rhythm, automatic FAQ publication or complete V2 closure. V2-A real platform attachment acceptance remains pending independently.
