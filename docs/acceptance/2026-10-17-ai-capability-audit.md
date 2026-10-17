# AI capability audit — 2026-10-17

Scope: localhost read, explain, compare and organize. No AI mutations, purchases, payment, publishing or deployment. This report separates source/test evidence from real browser/LLM evidence. Execution and issue history: `2026-10-17-ai-commerce-context-audit.md`; public context evidence: `2026-10-17-support-page-context.md`.

## Shared architecture

Widget submits a bounded path/variant/account-tab/selected-order selector with each message. The server resolves current records, session/customer ownership and current knowledge permissions. `SupportPageContext` and `SupportCommerceContext` supply allowlisted facts to the existing knowledge tool registry. `commerce.read` exposes only current authorized private facts after routing; routing receives page type rather than business records. Orders remain server-generated `order:private` responses and are removed from model facts. Existing takeover, settings-version and stale-message guards still control reply publication.

Enquiry intake retains its structured reception engine and now reuses these adapters. There are still two orchestrators; shared data adapters do not mean a single unified planner has been completed.

## Coverage and evidence

| ID | Capability | Current boundary / evidence |
| --- | --- | --- |
| AI-CTX-01 | Home/CMS/PLP/PDP current page | Published visible content, catalog filters and current variant. Prior real LLM/browser acceptance for home, collection and PDP; focused permission tests. |
| AI-CTX-02 | Cart/normal checkout | Session cart with active buy products, quantities, prices and currency. Trusted server-rendered estimates expire after 5 minutes and invalidate on item/quantity/price/coupon/currency changes. No contact form scraping. Cart/Checkout GET snapshot integration tested; Chrome real LLM correctly identifies empty cart. Nonempty normal checkout has automated evidence only because current active catalog is quote-only. |
| AI-CTX-03 | Enquiry | Viewer-owned enquiry reference, product and redacted requirements; Enquiry Access must also be On. Existing real reception acceptance plus owner/Off tests. |
| AI-CTX-04 | Quotation/quote checkout/payment | Viewer-owned immutable sent version, items, total, validity, acceptance, linked-order existence, previous version and enabled payment method labels. Draft document and bank credentials omitted. Ownership/snapshot tests; quotation checkout regression. Do not claim a new real LLM browser acceptance on every quote subpage. |
| AI-CTX-05 | Account | Logged-in customer only; counts, bounded saved items, address regions, profile-presence flags and permitted vouchers. Real LLM answered 10 orders / 0 saved items / 1 address. Full contact and street fields omitted. |
| AI-CTX-06 | Account orders/tracking/success | Current customer's selected order or latest five; tracking requires existing verified session. Existing field gates apply. Real LLM flow shows readable server-owned statuses/items; owner, denied fields and verification-expiry tests. No fresh end-to-end OTP email test in this phase. |
| AI-CTX-07 | Account quotations | Own sent references and explicit sent/accepted/expired status; acceptance does not imply order creation or payment. Browser list verified; acceptance-without-order regression. |

## Existing capability inventory

| ID | Capability | Assessment |
| --- | --- | --- |
| AI-CAP-01 | FAQ/product/category/discount retrieval | Existing `faq.search`, `product.search`, `product.count`, `taxonomy.search`, `discount.search`, `discount.lookup_code`, `support.tracking`, `order.lookup`; current-page facts supplement them. Permissions still apply. |
| AI-CAP-02 | Explain and compare | Can explain/compare provided descriptions, specifications, current cart lines and sent quote versions. Cannot verify undocumented compatibility, inventory commitments or industry service coverage. No separate visual-similarity index. |
| AI-CAP-03 | Product cards | Existing server-resolved catalog cards; quote-only hidden prices no longer display zero as a sale price. This does not prove exact image matching. |
| AI-CAP-04 | Enquiry organization/handoff | Universal checklist, supported-fact extraction and handoff summary. Waiting for human can retain AI assistance until Take Over; human reply/takeover prevents simultaneous AI publishing. |
| AI-CAP-05 | Image/PDF/audio | Analyzer and channel-specific attachment pipeline exist; current provider reports image/document/audio capability. This phase did not repeat full media upload/transcription acceptance. Audio support is WhatsApp-specific, not a universal storefront microphone capability. |
| AI-CAP-06 | Customer memory | Existing recognized-customer preference/retention system. Memory is not proof of ownership and does not grant order access. No new memory mutations in this phase. |
| AI-CAP-07 | Transaction actions | Intentionally unavailable: change cart/address/profile, accept/revise quote, create order, pay or refund. The current phase is read-only. |

## Remaining gaps and recommended order

1. **P1 — Knowledge content hygiene.** JTG currently retains 30 JW Home sample FAQ entries and a stylist greeting/persona. They can influence real answers. Review/replace irrelevant content with approved JTG facts before customer-facing use. This audit did not delete or invent business policies.
2. **P1 — Media plus private context.** Background attachment processing has no current web session, so private page reads fail closed. Enquiry attachment turns also retain their existing separate handling. A complete shared input pipeline needs a short-lived, owner-bound authorized context reference, revalidation before use and takeover checks; do not pass arbitrary screenshots/DOM or unbounded private records.
3. **P2 — Similar-product retrieval.** Image observations may inform text search today. Dedicated visual retrieval/ranking, confidence and no-match handling remain unfinished; do not market this as complete visual product search.
4. **P2 — Planner convergence.** Consolidate common tool execution/evidence/summary handling while preserving structured enquiry intake and server-only order replies. Avoid separate responders for each scenario.
5. **Separate existing inventory regression issue.** Expanded legacy checkout tests conflict with the current stock migration. Do not change inventory contracts in this AI phase. Track and repair under its active inventory checkpoint before claiming all checkout tests green.

## Local acceptance limits

Private scopes default Off on installation and require the master switch; localhost test configuration has all four scopes enabled. Admin save/readback verified. No production settings or deployment changed. Estimates are descriptive snapshots, not final charge guarantees. Large payloads are bounded and fail closed; account orders are limited to five and quotation items to eight. No claim of exhaustive account-history analysis or full contact-data access.

Measured evidence and final commands are recorded in the execution ledger. Existing TypeScript baseline errors and unrelated dirty inventory/social work are not represented as fixed by this phase.


## Follow-up implementation — JTG phase (2026-10-17)

The two P1 gaps listed above are historical findings, now addressed locally:
- Knowledge hygiene: 30 unrelated sample FAQ entries backed up/replaced with 18 bounded JTG FAQ and a JTG support identity. Real LLM coverage/handling-limit evidence in the current phase ledger.
- Private media: encrypted five-minute server-only session reference, current ownership/settings/resource re-read, fingerprint validation before each answer-model call and publication, including cadence. Session identifiers/private record snapshots are not persisted in message traces. Orders remain server-rendered.
- Shared image/PDF observations now reach enquiry reception in waiting_human until Take Over. Observations remain unverified; committed intake still requires exact typed customer evidence. Unreadable enquiry files receive deterministic honest fallback.
- Real Chrome image + account orders and PDF + account orders exercised actual source-specific queue handlers and provider calls. Local support queue worker was not running; unrelated queued jobs were not drained. Automatic ongoing queue consumption has not been claimed as verified.
- Similar-product image search remains explicitly excluded by the user. The CMS AI Generator is deferred to the next discussion.

Current evidence and issue accounting: docs/acceptance/2026-10-17-jtg-faq-detail-private-context.md. Local only, no release approval requested until phase evidence is reported.
