# Social AI and Answer Review audit

Read-only production audit after release 19c49ed; no provider calls, customer replies, configuration changes or deployments made.

## Verified production
- Storefront, Facebook/Messenger, Instagram, WhatsApp AI channel switches all true; OpenAI connection enabled, support enabled and verified.
- Shared tools: faq.search, product.search, product.count, taxonomy.search, order.lookup, support.tracking, commerce.read. Same FaqAutoReplyService consumed by web and social workers; social delivery/window and identity/context restrictions remain channel specific.
- 18 active published FAQs. No explicit business/service overview or dispatch-origin FAQ in their titles. Destination delivery confirmation is present; origin and destination must not be conflated.
- Two screenshot questions (source IDs240,242): WhatsApp, open, delivery read; faq.search no_match; retrievedSourceIds=[]; citedSourceIds=[]; decision=coverage_incomplete. No evidence of transport or credentials failure for these two replies.
- review drafts count0.

## Root causes
1. No-facts path returns synthetic answer/faq_ids without coverage. Coverage validator runs even when no answer model was called; this converts missing knowledge to coverage_incomplete, classified as a service failure. Do not relax source/citation checks for actual generated answers. Skip structured-answer coverage validation only for the deliberate zero-fact fallback and classify no_match.
2. Services question used FAQ only; catalog tools enabled but not selected. Available tools describe products/policies, no general verified company-profile/published-CMS discovery tool. Website current-page context does not mean every social chat automatically reads all website pages. Do not infer unsupported dispatch origin from company location.
3. Answer Review is a manually created draft list, not a live list of all unanswered questions. eligibleSource requires post-activation customer text >=3chars, no attachment, resolved conversation, linked AI trace decision no_match/partial, delivery not failed/unknown/pending. Admin selects missing-knowledge question then Create answer draft. It can seed a staff answer before the next customer message; publication requires human review. Screenshot records fail both missing-knowledge classification and resolved status.

## Recommended local repair scope
- Correct zero-fact classification; retain generation/schema and delivery failures in settings-check list.
- Make source routing handle company/service questions with authorized sources; retain unknown answers for unsupported facts.
- Improve draft creation flow so incomplete/knowledge-gap questions can be explicitly selected for manual answer authoring with clear eligibility reasons. No automatic FAQ publication. Decide whether resolved remains required or is replaced by an explicit manual-review action; current contract intentionally requires resolved.
- Regression matrix across4 channels: known FAQ, paraphrase/Chinese service question, true unknown, provider malformed response, failed delivery; draft/edit/publish/next-query retrieval closure. No external customer messages during tests without explicit send authorization.

Elapsed time/token usage unavailable. Findings verified by current production DB read and source review, not historical memory.
