# Private page context + attachments — design for review

Status: proposed, not implemented. Localhost only. Builds on the current page adapters and existing attachment pipeline; similar-product image search is excluded.

## Purpose and root cause

Text turns can resolve an owning HTTP session. Attachment analysis and delayed replies run in workers without that session. Current adapters deliberately return no private facts there. Enquiry turns also bypass common attachment processing, and attachment processing currently expects an open conversation rather than the enquiry waiting-for-human state. These are orchestration/authorization gaps, not missing catalog search handlers.

Outcome: a customer's text, image or PDF can be understood together with that message's authorized cart/checkout/enquiry/quotation/account/order page. AI remains read/explain/compare/organize only. Staff takeover stops automatic publication.

## Recommended mechanism

1. Issue a short-lived server-only **context lease per customer message**, after normal page/resource authorization. It references the sanitized selector, conversation/source ID, merchant, session and principal; it is not a bearer permission supplied by the browser. Lifetime 5 minutes. Public message trace retains only the existing sanitized selector.
2. Store the lease encrypted in the shared server cache. Encrypt session identifiers; never include them in logs, model prompts, API responses or queue arguments. Do not copy arbitrary session contents, contacts, payment details or full DOM into a message.
3. A worker reconstructs an isolated read-only request from the original server session using Laravel's configured session handler and encryption/serialization settings. It must not impersonate a customer in global Auth state or save the session. Missing/expired/regenerated/logout sessions fail closed. Unsupported/nonpersistent session drivers fail closed; tests explicitly provide supported session storage.
4. Verify the live session's customer ID, guest key, conversation generation and enquiry access against the lease. Use request-scoped customer identity in the shared owner check so HTTP and worker adapters have identical rules. Then rerun the existing adapters against current database records and current settings. Session-backed cart estimates keep their own signature and TTL checks.
5. Revalidate before model use **and again before reply publication**. Master/scope Off, source change, archived/resolved/human-owned conversation, ownership change, lease expiry or session invalidation suppress private publication. A newer message gets its own lease; never reuse a previous page's private facts for it.
6. Issue/dispatch with session persistence ordering in mind: worker authorization must not run against an unflushed login/cart session. Do not retry a missing session indefinitely or generate a paid answer before authorization. Record only safe outcome codes.

The lease grants no broader access than the page itself. Orders remain `order:private`, rendered on the server with current allowed-field gates, never sent to the answer model. Customer memory and matching email addresses remain insufficient authorization.

## Shared attachment orchestration

- Reuse existing image/PDF validation, private storage, hash-bound analysis, limits, quotas and provider calls. Do not introduce a second attachment analyzer or visual product index.
- Apply Enquiry Access and channel/attachment gates before scheduling. `waiting_human` remains AI-eligible only until Take Over; `human_mode`, archive and AI-suspended enquiry states are not eligible.
- Run one shared attachment-context reader before choosing general response versus structured enquiry intake. Pending analysis postpones generation. Failure yields a clear request to describe/resend the file; it must not claim the file was read.
- Analysis observations are untrusted customer material, separate from official FAQ/product/quotation facts. A PDF or image never verifies identity, payment, delivery capability or a business promise.
- Keep exact customer text as the evidence for committed intake fields. Values detected only in an image/PDF are candidates: ask the customer to confirm before they become committed requirements. Do not silently overwrite confirmed details.
- Queue/cadence jobs carry source IDs only. Deduplicate analysis and replies with the existing idempotency keys. Recheck the latest source and takeover at final write.

## Options considered

| Approach | Tradeoff |
| --- | --- |
| Make all attachment calls synchronous | Simpler session access, but long HTTP requests and duplicate billing/retry risk; rejected. |
| Persist a whole private facts snapshot in message trace | Easy for workers but creates stale data and revocation/privacy risks; rejected. |
| Encrypted short-lived lease + live session/record revalidation | Extra authorization code and tests, but reuses current adapters, supports revocation and avoids persistent private facts in traces; recommended. |

## Acceptance

1. Real image + current owned quotation: explain visible scope and image observations separately; no acceptance/payment action.
2. PDF + account orders: order fields generated server-side; attachment cannot unlock another customer's order.
3. Cart/checkout attachment: current own lines and fresh estimate; changed cart invalidates stale estimate.
4. Enquiry attachment in open and waiting_human: supported explanation plus confirmed requirements; Take Over prevents a pending reply.
5. Image/PDF candidate requirement is not committed until customer confirmation.
6. Master/scope/attachment/Enquiry Access Off denies its respective capability even after scheduling.
7. Logout, regeneration, lease expiry, foreign source/conversation/merchant, altered selector and missing session all fail closed.
8. Newer messages/pages invalidate earlier pending private response publication. Duplicate jobs generate no duplicate reply or billable analysis.
9. Corrupt/missing/unsupported attachment uses honest fallback; private session failure cannot be disguised as a successful read.
10. Real browser and LLM evidence accompanies focused authorization/queue/attachment/enquiry regression. No orders, payments or deployments during acceptance.

## Exclusions

Visual similar-product retrieval, embedding/index infrastructure, autonomous mutations, public sharing of private resources, storefront layout redesign, inventory repair and production deployment.
