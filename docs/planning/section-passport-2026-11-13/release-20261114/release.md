# Approved Passport release — 14 November 2026

Explicit user approval: synchronize the accepted Page Builder work to GitHub main and Hostinger www + ecom.

Release base: a30a6b5c8b7367212ca2f34434a1f9d3e97b65d8. Independent release checkout applies only the recorded passport allowlist; original canonical checkout and unrelated work remain intact. Scope: unified Eyebrow/Passport; deduplicated Page Settings and Section Design; capability-gated spacing/alignment; text/action color ownership and effective Size/Corners/Hover. No page/database migration, QA draft publication, business operation, environment change or unrelated module release.

Validation of combined release: production Admin/public/SSR build passed; 9 frontend suites /25 tests passed on first run, Eyebrow separately8 passed after creating its output directory. All161 Inspector sweep and frozen default parity passed. PHP97 tests/670 assertions passed after adding an ignored isolated test-only environment; initial missing-key and output-directory environment failures are recorded in checkpoint, resolved without product changes. SQLite memory guard retained. Exact API reload render comparison follows in reload.log. Whole-repository TypeScript remains a documented prior limitation, not claimed green.

Targets verified before update: both tracked-clean, a30a6b5c. www app /home/u762348670/apps/jw-corporate, separate public_html with install-passport-public-20261114.php backing up overwritten manifest/resources and writing manifest last. ecom app /home/u762348670/apps/jwecommerce with public-root symlink. Both use existing read-only SSH Deploy Keys. No server credentials copied into source.

Deployment: push release HEAD to main; each target git pull --ff-only origin main; install www bundles; php artisan optimize:clear; compare local release/GitHub/both target SHAs; verify live HTTPS HTML and referenced asset hashes. Signed-in Inspector UI acceptance reported separately if login is required. This document records approved release scope; actual final SHA/HTTP evidence is stored in the local delivery record.
