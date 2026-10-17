# Private media context implementation plan

> Execute inline using superpowers:executing-plans. Approved design: docs/designs/2026-10-17-private-page-media-context-spec.md. No separate agents for implementation.

**Goal:** Background attachment replies retain only currently authorized private-page context.
**Architecture:** Per-message encrypted five-minute server cache lease references persistent Laravel session and sanitized selector. Workers rebuild an isolated read-only session/request, recheck owner and records with existing adapters; final publication checks authorization again. Shared attachment handling feeds general and enquiry replies, without accepting extracted image/PDF values as confirmed intake fields.
**Tech Stack:** Laravel/PHP/MySQL, Laravel session handlers/cache/encryption, existing support queue/analyzers.

## Constraints and review focus

No credentials/session IDs in public traces or model prompts; no worker global Auth impersonation; persistent-session driver required; logout/generation/takeover/settings/source changes revoke private publication. Image search and AI mutations excluded. Test source/session tampering, TTL/revocation, waiting_human and takeover, failed media, no duplicate billing and text-only regressions.

- [x] Add lease authorization/queue tests; intentional missing-class red.
- [x] Implement SupportPrivateContextLease issue/facts/revalidation and request-scoped identity; hook trusted ingress and SupportPageContext fallback for workers only.
- [x] Revalidate private context before normal, enquiry and cadence publication.
- [x] Share media eligibility and scheduling with enquiry waiting_human; route analyzed replies to appropriate engine and pass bounded unverified observations separately.
- [x] Preserve text-evidence-only committed intake; honest failed/pending attachment handling.
- [x] Run focused security/attachment/enquiry/context/cadence tests, real browser image/PDF read-only acceptance and final scoped review. No order/payment/deploy actions.

Completion evidence: docs/acceptance/2026-10-17-jtg-faq-detail-private-context.md. Private plan real acceptance is image+orders, PDF+orders, image+quotation and waiting-enquiry PDF; ordinary nonempty cart/checkout automated only. Local permanent worker operation is not verified. No sync/deploy.
