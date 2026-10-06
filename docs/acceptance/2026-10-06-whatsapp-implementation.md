# WhatsApp implementation ledger

Plan: docs/superpowers/plans/2026-10-06-whatsapp-hub.md
User approved design and inline plan execution on 2026-10-06.
Ecommerce baseline: 0fda1b9c93461f7ff2a18ec4ed42938495d32b80.
Hub baseline: 9c66008b7751a1dcc140c957cf824c25b2b6b8d2.
Existing dirty changes preserved; baseline inventory in .superpowers/sdd/2026-10-06-whatsapp-hub/baseline.txt.

Ruling: Work in canonical checkout as explicitly required by user delivery contract; do not relocate existing uncommitted TikTok work. No commit/push/deploy in implementation phase.
Pre-flight: Task 1 phone/WABA identity feeds Task 3 routing and Task 4 sends; both must bind the same owning app and merchant. Task 2 native signup requires actual provider configuration and cannot use bootstrap as OAuth evidence.
Provider gate: Embedded Signup configuration and live sender token ownership remain unverified. No production credential imported and no callback changed.
Read-only discovery: two guessed file names absent; corrected using rg file inventory. No test runs or unexpected test failures yet.
Elapsed time/token usage: unavailable.

## Task 1
Intentional initial TDD red: WhatsAppAccountTest will prove missing adapter/schema behavior. Fake HTTP proves guards only, not live provider readiness.
Initial red setup hit absent planned migration before adapter assertion; test setup now applies that migration only when present. Initial red is repeated to prove absent service (not a blind retry).
2026-10-06 Task1 green attempt: 2 passed / 2 failed; unexpected failure count 1 for fixture override issue. Laravel Http::fake appends callbacks; setup wildcard consumed later mismatched app/phone fixtures. Reset Factory in those tests; implementation unchanged. Next focused run verifies real negative fixtures.
Provider read-only check: existing token valid, owning App 1280202060726106, required messaging/management scopes granted, configured phone belongs to reference WABA. Native signup config absent. No import or cutover. Hub test generation produced extra closing bracket; initial run was a parse failure, not a valid TDD red. Erroneously proceeded with implementation and reran unchanged syntax: cumulative issue count 2; correcting fixture before any further run. This test sequence does not meet RED-GREEN gate yet.
Hub fixture parse issue resolved after correcting extra bracket. Next valid run proves separate missing SocialPhaseOne allowlist (both routes 503); allowlist updated, next run verifies signed relay. Fixture syntax count remains 2; missing allowlist count 1. Task1 current 4 tests/11 assertions pass. Command added but command test pending. Provider/signup independently blocked on missing config, not missing sender credentials.
Task3/4 intentional initial TDD red: php artisan test --filter=WhatsAppSupportTest; missing dedicated inbound/reply adapters. Hub current 2 tests/14 assertions pass; full security routing tests pending.
Task2 management intentional red: safe account summaries, unavailable OAuth, staff forbidden; support initial 5/18 pass. Run focused added tests before management implementation.
Task4 shared-support integration intentional red: adminView reply window and WhatsApp identity plus shared FAQ one-send. Run added tests; prior 7/26 pass. Frontend red corrected working directory resources/admin, unavailable page import verified.
UI integration script stopped on Windows default cp1252 reading UTF-8 source; encoding issue count 1. Corrected explicit UTF-8 and idempotent replacement before continuing. WhatsApp page 15 frontend tests pass. Backend regression in progress.
Combined regression 47 passed/7 failed, new order-dependent account fixture issue count 1. Individual tests previously green; added non-secret persistence assertions to identify differing fields before rerun. Frontend 54 tests pass; build passed with existing bundle-size warning.
Order-dependent issue count 2: diagnostics confirm only WABA metadata silently omitted. Laravel GuardsAttributes caches table columns from preceding Facebook tests before WhatsApp migration. Verified whitelist now forceFill on server-validated import; fixture uses explicit unguarded known fields like existing tests. No request data mass assignment. Next combined run verifies cache-independent persistence.
Final reviewer status findings enter one RED-GREEN repair pass: callback before API response lost, and sent->failed equal rank ignored. New regression tests intentionally red before code fix; existing combined 54/324 now green, schema-cache issue resolved.

Status review repair: durable account-scoped receipts and reconciliation after provider ID binding; explicit sent-to-failed progression. Intentional red: 2 failures. Next run validates changed implementation; elapsed/token usage unavailable.

Native provider initial intentional red: verifies token-owned WABA phone discovery and owning-app rejection before implementation. Existing live configuration 1496764641846624 inspected: WhatsApp Embedded Signup, Cloud API, system-user never-expiring token, WhatsApp assets. No external save.

Subscription initial intentional red: require owning-app WABA subscription confirmation before readiness. Status green 11/46. Native provider green 2/3; native controller and local acceptance pending.

Subscription green fixture failure count 1: test account lacked management permission and fake URL omitted query wildcard. Correct fixture before rerun; implementation preserves required permissions.

Native relay validation: newly wired SDK form, session-bound callback, signed redemption/selection, verified WABA routing. This is post-implementation validation, not an initial TDD red. Run once to expose integration gaps.

Hub signup uses existing LoginPage utility patterns and dedicated CSS entry; build validates actual assets. No provider settings changed.

Regression run purpose: native controller and subscription changes, durable receipt schema, shared support. Subscription fixture resolved 1/3; no remaining unexpected failure in that issue.

Safety evidence expansion per review: actual ConnectionException one-attempt behavior, takeover/new turn cancellation, ambiguous route and preflight invalid batch. Existing code unchanged; validation run.

Ecom native selection validation with signed-Hub mock plus real adapter token verification and owning-app subscription fixture. Local launch remains disabled until deployment routing-ready gate; no fake OAuth activation.

Local setup: copy approved reference credentials to untracked private environment files, keep both OAuth/messaging routing gates false. Import sender locally through owning-app/WABA validation only. No Meta subscription/callback write; production unchanged.

Final frontend regression and build after native-copy correction. Source UI reused; no layout redesign.

Final backend regression after reauthorization clears inbound verification; tests include native select, status races, transport timeout, AI control changes. Run purpose includes shared FB/IG regression.

Attention: cached guardable-column issue recurred (cumulative 3, historical 2 retained). Import test schema omitted newly referenced inbound fields; explicit trusted connection_method update now forceFill, fresh model passed to subscription after encrypted-token save. Next combined run only after concrete schema/write fixes; no blind rerun.

Mixed tenant safety validation: one provider batch split to exact phone owners with signed response verification. No source behavior change; validates existing preflight dispatcher.

Latest combined regression 60 passed/344 assertions; model cache issue count 3 resolved. Local MySQL receipt schema already applied before adding query index; apply only missing non-destructive index locally, not rollback table/data. Account summary now reports actual safe permissions/routing fields and failed check invalidates readiness; next focused regression validates changes.

2026-10-06T11:05:36.2891867+08:00 Final closure validation: Ecom full chosen regression after safe summary/check invalidation; Hub import wrong-phone/secret retention validation plus full selected Hub regression. No changed build inputs since successful build; elapsed/token usage unavailable.

Phone registration safety gate intentional red: a subscribed WABA alone must not make a pending/unregistered phone Ready. Read-only reference phone query confirms CONNECTED/CLOUD_API; existing number will not be re-registered or PIN changed. New-number registration remains user-controlled in Meta; unknown/pending phone blocks readiness.

Phone registration green validation after CONNECTED/CLOUD_API guard and healthy fixtures updated. Pending phone fixture unchanged. Command: php artisan test --filter=WhatsAppSupportTest.

Final UI validation after staff check menu guard and WhatsApp inbox icon alignment. Run focused regression including existing TikTok prototype to identify preserved baseline compatibility, then build.

Final chosen backend suite after registration guard: cannot mark pending phone ready. New guard and fixture changes justify this run; no additional unchanged retries.

2026-10-06T11:13 local checkpoint: final Ecom 61/347, Hub 17/144, frontend 55; both builds pass. Source secret scan 0/69. Actual localhost check/details/light-dark confirmed. Existing number CONNECTED/CLOUD_API read-only confirmed; no production or provider writes. Native signup and real messaging still pending hosted acceptance. Elapsed time/token usage unavailable. See 2026-10-06-whatsapp-local-checkpoint.md for release gates.
