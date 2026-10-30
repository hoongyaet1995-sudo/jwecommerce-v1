# Email pilot release — 2026-10-30

User approved synchronization and online Email Settings configuration. Scope: local verified Segment batches, owner SMTP settings, 1,000 rolling mailbox quota, DSN bounce intake. Existing Hub/account records preserved; no real broadcast or email is authorized by this configuration step.

Baseline: b7616349b436fea24a9be7029cfe0a5937d68f39 (canonical/release/Hostinger). Unrelated dirty Inventory/Storefront/Admin files excluded. Three additive migrations160000/170000/180000 plus Webklex6.2.0 dependency installation required.

Controls: deploy exact locally verified commit; enable authentication check only after deployment. Bulk stays false. Existing server SMTP credentials reused internally, never printed or copied from local. Price remains unconfirmed unless evidence supports it. No new paid route or quota increase.

Security review: Composer10 advisories in7 existing packages are baseline issues, not introduced package updates; record exact affected versions before release. Newly added IMAP path exposes no anonymous input parser, limits message/UID sizes, uses owner permission, fixed public host, certificate TLS and signed DSN correlation. Do not claim dependency audit clean. Separate dependency security phase remains required.

Verification ledger: isolated source/vendor tests, dependency difference check, then canonical exact-source commit/push, Hostinger ff-only pull/composer/migrations/clear, exactSHA and read-only HTTPS/authenticated page evidence. Results appended below. Token use unavailable.

Preflight: source copy and dependency install completed. Diagnostic lockfile read initially failed due Windows default cp1252 (issue1); corrected explicit UTF-8, source bytes unchanged. Only added Webklex6.2.0. Existing dependency versions unchanged; advisories retained as documented. Next isolated fake-provider suite uses exact copied source, no network/mail.

Pre-commit gates: isolated exact release source passed99 tests/675 assertions (13.360s); JS syntax and scoped whitespace checks passed. TokenGuard critical-advisory applicability reviewed: configured web/customer guards are session-based, no auth:api/TokenGuard/token-driver references found in app/routes/config. This is bounded applicability evidence, not a clean security audit; remaining baseline upgrades stay open.
Release excludes .env, acceptance output/synthetic data, local test key/vendor overrides and unrelated Inventory/Storefront/Admin changes. Production bulk false; connection check configuration only after release.
