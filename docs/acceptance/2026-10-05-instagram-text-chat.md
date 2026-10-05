# Instagram text chat local phase

Approved boundary: Social Hub grants OAuth only. Ecommerce owns encrypted tokens, subscriptions, text receive/send, shared AI and human workflow, and alerts. Pausing Hub blocks new OAuth only.

Baseline Ecommerce 6d1bcc2005011b1c517d602eba9c222234f06dea. Preserve pre-existing dirty changes in Facebook services and inventory/docs/build files. No deployment authorization for this phase yet.

Implementation: Instagram account management reuses Facebook UI patterns; independent OAuth permission via signed capabilities, separate client session keys and platform-bound Hub transactions. Facebook Login discovers linked professional accounts. Only selected account tokens cross the signed server channel. Token/code/state are not exposed to client JavaScript or logs.

Client webhook: /webhooks/instagram/messages. Hub OAuth callback: /oauth/instagram/callback. Set INSTAGRAM_INBOUND_ROUTING_READY=true only after Meta App Instagram messages webhook is configured and verified. Configure INSTAGRAM_WEBHOOK_VERIFY_TOKEN privately. No new chat feature switches in Hub.

Verification ledger:
- OAuth platform + ingress changes: Ecommerce 65 tests / 442 assertions passed; Hub 7 tests / 89 assertions passed.
- Admin build passed with existing large chunk warning. Build is local development only; release assets must be rebuilt from scoped accepted source.
- Additional AI, human reply, account isolation and reply-window checks: 22 tests / 116 assertions passed. Reference App ID and Secret match both local applications (values never printed). Local Hub platform migration applied.
- Real Meta OAuth/DM not yet verified; localhost tests use fake provider responses and do not prove Meta permissions.
- Elapsed time and token usage unavailable. Unexpected test failures: 0 so far.

- 2026-10-05T18:00:17.6432311+08:00: Run combined Ecommerce OAuth/account/ingress/reply/Support regression after platform isolation changes. Purpose: Facebook compatibility and Instagram security.

- 2026-10-05T18:02:12.6548965+08:00: Subscription corrected to the linked Facebook Page (matches reference MetaProvider); linked_page_id migration applied locally. Run Hub OAuth and Ecommerce final regression after this change.

- Final combined Ecommerce regression: 87 tests / 604 assertions passed; additional client Instagram OAuth checks: 6 tests / 53 assertions passed. Hub: 7 tests / 89 assertions passed. Frontend Facebook + Instagram Toast/multiselect/permission checks: 29 passed. Admin build passed.
- Local authenticated browser verified Instagram management, signed Hub permission, Connect link, and Light/Dark. Screenshots: output/playwright/instagram-management-local-dark.png and instagram-management-local-light.png.
- No production deployment or Meta callback changes applied. Runtime routing remains false until production webhook configured. Root storefront build was also run inadvertently; its assets are excluded from release scope. CLI route-list --compact unsupported and PowerShell timestamp interpolation corrected; no test failures recorded.

- 2026-10-05T18:05:31.0770773+08:00: New subscription-target regression verifies linked Page URL and preservation of existing subscribed fields; run MessengerReplyTest.

- Subscription fixture issue: 1 unexpected failure. Eloquent caches guardable columns across tests; this fixture adds linked_page_id after earlier tests cached the schema, so update silently discarded it. Use forceFill for the fixture and assert persistence before provider requests. Retry purpose: verify this concrete fixture correction and subscription target.

- Subscription fixture correction verified: MessengerReplyTest 23 passed / 124 assertions. Issue resolved; historical unexpected failure count retained at 1. Linked Page target and preservation of existing subscription fields now covered.

- Release approved by user: 同步吧. Pre-release combined Ecommerce: 90 tests / 621 assertions passed; Hub: 7 / 89 passed. Scoped HEAD-plus-Instagram Admin build passed. Windows symlink privilege unavailable; used directory junction to existing dependencies. No application issue.
