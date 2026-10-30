# Email pilot release — 2026-10-30

User approved synchronization and online Email Settings configuration. Scope: local verified Segment batches, owner SMTP settings, 1,000 rolling mailbox quota, DSN bounce intake. Existing Hub/account records preserved; no real broadcast or email is authorized by this configuration step.

Baseline: b7616349b436fea24a9be7029cfe0a5937d68f39 (canonical/release/Hostinger). Unrelated dirty Inventory/Storefront/Admin files excluded. Three additive migrations160000/170000/180000 plus Webklex6.2.0 dependency installation required.

Controls: deploy exact locally verified commit; enable authentication check only after deployment. Bulk stays false. Existing server SMTP credentials reused internally, never printed or copied from local. Price remains unconfirmed unless evidence supports it. No new paid route or quota increase.

Security review: Composer10 advisories in7 existing packages are baseline issues, not introduced package updates; record exact affected versions before release. Newly added IMAP path exposes no anonymous input parser, limits message/UID sizes, uses owner permission, fixed public host, certificate TLS and signed DSN correlation. Do not claim dependency audit clean. Separate dependency security phase remains required.

Verification ledger: isolated source/vendor tests, dependency difference check, then canonical exact-source commit/push, Hostinger ff-only pull/composer/migrations/clear, exactSHA and read-only HTTPS/authenticated page evidence. Results appended below. Token use unavailable.

Preflight: source copy and dependency install completed. Diagnostic lockfile read initially failed due Windows default cp1252 (issue1); corrected explicit UTF-8, source bytes unchanged. Only added Webklex6.2.0. Existing dependency versions unchanged; advisories retained as documented. Next isolated fake-provider suite uses exact copied source, no network/mail.

Pre-commit gates: isolated exact release source passed99 tests/675 assertions (13.360s); JS syntax and scoped whitespace checks passed. TokenGuard critical-advisory applicability reviewed: configured web/customer guards are session-based, no auth:api/TokenGuard/token-driver references found in app/routes/config. This is bounded applicability evidence, not a clean security audit; remaining baseline upgrades stay open.
Release excludes .env, acceptance output/synthetic data, local test key/vendor overrides and unrelated Inventory/Storefront/Admin changes. Production bulk false; connection check configuration only after release.

Online config gate: server-owned existing Hostinger credentials encrypted into new settings; bulk/bounce remain false, cost unknown. Normal UI connection check returned connection_failed (issue1). Before further attempt verified password equals existing server config, public DNS2records, OpenSSL and requireTLS available. Next diagnostic classifies one connection exception without outputting its raw message or sending mail; not a blind repeat.

Root cause established: release manifest omitted existing local untracked MarketingSmtpProbe.php (name did not match Email-only discovery). No SMTP authentication was attempted by failed route. Classified issue1 release dependency omission; diagnostic failure confirms root cause. Add real-service file/DI assertion; intentional isolated red expected before copying missing service, then green.

Expected isolated red confirms missing file. Copied canonical SMTP probe unchanged into release; next fresh Settings/Email/Bounce suite checks restored service and existing behavior.

Restored-service gate:41 tests/240 assertions passed5.761s in isolated release source, including actual SMTP probe file/DI resolution. No provider request in tests. Follow-up release adds missing probe, regression assertion and evidence only; no credential changes.

Probe file omission fixed in6db96809. Next UI check still failed before authentication. Sanitized source/line established distinct issue2: settings transport emits scheme but real probe reads missing encryption. No credential rejection evidence. Add intentional red for actual saved SMTP transport -> real probe TLS construction (no network), then adapt boundary. First observed UI failure of this issue1; classified diagnostic calls do not resend mail or guess credentials.

Actual transport-construction red observed. Implemented strict scheme/port factory shared by real check, accepting saved runtime scheme plus prior encryption shape, always requireTLS. Next isolated regression validates the real transport boundary without any SMTP network access.

Runtime-contract repair green:42 tests/244 assertions passed5.784s. Real SSL465 and STARTTLS587 transports constructed from saved settings without network. Release repair only, SMTP credentials unchanged.
