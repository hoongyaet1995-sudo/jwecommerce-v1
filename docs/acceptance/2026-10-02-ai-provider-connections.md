# AI provider connections

Mode: REAL_DATA_BINDING. Scope: existing AI Connect UI -> owner-only Laravel APIs -> encrypted MySQL provider records -> real model listing and explicit small text test -> internal shared text service. Support auto-reply and CMS generation are not wired in this phase. No release authorization.

Baseline: main 6c569270fd9fe7ac3f4da56d4278c4899ab3840c, Integration UI and unrelated inventory dirty work preserved.

| Capability | Gate |
| --- | --- |
| OpenAI, Gemini, Anthropic connection credentials/model listing/text adapters | BACKEND_CONTRACT_GAP pending tests and user key verification |
| OpenAI-compatible | Allowlisted HTTPS endpoints only; DNS public-address check and curl pin; no arbitrary server URL |
| Secrets | Laravel encrypted cast, hidden serialization, explicit DTO, no raw failure body or chained HTTP exceptions, no request flashing |
| Authorization | Store owner (is_super_admin) only, web auth/admin, CSRF and throttling |
| State | Separate record per provider; one enabled connection; version conflict rejects stale saves/verification; credential change invalidates proof |
| Model availability | Provider model list proves authentication; explicit text test proves selected model can generate. Model lists alone do not prove generation capability/billing. |
| Future consumers | Internal generateText requires enabled connection, feature flag, verified key and text test. No public generation endpoint. Image selection prepared; image generation consumer not implemented. |

Reference documentation: https://developers.openai.com/api/reference/resources/models/methods/list ; https://platform.openai.com/docs/api-reference/responses ; https://ai.google.dev/api/models ; https://ai.google.dev/api/generate-content ; https://platform.claude.com/docs/en/api/models/list ; https://platform.claude.com/docs/en/api/messages/create .

## Durable issue and run ledger

- Initial file lookup used two nonexistent test/helper paths; actual SupportChatApiTest identified and its explicit SQLite isolation reused. No test run occurred during lookup. Lookup resolved, count 1 per path.
- Test/build runs and results will be recorded below. Elapsed/token accounting unavailable unless tool-reported.

- 2026-10-02T22:54:49.7079514+08:00: first targeted API test run. New service/adapters/controller/UI implemented. Tests explicitly purge/set SQLite :memory: and create only users plus scoped AI tables; no RefreshDatabase or business DB migrations. Purpose: encryption, authorization, request contracts, feature gating, version conflicts, provider errors, compatible SSRF guards.

- First result: 9 passed / 1 failure, 82 assertions. Issue T1: Gemini model-list wildcard fixture intercepted generation URL, producing non-text fixture. Unexpected count 1, resolved by matching generateContent before list wildcard. Compatible endpoint credential check also tightened to reject user OR password.
- 2026-10-02T22:55:21.8347675+08:00: rerun targeted API suite after concrete fixture fix; frontend build and route tests in parallel workstream.

- Second result: 9 passed / 1 failure (429), 86 assertions. T1 fixture correction passed its prior failure point. Distinct issue T2: numeric throttle middleware shares owner signature across save/verify/providers. Count 1, corrected to named per-owner/provider limiters with separate save/check buckets; regression added. Frontend route tests 2/2 and build passed (10.49s), existing chunk warning.
- 2026-10-02T22:56:33.8865604+08:00: targeted API suite after named throttling fix; new regression verifies saves do not consume provider checks.

- Third run passed 11 tests / 104 assertions, 1.075s; T1 and T2 resolved. Scoped local migration SQL preview creates only two AI tables. Current local .env verified APP_ENV local, APP_URL localhost:8500, MySQL jw_ecommerce. No production environment touched.
- 2026-10-02T22:57:12.6963583+08:00: apply only new AI migration locally for authenticated browser acceptance.

- Browser save/refresh persisted Support preference without any key. Attempting enable without verified/tested model was rejected. Browser summary locator initially matched sidebar and summary (B1, count 1, resolved by accessible name). No provider credential entered and no provider request made in browser acceptance.
- Review tightened custom destination guards for CGNAT, benchmarking, documentation and multicast ranges; regression added. Native pagination tests and frontend credential API boundary tests added.
- 2026-10-02T22:59:32.2898134+08:00: scoped API+existing Support regression suite and frontend API/route/Support tests after concrete guard/test additions.

- Final automated checks: PHP AI+Support suites 21 tests / 183 assertions passed (1.752s). Frontend AI/route/Support suites 9 tests passed (1.75s). All provider calls in tests were controlled fixtures; these are adapter/guard evidence, not real account acceptance.
- Unauthenticated OpenAI HTTPS preflight returned expected 401, proving local TLS/network reachability without transmitting a key. Local database contains one saved preference record and zero stored keys.
- Browser: each provider switches independently, saved preferences survive refresh, missing verification blocks enabling, compatible allowlist shows no endpoints until configured. Desktop light/dark and mobile 390x844 reviewed; document width equals viewport, no horizontal overflow; viewport reset.
- Credential/model/text test proof remains PARTIAL pending the user's real provider key. Support/CMS consumers remain deliberately unconnected. No GitHub push or Hostinger deployment performed.
- Screenshots: output/playwright/ai-provider-connect-dark.png, ai-provider-connect-light.png, ai-provider-connect-mobile.png.
- Operational setup and future consumer boundary: docs/contracts/ai-provider-connections.md.

- Final formatting: Pint applied only new AI files and its test; unrelated files were excluded. Syntax check below verifies formatted PHP; no unchanged blind test reruns. T1/T2 unexpected failure counts remain 1 each and resolved; B1 selector mismatch resolved. Historical initial path lookup accounting is incomplete beyond recorded entries.

- U1: user screenshot showed entered-but-unsaved key with Verify disabled. Root cause: verification required clean persisted state but UI did not offer a direct save-and-verify path. Implemented explicit Save & Verify Models, sequential save -> new version -> verify, keeps enable false, masks/clears key only after confirmed save, preserves input on save failure. Existing saved-key verification remains available. No user credential inspected or submitted by agent.
- 2026-10-02T23:08:39.8049330+08:00: targeted frontend regression and build after U1 change.

- U1 regression suite passed 6/6 tests. New tests prove sequential save/verify with the returned version, no automatic enable, no verify or input-clearing callback after a failed save, and direct verification of existing saved credentials.

- U1 browser acceptance: independent authenticated tab with a nonsensitive fixture string showed enabled Save & Verify Models immediately after filling the key. No save/verify action executed; fixture cleared and tab closed. Original user tab/key untouched. Evidence output/playwright/ai-save-verify-button-fixed.png. Build passed 9.72s, prior chunk-size warning only. Fix remains local.

- U2: user requested three recommended text/image models and clarified Test Text purpose. Added account-gated OpenAI 5.6 Luna/Terra/Sol recommendations and Image 2/2.5 Flare/2.5 Sunburst labels based on official model docs; image labels describe capability rather than unsupported relative-price claims. Selected model reply test now automatically saves pending preferences with Enable off and uses returned version. Disabled reason shown next to button. Short 5.6 diagnostic requests set supported reasoning effort none to avoid spending a 256-token test budget on reasoning; normal consumer defaults unchanged.
- 2026-10-02T23:22:09.2691841+08:00: targeted PHP AI suite and frontend flow/recommendation tests; frontend build after U2 modifications.

- U2 PHP suite passed 14/14 tests, 122 assertions (1.223s). Frontend tests passed 9/9 (1.39s), including availability intersection and auto-save model-test sequencing. No unexpected failure during U2.

- User steering: remove Test Model Reply entirely; no mandatory model test. Removed button, summary test status, test endpoint/controller branch, diagnostic-generation path, and text-test gate in enable/generateText. Verification is model-list only. Existing nullable text_tested_at schema retained inert to avoid destructive schema changes. No real diagnostic generation executed.
- U2 frontend second run passed 9/9, build 9.67s. Ledger append in resources/admin used relative path (documentation-path issue recurred, count 2; corrected here; use absolute ledger paths for subsequent app-subdirectory commands).
- 2026-10-02T23:25:59.7364521+08:00: targeted PHP suite and frontend tests/build after explicit test-step removal.

- Final removal verification: PHP 13 tests / 110 assertions passed (1.168s); frontend 8/8 passed; build passed (9.66s). Browser confirms zero Test buttons and three recommended text/image options. Removed one remaining summary reference to text testing; rebuild follows. No real generation request executed. Current contract supersedes earlier test-step descriptions in this historical ledger.
- 2026-10-02T23:29:13.2503995+08:00: rebuild after summary copy correction; purpose remove stale test wording; code change summary text only.

- Copy-only rebuild passed 10.68s. Local browser screenshot output/playwright/ai-connect-no-test.png confirms no test action and preserved model selection. Existing unrelated Customers Unavailable toast observed; no customer module changes made. Scope remains local, no push/deployment.
