# Storefront ↔ Admin Support chat API — 2026-10-02

Mode: OPERATIONAL_CLOSURE (local only). User approved real APIs, removal of dummy conversations and Guest identity for anonymous visitors. AI/social providers excluded. UI lock: keep accepted storefront floating/fullscreen chat and standalone Admin three-panel layout; reuse existing empty/error text patterns.

Baseline HEAD: 2dad160367b13e29b52cc630dfd6b26e52d3d6fb. Unrelated inventory changes/routes additions preserved. No deployment approval for this phase.

| Capability | Producer / persistence / consumer | Status |
|---|---|---|
| Guest identity and isolation | server session owner hash; Guest; no client name or conversation ID accepted | CLOSED (local) |
| Member identity | customer auth guard user; no cross-account/guest merge | SYSTEM_MANAGED (automated proof; member browser sign-in not run) |
| Customer send / staff reply | CSRF protected routes, DB transactions, idempotent UUID; reciprocal polling | CLOSED (local) |
| Resolve/reopen/read | authenticated Admin, row lock / read-through ID | CLOSED (local) |
| Private notes | staff-only table/serialization; never public | CLOSED (local) |
| AI, attachments, social channels | excluded | DORMANT |

Guest sessions survive refresh and same-session navigation; clearing session loses guest access. Login switches to own customer thread; no guest history transferred. No fake online or automatic AI replies. All messages plain text, server length 2000.

## Issue ledger
Historical sample I1 resolved; I2 unrelated /collections/all 404 remains outside scope. This phase fresh issues separate by evidenced cause. Pre-run plan: scoped additive migration only after runtime DB verification; isolated SQLite tests using explicit config override and manual selected schema only (no business DB migration reset); targeted frontend check + Admin build + guest-to-authenticated-Admin browser roundtrip and refresh/private notes/resolution/mobile checks. Elapsed time/tokens unavailable.
Run 1: scoped SupportChatApiTest, explicit SQLite :memory: connection + selected schema only; tests cover guest roundtrip/privacy, cross-session isolation, member identity, auth, validation, idempotency, read watermark and resolution. No business DB reset.
Run 1: 5 passed/1 failed, 51 assertions. S1 count1: app ClearLegacyCustomerWebSession logs non-admin web users out, so protected API correctly returns 401 rather than test-assumed403. Changed expectations to actual existing auth contract; no permission weakening. Run2: same targeted suite with corrected expectation.
Run2 passed 6 tests/55 assertions; S1 resolved count1. First Admin build passed 2990 modules,13.44s; standard existing large-chunk warning. Runtime verified local/mysql/jw_ecommerce, support tables absent, no cached config. Next: additive migrate with only 2026_10_02_160000_create_support_chat_tables.php path; no inventory migrations or database reset. UI input/error micro-state refinements require final rebuild.
Browser initial roundtrip: real MySQL 1 guest thread/1 message, Admin Guest name, staff reply observed in storefront; private note posted. Scoped migration passed. S2 count1 editing patch context mismatch, resolved via narrower patch with no partial changes. New diagnostic improvements: incremental Admin timestamp polling, private note touch, input busy lock and text length caps. Next targeted backend rerun adds incremental boundary and real CSRF tests; final build required after hook change.
Latest backend: 8 tests/66 assertions passed, including real CSRF419 and incremental timestamp/private-note changes. Latest build passed2990 modules/12.86s, standard large-chunk warning. Frontend hook tests and route regression: 4 tests passed (3 Support state/race/idempotency +1 route). S3 count1: pre-test ledger append used resources/admin-relative docs path, failed; pre-existing run plan remains recorded, this corrected append records actual run result. S3 resolved by absolute ledger path. Frontend test command: npx vitest run src/integration/supportChat.test.tsx src/integration/adminRoutes.test.ts, 2.56s.
UI check found selection can retain a resolved thread in Pending after refresh; fixed selection to follow current Pending/Resolved filter using existing behavior. Final frontend test+build below is justified by this change; no backend changes since8/66 passed.

## Final evidence

- Local MySQL additive migration passed; database jw_ecommerce. Final counts: 1 real Guest conversation, 3 messages (2 customer/1 staff), 1 private staff note, status open after Guest reopened it. No dummy conversations loaded or imported; legacy Support localStorage ownership and mock mutation handlers removed from canonical Admin.
- Actual browser: empty queue before Guest send; Guest message displayed in Admin; staff reply received by storefront polling; storefront refresh preserved message history; Admin refresh preserved thread+notes; Resolve reflected publicly, Guest next message restored Pending. Note text absent from public DOM/API.
- Both final pages captured console errors=0. Mobile390x844 chat fills viewport with no document horizontal overflow. Temporary viewport override reset.
- Screenshots inspected: output/playwright/support-api-admin-guest.png and support-api-storefront-mobile.png. Latest browser Admin loads main-C3WWTpzy.js after final build.
- Latest backend tests8/66 assertions and frontend tests4 passed; final build2990 modules13.66s. git diff --check on scoped source passed (line-ending notices only). Unexpected issues S1/S2/S3 resolved with cumulative count1 each; selection mismatch resolved by following current filter, no test failure. Historical sample404 remains unrelated.
- New source scope: Support migration/models/service/controllers, routes (preserve unrelated inventory route additions), shared chat Blade and layout, Admin Support context/hook/components, focused tests and this record. Existing inventory modifications preserved. Initial unrelated-file hashes unavailable; no claims of reconstructed hashes.
- Guest identity is server-owned; separate guest/member ownership, CSRF419, authorization401, max2000, idempotent message UUID, transactional appends/read watermarks covered by tests. Browser member sign-in and multiple real browser profiles not run; guest roundtrip is the verified browser acceptance.
- Known operational limits: guest access lasts for its browser session; no login migration of guest history. Polling every4s on visible/open pages. Initial history loads complete conversation history; pagination/long-retention archival is future scaling work. AI/social/media excluded, no fake replies. No push/deploy.
- Elapsed time/token usage unavailable except measured individual runs above.
C:\xampp\htdocs\jwstudio-ecommerce\app\Services\Support\SupportChatService.php 02370F18BAE4347946E245E87E77433B194ED4FF7E0D39621D557A85D2303C63
C:\xampp\htdocs\jwstudio-ecommerce\resources\views\components\storefront\support-chat.blade.php 4D607D889001A6105A4211BB4C0A22725DF09097C7C8D54CA7C0B2CC086EE955
C:\xampp\htdocs\jwstudio-ecommerce\resources\admin\src\integration\supportChat.ts B76B565F12762176C26DED703DCB0EC1F84A23E750AA1118017EBEFE1B21DB89
C:\xampp\htdocs\jwstudio-ecommerce\public\build-admin\manifest.json CBC1FAFFB00A314FFDBE2DABEAE1D2C5F3C94FEDAEC72EC1AEF9CBC6DE9F4BEF
Release approved by user 2026-10-02. Baseline local/GitHub/Hostinger=2dad160, remote tracked clean, production environment verified via non-secret fields. Prepared clean release package from origin/main, overlaid21 Support source/evidence files, routes include only7 Support API lines. Pre-run release validation: package backend8 tests, frontend4 tests+scoped build; no production migration until exact commit pushed. Existing S1-S3 remain resolved count1.
Release package checks passed: backend8/66 assertions3.28s, frontend4 tests2.05s, scoped build2986 modules11.98s. Build inventory runtime=Dht4mKoi excludes unpublished inventory work; main=main-3OGmN_Vl. Previous combined local build saved in tmp/support-chat-pre-release-build; scoped build copied to canonical localhost for exact-package UI check before commit.
Exact release local browser verified main-3OGmN_Vl.js and real Guest thread/staff replies. Concurrent user testing added another local staff message; this is real local data, not copied to production. Production authenticated Admin tab remains available for read-only post-deploy smoke. Production test-message sends deferred; release smoke will not inject customer chats.
