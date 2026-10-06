# Unified Social CRM Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans for native execution or superpowers:subagent-driven-development if the user chooses delegation. Track steps with checkboxes.

**Goal:** Associate validated incoming conversations with CRM contacts and show accumulated platforms without unsafe customer merges.

**Architecture:** Extend Customer with nullable contact fields, scoped channel identities and platform presences. A transactional resolver handles identity association; profile enrichment runs after persistence. Existing support authentication stays independent of CRM linking.

**Tech Stack:** Laravel, PHP 8, MySQL, existing React/Tailwind Admin and Vitest.

**Spec:** ../specs/2026-10-06-social-crm-identity-design.md

## Global constraints

- Develop first in C:/xampp/htdocs/jwstudio-ecommerce; preserve unrelated dirty work.
- No fabricated email, inferred identity from message text, name-based matching or automatic reassignment of orders/users.
- Existing directory presentation stays intact except Platform column and matching filter controls.
- TikTok remains Awaiting TikTok approval; no production acceptance claim.
- No commit/push/deployment before synchronization approval, overriding skill defaults about per-task commits.
- Record initial intentional reds separately from unexpected failures in docs/acceptance/2026-10-06-social-crm-identity.md. Follow 3/6/9 failure escalation; known environment blockers pause affected work.

## Review focus

- Different business accounts can use identical sender IDs: scoped identities must remain distinct.
- Email and telephone can point to different existing contacts: preserve both and flag conflict.
- A WhatsApp batch can include unrelated profile contacts: match sender before enrichment.
- A guest-supplied email does not prove ownership: never grant account access or merge authenticated customer data on that basis.
- Nullable-email contacts must survive directory DTO validation, editing, searching and rendering.

## Task 1: Contact storage and resolver

**Files:** create database/migrations/2026_10_06_150000_add_customer_channel_identity.php; app/Models/CustomerChannelIdentity.php; app/Models/CustomerPlatformPresence.php; app/Services/Customers/CustomerContactResolver.php; app/Services/Customers/ContactValueNormalizer.php; tests/Feature/CustomerContactResolverTest.php. Modify app/Models/Customer.php and app/Models/SupportConversation.php.

**Interfaces:** `ContactValueNormalizer::email(?string): ?string`, `phone(?string, ?string $country = null): ?string`; `CustomerContactResolver::associate(SupportConversation $conversation, array $identity, array $profile = []): Customer`. Identity keys: platform, merchant_key, app_id, account_id, sender_id. Profile keys: name, username, avatar_url, email, phone, email_trusted, phone_trusted, country. Existing customer_user_id association takes precedence for Storefront. Return the linked Customer; record ambiguity in identity metadata and preserve an independent contact.

- [ ] Write tests asserting nullable email creation, repeat association returns same ID, different account scopes return different IDs, unique trusted phone links existing Customer, untrusted email does not link another Customer, conflicting trusted email/phone preserves existing customers/users/orders, ambiguous phone never matches.
- [ ] Declare and log initial red, then run `C:/xampp/php/php.exe artisan test --filter=CustomerContactResolverTest`; confirm missing schema/service failure.
- [ ] Implement nullable email migration, foreign keys, unique identity_hash and customer/platform constraints. Add matching provenance/conflict metadata; use transaction locks and uniqueness guards. Phone normalization accepts explicit international format and conservatively rejects uncertain local numbers unless country parsing is supported; no guessed country.
- [ ] Implement resolver without provider network calls. Add Customer relationships and conversation.customer relation. Never auto-delete a Customer. Keep identity relinking restricted to provisional contacts without existing users/orders/addresses and reliable evidence; otherwise record a conflict.
- [ ] Run resolver tests and review migration rollback: never restore NOT NULL if null-email contacts exist without an explicit data resolution step.

## Task 2: Incoming messages and profile enrichment

**Files:** modify app/Services/Social/MessengerInboundService.php; app/Services/Social/WhatsAppInboundService.php; app/Services/Support/SupportChatService.php; app/Http/Controllers/Admin/SupportChatController.php. Create app/Jobs/EnrichSocialCustomerProfile.php; app/Services/Social/SocialCustomerProfileService.php; tests/Feature/SocialCustomerIdentityInboundTest.php.

**Interfaces:** consume Task 1 resolver. `SocialCustomerProfileService::fetch(ManagedSocialAccount $account, string $senderId): array` returns available name/username/avatar_url only. `EnrichSocialCustomerProfile` receives identity ID, loads the scoped account and calls the resolver/profile updater after commit. A failed remote lookup never rolls back an accepted incoming message.

- [ ] Test repeated receipts create one CRM contact; FB/IG receiver-account scopes stay isolated; WhatsApp profile.name selected by matching wa_id; unsupported/echo events create no contact; profile permission errors preserve message and generic name; guest support contact email remains unverified and grants no order access.
- [ ] Log and run initial red: `C:/xampp/php/php.exe artisan test --filter=SocialCustomerIdentityInboundTest`.
- [ ] Call resolver after duplicate-event guards and before message creation. WhatsApp gets sender-matched profile name and supported sender phone; FB/IG enqueue enrichment after commit. Queue uses existing support worker conventions and bounded retry/backoff; cache successful profile lookup timestamps rather than calling API on every message.
- [ ] Replace existing guest email firstOrCreate path with resolver association to avoid bypassing provenance checks. Authenticated Storefront links existing customer; anonymous guest resolves by stable conversation/guest identity. Expose linked CRM data in Admin only and eager-load customer to avoid N+1.
- [ ] Run new tests plus existing Messenger/Instagram/WhatsApp/support ingestion tests located via `rg --files tests`.

## Task 3: CRM directory platform column and filters

**Files:** modify app/Http/Controllers/Admin/CustomerDirectoryDataController.php; app/Services/Admin/CustomerDirectoryDataService.php; resources/admin/src/integration/customerDirectoryData.ts; resources/admin/src/components/customers/CustomerAdmin.tsx; relevant Customer type in resources/admin/src/types. Extend resources/admin/src/integration/__tests__/customerDirectoryData.test.ts. Create tests/Feature/CustomerDirectoryPlatformTest.php and component test beside CustomerAdmin.

**Interfaces:** directory JSON adds `platforms: Array<'storefront'|'facebook'|'instagram'|'tiktok'|'whatsapp'>`, permits email null. Index accepts validated platform and tag filters; service applies whereHas filters before paginate(30). Existing API consumers remain supported.

- [ ] Test nullable-email DTO round trip, multiple-platform icons, accessible platform labels, empty-email edit/search behavior and server-side filtering before pagination.
- [ ] Log and run initial red for PHP directory filter and frontend DTO/component tests.
- [ ] Add eager-loaded presences and nullable email validation/serialization. Render Platform between Lifetime Spend and Tags, reuse current channel icon patterns and classes. Add platform/tag filter plumbing without changing unrelated directory layout.
- [ ] Run `C:/xampp/php/php.exe artisan test --filter=CustomerDirectoryPlatformTest` and `npm --prefix resources/admin test -- --run` with focused file arguments; run `npm --prefix resources/admin run build` after focused checks pass.

## Task 4: Existing records and local acceptance

**Files:** create app/Console/Commands/BackfillSupportCustomers.php; tests/Feature/BackfillSupportCustomersTest.php; docs/acceptance/2026-10-06-social-crm-identity.md.

**Interfaces:** `php artisan social-crm:backfill` defaults dry-run; `--apply` performs chunked local association using Task 1. Reports created/linked/conflicted/skipped counts. Existing storefront presence derives from user/order/conversation evidence. No historical profile API calls inside transactions.

- [ ] Test default dry-run makes no changes, apply is idempotent, historical scopes remain distinct and unrelated existing customers are not labelled Storefront without evidence.
- [ ] Log initial red; run `C:/xampp/php/php.exe artisan test --filter=BackfillSupportCustomersTest`, implement command, then rerun.
- [ ] Apply migrations locally after passing tests; run backfill dry-run and inspect counts before local `--apply`. Record backup/rollback considerations and exact commands.
- [ ] Verify authenticated localhost /admin/customers and /admin/support: dark/light platform icons, provisional contacts, filters, actual conversation-to-CRM linkage, and unchanged account access. Capture screenshots and database assertions; do not send unrequested provider messages.
- [ ] Record completed and pending gates, measured elapsed time or unavailable. Report coherent phase and request synchronization approval separately. TikTok live ingestion remains blocked by provider approval; do not mark it complete.

## Plan review

Self-review completed: each spec section maps to Tasks 1–4; resolver signatures and DTO keys are consistent. Concurrency, nullable values, conflicting identifiers, guest trust and webhook contact selection have explicit tests. Native execution is recommended because these four tasks share the same resolver and customer DTO, with review at each verification gate.
