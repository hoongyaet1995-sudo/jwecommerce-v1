# TikTok and YouTube connection implementation plan

**Goal:** Connect multiple TikTok identities and YouTube channels through the shared Hub without enabling publishing.
**Spec:** docs/acceptance/2026-10-27-tiktok-youtube-connection-audit.md, approved 2026-10-28.
**Architecture:** Independent connection-only Hub controller and providers reuse signed transport and transaction tables. Ecommerce owns encrypted account grants and refresh lifecycle; browser sees identities only. Existing Meta endpoints remain unchanged.
**Constraints:** Canonical local development first; preserve dirty work, English UI, callbacks and accounts. No real OAuth/configuration/release actions this phase. No paid API or publishing.

- [ ] Write failing provider/relay and Ecommerce lifecycle tests; record intentional red.
- [ ] Add Hub TikTok/YouTube providers, transaction relay and check/refresh signed actions. Minimal scopes; fixed callback URLs; no dynamic provider endpoints.
- [ ] Add Ecommerce client-ID configuration (no client secrets), encrypted refresh/expiry migration, secure return/select and locked refresh/check handling.
- [ ] Wire existing four-platform UI; preserve structures/styles, show setup required until configured. No GET platform requests.
- [ ] Fake regression of Meta and new adapters: state/domain/client/replay, expiry, missing scopes, reconnect preserving refresh/other accounts, failures/redaction.
- [ ] Local normal-page free review, exact online configuration delta and limitations. Do not deploy.

Review focus: app mismatch; rotated refresh token omitted; concurrent refresh/check; wrong account selection; no-token browser or diagnostic leakage. No automatic retries or publishing routes.
