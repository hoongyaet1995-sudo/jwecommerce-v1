# Manual Refresh SEO release — 2026-10-18

User authorized commit/push/Hostinger deployment, preserving all unrelated dirty work. Subsequent instruction supersedes nightly policy: only manual Refresh SEO; no new cron or dedicated SEO queue service.

Scope: Search metadata foundation, sitemap/robots/canonical/noindex, CMS shared SSR, saved-provider Search permission, manual full-site snapshot+per-record browser advance/resume, per-field override locks and CMS draft-only results; remove legacy batch/task/individual AI UI, add Search light palette, fix390px CMS toolbar/menu and missing ArrowUpRight icon registration. Saving catalog/CMS content does not admit AI work. No daily task limit.

Implementation reuses durable task audit to track a manual run, but the page performs one entity per authenticated POST and continues until completed. Keep Search page open; returning to it resumes interrupted work. Resume SEO recovers request failures without creating another full-site run. No Search scheduled event, no nightly CLI command. Both previously created local Windows Search tasks removed. Hostinger has no new SEO cron.

Isolated release is based on848d89e and excludes Inventory/Fashion and all other unrelated work. Native worktree creation unavailable from projectless chat, so ignored Git worktree fallback used. Shared dependency paths require explicit isolated App/Tests/Database autoload plus APP_BASE_PATH for verification; no primary vendor changes.

Validation: primary manual Search20/244 passed15.10s; frontend9 passed2.17s. Fully isolated release93tests/786assertions:91passed,2existingCatalogstockassertions failed; the exact2were independently reproduced on untouched848d89e baseline (2tests/14assertions). They are not repaired or bundled. Isolated frontend9 passed2.16s; AdminSSR build13.82s androotVite4.54s passed with existingasset/chunkwarnings. Standalone releasebrowser8501 loads manual-only settings andcompletedrun, consolewarn/error[]. Previous local realrun1 updated product46/category18/collection11/page16, protectedQA1; new manual advance/resume covered byHTTPfixtureall4 andmountedbrowser-looptests, production actualrun tofollow.

Release artifact generation uses only scoped source, not dirty primary bundles. Encrypted provider keys, .env files, storage data and local QA output never enter commit. Production actual data/config is independent from local and is not copied. Deployment will migrate only four approvedSearch migrations and clear Laravel caches. Production Search permission/preferences can use existing verified provider without changing keys/other-featurepermissions; CMS publication remains manual.

Pending before closure: commit/push, sameSHAserverpull/migrations, productionpublicHTTPS/SSR/assets, realmanualglobalrun acrossall4, CMS publishedhash protection, browserUI ifproductionloginavailable. No overnight scheduler/worker is to be configured.
