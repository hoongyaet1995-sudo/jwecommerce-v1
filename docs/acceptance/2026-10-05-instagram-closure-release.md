# Instagram closure release

User approved synchronization on 2026-10-05. Baseline d71a5dd5a6c736a82315f298c17d4fcc660637ff. Scope: real Instagram channel readiness and combined Facebook/Instagram summary; account-specific callback/error copy. DOM and classes preserved. No Hub/backend/config/migration changes.

Source-first local verification: 35 frontend tests; 95 backend tests /656 assertions. Local authenticated browser verified overview -> channel picker -> Instagram management -> guide and notifications. Local database has no Instagram account; connected states verified through UI tests, not claimed as live provider evidence.

Release build produced from HEAD plus exactly the three changed frontend source/test files in tmp/instagram-closure-release-20261005. Scoped frontend tests: 35 passed. Build passed with existing chunk-size warning. Existing dirty inventory/docs/assets not part of release. Pending deployment and authenticated production check.
