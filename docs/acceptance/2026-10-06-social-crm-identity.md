# Social CRM identity phase

Baseline: 91aaa16699c98cf61e9fcd9bacb3f3a74478248b. Branch codex/social-crm-identity. Existing unrelated dirty changes preserved. Spec and plan approved. Native execution; no release approval yet.

Ruling: use canonical working directory and a feature branch rather than another checkout, as the project contract requires first acceptance here.

Pre-flight: Tasks 2 and 4 consume Task 1 associate API; Task 3 consumes its persisted platform presences. No signature conflicts.

Testing ledger: commands are timestamped before runs. Unexpected failures start at 0; historical accounting for other phases unavailable. Elapsed/token usage unavailable unless measured.

Ruling: existing minimal ingestion test schemas must include the new contact schema; otherwise they cannot exercise the now-required CRM persistence. Do not silently skip association when schema is absent.

Initial intentional RED: CustomerContactResolverTest before implementation, expected missing resolver. Covers provisional creation, identity scoping, normalization, conflict handling and trust boundaries.

2026-10-06T13:02:30.1355153+08:00 RUN php artisan test --filter=CustomerContactResolverTest; intentional initial RED.

2026-10-06T13:03:23.3194672+08:00 RUN resolver GREEN after schema/resolver implementation.

Resolver GREEN 4 tests/11 assertions. Task 1 complete. 2026-10-06T13:04:03.9441178+08:00 RUN SocialCustomerIdentityInboundTest::test_webhook_uses_matching_contact_and_deduplicates_customer initial intentional RED before inbound hooks.

2026-10-06T13:04:58.3577783+08:00 RUN CustomerDirectoryPlatformTest and customerDirectoryData.test.ts initial intentional RED before DTO/filters.

2026-10-06T13:05:56.0539588+08:00 Edit helper partially stopped at UTF-8 read (1 tooling failure); prior edits preserved, last step completed with explicit UTF-8. RUN focused GREEN after inbound hooks and directory DTO implementation.

Diagnostics: first GREEN suite had queue fixture missing enrichment job fake (1 run), durable job count changed by enrichment (1 run), legacy guest-email assertion conflicts with approved unverified identity rule (1 run), generic guest display changed (1 run), frontend URL assumed browser globals (1 run). Distinct causes fixed, no blind retry. Keep guest display Guest; unverified email stored as identity metadata, not authoritative Customer email. 2026-10-06T13:08:08.7070520+08:00 RUN updated focused suites.

2026-10-06T13:08:45.8609731+08:00 RUN BackfillSupportCustomersTest initial intentional RED before command.

Second GREEN: 107 passed; 1 legacy guest assertion remained (same issue count 2), now updated to assert unverified metadata and storefront presence. Frontend 4 passed and build passed. 2026-10-06T13:09:22.5634998+08:00 RUN focused PHP including backfill after command implementation.

112 PHP tests/651 assertions GREEN, DTO 4 GREEN, build GREEN. Reviewer found ambiguous bare numbers and later trusted enrichment gap. 2026-10-06T13:10:59.2449786+08:00 RUN new review regression tests intentional RED.

Ruling: reliable enrichment can link only a contact with no user/email/phone/orders/addresses and one identity. Keep old record with merged_into_id audit reference, omit it from directory; never delete/move orders/users. Generic normalization requires explicit international prefix; WhatsApp adapter adds documented prefix. 2026-10-06T13:11:48.7188741+08:00 RUN reviewer regression GREEN.

2026-10-06T13:12:22.8241084+08:00 Local MySQL migration hit XAMPP strict timestamp default limitation (1 unexpected run); subsequent dependent dry-run failed on missing table, same migration root cause. Pause backfill until migration repairs verified. Changed first/last timestamps to datetime and made migration resilient to MySQL partial DDL. RUN repaired migration.

MySQL repaired migration GREEN. Reviewer timestamp correction: historical first/last dates derive from conversation evidence, not backfill execution time. 2026-10-06T13:12:51.1921785+08:00 RUN dry-run counts only.

2026-10-06T13:13:19.8686174+08:00 Final regression after review fixes, including actual historical dates and rendered icon/empty-email checks (these extra presentation assertions are verification, not an initial TDD red).

2026-10-06T13:13:57.7088187+08:00 Local backfill dry-run created 8/linked 1/conflict 0/skip 0; applied after successful migration and review fixes; repeat dry-run verifies idempotency.

2026-10-06T13:14:43.2898745+08:00 Local browser acceptance fixture only: CRM acceptance (local test), one contact/three scoped identities Facebook/Instagram/WhatsApp. Synthetic profile, no provider calls, no real message acceptance claim.

2026-10-06T13:15:47.2776968+08:00 Reviewer safety ordering regression initial RED: contradicting supplied target field must block relink before any association mutation.

Contradiction check now precedes initial/later target association. Resolver hooks moved after conversation timestamp update, still within deduplicated ingestion transaction. 2026-10-06T13:16:06.1649791+08:00 RUN final safety regression.

2026-10-06T13:20:27.4524086+08:00 Coverage completion: new Storefront registration should record presence even before chatting; initial RED before registration/checkout producer hooks.

Ruling: platform history must also cover new registration/checkout without a chat; added transactional producer hooks, preserving existing account/order behavior. 2026-10-06T13:20:55.0937936+08:00 RUN regression including registration and checkout after hooks.

Expanded checkout run: 142 passed, 26 failed. Failures include inventory availability RuntimeException (CheckoutService:84). Inventory gate paused; no inventory repairs or blind retries. Historical baseline attribution not established. Continue independent CRM verification only. 2026-10-06T13:24:43.8640532+08:00 RUN focused CRM final verification after registration hooks.

Final focused verification: 117 PHP tests passed / 668 assertions / 11.79 seconds; frontend 5 passed; build passed; git diff --check passed. Browser verified multi-platform icons, Instagram filter, empty tag result, light/dark layout; screenshots tmp/crm-directory-dark.png and tmp/crm-platform-filter.png. Reviewer found no remaining important issue in resolver scope. Local migration and historical backfill applied; no production migration, push or deployment. Real provider profile-enrichment acceptance remains pending. Expanded checkout inventory gate remains unresolved: 26 failed tests, stop affected repair/retry work under escalation contract; independent CRM checks passed. Release blocked pending explicit decision and checkout gate diagnosis. Elapsed phase time and tokens unavailable.

2026-10-06T14:33:14.8768757+08:00 User explicitly authorized support profile UI binding and synchronization. Checkout presence hook deferred; original CheckoutService restored, inventory gate remains paused. RUN scoped profile API/UI regression and CRM suites after scoped identity rendering, staff-avatar isolation and incremental refresh fix.

Release acceptance: 118 PHP passed / 675 assertions, 8 frontend passed, admin build passed. Support profile UI verified with explicitly synthetic local fixture; screenshot tmp/crm-support-profile-local.png. User approved GitHub/Hostinger sync. CheckoutService unchanged from HEAD; broader inventory failures remain outside release repair scope. Existing live profiles to enrich through read-only provider API after migration/backfill, no messages sent.
