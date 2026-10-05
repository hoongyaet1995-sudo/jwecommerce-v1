# Instagram webhook relay — local acceptance

Scope approved: Meta -> Social Hub -> signed customer endpoint -> existing Ecommerce ingress. OAuth pause does not block existing messages. No UI changes.

Hub endpoint: `/webhooks/instagram/messages`. Customer endpoint: `/api/social/hub/instagram`.
Hub verifies original Meta HMAC, limits payload size, separates entries by account, resolves OAuth authorization records, refuses ambiguous cross-client ownership, pins public destination DNS using existing transport, signs with individual installation credentials and verifies response signatures. Client checks tenant/domain/key/signature/replay and retains existing atomic message deduplication, notification and AI pipeline. Provider receives 503 on forwarding failure for retry.

Validation on 2026-10-05 (exact run timestamps unavailable):
- Intentional initial TDD red: InstagramWebhookRelayTest expected challenge 200, existing middleware returned 503.
- After implementation: Hub relay/connectivity 4 tests, 39 assertions passed.
- Expanded Hub relay checks: 2 tests, 14 assertions passed (signed destination, OAuth paused, bad receipt, verify token, invalid Meta signature).
- Ecommerce connectivity/relay: 3 tests, 18 assertions passed (signed receipt, replay, wrong secret, cross-client).
- Existing MessengerInboundTest/MessengerReplyTest: 33 tests, 200 assertions passed (Instagram dedup, alerts, AI, handoff and send).
- Unexpected application test failures: 0. Historical accounting unavailable. Initial red recorded separately.

Pending: synchronization approval, exact-SHA releases, private Hub verify token configuration, Meta callback change and GET verification, real DM acceptance. Local tests use fake HTTP and do not prove live Meta message transport. Existing Ecommerce direct callback is retained for migration until live relay is accepted. OAuth records must unambiguously map each active account to one customer; multiple historical owners deliberately block routing.
Elapsed time and token usage: unavailable.
